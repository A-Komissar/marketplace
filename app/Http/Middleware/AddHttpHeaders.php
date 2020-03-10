<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Class AddHttpHeaders
 * @package App\Http\Middleware
 */
class AddHttpHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->header('Set-Cookie', 'HttpOnly;Secure;SameSite=Strict');
        return $response;
    }
}