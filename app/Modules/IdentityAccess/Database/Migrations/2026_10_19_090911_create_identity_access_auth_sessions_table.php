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
        Schema::create('identity_access_auth_sessions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('workshop_id');
            $table->uuid('user_id');
            $table->char('session_key_hmac', 64);
            $table->text('session_key_ciphertext');
            $table->char('broadcast_token', 64);
            $table->integer('auth_generation');
            $table->timestampTz('created_at', 6);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->primary('id', 'identity_access_auth_sessions_pkey');
            $table->unique('session_key_hmac', 'identity_access_sessions_hmac_unique');
            $table->unique('broadcast_token', 'identity_access_sessions_token_unique');
            $table->foreign(['workshop_id', 'user_id'], 'identity_access_sessions_user_fk')
                ->references(['workshop_id', 'id'])->on('identity_access_users')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->index(['user_id', 'expires_at'], 'identity_access_sessions_user_expiry');
            $table->index(['workshop_id', 'expires_at'], 'identity_access_sessions_workshop_expiry');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE epr.identity_access_auth_sessions
    ADD CONSTRAINT identity_access_sessions_hmac_check CHECK (session_key_hmac ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT identity_access_sessions_token_check CHECK (broadcast_token ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT identity_access_sessions_generation_check CHECK (auth_generation > 0),
    ADD CONSTRAINT identity_access_sessions_expiry_check CHECK (expires_at = created_at + interval '2 hours');
REVOKE ALL ON epr.identity_access_auth_sessions FROM PUBLIC, epr_app, epr_auth_lookup;
SQL);
    }

    public function down(): void
    {
        Schema::drop('identity_access_auth_sessions');
    }
};
