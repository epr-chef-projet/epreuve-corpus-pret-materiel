<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = ['pgsql', 'auth_lookup'];

    public function createApplication(): Application
    {
        if (getenv('APP_CONFIG_CACHE') || is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Inspect the Laravel configuration cache before running tests.');
        }
        $app = parent::createApplication();
        if ($app->environment() !== 'testing' || $app->configurationIsCached()) {
            throw new RuntimeException('Unsafe test environment or configuration cache.');
        }
        foreach (['driver' => 'pgsql', 'host' => 'pgsql', 'port' => '5432', 'database' => 'testing', 'username' => 'epr_app', 'search_path' => 'epr'] as $key => $expected) {
            if ((string) config('database.connections.pgsql.'.$key) !== $expected) {
                throw new RuntimeException('Unexpected test SQL configuration: '.$key);
            }
        }
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.url')) {
            throw new RuntimeException('Tests require the dedicated PostgreSQL database without a SQL URL override.');
        }
        $identity = DB::selectOne("SELECT session_user, current_user, current_database() AS database, current_schema() AS schema, host(inet_server_addr()) AS address, rolsuper, rolbypassrls, pg_has_role(current_user, 'epr_owner', 'MEMBER') AS owner_member FROM pg_roles WHERE rolname = current_user");
        if ($identity === null || $identity->session_user !== 'epr_app' || $identity->current_user !== 'epr_app' || $identity->database !== 'testing' || $identity->schema !== 'epr' || ! in_array($identity->address, gethostbynamel('pgsql') ?: [], true) || $identity->rolsuper || $identity->rolbypassrls || $identity->owner_member) {
            throw new RuntimeException('Unexpected actual test SQL identity.');
        }
        if ($app->storagePath() !== $app->basePath('storage/framework/testing')) {
            throw new RuntimeException('Tests require their dedicated storage directory.');
        }
        foreach (['app/private/pdf-assets', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'fonts'] as $directory) {
            $path = $app->storagePath($directory);
            if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
                throw new RuntimeException('Could not prepare test storage.');
            }
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.ssr.enabled' => false]);
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
