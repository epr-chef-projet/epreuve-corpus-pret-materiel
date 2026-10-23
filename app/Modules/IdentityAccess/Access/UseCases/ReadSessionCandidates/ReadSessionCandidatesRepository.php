<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\UseCases\ReadSessionCandidates;

use Illuminate\Support\Facades\DB;

final class ReadSessionCandidatesRepository
{
    /**
     * @param  list<string>  $roles
     * @return list<object{id: string, user_id: string, role: string, broadcast_token: string}>
     */
    public function read(array $roles, ?string $actorId, ?string $after, int $limit): array
    {
        return array_values(DB::connection('pgsql')->select('SELECT * FROM epr.identity_access_session_candidates(?::jsonb, ?::uuid, ?::uuid, ?)', [json_encode($roles, JSON_THROW_ON_ERROR), $actorId, $after, $limit]));
    }
}
