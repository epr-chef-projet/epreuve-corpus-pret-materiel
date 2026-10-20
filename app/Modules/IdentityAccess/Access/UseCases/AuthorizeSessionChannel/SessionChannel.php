<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\UseCases\AuthorizeSessionChannel;

use App\Modules\IdentityAccess\Identity\Models\User;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class SessionChannel
{
    public function __construct(private Request $request, private ResolveActor $actors) {}

    public function join(User $user, string $token): bool
    {
        try {
            $actor = $this->actors->handle($this->request);
        } catch (AuthenticationException) {
            return false;
        }

        $current = $this->request->session()->get('identity_access.broadcast_token');

        return $actor->actorId === $user->id && is_string($current) && hash_equals($current, $token);
    }
}
