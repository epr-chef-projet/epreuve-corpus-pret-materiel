<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog\Equipment;

use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\NotificationDeliveryFailed;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Tests\TestCase;

final class NotificationDiagnosticTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_native_cause_is_kept_but_failed_job_storage_receives_only_selected_diagnostics(): void
    {
        $private = 'private-route-and-binding-'.Str::uuid7();
        $native = new PDOException($private);
        $native->errorInfo = ['08006', 7, $private];
        $query = new QueryException('pgsql', 'SELECT ?', [$private], $native);
        $error = new NotificationDeliveryFailed((string) Str::uuid7(), (string) Str::uuid7(), $query);
        $this->assertSame($query, $error->getPrevious());
        $this->assertStringNotContainsString($private, (string) $error);
        $this->assertStringNotContainsString('SELECT', (string) $error);
        $this->assertSame('08006', $error->diagnostic()['sqlState']);
        $uuid = (string) Str::uuid7();
        $provider = new DatabaseUuidFailedJobProvider(app('db'), 'pgsql', 'failed_jobs');
        $provider->log('redis', 'testing-notifications', json_encode(['uuid' => $uuid], JSON_THROW_ON_ERROR), $error);
        $stored = DB::table('failed_jobs')->where('uuid', $uuid)->value('exception');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString($private, $stored);
        $this->assertStringContainsString('catalog.notification_delivery_failed', $stored);
        $this->assertSame(QueryException::class, json_decode($stored, true, flags: JSON_THROW_ON_ERROR)['exceptionClass']);
    }
}
