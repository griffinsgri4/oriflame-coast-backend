<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if user has admin role
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Admin access required'
            ], 403);
        }

        return $next($request);
    }
}