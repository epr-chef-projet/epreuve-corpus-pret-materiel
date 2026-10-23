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
        Schema::create('operation_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workshop_id');
            $table->uuid('actor_id');
            $table->string('operation_type', 120);
            $table->uuid('operation_key');
            $table->char('input_sha256', 64);
            $table->string('resource_type', 32);
            $table->uuid('resource_id');
            $table->uuid('equipment_id')->nullable();
            $table->integer('result_version');
            $table->timestampTz('recorded_at', 3);
            $table->unique(['workshop_id', 'actor_id', 'operation_type', 'operation_key'], 'operation_receipts_intention_unique');
            $table->foreign(['workshop_id', 'actor_id'], 'operation_receipts_actor_fk')->references(['workshop_id', 'id'])->on('identity_access_users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign(['workshop_id', 'equipment_id'], 'operation_receipts_equipment_fk')->references(['workshop_id', 'id'])->on('catalog_equipment')->restrictOnUpdate()->restrictOnDelete();
            $table->index(['workshop_id', 'equipment_id'], 'operation_receipts_equipment_index');
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE epr.operation_receipts
    ADD CONSTRAINT operation_receipts_version_check CHECK (result_version BETWEEN 1 AND 2147483647),
    ADD CONSTRAINT operation_receipts_hash_check CHECK (input_sha256 ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT operation_receipts_type_check CHECK (operation_type IN ('catalog.equipment.create', 'catalog.equipment.update_text', 'catalog.equipment.archive', 'catalog.equipment.reactivate')),
    ADD CONSTRAINT operation_receipts_resource_check CHECK (resource_type = 'equipment' AND equipment_id IS NOT NULL AND resource_id = equipment_id);
ALTER TABLE epr.operation_receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE epr.operation_receipts FORCE ROW LEVEL SECURITY;
CREATE POLICY operation_receipts_actor ON epr.operation_receipts TO epr_app
    USING (workshop_id = nullif(current_setting('epr.workshop_id', true), '')::uuid AND actor_id = nullif(current_setting('epr.actor_id', true), '')::uuid)
    WITH CHECK (workshop_id = nullif(current_setting('epr.workshop_id', true), '')::uuid AND actor_id = nullif(current_setting('epr.actor_id', true), '')::uuid);
REVOKE ALL ON epr.operation_receipts FROM PUBLIC, epr_app, epr_auth_lookup;
GRANT SELECT, INSERT ON epr.operation_receipts TO epr_app;
SQL);
    }

    public function down(): void
    {
        Schema::drop('operation_receipts');
    }
};
