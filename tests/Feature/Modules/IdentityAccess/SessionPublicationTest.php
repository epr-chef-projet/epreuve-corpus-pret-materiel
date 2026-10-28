<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\PublicApi\AdmitSessionPublication;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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
}
