#!/usr/bin/env bash
set +x
set -Eeuo pipefail

: "${POSTGRES_USER:?Missing bootstrap identity}"
: "${POSTGRES_DB:?Missing application database}"
[[ "$POSTGRES_DB" != testing ]] || exit 3
credential=/run/secrets/postgres_auth_lookup_password
[[ -f "$credential" && "$(wc -c < "$credential")" -eq 65 ]] || exit 3
IFS= read -r password_line < "$credential"
[[ "$password_line" =~ ^[0-9a-f]{64}$ ]] || exit 3
unset password_line
bootstrap_psql() {
    psql -X --no-password --host /var/run/postgresql --username "$POSTGRES_USER" --set ON_ERROR_STOP=1 "$@"
}
bootstrap_psql --dbname "$POSTGRES_DB" <<'SQL'
DO $body$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'epr_auth_lookup') THEN
        CREATE ROLE epr_auth_lookup LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    END IF;
    IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'epr_auth_lookup' AND (rolsuper OR rolcreatedb OR rolcreaterole OR rolreplication OR rolbypassrls))
       OR EXISTS (SELECT FROM pg_auth_members WHERE member = 'epr_auth_lookup'::regrole) THEN
        RAISE EXCEPTION 'Unexpected authentication role privileges';
    END IF;
END $body$;
SQL
{ cat "$credential" && cat "$credential"; } | bootstrap_psql --dbname "$POSTGRES_DB" --command '\password epr_auth_lookup'
for database in "$POSTGRES_DB" testing; do
    bootstrap_psql --dbname "$database" <<'SQL'
SELECT current_database() AS target \gset
GRANT CONNECT ON DATABASE :"target" TO epr_auth_lookup;
GRANT USAGE ON SCHEMA epr TO epr_auth_lookup;
ALTER ROLE epr_auth_lookup IN DATABASE :"target" SET lock_timeout = '2s';
ALTER ROLE epr_auth_lookup IN DATABASE :"target" SET statement_timeout = '5s';
SQL
done
printf '%s\n' 'Authentication role provisioned; existing data and other credentials preserved.'
