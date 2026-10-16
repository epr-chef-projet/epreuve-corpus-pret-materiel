<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Sessions\UseCases\StartSession;

use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\Sessions\UseCases\ManageSession\SessionRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class StartSessionAction
{
    public function __construct(private SessionRepository $sessions) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        try {
            $token = $this->sessions->start($user->id, $request->session()->getId());
            $request->session()->put('identity_access.broadcast_token', $token);
            $request->session()->put('identity_access.role', $user->role);
            $request->session()->put('identity_access.expires_at', now()->addHours(2)->getTimestamp());
            $request->session()->put('identity_access.auth_generation', 1);
        } catch (\Throwable $error) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $error;
        }

        return $next($request);
    }
}
