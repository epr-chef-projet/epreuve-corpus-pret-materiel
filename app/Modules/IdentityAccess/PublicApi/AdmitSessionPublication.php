<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use App\Modules\IdentityAccess\Sessions\UseCases\ManageSession\SessionRepository;
use App\Shared\Database\SqlUnit;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** The source owner must establish the recipient and permitted role before admission. */
final class AdmitSessionPublication
{
    public function __construct(private SqlUnit $units, private SessionRepository $sessions, private SessionManager $manager) {}

    /** @return array{channel: string, authGeneration: int}|null */
    public function handle(ServiceContext $service, string $actorId, string $role, string $broadcastToken): ?array
    {
        if (! Str::isUuid($actorId) || ! in_array($role, ['borrower', 'staff', 'manager'], true) || preg_match('/\A[0-9a-f]{64}\z/D', $broadcastToken) !== 1) {
            return null;
        }

        return $this->units->forService($service, function () use ($actorId, $role, $broadcastToken): ?array {
            $candidate = $this->sessions->lockCandidate($actorId, $role, $broadcastToken);
            if ($candidate === null) {
                return null;
            }
            try {
                $sessionId = Crypt::decryptString($candidate->session_key_ciphertext);
                $probe = clone $this->manager->driver();
                if (! $probe instanceof Store) {
                    throw new \LogicException('A Laravel session store is required.');
                }
                $probe->flush();
                $probe->setId($sessionId);
                $probe->start();
                /** @var SessionGuard $guard */
                $guard = Auth::guard('web');
                $live = $probe->get($guard->getName()) === $actorId
                    && $probe->get('identity_access.role') === $role
                    && $probe->get('identity_access.auth_generation') === (int) $candidate->auth_generation
                    && $probe->get('identity_access.expires_at', 0) > now()->getTimestamp();
                unset($sessionId, $probe);
            } catch (DecryptException) {
                return null;
            } catch (\RedisException) {
                return null;
            }

            return $live ? ['channel' => 'epr.session.'.$candidate->broadcast_token, 'authGeneration' => (int) $candidate->auth_generation] : null;
        });
    }
}
