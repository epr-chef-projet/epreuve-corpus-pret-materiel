<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Modules\IdentityAccess\Identity\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class AccessBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_the_identity_references_without_unused_starter_tables(): void
    {
        foreach (['identity_access_workshops', 'identity_access_users', 'identity_access_auth_sessions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        foreach (['users', 'identity_access_legacy_users', 'passkeys', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasColumn('identity_access_auth_sessions', 'session_key_ciphertext'));
        $this->assertFalse(Schema::hasColumn('identity_access_auth_sessions', 'session_id_ciphertext'));
    }

    public function test_identity_functions_keep_their_owner_safe_search_path_and_exact_sql_audience(): void
    {
        $audiences = [
            'identity_access_find_identity(text)' => 'auth_lookup',
            'identity_access_resolve_identity(uuid,text)' => 'auth_lookup',
            'identity_access_rehash_password(uuid,text,text)' => 'auth_lookup',
            'identity_access_start_session(uuid,uuid,text,text,text)' => 'auth_lookup',
            'identity_access_revoke_session(text)' => 'auth_lookup',
            'identity_access_assert_actor(uuid,uuid,text)' => 'pgsql',
            'identity_access_admit_session(text,uuid,text)' => 'pgsql',
        ];
        foreach (['pgsql', 'auth_lookup'] as $connection) {
            foreach ($audiences as $signature => $audience) {
                $function = DB::connection($connection)->selectOne(<<<'SQL'
SELECT owner.rolname AS owner, p.prosecdef AS definer, p.proconfig::text AS settings,
       has_function_privilege(current_user, p.oid, 'EXECUTE') AS allowed,
       EXISTS (SELECT FROM aclexplode(COALESCE(p.proacl, acldefault('f', p.proowner))) acl
               WHERE acl.grantee = 0 AND acl.privilege_type = 'EXECUTE') AS public_execute
FROM pg_proc p JOIN pg_roles owner ON owner.oid = p.proowner
WHERE p.oid = to_regprocedure(?)
SQL, ['epr.'.$signature]);
                $this->assertNotNull($function, $signature);
                $this->assertSame('epr_owner', $function->owner);
                $this->assertTrue($function->definer);
                $this->assertSame('{"search_path=pg_catalog, epr, pg_temp"}', $function->settings);
                $this->assertFalse($function->public_execute);
                $this->assertSame($connection === $audience, $function->allowed, $connection.': '.$signature);
            }
        }
    }

    public function test_session_creation_refuses_malformed_or_duplicate_private_keys(): void
    {
        $lookup = DB::connection('auth_lookup');
        $user = Accounts::user();
        $hmac = str_repeat('a', 64);
        $token = str_repeat('b', 64);
        $start = static function (string $key, string $broadcast) use ($lookup, $user): void {
            $lookup->transaction(fn () => $lookup->select('SELECT epr.identity_access_start_session(?::uuid, ?::uuid, ?, ?, ?)', [$user['id'], (string) Str::uuid7(), $key, 'synthetic-ciphertext', $broadcast]));
        };
        $start($hmac, $token);
        foreach ([
            [str_repeat('z', 64), str_repeat('c', 64), '23514'],
            [str_repeat('d', 64), str_repeat('z', 64), '23514'],
            [$hmac, str_repeat('e', 64), '23505'],
            [str_repeat('f', 64), $token, '23505'],
        ] as [$key, $broadcast, $state]) {
            try {
                $start($key, $broadcast);
                $this->fail('An invalid or duplicate private session key was accepted.');
            } catch (QueryException $error) {
                $this->assertSame($state, $error->errorInfo[0] ?? null);
            }
        }
    }

    public function test_sql_diagnostics_do_not_copy_bound_credentials_or_native_query_text(): void
    {
        $base = storage_path('logs/identity-privacy-'.Str::uuid7());
        config(['logging.channels.identity.path' => $base.'.log']);
        Log::forgetChannel('identity');
        $marker = 'private-auth-binding-marker';
        try {
            try {
                DB::connection('auth_lookup')->transaction(fn () => DB::connection('auth_lookup')->select('SELECT ?::integer AS confidential_probe', [$marker]));
                $this->fail('Expected a native PostgreSQL failure.');
            } catch (QueryException $error) {
                report($error);
            }
            $files = glob($base.'*.log') ?: [];
            $this->assertCount(1, $files);
            $contents = file_get_contents($files[0]);
            $this->assertIsString($contents);
            $this->assertStringContainsString('database.failure', $contents);
            $this->assertStringContainsString('22P02', $contents);
            $this->assertStringNotContainsString($marker, $contents);
            $this->assertStringNotContainsString('confidential_probe', $contents);
            $this->assertStringNotContainsString('SELECT', $contents);
        } finally {
            foreach (glob($base.'*.log') ?: [] as $file) {
                unlink($file);
            }
            Log::forgetChannel('identity');
        }
    }

    public function test_identity_serialization_is_explicit_and_performs_no_sql(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        $user = Accounts::user('atelier-1-manager');
        $data = new AuthenticatedUserData($user['id'], $user['login'], $user['displayName'], $user['workshopId'], 'Atelier 1', Role::Manager);
        $serialized = $data->toArray();
        $this->assertSame(['id', 'login', 'displayName', 'workshopId', 'workshopName', 'role'], array_keys($serialized));
        $this->assertSame('manager', $serialized['role']);
        $this->assertSame(0, $queries);
    }

    #[DataProvider('tableCases')]
    public function test_runtime_cannot_write_global_identity_references(string $connection, string $table): void
    {
        foreach (['insert', 'update', 'delete'] as $operation) {
            try {
                DB::connection($connection)->transaction(function () use ($connection, $table, $operation): void {
                    $query = DB::connection($connection)->table($table);
                    match ($operation) {
                        'insert' => $query->insert(['id' => (string) Str::uuid7()]),
                        'update' => $query->whereRaw('false')->update(['id' => (string) Str::uuid7()]),
                        'delete' => $query->whereRaw('false')->delete(),
                    };
                });
                $this->fail('A runtime role wrote a global identity reference.');
            } catch (QueryException $error) {
                $this->assertSame('42501', $error->errorInfo[0] ?? null);
            }
        }
    }

    /** @return list<array{string, string}> */
    public static function accounts(): array
    {
        return [['atelier-1-manager', 'manager'], ['atelier-1-staff', 'staff'], ['atelier-1-borrower-1', 'borrower'], ['atelier-1-borrower-2', 'borrower'], ['atelier-2-manager', 'manager'], ['atelier-2-staff', 'staff'], ['atelier-2-borrower-1', 'borrower'], ['atelier-2-borrower-2', 'borrower']];
    }

    /** @return list<array{string}> */
    public static function logins(): array
    {
        return array_map(static fn (array $row): array => [$row[0]], self::accounts());
    }

    /** @return list<array{string, string}> */
    public static function tableCases(): array
    {
        $cases = [];
        foreach (['pgsql', 'auth_lookup'] as $connection) {
            foreach (['identity_access_users', 'identity_access_workshops', 'identity_access_auth_sessions'] as $table) {
                $cases[] = [$connection, $table];
            }
        }

        return $cases;
    }

    #[DataProvider('accounts')]
    public function test_connects_each_precreated_account_with_its_exact_workshop_and_role_permissions(string $login, string $role): void
    {
        Accounts::signIn($this, $login);
        $expected = Accounts::user($login);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $expected['id'])
            ->where('auth.user.workshopId', $expected['workshopId'])
            ->where('auth.user.role', $role)
            ->has('auth.user', 6)
        );
        $user = Auth::user();
        expect(Gate::forUser($user)->allows('borrow'))->toBe($role === 'borrower');
        expect(Gate::forUser($user)->allows('operate-loans'))->toBe(in_array($role, ['staff', 'manager'], true));
        expect(Gate::forUser($user)->allows('manage-catalog'))->toBe($role === 'manager');
    }

    #[DataProvider('tableCases')]
    public function test_denies_direct_identity_table_access_with_both_runtime_sql_roles(string $connection, string $table): void
    {
        try {
            DB::connection($connection)->transaction(fn () => DB::connection($connection)->table($table)->get());
            $this->fail('A runtime role read a global identity table.');
        } catch (QueryException $error) {
            expect($error->errorInfo[0] ?? null)->toBe('42501');
        }
    }

    public function test_keeps_the_lookup_identity_distinct_and_without_ddl_or_owner_membership(): void
    {
        $row = DB::connection('auth_lookup')->selectOne("SELECT current_user, session_user, rolsuper, rolbypassrls, rolcreaterole, pg_has_role(current_user,'epr_owner','MEMBER') AS owner, has_schema_privilege(current_user,'epr','CREATE') AS ddl FROM pg_roles WHERE rolname=current_user");
        expect($row->current_user)->toBe('epr_auth_lookup')->and($row->session_user)->toBe('epr_auth_lookup');
        expect([$row->rolsuper, $row->rolbypassrls, $row->rolcreaterole, $row->owner, $row->ddl])->toBe([false, false, false, false, false]);
    }

    #[DataProvider('logins')]
    public function test_denies_horizon_diagnostics_in_local_and_production_for_every_product_account(string $login): void
    {
        Accounts::signIn($this, $login);
        foreach (['local', 'production'] as $environment) {
            app()->instance('env', $environment);
            foreach (['/horizon', '/horizon/api/stats', '/horizon/api/jobs/failed', '/horizon/api/masters'] as $path) {
                $this->get($path)->assertForbidden();
            }
        }
    }
}
