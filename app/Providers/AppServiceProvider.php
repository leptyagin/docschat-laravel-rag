<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\Contracts\ChatModel;
use App\Ai\Contracts\EmbeddingModel;
use App\Ai\Fakes\FakeChatModel;
use App\Ai\Fakes\FakeEmbeddingModel;
use App\Ai\LaravelAiChatModel;
use App\Ai\LaravelAiEmbeddingModel;
use App\Auth\ConfiguredUserProvider;
use App\Models\Conversation;
use App\Models\Document;
use App\Services\Admin\ManagedBot;
use App\Services\Answer\VisitorHasher;
use App\Services\Ingestion\Web\DnsHostResolver;
use App\Services\Ingestion\Web\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        HostResolver::class => DnsHostResolver::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        [$chatModel, $embeddingModel] = $this->aiImplementations();

        $this->app->scoped(ChatModel::class, $chatModel);
        $this->app->scoped(EmbeddingModel::class, $embeddingModel);

        $this->ensureModelsArePriced();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
        $this->configureRateLimiting();
        $this->configureAuthentication();
        $this->configureRouteBindings();
    }

    /**
     * An unknown driver must stop the boot: falling through to the real models
     * could spend money by accident, and the fake models must never reach visitors.
     *
     * @return array{class-string<ChatModel>, class-string<EmbeddingModel>}
     */
    private function aiImplementations(): array
    {
        $driver = $this->app->make('config')->get('docschat.ai.driver');

        return match (true) {
            $driver === 'laravel-ai' => [LaravelAiChatModel::class, LaravelAiEmbeddingModel::class],
            $driver === 'fake' && ! $this->app->isProduction() => [FakeChatModel::class, FakeEmbeddingModel::class],
            default => throw new InvalidArgumentException("The AI driver [{$driver}] cannot be used here."),
        };
    }

    /**
     * The daily budget adds up what the models cost, so a model without a price
     * could spend without limit. Like an unknown driver, that must stop the boot.
     */
    private function ensureModelsArePriced(): void
    {
        $config = $this->app->make('config');

        if ($config->get('docschat.ai.driver') !== 'laravel-ai') {
            return;
        }

        $unpricedModels = array_diff(
            [$config->get('docschat.ai.chat.model'), $config->get('docschat.ai.chat.rewrite_model'), $config->get('docschat.ai.embeddings.model')],
            array_keys($config->get('docschat.prices')),
        );

        if ($unpricedModels !== []) {
            throw new InvalidArgumentException('Add a price to docschat.prices for: '.implode(', ', array_unique($unpricedModels)));
        }
    }

    private function configureTrustedProxies(): void
    {
        $proxies = (string) config('docschat.trusted_proxies');

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }

    /**
     * Limits follow the network of the visitor rather than the visitor hash,
     * because a client can change its user agent freely but not its network.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('chat', function (Request $request): array {
            $key = $this->networkKey($request);

            /** @param array<string, int|string> $headers */
            $tooManyRequests = fn (Request $request, array $headers): JsonResponse => new JsonResponse([
                'message' => "You've reached the message limit for this demo. Please come back a little later.",
            ], 429, $headers);

            return [
                Limit::perHour(config()->integer('docschat.rate_limits.per_hour'))->by("hour:{$key}")->response($tooManyRequests),
                Limit::perDay(config()->integer('docschat.rate_limits.per_day'))->by("day:{$key}")->response($tooManyRequests),
            ];
        });

        RateLimiter::for('feedback', fn (Request $request): Limit => $this->perMinuteLimit('feedback', $request));
        RateLimiter::for('documents', fn (Request $request): Limit => $this->perMinuteLimit('documents', $request));
        RateLimiter::for('login', fn (Request $request): Limit => $this->perMinuteLimit('login', $request));
    }

    private function perMinuteLimit(string $name, Request $request): Limit
    {
        return Limit::perMinute(config()->integer("docschat.rate_limits.{$name}_per_minute"))->by($this->networkKey($request));
    }

    private function networkKey(Request $request): string
    {
        return $this->app->make(VisitorHasher::class)->hashNetwork($request->ip());
    }

    private function configureAuthentication(): void
    {
        Auth::provider('configured', fn (Application $app): ConfiguredUserProvider => new ConfiguredUserProvider(
            $app->make('config')->get('docschat.admin.accounts'),
        ));
    }

    /**
     * The panel can only reach records of the bot it manages.
     */
    private function configureRouteBindings(): void
    {
        Route::bind('managedDocument', fn (string $id): Document => $this->app->make(ManagedBot::class)->get()->documents()->findOrFail($id));
        Route::bind('managedConversation', fn (string $id): Conversation => $this->app->make(ManagedBot::class)->get()->conversations()->findOrFail($id));
    }
}
