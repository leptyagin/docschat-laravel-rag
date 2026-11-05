<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\AdminRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * An account without a password in the environment cannot log in.
 */
final readonly class ConfiguredUserProvider implements UserProvider
{
    /**
     * @param  array<string, array{email: string|null, password: string|null}>  $accounts  Keyed by role.
     */
    public function __construct(private array $accounts) {}

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

        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return hash_equals($user->getAuthPassword(), (string) ($credentials['password'] ?? ''));
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
