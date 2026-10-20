<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use App\Modules\IdentityAccess\Access\UseCases\ReadSessionCandidates\ReadSessionCandidatesRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** The source owner establishes the audience; each candidate still requires immediate admission. */
final class ReadSessionCandidates
{
    public function __construct(private SqlUnit $units, private ReadSessionCandidatesRepository $repository) {}

    /** @param array<array-key, Role> $roles */
    public function handle(ServiceContext $service, array $roles, ?string $actorId = null, ?string $cursor = null, int $limit = 100): SessionCandidatePage
    {
        if ($limit < 1 || $limit > 100 || ! array_is_list($roles) || $roles === [] || count($roles) > 3 || ($actorId !== null && ! Str::isUuid($actorId)) || ($cursor !== null && strlen($cursor) > 2048)) {
            throw new InvalidArgumentException('Invalid session audience or page size.');
        }
        $names = array_map(static fn (Role $role): string => $role->value, $roles);
        sort($names);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('Duplicate audience roles.');
        }
        $scope = hash('sha256', json_encode([$service->workshopId, $names, $actorId], JSON_THROW_ON_ERROR));
        $after = null;
        if ($cursor !== null) {
            try {
                $decoded = json_decode(Crypt::decryptString($cursor), true, 8, JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                throw new InvalidArgumentException('Invalid routing cursor.');
            }
            if (! is_array($decoded) || ($decoded['scope'] ?? null) !== $scope || ! is_string($decoded['after'] ?? null) || ! Str::isUuid($decoded['after'])) {
                throw new InvalidArgumentException('Foreign routing cursor.');
            }
            $after = $decoded['after'];
        }
        $rows = [];
        $this->units->forService($service, function () use ($names, $actorId, $after, $limit, &$rows): void {
            $rows = $this->repository->read($names, $actorId, $after, $limit);
        });
        $last = $rows === [] ? null : $rows[array_key_last($rows)]->id;
        $next = count($rows) === $limit && $last !== null ? Crypt::encryptString(json_encode(['scope' => $scope, 'after' => $last], JSON_THROW_ON_ERROR)) : null;

        return new SessionCandidatePage(array_map(static fn (object $row): SessionCandidateData => new SessionCandidateData($row->user_id, Role::from($row->role), $row->broadcast_token), $rows), $next);
    }
}
