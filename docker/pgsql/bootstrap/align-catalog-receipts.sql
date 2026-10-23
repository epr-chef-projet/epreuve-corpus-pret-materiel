\set ON_ERROR_STOP on
BEGIN;
SET LOCAL lock_timeout = '2s';
SET LOCAL statement_timeout = '5s';
DO $guard$
DECLARE
    definition text;
    old_definition constant text := $$CHECK (((operation_type)::text = ANY ((ARRAY['catalog.equipment.create'::character varying, 'catalog.equipment.update_text'::character varying])::text[])))$$;
    new_definition constant text := $$CHECK (((operation_type)::text = ANY ((ARRAY['catalog.equipment.create'::character varying, 'catalog.equipment.update_text'::character varying, 'catalog.equipment.archive'::character varying, 'catalog.equipment.reactivate'::character varying])::text[])))$$;
BEGIN
    IF current_database() NOT IN ('laravel', 'testing') OR session_user <> 'epr_migrator' OR current_user <> 'epr_owner' OR current_schema() <> 'epr' THEN
        RAISE EXCEPTION 'Catalog receipt alignment target refused';
    END IF;
    IF NOT EXISTS (
        SELECT FROM epr.migrations a JOIN epr.migrations b ON a.batch=b.batch
        WHERE a.migration='2026_10_20_161455_create_catalog_equipment_table'
        AND b.migration='2026_10_20_161455_create_operation_receipts_table'
    ) THEN
        RAISE EXCEPTION 'Catalog migration history requires inspection';
    END IF;
    SELECT pg_get_constraintdef(oid) INTO definition FROM pg_constraint
    WHERE conrelid='epr.operation_receipts'::regclass AND conname='operation_receipts_type_check' AND convalidated;
    IF definition = new_definition THEN
        RETURN;
    END IF;
    IF definition IS DISTINCT FROM old_definition THEN
        RAISE EXCEPTION 'Unexpected Catalog receipt constraint';
    END IF;
    ALTER TABLE epr.operation_receipts DROP CONSTRAINT operation_receipts_type_check;
    ALTER TABLE epr.operation_receipts ADD CONSTRAINT operation_receipts_type_check
        CHECK (operation_type IN ('catalog.equipment.create', 'catalog.equipment.update_text', 'catalog.equipment.archive', 'catalog.equipment.reactivate'));
END;
$guard$;
COMMIT;
