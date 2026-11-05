<?php

declare(strict_types=1);

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\EmbeddingModel;
use App\Auth\AdminUser;
use App\Enums\AdminRole;
use App\Models\Bot;
use App\Services\Ingestion\Web\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScriptedChatModel;
use Tests\Support\ScriptedEmbeddingModel;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function fakeChat(string ...$answers): ScriptedChatModel
{
    $chat = new ScriptedChatModel()->respondWith(...$answers);

    app()->instance(ChatModel::class, $chat);

    return $chat;
}

function fakeEmbeddings(): ScriptedEmbeddingModel
{
    $embeddings = new ScriptedEmbeddingModel;

    app()->instance(EmbeddingModel::class, $embeddings);

    return $embeddings;
}

/**
 * Answers every DNS lookup with the given addresses.
 *
 * @param  list<string>  $addresses
 */
function fakeDns(array $addresses = ['93.184.216.34']): void
{
    app()->instance(HostResolver::class, new class($addresses) implements HostResolver
    {
        /**
         * @param  list<string>  $addresses
         */
        public function __construct(private array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    });
}

/**
 * Writes the text to a temporary Markdown file and returns its path.
 */
function markdownFile(string $contents, string $name = 'guide.md'): string
{
    $path = sys_get_temp_dir().'/docschat-tests/'.getmypid().'/'.$name;

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    file_put_contents($path, $contents);

    return $path;
}

function actingAsAdmin(): void
{
    actingAs(new AdminUser(AdminRole::Admin, 'owner@example.com', 'secret'));
}

function actingAsDemo(): void
{
    actingAs(new AdminUser(AdminRole::Demo, 'demo@example.com', 'demo'));
}

function managedBot(): Bot
{
    return Bot::factory()->create(['slug' => 'seabreeze']);
}
