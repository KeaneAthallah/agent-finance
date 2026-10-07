<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (spec §35): applied to every web response.
 * The CSP is strict by default; local development loosens
 * script-src for the Vite HMR runtime.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        \assert($response instanceof Response);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
        );
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()',
        );
        $response->headers->set(
            'Content-Security-Policy',
            $this->contentSecurityPolicy(),
        );

        return $response;
    }

    /**
     * Strict default policy. Local development runs against
     * the Vite dev server on arbitrary loopback hosts and
     * ports — including IPv6 [::1], for which CSP has no
     * portable any-port wildcard — so dev gets a permissive
     * script/style/font policy. `blob:` is allowed in
     * script-src/worker-src because the Vite client creates
     * its HMR health-check worker from a blob: URL; without
     * it the worker is blocked, the client reports a lost
     * server connection and misses optimizer full-reloads
     * (leaving the module graph with mixed dep hashes).
     * Framing, base URIs and form targets stay locked down
     * in every environment.
     */
    private function contentSecurityPolicy(): string
    {
        if (app()->environment('local')) {
            return "default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' blob:; worker-src * blob:; style-src * 'unsafe-inline'; img-src * data: blob:; font-src * data:; connect-src * ws: wss:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        }

        return "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    }
}
