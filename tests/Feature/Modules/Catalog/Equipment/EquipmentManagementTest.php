<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog\Equipment;

use App\Modules\Catalog\Database\Factories\EquipmentFactory;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CloseEquipmentOperation\CloseEquipmentOperationAction;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ReadEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipmentOperation\ReadEquipmentOperationAction;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use App\Modules\Catalog\PublicApi\LockEquipmentBatch;
use App\Modules\Catalog\PublicApi\ReadEquipmentBatch;
use App\Modules\Catalog\PublicApi\ReadEquipmentPage;
use App\Modules\Catalog\PublicApi\ReplaceTotalWithinUnit;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use ArrayObject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class EquipmentManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function actor(string $login = 'atelier-1-manager'): ActorContext
    {
        $user = Accounts::user($login);

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    private function prepare(string $login = 'atelier-1-manager'): ActorContext
    {
        $actor = $this->actor($login);
        app(SqlUnit::class)->forActor($actor, static function (): void {});
        Accounts::signIn($this, $login);
        $this->withCredentials();

        return $actor;
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return ['name' => '  Perceuse %_ électrique  ', 'category' => '  Outillage  ', 'description' => '  Description française  ', 'totalQuantity' => ' 02 ', 'operationKey' => (string) Str::uuid7()];
    }

    public function test_manager_creates_text_and_initial_total_together_with_a_private_receipt(): void
    {
        $this->assertSame(EquipmentPolicy::class, Gate::policies()[Equipment::class] ?? null);
        $actor = $this->prepare();
        $input = $this->input();
        $response = $this->from('/gestion/materiels/nouveau')->post('/gestion/materiels', $input);
        $response->assertRedirect();
        $receipt = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $receipt['resultVersion']);
        $this->assertFalse($receipt['replayed']);
        $this->assertSame($input['operationKey'], $receipt['operationKey']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{14}7/', $receipt['resourceId']);
        $this->assertDatabaseHas('catalog_equipment', ['id' => $receipt['resourceId'], 'workshop_id' => $actor->workshopId, 'name' => 'Perceuse %_ électrique', 'category' => 'Outillage', 'description' => 'Description française', 'total_quantity' => 2, 'is_active' => true, 'version' => 1]);
        $this->assertDatabaseHas('operation_receipts', ['operation_key' => $input['operationKey'], 'actor_id' => $actor->actorId, 'resource_id' => $receipt['resourceId']]);
        $this->get('/gestion/materiels/'.$receipt['resourceId'])->assertInertia(fn (Assert $page) => $page->component('Manager/Catalog/Show')->where('equipment.totalQuantity', 2)->where('equipment.image', null)->where('equipment.description', 'Description française'));
        $this->getJson('/gestion/materiels/'.$receipt['resourceId'].'/lecture')->assertOk()->assertJsonPath('description', 'Description française');
    }

    public function test_detail_resolves_its_own_durable_receipt_when_the_temporary_confirmation_is_gone(): void
    {
        $this->prepare();
        $input = $this->input();
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $receipt = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        session()->forget('catalogReceipt');
        $this->withHeaders(['X-EPR-Operation-Key' => $input['operationKey'], 'X-EPR-Operation-Type' => CreateEquipmentAction::OPERATION])
            ->get('/gestion/materiels/'.$receipt['resourceId'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Manager/Catalog/Show')->where('commandReceipt.operationKey', $input['operationKey'])->where('commandReceipt.resourceId', $receipt['resourceId'])->where('commandReceipt.resultVersion', 1));
        $this->assertSame(1, DB::table('catalog_equipment')->where('id', $receipt['resourceId'])->count());
        $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $input['operationKey'])->count());
    }

    #[DataProvider('protectedCommands')]
    public function test_commands_obey_gate_refusal_without_writing(string $command): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        if ($command === 'total') {
            app(LockEquipmentBatch::class)->handle($actor, [$equipment->id]);
        }
        $key = (string) Str::uuid7();
        Gate::before(
            /** @param array<array-key, mixed> $arguments */
            static function (?Authenticatable $user, string $ability, array $arguments): ?bool {
                return isset($arguments[0]) && ($arguments[0] === Equipment::class || $arguments[0] instanceof Equipment) ? false : null;
            });
        try {
            match ($command) {
                'create' => app(CreateEquipmentAction::class)->handle($actor, new EquipmentInput('Gate probe', 'Tools', null), 1, $key),
                'texts' => app(EditEquipmentTextAction::class)->handle($actor, $equipment->id, new EquipmentInput('Gate probe', 'Tools', null), 1, $key),
                'archive' => app(ChangeEquipmentStateAction::class)->handle($actor, $equipment->id, ChangeEquipmentStateAction::ARCHIVE, 1, $key),
                'reactivate' => app(ChangeEquipmentStateAction::class)->handle($actor, $equipment->id, ChangeEquipmentStateAction::REACTIVATE, 1, $key),
                'total' => app(ReplaceTotalWithinUnit::class)->handle($actor, $equipment->id, 2, 1, 0),
                default => throw new \LogicException('Unknown command'),
            };
            $this->fail('The command did not consult Gate.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'name' => $equipment->name, 'version' => 1]);
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        }
    }

    /** @return array<string, array{string}> */
    public static function protectedCommands(): array
    {
        return ['create' => ['create'], 'texts' => ['texts'], 'archive' => ['archive'], 'reactivate' => ['reactivate'], 'total' => ['total']];
    }

    public function test_registered_policy_uses_the_explicit_context_and_does_not_query_sql(): void
    {
        $equipment = EquipmentFactory::new()->make(['workshop_id' => $this->actor()->workshopId]);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        foreach (['atelier-1-manager' => true, 'atelier-1-staff' => false, 'atelier-1-borrower-1' => false] as $login => $allowed) {
            $actor = $this->actor($login);
            $gate = Gate::forUser(null);
            $this->assertSame($allowed, $gate->allows('create', [Equipment::class, $actor]));
            $this->assertSame($allowed, $gate->allows('update', [$equipment, $actor]));
            $this->assertSame($allowed, $gate->allows('viewAny', [Equipment::class, $actor, CatalogAudience::Management]));
            $this->assertTrue($gate->allows('viewAny', [Equipment::class, $actor, CatalogAudience::Catalog]));
        }
        $this->assertSame([], $queries);
    }

    public function test_a_confirmation_reference_cannot_confirm_another_equipment_or_another_workshops_operation(): void
    {
        $actor = $this->prepare();
        $input = $this->input();
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $receipt = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        $another = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        $this->withHeaders(['X-EPR-Operation-Key' => $input['operationKey'], 'X-EPR-Operation-Type' => CreateEquipmentAction::OPERATION])
            ->get('/gestion/materiels/'.$another->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('commandReceipt', null));
        $this->withHeaders(['X-EPR-Operation-Key' => (string) Str::uuid7(), 'X-EPR-Operation-Type' => CreateEquipmentAction::OPERATION])
            ->get('/gestion/materiels/'.$receipt['resourceId'])->assertOk()->assertInertia(fn (Assert $page) => $page->where('commandReceipt', null));
        $foreign = Accounts::user('atelier-2-manager');
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true), set_config('epr.actor_role','manager',true)", [$foreign['workshopId'], $foreign['id']]);
        $foreignReceipt = app(CreateEquipmentAction::class)->handle(new ActorContext($foreign['id'], $foreign['workshopId'], $foreign['role']), new EquipmentInput('Foreign fixture', 'Synthetic', null), 0, (string) Str::uuid7());
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true)", [$actor->workshopId, $actor->actorId]);
        $this->withHeaders(['X-EPR-Operation-Key' => $foreignReceipt->operationKey, 'X-EPR-Operation-Type' => CreateEquipmentAction::OPERATION])
            ->get('/gestion/materiels/'.$receipt['resourceId'])->assertOk()->assertInertia(fn (Assert $page) => $page->where('commandReceipt', null));
        $this->get('/gestion/materiels/'.$foreignReceipt->resourceId)->assertNotFound();
    }

    public function test_operation_read_rechecks_the_manager_capability_without_relying_on_http(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ReadEquipmentOperationAction::class)->handle($this->actor('atelier-1-borrower-1'), CreateEquipmentAction::OPERATION, (string) Str::uuid7());
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidConfirmationReferences(): iterable
    {
        yield 'unknown operation family' => ['lending.confirm', '019a0000-0000-7000-8000-000000000001'];
        yield 'invalid operation identifier' => [CreateEquipmentAction::OPERATION, 'not-a-uuid'];
        yield 'missing operation family' => ['', '019a0000-0000-7000-8000-000000000001'];
    }

    #[DataProvider('invalidConfirmationReferences')]
    public function test_malformed_confirmation_headers_are_rejected(string $type, string $key): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        $this->withHeaders(['X-EPR-Operation-Key' => $key, 'X-EPR-Operation-Type' => $type])
            ->getJson('/gestion/materiels/'.$equipment->id)->assertUnprocessable()->assertJsonPath('issue.errorCode', 'validation.invalid_input');
    }

    public function test_same_creation_is_replayed_but_changed_content_conflicts_without_disclosing_the_original(): void
    {
        $this->prepare();
        $input = $this->input();
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $first = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $second = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($first['resourceId'], $second['resourceId']);
        $this->assertTrue($second['replayed']);
        $this->assertSame(1, DB::table('catalog_equipment')->where('id', $first['resourceId'])->count());
        $this->postJson('/gestion/materiels', [...$input, 'name' => 'Different'])->assertConflict()->assertJsonPath('issue.errorCode', 'operations.intent_mismatch')->assertJsonMissing(['name' => 'Perceuse %_ électrique']);
    }

    public function test_text_edit_changes_neither_total_nor_state_and_replays_its_original_result_after_another_edit(): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'total_quantity' => 5]);
        $first = ['name' => 'Nouveau nom', 'category' => 'Classement', 'description' => '', 'expectedVersion' => '1', 'operationKey' => (string) Str::uuid7()];
        $this->patch('/gestion/materiels/'.$equipment->id, $first)->assertRedirect();
        $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'name' => 'Nouveau nom', 'description' => null, 'version' => 2, 'total_quantity' => 5, 'is_active' => true]);
        $this->patch('/gestion/materiels/'.$equipment->id, [...$first, 'name' => 'Autre nom', 'expectedVersion' => 2, 'operationKey' => (string) Str::uuid7()])->assertRedirect();
        $this->patch('/gestion/materiels/'.$equipment->id, $first)->assertRedirect();
        $this->assertSame(2, json_decode(session('catalogReceipt'), true)['resultVersion']);
        $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'name' => 'Autre nom', 'version' => 3]);
    }

    public function test_a_stale_version_preserves_the_current_row_and_has_no_success_receipt(): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'version' => 2]);
        $key = (string) Str::uuid7();
        $this->patchJson('/gestion/materiels/'.$equipment->id, ['name' => 'Refusé', 'category' => 'Outillage', 'description' => null, 'expectedVersion' => 1, 'operationKey' => $key])->assertConflict()->assertJsonPath('issue.errorCode', 'catalog.content_conflict');
        $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'name' => $equipment->name, 'version' => 2]);
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidInputs(): iterable
    {
        yield 'empty name' => ['name', '   '];
        yield 'Unicode whitespace name' => ['name', "\u{00a0}\u{2009}\u{3000}"];
        yield 'NUL description' => ['description', "invalid\0text"];
        yield 'long Unicode name' => ['name', str_repeat('é', 121)];
        yield 'missing category' => ['category', ''];
        yield 'long description' => ['description', str_repeat('é', 2001)];
        foreach ([-1, 1000000, true, 1.5, '1e2', '2.0', 'two'] as $index => $quantity) {
            yield 'quantity '.$index => ['totalQuantity', $quantity];
        }
        yield 'unknown key' => ['workshopId', '019a0000-0000-7000-8000-000000000001'];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_before_any_equipment_write(string $field, mixed $value): void
    {
        $this->prepare();
        $input = [...$this->input(), $field => $value];
        $this->postJson('/gestion/materiels', $input)->assertUnprocessable()->assertJsonPath('issue.errorCode', 'validation.invalid_input');
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $input['operationKey']]);
    }

    public function test_text_command_rejects_a_total_or_state_injected_into_its_payload(): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        $this->patchJson('/gestion/materiels/'.$equipment->id, ['name' => 'Changed', 'category' => 'Tools', 'description' => null, 'expectedVersion' => 1, 'operationKey' => (string) Str::uuid7(), 'totalQuantity' => 999])->assertUnprocessable();
        $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'version' => 1, 'total_quantity' => 2]);
    }

    public function test_a_sql_failure_after_the_first_equipment_write_leaves_neither_equipment_nor_receipt(): void
    {
        $this->prepare();
        $input = [...$this->input(), 'name' => '__a03_rollback_after_insert_'.Str::uuid7()];
        $armed = true;
        DB::listen(static function (QueryExecuted $query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'insert into "catalog_equipment"')) {
                $armed = false;
                DB::select('SELECT 1 / 0');
            }
        });
        $this->postJson('/gestion/materiels', $input)->assertStatus(500)->assertJsonPath('issue.errorCode', 'operations.unexpected_failure');
        $this->assertFalse($armed);
        $this->assertDatabaseMissing('catalog_equipment', ['name' => $input['name']]);
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $input['operationKey']]);
    }

    public function test_staff_cannot_read_management_or_create_equipment_by_direct_http(): void
    {
        $this->prepare('atelier-1-staff');
        $this->get('/gestion/materiels')->assertForbidden();
        $this->getJson('/gestion/materiels/lectures')->assertForbidden();
        $this->post('/gestion/materiels', $this->input())->assertForbidden();
    }

    #[DataProvider('nonManagers')]
    public function test_summary_reads_require_the_manager_capability_for_every_supplied_audience(string $login): void
    {
        $actor = $this->prepare($login);

        foreach ([CatalogAudience::Catalog, CatalogAudience::Management] as $audience) {
            $this->assertThrows(fn () => app(ReadEquipmentAction::class)->managementPage($actor, new EquipmentQuery(audience: $audience)), AuthorizationException::class);
        }
        $this->getJson('/gestion/materiels/lectures')->assertForbidden();
    }

    public function test_summary_reads_obey_the_management_policy_for_direct_and_http_calls(): void
    {
        $actor = $this->prepare();
        Gate::before(
            /** @param array<array-key, mixed> $arguments */
            static function (?Authenticatable $user, string $ability, array $arguments): ?bool {
                return $ability === 'viewAny' && ($arguments[0] ?? null) === Equipment::class ? false : null;
            });

        $this->assertThrows(fn () => app(ReadEquipmentAction::class)->managementPage($actor, new EquipmentQuery), AuthorizationException::class);
        $this->get('/gestion/materiels')->assertForbidden();
        $this->getJson('/gestion/materiels/lectures')->assertForbidden();
    }

    public function test_read_operation_recovers_a_committed_result_but_absence_does_not_claim_rollback(): void
    {
        $this->prepare();
        $input = $this->input();
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $query = ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $input['operationKey']];
        $this->getJson('/gestion/materiels/operations?'.http_build_query($query))->assertOk()->assertJsonPath('status', 'applied')->assertJsonPath('receipt.operationKey', $input['operationKey']);
        $query['operationKey'] = (string) Str::uuid7();
        $this->getJson('/gestion/materiels/operations?'.http_build_query($query))->assertOk()->assertExactJson(['status' => 'notEstablished', 'receipt' => null]);
    }

    public function test_closing_an_unreceived_creation_returns_a_durable_closure_without_equipment_or_notification(): void
    {
        $actor = $this->prepare();
        $this->travelTo('2026-10-26T12:30:00.123Z');
        Queue::fake([PublishCatalogInvalidation::class]);
        $payload = ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => (string) Str::uuid7()];
        $equipmentBefore = DB::table('catalog_equipment')->count();
        $expected = ['status' => 'closed', 'receipt' => null, 'closure' => [...$payload, 'closedAt' => '2026-10-26T12:30:00.123Z']];

        $this->postJson('/gestion/materiels/operations/cloture', $payload)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson($expected);
        $this->postJson('/gestion/materiels/operations/cloture', $payload)->assertOk()->assertExactJson($expected);
        $this->getJson('/gestion/materiels/operations?'.http_build_query($payload))->assertOk()->assertExactJson($expected);

        $this->assertSame($equipmentBefore, DB::table('catalog_equipment')->count());
        $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $payload['operationKey'])->count());
        $this->assertDatabaseHas('operation_receipts', ['workshop_id' => $actor->workshopId, 'actor_id' => $actor->actorId, 'operation_key' => $payload['operationKey'], 'status' => 'closed', 'input_sha256' => null, 'resource_type' => null, 'resource_id' => null, 'equipment_id' => null, 'result_version' => null]);
        Queue::assertNothingPushed();
    }

    public function test_resolution_finds_the_original_success_without_closing_or_changing_its_equipment(): void
    {
        $this->prepare();
        $input = $this->input();
        $this->post('/gestion/materiels', $input)->assertRedirect();
        $receipt = json_decode(session('catalogReceipt'), true, flags: JSON_THROW_ON_ERROR);
        Queue::fake([PublishCatalogInvalidation::class]);

        $this->postJson('/gestion/materiels/operations/cloture', ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $input['operationKey']])
            ->assertOk()->assertExactJson(['status' => 'applied', 'receipt' => [...$receipt, 'replayed' => true]]);

        $this->assertDatabaseHas('catalog_equipment', ['id' => $receipt['resourceId'], 'version' => 1, 'name' => 'Perceuse %_ électrique']);
        $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $input['operationKey'])->count());
        $this->assertDatabaseHas('operation_receipts', ['operation_key' => $input['operationKey'], 'status' => 'applied', 'resource_id' => $receipt['resourceId']]);
        Queue::assertNothingPushed();
    }

    public function test_a_late_creation_with_a_closed_key_gets_409_and_cannot_create_equipment(): void
    {
        $this->prepare();
        $input = [...$this->input(), 'name' => 'Late creation '.Str::uuid7()];
        $payload = ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $input['operationKey']];
        $closed = $this->postJson('/gestion/materiels/operations/cloture', $payload)->assertOk()->json();
        Queue::fake([PublishCatalogInvalidation::class]);

        $this->postJson('/gestion/materiels', $input)->assertConflict()
            ->assertJsonPath('issue.errorCode', 'operations.intent_closed')->assertJsonPath('issue.outcome', 'notApplied')->assertJsonPath('issue.nextAction.kind', 'reload');

        $this->assertDatabaseMissing('catalog_equipment', ['name' => $input['name']]);
        $this->getJson('/gestion/materiels/operations?'.http_build_query($payload))->assertOk()->assertExactJson($closed);
        Queue::assertNothingPushed();
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidClosures(): iterable
    {
        yield 'other command family' => [['operationType' => EditEquipmentTextAction::OPERATION]];
        yield 'invalid key' => [['operationKey' => 'not-a-uuid']];
        yield 'noncanonical key' => [['operationKey' => '019A000A-0000-7000-8000-000000000001']];
        yield 'missing family' => [['operationType' => null]];
        yield 'missing key' => [['operationKey' => null]];
        yield 'client actor' => [['actorId' => '019a0000-0000-7000-8000-000000000001']];
        yield 'client workshop' => [['workshopId' => '019a0000-0000-7000-8000-000000000001']];
        yield 'content reconstruction' => [['name' => 'Reconstructed content']];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidClosures')]
    public function test_invalid_closure_inputs_get_422_without_recording_a_terminal_outcome(array $changes): void
    {
        $this->prepare();
        $key = (string) Str::uuid7();
        $before = DB::table('operation_receipts')->count();

        $this->postJson('/gestion/materiels/operations/cloture', [...['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $key], ...$changes])
            ->assertUnprocessable()->assertJsonPath('issue.errorCode', 'validation.invalid_input');

        $this->assertSame($before, DB::table('operation_receipts')->count());
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
    }

    public function test_closure_requires_a_current_session_and_does_not_record_an_anonymous_request(): void
    {
        $key = (string) Str::uuid7();

        $this->postJson('/gestion/materiels/operations/cloture', ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $key])->assertUnauthorized();

        app(SqlUnit::class)->forActor($this->actor(), function () use ($key): void {
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        });
    }

    /** @return iterable<string, array{string}> */
    public static function nonManagers(): iterable
    {
        yield 'staff' => ['atelier-1-staff'];
        yield 'borrower' => ['atelier-1-borrower-1'];
    }

    #[DataProvider('nonManagers')]
    public function test_a_non_manager_cannot_close_a_creation_by_http_or_direct_action(string $login): void
    {
        $actor = $this->prepare($login);
        $key = (string) Str::uuid7();

        $this->postJson('/gestion/materiels/operations/cloture', ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $key])->assertForbidden();

        try {
            app(CloseEquipmentOperationAction::class)->handle($actor, $key);
            $this->fail('Closure did not consult the current capability.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        }
    }

    public function test_closure_consults_the_registered_policy_without_recording_a_refused_operation(): void
    {
        $actor = $this->prepare();
        $key = (string) Str::uuid7();
        Gate::before(static function (?Authenticatable $user, string $ability): ?bool {
            return $ability === 'create' ? false : null;
        });

        try {
            app(CloseEquipmentOperationAction::class)->handle($actor, $key);
            $this->fail('Closure did not consult the equipment policy.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        }
    }

    public function test_resolution_cannot_read_or_close_another_managers_success(): void
    {
        $actor = $this->prepare();
        $foreign = $this->actor('atelier-2-manager');
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true), set_config('epr.actor_role','manager',true)", [$foreign->workshopId, $foreign->actorId]);
        $receipt = app(CreateEquipmentAction::class)->handle($foreign, new EquipmentInput('Foreign closure fixture', 'Synthetic', null), 0, (string) Str::uuid7());
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true)", [$actor->workshopId, $actor->actorId]);
        $payload = ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => $receipt->operationKey];

        $own = $this->postJson('/gestion/materiels/operations/cloture', $payload)->assertOk()->assertJsonPath('status', 'closed')->assertJsonPath('receipt', null)->json();

        $this->assertSame($receipt->operationKey, $own['closure']['operationKey']);
        $this->assertDatabaseHas('operation_receipts', ['actor_id' => $actor->actorId, 'operation_key' => $receipt->operationKey, 'status' => 'closed']);
        $this->assertDatabaseMissing('operation_receipts', ['actor_id' => $foreign->actorId, 'operation_key' => $receipt->operationKey]);
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true)", [$foreign->workshopId, $foreign->actorId]);
        $this->assertDatabaseHas('operation_receipts', ['actor_id' => $foreign->actorId, 'operation_key' => $receipt->operationKey, 'status' => 'applied', 'resource_id' => $receipt->resourceId]);
        $this->assertDatabaseHas('catalog_equipment', ['id' => $receipt->resourceId]);
    }

    public function test_the_twenty_closure_limit_returns_429_but_keeps_the_read_endpoint_available(): void
    {
        $this->prepare();
        $payload = ['operationType' => CreateEquipmentAction::OPERATION, 'operationKey' => (string) Str::uuid7()];
        $closed = null;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $closed = $this->postJson('/gestion/materiels/operations/cloture', $payload)->assertOk()->json();
        }

        $this->postJson('/gestion/materiels/operations/cloture', $payload)->assertTooManyRequests()->assertHeader('Retry-After');

        $this->getJson('/gestion/materiels/operations?'.http_build_query($payload))->assertOk()->assertExactJson($closed);
        $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $payload['operationKey'])->count());
    }

    public function test_literal_case_insensitive_search_and_exact_category_preserve_stable_pagination(): void
    {
        $actor = $this->prepare();
        $rows = [];
        foreach (['Perceuse %_ ÉLECTRIQUE', 'Perceuse %_ ÉLECTRIQUE', 'Perceuse simple', 'Perceuse %_ ÉLECTRIQUE'] as $index => $name) {
            $rows[] = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'name' => $name, 'category' => $index === 3 ? 'Other' : 'Outillage']);
        }
        $read = app(ReadEquipmentPage::class);
        $first = $read->handle($actor, new EquipmentQuery('%_ éle', 'Outillage', 1, 1, CatalogAudience::Management));
        $second = $read->handle($actor, new EquipmentQuery('%_ éle', 'Outillage', 2, 1, CatalogAudience::Management));
        $this->assertTrue($first->hasMore);
        $this->assertFalse($second->hasMore);
        $ids = [$rows[0]->id, $rows[1]->id];
        sort($ids);
        $this->assertSame($ids, [$first->items[0]->equipmentId, $second->items[0]->equipmentId]);
        $this->assertSame([], $read->handle($actor, new EquipmentQuery('%_ éle', 'outillage', audience: CatalogAudience::Management))->items);
        $summary = app(ReadEquipmentAction::class);
        $summaryFirst = $summary->managementPage($actor, new EquipmentQuery('%_ éle', 'Outillage', 1, 1));
        $summarySecond = $summary->managementPage($actor, new EquipmentQuery('%_ éle', 'Outillage', 2, 1));
        $this->assertSame($ids, [$summaryFirst->items[0]->equipmentId, $summarySecond->items[0]->equipmentId]);
        $this->assertTrue($summaryFirst->hasMore);
        $this->assertFalse($summarySecond->hasMore);
        $this->assertSame([1, 2, 1, 1], [$summaryFirst->page, $summarySecond->page, $summaryFirst->perPage, $summarySecond->perPage]);
        $this->assertSame([], $summary->managementPage($actor, new EquipmentQuery('%_ éle', 'outillage'))->items);
        $this->assertSame([], $summary->managementPage($actor, new EquipmentQuery('absent-fragment', 'Outillage'))->items);
        $this->getJson('/gestion/materiels/lectures?perPage=101')->assertUnprocessable();
    }

    public function test_rls_hides_a_real_foreign_workshop_reference_and_receipts_are_immutable(): void
    {
        $actor = $this->prepare();
        $foreign = Accounts::user('atelier-2-manager');
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true), set_config('epr.actor_role','manager',true)", [$foreign['workshopId'], $foreign['id']]);
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $foreign['workshopId']]);
        DB::select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_id', ?, true)", [$actor->workshopId, $actor->actorId]);
        $this->assertSame([], app(ReadEquipmentBatch::class)->handle($actor, [$equipment->id]));
        $this->assertSame([], app(ReadEquipmentAction::class)->managementPage($actor, new EquipmentQuery(category: $equipment->category))->items);
        $this->getJson('/gestion/materiels/lectures?'.http_build_query(['category' => $equipment->category]))->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/gestion/materiels/'.$equipment->id.'/lecture')->assertNotFound()->assertJsonPath('issue.errorCode', 'identity_access.resource_unavailable');
        $this->getJson('/gestion/materiels/'.Str::uuid7().'/lecture')->assertNotFound()->assertJsonPath('issue.errorCode', 'identity_access.resource_unavailable');
        $permissions = DB::selectOne("SELECT has_table_privilege(current_user,'epr.operation_receipts','UPDATE') AS receipt_update, has_table_privilege(current_user,'epr.operation_receipts','DELETE') AS receipt_delete, has_table_privilege(current_user,'epr.catalog_equipment','DELETE') AS equipment_delete");
        $this->assertFalse($permissions->receipt_update);
        $this->assertFalse($permissions->receipt_delete);
        $this->assertFalse($permissions->equipment_delete);
    }

    public function test_missing_context_and_invalid_quantities_are_refused_by_sql_itself(): void
    {
        $actor = $this->prepare();
        $this->assertSqlRejects(fn () => EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'total_quantity' => -1]), '23514');
        $this->assertSqlRejects(fn () => EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'name' => "\u{00a0}\u{2009}\u{3000}"]), '23514');
        $this->assertSqlRejects(function () use ($actor): void {
            DB::select("SELECT set_config('epr.actor_id','',true)");
            EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        }, '42501');
        $connection = DB::connectUsing('catalog_missing_actor', config('database.connections.pgsql'));
        try {
            $connection->beginTransaction();
            $connection->select("SELECT set_config('epr.workshop_id', ?, true), set_config('epr.actor_role', 'manager', true)", [$actor->workshopId]);
            $this->assertNull($connection->selectOne("SELECT current_setting('epr.actor_id', true) AS actor")->actor);
            $this->assertSqlRejects(function () use ($actor): void {
                EquipmentFactory::new()->makeOne(['workshop_id' => $actor->workshopId])->setConnection('catalog_missing_actor')->save();
            }, '42501');
        } finally {
            $connection->rollBack();
            DB::purge('catalog_missing_actor');
        }
    }

    public function test_saturated_versions_and_cross_workshop_receipt_links_are_refused_without_partial_changes(): void
    {
        $actor = $this->prepare();
        $equipment = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'version' => 2147483647]);
        $key = (string) Str::uuid7();
        $this->patchJson('/gestion/materiels/'.$equipment->id, ['name' => 'Refusé', 'category' => 'Outillage', 'description' => null, 'expectedVersion' => 2147483647, 'operationKey' => $key])->assertStatus(500)->assertJsonPath('issue.errorCode', 'catalog.version_exhausted')->assertJsonPath('issue.outcome', 'notApplied');
        $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        $this->assertDatabaseHas('catalog_equipment', ['id' => $equipment->id, 'version' => 2147483647, 'name' => $equipment->name]);
        $foreign = Accounts::user('atelier-2-manager');
        $this->assertSqlRejects(fn () => DB::table('operation_receipts')->insert(['id' => (string) Str::uuid7(), 'workshop_id' => $actor->workshopId, 'actor_id' => $foreign['id'], 'operation_type' => 'catalog.equipment.create', 'operation_key' => (string) Str::uuid7(), 'input_sha256' => str_repeat('a', 64), 'resource_type' => 'equipment', 'resource_id' => $equipment->id, 'equipment_id' => $equipment->id, 'result_version' => 1, 'recorded_at' => now()]), '42501');
    }

    public function test_public_pages_and_batches_keep_one_equipment_select_for_one_or_one_hundred_rows(): void
    {
        $actor = $this->prepare();
        $category = '__a03_cost_'.Str::uuid7();
        $description = 'Description complète sentinelle 🧰';
        $rows = EquipmentFactory::new()->count(101)->create(['workshop_id' => $actor->workshopId, 'category' => $category, 'description' => $description]);
        $ids = $rows->pluck('id')->all();
        $connection = DB::connection('pgsql');
        $connection->enableQueryLog();
        try {
            $counts = [];
            foreach ([1, 100] as $limit) {
                $connection->flushQueryLog();
                $page = app(ReadEquipmentPage::class)->handle($actor, new EquipmentQuery(category: $category, perPage: $limit, audience: CatalogAudience::Management));
                $this->assertCount($limit, $page->items);
                $this->assertTrue($page->hasMore);
                $this->assertSame(array_fill(0, $limit, $description), array_column($page->items, 'description'));
                $queries = $connection->getQueryLog();
                $equipmentQueries = array_values(array_filter($queries, static fn (array $query): bool => str_contains($query['query'], 'from "catalog_equipment"')));
                $this->assertCount(1, $equipmentQueries);
                $counts[] = count($queries);
                $before = count($queries);
                json_encode($page, JSON_THROW_ON_ERROR);
                $this->assertCount($before, $connection->getQueryLog());
                $connection->flushQueryLog();
                $batch = app(ReadEquipmentBatch::class)->handle($actor, array_slice($ids, 0, $limit));
                $this->assertCount($limit, $batch);
                $this->assertSame(array_fill(0, $limit, $description), array_column($batch, 'description'));
                $this->assertCount(1, array_filter($connection->getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'from "catalog_equipment"')));
            }
            $this->assertSame($counts[0], $counts[1]);
            $plans = [];
            foreach (['', 'absent-fragment', '%_', 'é'] as $fragment) {
                $connection->flushQueryLog();
                app(ReadEquipmentPage::class)->handle($actor, new EquipmentQuery($fragment, $category, 5, 25, CatalogAudience::Management));
                $query = array_values(array_filter($connection->getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'from "catalog_equipment"')))[0];
                $plans[] = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query['query'], $query['bindings']);
            }
            file_put_contents(base_path('.epr-local/catalog-read-cost.json'), json_encode(['role' => DB::selectOne('SELECT current_user AS role')->role, 'fixtureRows' => 101, 'pageReadStatements' => $counts, 'equipmentSelectsPerPage' => 1, 'plans' => $plans, 'fullVolumeQualified' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } finally {
            $connection->disableQueryLog();
        }
    }

    #[DataProvider('managementOutputs')]
    public function test_management_pages_select_and_hydrate_only_summary_fields(bool $json): void
    {
        $actor = $this->prepare();
        $category = '__af07_projection_'.Str::uuid7();
        $rows = EquipmentFactory::new()->sequence(['is_active' => false], ['is_active' => true])->state([
            'workshop_id' => $actor->workshopId,
            'name' => 'Synthetic summary',
            'category' => $category,
            'description' => str_repeat('🧰', 2000),
        ])->createMany(101);
        $first = $rows->sortBy('id')->firstOrFail();
        $connection = DB::connection('pgsql');
        $hydrated = $this->hydrationCapture();
        Equipment::retrieved(static function (Equipment $equipment) use ($connection, $hydrated): void {
            if ($connection->logging()) {
                $hydrated->append(array_keys($equipment->getAttributes()));
            }
        });
        $expectedColumns = ['category', 'id', 'is_active', 'name', 'total_quantity', 'version', 'workshop_id'];
        $connection->enableQueryLog();
        try {
            foreach ([1, 25, 100] as $size) {
                $connection->flushQueryLog();
                $hydrated->exchangeArray([]);
                $url = ($json ? '/gestion/materiels/lectures' : '/gestion/materiels').'?'.http_build_query(['category' => $category, 'perPage' => $size]);
                $response = ($json ? $this->getJson($url) : $this->get($url))->assertOk();
                $queries = array_values(array_filter($connection->getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'from "catalog_equipment"')));
                $this->assertCount(1, $queries);
                $this->assertStringNotContainsString('description', $queries[0]['query']);
                $this->assertStringNotContainsString('*', $queries[0]['query']);
                $this->assertGreaterThanOrEqual($size, count($hydrated));
                $this->assertLessThanOrEqual($size + 1, count($hydrated));
                foreach ($hydrated as $columns) {
                    sort($columns);
                    $this->assertSame($expectedColumns, $columns);
                }
                $payload = $json ? $response->json() : json_decode(json_encode($response->viewData('page')['props']['catalogPage'], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
                $this->assertCount($size, $payload['items']);
                $this->assertSame(1, $payload['page']);
                $this->assertSame($size, $payload['perPage']);
                $this->assertTrue($payload['hasMore']);
                $this->assertSame(['equipmentId', 'name', 'category', 'totalQuantity', 'status', 'equipmentVersion', 'image'], array_keys($payload['items'][0]));
                $this->assertSame($first->id, $payload['items'][0]['equipmentId']);
                $this->assertSame($first->is_active ? 'active' : 'archived', $payload['items'][0]['status']);
            }

            $connection->flushQueryLog();
            $page = app(ReadEquipmentAction::class)->managementPage($actor, new EquipmentQuery(category: $category, perPage: 100));
            $this->assertCount(100, $page->items);
            $this->assertContains('archived', array_column($page->items, 'status'));
            $before = count($connection->getQueryLog());
            json_encode($page, JSON_THROW_ON_ERROR);
            $this->assertCount($before, $connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
        }
    }

    /** @return ArrayObject<int, list<string>> */
    private function hydrationCapture(): ArrayObject
    {
        return new ArrayObject;
    }

    /** @return array<string, array{bool}> */
    public static function managementOutputs(): array
    {
        return ['inertia' => [false], 'json' => [true]];
    }

    public function test_the_browser_list_sends_only_its_selected_fields_within_the_unicode_payload_budgets(): void
    {
        $actor = $this->prepare();
        $category = str_repeat('é', 80);
        EquipmentFactory::new()->count(100)->create(['workshop_id' => $actor->workshopId, 'name' => str_repeat('🧰', 120), 'category' => $category, 'description' => str_repeat('🧰', 2000)]);
        foreach ([25 => 128 * 1024, 100 => 512 * 1024] as $size => $budget) {
            $response = $this->getJson('/gestion/materiels/lectures?'.http_build_query(['category' => $category, 'perPage' => $size]));
            $response->assertOk()->assertJsonCount($size, 'items');
            $content = $response->getContent();
            $this->assertIsString($content);
            $this->assertLessThanOrEqual($budget, strlen($content));
            $response->assertJsonMissingPath('items.0.description');
        }
    }

    private function assertSqlRejects(\Closure $write, string $state): void
    {
        try {
            DB::transaction(function () use ($write): void {
                $write();
            });
            $this->fail('SQL accepted an invalid write.');
        } catch (QueryException $error) {
            $this->assertSame($state, ($error->errorInfo[0] ?? null));
        }
    }
}
