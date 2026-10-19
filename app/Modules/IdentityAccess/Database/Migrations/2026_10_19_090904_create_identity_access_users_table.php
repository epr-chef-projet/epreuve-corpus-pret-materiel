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
        Schema::create('identity_access_users', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('workshop_id');
            $table->string('login', 190);
            $table->string('display_name', 120);
            $table->string('password', 255);
            $table->string('role', 16);
            $table->primary('id', 'identity_access_users_pkey');
            $table->unique('login', 'identity_access_users_login_unique');
            $table->unique(['workshop_id', 'id'], 'identity_access_users_workshop_user_unique');
            $table->foreign('workshop_id', 'identity_access_users_workshop_fk')
                ->references('id')->on('identity_access_workshops')
                ->restrictOnUpdate()->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE epr.identity_access_users
    ADD CONSTRAINT identity_access_users_login_check CHECK (login = lower(btrim(login)) AND length(login) > 0),
    ADD CONSTRAINT identity_access_users_display_name_check CHECK (length(btrim(display_name)) > 0),
    ADD CONSTRAINT identity_access_users_role_check CHECK (role IN ('borrower', 'staff', 'manager'));
REVOKE ALL ON epr.identity_access_users FROM PUBLIC, epr_app, epr_auth_lookup;
SQL);
    }

    public function down(): void
    {
        Schema::drop('identity_access_users');
    }
};
