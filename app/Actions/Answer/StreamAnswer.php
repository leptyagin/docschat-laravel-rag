<?php

declare(strict_types=1);

namespace App\Actions\Answer;

use App\Ai\Contracts\ChatModel;
use App\Ai\Data\ChatRequest;
use App\Data\Answer\AnswerDraft;
use App\Data\Answer\AnswerEvent;
use App\Data\Answer\ModelUsage;
use App\Data\Answer\RetrievalResult;
use App\Data\Answer\Source;
use App\Enums\MessageRole;
use App\Enums\RefusalReason;
use App\Models\Bot;
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

    private const string RESTING = 'The demo is resting for today and will be back tomorrow. In the meantime, please contact %s.';

    public function __construct(
        private QueryRewriter $rewriter,
        private HybridRetriever $retriever,
        private ChatModel $chat,
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
     * @return Generator<int, AnswerEvent, mixed, Message>
     */
    public function handle(Conversation $conversation, string $question): Generator
    {
        $startedAt = hrtime(true);
        $history = $this->recentHistory($conversation);
        $userMessage = $conversation->messages()->create(['role' => MessageRole::User, 'content' => $question]);
        $draft = new AnswerDraft;

        yield AnswerEvent::started($this->tokens->issue($conversation));

        try {
            yield from $this->compose($draft, $conversation->bot, $userMessage, $history);
        } catch (Throwable $exception) {
            report($exception);

            $apology = sprintf(self::UNAVAILABLE, $conversation->bot->fallback_contact);

            $draft->refusalReason = RefusalReason::ModelError;
            $draft->text = trim($draft->text."\n\n".$apology);

            yield AnswerEvent::delta($apology);
        }

        $message = $this->store($conversation, $draft, $startedAt);

        yield AnswerEvent::completed($message, $draft->sources);

        return $message;
    }

    /**
     * @param  list<Message>  $history
     * @return Generator<int, AnswerEvent, mixed, void>
     */
    private function compose(AnswerDraft $draft, Bot $bot, Message $userMessage, array $history): Generator
    {
        if ($this->budget->isExhausted()) {
            $draft->refusalReason = RefusalReason::BudgetExceeded;
            $draft->text = sprintf(self::RESTING, $bot->fallback_contact);

            yield AnswerEvent::delta($draft->text);

            return;
        }

        $question = $userMessage->content;
        $rewritten = $this->rewriter->rewrite($question, $history);

        $userMessage->update(['rewritten_query' => $rewritten->query]);

        if ($rewritten->usage !== null) {
            $draft->chatUsages[] = $rewritten->usage;
        }

        $retrieval = $this->retriever->retrieve($bot, $rewritten->query);
        $draft->embeddingUsages[] = $retrieval->usage;

        if ($this->isBelowThreshold($retrieval)) {
            $draft->refusalReason = RefusalReason::LowSimilarity;
            $draft->text = sprintf(self::NOT_IN_DOCUMENTS, $bot->fallback_contact);

            yield AnswerEvent::delta($draft->text);

            return;
        }

        yield from $this->generate($draft, $this->prompts->build($bot, $question, $rewritten->query, $retrieval->chunks));

        $draft->sources = $this->citations->extract($draft->text, $retrieval->chunks, $bot);
        $draft->refusalReason = $draft->sources === [] ? RefusalReason::NoCitation : null;
    }

    /**
     * The provider bills a failed stream too, but only a finished one reports
     * its usage, so the spend of a failure is estimated to keep the budget honest.
     *
     * @return Generator<int, AnswerEvent, mixed, void>
     */
    private function generate(AnswerDraft $draft, ChatRequest $request): Generator
    {
        $stream = $this->chat->stream($request);

        try {
            foreach ($stream as $delta) {
                $draft->text .= $delta;

                yield AnswerEvent::delta($delta);
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
