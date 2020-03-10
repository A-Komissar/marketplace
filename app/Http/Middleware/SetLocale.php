<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
class SetLocale
{
    public function __construct(Application $app, Request $request) {
        $this->app = $app;
        $this->request = $request;
    }

    public function handle($request, Closure $next)
    {
        $locale = session('locale');
        if ($locale === 'uk' || $locale === 'ru') {
            $this->app->setLocale($locale);
        }
        return $next($request);
    }

}
