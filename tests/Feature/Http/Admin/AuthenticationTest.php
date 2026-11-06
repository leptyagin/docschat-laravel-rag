<?php

declare(strict_types=1);

use App\Auth\AdminUser;
use App\Auth\ConfiguredUserProvider;
use App\Enums\AdminRole;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

beforeEach(function (): void {
    config()->set('docschat.admin.accounts', [
        'admin' => ['email' => 'owner@example.com', 'password' => 'correct-horse'],
        'demo' => ['email' => 'demo@example.com', 'password' => 'demo'],
    ]);
});

it('logs in with the credentials from the environment', function (string $email, string $password, AdminRole $role): void {
    post(route('admin.login'), ['email' => $email, 'password' => $password])
        ->assertRedirect(route('admin.dashboard'));

    assertAuthenticated();

    /** @var AdminUser $user */
    $user = Auth::user();

    expect($user->role)->toBe($role);
})->with([
    'admin' => ['owner@example.com', 'correct-horse', AdminRole::Admin],
    'demo' => ['demo@example.com', 'demo', AdminRole::Demo],
]);

it('rejects a wrong password', function (): void {
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('rejects an account that has no password in the environment', function (): void {
    config()->set('docschat.admin.accounts.admin.password', null);

    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => ''])->assertSessionHasErrors();
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');

    assertGuest();
});

it('limits login attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'wrong']);
    }

    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'correct-horse'])->assertTooManyRequests();

    assertGuest();
});

it('counts login attempts for a whole IPv6 network', function (): void {
    config()->set('docschat.rate_limits.login_per_minute', 1);

    withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::1']);
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'wrong']);

    withServerVariables(['REMOTE_ADDR' => '2001:db8:1:ff00::2']);
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});

it('shows the demo credentials on the login page, and never the real ones', function (): void {
    get(route('admin.login'))
        ->assertOk()
        ->assertSee('demo@example.com')
        ->assertDontSee('owner@example.com')
        ->assertDontSee('correct-horse');
});

it('links the login page back to the landing page', function (): void {
    get(route('admin.login'))->assertSee('Back to the landing page')->assertSee('href="'.route('home').'"', escape: false);
});

it('logs out', function (): void {
    actingAs(new AdminUser(AdminRole::Admin, 'owner@example.com', 'x'));

    post(route('admin.logout'))->assertRedirect(route('admin.login'));

    assertGuest();
});

it('sends guests to the login page', function (string $route): void {
    get(route($route))->assertRedirect(route('admin.login'));
})->with(['admin.dashboard', 'admin.documents.index', 'admin.conversations.index', 'admin.unanswered', 'admin.settings.edit']);

it('allows only the real admin into Horizon', function (AdminRole $role, int $status): void {
    app()->detectEnvironment(fn (): string => 'production');

    actingAs(new AdminUser($role, 'someone@example.com', 'x'));

    get('/horizon')->assertStatus($status);
})->with([
    'admin' => [AdminRole::Admin, 200],
    'demo' => [AdminRole::Demo, 403],
]);

it('accepts the right password for a stored hash and nothing else', function (): void {
    config()->set('docschat.admin.accounts.admin.password', Hash::make('correct-horse-battery'));

    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => config('docschat.admin.accounts.admin.password')])
        ->assertSessionHasErrors('email');
    assertGuest();

    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('admin.dashboard'));
    assertAuthenticated();
});

it('limits attempts on one account from any number of networks', function (): void {
    config()->set('docschat.rate_limits.login_account_per_hour', 1);

    withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
    post(route('admin.login'), ['email' => 'Owner@Example.com', 'password' => 'wrong']);

    withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});

it('rejects a password that is not valid UTF-8', function (): void {
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => "\xFF\xFE"])->assertSessionHasErrors('password');
});

it('ends existing sessions when the password changes', function (): void {
    managedBot();
    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'correct-horse']);

    get(route('admin.dashboard'))->assertOk();

    config()->set('docschat.admin.accounts.admin.password', 'a-new-password');
    Auth::forgetGuards();

    get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('hashes a password for the environment', function (): void {
    artisan('docschat:hash-password')
        ->expectsQuestion('The admin password', 'correct-horse-battery')
        ->expectsOutputToContain('$2y$')
        ->assertSuccessful();
});

it('refuses to hash a short password', function (): void {
    artisan('docschat:hash-password')
        ->expectsQuestion('The admin password', 'short')
        ->assertFailed();
});

it('cannot be used to lock everybody out of the demo account', function (): void {
    config()->set('docschat.rate_limits.login_account_per_hour', 1);

    foreach (['203.0.113.7', '198.51.100.7', '192.0.2.7'] as $address) {
        withServerVariables(['REMOTE_ADDR' => $address]);
        post(route('admin.login'), ['email' => 'demo@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    post(route('admin.login'), ['email' => 'demo@example.com', 'password' => 'demo'])->assertRedirect(route('admin.dashboard'));
});

it('spends the same work on an unknown address as on a known one', function (): void {
    /** @var Hasher&MockInterface $hasher */
    $hasher = Mockery::mock(Hasher::class);
    $hasher->shouldReceive('make')->once()->with('wrong')->andReturn('hash');

    $provider = new ConfiguredUserProvider(config('docschat.admin.accounts'), $hasher);

    expect($provider->retrieveByCredentials(['email' => 'nobody@example.com', 'password' => 'wrong']))->toBeNull();
});

it('still logs in when no demo account is configured', function (): void {
    config()->set('docschat.admin.accounts.demo', ['email' => null, 'password' => null]);

    post(route('admin.login'), ['email' => 'owner@example.com', 'password' => 'correct-horse'])->assertRedirect(route('admin.dashboard'));
});
