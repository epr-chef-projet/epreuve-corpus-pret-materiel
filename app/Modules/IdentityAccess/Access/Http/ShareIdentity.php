<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\Http;

use App\Modules\IdentityAccess\Identity\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\Identity\Models\User;
use App\Modules\IdentityAccess\PublicApi\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class ShareIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            Inertia::clearHistory();
        }
        Inertia::share('auth', [
            'user' => $user instanceof User ? (new AuthenticatedUserData($user->id, $user->login, $user->display_name, $user->workshop_id, $user->workshop_name, Role::from($user->role)))->toArray() : null,
            'broadcastToken' => $user instanceof User ? $request->session()->get('identity_access.broadcast_token') : null,
            'authGeneration' => $user instanceof User ? $request->session()->get('identity_access.auth_generation') : null,
            'expiresAt' => $user instanceof User ? Carbon::createFromTimestampUTC($request->session()->get('identity_access.expires_at'))->format('Y-m-d\TH:i:s.v\Z') : null,
        ]);
        Inertia::share('locale', app()->getLocale());
        $validation = $request->session()->get('validationPresentation');
        Inertia::share('validationPresentation', is_string($validation) ? json_decode($validation, false, 32) : null);
        Inertia::share('validationErrorBag', $request->session()->get('validationErrorBag', 'default'));

        return $next($request);
    }
}
