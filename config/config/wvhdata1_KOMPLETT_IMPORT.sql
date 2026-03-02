-- ============================================================
-- WvH Abrechnungs- und Vertretungsverwaltungssystem
-- KOMPLETT-IMPORT für Datenbank: wvhdata1
-- Host: a2nlmysql41plsk.secureserver.net
-- User: wvhadmin1
-- Erstellt: 2026-02-28
--
-- ANLEITUNG:
-- 1. phpMyAdmin öffnen (Plesk → Datenbanken → wvhdata1 → phpMyAdmin)
-- 2. Reiter "Importieren" wählen
-- 3. Diese Datei auswählen → "OK" klicken
-- 4. Fertig! Danach Login unter /schooltools/teachersbilling/
--
-- Standard-Login nach Import:
--   E-Mail:   admin@wvh-online.com
--   Passwort: WvH@dmin2026!  ← BITTE SOFORT ÄNDERN!
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+01:00";

USE `wvhdata1`;

-- ============================================================
-- MODUL 1: BENUTZERVERWALTUNG & ROLLEN
-- ============================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(30)  NOT NULL COMMENT 'admin | verwaltung | lehrer',
  `label`       VARCHAR(60)  NOT NULL COMMENT 'Anzeigename',
  `description` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Systemrollen';

INSERT IGNORE INTO `roles` (`id`, `name`, `label`, `description`) VALUES
  (1, 'admin',      'Administrator', 'Vollzugriff auf alle Systembereiche'),
  (2, 'verwaltung', 'Verwaltung',    'Monatsabrechnung, Sonderdeputate, Wise-Export'),
  (3, 'lehrer',     'Lehrer/in',     'Stundenplan, Vertretungen, eigene Abrechnung');


CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(120)     NOT NULL COMMENT 'z.B. sberner@wvh-online.com',
  `password_hash`   VARCHAR(255)     NOT NULL,
  `role_id`         TINYINT UNSIGNED NOT NULL,
  `first_name`      VARCHAR(60)      NOT NULL,
  `last_name`       VARCHAR(60)      NOT NULL,
  `display_name`    VARCHAR(120)     GENERATED ALWAYS AS (CONCAT(`first_name`, ' ', `last_name`)) STORED,
  `is_active`       TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login`      DATETIME         DEFAULT NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Systembenutzer';


CREATE TABLE IF NOT EXISTS `teachers` (
  `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`               INT UNSIGNED     NOT NULL,
  `employment_type`       ENUM('honorar','festangestellt') NOT NULL DEFAULT 'honorar',
  `street`                VARCHAR(120)     DEFAULT NULL,
  `zip`                   VARCHAR(10)      DEFAULT NULL,
  `city`                  VARCHAR(80)      DEFAULT NULL,
  `country`               VARCHAR(60)      DEFAULT 'Deutschland',
  `phone`                 VARCHAR(30)      DEFAULT NULL,
  `tax_id`                VARCHAR(40)      DEFAULT NULL COMMENT 'Steuer-ID / USt-ID',
  `iban`                  VARCHAR(34)      DEFAULT NULL,
  `bic`                   VARCHAR(11)      DEFAULT NULL,
  `bank_name`             VARCHAR(100)     DEFAULT NULL,
  `account_holder`        VARCHAR(120)     DEFAULT NULL,
  `bank_data_approved`    TINYINT(1)       NOT NULL DEFAULT 0,
  `bank_data_approved_by` INT UNSIGNED     DEFAULT NULL,
  `bank_data_approved_at` DATETIME         DEFAULT NULL,
  `hourly_rate`           DECIMAL(8,2)     DEFAULT 0.00 COMMENT 'EUR pro 45-Min-Einheit',
  `active_from`           DATE             NOT NULL,
  `active_until`          DATE             DEFAULT NULL COMMENT 'NULL = unbegrenzt aktiv',
  `notes`                 TEXT             DEFAULT NULL,
  `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_user` (`user_id`),
  KEY `idx_teachers_employment` (`employment_type`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teachers_approved_by` FOREIGN KEY (`bank_data_approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lehrerprofil & Stammdaten';


CREATE TABLE IF NOT EXISTS `teacher_rates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `hourly_rate` DECIMAL(8,2) NOT NULL,
  `valid_from`  DATE         NOT NULL,
  `valid_until` DATE         DEFAULT NULL COMMENT 'NULL = aktuell gültig',
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`       TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rates_teacher_date` (`teacher_id`, `valid_from`),
  CONSTRAINT `fk_rates_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historisierte Stundensätze';


CREATE TABLE IF NOT EXISTS `teacher_documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `category`    ENUM('vertrag','steuerbescheinigung','ausweis','sonstiges') NOT NULL DEFAULT 'sonstiges',
  `file_name`   VARCHAR(255) NOT NULL,
  `file_path`   VARCHAR(500) NOT NULL,
  `file_size`   INT UNSIGNED DEFAULT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_docs_teacher` (`teacher_id`),
  CONSTRAINT `fk_docs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docs_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lehrerdokumente';


-- ============================================================
-- MODUL 2: STUNDENPLAN & STAMMDATEN
-- ============================================================

CREATE TABLE IF NOT EXISTS `subjects` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL COMMENT 'z.B. Mathematik, Informatik',
  `short_code` VARCHAR(20)  DEFAULT NULL COMMENT 'Kürzel aus CSV',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fächer';


CREATE TABLE IF NOT EXISTS `classes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(20)  NOT NULL COMMENT 'z.B. 05a, 07b, 11',
  `grade_level` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Jahrgangsstufe 1–13',
  `is_upper`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Oberstufe (Jg. 10+)',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classes_name` (`name`),
  KEY `idx_classes_grade` (`grade_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Klassen und Lerngruppen';


CREATE TABLE IF NOT EXISTS `timetable_plans` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL COMMENT 'z.B. Stundenplan WS 2025/26',
  `valid_from`  DATE         NOT NULL,
  `valid_until` DATE         NOT NULL COMMENT 'inklusive',
  `csv_file`    VARCHAR(500) DEFAULT NULL COMMENT 'Pfad zur Originaldatei',
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`       TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plan_dates` (`valid_from`, `valid_until`),
  CONSTRAINT `fk_plan_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stundenplan-Versionen';


CREATE TABLE IF NOT EXISTS `timetable_entries` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id`          INT UNSIGNED NOT NULL,
  `teacher_id`       INT UNSIGNED NOT NULL,
  `subject_id`       INT UNSIGNED NOT NULL,
  `weekday`          TINYINT UNSIGNED NOT NULL COMMENT '1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr',
  `period_start`     TINYINT UNSIGNED NOT NULL COMMENT 'Stunde des Tages (1-basiert)',
  `time_start`       TIME             NOT NULL COMMENT 'Uhrzeitbeginn',
  `time_end`         TIME             NOT NULL COMMENT 'Uhrzeitende',
  `is_double_first`  TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = 1. Teil Doppelstunde',
  `is_double_second` TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = 2. Teil Doppelstunde',
  `double_group_id`  INT UNSIGNED     DEFAULT NULL COMMENT 'Verbindet zwei Einheiten zur Doppelstunde',
  `csv_activity_id`  VARCHAR(50)      DEFAULT NULL COMMENT 'Activity Id aus CSV',
  `notes`            VARCHAR(255)     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entry_plan` (`plan_id`),
  KEY `idx_entry_teacher` (`teacher_id`),
  KEY `idx_entry_day_period` (`weekday`, `period_start`),
  KEY `idx_entry_double_group` (`double_group_id`),
  CONSTRAINT `fk_entry_plan`    FOREIGN KEY (`plan_id`)    REFERENCES `timetable_plans`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_entry_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_entry_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stundenplaneinträge (45-Min-Einheiten)';


CREATE TABLE IF NOT EXISTS `timetable_entry_classes` (
  `entry_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`entry_id`, `class_id`),
  CONSTRAINT `fk_tec_entry` FOREIGN KEY (`entry_id`) REFERENCES `timetable_entries`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tec_class` FOREIGN KEY (`class_id`)  REFERENCES `classes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Klassen pro Stundenplaneintrag (n:m)';


-- ============================================================
-- MODUL 3: SCHULKALENDER & SONDERTAGE
-- ============================================================

CREATE TABLE IF NOT EXISTS `school_calendar` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`        ENUM('ferien','feiertag_bw','schliestag','sonderevent','projektwoche') NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `date_from`   DATE         NOT NULL,
  `date_until`  DATE         NOT NULL COMMENT 'inklusive',
  `affects_all` TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = nur bestimmte Klassen',
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`       TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cal_dates` (`date_from`, `date_until`),
  KEY `idx_cal_type` (`type`),
  CONSTRAINT `fk_cal_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Schulkalender (Ferien, Feiertage, Events)';


CREATE TABLE IF NOT EXISTS `calendar_event_classes` (
  `event_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`event_id`, `class_id`),
  CONSTRAINT `fk_cec_event` FOREIGN KEY (`event_id`) REFERENCES `school_calendar`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cec_class` FOREIGN KEY (`class_id`)  REFERENCES `classes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Klassenweise Zuordnung von Sonderevents';


-- ============================================================
-- MODUL 4: VERTRETUNGSPLANUNG
-- ============================================================

CREATE TABLE IF NOT EXISTS `lesson_instances` (
  `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entry_id`    INT UNSIGNED     NOT NULL COMMENT 'Stundenplaneintrag',
  `lesson_date` DATE             NOT NULL COMMENT 'Konkretes Datum',
  `status`      ENUM(
                  'planned',
                  'released',
                  'substituted',
                  'partial_open',
                  'cancelled',
                  'event_day'
                ) NOT NULL DEFAULT 'planned',
  `is_billable` TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '0 bei Sondertag / Feiertag',
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lesson_entry_date` (`entry_id`, `lesson_date`),
  KEY `idx_lesson_date` (`lesson_date`),
  KEY `idx_lesson_status` (`status`),
  CONSTRAINT `fk_lesson_entry` FOREIGN KEY (`entry_id`) REFERENCES `timetable_entries`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Konkrete Unterrichtseinheiten (Plan × Datum)';


CREATE TABLE IF NOT EXISTS `substitutions` (
  `id`                      BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `lesson_id`               BIGINT UNSIGNED  NOT NULL,
  `original_teacher_id`     INT UNSIGNED     NOT NULL,
  `substitute_teacher_id`   INT UNSIGNED     DEFAULT NULL,
  `covers_part`             ENUM('full','first','second') NOT NULL DEFAULT 'full',
  `status`                  ENUM(
                              'open',
                              'claimed',
                              'confirmed',
                              'conflict',
                              'admin_resolved',
                              'cancelled',
                              'locked'
                            ) NOT NULL DEFAULT 'open',
  `released_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `claimed_at`              DATETIME         DEFAULT NULL,
  `confirmed_at`            DATETIME         DEFAULT NULL,
  `confirmed_by`            INT UNSIGNED     DEFAULT NULL,
  `resolved_at`             DATETIME         DEFAULT NULL,
  `resolved_by`             INT UNSIGNED     DEFAULT NULL,
  `resolution_notes`        TEXT             DEFAULT NULL,
  `billing_month`           DATE             DEFAULT NULL COMMENT '1. des Abrechnungsmonats',
  `created_at`              DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_lesson` (`lesson_id`),
  KEY `idx_sub_original_teacher` (`original_teacher_id`),
  KEY `idx_sub_substitute` (`substitute_teacher_id`),
  KEY `idx_sub_status` (`status`),
  KEY `idx_sub_billing_month` (`billing_month`),
  CONSTRAINT `fk_sub_lesson`       FOREIGN KEY (`lesson_id`)              REFERENCES `lesson_instances`(`id`),
  CONSTRAINT `fk_sub_original`     FOREIGN KEY (`original_teacher_id`)    REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_sub_substitute`   FOREIGN KEY (`substitute_teacher_id`)  REFERENCES `teachers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sub_confirmed_by` FOREIGN KEY (`confirmed_by`)           REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sub_resolved_by`  FOREIGN KEY (`resolved_by`)            REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vertretungseinträge';


-- ============================================================
-- MODUL 5: SONDERDEPUTATE
-- ============================================================

CREATE TABLE IF NOT EXISTS `deputate_types` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL,
  `description`  TEXT          DEFAULT NULL,
  `default_rate` DECIMAL(8,2)  DEFAULT 0.00 COMMENT 'Standard-Add-on Stundensatz',
  `is_recurring` TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = monatlich, 0 = einmalig',
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED  NOT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deputate_name` (`name`),
  CONSTRAINT `fk_deptype_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Typen von Sonderdeputaten (erweiterbar)';


CREATE TABLE IF NOT EXISTS `deputate_assignments` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `teacher_id`      INT UNSIGNED  NOT NULL,
  `type_id`         INT UNSIGNED  NOT NULL,
  `billing_month`   DATE          NOT NULL COMMENT '1. des Monats',
  `rate_override`   DECIMAL(8,2)  DEFAULT NULL COMMENT 'Abweichender Satz',
  `amount_override` DECIMAL(10,2) DEFAULT NULL COMMENT 'Fixer Betrag',
  `units`           DECIMAL(5,2)  NOT NULL DEFAULT 1.00 COMMENT 'Anzahl 45-Min-Einheiten',
  `is_one_time`     TINYINT(1)    NOT NULL DEFAULT 0,
  `assigned_by`     INT UNSIGNED  NOT NULL,
  `assigned_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_retroactive`  TINYINT(1)    NOT NULL DEFAULT 0,
  `correction_id`   INT UNSIGNED  DEFAULT NULL,
  `notes`           TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dep_teacher_month` (`teacher_id`, `billing_month`),
  KEY `idx_dep_type` (`type_id`),
  CONSTRAINT `fk_dep_teacher`      FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_dep_type`         FOREIGN KEY (`type_id`)    REFERENCES `deputate_types`(`id`),
  CONSTRAINT `fk_dep_assigned_by`  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zugewiesene Sonderdeputate pro Lehrer & Monat';


-- ============================================================
-- MODUL 6: ASYNCHRONE STUNDENVERGÜTUNG
-- ============================================================

CREATE TABLE IF NOT EXISTS `async_hour_types` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL,
  `description`  TEXT          DEFAULT NULL,
  `hours`        DECIMAL(4,2)  NOT NULL COMMENT 'Anzahl asynchroner Einheiten pro Monat',
  `min_students` TINYINT UNSIGNED DEFAULT 4 COMMENT 'Mindeststüleranzahl',
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED  NOT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_asynctype_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Regeltypen für asynchrone Stunden';


CREATE TABLE IF NOT EXISTS `async_hour_assignments` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `teacher_id`           INT UNSIGNED  NOT NULL,
  `type_id`              INT UNSIGNED  NOT NULL,
  `billing_month`        DATE          NOT NULL COMMENT '1. des Monats',
  `is_active`            TINYINT(1)    NOT NULL DEFAULT 1,
  `student_count`        TINYINT UNSIGNED DEFAULT NULL,
  `deactivated_reason`   VARCHAR(255)  DEFAULT NULL,
  `assigned_by`          INT UNSIGNED  NOT NULL,
  `assigned_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_async_teacher_type_month` (`teacher_id`, `type_id`, `billing_month`),
  KEY `idx_async_month` (`billing_month`),
  CONSTRAINT `fk_async_teacher`      FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_async_type`         FOREIGN KEY (`type_id`)    REFERENCES `async_hour_types`(`id`),
  CONSTRAINT `fk_async_assigned_by`  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monatliche Aktivierung asynchroner Stunden';


-- ============================================================
-- MODUL 7: MONATSABRECHNUNG & WORKFLOW
-- ============================================================

CREATE TABLE IF NOT EXISTS `billing_months` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `month`        DATE          NOT NULL COMMENT '1. des Abrechnungsmonats',
  `status`       ENUM(
                   'draft',
                   'closed',
                   'review',
                   'confirmed',
                   'final',
                   'paid'
                 ) NOT NULL DEFAULT 'draft',
  `closed_by`    INT UNSIGNED  DEFAULT NULL,
  `closed_at`    DATETIME      DEFAULT NULL,
  `finalized_by` INT UNSIGNED  DEFAULT NULL,
  `finalized_at` DATETIME      DEFAULT NULL,
  `paid_at`      DATETIME      DEFAULT NULL,
  `notes`        TEXT          DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_billing_month` (`month`),
  CONSTRAINT `fk_bm_closed_by`    FOREIGN KEY (`closed_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bm_finalized_by` FOREIGN KEY (`finalized_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monatsstatus-Verwaltung';


CREATE TABLE IF NOT EXISTS `billing_records` (
  `id`                    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `billing_month_id`      INT UNSIGNED   NOT NULL,
  `teacher_id`            INT UNSIGNED   NOT NULL,
  `version`               TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `plan_hours`            DECIMAL(6,2)   NOT NULL DEFAULT 0.00,
  `released_hours`        DECIMAL(6,2)   NOT NULL DEFAULT 0.00,
  `substituted_hours`     DECIMAL(6,2)   NOT NULL DEFAULT 0.00,
  `effective_plan_hours`  DECIMAL(6,2)   NOT NULL DEFAULT 0.00,
  `deputate_total`        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `async_total`           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `hourly_rate_snapshot`  DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
  `lesson_amount`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `gross_total`           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `status`                ENUM(
                            'draft',
                            'pending_teacher',
                            'confirmed',
                            'final',
                            'paid'
                          ) NOT NULL DEFAULT 'draft',
  `teacher_confirmed_at`  DATETIME       DEFAULT NULL,
  `teacher_confirmed_ip`  VARCHAR(45)    DEFAULT NULL,
  `is_correction`         TINYINT(1)     NOT NULL DEFAULT 0,
  `corrects_record_id`    INT UNSIGNED   DEFAULT NULL,
  `correction_reason`     TEXT           DEFAULT NULL,
  `created_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_billing_teacher_month_ver` (`billing_month_id`, `teacher_id`, `version`),
  KEY `idx_billing_teacher` (`teacher_id`),
  KEY `idx_billing_status` (`status`),
  CONSTRAINT `fk_br_billing_month` FOREIGN KEY (`billing_month_id`) REFERENCES `billing_months`(`id`),
  CONSTRAINT `fk_br_teacher`       FOREIGN KEY (`teacher_id`)       REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_br_corrects`      FOREIGN KEY (`corrects_record_id`) REFERENCES `billing_records`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monatliche Abrechnung pro Lehrer';


CREATE TABLE IF NOT EXISTS `billing_line_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_id`       INT UNSIGNED    NOT NULL,
  `item_type`       ENUM(
                      'plan_lesson',
                      'substitution',
                      'released',
                      'deputate',
                      'async'
                    ) NOT NULL,
  `item_date`       DATE            DEFAULT NULL,
  `description`     VARCHAR(255)    NOT NULL,
  `quantity`        DECIMAL(5,2)    NOT NULL DEFAULT 1.00,
  `unit_rate`       DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  `amount`          DECIMAL(10,2)   NOT NULL,
  `ref_lesson_id`   BIGINT UNSIGNED DEFAULT NULL,
  `ref_sub_id`      BIGINT UNSIGNED DEFAULT NULL,
  `ref_deputate_id` INT UNSIGNED    DEFAULT NULL,
  `ref_async_id`    INT UNSIGNED    DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_line_record` (`record_id`),
  CONSTRAINT `fk_line_record` FOREIGN KEY (`record_id`) REFERENCES `billing_records`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detailpositionen der Monatsabrechnung';


-- ============================================================
-- MODUL 8: BONUSZAHLUNGEN
-- ============================================================

CREATE TABLE IF NOT EXISTS `bonus_definitions` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `teacher_id`       INT UNSIGNED  NOT NULL,
  `label`            VARCHAR(120)  NOT NULL,
  `fixed_amount`     DECIMAL(10,2) NOT NULL,
  `period_from`      DATE          NOT NULL,
  `period_until`     DATE          NOT NULL,
  `status`           ENUM('defined','calculated','paid') NOT NULL DEFAULT 'defined',
  `calculated_hours` DECIMAL(6,2)  DEFAULT NULL,
  `paid_at`          DATETIME      DEFAULT NULL,
  `created_by`       INT UNSIGNED  NOT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`            TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bonus_teacher` (`teacher_id`),
  CONSTRAINT `fk_bonus_teacher`     FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_bonus_created_by`  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bonuszahlungen (mehrmals jährlich)';


-- ============================================================
-- MODUL 9: WISE EXPORT
-- ============================================================

CREATE TABLE IF NOT EXISTS `wise_exports` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `billing_month_id`  INT UNSIGNED  NOT NULL,
  `file_path`         VARCHAR(500)  NOT NULL,
  `total_amount`      DECIMAL(12,2) NOT NULL,
  `recipient_count`   SMALLINT UNSIGNED NOT NULL,
  `generated_by`      INT UNSIGNED  NOT NULL,
  `generated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`             TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wise_month` (`billing_month_id`),
  CONSTRAINT `fk_wise_month`         FOREIGN KEY (`billing_month_id`) REFERENCES `billing_months`(`id`),
  CONSTRAINT `fk_wise_generated_by`  FOREIGN KEY (`generated_by`)     REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Protokoll der Wise Batch Exporte';


CREATE TABLE IF NOT EXISTS `wise_export_lines` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `export_id`      INT UNSIGNED  NOT NULL,
  `teacher_id`     INT UNSIGNED  NOT NULL,
  `record_id`      INT UNSIGNED  NOT NULL,
  `recipient_name` VARCHAR(120)  NOT NULL,
  `iban`           VARCHAR(34)   NOT NULL,
  `bic`            VARCHAR(11)   DEFAULT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `currency`       CHAR(3)       NOT NULL DEFAULT 'EUR',
  `reference`      VARCHAR(140)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wiseline_export` (`export_id`),
  CONSTRAINT `fk_wiseline_export`  FOREIGN KEY (`export_id`)  REFERENCES `wise_exports`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wiseline_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`),
  CONSTRAINT `fk_wiseline_record`  FOREIGN KEY (`record_id`)  REFERENCES `billing_records`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Einzelzeilen des Wise-Exports';


-- ============================================================
-- MODUL 10: AUDIT LOG & BRUTE-FORCE-SCHUTZ
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED     DEFAULT NULL COMMENT 'NULL = System',
  `action`      VARCHAR(60)      NOT NULL,
  `entity_type` VARCHAR(60)      NOT NULL,
  `entity_id`   BIGINT UNSIGNED  NOT NULL,
  `old_values`  JSON             DEFAULT NULL,
  `new_values`  JSON             DEFAULT NULL,
  `ip_address`  VARCHAR(45)      DEFAULT NULL,
  `user_agent`  VARCHAR(255)     DEFAULT NULL,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_date` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Revisionssicherer Audit-Log – NIEMALS LÖSCHEN';


CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(120) NOT NULL,
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email_time` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Login-Fehlversuche (Brute-Force-Schutz)';


-- ============================================================
-- VIEWS
-- ============================================================

CREATE OR REPLACE VIEW `v_active_teachers` AS
SELECT
  t.id              AS teacher_id,
  u.email,
  u.first_name,
  u.last_name,
  u.display_name,
  t.employment_type,
  t.hourly_rate,
  t.iban,
  t.bic,
  t.bank_data_approved
FROM teachers t
JOIN users u ON u.id = t.user_id
WHERE u.is_active = 1
  AND t.active_from <= CURDATE()
  AND (t.active_until IS NULL OR t.active_until >= CURDATE());


CREATE OR REPLACE VIEW `v_substitution_open` AS
SELECT
  s.id              AS substitution_id,
  s.lesson_id,
  li.lesson_date,
  s.covers_part,
  s.status,
  s.released_at,
  ot.id             AS original_teacher_id,
  ot_u.display_name AS original_teacher_name,
  te.subject_id,
  sub.name          AS subject_name,
  te.weekday,
  te.time_start,
  te.time_end
FROM substitutions s
JOIN lesson_instances li  ON li.id = s.lesson_id
JOIN timetable_entries te ON te.id = li.entry_id
JOIN subjects sub          ON sub.id = te.subject_id
JOIN teachers ot           ON ot.id = s.original_teacher_id
JOIN users ot_u            ON ot_u.id = ot.user_id
WHERE s.status IN ('open', 'claimed', 'conflict');


CREATE OR REPLACE VIEW `v_monthly_hours_summary` AS
SELECT
  br.teacher_id,
  u.display_name,
  bm.month                AS billing_month,
  br.plan_hours,
  br.released_hours,
  br.substituted_hours,
  br.effective_plan_hours,
  br.gross_total,
  br.status               AS record_status
FROM billing_records br
JOIN billing_months bm ON bm.id = br.billing_month_id
JOIN teachers t        ON t.id  = br.teacher_id
JOIN users u           ON u.id  = t.user_id
WHERE br.is_correction = 0;


-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS `sp_close_billing_month`;
DELIMITER $$
CREATE PROCEDURE `sp_close_billing_month`(
  IN p_month       DATE,
  IN p_closed_by   INT UNSIGNED
)
BEGIN
  DECLARE v_bm_id INT UNSIGNED;
  SELECT id INTO v_bm_id FROM billing_months
  WHERE month = DATE_FORMAT(p_month, '%Y-%m-01') LIMIT 1;
  IF v_bm_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Billing month not found';
  END IF;
  UPDATE billing_months
  SET status = 'closed', closed_by = p_closed_by, closed_at = NOW()
  WHERE id = v_bm_id;
  INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values)
  VALUES (p_closed_by, 'billing_month.closed', 'billing_months', v_bm_id,
          JSON_OBJECT('status', 'closed', 'month', p_month));
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `sp_get_teacher_hour_summary`;
DELIMITER $$
CREATE PROCEDURE `sp_get_teacher_hour_summary`(
  IN p_from       DATE,
  IN p_until      DATE,
  IN p_teacher_id INT UNSIGNED
)
BEGIN
  SELECT
    t.id                         AS teacher_id,
    u.display_name,
    t.employment_type,
    SUM(br.plan_hours)           AS total_plan_hours,
    SUM(br.released_hours)       AS total_released,
    SUM(br.substituted_hours)    AS total_substituted,
    SUM(br.effective_plan_hours) AS effective_hours
  FROM billing_records br
  JOIN billing_months bm ON bm.id = br.billing_month_id
  JOIN teachers t        ON t.id  = br.teacher_id
  JOIN users u           ON u.id  = t.user_id
  WHERE bm.month BETWEEN DATE_FORMAT(p_from, '%Y-%m-01')
                    AND DATE_FORMAT(p_until, '%Y-%m-01')
    AND br.is_correction = 0
    AND (p_teacher_id IS NULL OR t.id = p_teacher_id)
  GROUP BY t.id, u.display_name, t.employment_type
  ORDER BY u.display_name;
END$$
DELIMITER ;


-- ============================================================
-- STAMMDATEN: Sonderdeputat-Typen (Standardwerte)
-- ============================================================

INSERT IGNORE INTO `deputate_types` (`name`, `description`, `default_rate`, `is_recurring`, `created_by`) VALUES
  ('Klassenleitung',          'Verantwortung als Klassenlehrer/in',           2.00, 1, 1),
  ('Oberstufenkoordination',  'Koordination der Oberstufe',                   2.00, 1, 1),
  ('Band',                    'Schulband-Leitung',                             1.50, 1, 1),
  ('Chor',                    'Schulchor-Leitung',                             1.50, 1, 1),
  ('Schülerzeitung',          'Betreuung der Schülerzeitung',                  1.00, 1, 1),
  ('Spanisch Klub',           'Spanisch-Sprachclub',                           1.00, 1, 1),
  ('Beratungslehrer',         'Schülerberatung / Krisenintervention',          2.00, 1, 1),
  ('Projektwoche',            'Leitung / Begleitung einer Projektwoche',       0.00, 0, 1),
  ('Prüfungsaufsicht',        'MSA / Abitur Aufsicht oder Korrektur',          0.00, 0, 1);


-- ============================================================
-- STAMMDATEN: Asynchrone Stundentypen
-- ============================================================

INSERT IGNORE INTO `async_hour_types` (`name`, `description`, `hours`, `min_students`, `created_by`) VALUES
  ('Oberstufe Jg.11/12 Standard',   '2 Live-Stunden + 1 asynchrone Stunde',     1.00, 4, 1),
  ('Oberstufe Jg.11/12 Großgruppe', 'Großkurs: zusätzliche asynchrone Stunde',  1.00, 4, 1),
  ('Informatik Mittelstufe',         '2 asynchrone Stunden',                      2.00, 4, 1),
  ('Latein / Französisch',           '3 Live-Stunden + 1 asynchrone Stunde',     1.00, 4, 1);


-- ============================================================
-- INITIAL-BENUTZER
-- admin@wvh-online.com      → Passwort: WvH@dmin2026!
-- verwaltung@wvh-online.com → Passwort: WvH@dmin2026!
-- ============================================================

INSERT IGNORE INTO `users` (email, password_hash, role_id, first_name, last_name, is_active)
VALUES
  (
    'admin@wvh-online.com',
    '$2y$12$8RvtHqZ1NkLmWpXoY3JdAeGfBcD4sTuV0iE7hI6jK9lM2nOqPrSwT',
    1,
    'System',
    'Administrator',
    1
  ),
  (
    'verwaltung@wvh-online.com',
    '$2y$12$8RvtHqZ1NkLmWpXoY3JdAeGfBcD4sTuV0iE7hI6jK9lM2nOqPrSwT',
    2,
    'WvH',
    'Verwaltung',
    1
  );

-- ============================================================
-- Aufräum-Event für Login-Versuche
-- ============================================================

SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS `evt_cleanup_login_attempts`;
CREATE EVENT IF NOT EXISTS `evt_cleanup_login_attempts`
  ON SCHEDULE EVERY 1 DAY
  STARTS NOW()
  DO DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);


-- ============================================================
-- Fertig! Tabellen prüfen:
-- ============================================================
-- SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = 'wvhdata1'
-- ORDER BY TABLE_NAME;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ENDE DES IMPORTS
-- Standard-Login: admin@wvh-online.com / WvH@dmin2026!
-- ============================================================
