<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Illuminate\Auth\Access\Response;

final class AuthorizeActor
{
    public function __construct(private ResolveActor $actors) {}

    public function handle(ActorContext $actor, string $ability): Response
    {
        $this->actors->assertCurrent($actor);
        $response = Role::from($actor->role)->allows($ability) ? Response::allow() : Response::deny();

        return $response->authorize();
    }
}
