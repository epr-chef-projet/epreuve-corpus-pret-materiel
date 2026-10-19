<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use ReflectionMethod;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class SqlUnitTest extends TestCase
{
    private function actor(string $login): ActorContext
    {
        $user = Accounts::user($login);

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    public function test_runs_actor_and_service_work_without_passing_a_connection_or_returning_a_value(): void
    {
        $actor = $this->actor('atelier-1-manager');
        $units = app(SqlUnit::class);
        $executed = [];

        $units->forActor($actor, function () use ($units, $actor, &$executed): void {
            $this->assertSame(0, func_num_args());
            $units->assertActor(DB::connection('pgsql'), $actor);
            $executed[] = 'actor';
        });

        config(['identity_access.service_id' => 'test-publisher', 'identity_access.service_capabilities' => ['identity_access.publish']]);
        $service = new ServiceContext('test-publisher', $actor->workshopId, 'identity_access.publish');
        $units->forService($service, function () use ($actor, &$executed): void {
            $this->assertSame(0, func_num_args());
            $this->assertSame($actor->workshopId, DB::connection('pgsql')->selectOne("SELECT current_setting('epr.workshop_id') AS workshop")->workshop);
            $this->assertSame('test-publisher', DB::connection('pgsql')->selectOne("SELECT current_setting('epr.service_id') AS service")->service);
            $executed[] = 'service';
        });

        $this->assertSame(['actor', 'service'], $executed);
        foreach (['forActor', 'forService'] as $method) {
            $this->assertSame('void', (string) (new ReflectionMethod(SqlUnit::class, $method))->getReturnType());
        }
    }

    public function test_requires_the_actual_business_transaction_and_rejects_a_foreign_connection(): void
    {
        $actor = $this->actor('atelier-1-manager');
        $units = app(SqlUnit::class);
        expect(fn () => $units->assertActor(DB::connection('pgsql'), $actor))->toThrow(LogicException::class);
        DB::connection('auth_lookup')->transaction(function () use ($units, $actor) {
            expect(fn () => $units->assertActor(DB::connection('auth_lookup'), $actor))->toThrow(LogicException::class);
        });
    }

    public function test_clears_the_context_between_workshops_after_success_and_a_postgresql_error(): void
    {
        $units = app(SqlUnit::class);
        $first = $this->actor('atelier-1-manager');
        $second = $this->actor('atelier-2-staff');
        $units->forActor($first, function () use ($units, $first) {
            $connection = DB::connection('pgsql');
            $units->assertActor($connection, $first);
            expect($connection->selectOne("SELECT current_setting('lock_timeout') AS lock, current_setting('statement_timeout') AS statement"))->toMatchObject(['lock' => '2s', 'statement' => '5s']);
            expect($connection->selectOne("SELECT current_setting('epr.workshop_id') AS workshop")->workshop)->toBe($first->workshopId);
        });
        try {
            $units->forActor($first, function (): void {
                DB::connection('pgsql')->select('SELECT 1/0');
            });
            $this->fail('Expected SQL failure.');
        } catch (QueryException $error) {
            expect($error->errorInfo[0] ?? null)->toBe('22012');
        }
        $units->forActor($second, function () use ($units, $second) {
            $connection = DB::connection('pgsql');
            $units->assertActor($connection, $second);
            expect($connection->selectOne("SELECT current_setting('epr.workshop_id') AS workshop")->workshop)->toBe($second->workshopId);
        });
        expect(DB::connection()->transactionLevel())->toBe(0);
        expect(DB::selectOne("SELECT nullif(current_setting('epr.workshop_id',true),'') AS workshop")->workshop)->toBeNull();
    }

    public function test_rejects_a_changed_workshop_or_role_in_a_context_instead_of_trusting_the_dto(): void
    {
        $first = $this->actor('atelier-1-manager');
        $second = $this->actor('atelier-2-manager');
        $units = app(SqlUnit::class);
        expect(fn () => $units->forActor(new ActorContext($first->actorId, $second->workshopId, 'manager'), fn () => null))->toThrow(AuthorizationException::class);
        expect(fn () => $units->forActor(new ActorContext($first->actorId, $first->workshopId, 'borrower'), fn () => null))->toThrow(AuthorizationException::class);
    }

    public function test_refuses_a_nested_scope_switch_while_allowing_participation_in_the_same_unit(): void
    {
        $units = app(SqlUnit::class);
        $first = $this->actor('atelier-1-manager');
        $units->forActor($first, function () use ($units, $first) {
            $connection = DB::connection('pgsql');
            $units->forActor($first, function () use ($connection): void {
                expect(DB::connection('pgsql'))->toBe($connection);
            });
            expect(fn () => $units->forActor($this->actor('atelier-2-manager'), fn () => null))->toThrow(LogicException::class);
        });
    }

    public function test_public_authorization_checks_current_rights_without_importing_the_user_model(): void
    {
        $authorization = app(AuthorizeActor::class);
        foreach (['borrower-1' => ['borrow'], 'staff' => ['operate-loans'], 'manager' => ['operate-loans', 'manage-catalog']] as $suffix => $allowed) {
            $actor = $this->actor('atelier-1-'.$suffix);
            foreach (['borrow', 'operate-loans', 'manage-catalog', 'unknown-capability'] as $ability) {
                if (in_array($ability, $allowed, true)) {
                    $this->assertTrue($authorization->handle($actor, $ability)->allowed());
                } else {
                    expect(fn () => $authorization->handle($actor, $ability))->toThrow(AuthorizationException::class);
                }
            }
        }
        $actor = $this->actor('atelier-1-manager');
        $other = $this->actor('atelier-2-manager');
        expect(fn () => $authorization->handle(new ActorContext($actor->actorId, $other->workshopId, $actor->role), 'manage-catalog'))->toThrow(AuthorizationException::class);
    }

    public function test_denies_a_fabricated_service_capability_in_an_http_process(): void
    {
        $first = $this->actor('atelier-1-manager');
        expect(fn () => app(SqlUnit::class)->forService(new ServiceContext('worker', $first->workshopId, 'identity_access.publish'), fn () => null))->toThrow(LogicException::class);
    }
}
