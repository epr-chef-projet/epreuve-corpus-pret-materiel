<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('LOCK TABLE epr.users IN ACCESS EXCLUSIVE MODE');
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Legacy identities require an explicit UUID/login/workshop/role mapping before transition. No data changed.');
        }
        DB::unprepared(<<<'SQL'
ALTER TABLE epr.users RENAME TO identity_access_legacy_users;
REVOKE ALL ON epr.identity_access_legacy_users, epr.passkeys, epr.password_reset_tokens, epr.sessions FROM epr_app, epr_auth_lookup;

CREATE TABLE epr.identity_access_workshops (
    id uuid PRIMARY KEY,
    name varchar(120) NOT NULL CHECK (length(btrim(name)) > 0)
);
CREATE TABLE epr.identity_access_users (
    id uuid PRIMARY KEY,
    workshop_id uuid NOT NULL REFERENCES epr.identity_access_workshops(id),
    login varchar(190) NOT NULL UNIQUE CHECK (login = lower(btrim(login)) AND length(login) > 0),
    display_name varchar(120) NOT NULL CHECK (length(btrim(display_name)) > 0),
    password varchar(255) NOT NULL,
    role varchar(16) NOT NULL CHECK (role IN ('borrower', 'staff', 'manager')),
    UNIQUE (workshop_id, id)
);
CREATE TABLE epr.identity_access_auth_sessions (
    id uuid PRIMARY KEY,
    workshop_id uuid NOT NULL,
    user_id uuid NOT NULL,
    session_key_hmac char(64) NOT NULL UNIQUE CHECK (session_key_hmac ~ '^[0-9a-f]{64}$'),
    session_id_ciphertext text NOT NULL,
    broadcast_token char(64) NOT NULL UNIQUE CHECK (broadcast_token ~ '^[0-9a-f]{64}$'),
    auth_generation integer NOT NULL CHECK (auth_generation > 0),
    created_at timestamptz NOT NULL,
    expires_at timestamptz NOT NULL,
    revoked_at timestamptz,
    FOREIGN KEY (workshop_id, user_id) REFERENCES epr.identity_access_users(workshop_id, id),
    CHECK (expires_at = created_at + interval '2 hours')
);
CREATE INDEX identity_access_sessions_audience ON epr.identity_access_auth_sessions (workshop_id, user_id, expires_at) WHERE revoked_at IS NULL;
REVOKE ALL ON epr.identity_access_workshops, epr.identity_access_users, epr.identity_access_auth_sessions FROM PUBLIC, epr_app, epr_auth_lookup;

CREATE FUNCTION epr.identity_access_find_identity(p_login text)
RETURNS TABLE(id uuid, workshop_id uuid, login varchar, display_name varchar, password varchar, role varchar, workshop_name varchar)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    SELECT u.id, u.workshop_id, u.login, u.display_name, u.password, u.role, w.name
    FROM epr.identity_access_users u JOIN epr.identity_access_workshops w ON w.id = u.workshop_id
    WHERE u.login = p_login;
$$;
CREATE FUNCTION epr.identity_access_resolve_identity(p_id uuid, p_hmac text)
RETURNS TABLE(id uuid, workshop_id uuid, login varchar, display_name varchar, password varchar, role varchar, workshop_name varchar)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    SELECT u.id, u.workshop_id, u.login, u.display_name, NULL::varchar, u.role, w.name
    FROM epr.identity_access_users u JOIN epr.identity_access_workshops w ON w.id = u.workshop_id
    JOIN epr.identity_access_auth_sessions s ON s.user_id = u.id AND s.workshop_id = u.workshop_id
    WHERE u.id = p_id AND s.session_key_hmac = p_hmac AND s.revoked_at IS NULL AND s.expires_at > statement_timestamp();
$$;
CREATE FUNCTION epr.identity_access_rehash_password(p_id uuid, p_previous text, p_password text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    UPDATE epr.identity_access_users SET password = p_password WHERE id = p_id AND password = p_previous;
$$;
CREATE FUNCTION epr.identity_access_start_session(p_id uuid, p_session uuid, p_hmac text, p_ciphertext text, p_token text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    INSERT INTO epr.identity_access_auth_sessions (id, workshop_id, user_id, session_key_hmac, session_id_ciphertext, broadcast_token, auth_generation, created_at, expires_at)
    SELECT p_session, u.workshop_id, u.id, p_hmac, p_ciphertext, p_token, 1, statement_timestamp(), statement_timestamp() + interval '2 hours'
    FROM epr.identity_access_users u WHERE u.id = p_id;
$$;
CREATE FUNCTION epr.identity_access_revoke_session(p_hmac text)
RETURNS void LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    UPDATE epr.identity_access_auth_sessions SET revoked_at = COALESCE(revoked_at, statement_timestamp()) WHERE session_key_hmac = p_hmac;
$$;
CREATE FUNCTION epr.identity_access_assert_actor(p_id uuid, p_workshop uuid, p_role text)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    SELECT EXISTS (SELECT FROM epr.identity_access_users WHERE id = p_id AND workshop_id = p_workshop AND role = p_role);
$$;
CREATE FUNCTION epr.identity_access_admit_session(p_token text, p_actor uuid, p_role text)
RETURNS TABLE(user_id uuid, session_id_ciphertext text, broadcast_token char, auth_generation integer)
LANGUAGE sql SECURITY DEFINER SET search_path = pg_catalog, epr AS $$
    SELECT s.user_id, s.session_id_ciphertext, s.broadcast_token, s.auth_generation
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
        throw new RuntimeException('Identity transition preserves history; use a reviewed forward migration, not a destructive rollback.');
    }
};
