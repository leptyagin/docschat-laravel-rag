<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\AdminRole;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An account defined in the environment. The panel has exactly two, so a users
 * table would add registration concerns that this application does not have.
 */
final readonly class AdminUser implements Authenticatable
{
    public function __construct(
        public AdminRole $role,
        public string $email,
        private string $password,
    ) {}

    public function isReadOnly(): bool
    {
        return $this->role === AdminRole::Demo;
    }

    public function getAuthIdentifierName(): string
    {
        return 'role';
    }

    public function getAuthIdentifier(): string
    {
        return $this->role->value;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
