<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\UseCases\Authenticate;

use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use Illuminate\Support\Facades\DB;

final class IdentityRepository
{
    public function isCurrent(ActorContext $actor): bool
    {
        return DB::connection('pgsql')->selectOne('SELECT epr.identity_access_assert_actor(?::uuid, ?::uuid, ?) AS valid', [$actor->actorId, $actor->workshopId, $actor->role])?->valid === true;
    }

    public function findByLogin(string $login): ?User
    {
        return $this->hydrate(DB::connection('auth_lookup')->selectOne('SELECT * FROM epr.identity_access_find_identity(?)', [$login]));
    }

    public function resolve(string $id, string $hmac): ?User
    {
        return $this->hydrate(DB::connection('auth_lookup')->selectOne('SELECT * FROM epr.identity_access_resolve_identity(?::uuid, ?)', [$id, $hmac]));
    }

    public function rehash(User $user, string $hash): void
    {
        DB::connection('auth_lookup')->select('SELECT epr.identity_access_rehash_password(?::uuid, ?, ?)', [$user->id, $user->password, $hash]);
        $user->password = $hash;
    }

    private function hydrate(?object $row): ?User
    {
        if ($row === null) {
            return null;
        }
        $user = new User;
        $user->setRawAttributes((array) $row, true);
        $user->exists = true;

        return $user;
    }
}
