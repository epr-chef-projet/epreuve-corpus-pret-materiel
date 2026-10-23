\set ON_ERROR_STOP on
BEGIN;
SET LOCAL lock_timeout = '2s';
SET LOCAL statement_timeout = '5s';
DO $guard$
DECLARE
    actual record;
    old_check constant text := $$((workshop_id = (NULLIF(current_setting('epr.workshop_id'::text, true), ''::text))::uuid) AND (current_setting('epr.actor_role'::text, true) = 'manager'::text))$$;
    expected_check constant text := $$((workshop_id = (NULLIF(current_setting('epr.workshop_id'::text, true), ''::text))::uuid) AND ((NULLIF(current_setting('epr.actor_id'::text, true), ''::text))::uuid IS NOT NULL) AND (current_setting('epr.actor_role'::text, true) = 'manager'::text))$$;
    expected_using constant text := $$((workshop_id = (NULLIF(current_setting('epr.workshop_id'::text, true), ''::text))::uuid) AND ((NULLIF(current_setting('epr.actor_id'::text, true), ''::text))::uuid IS NOT NULL) AND (current_setting('epr.actor_role'::text, true) = ANY (ARRAY['borrower'::text, 'staff'::text, 'manager'::text])))$$;
BEGIN
    IF current_database() NOT IN ('laravel', 'testing') OR session_user <> 'epr_migrator' OR current_user <> 'epr_owner' OR current_schema() <> 'epr' THEN
        RAISE EXCEPTION 'Catalog actor alignment target refused';
    END IF;
    IF NOT EXISTS (SELECT FROM epr.migrations WHERE migration='2026_10_20_161455_create_catalog_equipment_table') THEN
        RAISE EXCEPTION 'Catalog migration history requires inspection';
    END IF;
    SELECT pg_get_expr(p.polqual,p.polrelid) AS using_expression,
        pg_get_expr(p.polwithcheck,p.polrelid) AS check_expression,
        p.polcmd, p.polpermissive, p.polroles, c.relowner, c.relrowsecurity, c.relforcerowsecurity,
        (SELECT count(*) FROM pg_policy other WHERE other.polrelid=c.oid) AS policies
    INTO actual FROM pg_policy p JOIN pg_class c ON c.oid=p.polrelid
    WHERE p.polrelid='epr.catalog_equipment'::regclass AND p.polname='catalog_equipment_actor';
    IF actual IS NULL OR actual.using_expression IS DISTINCT FROM expected_using
        OR actual.polcmd <> '*' OR NOT actual.polpermissive
        OR actual.polroles IS DISTINCT FROM ARRAY['epr_app'::regrole::oid]
        OR actual.relowner <> 'epr_owner'::regrole::oid
        OR NOT actual.relrowsecurity OR NOT actual.relforcerowsecurity OR actual.policies <> 1 THEN
        RAISE EXCEPTION 'Unexpected Catalog actor policy';
    END IF;
    IF actual.check_expression = expected_check THEN
        RETURN;
    END IF;
    IF actual.check_expression IS DISTINCT FROM old_check THEN
        RAISE EXCEPTION 'Unexpected Catalog write condition';
    END IF;
    ALTER POLICY catalog_equipment_actor ON epr.catalog_equipment
        WITH CHECK (workshop_id = nullif(current_setting('epr.workshop_id', true), '')::uuid
            AND nullif(current_setting('epr.actor_id', true), '')::uuid IS NOT NULL
            AND current_setting('epr.actor_role', true) = 'manager');
    IF (SELECT pg_get_expr(polwithcheck,polrelid) FROM pg_policy
        WHERE polrelid='epr.catalog_equipment'::regclass AND polname='catalog_equipment_actor') IS DISTINCT FROM expected_check THEN
        RAISE EXCEPTION 'Catalog write condition did not align';
    END IF;
END;
$guard$;
COMMIT;
