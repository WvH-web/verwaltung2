-- ============================================================
-- WvH PATCH: Ferien & Feiertage Tabelle
-- BW / Stuttgart Schuljahr 2025/2026
-- Ausführen in phpMyAdmin → wvhdata1 → SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS `school_holidays` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `school_year`    VARCHAR(9)      NOT NULL DEFAULT '2025-2026',
  `name`           VARCHAR(120)    NOT NULL,
  `type`           ENUM(
                     'sommerferien','herbstferien','weihnachtsferien',
                     'osterferien','pfingstferien','faschingsferien',
                     'feiertag','beweglicher_ferientag','sonstiges'
                   ) NOT NULL DEFAULT 'sonstiges',
  `date_from`      DATE            NOT NULL,
  `date_until`     DATE            NOT NULL,
  `is_school_free` TINYINT(1)      NOT NULL DEFAULT 1,
  `notes`          VARCHAR(255)    DEFAULT NULL,
  `created_by`     INT UNSIGNED    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hol_dates` (`date_from`, `date_until`),
  KEY `idx_hol_year`  (`school_year`),
  CONSTRAINT `fk_hol_user` FOREIGN KEY (`created_by`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Daten: Stuttgart / BW · Schuljahr 2025/2026
-- (b) = beweglicher Ferientag
-- ============================================================
INSERT INTO `school_holidays`
  (`school_year`,`name`,`type`,`date_from`,`date_until`,`notes`)
VALUES
-- Sommerferien 2025 (Schuljahr-Start)
('2025-2026','Sommerferien 2025','sommerferien','2025-09-01','2025-09-13',
 'BW Sommerferien 2025 · Mo 01.09.–Sa 13.09.2025'),

-- Feiertag Oktober
('2025-2026','Tag der Deutschen Einheit','feiertag','2025-10-03','2025-10-03',
 'Gesetzlicher Feiertag'),
('2025-2026','Brückentag nach Tag d. Einheit','beweglicher_ferientag','2025-10-04','2025-10-04',
 'Beweglicher Ferientag (b)'),

-- Herbstferien 2025
('2025-2026','Herbstferien 2025','herbstferien','2025-10-27','2025-10-31',
 'BW Herbstferien 2025'),
('2025-2026','Allerheiligen','feiertag','2025-11-01','2025-11-01',
 'Gesetzlicher Feiertag BW'),

-- Weihnachtsferien 2025/2026
('2025-2026','Weihnachtsferien 2025/26','weihnachtsferien','2025-12-22','2026-01-06',
 'BW Weihnachtsferien · inkl. Feiertage und Neujahr'),
('2025-2026','1. Weihnachtsfeiertag','feiertag','2025-12-25','2025-12-25',
 'Gesetzlicher Feiertag'),
('2025-2026','2. Weihnachtsfeiertag','feiertag','2025-12-26','2025-12-26',
 'Gesetzlicher Feiertag'),
('2025-2026','Silvester','feiertag','2025-12-31','2025-12-31',
 'Schulfreier Tag'),
('2025-2026','Neujahr 2026','feiertag','2026-01-01','2026-01-01',
 'Gesetzlicher Feiertag'),
('2025-2026','Heilige Drei Könige','feiertag','2026-01-06','2026-01-06',
 'Gesetzlicher Feiertag BW'),

-- Faschingsferien 2026
('2025-2026','Faschingsferien 2026','faschingsferien','2026-02-16','2026-02-21',
 'BW Faschingsferien (b) · Mo 16.02.–Sa 21.02.2026'),

-- Osterferien 2026
('2025-2026','Osterferien 2026','osterferien','2026-03-30','2026-04-11',
 'BW Osterferien · Mo 30.03.–Sa 11.04.2026'),
('2025-2026','Karfreitag','feiertag','2026-04-03','2026-04-03',
 'Gesetzlicher Feiertag'),
('2025-2026','Ostermontag','feiertag','2026-04-06','2026-04-06',
 'Gesetzlicher Feiertag'),

-- Mai / Himmelfahrt
('2025-2026','Tag der Arbeit','feiertag','2026-05-01','2026-05-01',
 'Gesetzlicher Feiertag'),
('2025-2026','Brückentage Maifeiertag','beweglicher_ferientag','2026-05-02','2026-05-03',
 'Bewegliche Ferientage (b)'),
('2025-2026','Christi Himmelfahrt','feiertag','2026-05-14','2026-05-14',
 'Gesetzlicher Feiertag'),
('2025-2026','Brückentage Himmelfahrt','beweglicher_ferientag','2026-05-15','2026-05-16',
 'Bewegliche Ferientage (b)'),
('2025-2026','Pfingstmontag','feiertag','2026-05-25','2026-05-25',
 'Gesetzlicher Feiertag'),

-- Pfingstferien 2026
('2025-2026','Pfingstferien 2026','pfingstferien','2026-05-26','2026-06-06',
 'BW Pfingstferien · Di 26.05.–Sa 06.06.2026'),
('2025-2026','Fronleichnam','feiertag','2026-06-04','2026-06-04',
 'Gesetzlicher Feiertag BW'),

-- Sommerferien 2026
('2025-2026','Sommerferien 2026','sommerferien','2026-07-30','2026-09-12',
 'BW Sommerferien 2026 · Do 30.07.–Sa 12.09.2026');

-- ============================================================
SELECT type, name, date_from, date_until, notes
FROM school_holidays
WHERE school_year = '2025-2026'
ORDER BY date_from;
