<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SqlUnit
{
    public function __construct(private ResolveActor $actors) {}

    /**
     * @template T
     *
     * @param  Closure(Connection): T  $work
     * @return T
     */
    public function forActor(ActorContext $actor, Closure $work): mixed
    {
        return DB::connection('pgsql')->transaction(function (Connection $connection) use ($actor, $work): mixed {
            $this->establish($connection, $actor->workshopId, $actor->actorId, $actor->role, '', '');
            $this->actors->assertCurrent($actor);

            return $work($connection);
        });
    }

    /**
     * @template T
     *
     * @param  Closure(Connection): T  $work
     * @return T
     */
    public function forService(ServiceContext $service, Closure $work): mixed
    {
        if ($service->serviceId !== config('identity_access.service_id') || ! in_array($service->capability, config('identity_access.service_capabilities'), true)) {
            throw new LogicException('Service capability is not configured for this process.');
        }

        return DB::connection('pgsql')->transaction(function (Connection $connection) use ($service, $work): mixed {
            $this->establish($connection, $service->workshopId, '', '', $service->serviceId, $service->capability);

            return $work($connection);
        });
    }

    public function assertActor(Connection $connection, ActorContext $actor): void
    {
        if ($connection !== DB::connection('pgsql') || $connection->transactionLevel() < 1) {
            throw new LogicException('The business connection requires an active SQL unit.');
        }
        $context = $connection->selectOne("SELECT current_setting('epr.workshop_id', true) AS workshop, current_setting('epr.actor_id', true) AS actor, current_setting('epr.actor_role', true) AS role");
        if ($context === null || $context->workshop !== $actor->workshopId || $context->actor !== $actor->actorId || $context->role !== $actor->role) {
            throw new LogicException('SQL unit context mismatch.');
        }
    }

    private function establish(Connection $connection, string $workshop, string $actor, string $role, string $service, string $capability): void
    {
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('An active transaction is required.');
        }
        $connection->select("SELECT set_config('lock_timeout', '2s', true), set_config('statement_timeout', '5s', true)");
        $current = $connection->selectOne("SELECT current_setting('epr.workshop_id', true) AS workshop, current_setting('epr.actor_id', true) AS actor, current_setting('epr.actor_role', true) AS role, current_setting('epr.service_id', true) AS service, current_setting('epr.service_capability', true) AS capability");
        if ($current !== null && $current->workshop !== null && $current->workshop !== '' && [$current->workshop, $current->actor, $current->role, $current->service, $current->capability] !== [$workshop, $actor, $role, $service, $capability]) {
            throw new LogicException('Cannot change isolation scope inside an existing SQL unit.');
        }
        $connection->select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true), set_config('epr.actor_role', ?, true), set_config('epr.service_id', ?, true), set_config('epr.service_capability', ?, true)", [$workshop, $actor, $role, $service, $capability]);
    }
}
