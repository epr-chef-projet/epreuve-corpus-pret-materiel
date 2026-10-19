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
        Schema::create('identity_access_workshops', function (Blueprint $table): void {
            $table->uuid('id');
            $table->string('name', 120);
            $table->primary('id', 'identity_access_workshops_pkey');
        });

        DB::statement('ALTER TABLE epr.identity_access_workshops ADD CONSTRAINT identity_access_workshops_name_check CHECK (length(btrim(name)) > 0)');
        DB::statement('REVOKE ALL ON epr.identity_access_workshops FROM PUBLIC, epr_app, epr_auth_lookup');
    }

    public function down(): void
    {
        Schema::drop('identity_access_workshops');
    }
};
