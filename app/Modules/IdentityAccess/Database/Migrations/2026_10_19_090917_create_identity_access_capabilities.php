<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION epr.identity_access_find_identity(p_login text)
RETURNS TABLE(id uuid, workshop_id uuid, login varchar, display_name varchar, password varchar, role varchar, workshop_name varchar)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    SELECT u.id, u.workshop_id, u.login, u.display_name, u.password, u.role, w.name
    FROM epr.identity_access_users u JOIN epr.identity_access_workshops w ON w.id = u.workshop_id
    WHERE u.login = p_login;
$$;
CREATE FUNCTION epr.identity_access_resolve_identity(p_id uuid, p_hmac text)
RETURNS TABLE(id uuid, workshop_id uuid, login varchar, display_name varchar, password varchar, role varchar, workshop_name varchar)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    SELECT u.id, u.workshop_id, u.login, u.display_name, NULL::varchar, u.role, w.name
    FROM epr.identity_access_users u JOIN epr.identity_access_workshops w ON w.id = u.workshop_id
    JOIN epr.identity_access_auth_sessions s ON s.user_id = u.id AND s.workshop_id = u.workshop_id
    WHERE u.id = p_id AND s.session_key_hmac = p_hmac AND s.revoked_at IS NULL AND s.expires_at > statement_timestamp();
$$;
CREATE FUNCTION epr.identity_access_rehash_password(p_id uuid, p_previous text, p_password text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    UPDATE epr.identity_access_users SET password = p_password WHERE id = p_id AND password = p_previous;
$$;
CREATE FUNCTION epr.identity_access_start_session(p_id uuid, p_session uuid, p_hmac text, p_ciphertext text, p_token text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    INSERT INTO epr.identity_access_auth_sessions (id, workshop_id, user_id, session_key_hmac, session_key_ciphertext, broadcast_token, auth_generation, created_at, expires_at)
    SELECT p_session, u.workshop_id, u.id, p_hmac, p_ciphertext, p_token, 1, statement_timestamp(), statement_timestamp() + interval '2 hours'
    FROM epr.identity_access_users u WHERE u.id = p_id;
$$;
CREATE FUNCTION epr.identity_access_revoke_session(p_hmac text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    UPDATE epr.identity_access_auth_sessions SET revoked_at = COALESCE(revoked_at, statement_timestamp()) WHERE session_key_hmac = p_hmac;
$$;
CREATE FUNCTION epr.identity_access_assert_actor(p_id uuid, p_workshop uuid, p_role text)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    SELECT EXISTS (SELECT FROM epr.identity_access_users WHERE id = p_id AND workshop_id = p_workshop AND role = p_role);
$$;
CREATE FUNCTION epr.identity_access_admit_session(p_token text, p_actor uuid, p_role text)
RETURNS TABLE(user_id uuid, session_key_ciphertext text, broadcast_token char, auth_generation integer)
LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
    SELECT s.user_id, s.session_key_ciphertext, s.broadcast_token, s.auth_generation
    FROM epr.identity_access_auth_sessions s JOIN epr.identity_access_users u ON u.id = s.user_id AND u.workshop_id = s.workshop_id
    WHERE s.broadcast_token = p_token AND u.id = p_actor AND u.role = p_role AND s.revoked_at IS NULL AND s.expires_at > statement_timestamp()
      AND s.workshop_id::text = current_setting('epr.workshop_id', true)
      AND current_setting('epr.service_capability', true) = 'identity_access.publish'
      AND COALESCE(current_setting('epr.actor_id', true), '') = ''
    FOR UPDATE OF s;
$$;
REVOKE ALL ON FUNCTION epr.identity_access_find_identity(text), epr.identity_access_resolve_identity(uuid,text), epr.identity_access_rehash_password(uuid,text,text), epr.identity_access_start_session(uuid,uuid,text,text,text), epr.identity_access_revoke_session(text), epr.identity_access_assert_actor(uuid,uuid,text), epr.identity_access_admit_session(text,uuid,text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION epr.identity_access_find_identity(text), epr.identity_access_resolve_identity(uuid,text), epr.identity_access_rehash_password(uuid,text,text), epr.identity_access_start_session(uuid,uuid,text,text,text), epr.identity_access_revoke_session(text) TO epr_auth_lookup;
GRANT EXECUTE ON FUNCTION epr.identity_access_assert_actor(uuid,uuid,text), epr.identity_access_admit_session(text,uuid,text) TO epr_app;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP FUNCTION epr.identity_access_admit_session(text, uuid, text);
DROP FUNCTION epr.identity_access_assert_actor(uuid, uuid, text);
DROP FUNCTION epr.identity_access_revoke_session(text);
DROP FUNCTION epr.identity_access_start_session(uuid, uuid, text, text, text);
DROP FUNCTION epr.identity_access_rehash_password(uuid, text, text);
DROP FUNCTION epr.identity_access_resolve_identity(uuid, text);
DROP FUNCTION epr.identity_access_find_identity(text);
SQL);
    }
};
