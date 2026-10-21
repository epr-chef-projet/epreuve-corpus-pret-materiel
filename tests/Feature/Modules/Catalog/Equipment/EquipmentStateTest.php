<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog\Equipment;

use App\Infrastructure\Operations\IntentMismatch;
use App\Modules\Catalog\Database\Factories\EquipmentFactory;
use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipmentOperation\ReadEquipmentOperationAction;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use App\Modules\Catalog\PublicApi\Errors\ContentConflict;
use App\Modules\Catalog\PublicApi\Errors\VersionExhausted;
use App\Modules\Catalog\PublicApi\ReadEquipmentPage;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class EquipmentStateTest extends TestCase
{
    use DatabaseTransactions;

    private function actor(string $login = 'atelier-1-manager'): ActorContext
    {
        $user = Accounts::user($login);

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    public function test_archive_reactivate_and_repeated_intention_preserve_text_total_and_initial_receipt(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $actor = $this->actor();
        app(SqlUnit::class)->forActor($actor, function () use ($actor): void {
            $row = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'name' => 'Kept name', 'description' => 'Kept description', 'category' => 'Kept category', 'total_quantity' => 7]);
            $action = app(ChangeEquipmentStateAction::class);
            $key = (string) Str::uuid7();
            $archive = $action->handle($actor, $row->id, $action::ARCHIVE, 1, $key);
            $this->assertSame(2, $archive->resultVersion);
            $this->assertFalse($archive->replayed);
            $this->assertSame([], app(ReadEquipmentPage::class)->handle($actor, new EquipmentQuery('Kept name', null, 1, 100, CatalogAudience::Catalog))->items);
            $this->assertCount(1, app(ReadEquipmentPage::class)->handle($actor, new EquipmentQuery('Kept name', null, 1, 100, CatalogAudience::Management))->items);
            $reactivate = $action->handle($actor, $row->id, $action::REACTIVATE, 2, (string) Str::uuid7());
            $this->assertSame(3, $reactivate->resultVersion);
            $repeat = $action->handle($actor, $row->id, $action::ARCHIVE, 1, $key);
            $this->assertTrue($repeat->replayed);
            $this->assertSame(2, $repeat->resultVersion);
            $this->assertSame($archive->recordedAt, $repeat->recordedAt);
            $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'is_active' => true, 'version' => 3, 'name' => 'Kept name', 'description' => 'Kept description', 'category' => 'Kept category', 'total_quantity' => 7]);
            $this->assertSame(2, DB::table('operation_receipts')->where('equipment_id', $row->id)->count());
            expect(fn () => $action->handle($actor, $row->id, $action::ARCHIVE, 3, $key))->toThrow(IntentMismatch::class);
        });
    }

    public function test_already_current_state_creates_only_a_receipt_but_stale_version_still_conflicts(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $actor = $this->actor();
        app(SqlUnit::class)->forActor($actor, function () use ($actor): void {
            $row = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'version' => 2147483647]);
            $row->refresh();
            $before = $row->getRawOriginal('updated_at');
            $action = app(ChangeEquipmentStateAction::class);
            $receipt = $action->handle($actor, $row->id, $action::REACTIVATE, 2147483647, (string) Str::uuid7());
            $this->assertSame(2147483647, $receipt->resultVersion);
            $row->refresh();
            $this->assertSame($before, $row->getRawOriginal('updated_at'));
            Queue::assertNothingPushed();
            expect(fn () => $action->handle($actor, $row->id, $action::REACTIVATE, 1, (string) Str::uuid7()))->toThrow(ContentConflict::class);
            expect(fn () => $action->handle($actor, $row->id, $action::ARCHIVE, 2147483647, (string) Str::uuid7()))->toThrow(VersionExhausted::class);
            $this->assertSame(1, DB::table('operation_receipts')->where('equipment_id', $row->id)->count());
            $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'is_active' => true, 'version' => 2147483647]);
        });
    }

    public function test_http_archive_redirect_resolves_the_durable_receipt_even_after_an_intervening_read(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $actor = $this->actor();
        app(SqlUnit::class)->forActor($actor, static function (): void {});
        Accounts::signIn($this, 'atelier-1-manager');
        $this->withCredentials();
        $row = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        $key = (string) Str::uuid7();
        $this->post('/gestion/materiels/'.$row->id.'/archivage', ['expectedVersion' => ' 1 ', 'operationKey' => $key])->assertRedirect('/gestion/materiels/'.$row->id);
        session()->forget('catalogReceipt');
        $this->getJson('/gestion/materiels/lectures')->assertOk();
        $this->withHeaders(['X-EPR-Operation-Key' => $key, 'X-EPR-Operation-Type' => ChangeEquipmentStateAction::ARCHIVE])
            ->get('/gestion/materiels/'.$row->id)->assertInertia(fn (Assert $page) => $page->where('equipment.status', 'archived')->where('commandReceipt.operationKey', $key)->where('commandReceipt.resultVersion', 2));
        $result = app(ReadEquipmentOperationAction::class)->handle($actor, ChangeEquipmentStateAction::ARCHIVE, $key);
        $this->assertSame('applied', $result->status);
        $this->assertSame(2, $result->receipt?->resultVersion);
        $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'is_active' => false, 'version' => 2]);
        $this->withHeaders(['X-EPR-Operation-Key' => '', 'X-EPR-Operation-Type' => ''])->postJson('/gestion/materiels/'.$row->id.'/reactivation', ['expectedVersion' => 1, 'operationKey' => (string) Str::uuid7()])->assertConflict()->assertJsonPath('issue.errorCode', 'catalog.content_conflict');
    }

    /** @return array<string, array{string}> */
    public static function deniedRoles(): array
    {
        return ['borrower' => ['atelier-1-borrower-1'], 'staff' => ['atelier-1-staff']];
    }

    #[DataProvider('deniedRoles')]
    public function test_state_endpoints_reject_other_roles_without_creating_receipts(string $login): void
    {
        Accounts::signIn($this, $login);
        $this->withCredentials();
        foreach (['archivage', 'reactivation'] as $suffix) {
            $this->postJson('/gestion/materiels/'.Str::uuid7().'/'.$suffix, ['expectedVersion' => 1, 'operationKey' => (string) Str::uuid7()])->assertForbidden();
        }
    }

    public function test_missing_and_foreign_equipment_have_the_same_response_and_create_no_receipt(): void
    {
        $actor = $this->actor('atelier-2-manager');
        app(SqlUnit::class)->forActor($actor, static function (): void {});
        $foreign = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        DB::select("SELECT set_config('epr.workshop_id', '', true), set_config('epr.actor_id', '', true), set_config('epr.actor_role', '', true)");
        Accounts::signIn($this, 'atelier-1-manager');
        $this->withCredentials();
        foreach ([$foreign->id, (string) Str::uuid7()] as $id) {
            $key = (string) Str::uuid7();
            $this->postJson('/gestion/materiels/'.$id.'/archivage', ['expectedVersion' => 1, 'operationKey' => $key])->assertNotFound()->assertJsonPath('issue.errorCode', 'identity_access.resource_unavailable');
        }
    }

    public function test_anonymous_and_invalid_state_commands_have_no_effect(): void
    {
        $this->post('/gestion/materiels/'.Str::uuid7().'/archivage', [])->assertRedirect('/login');
        $actor = $this->actor();
        app(SqlUnit::class)->forActor($actor, static function (): void {});
        Accounts::signIn($this, 'atelier-1-manager');
        $this->withCredentials();
        $row = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId]);
        foreach ([[], ['expectedVersion' => true, 'operationKey' => (string) Str::uuid7()], ['expectedVersion' => 0, 'operationKey' => (string) Str::uuid7()], ['expectedVersion' => '1e0', 'operationKey' => (string) Str::uuid7()], ['expectedVersion' => 1, 'operationKey' => 'invalid'], ['expectedVersion' => 1, 'operationKey' => (string) Str::uuid7(), 'is_active' => false]] as $input) {
            $this->postJson('/gestion/materiels/'.$row->id.'/archivage', $input)->assertUnprocessable()->assertJsonPath('issue.errorCode', 'validation.invalid_input');
        }
        $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'is_active' => true, 'version' => 1]);
        $this->assertSame(0, DB::table('operation_receipts')->where('equipment_id', $row->id)->count());
    }
}
