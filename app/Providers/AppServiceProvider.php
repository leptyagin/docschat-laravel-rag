<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Fakes\FakeChatModel;
use App\Ai\Fakes\FakeEmbeddingModel;
use App\Ai\LaravelAiChatModel;
use App\Ai\LaravelAiEmbeddingModel;
use App\Services\Ingestion\Chunking\EstimatedTokenCounter;
use App\Services\Ingestion\Chunking\TokenCounter;
use App\Services\Ingestion\Web\DnsHostResolver;
use App\Services\Ingestion\Web\HostResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TokenCounter::class => EstimatedTokenCounter::class,
        HostResolver::class => DnsHostResolver::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $usesFakeModels = $this->app->make('config')->get('docschat.ai.driver') === 'fake';

        $this->app->scoped(ChatModel::class, $usesFakeModels ? FakeChatModel::class : LaravelAiChatModel::class);
        $this->app->scoped(EmbeddingModel::class, $usesFakeModels ? FakeEmbeddingModel::class : LaravelAiEmbeddingModel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
