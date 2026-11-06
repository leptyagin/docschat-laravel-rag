<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\AdminRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * An account without a password in the environment cannot log in. The owner's
 * password should be stored as a hash. The demo password is shown on the login
 * page, so it stays readable.
 */
final readonly class ConfiguredUserProvider implements UserProvider
{
    /**
     * @param  array<string, array{email: string|null, password: string|null}>  $accounts  Keyed by role.
     */
    public function __construct(
        private array $accounts,
        private Hasher $hasher,
    ) {}

    public function retrieveById($identifier): ?AdminUser
    {
        $role = AdminRole::tryFrom((string) $identifier);

        return $role === null ? null : $this->userFor($role);
    }

    public function retrieveByToken($identifier, $token): ?AdminUser
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    /**
     * An unknown address takes as long as a known one, because checking a stored
     * hash is slow, and the difference would reveal the address of the owner.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?AdminUser
    {
        foreach (AdminRole::cases() as $role) {
            $user = $this->userFor($role);

            if ($user !== null && hash_equals(mb_strtolower($user->email), mb_strtolower((string) ($credentials['email'] ?? '')))) {
                return $user;
            }
        }

        $this->hasher->make((string) ($credentials['password'] ?? ''));

        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $stored = $user->getAuthPassword();
        $given = (string) ($credentials['password'] ?? '');

        return $this->hasher->info($stored)['algo'] === null
            ? hash_equals($stored, $given)
            : $this->hasher->check($given, $stored);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}

    private function userFor(AdminRole $role): ?AdminUser
    {
        $account = $this->accounts[$role->value] ?? null;

        if (blank($account['email'] ?? null) || blank($account['password'] ?? null)) {
            return null;
        }

        return new AdminUser($role, (string) $account['email'], (string) $account['password']);
    }
}
