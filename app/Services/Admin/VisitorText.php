<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\AdminUser;
use Illuminate\Auth\AuthManager;

/**
 * Anyone can open the read-only demo account, so it must not show contact details
 * or links that visitors typed. The owner sees the text as it was written.
 */
final readonly class VisitorText
{
    private const string HIDDEN = '[hidden]';

    private const array PERSONAL_DATA = [
        '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u',
        '/(?:https?:\/\/|www\.)\S+/iu',
        '/\+?\d(?:[\s().-]*\d){6,}/u',
    ];

    public function __construct(private AuthManager $auth) {}

    public function forViewer(string $text): string
    {
        $viewer = $this->auth->guard()->user();

        if ($viewer instanceof AdminUser && ! $viewer->isReadOnly()) {
            return $text;
        }

        return (string) preg_replace(self::PERSONAL_DATA, self::HIDDEN, $text);
    }
}
