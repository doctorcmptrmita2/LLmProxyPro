<?php

namespace App\Http\Middleware;

use App\Models\ProjectApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Missing API key',
                    'request_id' => $request->header('x-request-id'),
                ],
            ], 401);
        }

        $apiKey = ProjectApiKey::whereNotNull('key_hash')
            ->whereNull('revoked_at')
            ->get()
            ->first(fn($key) => Hash::check($token, $key->key_hash));

        if (!$apiKey) {
            return response()->json([
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Invalid API key',
                    'request_id' => $request->header('x-request-id'),
                ],
            ], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->merge([
            'project' => $apiKey->project,
            'api_key' => $apiKey,
        ]);

        return $next($request);
    }
}

