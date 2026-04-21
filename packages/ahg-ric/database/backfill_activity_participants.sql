-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- One-shot backfill: activity → participant agent links.
-- =====================================================
--
-- Context:
--   openric-spec v0.35.0 Provenance & Event profile requires every
--   rico:Production to carry rico:hasOrHadParticipant (a creator agent).
--   Service v0.8.13's serializer emits the predicate from relation rows
--   with dropdown_code='performed_by'. But the reference store had only
--   42 such rows against 222 Production activities — 187 missing.
--
-- What this script does:
--   For each Production-type ric_activity that lacks a performed_by
--   relation, find a creation event (event.type_id = 111) on the SAME
--   information_object with a non-null actor_id, and create a
--   performed_by relation from the activity to that actor.
--
--   Deterministic: the source data is already in the system — it's just
--   that the linkage was never materialised at seed time because the
--   activity's own source event had actor_id=NULL while a sibling
--   creation event on the same IO had it. The backfilled rows are
--   marked certainty='inferred' with a provenance note.
--
-- What this script does NOT do:
--   It does NOT invent creator agents where none are named anywhere on
--   the record. Surveyed coverage at 2026-04-21: ~10 activities
--   recoverable this way, ~175 with no attested creator in the store.
--   Those 175 are genuine archival gaps (ISAD(G) descriptions that
--   predate ISAAR authority records) and require curator judgment.
--
-- Idempotency:
--   Safe to re-run. The cursor selects only activities lacking
--   performed_by, so a second run has nothing to insert.
--
-- Usage:
--   mysql <dbname> < packages/ahg-ric/database/backfill_activity_participants.sql

DELIMITER //
DROP PROCEDURE IF EXISTS openric_backfill_participants //
CREATE PROCEDURE openric_backfill_participants()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_activity_id INT;
    DECLARE v_actor_id    INT;
    DECLARE v_start_date  DATE;
    DECLARE v_end_date    DATE;
    DECLARE v_rel_obj_id  INT;
    DECLARE v_count       INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT
            a.id                                            AS activity_id,
            (SELECT e2.actor_id FROM event e2
             WHERE e2.object_id = r.object_id
               AND e2.type_id = 111           -- creation event
               AND e2.actor_id IS NOT NULL
             ORDER BY e2.id LIMIT 1)                        AS actor_id,
            a.start_date,
            a.end_date
        FROM ric_activity a
        JOIN relation r          ON r.subject_id = a.id
        JOIN ric_relation_meta m ON m.relation_id = r.id AND m.dropdown_code = 'results_from'
        WHERE LOWER(COALESCE(a.type_id, '')) IN ('production', 'creation', 'contribution')
          AND NOT EXISTS (
              SELECT 1 FROM relation r2
              JOIN ric_relation_meta m2 ON m2.relation_id = r2.id
              WHERE r2.subject_id = a.id AND m2.dropdown_code = 'performed_by'
          )
        HAVING actor_id IS NOT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_activity_id, v_actor_id, v_start_date, v_end_date;
        IF done THEN LEAVE read_loop; END IF;

        -- Object row (PK anchor for the relation FK)
        INSERT INTO `object` (`class_name`, `serial_number`, `created_at`, `updated_at`)
        VALUES ('QubitRelation', 0, NOW(), NOW());
        SET v_rel_obj_id = LAST_INSERT_ID();

        -- The relation itself: Activity --performed-by--> Agent
        INSERT INTO `relation` (`id`, `subject_id`, `object_id`, `type_id`, `start_date`, `end_date`, `source_culture`)
        VALUES (v_rel_obj_id, v_activity_id, v_actor_id, NULL, v_start_date, v_end_date, 'en');

        -- RiC-O semantic metadata — marked as inferred, with provenance.
        INSERT INTO `ric_relation_meta`
            (`relation_id`, `rico_predicate`, `inverse_predicate`, `domain_class`, `range_class`, `dropdown_code`, `certainty`, `evidence`)
        VALUES
            (v_rel_obj_id, 'rico:isOrWasPerformedBy', 'rico:performsOrPerformed',
             'Activity', 'Agent', 'performed_by',
             'inferred',
             'Backfilled from a sibling creation event (type_id=111) with matching object_id on the same information_object. Source: packages/ahg-ric/database/backfill_activity_participants.sql (2026-04-21).');

        SET v_count = v_count + 1;
    END LOOP;
    CLOSE cur;

    SELECT CONCAT('Backfilled ', v_count, ' performed_by relation(s).') AS result;
END //
DELIMITER ;

CALL openric_backfill_participants();
DROP PROCEDURE openric_backfill_participants;

-- Verify ----------------------------------------------------------------------
SELECT 'Productions with participant after backfill' AS metric,
       COUNT(*) AS n
FROM ric_activity a
WHERE LOWER(COALESCE(a.type_id, '')) IN ('production', 'creation', 'contribution')
  AND EXISTS (
      SELECT 1 FROM relation r JOIN ric_relation_meta m ON r.id = m.relation_id
      WHERE r.subject_id = a.id AND m.dropdown_code = 'performed_by'
  )
UNION ALL
SELECT 'Productions still missing participant',
       COUNT(*)
FROM ric_activity a
WHERE LOWER(COALESCE(a.type_id, '')) IN ('production', 'creation', 'contribution')
  AND NOT EXISTS (
      SELECT 1 FROM relation r JOIN ric_relation_meta m ON r.id = m.relation_id
      WHERE r.subject_id = a.id AND m.dropdown_code = 'performed_by'
  );
