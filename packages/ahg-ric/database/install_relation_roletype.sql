-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Add an optional agent-role to a reified relation.
-- =================================================
--
-- Context:
--   A participant relation (Activity/Event --performed_by--> Agent) can now
--   carry the ROLE the agent played in that relation — e.g. "bride" at a
--   wedding Event. RiC-O 1.1 has no generic property for this; OpenRiC models
--   it with openricx:relationHasAgentRole (see tools/openric_ext.ttl), whose
--   value is a rico:RoleType. In the store, a RoleType is an ordinary term,
--   so the role is a nullable FK from ric_relation_meta to term.id.
--
--   The serializer (RicSerializationService::serializeActivity) emits, for any
--   participant whose relation has role_term_id set, a reified rico:EventRelation
--   node with openricx:relationHasAgentRole -> rico:RoleType, alongside the
--   existing rico:hasOrHadParticipant shortcut.
--
-- Idempotency:
--   Guarded on information_schema so a second run is a no-op.
--
-- Usage:
--   mysql <dbname> < packages/ahg-ric/database/install_relation_roletype.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'ric_relation_meta'
      AND COLUMN_NAME  = 'role_term_id'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `ric_relation_meta`
        ADD COLUMN `role_term_id` INT NULL DEFAULT NULL AFTER `evidence`,
        ADD INDEX `idx_rrm_role_term` (`role_term_id`)',
    'SELECT ''role_term_id already present — nothing to do'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify ----------------------------------------------------------------------
SELECT 'ric_relation_meta.role_term_id present' AS metric,
       COUNT(*)                                  AS n
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'ric_relation_meta'
  AND COLUMN_NAME  = 'role_term_id';
