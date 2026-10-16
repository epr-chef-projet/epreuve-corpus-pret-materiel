<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\PublicApi\AdmitSessionPublication;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use App\Shared\Database\SqlUnit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class SessionPublicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'redis', 'session.connection' => 'sessions', 'identity_access.service_id' => 'test-publisher', 'identity_access.service_capabilities' => ['identity_access.publish']]);
        app('session')->forgetDrivers();
        Auth::forgetGuards();
    }

    public function test_no_route_is_admitted_when_the_real_session_store_is_unreachable(): void
    {
        Accounts::signIn($this);
        $user = Accounts::user();
        $id = session()->getId();
        $token = session('identity_access.broadcast_token');
        $originalHandler = app('session')->driver()->getHandler();
        config(['database.redis.sessions.host' => '127.0.0.1', 'database.redis.sessions.port' => 1]);
        app()->forgetInstance('redis');
        Redis::clearResolvedInstance('redis');
        Cache::forgetDriver('redis');
        app('session')->forgetDrivers();
        try {
            $service = new ServiceContext('test-publisher', $user['workshopId'], 'identity_access.publish');
            $this->assertNull(app(AdmitSessionPublication::class)->handle($service, $user['id'], 'borrower', $token));
        } finally {
            $originalHandler->destroy($id);
            app(SessionRepository::class)->revoke($id);
        }
    }

    public function test_admits_only_the_authorized_recipient_and_workshop_while_the_real_redis_session_is_alive(): void
    {
        /** @var Redis $redis */
        $redis = Redis::connection('sessions');
        expect($redis->client()->getDBNum())->toBe(12);
        Accounts::signIn($this);
        $user = Accounts::user();
        $token = session('identity_access.broadcast_token');
        $this->assertSame([], DB::select('SELECT * FROM epr.identity_access_admit_session(?,?::uuid,?)', [$token, $user['id'], 'borrower']));
        $service = new ServiceContext('test-publisher', $user['workshopId'], 'identity_access.publish');
        $admission = app(AdmitSessionPublication::class);
        expect($admission->handle($service, $user['id'], 'borrower', $token))->toBe(['channel' => 'epr.session.'.$token, 'authGeneration' => 1]);
        expect($admission->handle($service, $user['id'], 'manager', $token))->toBeNull();
        $other = Accounts::user('atelier-2-borrower-1');
        expect($admission->handle(new ServiceContext('test-publisher', $other['workshopId'], 'identity_access.publish'), $user['id'], 'borrower', $token))->toBeNull();
        expect($admission->handle($service, $other['id'], 'borrower', $token))->toBeNull();
        $this->post('/logout')->assertRedirect('/');
        expect($admission->handle($service, $user['id'], 'borrower', $token))->toBeNull();
    }

    public function test_does_not_recreate_a_lost_redis_session_from_sql_metadata(): void
    {
        Accounts::signIn($this);
        $user = Accounts::user();
        $id = session()->getId();
        $token = session('identity_access.broadcast_token');
        $handler = app('session')->driver()->getHandler();
        $handler->destroy($id);
        expect(fn () => app(ResolveActor::class)->handle(request()))->toThrow(AuthenticationException::class);
        $service = new ServiceContext('test-publisher', $user['workshopId'], 'identity_access.publish');
        expect(app(AdmitSessionPublication::class)->handle($service, $user['id'], 'borrower', $token))->toBeNull();
        expect($handler->read($id))->toBe('');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_serializes_a_concurrent_revocation_behind_publication_admission_on_the_same_sql_row(): void
    {
        Accounts::signIn($this);
        $user = Accounts::user();
        $sessionId = session()->getId();
        $token = session('identity_access.broadcast_token');
        $service = new ServiceContext('test-publisher', $user['workshopId'], 'identity_access.publish');
        $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/IdentityAccess/revoke-session.php')], base_path(), array_filter($_ENV, is_string(...)));
        $worker->setInput(hash_hmac('sha256', $sessionId, config('app.key')));
        $worker->setTimeout(15);
        try {
            $route = app(SqlUnit::class)->forService($service, function ($connection) use ($worker, $user, $token, $service): ?array {
                app(SessionRepository::class)->lockCandidate($user['id'], 'borrower', $token);
                $worker->start();
                $deadline = microtime(true) + 8;
                $blocked = false;
                do {
                    $output = $worker->getOutput();
                    if (str_contains($output, "\n")) {
                        $backend = (int) explode("\n", $output)[0];
                        $blocked = $connection->selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [$backend])->blocked;
                    }
                    if ($blocked) {
                        break;
                    }
                    $this->assertTrue($worker->isRunning(), 'Revocation process ended before arbitration.');
                    usleep(10000);
                } while (microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Revocation did not wait for the publication row lock.');

                return app(AdmitSessionPublication::class)->handle($service, $user['id'], 'borrower', $token);
            });
            $this->assertNotNull($route);
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode());
            $this->assertStringContainsString('committed', $worker->getOutput());
            $this->assertNull(app(AdmitSessionPublication::class)->handle($service, $user['id'], 'borrower', $token));
        } finally {
            $worker->stop(2);
            app('session')->driver()->getHandler()->destroy($sessionId);
        }
    }
}
