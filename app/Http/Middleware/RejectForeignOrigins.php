<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The chat runs on this origin, even inside the iframe on a customer's site, so a
 * script on another site has no reason to call the API. Refusing it here stops a
 * page from spending the budget through the browsers of its visitors, each with
 * its own limit. Some requests skip the CORS preflight, so CORS alone cannot.
 *
 * A client outside a browser sends no Origin, and only the rate limits apply to it.
 */
final class RejectForeignOrigins
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
            return new JsonResponse(['message' => 'This API can only be used from the chat itself.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
