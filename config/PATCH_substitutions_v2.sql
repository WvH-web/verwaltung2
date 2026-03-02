-- ============================================================
-- PATCH: Vertretungsplanung v2
-- Erweitert substitutions + school_calendar
-- ============================================================

-- 1. proposed_by + proposal_notes zu substitutions
ALTER TABLE `substitutions`
  ADD COLUMN IF NOT EXISTS `proposed_by_teacher_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'Lehrkraft, die sich selbst als Vertretung eingetragen hat'
    AFTER `substitute_teacher_id`,
  ADD COLUMN IF NOT EXISTS `proposed_at`  DATETIME DEFAULT NULL AFTER `proposed_by_teacher_id`,
  ADD COLUMN IF NOT EXISTS `proposal_notes` TEXT     DEFAULT NULL AFTER `proposed_at`,
  ADD COLUMN IF NOT EXISTS `original_teacher_decision` 
    ENUM('pending','accepted','rejected') DEFAULT NULL
    COMMENT 'Entscheidung der ursprünglichen Lehrkraft' AFTER `proposal_notes`;

-- FK nur wenn noch nicht vorhanden
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'substitutions'
    AND CONSTRAINT_NAME = 'fk_sub_proposed_by'
);
-- safe: IF NOT EXISTS via CREATE IF NOT EXISTS ist nicht möglich bei FK
-- daher manuell prüfen via stored procedure trick

-- 2. Index
ALTER TABLE `substitutions`
  ADD INDEX IF NOT EXISTS `idx_sub_proposed` (`proposed_by_teacher_id`);

-- 3. Sonderveranstaltungen: school_calendar hat bereits type='sonderevent'
--    Sicherstellen dass calendar_event_classes korrekt existiert (schon im Schema)

-- 4. lesson_instances: "notes" Feld für Sonderinfos
ALTER TABLE `lesson_instances`
  ADD COLUMN IF NOT EXISTS `release_notes` VARCHAR(255) DEFAULT NULL
    COMMENT 'Grund der Freigabe' AFTER `is_billable`,
  ADD COLUMN IF NOT EXISTS `released_by` INT UNSIGNED DEFAULT NULL
    COMMENT 'Wer die Stunde freigegeben hat' AFTER `release_notes`;

SELECT 'PATCH_substitutions_v2 applied' AS result;
