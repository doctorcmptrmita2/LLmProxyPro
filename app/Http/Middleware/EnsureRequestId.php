<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->header('x-request-id')) {
            $request->headers->set('x-request-id', (string) Str::uuid());
        }

        return $next($request);
    }
}

