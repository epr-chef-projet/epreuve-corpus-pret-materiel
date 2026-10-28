<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\IdentityAccess\Access;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\PublicApi\AdmitSessionPublication;
use App\Modules\IdentityAccess\PublicApi\ServiceContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class SessionPublicationConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'redis', 'session.connection' => 'sessions', 'identity_access.service_id' => 'test-publisher', 'identity_access.service_capabilities' => ['identity_access.publish']]);
        app('session')->forgetDrivers();
        Auth::forgetGuards();
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
            $route = null;
            app(SqlUnit::class)->forService($service, function () use ($worker, $user, $token, $service, &$route): void {
                $connection = DB::connection('pgsql');
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

                $route = app(AdmitSessionPublication::class)->handle($service, $user['id'], 'borrower', $token);
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
