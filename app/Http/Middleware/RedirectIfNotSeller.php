<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotSeller
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @param  string|null  $guard
	 * @return mixed
	 */
	public function handle($request, Closure $next, $guard = 'seller')
	{
	    if (!Auth::guard($guard)->check()) {
	        return redirect('seller/login');
	    } else {
	        // logout user if he is not approved
	        try {
                if(!Auth::guard('seller')->user() || !Auth::guard('seller')->user()->approved) {
                    Auth::guard('seller')->logout();
                    $request->session()->invalidate();
                    return redirect('seller/login');
                }
            } catch (\Exception $e) {
                Auth::guard('seller')->logout();
                $request->session()->invalidate();
                return redirect('seller/login');
            }
        }
	    return $next($request);
	}
}
