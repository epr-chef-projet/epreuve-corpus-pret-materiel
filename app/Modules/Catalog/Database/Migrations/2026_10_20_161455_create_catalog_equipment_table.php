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
        $extension = DB::selectOne("SELECT n.nspname FROM pg_extension x JOIN pg_namespace n ON n.oid=x.extnamespace WHERE x.extname='pg_trgm'");
        if ($extension === null || $extension->nspname !== 'epr') {
            throw new RuntimeException('Prepare the pg_trgm extension in epr before the catalogue migration.');
        }
        Schema::create('catalog_equipment', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workshop_id');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 80);
            $table->integer('total_quantity');
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestampTz('created_at', 3);
            $table->timestampTz('updated_at', 3);
            $table->unique(['workshop_id', 'id'], 'catalog_equipment_workshop_id_unique');
            $table->foreign('workshop_id', 'catalog_equipment_workshop_fk')->references('id')->on('identity_access_workshops')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['workshop_id', 'is_active', 'name', 'id'], 'catalog_equipment_name_index');
            $table->index(['workshop_id', 'is_active', 'category', 'name', 'id'], 'catalog_equipment_category_index');
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE epr.catalog_equipment
    ADD CONSTRAINT catalog_equipment_name_check CHECK (length(btrim(name, E' \t\n\r\v\f\u0085\u00a0\u1680\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u2028\u2029\u202f\u205f\u3000')) > 0),
    ADD CONSTRAINT catalog_equipment_category_check CHECK (length(btrim(category, E' \t\n\r\v\f\u0085\u00a0\u1680\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u2028\u2029\u202f\u205f\u3000')) > 0),
    ADD CONSTRAINT catalog_equipment_description_check CHECK (description IS NULL OR length(description) BETWEEN 1 AND 2000),
    ADD CONSTRAINT catalog_equipment_total_check CHECK (total_quantity BETWEEN 0 AND 999999),
    ADD CONSTRAINT catalog_equipment_version_check CHECK (version BETWEEN 1 AND 2147483647);
ALTER TABLE epr.catalog_equipment ENABLE ROW LEVEL SECURITY;
ALTER TABLE epr.catalog_equipment FORCE ROW LEVEL SECURITY;
CREATE POLICY catalog_equipment_actor ON epr.catalog_equipment TO epr_app
    USING (workshop_id = nullif(current_setting('epr.workshop_id', true), '')::uuid AND nullif(current_setting('epr.actor_id', true), '')::uuid IS NOT NULL AND current_setting('epr.actor_role', true) IN ('borrower', 'staff', 'manager'))
    WITH CHECK (workshop_id = nullif(current_setting('epr.workshop_id', true), '')::uuid AND current_setting('epr.actor_role', true) = 'manager');
REVOKE ALL ON epr.catalog_equipment FROM PUBLIC, epr_app, epr_auth_lookup;
GRANT SELECT, INSERT, UPDATE ON epr.catalog_equipment TO epr_app;
CREATE INDEX catalog_equipment_search_index ON epr.catalog_equipment USING gin (lower(name) epr.gin_trgm_ops);
SQL);
    }

    public function down(): void
    {
        Schema::drop('catalog_equipment');
    }
};
