<?php

namespace App\Http\Middleware;

use App\Models\OAuthClientCorsOrigin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowRegisteredOAuthClientCorsOrigin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldHandle($request)) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');

        if (! is_string($origin) || $origin === '') {
            return $next($request);
        }

        if (! $this->originIsAllowed($origin)) {
            if ($request->isMethod('OPTIONS')) {
                return response(status: Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->addCorsHeaders(response(status: Response::HTTP_NO_CONTENT), $origin);
        }

        return $this->addCorsHeaders($next($request), $origin);
    }

    private function shouldHandle(Request $request): bool
    {
        return $request->is('api/*') || $request->is('oauth/token');
    }

    private function originIsAllowed(string $origin): bool
    {
        return OAuthClientCorsOrigin::query()
            ->where('origin', $origin)
            ->whereHas('client', fn ($query) => $query->where('revoked', false))
            ->exists();
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With');
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }
}
