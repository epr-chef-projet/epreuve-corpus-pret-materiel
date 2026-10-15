<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseIdentityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_restricted_runtime_is_not_the_owner_and_cannot_create_tables(): void
    {
        $identity = DB::selectOne('SELECT session_user, current_user');
        $this->assertNotNull($identity);
        $this->assertSame('epr_app', $identity->session_user);
        $this->assertSame('epr_app', $identity->current_user);
        try {
            DB::statement('CREATE TABLE epr.forbidden_foundation_probe (id integer)');
            $this->fail('Application identity unexpectedly acquired DDL rights.');
        } catch (QueryException $error) {
            $this->assertSame('42501', $error->errorInfo[0] ?? null);
        }
    }

    public function test_horizon_dashboard_and_api_refuse_visitors_and_authenticated_users(): void
    {
        foreach (['/horizon', '/horizon/api/stats'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->actingAs(User::factory()->create());
        foreach (['/horizon', '/horizon/api/stats'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }
}
