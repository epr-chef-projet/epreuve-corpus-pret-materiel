<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE epr.identity_access_auth_sessions RENAME COLUMN session_id_ciphertext TO session_key_ciphertext;
DROP INDEX epr.identity_access_sessions_audience;
CREATE INDEX identity_access_sessions_user_expiry ON epr.identity_access_auth_sessions (user_id, expires_at);
CREATE INDEX identity_access_sessions_workshop_expiry ON epr.identity_access_auth_sessions (workshop_id, expires_at);

CREATE OR REPLACE FUNCTION epr.identity_access_start_session(p_id uuid, p_session uuid, p_hmac text, p_ciphertext text, p_token text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    INSERT INTO epr.identity_access_auth_sessions (id, workshop_id, user_id, session_key_hmac, session_key_ciphertext, broadcast_token, auth_generation, created_at, expires_at)
    SELECT p_session, u.workshop_id, u.id, p_hmac, p_ciphertext, p_token, 1, statement_timestamp(), statement_timestamp() + interval '2 hours'
    FROM epr.identity_access_users u WHERE u.id = p_id;
$$;
DROP FUNCTION epr.identity_access_admit_session(text,uuid,text);
CREATE FUNCTION epr.identity_access_admit_session(p_token text, p_actor uuid, p_role text)
RETURNS TABLE(user_id uuid, session_key_ciphertext text, broadcast_token char, auth_generation integer)
LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    SELECT s.user_id, s.session_key_ciphertext, s.broadcast_token, s.auth_generation
    FROM epr.identity_access_auth_sessions s JOIN epr.identity_access_users u ON u.id = s.user_id AND u.workshop_id = s.workshop_id
    WHERE s.broadcast_token = p_token AND u.id = p_actor AND u.role = p_role AND s.revoked_at IS NULL AND s.expires_at > statement_timestamp()
      AND s.workshop_id::text = current_setting('epr.workshop_id', true)
      AND current_setting('epr.service_capability', true) = 'identity_access.publish'
      AND COALESCE(current_setting('epr.actor_id', true), '') = ''
    FOR UPDATE OF s;
$$;
REVOKE ALL ON FUNCTION epr.identity_access_admit_session(text,uuid,text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION epr.identity_access_admit_session(text,uuid,text) TO epr_app;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Use a reviewed forward migration for the session routing contract.');
    }
};
