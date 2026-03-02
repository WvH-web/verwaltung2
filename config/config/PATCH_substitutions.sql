-- ============================================================
-- WvH PATCH: Vertretungsplanung – fehlende Spalten
-- Abgestimmt auf DB-Stand vom 01.03.2026
-- Ausführen in phpMyAdmin → wvhdata1
-- ============================================================

-- 1. Neue Spalten in substitutions
--    (ENUM 'pending_confirm' wurde bereits in Query 1 des fehlgeschlagenen
--     ersten Versuchs ergänzt und ist laut Dump bereits vorhanden)

ALTER TABLE `substitutions`
  ADD COLUMN `self_assigned_by` INT UNSIGNED DEFAULT NULL
    COMMENT 'Teacher-ID: Kollege hat sich selbst eingetragen'
    AFTER `substitute_teacher_id`,
  ADD COLUMN `self_assigned_at` DATETIME DEFAULT NULL
    AFTER `self_assigned_by`,
  ADD COLUMN `released_by` INT UNSIGNED DEFAULT NULL
    COMMENT 'Teacher-ID: wer hat die Stunde freigegeben'
    AFTER `released_at`,
  ADD COLUMN `notes` TEXT DEFAULT NULL
    COMMENT 'Freitext-Notiz zur Vertretung'
    AFTER `resolution_notes`;

-- 2. FK self_assigned_by
ALTER TABLE `substitutions`
  ADD CONSTRAINT `fk_sub_self_assigned`
    FOREIGN KEY (`self_assigned_by`)
    REFERENCES `teachers`(`id`) ON DELETE SET NULL;

-- 3. Benachrichtigungs-Tabelle
CREATE TABLE `substitution_notifications` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `substitution_id` BIGINT UNSIGNED NOT NULL,
  `recipient_id`    INT UNSIGNED    NOT NULL,
  `type`            ENUM('pending_confirm','confirmed','rejected','assigned','released')
                    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_confirm',
  `message`         VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read`         TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_recipient` (`recipient_id`, `is_read`),
  KEY `idx_notif_sub` (`substitution_id`),
  CONSTRAINT `fk_notif_sub`
    FOREIGN KEY (`substitution_id`) REFERENCES `substitutions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Benachrichtigungen für Vertretungsanfragen';

SELECT 'PATCH erfolgreich – 3 Statements' AS status;
