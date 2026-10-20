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
        Schema::table('identity_access_auth_sessions', function (Blueprint $table): void {
            $table->index(['workshop_id', 'id'], 'identity_access_sessions_routing');
        });
        DB::unprepared(<<<'SQL'
CREATE FUNCTION epr.identity_access_session_candidates(p_roles jsonb, p_actor uuid, p_after uuid, p_limit integer)
RETURNS TABLE(id uuid, user_id uuid, role varchar, broadcast_token char)
LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = pg_catalog, epr, pg_temp AS $$
BEGIN
    IF COALESCE(current_setting('epr.service_capability', true), '') <> 'identity_access.publish'
       OR COALESCE(current_setting('epr.service_id', true), '') = ''
       OR COALESCE(current_setting('epr.actor_id', true), '') <> ''
       OR COALESCE(current_setting('epr.workshop_id', true), '') = '' THEN
        RETURN;
    END IF;
    IF p_limit IS NULL OR p_limit < 1 OR p_limit > 100 OR jsonb_typeof(p_roles) IS DISTINCT FROM 'array'
       OR jsonb_array_length(p_roles) < 1 OR jsonb_array_length(p_roles) > 3 THEN
        RAISE EXCEPTION 'Invalid routing bounds' USING ERRCODE = '22023';
    END IF;
    IF EXISTS (SELECT FROM jsonb_array_elements_text(p_roles) r WHERE r NOT IN ('borrower','staff','manager')) THEN
        RAISE EXCEPTION 'Invalid routing audience' USING ERRCODE = '22023';
    END IF;
    RETURN QUERY
    SELECT s.id, s.user_id, u.role, s.broadcast_token
    FROM epr.identity_access_auth_sessions s
    JOIN epr.identity_access_users u ON u.id = s.user_id AND u.workshop_id = s.workshop_id
    WHERE s.workshop_id = current_setting('epr.workshop_id', true)::uuid
      AND s.revoked_at IS NULL AND s.expires_at > statement_timestamp()
      AND (p_after IS NULL OR s.id > p_after)
      AND (p_actor IS NULL OR s.user_id = p_actor)
      AND u.role IN (SELECT jsonb_array_elements_text(p_roles))
    ORDER BY s.id LIMIT p_limit;
END;
$$;
REVOKE ALL ON FUNCTION epr.identity_access_session_candidates(jsonb, uuid, uuid, integer) FROM PUBLIC, epr_auth_lookup;
GRANT EXECUTE ON FUNCTION epr.identity_access_session_candidates(jsonb, uuid, uuid, integer) TO epr_app;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION epr.identity_access_session_candidates(jsonb, uuid, uuid, integer)');
        Schema::table('identity_access_auth_sessions', function (Blueprint $table): void {
            $table->dropIndex('identity_access_sessions_routing');
        });
    }
};
