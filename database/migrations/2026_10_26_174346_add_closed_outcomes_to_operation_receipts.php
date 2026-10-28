<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '2s'");
        DB::statement("SET LOCAL statement_timeout = '5s'");
        Schema::table('operation_receipts', function (Blueprint $table): void {
            $table->string('status', 16)->default('applied');
            $table->char('input_sha256', 64)->nullable()->change();
            $table->string('resource_type', 32)->nullable()->change();
            $table->uuid('resource_id')->nullable()->change();
            $table->integer('result_version')->nullable()->change();
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE epr.operation_receipts
    DROP CONSTRAINT operation_receipts_resource_check,
    ADD CONSTRAINT operation_receipts_status_check CHECK (status IN ('applied', 'closed')),
    ADD CONSTRAINT operation_receipts_outcome_check CHECK (
        (status = 'applied'
            AND input_sha256 IS NOT NULL AND resource_type IS NOT NULL
            AND resource_id IS NOT NULL AND equipment_id IS NOT NULL
            AND result_version IS NOT NULL)
        OR (status = 'closed' AND operation_type = 'catalog.equipment.create'
            AND input_sha256 IS NULL AND resource_type IS NULL
            AND resource_id IS NULL AND equipment_id IS NULL
            AND result_version IS NULL)
    ),
    ADD CONSTRAINT operation_receipts_resource_check CHECK (
        status = 'closed'
        OR (resource_type = 'equipment' AND equipment_id IS NOT NULL AND resource_id = equipment_id)
    );
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Keep terminal operation outcomes and use a compatible forward migration.');
    }
};
