<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\IdentityAccess\Access\Http\ResolveSession;
use App\Modules\IdentityAccess\Access\Http\ShareIdentity;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveSession::class);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            ResolveSession::class,
            ShareIdentity::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $error): bool {
            $diagnostic = [
                'environment' => app()->environment(),
                'exceptionClass' => $error::class,
                'sqlState' => $error->errorInfo[0] ?? null,
                'correlationId' => (string) Str::uuid7(),
            ];
            try {
                Log::channel($error->getConnectionName() === 'auth_lookup' ? 'identity' : 'runtime')->error('database.failure', $diagnostic);
            } catch (Throwable) {
                error_log(json_encode(['message' => 'runtime.collector_unavailable', 'context' => $diagnostic], JSON_THROW_ON_ERROR));
            }

            return false;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
