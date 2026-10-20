<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\PublicApi;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SqlUnit
{
    public function __construct(private ResolveActor $actors) {}

    /**
     * Standalone reads own a short, read-only snapshot. Participants retain the outer unit.
     *
     * @param  Closure(): void  $work
     */
    public function forActorRead(ActorContext $actor, Closure $work): void
    {
        $connection = DB::connection('pgsql');
        if ($connection->transactionLevel() > 0) {
            $this->assertActor($connection, $actor);
            $this->actors->assertCurrent($actor);
            $work();

            return;
        }
        $connection->transaction(function (Connection $connection) use ($actor, $work): void {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $this->establish($connection, $actor->workshopId, $actor->actorId, $actor->role, '', '');
            $connection->select("SELECT set_config('transaction_timeout', '5s', true)");
            $this->actors->assertCurrent($actor);
            $work();
        });
    }

    /**
     * @param  Closure(): void  $work
     */
    public function forActor(ActorContext $actor, Closure $work): void
    {
        DB::connection('pgsql')->transaction(function (Connection $connection) use ($actor, $work): void {
            $this->establish($connection, $actor->workshopId, $actor->actorId, $actor->role, '', '');
            $this->actors->assertCurrent($actor);

            $work();
        });
    }

    /**
     * @param  Closure(): void  $work
     */
    public function forService(ServiceContext $service, Closure $work): void
    {
        if ($service->serviceId !== config('identity_access.service_id') || ! in_array($service->capability, config('identity_access.service_capabilities'), true)) {
            throw new LogicException('Service capability is not configured for this process.');
        }

        DB::connection('pgsql')->transaction(function (Connection $connection) use ($service, $work): void {
            $this->establish($connection, $service->workshopId, '', '', $service->serviceId, $service->capability);

            $work();
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
