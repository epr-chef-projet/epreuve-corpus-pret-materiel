<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Sessions\Http;

use App\Modules\IdentityAccess\Accounts\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\PublicApi\Role;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class ShareIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        Inertia::share('auth', [
            'user' => $user instanceof User ? (new AuthenticatedUserData($user->id, $user->login, $user->display_name, $user->workshop_id, $user->workshop_name, Role::from($user->role)))->toArray() : null,
        ]);

        return $next($request);
    }
}
