<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Presentation;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SetPresentationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Presentation-Locale');
        App::setLocale(in_array($locale, ['fr', 'en'], true) ? $locale : 'fr');
        $request->attributes->set('correlationId', (string) Str::uuid7());
        $response = $next($request);
        $response->headers->set('Content-Language', App::getLocale());
        $response->setVary('X-Presentation-Locale', false);

        return $response;
    }
}
