<?php

declare(strict_types=1);

use App\Enums\MessageFeedback;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Answer\ConversationTokens;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;

function answerIn(Conversation $conversation): Message
{
    return Message::factory()->for($conversation)->create(['role' => MessageRole::Assistant]);
}

/**
 * @return TestResponse<Response>
 */
function rate(Message $message, string $feedback, ?string $token): TestResponse
{
    return postJson(
        route('api.v1.feedback.store', [$message->conversation->bot->public_key, $message]),
        ['feedback' => $feedback, 'conversation_token' => $token],
    );
}

it('records a rating from the visitor who holds the conversation', function (string $feedback, MessageFeedback $stored): void {
    $conversation = Conversation::factory()->create();
    $message = answerIn($conversation);

    rate($message, $feedback, app(ConversationTokens::class)->issue($conversation))->assertNoContent();

    expect($message->refresh()->feedback)->toBe($stored);
})->with([
    'up' => ['up', MessageFeedback::Up],
    'down' => ['down', MessageFeedback::Down],
]);

it('forbids rating an answer of another conversation with 403', function (): void {
    $message = answerIn(Conversation::factory()->create());

    rate($message, 'up', app(ConversationTokens::class)->issue(Conversation::factory()->create()))->assertForbidden();

    expect($message->refresh()->feedback)->toBeNull();
});

it('forbids rating without a valid token with 403', function (): void {
    rate(answerIn(Conversation::factory()->create()), 'up', 'forged')->assertForbidden();
});

it('forbids rating a question with 403', function (): void {
    $conversation = Conversation::factory()->create();
    $question = Message::factory()->for($conversation)->create();

    rate($question, 'up', app(ConversationTokens::class)->issue($conversation))->assertForbidden();
});

it('rejects an unknown rating with 422', function (): void {
    $conversation = Conversation::factory()->create();

    rate(answerIn($conversation), 'sideways', app(ConversationTokens::class)->issue($conversation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('feedback');
});
