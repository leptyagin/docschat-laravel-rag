<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;

#[Signature('docschat:hash-password')]
#[Description('Hash a password for ADMIN_PASSWORD, so that the environment does not hold it in plain text')]
final class HashAdminPasswordCommand extends Command
{
    private const int MIN_LENGTH = 16;

    public function handle(Hasher $hasher): int
    {
        $password = (string) $this->secret('The admin password');

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $this->components->error('Use at least '.self::MIN_LENGTH.' characters.');

            return self::FAILURE;
        }

        $this->line($hasher->make($password));

        return self::SUCCESS;
    }
}
