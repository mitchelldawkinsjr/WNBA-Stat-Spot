<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Use environment variable for allowed origins instead of wildcard
        $allowedOrigins = env('CORS_ALLOWED_ORIGINS', 'http://localhost:4173,http://localhost:3000,http://localhost:5173,http://localhost:8000');
        $origins = explode(',', $allowedOrigins);
        $origin = $request->header('Origin');

        if (in_array($origin, $origins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        // Only allow credentials with specific origins, not wildcard
        if (in_array($origin, $origins)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(200);
        }

        return $response;
    }
}
