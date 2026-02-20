<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
            ], 401);
        }

        $user = Auth::user();
        
        // Check if user has user role (not admin)
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User role required.',
            ], 403);
        }
            // Update 'last_seen' timestamp every time a user makes a request
            // $user->update(['last_seen' => Carbon::now()]);
            $user->touch('last_seen');
       
        return $next($request);
    }
}
