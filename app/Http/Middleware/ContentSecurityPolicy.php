<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A second line of defence for the pages that print document and visitor text.
 * It also keeps images in a document from calling out to other servers. The Vite
 * dev server loads from another origin, so it runs without the policy.
 */
final readonly class ContentSecurityPolicy
{
    public function __construct(private Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->vite->isRunningHot()) {
            return $next($request);
        }

        $nonce = $this->vite->useCspNonce();
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
