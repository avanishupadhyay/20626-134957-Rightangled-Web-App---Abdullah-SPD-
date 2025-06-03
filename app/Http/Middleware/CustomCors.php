<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Only apply CORS for specific URLs
        //if ($request->is('shopify/loyalty')) {
            // return $next($request)
            //     //->header('Access-Control-Allow-Origin', 'http://localhost:3000')
            //     ->header('Access-Control-Allow-Origin', '*')
            //     ->header('Access-Control-Allow-Methods', 'GET, POST')
            //     ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization')
            //     ->header('Access-Control-Allow-Credentials', 'true');
        //}

        return response()->json([], 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');

        return $next($request);
    }
}
