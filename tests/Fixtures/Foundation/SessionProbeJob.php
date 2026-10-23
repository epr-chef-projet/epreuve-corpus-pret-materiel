<?php

declare(strict_types=1);

namespace Tests\Fixtures\Foundation;

use App\Modules\IdentityAccess\PublicApi\AdmitSessionPublication;
use App\Modules\IdentityAccess\PublicApi\ReadSessionCandidates;
use App\Modules\IdentityAccess\PublicApi\Role;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

final class SessionProbeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 25;

    public int $tries = 1;

    public function __construct(public readonly string $workshopId, public readonly string $actorId, public readonly Role $role, public readonly string $eventId, public readonly bool $barrier)
    {
        $this->onConnection('redis')->onQueue('testing-notifications')->afterCommit();
    }

    public function handle(ReadSessionCandidates $candidates, AdmitSessionPublication $admission): void
    {
        $disk = Storage::disk('local');
        $path = 'foundation/session-probe/'.$this->eventId;
        $disk->put($path.'.started', 'started');
        $deadline = microtime(true) + 15;
        while ($this->barrier && ! $disk->exists($path.'.release')) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Session probe release barrier timed out.');
            }
            usleep(20_000);
        }
        $service = new ServiceContext((string) config('identity_access.service_id'), $this->workshopId, 'identity_access.publish');
        $cursor = null;
        $admitted = 0;
        $routes = [];
        do {
            $page = $candidates->handle($service, [$this->role], $this->actorId, $cursor);
            foreach ($page->candidates as $candidate) {
                $route = $admission->handle($service, $candidate->actorId, $candidate->role->value, $candidate->broadcastToken);
                if ($route !== null) {
                    event(new SessionProbePublished($route['channel'], new NotificationEnvelopeData($this->eventId, $route['authGeneration'], 'catalog.invalidated', ['projection' => 'catalog'])));
                    $admitted++;
                    $routes[] = hash('sha256', $route['channel']);
                }
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
        $disk->put($path.'.done', json_encode(['admitted' => $admitted, 'routes' => $routes], JSON_THROW_ON_ERROR));
    }
}
