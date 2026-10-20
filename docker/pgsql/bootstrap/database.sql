SELECT current_database() AS epr_database \gset

BEGIN;

REVOKE ALL ON DATABASE :"epr_database" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"epr_database"
         TO epr_app, epr_migrator, epr_backup, epr_auth_lookup;

REVOKE CREATE ON SCHEMA public FROM PUBLIC;

CREATE SCHEMA epr AUTHORIZATION epr_owner;
CREATE EXTENSION pg_trgm WITH SCHEMA epr;
REVOKE ALL ON SCHEMA epr FROM PUBLIC;
GRANT USAGE ON SCHEMA epr TO epr_app, epr_backup, epr_auth_lookup;
ALTER ROLE epr_auth_lookup IN DATABASE :"epr_database" SET lock_timeout = '2s';
ALTER ROLE epr_auth_lookup IN DATABASE :"epr_database" SET statement_timeout = '5s';

     ALTER ROLE epr_app IN DATABASE :"epr_database"
         SET search_path = epr;
     ALTER ROLE epr_migrator IN DATABASE :"epr_database"
         SET search_path = epr;
     ALTER ROLE epr_migrator IN DATABASE :"epr_database"
         SET role = 'epr_owner';
     ALTER ROLE epr_backup IN DATABASE :"epr_database"
         SET search_path = epr;

     ALTER DEFAULT PRIVILEGES FOR ROLE epr_owner IN SCHEMA epr
         GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO epr_app;
ALTER DEFAULT PRIVILEGES FOR ROLE epr_owner IN SCHEMA epr
         GRANT USAGE ON SEQUENCES TO epr_app;

     ALTER DEFAULT PRIVILEGES FOR ROLE epr_owner IN SCHEMA epr
         GRANT SELECT ON TABLES TO epr_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE epr_owner IN SCHEMA epr
         GRANT SELECT ON SEQUENCES TO epr_backup;

ALTER DEFAULT PRIVILEGES FOR ROLE epr_owner
         REVOKE EXECUTE ON ROUTINES FROM PUBLIC;

COMMIT;
