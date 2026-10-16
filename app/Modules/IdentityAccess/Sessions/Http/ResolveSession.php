<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Sessions\Http;

use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\Sessions\UseCases\ManageSession\SessionRepository;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSession
{
    public function __construct(private SessionRepository $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $guard->forgetUser();
        $hasIdentity = $request->session()->has($guard->getName());
        if ($hasIdentity) {
            $probe = clone $request->session();
            $probe->flush();
            $probe->start();
            if ($probe->get($guard->getName()) !== $request->session()->get($guard->getName())) {
                try {
                    $this->sessions->revoke($request->session()->getId());
                } finally {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        }
        try {
            $user = $guard->user();
        } catch (\Throwable $error) {
            if ($hasIdentity) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $guard->forgetUser();
            }
            throw $error;
        }
        $changed = $user instanceof User && $request->session()->get('identity_access.role') !== $user->role;
        $expired = $hasIdentity && $request->session()->get('identity_access.expires_at', 0) <= now()->getTimestamp();
        if ($hasIdentity && ($user === null || $changed || $expired || $request->routeIs('logout'))) {
            try {
                $this->sessions->revoke($request->session()->getId());
            } catch (\Throwable $error) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $guard->forgetUser();
                throw $error;
            } finally {
                if ($user === null || $changed || $expired) {
                    $guard->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        }
        $response = $next($request);
        if ($hasIdentity || $guard->check() || $request->routeIs('login', 'login.store', 'logout')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
