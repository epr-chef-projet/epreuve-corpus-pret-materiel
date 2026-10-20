<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\PublicApi\ReadSessionCandidates;
use App\Modules\IdentityAccess\PublicApi\Role;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class SessionRoutingTest extends TestCase
{
    /** @var list<string> */
    private array $sessionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity_access.service_id' => 'routing-test', 'identity_access.service_capabilities' => ['identity_access.publish']]);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->sessionIds as $id) {
                app(SessionRepository::class)->revoke($id);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createCandidate(string $login): string
    {
        $id = bin2hex(random_bytes(32));
        $this->sessionIds[] = $id;

        return app(SessionRepository::class)->start(Accounts::user($login)['id'], $id);
    }

    private function service(string $login = 'atelier-1-borrower-1'): ServiceContext
    {
        return new ServiceContext('routing-test', Accounts::user($login)['workshopId'], 'identity_access.publish');
    }

    public function test_candidate_pages_are_bounded_to_the_source_workshop_role_and_actor(): void
    {
        $this->createCandidate('atelier-1-borrower-1');
        $this->createCandidate('atelier-1-manager');
        $this->createCandidate('atelier-2-manager');
        $read = app(ReadSessionCandidates::class);
        $page = $read->handle($this->service(), [Role::Manager]);
        $this->assertNotEmpty($page->candidates);
        foreach ($page->candidates as $candidate) {
            $this->assertSame(Accounts::user('atelier-1-manager')['id'], $candidate->actorId);
            $this->assertSame(Role::Manager, $candidate->role);
        }
        $this->assertSame([], $read->handle($this->service(), [Role::Manager], Accounts::user()['id'])->candidates);
        $this->assertSame([], $read->handle($this->service(), [Role::Manager], Accounts::user('atelier-2-manager')['id'])->candidates);
    }

    public function test_cursor_pages_do_not_repeat_a_candidate_and_cannot_change_audience(): void
    {
        $this->createCandidate('atelier-1-borrower-1');
        $this->createCandidate('atelier-1-borrower-1');
        $read = app(ReadSessionCandidates::class);
        $actor = Accounts::user()['id'];
        $first = $read->handle($this->service(), [Role::Borrower], $actor, limit: 1);
        $this->assertCount(1, $first->candidates);
        $this->assertNotNull($first->nextCursor);
        $second = $read->handle($this->service(), [Role::Borrower], $actor, $first->nextCursor, 1);
        $this->assertCount(1, $second->candidates);
        $this->assertNotSame($first->candidates[0]->broadcastToken, $second->candidates[0]->broadcastToken);
        $this->expectException(\InvalidArgumentException::class);
        $read->handle($this->service('atelier-2-manager'), [Role::Borrower], $actor, $first->nextCursor, 1);
    }

    public function test_revocation_removes_our_candidate_and_candidate_listing_is_not_an_admission(): void
    {
        $token = $this->createCandidate('atelier-1-manager');
        $read = app(ReadSessionCandidates::class);
        $page = $read->handle($this->service(), [Role::Manager], Accounts::user('atelier-1-manager')['id']);
        $this->assertContains($token, array_map(fn ($candidate) => $candidate->broadcastToken, $page->candidates));
        app(SessionRepository::class)->revoke($this->sessionIds[0]);
        $page = $read->handle($this->service(), [Role::Manager], Accounts::user('atelier-1-manager')['id']);
        $this->assertNotContains($token, array_map(fn ($candidate) => $candidate->broadcastToken, $page->candidates));
    }

    public function test_no_routing_is_available_without_a_service_context_or_to_the_authentication_sql_identity(): void
    {
        $this->assertSame([], DB::select("SELECT * FROM epr.identity_access_session_candidates('[\"manager\"]'::jsonb, NULL, NULL, 100)"));
        try {
            DB::connection('auth_lookup')->transaction(fn () => DB::connection('auth_lookup')->select("SELECT * FROM epr.identity_access_session_candidates('[\"manager\"]'::jsonb, NULL, NULL, 100)"));
            $this->fail('The authentication identity enumerated session routes.');
        } catch (QueryException $error) {
            $this->assertSame('42501', $error->errorInfo[0] ?? null);
        }
    }

    public function test_a_disabled_process_capability_cannot_enumerate_routes(): void
    {
        config(['identity_access.service_capabilities' => []]);
        $this->expectException(\LogicException::class);
        app(ReadSessionCandidates::class)->handle($this->service(), [Role::Borrower]);
    }

    public function test_page_bounds_and_corrupted_cursors_are_rejected(): void
    {
        foreach ([0, 101] as $limit) {
            try {
                app(ReadSessionCandidates::class)->handle($this->service(), [Role::Borrower], limit: $limit);
                $this->fail('Invalid page size accepted.');
            } catch (\InvalidArgumentException $error) {
                $this->assertSame('Invalid session audience or page size.', $error->getMessage());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        app(ReadSessionCandidates::class)->handle($this->service(), [Role::Borrower], cursor: 'untrusted');
    }
}
