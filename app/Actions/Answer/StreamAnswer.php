<?php

declare(strict_types=1);

namespace App\Actions\Answer;

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\ModerationModel;
use App\Ai\Data\ChatRequest;
use App\Data\Answer\AnswerDraft;
use App\Data\Answer\AnswerEvent;
use App\Data\Answer\ModelUsage;
use App\Data\Answer\RetrievalResult;
use App\Data\Answer\RetrievedChunk;
use App\Data\Answer\Source;
use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Answer\AnswerPromptBuilder;
use App\Services\Answer\CitationExtractor;
use App\Services\Answer\ConversationTokens;
use App\Services\Answer\CostCalculator;
use App\Services\Answer\DailyBudget;
use App\Services\Answer\HybridRetriever;
use App\Services\Answer\QueryRewriter;
use App\Services\Ingestion\Chunking\EstimatedTokenCounter;
use Generator;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class StreamAnswer
{
    private const string NOT_IN_DOCUMENTS = "I couldn't find that in our documents. Please contact %s and the team will be happy to help.";

    private const string UNAVAILABLE = "Sorry, I can't answer right now. Please try again in a moment, or contact %s.";

    private const string RESTING = 'The demo is resting for now and will be back soon. In the meantime, please contact %s.';

    public function __construct(
        private QueryRewriter $rewriter,
        private HybridRetriever $retriever,
        private ChatModel $chat,
        private ModerationModel $moderation,
        private AnswerPromptBuilder $prompts,
        private CitationExtractor $citations,
        private CostCalculator $costs,
        private DailyBudget $budget,
        private ConversationTokens $tokens,
        private EstimatedTokenCounter $tokenCounter,
        #[Config('docschat.retrieval.min_similarity')] private float $minSimilarity,
        #[Config('docschat.answers.history_messages')] private int $historyMessages,
    ) {}

    /**
     * Runs one question through the pipeline, yielding events as the answer
     * forms. The generator returns the stored assistant message.
     *
     * The share of the budget is held until the cost is stored, so that answers
     * running at the same moment cannot all pass the check and overspend together.
     *
     * @return Generator<int, AnswerEvent, mixed, Message>
     */
    public function handle(Conversation $conversation, string $question): Generator
    {
        $startedAt = hrtime(true);
        $history = $this->recentHistory($conversation);
        $userMessage = $conversation->messages()->create(['role' => MessageRole::User, 'content' => $question]);
        $draft = new AnswerDraft;

        yield AnswerEvent::started($this->tokens->issue($conversation));

        $reservation = $this->budget->tryReserve();

        try {
            try {
                yield from $reservation === null
                    ? $this->refuse($draft, RefusalReason::BudgetExceeded, sprintf(self::RESTING, $conversation->bot->fallback_contact))
                    : $this->compose($draft, $conversation, $userMessage, $history);
            } catch (Throwable $exception) {
                report($exception);

                $apology = sprintf(self::UNAVAILABLE, $conversation->bot->fallback_contact);

                $draft->refusalReason = RefusalReason::ModelError;
                $draft->text = trim($draft->text."\n\n".$apology);

                yield AnswerEvent::delta($apology);
            }

            $message = $this->store($conversation, $draft, $startedAt);
        } finally {
            if ($reservation !== null) {
                $this->budget->release($reservation);
            }
        }

        yield AnswerEvent::completed($message, $draft->sources);

        return $message;
    }

    /**
     * @param  list<Message>  $history
     * @return Generator<int, AnswerEvent, mixed, void>
     */
    private function compose(AnswerDraft $draft, Conversation $conversation, Message $userMessage, array $history): Generator
    {
        $bot = $conversation->bot;
        $question = $userMessage->content;

        if ($this->moderation->isFlagged($question)) {
            yield from $this->refuse($draft, RefusalReason::Flagged, sprintf(self::NOT_IN_DOCUMENTS, $bot->fallback_contact));

            return;
        }

        $rewritten = $this->rewriter->rewrite($question, $history, $conversation->visitor_hash);

        $userMessage->update(['rewritten_query' => $rewritten->query]);

        if ($rewritten->usage !== null) {
            $draft->chatUsages[] = $rewritten->usage;
        }

        $retrieval = $this->retriever->retrieve($bot, $rewritten->query);
        $draft->embeddingUsages[] = $retrieval->usage;

        if ($this->isBelowThreshold($retrieval)) {
            yield from $this->refuse($draft, RefusalReason::LowSimilarity, sprintf(self::NOT_IN_DOCUMENTS, $bot->fallback_contact));

            return;
        }

        $request = $this->prompts->build($bot, $question, $rewritten->query, $retrieval->chunks, $conversation->visitor_hash);

        yield from $this->generate($draft, $request, $retrieval->chunks);

        $draft->sources = $this->citations->extract($draft->text, $retrieval->chunks, $bot);

        if ($draft->sources === []) {
            yield from $this->refuse($draft, RefusalReason::NoCitation, sprintf(self::NOT_IN_DOCUMENTS, $bot->fallback_contact));
        }
    }

    /**
     * @return Generator<int, AnswerEvent, mixed, void>
     */
    private function refuse(AnswerDraft $draft, RefusalReason $reason, string $text): Generator
    {
        $draft->refusalReason = $reason;
        $draft->text = $text;

        yield AnswerEvent::delta($text);
    }

    /**
     * Nothing reaches the visitor until the text cites the context. The rules in
     * the prompt can be talked around, so this is what keeps text that is not
     * based on the documents, such as the result of a jailbreak, from being shown.
     *
     * The provider bills a failed stream too, but only a finished one reports
     * its usage, so the spend of a failure is estimated to keep the budget honest.
     *
     * @param  list<RetrievedChunk>  $chunks
     * @return Generator<int, AnswerEvent, mixed, void>
     */
    private function generate(AnswerDraft $draft, ChatRequest $request, array $chunks): Generator
    {
        $stream = $this->chat->stream($request);
        $isGrounded = false;

        try {
            foreach ($stream as $delta) {
                $draft->text .= $delta;

                if ($isGrounded) {
                    yield AnswerEvent::delta($delta);
                } elseif ($this->citations->citesContext($draft->text, count($chunks))) {
                    $isGrounded = true;

                    yield AnswerEvent::delta($draft->text);
                }
            }

            $response = $stream->getReturn();
        } catch (Throwable $exception) {
            $draft->chatUsages[] = $this->estimatedUsage($request, $draft->text);

            throw $exception;
        }

        $draft->model = $response->model;

        if ($response->promptTokens === 0) {
            Log::warning('The chat model reported no usage, so it is estimated.', ['model' => $response->model]);

            $draft->chatUsages[] = $this->estimatedUsage($request, $draft->text);

            return;
        }

        $draft->chatUsages[] = new ModelUsage($response->model, $response->promptTokens, $response->completionTokens);
    }

    private function estimatedUsage(ChatRequest $request, string $answer): ModelUsage
    {
        return new ModelUsage(
            $request->model ?? $this->chat->name(),
            $this->tokenCounter->count($request->systemPrompt.$request->prompt),
            $this->tokenCounter->count($answer),
        );
    }

    private function store(Conversation $conversation, AnswerDraft $draft, int $startedAt): Message
    {
        return $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'content' => $draft->text,
            'cited_chunk_ids' => array_map(fn (Source $source): int => $source->chunkId, $draft->sources),
            'sources' => array_map(fn (Source $source): array => $source->toSnapshot(), $draft->sources),
            'answered' => $draft->refusalReason === null,
            'refusal_reason' => $draft->refusalReason,
            'model' => $draft->model,
            'prompt_tokens' => $draft->promptTokens(),
            'completion_tokens' => $draft->completionTokens(),
            'cost_usd' => $this->costs->total($draft->usages()),
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }

    /**
     * @return list<Message> Oldest first.
     */
    private function recentHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->latest('id')
            ->limit($this->historyMessages)
            ->get(['id', 'conversation_id', 'role', 'content'])
            ->reverse()
            ->values()
            ->all();
    }

    private function isBelowThreshold(RetrievalResult $retrieval): bool
    {
        return $retrieval->bestSimilarity === null || $retrieval->bestSimilarity < $this->minSimilarity;
    }
}
