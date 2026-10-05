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
     * Strict default policy. `style-src 'unsafe-inline'` is
     * required by compiled Tailwind; local development adds
     * script/font/style exceptions for the Vite dev server
     * (HMR runtime, bundled fonts) on every loopback origin.
     */
    private function contentSecurityPolicy(): string
    {
        if (app()->environment('local')) {
            return "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:* http://[::1]:*; style-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:* http://[::1]:*; img-src 'self' data:; font-src 'self' http://localhost:* http://127.0.0.1:* http://[::1]:*; connect-src 'self' ws://localhost:* ws://127.0.0.1:* ws://[::1]:* http://localhost:* http://127.0.0.1:* http://[::1]:*; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        }

        return "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    }
}
