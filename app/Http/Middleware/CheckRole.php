<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // For admin routes (using Sanctum token capability or Admin model check)
        if ($role === 'admin') {
            if (!$request->user() instanceof \App\Models\Admin) {
                 return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }
        }
        
        return $next($request);
    }
}
