<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Session;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Store;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Reads the native session without saving its snapshot, flash age, navigation or cookies. */
final class ReadOnlySession
{
    public function __construct(private StartSession $sessions) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->getSession($request);
        if (! $session instanceof Store) {
            throw new LogicException('Read-only HTTP sessions require the native Laravel session store.');
        }
        $session->setRequestOnHandler($request);
        $session->start();
        $request->setLaravelSession($session);

        $response = $next($request);
        $response->headers->remove('Set-Cookie');

        return $response;
    }
}
