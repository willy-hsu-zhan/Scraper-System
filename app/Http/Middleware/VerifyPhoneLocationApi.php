<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPhoneLocationApi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $token = $request->bearerToken();

        if (
            !$token ||
            !hash_equals(
                config('services.phone_location.secret'),
                $token
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
