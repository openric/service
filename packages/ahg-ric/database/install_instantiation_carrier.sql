-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Add an individual physical-carrier identity to an instantiation.
-- ================================================================
--
-- Context:
--   ric_instantiation.carrier_type records the KIND of medium (physical/audio/
--   video/...). It does not identify the individual physical object — e.g.
--   "this magnetic tape, shelf TAP-0042" — which several instantiations
--   (several tracks) may share. RiC-O 1.1 has no Carrier entity; OpenRiC models
--   it with openricx:Carrier (subclass of rico:Thing) via openricx:hasCarrier
--   (see tools/openric_ext.ttl). A Carrier class is planned for RiC-O 2.0
--   (Clavaud, Records_in_Contexts_users list 2026-07).
--
--   carrier_identifier is the physical object's citable identifier (shelf /
--   accession number). When set, RicSerializationService::serializeInstantiation
--   emits an openricx:Carrier node linked with openricx:hasCarrier, carrying the
--   carrier_type as its rico:hasCarrierType.
--
-- Idempotency:
--   Guarded on information_schema so a second run is a no-op.
--
-- Usage:
--   mysql <dbname> < packages/ahg-ric/database/install_instantiation_carrier.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'ric_instantiation'
      AND COLUMN_NAME  = 'carrier_identifier'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `ric_instantiation`
        ADD COLUMN `carrier_identifier` VARCHAR(255) NULL DEFAULT NULL
            COMMENT ''Identifier of the individual physical carrier (openricx:Carrier)''
            AFTER `carrier_type`,
        ADD INDEX `idx_ric_instantiation_carrier_ident` (`carrier_identifier`)',
    'SELECT ''carrier_identifier already present — nothing to do'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify ----------------------------------------------------------------------
SELECT 'ric_instantiation.carrier_identifier present' AS metric,
       COUNT(*)                                        AS n
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'ric_instantiation'
  AND COLUMN_NAME  = 'carrier_identifier';
