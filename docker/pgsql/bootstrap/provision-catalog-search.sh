#!/usr/bin/env bash
set +x
set -Eeuo pipefail

: "${POSTGRES_USER:?Missing bootstrap identity}"
[[ "${POSTGRES_DB:-}" == laravel ]] || { printf >&2 '%s\n' 'Unexpected catalogue preparation target'; exit 3; }
for database in laravel testing; do
    psql -X --no-password --host /var/run/postgresql --username "$POSTGRES_USER" \
        --dbname "$database" --set ON_ERROR_STOP=1 <<'SQL'
BEGIN;
DO $body$
BEGIN
    IF current_database() NOT IN ('laravel', 'testing')
       OR NOT EXISTS (SELECT FROM pg_roles WHERE rolname = session_user AND rolsuper)
       OR NOT EXISTS (SELECT FROM pg_namespace WHERE nspname = 'epr' AND nspowner = 'epr_owner'::regrole)
       OR has_database_privilege('epr_app', current_database(), 'CREATE') THEN
        RAISE EXCEPTION 'Catalogue extension preparation refused';
    END IF;
    IF EXISTS (SELECT FROM pg_extension WHERE extname = 'pg_trgm') THEN
        IF NOT EXISTS (SELECT FROM pg_extension x JOIN pg_namespace n ON n.oid=x.extnamespace WHERE x.extname='pg_trgm' AND x.extversion='1.6' AND n.nspname='epr') THEN
            RAISE EXCEPTION 'Unexpected pg_trgm installation';
        END IF;
    ELSE
        CREATE EXTENSION pg_trgm WITH SCHEMA epr;
    END IF;
END $body$;
COMMIT;
SELECT current_database() AS database, extname, extversion, n.nspname AS schema
FROM pg_extension x JOIN pg_namespace n ON n.oid=x.extnamespace WHERE extname='pg_trgm';
SQL
done
