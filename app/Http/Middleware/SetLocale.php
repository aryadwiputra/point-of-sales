<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = ['id', 'en'];
        $defaultLocale = 'id';

        $locale = $defaultLocale;

        if ($request->user() && $request->user()->locale) {
            $locale = $request->user()->locale;
        } elseif ($request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
        } elseif ($request->cookie('locale')) {
            $locale = $request->cookie('locale');
        } elseif ($request->hasHeader('Accept-Language')) {
            $acceptLanguage = $request->header('Accept-Language');
            $preferredLocale = explode(',', $acceptLanguage)[0] ?? null;
            if ($preferredLocale) {
                $localeCode = explode('-', $preferredLocale)[0];
                if (in_array($localeCode, $availableLocales)) {
                    $locale = $localeCode;
                }
            }
        }

        if (! in_array($locale, $availableLocales)) {
            $locale = $defaultLocale;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
