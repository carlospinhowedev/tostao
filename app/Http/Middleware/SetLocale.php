<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if ($locale && array_key_exists($locale, config('tostao.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
