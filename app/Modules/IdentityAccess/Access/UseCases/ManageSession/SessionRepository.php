<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\UseCases\ManageSession;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SessionRepository
{
    /** @return object{user_id: string, session_key_ciphertext: string, broadcast_token: string, auth_generation: int}|null */
    public function lockCandidate(string $actorId, string $role, string $token): ?object
    {
        $connection = DB::connection('pgsql');
        if ($connection->transactionLevel() < 1) {
            throw new \LogicException('Session publication requires its SQL unit.');
        }

        return $connection->selectOne('SELECT * FROM epr.identity_access_admit_session(?, ?::uuid, ?)', [$token, $actorId, $role]);
    }

    public function key(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, config('app.key'));
    }

    public function start(string $actorId, string $sessionId): string
    {
        $token = bin2hex(random_bytes(32));
        DB::connection('auth_lookup')->select('SELECT epr.identity_access_start_session(?::uuid, ?::uuid, ?, ?, ?)', [
            $actorId, (string) Str::uuid7(), $this->key($sessionId), Crypt::encryptString($sessionId), $token,
        ]);

        return $token;
    }

    public function revoke(string $sessionId): void
    {
        DB::connection('auth_lookup')->select('SELECT epr.identity_access_revoke_session(?)', [$this->key($sessionId)]);
    }
}
