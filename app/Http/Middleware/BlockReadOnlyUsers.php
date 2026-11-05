<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The buttons are disabled for the demo account too, but this is what enforces it.
 */
final class BlockReadOnlyUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof AdminUser && $user->isReadOnly()) {
            return back()->with('notice', 'This is a read-only demo, so changes are switched off. Everything else works as usual.');
        }

        return $next($request);
    }
}
