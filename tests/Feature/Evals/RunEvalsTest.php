<?php

declare(strict_types=1);

use App\Ai\Contracts\ChatModel;
use App\Ai\Fakes\FakeChatModel;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Services\Evals\EvalRunner;
use App\Services\Evals\EvalSuite;
use Database\Seeders\SeabreezeSeeder;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

function evalBot(string ...$answers): Bot
{
    app()->instance(ChatModel::class, new FakeChatModel()->respondWith(...$answers));
    fakeEmbeddings()->returnVector(array_pad([1.0], 1536, 0.0));

    $bot = Bot::factory()->create(['slug' => 'seabreeze']);

    Chunk::factory()
        ->for(Document::factory()->for($bot)->state(['title' => 'Pets Policy']))
        ->for($bot)
        ->create(['embedding' => array_pad([1.0], 1536, 0.0)]);

    return $bot;
}

function evalFile(string $yaml): string
{
    $path = 'storage/framework/testing/questions-'.uniqid().'.yaml';

    File::ensureDirectoryExists(dirname(base_path($path)));
    File::put(base_path($path), $yaml);

    return $path;
}

it('scores source hits, facts and refusals', function (): void {
    $bot = evalBot('The fee is 40 dollars [1].', 'The fee is 99 dollars [1].', 'Sorry, I do not know.', 'It is Paris [1].');

    $yaml = <<<'YAML'
    - {question: Fee?, expected_document: Pets Policy, must_contain: ["40"]}
    - {question: Fee again?, expected_document: Pets Policy, must_contain: ["40"]}
    - {question: Unknown?, should_refuse: true}
    - {question: Capital?, should_refuse: true}
    YAML;

    $report = app(EvalRunner::class)->run($bot, app(EvalSuite::class)->load(base_path(evalFile($yaml))));

    expect($report->sourceHits())->toBe(2)
        ->and($report->keywordHits())->toBe(1)
        ->and($report->correctRefusals())->toBe(1)
        ->and($report->falseRefusals())->toBe(0)
        ->and($report->failures())->toHaveCount(2);
});

it('counts an unanswered question that should have been answered as a false refusal', function (): void {
    $bot = evalBot('I do not know.');

    $report = app(EvalRunner::class)->run($bot, app(EvalSuite::class)->load(base_path(evalFile(
        '- {question: Fee?, expected_document: Pets Policy}',
    ))));

    expect($report->falseRefusals())->toBe(1)
        ->and($report->sourceHits())->toBe(0);
});

it('uses the history of a follow-up question', function (): void {
    $bot = evalBot('Rewritten', 'Yes [1].');

    $yaml = <<<'YAML'
    - question: And there?
      history:
        - {role: user, content: Earlier question}
        - {role: assistant, content: Earlier answer}
      expected_document: Pets Policy
    YAML;

    app(EvalRunner::class)->run($bot, app(EvalSuite::class)->load(base_path(evalFile($yaml))));

    /** @var FakeChatModel $chat */
    $chat = app(ChatModel::class);

    expect($chat->requests[0]->prompt)->toContain('Visitor: Earlier question');
});

it('leaves no conversations behind', function (): void {
    $bot = evalBot('Yes [1].');

    app(EvalRunner::class)->run($bot, app(EvalSuite::class)->load(base_path(evalFile(
        '- {question: Fee?, expected_document: Pets Policy}',
    ))));

    expect(Conversation::query()->count())->toBe(0);
});

it('prints a summary and saves a JSON report', function (): void {
    evalBot('Yes [1].');
    $before = File::glob(storage_path('evals/*.json'));

    artisan('docschat:eval', ['--questions' => evalFile('- {question: Fee?, expected_document: Pets Policy}')])
        ->expectsOutputToContain('fake driver')
        ->expectsOutputToContain('1/1 (100%)')
        ->assertSuccessful();

    $created = array_values(array_diff(File::glob(storage_path('evals/*.json')), $before));

    expect($created)->toHaveCount(1)
        ->and(json_decode(File::get($created[0]), true)['summary']['source_hits'])->toBe(1);

    File::delete($created);
});

it('fails for an unknown bot', function (): void {
    artisan('docschat:eval', ['--bot' => 'ghost'])
        ->expectsOutputToContain('No bot with the slug [ghost]')
        ->assertFailed();
});

it('rejects an entry that states neither an expected document nor a refusal', function (): void {
    app(EvalSuite::class)->load(base_path(evalFile('- {question: Fee?}')));
})->throws(InvalidArgumentException::class);

it('rejects an entry that states both an expected document and a refusal', function (): void {
    app(EvalSuite::class)->load(base_path(evalFile('- {question: Fee?, expected_document: X, should_refuse: true}')));
})->throws(InvalidArgumentException::class);

test('the shipped questions point at documents that exist', function (): void {
    $questions = app(EvalSuite::class)->load(base_path('tests/evals/questions.yaml'));
    $titles = array_values(SeabreezeSeeder::DOCUMENTS);

    expect(count($questions))->toBeGreaterThanOrEqual(30);

    foreach ($questions as $question) {
        expect($question->shouldRefuse || in_array($question->expectedDocument, $titles, true))->toBeTrue();
    }
});
