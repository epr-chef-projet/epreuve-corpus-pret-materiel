<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog;

use App\Modules\IdentityAccess\PublicApi\AdmitSessionPublication;
use App\Modules\IdentityAccess\PublicApi\ReadSessionCandidates;
use App\Modules\IdentityAccess\PublicApi\Role;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

final class PublishCatalogInvalidation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15];

    /** @var array<string, string> */
    private readonly array $eventIds;

    public function __construct(public readonly string $workshopId)
    {
        $this->eventIds = ['catalog' => (string) Str::uuid7(), 'catalogManagement' => (string) Str::uuid7()];
        $this->onConnection('redis')->onQueue(app()->environment('testing') ? 'testing-notifications' : 'notifications')->afterCommit();
    }

    public function handle(ReadSessionCandidates $candidates, AdmitSessionPublication $admit): void
    {
        try {
            $this->publish($candidates, $admit);
        } catch (\Throwable $error) {
            throw new NotificationDeliveryFailed($this->workshopId, $this->eventIds['catalog'], $error);
        }
    }

    private function publish(ReadSessionCandidates $candidates, AdmitSessionPublication $admit): void
    {
        $service = new ServiceContext((string) config('identity_access.service_id'), $this->workshopId, 'identity_access.publish');
        foreach (['catalog' => Role::cases(), 'catalogManagement' => [Role::Manager]] as $projection => $roles) {
            $cursor = null;
            do {
                $page = $candidates->handle($service, $roles, cursor: $cursor);
                foreach ($page->candidates as $candidate) {
                    $route = $admit->handle($service, $candidate->actorId, $candidate->role->value, $candidate->broadcastToken);
                    if ($route !== null) {
                        event(new CatalogInvalidated($route['channel'], new NotificationEnvelopeData($this->eventIds[$projection], $route['authGeneration'], 'catalog.invalidated', ['projection' => $projection])));
                    }
                }
                $cursor = $page->nextCursor;
            } while ($cursor !== null);
        }
    }
}
