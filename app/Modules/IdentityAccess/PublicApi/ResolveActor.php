<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\Identity\UseCases\Authenticate\IdentityRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class ResolveActor
{
    public function __construct(private IdentityRepository $identities, private SessionRepository $sessions) {}

    public function handle(Request $request): ActorContext
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession()) {
            throw new AuthenticationException;
        }
        $probe = clone $request->session();
        $probe->flush();
        $probe->start();
        $id = $probe->get($guard->getName());
        if (! is_string($id) || ! Str::isUuid($id) || $probe->get('identity_access.expires_at', 0) <= now()->getTimestamp()) {
            throw new AuthenticationException;
        }
        $user = $this->identities->resolve($id, $this->sessions->key($probe->getId()));
        if ($user === null || $probe->get('identity_access.role') !== $user->role) {
            throw new AuthenticationException;
        }

        return new ActorContext($user->id, $user->workshop_id, $user->role);
    }

    public function assertCurrent(ActorContext $actor): void
    {
        if (! $this->identities->isCurrent($actor)) {
            throw new AuthorizationException;
        }
    }
}
