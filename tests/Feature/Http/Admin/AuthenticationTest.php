<?php

declare(strict_types=1);

use App\Auth\AdminUser;
use App\Enums\AdminRole;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;
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
