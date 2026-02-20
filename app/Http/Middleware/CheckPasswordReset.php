<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordReset
{
    /**
     * Handle an incoming request.
     * Invalidates JWT token if password was reset by admin (forces re-login).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('api')->check()) {
            return $next($request);
        }

        $user = Auth::guard('api')->user();

        // If password was reset by admin, token's password_changed_at won't match
        if ($user->password_changed_at) {
            try {
                $payload = auth('api')->payload();
                $tokenPasswordChangedAt = $payload->get('password_changed_at');
                $userPasswordChangedAt = $user->password_changed_at->timestamp;

                if ($tokenPasswordChangedAt != $userPasswordChangedAt) {
                    auth('api')->logout();

                    return response()->json([
                        'success' => false,
                        'message' => 'Your password has been reset by the administrator. Please log in again with your new password.',
                    ], 401);
                }
            } catch (\Throwable $e) {
                // If payload not available (e.g. web guard), skip check
            }
        }

        return $next($request);
    }
}
