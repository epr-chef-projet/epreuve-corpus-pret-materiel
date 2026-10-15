#!/usr/bin/env bash
     set +x
     set -Eeuo pipefail

     fail() {
         printf >&2 '%s\n' "$1"
         exit 1
     }

     : "${POSTGRES_USER:?Missing POSTGRES_USER}"
     : "${POSTGRES_DB:?Missing POSTGRES_DB}"

     [[ "$POSTGRES_DB" != testing ]] ||
         fail "POSTGRES_DB must differ from testing"

     if ( : </dev/tty >/dev/tty ) 2>/dev/null; then
         fail "Bootstrap requires no controlling terminal"
     fi

     check_password_file() {
         local password_file="$1" password_bytes password_line
         [[ -f "$password_file" && -r "$password_file" ]] ||
             fail "Missing or unreadable role password file"

         password_bytes="$(wc -c < "$password_file")"
         IFS= read -r password_line < "$password_file" ||
             fail "Invalid role password file"

         [[ "$password_bytes" -eq 65 && "$password_line" =~ ^[0-9a-f]{64}$ ]] ||
             fail "Invalid role password file"
     }

     bootstrap_psql() {
         psql -X --no-password \
             --host /var/run/postgresql \
             --username "$POSTGRES_USER" \
             --set ON_ERROR_STOP=1 "$@"
     }

     set_role_password() {
         local role="$1" password_file="$2"
         case "$role" in
             epr_app|epr_migrator|epr_backup) ;;
             *) fail "Unexpected database role" ;;
         esac

         { cat "$password_file" && cat "$password_file"; } |
             bootstrap_psql --dbname "$POSTGRES_DB" \
                 --command "\\password $role"
     }

     for account in app migrator backup; do
         check_password_file "/run/secrets/postgres_${account}_password"
     done

     [[ -r /opt/epr-postgres-bootstrap/roles.sql &&
        -r /opt/epr-postgres-bootstrap/database.sql ]] ||
         fail "Missing or unreadable bootstrap SQL file"

     bootstrap_psql --dbname "$POSTGRES_DB" \
         --file /opt/epr-postgres-bootstrap/roles.sql

     for account in app migrator backup; do
         set_role_password "epr_${account}" "/run/secrets/postgres_${account}_password"
     done

     for database in "$POSTGRES_DB" testing; do
         bootstrap_psql --dbname "$database" \
             --file /opt/epr-postgres-bootstrap/database.sql
     done

     printf '%s\n' "PostgreSQL role bootstrap complete"
