-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: a2nlmysql41plsk.secureserver.net:3306
-- Generation Time: Mar 02, 2026 at 07:10 AM
-- Server version: 8.0.37-29
-- PHP Version: 8.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wvhdata1`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`wvhadmin1`@`%` PROCEDURE `sp_close_billing_month` (IN `p_month` DATE, IN `p_closed_by` INT UNSIGNED)   BEGIN
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

CREATE DEFINER=`wvhadmin1`@`%` PROCEDURE `sp_get_teacher_hour_summary` (IN `p_from` DATE, IN `p_until` DATE, IN `p_teacher_id` INT UNSIGNED)   BEGIN
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

-- --------------------------------------------------------

--
-- Table structure for table `async_hour_assignments`
--

CREATE TABLE `async_hour_assignments` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `type_id` int UNSIGNED NOT NULL,
  `billing_month` date NOT NULL COMMENT '1. des Monats',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `student_count` tinyint UNSIGNED DEFAULT NULL,
  `deactivated_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_by` int UNSIGNED NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monatliche Aktivierung asynchroner Stunden';

-- --------------------------------------------------------

--
-- Table structure for table `async_hour_types`
--

CREATE TABLE `async_hour_types` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `hours` decimal(4,2) NOT NULL COMMENT 'Anzahl asynchroner Einheiten pro Monat',
  `min_students` tinyint UNSIGNED DEFAULT '4' COMMENT 'Mindeststüleranzahl',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Regeltypen für asynchrone Stunden';

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL = System',
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint UNSIGNED NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Revisionssicherer Audit-Log – NIEMALS LÖSCHEN';

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'user.login', 'users', 1, NULL, NULL, '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-28 22:55:56'),
(2, 1, 'user.created', 'users', 3, NULL, '{\"email\": \"lehrer@wvh-online.com\", \"role_id\": 3}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-28 23:06:46'),
(3, 3, 'user.login', 'users', 3, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-28 23:07:37'),
(4, 1, 'user.login', 'users', 1, NULL, NULL, '104.23.248.44', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 03:24:59'),
(5, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.254.224', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 03:30:31'),
(6, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.82.98', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 14:08:50'),
(7, 1, 'user.login', 'users', 1, NULL, NULL, '172.68.12.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 16:53:02'),
(8, 1, 'teacher.csv_created', 'users', 4, NULL, '{\"email\": \"abeck@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:37'),
(9, 1, 'teacher.csv_created', 'users', 5, NULL, '{\"email\": \"akuepper@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:38'),
(10, 1, 'teacher.csv_created', 'users', 6, NULL, '{\"email\": \"asturm@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:38'),
(11, 1, 'teacher.csv_created', 'users', 7, NULL, '{\"email\": \"anolte@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:39'),
(12, 1, 'teacher.csv_created', 'users', 8, NULL, '{\"email\": \"bbredow@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:39'),
(13, 1, 'teacher.csv_created', 'users', 9, NULL, '{\"email\": \"cjansen@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:39'),
(14, 1, 'teacher.csv_created', 'users', 10, NULL, '{\"email\": \"cstroh@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:40'),
(15, 1, 'teacher.csv_created', 'users', 11, NULL, '{\"email\": \"cgallardo@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:40'),
(16, 1, 'teacher.csv_created', 'users', 12, NULL, '{\"email\": \"cbrahmi@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:40'),
(17, 1, 'teacher.csv_created', 'users', 13, NULL, '{\"email\": \"dbuerling@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:41'),
(18, 1, 'teacher.csv_created', 'users', 14, NULL, '{\"email\": \"dkonkol@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:41'),
(19, 1, 'teacher.csv_created', 'users', 15, NULL, '{\"email\": \"dstriffler@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:42'),
(20, 1, 'teacher.csv_created', 'users', 16, NULL, '{\"email\": \"egunjic@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:42'),
(21, 1, 'teacher.csv_created', 'users', 17, NULL, '{\"email\": \"ebilchinski@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:42'),
(22, 1, 'teacher.csv_created', 'users', 18, NULL, '{\"email\": \"erecht@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:43'),
(23, 1, 'teacher.csv_created', 'users', 19, NULL, '{\"email\": \"fkegel@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:43'),
(24, 1, 'teacher.csv_created', 'users', 20, NULL, '{\"email\": \"fbeck@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:43'),
(25, 1, 'teacher.csv_created', 'users', 21, NULL, '{\"email\": \"gmaerz@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:44'),
(26, 1, 'teacher.csv_created', 'users', 22, NULL, '{\"email\": \"gkuessner@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:44'),
(27, 1, 'teacher.csv_created', 'users', 23, NULL, '{\"email\": \"hriffer@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:45'),
(28, 1, 'teacher.csv_created', 'users', 24, NULL, '{\"email\": \"hutz@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:45'),
(29, 1, 'teacher.csv_created', 'users', 25, NULL, '{\"email\": \"iguillaume@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:45'),
(30, 1, 'teacher.csv_created', 'users', 26, NULL, '{\"email\": \"jhochreiter@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:46'),
(31, 1, 'teacher.csv_created', 'users', 27, NULL, '{\"email\": \"jhannemann@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:46'),
(32, 1, 'teacher.csv_created', 'users', 28, NULL, '{\"email\": \"jhettstedt@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:46'),
(33, 1, 'teacher.csv_created', 'users', 29, NULL, '{\"email\": \"jhildebrandt@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:47'),
(34, 1, 'teacher.csv_created', 'users', 30, NULL, '{\"email\": \"jkerschhofer@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:47'),
(35, 1, 'teacher.csv_created', 'users', 31, NULL, '{\"email\": \"jmischke@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:48'),
(36, 1, 'teacher.csv_created', 'users', 32, NULL, '{\"email\": \"kschroeder@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:48'),
(37, 1, 'teacher.csv_created', 'users', 33, NULL, '{\"email\": \"kenns@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:48'),
(38, 1, 'teacher.csv_created', 'users', 34, NULL, '{\"email\": \"kgleine@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:49'),
(39, 1, 'teacher.csv_created', 'users', 35, NULL, '{\"email\": \"kmeier-sigwart@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:49'),
(40, 1, 'teacher.csv_created', 'users', 36, NULL, '{\"email\": \"kreiser@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:49'),
(41, 1, 'teacher.csv_created', 'users', 37, NULL, '{\"email\": \"kschmidt@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:50'),
(42, 1, 'teacher.csv_created', 'users', 38, NULL, '{\"email\": \"kaldibssi@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:50'),
(43, 1, 'teacher.csv_created', 'users', 39, NULL, '{\"email\": \"lzuniga@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:51'),
(44, 1, 'teacher.csv_created', 'users', 40, NULL, '{\"email\": \"lschallenberg@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:51'),
(45, 1, 'teacher.csv_created', 'users', 41, NULL, '{\"email\": \"tmaamar@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:51'),
(46, 1, 'teacher.csv_created', 'users', 42, NULL, '{\"email\": \"mewald@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:52'),
(47, 1, 'teacher.csv_created', 'users', 43, NULL, '{\"email\": \"mschreiber@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:52'),
(48, 1, 'teacher.csv_created', 'users', 44, NULL, '{\"email\": \"mschneider@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:52'),
(49, 1, 'teacher.csv_created', 'users', 45, NULL, '{\"email\": \"mherold@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:53'),
(50, 1, 'teacher.csv_created', 'users', 46, NULL, '{\"email\": \"nuntu@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:53'),
(51, 1, 'teacher.csv_created', 'users', 47, NULL, '{\"email\": \"nschmidt@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:54'),
(52, 1, 'teacher.csv_created', 'users', 48, NULL, '{\"email\": \"nblume@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:54'),
(53, 1, 'teacher.csv_created', 'users', 49, NULL, '{\"email\": \"pjurtschitsch@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:54'),
(54, 1, 'teacher.csv_created', 'users', 50, NULL, '{\"email\": \"pfellner@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:55'),
(55, 1, 'teacher.csv_created', 'users', 51, NULL, '{\"email\": \"rmaul@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:55'),
(56, 1, 'teacher.csv_created', 'users', 52, NULL, '{\"email\": \"rbt@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:55'),
(57, 1, 'teacher.csv_created', 'users', 53, NULL, '{\"email\": \"rtoufani@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:56'),
(58, 1, 'teacher.csv_created', 'users', 54, NULL, '{\"email\": \"rrousseau@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:56'),
(59, 1, 'teacher.csv_created', 'users', 55, NULL, '{\"email\": \"sziegler@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:57'),
(60, 1, 'teacher.csv_created', 'users', 56, NULL, '{\"email\": \"sberner@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:57'),
(61, 1, 'teacher.csv_created', 'users', 57, NULL, '{\"email\": \"sboehmer@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:57'),
(62, 1, 'teacher.csv_created', 'users', 58, NULL, '{\"email\": \"skolburan@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:58'),
(63, 1, 'teacher.csv_created', 'users', 59, NULL, '{\"email\": \"sbehrens@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:58'),
(64, 1, 'teacher.csv_created', 'users', 60, NULL, '{\"email\": \"sehrenfeuchter@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:58'),
(65, 1, 'teacher.csv_created', 'users', 61, NULL, '{\"email\": \"skuessner@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:59'),
(66, 1, 'teacher.csv_created', 'users', 62, NULL, '{\"email\": \"smenzel@wvh-online.com\", \"emp_type\": \"festangestellt\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:12:59'),
(67, 1, 'teacher.csv_created', 'users', 63, NULL, '{\"email\": \"tkriegel@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:00'),
(68, 1, 'teacher.csv_created', 'users', 64, NULL, '{\"email\": \"ivoeltz@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:00'),
(69, 1, 'teacher.csv_created', 'users', 65, NULL, '{\"email\": \"uelstner@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:00'),
(70, 1, 'teacher.csv_created', 'users', 66, NULL, '{\"email\": \"iromeroespejo@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:01'),
(71, 1, 'teacher.csv_created', 'users', 67, NULL, '{\"email\": \"fmarin@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:01'),
(72, 1, 'teacher.csv_created', 'users', 68, NULL, '{\"email\": \"srickert@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:01'),
(73, 1, 'teacher.csv_created', 'users', 69, NULL, '{\"email\": \"smarcialgomes@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:02'),
(74, 1, 'teacher.csv_created', 'users', 70, NULL, '{\"email\": \"youtadrarte@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:02'),
(75, 1, 'teacher.csv_created', 'users', 71, NULL, '{\"email\": \"lkrueger@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:03'),
(76, 1, 'teacher.csv_created', 'users', 72, NULL, '{\"email\": \"spfenninger@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:03'),
(77, 1, 'teacher.csv_created', 'users', 73, NULL, '{\"email\": \"vschumann@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:03'),
(78, 1, 'teacher.csv_created', 'users', 74, NULL, '{\"email\": \"krumpel@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:04'),
(79, 1, 'teacher.csv_created', 'users', 75, NULL, '{\"email\": \"jwengler@wvh-online.com\", \"emp_type\": \"honorar\"}', '172.68.12.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:13:04'),
(80, 1, 'timetable.imported', 'timetable_plans', 1, NULL, '{\"name\": \"2. Halbjahr 2025/26\", \"skipped\": 0, \"imported\": 605}', '172.68.7.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:17:07'),
(81, 1, 'user.profile_updated', 'users', 1, NULL, NULL, '172.68.12.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 18:43:25'),
(82, 1, 'user.profile_updated', 'users', 1, NULL, NULL, '172.68.12.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 18:43:46'),
(83, 1, 'user.login', 'users', 1, NULL, NULL, '104.23.248.137', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 18:52:43'),
(84, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.83.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 19:22:57'),
(85, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.82.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 19:24:01'),
(86, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.82.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 19:24:13'),
(87, 1, 'user.logout', 'users', 1, NULL, NULL, '104.23.248.136', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 19:33:38'),
(88, 1, 'user.login', 'users', 1, NULL, NULL, '104.23.248.136', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 19:34:56'),
(89, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.254.225', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 21:14:03'),
(90, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.82.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 21:49:59'),
(91, 1, 'user.logout', 'users', 1, NULL, NULL, '172.70.82.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 21:50:57'),
(92, 1, 'user.login', 'users', 1, NULL, NULL, '104.23.248.137', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:09:40'),
(93, 1, 'substitution.admin_direct', 'substitutions', 1, NULL, NULL, '172.70.55.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 22:11:34'),
(94, 1, 'substitution.admin_direct', 'substitutions', 2, NULL, NULL, '172.70.55.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 22:12:55'),
(95, 1, 'substitution.released', 'lesson_instances', 3, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:16:28'),
(96, 1, 'substitution.admin_assigned', 'substitutions', 3, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:18:45'),
(97, 1, 'substitution.released', 'lesson_instances', 4, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:23:42'),
(98, 1, 'user.logout', 'users', 1, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:25:39'),
(99, 59, 'user.login', 'users', 59, NULL, NULL, '172.68.7.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:28:00'),
(100, 59, 'substitution.released', 'lesson_instances', 5, NULL, NULL, '172.70.55.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-01 22:40:18'),
(101, 59, 'user.login', 'users', 59, NULL, NULL, '172.70.55.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-02 00:32:48'),
(102, 1, 'user.login', 'users', 1, NULL, NULL, '172.70.254.224', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-02 00:58:03');

-- --------------------------------------------------------

--
-- Table structure for table `billing_line_items`
--

CREATE TABLE `billing_line_items` (
  `id` bigint UNSIGNED NOT NULL,
  `record_id` int UNSIGNED NOT NULL,
  `item_type` enum('plan_lesson','substitution','released','deputate','async') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_date` date DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(5,2) NOT NULL DEFAULT '1.00',
  `unit_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(10,2) NOT NULL,
  `ref_lesson_id` bigint UNSIGNED DEFAULT NULL,
  `ref_sub_id` bigint UNSIGNED DEFAULT NULL,
  `ref_deputate_id` int UNSIGNED DEFAULT NULL,
  `ref_async_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailpositionen der Monatsabrechnung';

-- --------------------------------------------------------

--
-- Table structure for table `billing_months`
--

CREATE TABLE `billing_months` (
  `id` int UNSIGNED NOT NULL,
  `month` date NOT NULL COMMENT '1. des Abrechnungsmonats',
  `status` enum('draft','closed','review','confirmed','final','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `closed_by` int UNSIGNED DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `finalized_by` int UNSIGNED DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monatsstatus-Verwaltung';

-- --------------------------------------------------------

--
-- Table structure for table `billing_records`
--

CREATE TABLE `billing_records` (
  `id` int UNSIGNED NOT NULL,
  `billing_month_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `version` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `plan_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `released_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `substituted_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `effective_plan_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `deputate_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `async_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hourly_rate_snapshot` decimal(8,2) NOT NULL DEFAULT '0.00',
  `lesson_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gross_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','pending_teacher','confirmed','final','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `teacher_confirmed_at` datetime DEFAULT NULL,
  `teacher_confirmed_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_correction` tinyint(1) NOT NULL DEFAULT '0',
  `corrects_record_id` int UNSIGNED DEFAULT NULL,
  `correction_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monatliche Abrechnung pro Lehrer';

-- --------------------------------------------------------

--
-- Table structure for table `bonus_definitions`
--

CREATE TABLE `bonus_definitions` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fixed_amount` decimal(10,2) NOT NULL,
  `period_from` date NOT NULL,
  `period_until` date NOT NULL,
  `status` enum('defined','calculated','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'defined',
  `calculated_hours` decimal(6,2) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bonuszahlungen (mehrmals jährlich)';

-- --------------------------------------------------------

--
-- Table structure for table `calendar_event_classes`
--

CREATE TABLE `calendar_event_classes` (
  `event_id` int UNSIGNED NOT NULL,
  `class_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Klassenweise Zuordnung von Sonderevents';

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. 05a, 07b, 11',
  `grade_level` tinyint UNSIGNED DEFAULT NULL COMMENT 'Jahrgangsstufe 1–13',
  `is_upper` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Oberstufe (Jg. 10+)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Klassen und Lerngruppen';

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `name`, `grade_level`, `is_upper`, `is_active`) VALUES
(1, 'Französisch A0-A1 Mi', NULL, 0, 1),
(2, 'Französisch A2-B1 Na', NULL, 0, 1),
(3, 'Französisch A0-A1 Yo', NULL, 0, 1),
(4, '01a', 1, 0, 1),
(5, '02a', 2, 0, 1),
(6, '03a', 3, 0, 1),
(7, '04a', 4, 0, 1),
(8, '04b', 4, 0, 1),
(9, '05a', 5, 0, 1),
(10, '05b', 5, 0, 1),
(11, '06a', 6, 0, 1),
(12, '06b', 6, 0, 1),
(13, '07a', 7, 0, 1),
(14, '07b', 7, 0, 1),
(15, '08a', 8, 0, 1),
(16, '08b', 8, 0, 1),
(17, '08c', 8, 0, 1),
(18, '09a', 9, 0, 1),
(19, '09b', 9, 0, 1),
(20, '09c', 9, 0, 1),
(21, '10a', 10, 1, 1),
(22, '10b', 10, 1, 1),
(23, '10c', 10, 1, 1),
(24, 'Französisch A1-A2 Ma', NULL, 0, 1),
(25, 'Latein Prima - Nele ', NULL, 0, 1),
(26, 'Latein Terica - Dani', NULL, 0, 1),
(27, '03b', 3, 0, 1),
(28, 'Latein Secunda - Mar', NULL, 0, 1),
(29, 'Q11 Alexander Schiet', NULL, 0, 1),
(30, 'Q11 Amelie Gretscher', NULL, 0, 1),
(31, 'Q11 Annalena Rausche', NULL, 0, 1),
(32, 'Q11 Aurice Suppan', NULL, 0, 1),
(33, 'Q11 Emelie Richter', NULL, 0, 1),
(34, 'Q11 Frida Kübler', NULL, 0, 1),
(35, 'Q11 Glenn Klein', NULL, 0, 1),
(36, 'Q11 Ilyas Madad Fass', NULL, 0, 1),
(37, 'Q11 Immanuel Wagner', NULL, 0, 1),
(38, 'Q11 Jeremias Hostett', NULL, 0, 1),
(39, 'Q11 Kim Künstler', NULL, 0, 1),
(40, 'Q11 Kyra Narewsky', NULL, 0, 1),
(41, 'Q11 Landon Clark', NULL, 0, 1),
(42, 'Q11 Lenja Jürgens', NULL, 0, 1),
(43, 'Q11 Madeleine Nebele', NULL, 0, 1),
(44, 'Q11 Marie Schneider', NULL, 0, 1),
(45, 'Q11 Marius Michutta', NULL, 0, 1),
(46, 'Q11 Nico Schmitke', NULL, 0, 1),
(47, 'Q11 Raphael Gaffga', NULL, 0, 1),
(48, 'Q11 Sami Mosaui', NULL, 0, 1),
(49, 'Q11 Sophie Gerbaudo', NULL, 0, 1),
(50, 'Q11 Zakariya Rahimi', NULL, 0, 1),
(51, 'Q11 Samuel Kliemt', NULL, 0, 1),
(52, 'Q11 Sebastian Sander', NULL, 0, 1),
(53, 'Q12 Agata Henke', NULL, 0, 1),
(54, 'Q12 Aljoscha Meyer d', NULL, 0, 1),
(55, 'Q12 Atrina Jasseb', NULL, 0, 1),
(56, 'Q12 Carlotta Hosius', NULL, 0, 1),
(57, 'Q12 Charlotte Erber', NULL, 0, 1),
(58, 'Q12 Constantin Sporl', NULL, 0, 1),
(59, 'Q12 Emanuel Striehl', NULL, 0, 1),
(60, 'Q12 Felix Giese', NULL, 0, 1),
(61, 'Q12 Iris Eisenhauser', NULL, 0, 1),
(62, 'Q12 Jaron Weidlich', NULL, 0, 1),
(63, 'Q12 Len Marcy', NULL, 0, 1),
(64, 'Q12 Liv Andreassen', NULL, 0, 1),
(65, 'Q12 Nea von Holdt', NULL, 0, 1),
(66, 'Q12 Noah Schwermache', NULL, 0, 1),
(67, 'Q12 Rose Dohm', NULL, 0, 1),
(68, 'Q12 Samira El Mozaye', NULL, 0, 1),
(69, 'Q12 Sarah Krohnfeld', NULL, 0, 1),
(70, 'Q12 Sofia  Goegelein', NULL, 0, 1),
(71, 'Q12 Tabea Palenski', NULL, 0, 1),
(72, 'Q12 Tarek Moushssin', NULL, 0, 1),
(73, 'Q12 Thorin Hofman-Al', NULL, 0, 1),
(74, 'Q12 Valentina Mandes', NULL, 0, 1),
(75, 'Französisch A1-A2 Th', NULL, 0, 1),
(76, 'Französisch A2-B1 Va', NULL, 0, 1),
(77, 'Französisch B1 Isabe', NULL, 0, 1),
(78, 'Französisch B1-B2 Ro', NULL, 0, 1),
(79, 'Französisch B2', NULL, 0, 1),
(80, 'Claudia Brahmi', NULL, 0, 1),
(81, 'P13 Nick Sauter', NULL, 0, 1),
(82, 'P13 Sophia Lkandouch', NULL, 0, 1),
(83, 'P13 Zoe Dibbern', NULL, 0, 1),
(84, 'Latein Quarta - Mari', NULL, 0, 1),
(85, 'P13 Anna Heine', NULL, 0, 1),
(86, 'P13 Celine Dornick', NULL, 0, 1),
(87, 'P13 Lara Krohn', NULL, 0, 1),
(88, 'P13 Laura Hockmann', NULL, 0, 1),
(89, 'P13 Ryan Winter', NULL, 0, 1),
(90, 'P13 Viktoria Stroh', NULL, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `deputate_assignments`
--

CREATE TABLE `deputate_assignments` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `type_id` int UNSIGNED NOT NULL,
  `billing_month` date NOT NULL COMMENT '1. des Monats',
  `rate_override` decimal(8,2) DEFAULT NULL COMMENT 'Abweichender Satz',
  `amount_override` decimal(10,2) DEFAULT NULL COMMENT 'Fixer Betrag',
  `units` decimal(5,2) NOT NULL DEFAULT '1.00' COMMENT 'Anzahl 45-Min-Einheiten',
  `is_one_time` tinyint(1) NOT NULL DEFAULT '0',
  `assigned_by` int UNSIGNED NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_retroactive` tinyint(1) NOT NULL DEFAULT '0',
  `correction_id` int UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zugewiesene Sonderdeputate pro Lehrer & Monat';

-- --------------------------------------------------------

--
-- Table structure for table `deputate_types`
--

CREATE TABLE `deputate_types` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. Klassenleitung, Chor, Band',
  `description` text COLLATE utf8mb4_unicode_ci,
  `default_rate` decimal(8,2) DEFAULT '0.00' COMMENT 'Standard-Add-on Stundensatz',
  `is_recurring` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = monatlich, 0 = einmalig',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Typen von Sonderdeputaten (erweiterbar)';

-- --------------------------------------------------------

--
-- Table structure for table `lesson_instances`
--

CREATE TABLE `lesson_instances` (
  `id` bigint UNSIGNED NOT NULL,
  `entry_id` int UNSIGNED NOT NULL COMMENT 'Stundenplaneintrag',
  `lesson_date` date NOT NULL COMMENT 'Konkretes Datum',
  `status` enum('planned','released','substituted','partial_open','cancelled','event_day') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `is_billable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 bei Sondertag / Feiertag',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Konkrete Unterrichtseinheiten (Plan × Datum)';

--
-- Dumping data for table `lesson_instances`
--

INSERT INTO `lesson_instances` (`id`, `entry_id`, `lesson_date`, `status`, `is_billable`, `created_at`, `updated_at`) VALUES
(1, 382, '2026-02-23', 'substituted', 1, '2026-03-01 22:11:34', '2026-03-01 22:11:34'),
(2, 17, '2026-02-24', 'substituted', 1, '2026-03-01 22:12:55', '2026-03-01 22:12:55'),
(3, 383, '2026-02-23', 'substituted', 1, '2026-03-01 22:16:28', '2026-03-01 22:18:45'),
(4, 19, '2026-02-24', 'released', 1, '2026-03-01 22:23:42', '2026-03-01 22:23:42'),
(5, 380, '2026-02-25', 'released', 1, '2026-03-01 22:40:18', '2026-03-01 22:40:18');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login-Fehlversuche (Brute-Force-Schutz)';

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`) VALUES
(3, 'admin', '104.23.248.137', '2026-03-01 18:52:39'),
(4, 'demo_teacher', '104.23.248.136', '2026-03-01 19:33:47'),
(5, 'admin', '104.23.248.136', '2026-03-01 19:34:49'),
(6, 'admin', '172.70.82.97', '2026-03-01 21:49:56'),
(7, 'demo_teacher', '172.68.12.164', '2026-03-01 21:55:39'),
(8, 'demo_teacher', '104.23.248.137', '2026-03-01 22:09:33');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint UNSIGNED NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin | verwaltung | lehrer',
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Anzeigename',
  `description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Systemrollen';

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`, `description`) VALUES
(1, 'admin', 'Administrator', 'Vollzugriff auf alle Systembereiche'),
(2, 'verwaltung', 'Verwaltung', 'Monatsabrechnung, Sonderdeputate, Wise-Export'),
(3, 'lehrer', 'Lehrer/in', 'Stundenplan, Vertretungen, eigene Abrechnung');

-- --------------------------------------------------------

--
-- Table structure for table `school_calendar`
--

CREATE TABLE `school_calendar` (
  `id` int UNSIGNED NOT NULL,
  `type` enum('ferien','feiertag_bw','schliestag','sonderevent','projektwoche') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. Sommerferien, Christi Himmelfahrt',
  `date_from` date NOT NULL,
  `date_until` date NOT NULL COMMENT 'inklusive (auch Einzeltag: from=until)',
  `affects_all` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = nur bestimmte Klassen',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Schulkalender (Ferien, Feiertage, Events)';

-- --------------------------------------------------------

--
-- Table structure for table `school_holidays`
--

CREATE TABLE `school_holidays` (
  `id` int UNSIGNED NOT NULL,
  `school_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2025-2026',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('sommerferien','herbstferien','weihnachtsferien','osterferien','pfingstferien','faschingsferien','feiertag','beweglicher_ferientag','sonstiges') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sonstiges',
  `date_from` date NOT NULL,
  `date_until` date NOT NULL,
  `is_school_free` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_holidays`
--

INSERT INTO `school_holidays` (`id`, `school_year`, `name`, `type`, `date_from`, `date_until`, `is_school_free`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2025-2026', 'Sommerferien 2025', 'sommerferien', '2025-09-01', '2025-09-13', 1, 'BW Sommerferien 2025 · Mo 01.09.–Sa 13.09.2025', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(2, '2025-2026', 'Tag der Deutschen Einheit', 'feiertag', '2025-10-03', '2025-10-03', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(3, '2025-2026', 'Brückentag nach Tag d. Einheit', 'beweglicher_ferientag', '2025-10-04', '2025-10-04', 1, 'Beweglicher Ferientag (b)', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(4, '2025-2026', 'Herbstferien 2025', 'herbstferien', '2025-10-27', '2025-10-31', 1, 'BW Herbstferien 2025', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(5, '2025-2026', 'Allerheiligen', 'feiertag', '2025-11-01', '2025-11-01', 1, 'Gesetzlicher Feiertag BW', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(6, '2025-2026', 'Weihnachtsferien 2025/26', 'weihnachtsferien', '2025-12-22', '2026-01-06', 1, 'BW Weihnachtsferien · inkl. Feiertage und Neujahr', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(7, '2025-2026', '1. Weihnachtsfeiertag', 'feiertag', '2025-12-25', '2025-12-25', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(8, '2025-2026', '2. Weihnachtsfeiertag', 'feiertag', '2025-12-26', '2025-12-26', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(9, '2025-2026', 'Silvester', 'feiertag', '2025-12-31', '2025-12-31', 1, 'Schulfreier Tag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(10, '2025-2026', 'Neujahr 2026', 'feiertag', '2026-01-01', '2026-01-01', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(11, '2025-2026', 'Heilige Drei Könige', 'feiertag', '2026-01-06', '2026-01-06', 1, 'Gesetzlicher Feiertag BW', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(12, '2025-2026', 'Faschingsferien 2026', 'faschingsferien', '2026-02-16', '2026-02-21', 1, 'BW Faschingsferien (b) · Mo 16.02.–Sa 21.02.2026', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(13, '2025-2026', 'Osterferien 2026', 'osterferien', '2026-03-30', '2026-04-11', 1, 'BW Osterferien · Mo 30.03.–Sa 11.04.2026', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(14, '2025-2026', 'Karfreitag', 'feiertag', '2026-04-03', '2026-04-03', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(15, '2025-2026', 'Ostermontag', 'feiertag', '2026-04-06', '2026-04-06', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(16, '2025-2026', 'Tag der Arbeit', 'feiertag', '2026-05-01', '2026-05-01', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(17, '2025-2026', 'Brückentage Maifeiertag', 'beweglicher_ferientag', '2026-05-02', '2026-05-03', 1, 'Bewegliche Ferientage (b)', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(18, '2025-2026', 'Christi Himmelfahrt', 'feiertag', '2026-05-14', '2026-05-14', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(19, '2025-2026', 'Brückentage Himmelfahrt', 'beweglicher_ferientag', '2026-05-15', '2026-05-16', 1, 'Bewegliche Ferientage (b)', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(20, '2025-2026', 'Pfingstmontag', 'feiertag', '2026-05-25', '2026-05-25', 1, 'Gesetzlicher Feiertag', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(21, '2025-2026', 'Pfingstferien 2026', 'pfingstferien', '2026-05-26', '2026-06-06', 1, 'BW Pfingstferien · Di 26.05.–Sa 06.06.2026', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(22, '2025-2026', 'Fronleichnam', 'feiertag', '2026-06-04', '2026-06-04', 1, 'Gesetzlicher Feiertag BW', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16'),
(23, '2025-2026', 'Sommerferien 2026', 'sommerferien', '2026-07-30', '2026-09-12', 1, 'BW Sommerferien 2026 · Do 30.07.–Sa 12.09.2026', NULL, '2026-03-01 11:21:16', '2026-03-01 11:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. Mathematik, Informatik',
  `short_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kürzel aus CSV',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fächer';

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `short_code`, `is_active`) VALUES
(1, 'Französisch A0-A1', NULL, 1),
(2, 'Französisch A2-B1', NULL, 1),
(3, 'Deutsch', NULL, 1),
(4, 'Sachunterricht', NULL, 1),
(5, 'Kunst/Musik', NULL, 1),
(6, 'Sport/Life Skills', NULL, 1),
(7, 'Klassenspaß/runder Tisch', NULL, 1),
(8, 'Mathematik', NULL, 1),
(9, 'Englisch', NULL, 1),
(10, 'Geografie', NULL, 1),
(11, 'Naturphänomene', NULL, 1),
(12, 'Kunst Projektfach - Projektansatz: Wechsel zwischen Gesamt- und Kleingruppenunterricht.', NULL, 1),
(13, 'Biologie', NULL, 1),
(14, 'Musik Projektfach - Projektansatz: Wechsel zwischen Gesamt- und Kleingruppenunterricht.', NULL, 1),
(15, 'Individualfachblock 1 - Kleingruppenunterricht der Nebenfächer nach Einteilung.', NULL, 1),
(16, 'Individualfachblock 2 - Kleingruppenunterricht der Nebenfächer nach Einteilung.', NULL, 1),
(17, 'Spanisch Profil', NULL, 1),
(18, 'Individualfachblock 3 - Kleingruppenunterricht der Nebenfächer nach Einteilung.', NULL, 1),
(19, 'Informatik Profil', NULL, 1),
(20, 'Exam Skills', NULL, 1),
(21, 'Life Skills', NULL, 1),
(22, 'Französisch A1-A2', NULL, 1),
(23, 'Latein Prima', NULL, 1),
(24, 'Latein Tercia', NULL, 1),
(25, 'Mathematik Förderunterricht', NULL, 1),
(26, 'Geschichte', NULL, 1),
(27, 'Zaubern und Akrobatik', NULL, 1),
(28, 'Latein Secunda', NULL, 1),
(29, 'Q11 Biologie GK', NULL, 1),
(30, 'Q11 Deutsch GK', NULL, 1),
(31, 'Q11 Deutsch VK', NULL, 1),
(32, 'Q11 Englisch GK', NULL, 1),
(33, 'Q11 Englisch VK', NULL, 1),
(34, 'Q11 Mathematik GK', NULL, 1),
(35, 'Q11 Mathematik VK', NULL, 1),
(36, 'Q11 Spanisch GK', NULL, 1),
(37, 'Q11 Spanisch Schnellkurs', NULL, 1),
(38, 'Q11 Biologie VK', NULL, 1),
(39, 'Q11 Chemie GK', NULL, 1),
(40, 'Q11 Geschichte GK', NULL, 1),
(41, 'Q11 Geschichte VK', NULL, 1),
(42, 'Q11 Erdkunde/Geographie GK', NULL, 1),
(43, 'Q11 Informatik GK', NULL, 1),
(44, 'Q11 Kunst GK', NULL, 1),
(45, 'Q12 Deutsch GK', NULL, 1),
(46, 'Q12 Deutsch VK', NULL, 1),
(47, 'Q12 Englisch GK', NULL, 1),
(48, 'Q12 Englisch VK', NULL, 1),
(49, 'Q12 Mathematik GK', NULL, 1),
(50, 'Q12 Mathematik VK', NULL, 1),
(51, 'Q12 Spanisch Schnellkurs', NULL, 1),
(52, 'Q12 Biologie GK', NULL, 1),
(53, 'Q12 Biologie VK', NULL, 1),
(54, 'Q12 Chemie GK', NULL, 1),
(55, 'Q12 Geschichte GK', NULL, 1),
(56, 'Q12 Erdkunde/Geographie GK', NULL, 1),
(57, 'Q12 Physik GK', NULL, 1),
(58, 'Q12 Informatik GK', NULL, 1),
(59, 'Q12 Kunst GK', NULL, 1),
(60, 'Französisch B1', NULL, 1),
(61, 'Französisch B1-B2', NULL, 1),
(62, 'Französisch B2+', NULL, 1),
(63, 'Latein Quarta', NULL, 1),
(64, 'Q11 Physik GK', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `substitutions`
--

CREATE TABLE `substitutions` (
  `id` bigint UNSIGNED NOT NULL,
  `lesson_id` bigint UNSIGNED NOT NULL,
  `original_teacher_id` int UNSIGNED NOT NULL COMMENT 'Freigebender Lehrer',
  `substitute_teacher_id` int UNSIGNED DEFAULT NULL COMMENT 'Vertretender Lehrer (NULL = noch offen)',
  `self_assigned_by` int UNSIGNED DEFAULT NULL COMMENT 'Teacher-ID wenn Kollege sich selbst eingetragen hat',
  `self_assigned_at` datetime DEFAULT NULL,
  `covers_part` enum('full','first','second') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `status` enum('open','pending_confirm','claimed','confirmed','conflict','admin_resolved','cancelled','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `released_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_by` int UNSIGNED DEFAULT NULL COMMENT 'Teacher-ID der die Stunde freigegeben hat',
  `claimed_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by` int UNSIGNED DEFAULT NULL COMMENT 'Wer hat bestätigt (Originallehrer oder Verwaltung)',
  `resolved_at` datetime DEFAULT NULL COMMENT 'Zeitpunkt Verwaltungsentscheid',
  `resolved_by` int UNSIGNED DEFAULT NULL COMMENT 'Verwaltungs-User ID',
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `billing_month` date DEFAULT NULL COMMENT 'Abrechnungsmonat (1. des Monats) – bei Korrekturen abweichend',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vertretungseinträge';

--
-- Dumping data for table `substitutions`
--

INSERT INTO `substitutions` (`id`, `lesson_id`, `original_teacher_id`, `substitute_teacher_id`, `self_assigned_by`, `self_assigned_at`, `covers_part`, `status`, `released_at`, `released_by`, `claimed_at`, `confirmed_at`, `confirmed_by`, `resolved_at`, `resolved_by`, `resolution_notes`, `notes`, `billing_month`, `created_at`, `updated_at`) VALUES
(1, 1, 57, 2, NULL, NULL, 'full', 'confirmed', '2026-03-01 22:11:34', NULL, NULL, '2026-03-01 22:11:34', 1, NULL, NULL, NULL, NULL, '2026-02-01', '2026-03-01 22:11:34', '2026-03-01 22:11:34'),
(2, 2, 27, 6, NULL, NULL, 'full', 'confirmed', '2026-03-01 22:12:55', NULL, NULL, '2026-03-01 22:12:55', 1, NULL, NULL, NULL, NULL, '2026-02-01', '2026-03-01 22:12:55', '2026-03-01 22:12:55'),
(3, 3, 57, 57, NULL, NULL, 'full', 'confirmed', '2026-03-01 22:16:28', NULL, NULL, '2026-03-01 22:18:45', 1, '2026-03-01 22:18:45', 1, NULL, NULL, '2026-02-01', '2026-03-01 22:16:28', '2026-03-01 22:18:45'),
(4, 4, 32, NULL, NULL, NULL, 'full', 'open', '2026-03-01 22:23:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-01', '2026-03-01 22:23:42', '2026-03-01 22:23:42'),
(5, 5, 57, NULL, NULL, NULL, 'full', 'open', '2026-03-01 22:40:18', 57, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-01', '2026-03-01 22:40:18', '2026-03-01 22:40:18');

-- --------------------------------------------------------

--
-- Table structure for table `substitution_notifications`
--

CREATE TABLE `substitution_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `substitution_id` bigint UNSIGNED NOT NULL,
  `recipient_id` int UNSIGNED NOT NULL,
  `type` enum('pending_confirm','confirmed','rejected','assigned','released') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_confirm',
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Benachrichtigungen für Vertretungsanfragen';

--
-- Dumping data for table `substitution_notifications`
--

INSERT INTO `substitution_notifications` (`id`, `substitution_id`, `recipient_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 4, 'assigned', 'Du wurdest als Vertreter am 23.02.2026 eingetragen.', 0, '2026-03-01 22:11:34'),
(2, 2, 8, 'assigned', 'Du wurdest als Vertreter am 24.02.2026 eingetragen.', 0, '2026-03-01 22:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `employment_type` enum('honorar','festangestellt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'honorar',
  `street` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'Deutschland',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Steuer-ID / USt-ID',
  `iban` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bic` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_holder` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_data_approved` tinyint(1) NOT NULL DEFAULT '0',
  `bank_data_approved_by` int UNSIGNED DEFAULT NULL,
  `bank_data_approved_at` datetime DEFAULT NULL,
  `wise_recipient_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wise Recipient UUID fuer Batch-Export',
  `wise_recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name wie in Wise hinterlegt (kann Ehename, Kuenstlername, GmbH abweichen!)',
  `wise_recipient_detail` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bank-Beschreibung aus Wise z.B. Sparkasse ending 8770',
  `wise_receiver_type` enum('PERSON','INSTITUTION') COLLATE utf8mb4_unicode_ci DEFAULT 'PERSON' COMMENT 'Wise receiverType: PERSON oder INSTITUTION',
  `hourly_rate` decimal(8,2) DEFAULT '0.00' COMMENT 'EUR pro 45-Min-Einheit',
  `active_from` date NOT NULL,
  `active_until` date DEFAULT NULL COMMENT 'NULL = unbegrenzt aktiv',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lehrerprofil & Stammdaten';

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `employment_type`, `street`, `zip`, `city`, `country`, `phone`, `tax_id`, `iban`, `bic`, `bank_name`, `account_holder`, `bank_data_approved`, `bank_data_approved_by`, `bank_data_approved_at`, `wise_recipient_id`, `wise_recipient_name`, `wise_recipient_detail`, `wise_receiver_type`, `hourly_rate`, `active_from`, `active_until`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-02-28', NULL, NULL, '2026-02-28 23:06:46', '2026-02-28 23:06:46'),
(2, 4, 'honorar', NULL, NULL, NULL, 'Deutschland', '015232713505', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '53c015e4-b1a7-47d9-281d-59aff8755e60', 'Andreas Beck', 'Trade Republic Bank, Gmbh ending ·· 1901', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:37', '2026-03-01 17:12:37'),
(3, 5, 'honorar', NULL, NULL, NULL, 'Deutschland', '+4915752631000', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e97847d-df75-475d-fc2f-efe70e7c6dc3', 'Andreas Kuepper', 'Sparkasse ending ·· 8770', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:38', '2026-03-01 17:12:38'),
(4, 6, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '49255c6f-4eb6-4b89-29b7-9cf0d9cf5401', 'Anja Sturm', 'Bbbank Eg ending ·· 9259', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:38', '2026-03-01 17:12:38'),
(5, 7, 'honorar', NULL, NULL, NULL, 'Deutschland', '+4915774031396', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e41d9d8-5d55-4105-598d-487268a5f4b0', 'Annegret Nolte', 'Wise Europe Sa/nv ending ·· 8502', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(6, 8, 'honorar', NULL, NULL, NULL, 'Deutschland', '01705724217', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2dccde71-3b9f-4893-ad88-7af93f0ca74e', 'Björn Bredow', 'Deutsche Kredit Bank Berlin ending ·· 8110', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(7, 9, 'honorar', NULL, NULL, NULL, 'Deutschland', '00491789154118', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2b8440e8-d23e-417b-d904-d0c0dc22d4fa', 'Carolin Jansen', 'Vr-Bank Kreis Steinfurt Eg ending ·· 1900', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(8, 10, 'honorar', NULL, NULL, NULL, 'Deutschland', '004915788159509', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(9, 11, 'honorar', NULL, NULL, NULL, 'Deutschland', '+491622376496', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '7056e7a4-19b3-478a-c5b3-4de6bc4332d0', 'Christin Gallardo Sanchez', 'N26 Bank Gmbh ending ·· 6661', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(10, 12, 'honorar', NULL, NULL, NULL, 'Deutschland', '004915157313665', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '6dfc8914-57e1-4a04-8d04-bb01b414d619', 'Dr. Claudia Brahmi', 'Targobank Ag ending ·· 3271', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(11, 13, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '00491634737186', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:41', '2026-03-01 17:12:41'),
(12, 14, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '0049 17664081316', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:41', '2026-03-01 17:12:41'),
(13, 15, 'honorar', NULL, NULL, NULL, 'Deutschland', '+49 1602771160', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '494c83ad-1f55-495a-512b-9ef671f1b8d5', 'Jasmin Striffler', 'N26 Bank Gmbh ending ·· 3581', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(14, 16, 'honorar', NULL, NULL, NULL, 'Deutschland', '0041764239991', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '71331c0a-7a15-42f7-bc2d-ad56e116d9d8', 'Ela Gunjic', 'Ubs Switzerland Ag ending ·· 540T', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(15, 17, 'honorar', NULL, NULL, NULL, 'Deutschland', '015204525510', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '34504091-8d12-4615-b83e-561bfdb6e0a8', 'Miracle Soul Academy', 'Wise Europe Sa/nv ending ·· 8402', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(16, 18, 'honorar', NULL, NULL, NULL, 'Deutschland', '0049 172 8195668', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(17, 19, 'honorar', NULL, NULL, NULL, 'Deutschland', '004917661871399', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2b9d03f0-b98b-4c5b-98a3-efa724aaf960', 'Frank Kegel', 'Sparkasse ending ·· 4815', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(18, 20, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2d9e1308-5c6c-43bc-009f-23bd7abc78ed', 'Franziska Beck', 'Berliner Sparkasse ending ·· 6487', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(19, 21, 'honorar', NULL, NULL, NULL, 'Deutschland', '00491772370137', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '6694e9dc-ecf2-45f9-1322-6970c3140497', 'Gloria Marz Weier', 'Bankinter, S.A. ending ·· 7377', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:44', '2026-03-01 17:12:44'),
(20, 22, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '+15770406046', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:44', '2026-03-01 17:12:44'),
(21, 23, 'honorar', NULL, NULL, NULL, 'Deutschland', '017657759625', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e77f506-27b5-42e2-66d7-a378cef59ae0', 'Helena Riffer', 'Frankfurter Sparkasse ending ·· 4750', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(22, 24, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '+4915902468287', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(23, 25, 'honorar', NULL, NULL, NULL, 'Deutschland', '+7-925-612-29-37', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '57ca904a-665a-4457-1b69-d6fcafdaa5f0', 'Isabelle Guillaume', 'Wio Bank P.J.S.C. ending ·· 5525', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(24, 26, 'honorar', NULL, NULL, NULL, 'Deutschland', '00436603907271', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(25, 27, 'honorar', NULL, NULL, NULL, 'Deutschland', '+50671586971', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(26, 28, 'honorar', NULL, NULL, NULL, 'Deutschland', '+4915222823464', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '19bcbfbd-0107-47a2-5ec6-7b59d63e806d', 'Jens Hettstedt', 'N26 Bank Gmbh ending ·· 3136', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(27, 29, 'honorar', NULL, NULL, NULL, 'Deutschland', '+49 157 3444 7426', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '713790bb-458f-452f-2cce-adff2216f057', 'Niheki OÜ', 'Wise Europe Sa/nv ending ·· 4348', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:47', '2026-03-01 17:12:47'),
(28, 30, 'honorar', NULL, NULL, NULL, 'Deutschland', '+4367764105095', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:47', '2026-03-01 17:12:47'),
(29, 31, 'honorar', NULL, NULL, NULL, 'Deutschland', '00491605411025', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2bf00b3f-9831-4997-f245-f6819b8854d3', 'Julika Mischke', 'Sparkasse Essen ending ·· 2572', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(30, 32, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '+49 176/ 56935579', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(31, 33, 'honorar', NULL, NULL, NULL, 'Deutschland', '+595981376101', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '3b265979-04c7-473e-2526-25034ed29224', 'Katrin Enns', 'Wise Europe Sa/nv ending ·· 8176', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(32, 34, 'honorar', NULL, NULL, NULL, 'Deutschland', '01704949834', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e536bfc-a92e-4364-dd3f-2d32a9d38366', 'Kerstin Gleine', 'Ing-Diba Ag (retail Banking) ending ·· 2352', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(33, 35, 'honorar', NULL, NULL, NULL, 'Deutschland', '+49 151 46544276', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e52e716-366e-4bb1-3a42-e0df0bcf29f8', 'Kerstin Meier-Sigwart', 'Db Privat- Und Firmenkundenban. ending ·· 2301', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(34, 36, 'honorar', NULL, NULL, NULL, 'Deutschland', '+49 176 600 222 44', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2bb42fe3-eff8-4e29-f719-84af273cc50b', 'Kirsten Reiser', 'Vr-Bank Ismaning Hallbergmoos . ending ·· 5334', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(35, 37, 'honorar', NULL, NULL, NULL, 'Deutschland', '0157-39284164', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '38b92a42-ed99-4530-dbb2-14c865598fe6', 'Kornelius Schmidt', 'Deutsche Kredit Bank Berlin ending ·· 3788', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:50', '2026-03-01 17:12:50'),
(36, 38, 'honorar', NULL, NULL, NULL, 'Deutschland', '01789729432', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '56157c14-3b62-43ea-52df-504e50886040', 'Kulod Aldibssi', 'Ing-Diba Ag (retail Banking) ending ·· 5919', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:50', '2026-03-01 17:12:50'),
(37, 39, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '+49 151 18635496', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(38, 40, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2beed1cb-7b72-4029-8610-d66c22168d62', 'Lydia Schallenberg', 'Deutsche Kredit Bank Berlin ending ·· 8780', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(39, 41, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '721c7a23-6df3-4ba0-05ca-ac1fbaf8646e', 'Sarah Maamar', 'Berliner Sparkasse ending ·· 4556', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(40, 42, 'honorar', NULL, NULL, NULL, 'Deutschland', '0090-533-4853911', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2ed58a11-4013-4e60-cd06-8d0edd5368c3', 'Marina Katharina Ewald', 'Turkiye Is Bankasi A.S. ending ·· 5723', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(41, 43, 'honorar', NULL, NULL, NULL, 'Deutschland', '0178-4721611', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '68c29817-dd2b-4377-7882-cb244c6441fb', 'Wilhelm Markus SCHREIBER', 'Credit Mutuel ending ·· 0392', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(42, 44, 'honorar', NULL, NULL, NULL, 'Deutschland', '00491708487887', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '1430884d-995e-45e9-1ccb-9d1d8cb5b0ff', 'Maximilian Schneider', 'Stadtsparkasse Muenchen ending ·· 9233', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(43, 45, 'honorar', NULL, NULL, NULL, 'Deutschland', '015256515452', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '56166f63-a712-42e0-4f79-f5fa1cf3ac84', 'Milan Herold', 'Deutsche Bank ending ·· 9400', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:53', '2026-03-01 17:12:53'),
(44, 46, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '00491732363519', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:53', '2026-03-01 17:12:53'),
(45, 47, 'honorar', NULL, NULL, NULL, 'Deutschland', '015238246980', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '66976f10-ea91-41a3-a90d-436d212bc818', 'Nele Schmidt', 'Mlp Finanzdienstleistungen Ag ending ·· 3980', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(46, 48, 'honorar', NULL, NULL, NULL, 'Deutschland', '017654018065', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '5a498441-68ea-440b-d425-cde825dab64f', 'Nikolas Blume', 'Stadtsparkasse Wuppertal ending ·· 4510', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(47, 49, 'honorar', NULL, NULL, NULL, 'Deutschland', '+436787902121', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '38657c74-5a0a-4407-a226-4b810e4b0a6c', 'Paul Jakob Jurtschitsch', 'Bawag P.S.K. Bank  ending ·· 6461', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(48, 50, 'honorar', NULL, NULL, NULL, 'Deutschland', '+506 87179720', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2b9f54c1-0a3b-4a49-cd99-8f15c6696164', 'Peter Fellner', 'Db Privat- Und Firmenkundenban. ending ·· 7807', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(49, 51, 'honorar', NULL, NULL, NULL, 'Deutschland', '+59892709738', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e9785b7-42fa-48bd-6dd3-c62335c7837c', 'Rebecca Maul', 'Frankfurter Volksbank Eg ending ·· 6501', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(50, 52, 'honorar', NULL, NULL, NULL, 'Deutschland', '004368110611083', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '30995257-24e7-426d-d3b0-f6760a59650e', 'Regine Behrmann-Thiele', 'Wise Europe Sa/nv ending ·· 6816', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(51, 53, 'honorar', NULL, NULL, NULL, 'Deutschland', '+491736948499 / +971505527034', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0eb7ac53-0d11-4fc5-bf27-30504ad6bbdd', 'Rojan Toufani', 'Revolut Payments Uab ending ·· 4239', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:56', '2026-03-01 17:12:56'),
(52, 54, 'honorar', NULL, NULL, NULL, 'Deutschland', '+34 607958643', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '49257841-5cc5-46e1-75d3-83a0b8a88254', 'Ruth Michelle Socorro Brunk', 'Revolut Bank Uab, Sucursal En . ending ·· 3014', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:56', '2026-03-01 17:12:56'),
(53, 55, 'honorar', NULL, NULL, NULL, 'Deutschland', '017641080406', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '68c2e1ae-467c-4f9a-86fe-536b89aacd31', 'Sandra Stefanie Ziegler', 'Comdirect Bank Ag ending ·· 2500', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(54, 56, 'festangestellt', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(55, 57, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e978530-3a71-49dd-7431-7566c4a716ca', 'Sebastian Böhmer', 'Saalesparkasse ending ·· 1840', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(56, 58, 'honorar', NULL, NULL, NULL, 'Deutschland', '00971544317379', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '5f1a4e50-810e-4cbf-4a1a-c42b18840033', 'Sitem Kolburan', 'Revolut Bank Uab, Zweigniederl. ending ·· 0877', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:58', '2026-03-01 17:12:58'),
(57, 59, 'honorar', NULL, NULL, NULL, 'Deutschland', '0049 15788170857', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e514814-8f0d-4e25-a3e6-618a97a1b71d', 'Marcus Behrens', 'N26 Bank Gmbh ending ·· 5194', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:58', '2026-03-01 17:12:58'),
(58, 60, 'honorar', NULL, NULL, NULL, 'Deutschland', '07672-481544', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e41d289-69c8-4bd5-1aa8-de82960261c3', 'Sonja Ehrenfeuchter', 'Wise Europe Sa/nv ending ·· 9165', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:58', '2026-03-01 17:12:58'),
(59, 61, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '015755769187', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:59', '2026-03-01 17:12:59'),
(60, 62, 'festangestellt', NULL, NULL, NULL, 'Deutschland', '+4917662312240', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:12:59', '2026-03-01 17:12:59'),
(61, 63, 'honorar', NULL, NULL, NULL, 'Deutschland', '+49 176 61820014', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '0e513c42-f22d-478b-fbf1-227611b57110', 'Thomas Kriegel', 'Sparkasse Lueneburg ending ·· 9679', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(62, 64, 'honorar', NULL, NULL, NULL, 'Deutschland', '0050687511050', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '68c292ec-1509-4553-a3d8-573706fa2619', 'Inna Völtz Völtz', 'Wise Europe Sa/nv ending ·· 2501', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(63, 65, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '61dadc46-971c-4671-0ceb-34c133479e4e', 'Elstner Analytics GmbH', 'Finom Payments B.V. Zweigniede. ending ·· 8449', 'INSTITUTION', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(64, 66, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '61c2b2c2-ff15-4248-6ad3-c356b88b759f', 'Maria Isabel Romero Espejo', 'Caixabank, S.A. ending ·· 1861', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(65, 67, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(66, 68, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '626a4107-9bfb-46e5-c8e4-924f9e263fe9', 'Stefanie Rickert', 'Zuercher Kantonalbank ending ·· 9201', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(67, 69, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '68c33729-4194-4d89-6b2a-de78d79679d7', 'Christina Sibylle Marcial Gomes', 'Banco Bpi Sa ending ·· 0195', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:02', '2026-03-01 17:13:02'),
(68, 70, 'honorar', NULL, NULL, NULL, 'Deutschland', '+971 585778903', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '66850e74-0e64-4063-0773-a0ca521e627d', 'Yosef Outadrarte', 'N26 Bank Gmbh ending ·· 8631', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:02', '2026-03-01 17:13:02'),
(69, 71, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '6ca4a882-dd41-4b54-df9c-e699901eec6e', 'Kerr&Jones Pte Ltd', 'Wise Europe Sa/nv ending ·· 3388', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(70, 72, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '721c8b52-c3fc-4b6a-fd47-123252d8152d', 'Stefan Pfenninger', 'Banque Et Caisse D\'epargne De . ending ·· 3000', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(71, 73, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '674e1dd0-1281-446e-bfc2-d19fa0f67af8', 'Vanessa Schumann', 'Deutsche Kredit Bank Berlin ending ·· 6631', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(72, 74, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '721c883e-f44f-48f9-7d00-47b62f6b3c97', 'Klaus-Dieter Rumpel', 'Kreissparkasse Syke ending ·· 1339', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:04', '2026-03-01 17:13:04'),
(73, 75, 'honorar', NULL, NULL, NULL, 'Deutschland', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '10ac70ef-81e9-416c-4d1b-8ca88d0af1f0', 'Dr. des. Jennifer Wengler', 'Santander Consumer Bank Ag ending ·· 1767', 'PERSON', 36.00, '2026-03-01', NULL, NULL, '2026-03-01 17:13:04', '2026-03-01 17:13:04');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_documents`
--

CREATE TABLE `teacher_documents` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `category` enum('vertrag','steuerbescheinigung','ausweis','sonstiges') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sonstiges',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int UNSIGNED DEFAULT NULL,
  `uploaded_by` int UNSIGNED NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lehrerdokumente';

-- --------------------------------------------------------

--
-- Table structure for table `teacher_rates`
--

CREATE TABLE `teacher_rates` (
  `id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `hourly_rate` decimal(8,2) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_until` date DEFAULT NULL COMMENT 'NULL = aktuell gültig',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historisierte Stundensätze';

--
-- Dumping data for table `teacher_rates`
--

INSERT INTO `teacher_rates` (`id`, `teacher_id`, `hourly_rate`, `valid_from`, `valid_until`, `created_by`, `created_at`, `notes`) VALUES
(1, 1, 36.00, '2026-02-28', NULL, 1, '2026-02-28 23:06:46', NULL),
(2, 2, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:37', NULL),
(3, 3, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:38', NULL),
(4, 4, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:38', NULL),
(5, 5, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:39', NULL),
(6, 6, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:39', NULL),
(7, 7, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:39', NULL),
(8, 8, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:40', NULL),
(9, 9, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:40', NULL),
(10, 10, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:40', NULL),
(11, 11, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:41', NULL),
(12, 12, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:41', NULL),
(13, 13, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:42', NULL),
(14, 14, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:42', NULL),
(15, 15, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:42', NULL),
(16, 16, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:43', NULL),
(17, 17, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:43', NULL),
(18, 18, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:43', NULL),
(19, 19, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:44', NULL),
(20, 20, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:44', NULL),
(21, 21, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:45', NULL),
(22, 22, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:45', NULL),
(23, 23, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:45', NULL),
(24, 24, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:46', NULL),
(25, 25, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:46', NULL),
(26, 26, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:46', NULL),
(27, 27, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:47', NULL),
(28, 28, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:47', NULL),
(29, 29, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:48', NULL),
(30, 30, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:48', NULL),
(31, 31, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:48', NULL),
(32, 32, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:49', NULL),
(33, 33, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:49', NULL),
(34, 34, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:49', NULL),
(35, 35, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:50', NULL),
(36, 36, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:50', NULL),
(37, 37, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:51', NULL),
(38, 38, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:51', NULL),
(39, 39, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:51', NULL),
(40, 40, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:52', NULL),
(41, 41, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:52', NULL),
(42, 42, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:52', NULL),
(43, 43, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:53', NULL),
(44, 44, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:53', NULL),
(45, 45, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:54', NULL),
(46, 46, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:54', NULL),
(47, 47, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:54', NULL),
(48, 48, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:55', NULL),
(49, 49, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:55', NULL),
(50, 50, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:55', NULL),
(51, 51, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:56', NULL),
(52, 52, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:56', NULL),
(53, 53, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:57', NULL),
(54, 54, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:57', NULL),
(55, 55, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:57', NULL),
(56, 56, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:58', NULL),
(57, 57, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:58', NULL),
(58, 58, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:58', NULL),
(59, 59, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:59', NULL),
(60, 60, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:12:59', NULL),
(61, 61, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:00', NULL),
(62, 62, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:00', NULL),
(63, 63, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:00', NULL),
(64, 64, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:01', NULL),
(65, 65, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:01', NULL),
(66, 66, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:01', NULL),
(67, 67, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:02', NULL),
(68, 68, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:02', NULL),
(69, 69, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:03', NULL),
(70, 70, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:03', NULL),
(71, 71, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:03', NULL),
(72, 72, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:04', NULL),
(73, 73, 36.00, '2026-03-01', NULL, 1, '2026-03-01 17:13:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_entries`
--

CREATE TABLE `timetable_entries` (
  `id` int UNSIGNED NOT NULL,
  `plan_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `subject_id` int UNSIGNED NOT NULL,
  `weekday` tinyint UNSIGNED NOT NULL COMMENT '1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr',
  `period_start` tinyint UNSIGNED NOT NULL COMMENT 'Stunde des Tages (1-basiert)',
  `time_start` time NOT NULL COMMENT 'Uhrzeitbeginn',
  `time_end` time NOT NULL COMMENT 'Uhrzeitende',
  `is_double_first` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = 1. Teil einer Doppelstunde',
  `is_double_second` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = 2. Teil einer Doppelstunde',
  `double_group_id` int UNSIGNED DEFAULT NULL COMMENT 'Verbindet zwei Einheiten zur Doppelstunde',
  `csv_activity_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Activity Id aus CSV zur Referenz',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stundenplaneinträge (45-Min-Einheiten)';

--
-- Dumping data for table `timetable_entries`
--

INSERT INTO `timetable_entries` (`id`, `plan_id`, `teacher_id`, `subject_id`, `weekday`, `period_start`, `time_start`, `time_end`, `is_double_first`, `is_double_second`, `double_group_id`, `csv_activity_id`, `notes`) VALUES
(1, 1, 43, 1, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '191', NULL),
(2, 1, 43, 1, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '192', NULL),
(3, 1, 43, 1, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '193', NULL),
(4, 1, 44, 2, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '199', NULL),
(5, 1, 44, 2, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '200', NULL),
(6, 1, 44, 2, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '201', NULL),
(7, 1, 68, 1, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '252', NULL),
(8, 1, 68, 1, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '253', NULL),
(9, 1, 68, 1, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '254', NULL),
(10, 1, 18, 3, 5, 1, '14:00:00', '14:45:00', 1, 0, 1, '261', NULL),
(11, 1, 18, 3, 5, 2, '14:45:00', '15:30:00', 0, 1, 1, '261', NULL),
(12, 1, 18, 3, 2, 3, '15:45:00', '16:30:00', 0, 0, NULL, '262', NULL),
(13, 1, 18, 3, 3, 1, '14:00:00', '14:45:00', 1, 0, 2, '263', NULL),
(14, 1, 18, 3, 3, 2, '14:45:00', '15:30:00', 0, 1, 2, '263', NULL),
(15, 1, 27, 4, 4, 1, '14:00:00', '14:45:00', 1, 0, 3, '267', NULL),
(16, 1, 27, 4, 4, 2, '14:45:00', '15:30:00', 0, 1, 3, '267', NULL),
(17, 1, 27, 4, 2, 1, '14:00:00', '14:45:00', 0, 0, NULL, '268', NULL),
(18, 1, 5, 5, 4, 4, '16:30:00', '17:15:00', 0, 0, NULL, '269', NULL),
(19, 1, 32, 6, 2, 2, '14:45:00', '15:30:00', 0, 1, 4, '270', NULL),
(20, 1, 57, 7, 4, 3, '15:45:00', '16:30:00', 0, 0, NULL, '271', NULL),
(21, 1, 27, 3, 4, 3, '15:45:00', '16:30:00', 1, 0, 5, '274', NULL),
(22, 1, 27, 3, 4, 4, '16:30:00', '17:15:00', 0, 1, 5, '274', NULL),
(23, 1, 27, 3, 2, 3, '15:45:00', '16:30:00', 0, 0, NULL, '275', NULL),
(24, 1, 27, 3, 3, 1, '14:00:00', '14:45:00', 1, 0, 6, '276', NULL),
(25, 1, 27, 3, 3, 2, '14:45:00', '15:30:00', 0, 1, 6, '276', NULL),
(26, 1, 27, 4, 1, 1, '14:00:00', '14:45:00', 1, 0, 7, '280', NULL),
(27, 1, 27, 4, 1, 2, '14:45:00', '15:30:00', 0, 1, 7, '280', NULL),
(28, 1, 27, 4, 3, 3, '15:45:00', '16:30:00', 0, 0, NULL, '281', NULL),
(29, 1, 5, 5, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '282', NULL),
(30, 1, 59, 6, 5, 4, '16:30:00', '17:15:00', 0, 0, NULL, '283', NULL),
(31, 1, 27, 7, 2, 2, '14:45:00', '15:30:00', 0, 0, NULL, '284', NULL),
(32, 1, 33, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 8, '286', NULL),
(33, 1, 33, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 8, '286', NULL),
(34, 1, 33, 3, 1, 3, '15:45:00', '16:30:00', 1, 0, 9, '287', NULL),
(35, 1, 33, 3, 1, 4, '16:30:00', '17:15:00', 0, 1, 9, '287', NULL),
(36, 1, 57, 8, 5, 1, '14:00:00', '14:45:00', 1, 0, 10, '289', NULL),
(37, 1, 57, 8, 5, 2, '14:45:00', '15:30:00', 0, 1, 10, '289', NULL),
(38, 1, 57, 8, 2, 1, '14:00:00', '14:45:00', 1, 0, 11, '290', NULL),
(39, 1, 57, 8, 2, 2, '14:45:00', '15:30:00', 0, 1, 11, '290', NULL),
(40, 1, 57, 8, 1, 5, '17:30:00', '18:15:00', 0, 0, NULL, '291', NULL),
(41, 1, 21, 4, 2, 3, '15:45:00', '16:30:00', 1, 0, 12, '292', NULL),
(42, 1, 21, 4, 2, 4, '16:30:00', '17:15:00', 0, 1, 12, '292', NULL),
(43, 1, 21, 4, 5, 4, '16:30:00', '17:15:00', 0, 1, 13, '293', NULL),
(44, 1, 45, 6, 1, 2, '14:45:00', '15:30:00', 0, 0, NULL, '297', NULL),
(45, 1, 33, 7, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '298', NULL),
(46, 1, 61, 3, 2, 1, '14:00:00', '14:45:00', 1, 0, 14, '310', NULL),
(47, 1, 61, 3, 2, 2, '14:45:00', '15:30:00', 0, 1, 14, '310', NULL),
(48, 1, 61, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 15, '311', NULL),
(49, 1, 61, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 15, '311', NULL),
(50, 1, 61, 3, 1, 3, '15:45:00', '16:30:00', 1, 0, 16, '312', NULL),
(51, 1, 61, 3, 1, 4, '16:30:00', '17:15:00', 0, 1, 16, '312', NULL),
(52, 1, 21, 4, 5, 1, '14:00:00', '14:45:00', 1, 0, 17, '316', NULL),
(53, 1, 21, 4, 5, 2, '14:45:00', '15:30:00', 0, 1, 17, '316', NULL),
(54, 1, 21, 4, 4, 3, '15:45:00', '16:30:00', 0, 0, NULL, '317', NULL),
(55, 1, 49, 9, 2, 3, '15:45:00', '16:30:00', 1, 0, 18, '318', NULL),
(56, 1, 49, 9, 2, 4, '16:30:00', '17:15:00', 0, 1, 18, '318', NULL),
(57, 1, 59, 5, 5, 3, '15:45:00', '16:30:00', 0, 0, NULL, '319', NULL),
(58, 1, 59, 6, 1, 5, '17:30:00', '18:15:00', 0, 0, NULL, '320', NULL),
(59, 1, 31, 7, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '321', NULL),
(60, 1, 38, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 19, '323', NULL),
(61, 1, 38, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 19, '323', NULL),
(62, 1, 38, 3, 1, 3, '15:45:00', '16:30:00', 1, 0, 20, '324', NULL),
(63, 1, 38, 3, 1, 4, '16:30:00', '17:15:00', 0, 1, 20, '324', NULL),
(64, 1, 38, 3, 4, 1, '14:00:00', '14:45:00', 1, 0, 21, '325', NULL),
(65, 1, 38, 3, 4, 2, '14:45:00', '15:30:00', 0, 1, 21, '325', NULL),
(66, 1, 57, 8, 4, 4, '16:30:00', '17:15:00', 0, 0, NULL, '326', NULL),
(67, 1, 57, 8, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '326', NULL),
(68, 1, 57, 8, 1, 1, '14:00:00', '14:45:00', 1, 0, 22, '327', NULL),
(69, 1, 57, 8, 1, 2, '14:45:00', '15:30:00', 0, 1, 22, '327', NULL),
(70, 1, 57, 8, 2, 3, '15:45:00', '16:30:00', 1, 0, 23, '328', NULL),
(71, 1, 57, 8, 2, 4, '16:30:00', '17:15:00', 0, 1, 23, '328', NULL),
(72, 1, 49, 9, 2, 1, '14:00:00', '14:45:00', 1, 0, 24, '331', NULL),
(73, 1, 49, 9, 2, 2, '14:45:00', '15:30:00', 0, 1, 24, '331', NULL),
(74, 1, 59, 5, 3, 2, '14:45:00', '15:30:00', 0, 0, NULL, '332', NULL),
(75, 1, 59, 6, 3, 1, '14:00:00', '14:45:00', 0, 0, NULL, '333', NULL),
(76, 1, 18, 3, 1, 1, '14:00:00', '14:45:00', 1, 0, 25, '334', NULL),
(77, 1, 18, 3, 1, 2, '14:45:00', '15:30:00', 0, 1, 25, '334', NULL),
(78, 1, 18, 3, 2, 1, '14:00:00', '14:45:00', 1, 0, 26, '335', NULL),
(79, 1, 18, 3, 2, 2, '14:45:00', '15:30:00', 0, 1, 26, '335', NULL),
(80, 1, 59, 9, 4, 5, '17:30:00', '18:15:00', 1, 0, 27, '336', NULL),
(81, 1, 59, 9, 4, 6, '18:15:00', '19:00:00', 0, 1, 27, '336', NULL),
(82, 1, 59, 9, 3, 5, '17:30:00', '18:15:00', 1, 0, 28, '337', NULL),
(83, 1, 59, 9, 3, 6, '18:15:00', '19:00:00', 0, 1, 28, '337', NULL),
(84, 1, 20, 8, 4, 3, '15:45:00', '16:30:00', 1, 0, 29, '338', NULL),
(85, 1, 20, 8, 4, 4, '16:30:00', '17:15:00', 0, 1, 29, '338', NULL),
(86, 1, 20, 8, 5, 3, '15:45:00', '16:30:00', 1, 0, 30, '339', NULL),
(87, 1, 20, 8, 5, 4, '16:30:00', '17:15:00', 0, 1, 30, '339', NULL),
(88, 1, 60, 10, 5, 1, '14:00:00', '14:45:00', 1, 0, 31, '340', NULL),
(89, 1, 60, 10, 5, 2, '14:45:00', '15:30:00', 0, 1, 31, '340', NULL),
(90, 1, 42, 11, 3, 1, '14:00:00', '14:45:00', 1, 0, 32, '341', NULL),
(91, 1, 42, 11, 3, 2, '14:45:00', '15:30:00', 0, 1, 32, '341', NULL),
(92, 1, 18, 12, 1, 3, '15:45:00', '16:30:00', 1, 0, 33, '344', NULL),
(93, 1, 18, 12, 1, 4, '16:30:00', '17:15:00', 0, 1, 33, '344', NULL),
(94, 1, 58, 9, 4, 2, '14:45:00', '15:30:00', 0, 0, NULL, '346', NULL),
(95, 1, 58, 9, 4, 3, '15:45:00', '16:30:00', 0, 0, NULL, '346', NULL),
(96, 1, 59, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 34, '348', NULL),
(97, 1, 59, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 34, '348', NULL),
(98, 1, 59, 3, 4, 1, '14:00:00', '14:45:00', 1, 0, 35, '349', NULL),
(99, 1, 59, 3, 4, 2, '14:45:00', '15:30:00', 0, 1, 35, '349', NULL),
(100, 1, 58, 9, 4, 5, '17:30:00', '18:15:00', 1, 0, 36, '350', NULL),
(101, 1, 58, 9, 4, 6, '18:15:00', '19:00:00', 0, 1, 36, '350', NULL),
(102, 1, 58, 9, 1, 3, '15:45:00', '16:30:00', 1, 0, 37, '351', NULL),
(103, 1, 58, 9, 1, 4, '16:30:00', '17:15:00', 0, 1, 37, '351', NULL),
(104, 1, 19, 8, 4, 3, '15:45:00', '16:30:00', 1, 0, 38, '352', NULL),
(105, 1, 19, 8, 4, 4, '16:30:00', '17:15:00', 0, 1, 38, '352', NULL),
(106, 1, 19, 8, 2, 1, '14:00:00', '14:45:00', 1, 0, 39, '353', NULL),
(107, 1, 19, 8, 2, 2, '14:45:00', '15:30:00', 0, 1, 39, '353', NULL),
(108, 1, 19, 10, 2, 3, '15:45:00', '16:30:00', 1, 0, 40, '354', NULL),
(109, 1, 19, 10, 2, 4, '16:30:00', '17:15:00', 0, 1, 40, '354', NULL),
(110, 1, 32, 11, 3, 5, '17:30:00', '18:15:00', 1, 0, 41, '355', NULL),
(111, 1, 32, 11, 3, 6, '18:15:00', '19:00:00', 0, 1, 41, '355', NULL),
(112, 1, 32, 13, 3, 1, '14:00:00', '14:45:00', 1, 0, 42, '356', NULL),
(113, 1, 32, 13, 3, 2, '14:45:00', '15:30:00', 0, 1, 42, '356', NULL),
(114, 1, 5, 14, 1, 5, '17:30:00', '18:15:00', 1, 0, 43, '357', NULL),
(115, 1, 5, 14, 1, 6, '18:15:00', '19:00:00', 0, 1, 43, '357', NULL),
(116, 1, 5, 12, 2, 5, '17:30:00', '18:15:00', 1, 0, 44, '358', NULL),
(117, 1, 5, 12, 2, 6, '18:15:00', '19:00:00', 0, 1, 44, '358', NULL),
(118, 1, 61, 3, 3, 5, '17:30:00', '18:15:00', 1, 0, 45, '359', NULL),
(119, 1, 61, 3, 3, 6, '18:15:00', '19:00:00', 0, 1, 45, '359', NULL),
(120, 1, 61, 3, 1, 5, '17:30:00', '18:15:00', 1, 0, 46, '360', NULL),
(121, 1, 61, 3, 1, 6, '18:15:00', '19:00:00', 0, 1, 46, '360', NULL),
(122, 1, 52, 9, 2, 1, '14:00:00', '14:45:00', 1, 0, 47, '361', NULL),
(123, 1, 52, 9, 2, 2, '14:45:00', '15:30:00', 0, 1, 47, '361', NULL),
(124, 1, 52, 9, 4, 5, '17:30:00', '18:15:00', 1, 0, 48, '362', NULL),
(125, 1, 52, 9, 4, 6, '18:15:00', '19:00:00', 0, 1, 48, '362', NULL),
(126, 1, 20, 8, 4, 1, '14:00:00', '14:45:00', 1, 0, 49, '363', NULL),
(127, 1, 20, 8, 4, 2, '14:45:00', '15:30:00', 0, 1, 49, '363', NULL),
(128, 1, 20, 8, 1, 1, '14:00:00', '14:45:00', 1, 0, 50, '364', NULL),
(129, 1, 20, 8, 1, 2, '14:45:00', '15:30:00', 0, 1, 50, '364', NULL),
(130, 1, 40, 10, 1, 3, '15:45:00', '16:30:00', 1, 0, 51, '365', NULL),
(131, 1, 40, 10, 1, 4, '16:30:00', '17:15:00', 0, 1, 51, '365', NULL),
(132, 1, 13, 11, 3, 3, '15:45:00', '16:30:00', 1, 0, 52, '366', NULL),
(133, 1, 13, 11, 3, 4, '16:30:00', '17:15:00', 0, 1, 52, '366', NULL),
(134, 1, 61, 14, 2, 5, '17:30:00', '18:15:00', 1, 0, 53, '367', NULL),
(135, 1, 61, 14, 2, 6, '18:15:00', '19:00:00', 0, 1, 53, '367', NULL),
(136, 1, 15, 12, 3, 1, '14:00:00', '14:45:00', 1, 0, 54, '368', NULL),
(137, 1, 15, 12, 3, 2, '14:45:00', '15:30:00', 0, 1, 54, '368', NULL),
(138, 1, 13, 3, 5, 1, '14:00:00', '14:45:00', 1, 0, 55, '371', NULL),
(139, 1, 13, 3, 5, 2, '14:45:00', '15:30:00', 0, 1, 55, '371', NULL),
(140, 1, 13, 3, 3, 1, '14:00:00', '14:45:00', 1, 0, 56, '372', NULL),
(141, 1, 13, 3, 3, 2, '14:45:00', '15:30:00', 0, 1, 56, '372', NULL),
(142, 1, 52, 9, 2, 5, '17:30:00', '18:15:00', 1, 0, 57, '373', NULL),
(143, 1, 52, 9, 2, 6, '18:15:00', '19:00:00', 0, 1, 57, '373', NULL),
(144, 1, 52, 9, 1, 3, '15:45:00', '16:30:00', 1, 0, 58, '374', NULL),
(145, 1, 52, 9, 1, 4, '16:30:00', '17:15:00', 0, 1, 58, '374', NULL),
(146, 1, 3, 8, 3, 3, '15:45:00', '16:30:00', 1, 0, 59, '375', NULL),
(147, 1, 3, 8, 3, 4, '16:30:00', '17:15:00', 0, 1, 59, '375', NULL),
(148, 1, 3, 8, 4, 3, '15:45:00', '16:30:00', 1, 0, 60, '376', NULL),
(149, 1, 3, 8, 4, 4, '16:30:00', '17:15:00', 0, 1, 60, '376', NULL),
(150, 1, 60, 10, 2, 3, '15:45:00', '16:30:00', 1, 0, 61, '377', NULL),
(151, 1, 60, 10, 2, 4, '16:30:00', '17:15:00', 0, 1, 61, '377', NULL),
(152, 1, 13, 11, 4, 1, '14:00:00', '14:45:00', 1, 0, 62, '378', NULL),
(153, 1, 13, 11, 4, 2, '14:45:00', '15:30:00', 0, 1, 62, '378', NULL),
(154, 1, 5, 14, 3, 5, '17:30:00', '18:15:00', 1, 0, 63, '379', NULL),
(155, 1, 5, 14, 3, 6, '18:15:00', '19:00:00', 0, 1, 63, '379', NULL),
(156, 1, 15, 12, 2, 1, '14:00:00', '14:45:00', 1, 0, 64, '380', NULL),
(157, 1, 15, 12, 2, 2, '14:45:00', '15:30:00', 0, 1, 64, '380', NULL),
(158, 1, 49, 3, 4, 3, '15:45:00', '16:30:00', 1, 0, 65, '382', NULL),
(159, 1, 49, 3, 4, 4, '16:30:00', '17:15:00', 0, 1, 65, '382', NULL),
(160, 1, 49, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 66, '383', NULL),
(161, 1, 49, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 66, '383', NULL),
(162, 1, 59, 9, 2, 1, '14:00:00', '14:45:00', 1, 0, 67, '384', NULL),
(163, 1, 59, 9, 2, 2, '14:45:00', '15:30:00', 0, 1, 67, '384', NULL),
(164, 1, 59, 9, 1, 1, '14:00:00', '14:45:00', 1, 0, 68, '385', NULL),
(165, 1, 59, 9, 1, 2, '14:45:00', '15:30:00', 0, 1, 68, '385', NULL),
(166, 1, 62, 8, 2, 3, '15:45:00', '16:30:00', 1, 0, 69, '386', NULL),
(167, 1, 62, 8, 2, 4, '16:30:00', '17:15:00', 0, 1, 69, '386', NULL),
(168, 1, 62, 8, 3, 5, '17:30:00', '18:15:00', 1, 0, 70, '387', NULL),
(169, 1, 62, 8, 3, 6, '18:15:00', '19:00:00', 0, 1, 70, '387', NULL),
(170, 1, 15, 14, 4, 1, '14:00:00', '14:45:00', 1, 0, 71, '392', NULL),
(171, 1, 15, 14, 4, 2, '14:45:00', '15:30:00', 0, 1, 71, '392', NULL),
(172, 1, 32, 12, 2, 5, '17:30:00', '18:15:00', 1, 0, 72, '393', NULL),
(173, 1, 32, 12, 2, 6, '18:15:00', '19:00:00', 0, 1, 72, '393', NULL),
(174, 1, 46, 15, 1, 5, '17:30:00', '18:15:00', 1, 0, 73, '396', NULL),
(175, 1, 46, 15, 1, 6, '18:15:00', '19:00:00', 0, 1, 73, '396', NULL),
(176, 1, 12, 16, 1, 3, '15:45:00', '16:30:00', 1, 0, 74, '397', NULL),
(177, 1, 12, 16, 1, 4, '16:30:00', '17:15:00', 0, 1, 74, '397', NULL),
(178, 1, 49, 9, 3, 5, '17:30:00', '18:15:00', 1, 0, 75, '400', NULL),
(179, 1, 49, 9, 3, 6, '18:15:00', '19:00:00', 0, 1, 75, '400', NULL),
(180, 1, 49, 9, 5, 3, '15:45:00', '16:30:00', 1, 0, 76, '401', NULL),
(181, 1, 49, 9, 5, 4, '16:30:00', '17:15:00', 0, 1, 76, '401', NULL),
(182, 1, 20, 8, 1, 5, '17:30:00', '18:15:00', 1, 0, 77, '402', NULL),
(183, 1, 20, 8, 1, 6, '18:15:00', '19:00:00', 0, 1, 77, '402', NULL),
(184, 1, 20, 8, 5, 1, '14:00:00', '14:45:00', 1, 0, 78, '403', NULL),
(185, 1, 20, 8, 5, 2, '14:45:00', '15:30:00', 0, 1, 78, '403', NULL),
(186, 1, 32, 12, 4, 1, '14:00:00', '14:45:00', 1, 0, 79, '408', NULL),
(187, 1, 32, 12, 4, 2, '14:45:00', '15:30:00', 0, 1, 79, '408', NULL),
(188, 1, 4, 15, 1, 3, '15:45:00', '16:30:00', 1, 0, 80, '410', NULL),
(189, 1, 4, 15, 1, 4, '16:30:00', '17:15:00', 0, 1, 80, '410', NULL),
(190, 1, 53, 16, 3, 1, '14:00:00', '14:45:00', 1, 0, 81, '411', NULL),
(191, 1, 17, 16, 3, 1, '14:00:00', '14:45:00', 1, 0, 81, '411', NULL),
(192, 1, 53, 16, 3, 2, '14:45:00', '15:30:00', 0, 1, 81, '411', NULL),
(193, 1, 17, 16, 3, 2, '14:45:00', '15:30:00', 0, 1, 81, '411', NULL),
(194, 1, 50, 3, 3, 5, '17:30:00', '18:15:00', 1, 0, 82, '412', NULL),
(195, 1, 50, 3, 3, 6, '18:15:00', '19:00:00', 0, 1, 82, '412', NULL),
(196, 1, 3, 8, 1, 3, '15:45:00', '16:30:00', 1, 0, 83, '415', NULL),
(197, 1, 3, 8, 1, 4, '16:30:00', '17:15:00', 0, 1, 83, '415', NULL),
(198, 1, 3, 8, 5, 3, '15:45:00', '16:30:00', 1, 0, 84, '416', NULL),
(199, 1, 3, 8, 5, 4, '16:30:00', '17:15:00', 0, 1, 84, '416', NULL),
(200, 1, 37, 17, 4, 3, '15:45:00', '16:30:00', 1, 0, 85, '423', NULL),
(201, 1, 37, 17, 4, 4, '16:30:00', '17:15:00', 0, 1, 85, '423', NULL),
(202, 1, 37, 17, 2, 3, '15:45:00', '16:30:00', 1, 0, 86, '424', NULL),
(203, 1, 37, 17, 2, 4, '16:30:00', '17:15:00', 0, 1, 86, '424', NULL),
(204, 1, 18, 15, 4, 1, '14:00:00', '14:45:00', 1, 0, 87, '428', NULL),
(205, 1, 53, 15, 4, 1, '14:00:00', '14:45:00', 1, 0, 87, '428', NULL),
(206, 1, 18, 15, 4, 2, '14:45:00', '15:30:00', 0, 1, 87, '428', NULL),
(207, 1, 53, 15, 4, 2, '14:45:00', '15:30:00', 0, 1, 87, '428', NULL),
(208, 1, 20, 16, 2, 1, '14:00:00', '14:45:00', 1, 0, 88, '429', NULL),
(209, 1, 17, 16, 2, 1, '14:00:00', '14:45:00', 1, 0, 88, '429', NULL),
(210, 1, 20, 16, 2, 2, '14:45:00', '15:30:00', 0, 1, 88, '429', NULL),
(211, 1, 17, 16, 2, 2, '14:45:00', '15:30:00', 0, 1, 88, '429', NULL),
(212, 1, 41, 18, 5, 1, '14:00:00', '14:45:00', 1, 0, 89, '430', NULL),
(213, 1, 41, 18, 5, 2, '14:45:00', '15:30:00', 0, 1, 89, '430', NULL),
(214, 1, 13, 3, 3, 5, '17:30:00', '18:15:00', 1, 0, 90, '439', NULL),
(215, 1, 13, 3, 3, 6, '18:15:00', '19:00:00', 0, 1, 90, '439', NULL),
(216, 1, 13, 3, 5, 3, '15:45:00', '16:30:00', 1, 0, 91, '440', NULL),
(217, 1, 13, 3, 5, 4, '16:30:00', '17:15:00', 0, 1, 91, '440', NULL),
(218, 1, 63, 8, 4, 1, '14:00:00', '14:45:00', 1, 0, 92, '442', NULL),
(219, 1, 63, 8, 4, 2, '14:45:00', '15:30:00', 0, 1, 92, '442', NULL),
(220, 1, 63, 8, 2, 1, '14:00:00', '14:45:00', 1, 0, 93, '443', NULL),
(221, 1, 63, 8, 2, 2, '14:45:00', '15:30:00', 0, 1, 93, '443', NULL),
(222, 1, 35, 15, 1, 3, '15:45:00', '16:30:00', 1, 0, 94, '451', NULL),
(223, 1, 17, 15, 1, 3, '15:45:00', '16:30:00', 1, 0, 94, '451', NULL),
(224, 1, 35, 15, 1, 4, '16:30:00', '17:15:00', 0, 1, 94, '451', NULL),
(225, 1, 17, 15, 1, 4, '16:30:00', '17:15:00', 0, 1, 94, '451', NULL),
(226, 1, 41, 16, 4, 5, '17:30:00', '18:15:00', 1, 0, 95, '452', NULL),
(227, 1, 45, 16, 4, 5, '17:30:00', '18:15:00', 1, 0, 95, '452', NULL),
(228, 1, 41, 16, 4, 6, '18:15:00', '19:00:00', 0, 1, 95, '452', NULL),
(229, 1, 45, 16, 4, 6, '18:15:00', '19:00:00', 0, 1, 95, '452', NULL),
(230, 1, 47, 18, 3, 3, '15:45:00', '16:30:00', 1, 0, 96, '453', NULL),
(231, 1, 47, 18, 3, 4, '16:30:00', '17:15:00', 0, 1, 96, '453', NULL),
(232, 1, 56, 3, 1, 5, '17:30:00', '18:15:00', 1, 0, 97, '454', NULL),
(233, 1, 56, 3, 1, 6, '18:15:00', '19:00:00', 0, 1, 97, '454', NULL),
(234, 1, 56, 3, 3, 5, '17:30:00', '18:15:00', 1, 0, 98, '455', NULL),
(235, 1, 56, 3, 3, 6, '18:15:00', '19:00:00', 0, 1, 98, '455', NULL),
(236, 1, 52, 9, 3, 3, '15:45:00', '16:30:00', 1, 0, 99, '456', NULL),
(237, 1, 52, 9, 3, 4, '16:30:00', '17:15:00', 0, 1, 99, '456', NULL),
(238, 1, 52, 9, 5, 1, '14:00:00', '14:45:00', 1, 0, 100, '457', NULL),
(239, 1, 52, 9, 5, 2, '14:45:00', '15:30:00', 0, 1, 100, '457', NULL),
(240, 1, 63, 8, 5, 3, '15:45:00', '16:30:00', 1, 0, 101, '458', NULL),
(241, 1, 63, 8, 5, 4, '16:30:00', '17:15:00', 0, 1, 101, '458', NULL),
(242, 1, 63, 8, 1, 1, '14:00:00', '14:45:00', 1, 0, 102, '459', NULL),
(243, 1, 63, 8, 1, 2, '14:45:00', '15:30:00', 0, 1, 102, '459', NULL),
(244, 1, 37, 17, 3, 1, '14:00:00', '14:45:00', 1, 0, 103, '466', NULL),
(245, 1, 55, 17, 3, 1, '14:00:00', '14:45:00', 1, 0, 103, '466', NULL),
(246, 1, 37, 17, 3, 2, '14:45:00', '15:30:00', 0, 1, 103, '466', NULL),
(247, 1, 55, 17, 3, 2, '14:45:00', '15:30:00', 0, 1, 103, '466', NULL),
(248, 1, 37, 17, 2, 1, '14:00:00', '14:45:00', 1, 0, 104, '467', NULL),
(249, 1, 55, 17, 2, 1, '14:00:00', '14:45:00', 1, 0, 104, '467', NULL),
(250, 1, 37, 17, 2, 2, '14:45:00', '15:30:00', 0, 1, 104, '467', NULL),
(251, 1, 55, 17, 2, 2, '14:45:00', '15:30:00', 0, 1, 104, '467', NULL),
(252, 1, 47, 19, 4, 5, '17:30:00', '18:15:00', 1, 0, 105, '468', NULL),
(253, 1, 47, 19, 4, 6, '18:15:00', '19:00:00', 0, 1, 105, '468', NULL),
(254, 1, 20, 15, 2, 5, '17:30:00', '18:15:00', 1, 0, 106, '471', NULL),
(255, 1, 17, 15, 2, 5, '17:30:00', '18:15:00', 1, 0, 106, '471', NULL),
(256, 1, 20, 15, 2, 6, '18:15:00', '19:00:00', 0, 1, 106, '471', NULL),
(257, 1, 17, 15, 2, 6, '18:15:00', '19:00:00', 0, 1, 106, '471', NULL),
(258, 1, 41, 16, 4, 3, '15:45:00', '16:30:00', 1, 0, 107, '472', NULL),
(259, 1, 47, 16, 4, 3, '15:45:00', '16:30:00', 1, 0, 107, '472', NULL),
(260, 1, 41, 16, 4, 4, '16:30:00', '17:15:00', 0, 1, 107, '472', NULL),
(261, 1, 47, 16, 4, 4, '16:30:00', '17:15:00', 0, 1, 107, '472', NULL),
(262, 1, 59, 3, 5, 1, '14:00:00', '14:45:00', 1, 0, 108, '487', NULL),
(263, 1, 59, 3, 5, 2, '14:45:00', '15:30:00', 0, 1, 108, '487', NULL),
(264, 1, 59, 3, 4, 3, '15:45:00', '16:30:00', 1, 0, 109, '488', NULL),
(265, 1, 59, 3, 4, 4, '16:30:00', '17:15:00', 0, 1, 109, '488', NULL),
(266, 1, 51, 9, 3, 5, '17:30:00', '18:15:00', 1, 0, 110, '489', NULL),
(267, 1, 51, 9, 3, 6, '18:15:00', '19:00:00', 0, 1, 110, '489', NULL),
(268, 1, 51, 9, 2, 3, '15:45:00', '16:30:00', 1, 0, 111, '490', NULL),
(269, 1, 51, 9, 2, 4, '16:30:00', '17:15:00', 0, 1, 111, '490', NULL),
(270, 1, 61, 12, 1, 1, '14:00:00', '14:45:00', 1, 0, 112, '498', NULL),
(271, 1, 61, 12, 1, 2, '14:45:00', '15:30:00', 0, 1, 112, '498', NULL),
(272, 1, 45, 15, 2, 5, '17:30:00', '18:15:00', 1, 0, 113, '501', NULL),
(273, 1, 35, 15, 2, 5, '17:30:00', '18:15:00', 1, 0, 113, '501', NULL),
(274, 1, 45, 15, 2, 6, '18:15:00', '19:00:00', 0, 1, 113, '501', NULL),
(275, 1, 35, 15, 2, 6, '18:15:00', '19:00:00', 0, 1, 113, '501', NULL),
(276, 1, 22, 16, 4, 1, '14:00:00', '14:45:00', 1, 0, 114, '502', NULL),
(277, 1, 41, 16, 4, 1, '14:00:00', '14:45:00', 1, 0, 114, '502', NULL),
(278, 1, 22, 16, 4, 2, '14:45:00', '15:30:00', 0, 1, 114, '502', NULL),
(279, 1, 41, 16, 4, 2, '14:45:00', '15:30:00', 0, 1, 114, '502', NULL),
(280, 1, 59, 3, 1, 3, '15:45:00', '16:30:00', 1, 0, 115, '506', NULL),
(281, 1, 59, 3, 1, 4, '16:30:00', '17:15:00', 0, 1, 115, '506', NULL),
(282, 1, 59, 3, 2, 3, '15:45:00', '16:30:00', 1, 0, 116, '507', NULL),
(283, 1, 59, 3, 2, 4, '16:30:00', '17:15:00', 0, 1, 116, '507', NULL),
(284, 1, 51, 9, 1, 5, '17:30:00', '18:15:00', 1, 0, 117, '508', NULL),
(285, 1, 51, 9, 1, 6, '18:15:00', '19:00:00', 0, 1, 117, '508', NULL),
(286, 1, 3, 8, 3, 1, '14:00:00', '14:45:00', 1, 0, 118, '510', NULL),
(287, 1, 3, 8, 3, 2, '14:45:00', '15:30:00', 0, 1, 118, '510', NULL),
(288, 1, 3, 8, 5, 1, '14:00:00', '14:45:00', 1, 0, 119, '511', NULL),
(289, 1, 3, 8, 5, 2, '14:45:00', '15:30:00', 0, 1, 119, '511', NULL),
(290, 1, 47, 19, 4, 1, '14:00:00', '14:45:00', 1, 0, 120, '520', NULL),
(291, 1, 47, 19, 4, 2, '14:45:00', '15:30:00', 0, 1, 120, '520', NULL),
(292, 1, 40, 15, 1, 1, '14:00:00', '14:45:00', 1, 0, 121, '523', NULL),
(293, 1, 17, 15, 1, 1, '14:00:00', '14:45:00', 1, 0, 121, '523', NULL),
(294, 1, 40, 15, 1, 2, '14:45:00', '15:30:00', 0, 1, 121, '523', NULL),
(295, 1, 17, 15, 1, 2, '14:45:00', '15:30:00', 0, 1, 121, '523', NULL),
(296, 1, 31, 16, 2, 5, '17:30:00', '18:15:00', 1, 0, 122, '524', NULL),
(297, 1, 41, 16, 2, 5, '17:30:00', '18:15:00', 1, 0, 122, '524', NULL),
(298, 1, 63, 16, 2, 5, '17:30:00', '18:15:00', 1, 0, 122, '524', NULL),
(299, 1, 31, 16, 2, 6, '18:15:00', '19:00:00', 0, 1, 122, '524', NULL),
(300, 1, 41, 16, 2, 6, '18:15:00', '19:00:00', 0, 1, 122, '524', NULL),
(301, 1, 63, 16, 2, 6, '18:15:00', '19:00:00', 0, 1, 122, '524', NULL),
(302, 1, 32, 20, 4, 3, '15:45:00', '16:30:00', 1, 0, 123, '525', NULL),
(303, 1, 32, 20, 4, 4, '16:30:00', '17:15:00', 0, 1, 123, '525', NULL),
(304, 1, 56, 3, 2, 5, '17:30:00', '18:15:00', 1, 0, 124, '528', NULL),
(305, 1, 56, 3, 2, 6, '18:15:00', '19:00:00', 0, 1, 124, '528', NULL),
(306, 1, 56, 3, 5, 1, '14:00:00', '14:45:00', 1, 0, 125, '529', NULL),
(307, 1, 56, 3, 5, 2, '14:45:00', '15:30:00', 0, 1, 125, '529', NULL),
(308, 1, 62, 8, 4, 3, '15:45:00', '16:30:00', 1, 0, 126, '531', NULL),
(309, 1, 62, 8, 4, 4, '16:30:00', '17:15:00', 0, 1, 126, '531', NULL),
(310, 1, 62, 8, 5, 3, '15:45:00', '16:30:00', 1, 0, 127, '532', NULL),
(311, 1, 62, 8, 5, 4, '16:30:00', '17:15:00', 0, 1, 127, '532', NULL),
(312, 1, 35, 15, 1, 5, '17:30:00', '18:15:00', 1, 0, 128, '542', NULL),
(313, 1, 17, 15, 1, 5, '17:30:00', '18:15:00', 1, 0, 128, '542', NULL),
(314, 1, 35, 15, 1, 6, '18:15:00', '19:00:00', 0, 1, 128, '542', NULL),
(315, 1, 17, 15, 1, 6, '18:15:00', '19:00:00', 0, 1, 128, '542', NULL),
(316, 1, 63, 16, 3, 1, '14:00:00', '14:45:00', 1, 0, 129, '543', NULL),
(317, 1, 40, 16, 3, 1, '14:00:00', '14:45:00', 1, 0, 129, '543', NULL),
(318, 1, 63, 16, 3, 2, '14:45:00', '15:30:00', 0, 1, 129, '543', NULL),
(319, 1, 40, 16, 3, 2, '14:45:00', '15:30:00', 0, 1, 129, '543', NULL),
(320, 1, 48, 9, 1, 3, '15:45:00', '16:30:00', 1, 0, 130, '544', NULL),
(321, 1, 48, 9, 1, 4, '16:30:00', '17:15:00', 0, 1, 130, '544', NULL),
(322, 1, 48, 9, 3, 3, '15:45:00', '16:30:00', 1, 0, 131, '545', NULL),
(323, 1, 48, 9, 3, 4, '16:30:00', '17:15:00', 0, 1, 131, '545', NULL),
(324, 1, 40, 21, 4, 1, '14:00:00', '14:45:00', 1, 0, 132, '550', NULL),
(325, 1, 40, 21, 4, 2, '14:45:00', '15:30:00', 0, 1, 132, '550', NULL),
(326, 1, 49, 21, 4, 5, '17:30:00', '18:15:00', 1, 0, 133, '553', NULL),
(327, 1, 49, 21, 4, 6, '18:15:00', '19:00:00', 0, 1, 133, '553', NULL),
(328, 1, 32, 21, 4, 5, '17:30:00', '18:15:00', 1, 0, 134, '556', NULL),
(329, 1, 32, 21, 4, 6, '18:15:00', '19:00:00', 0, 1, 134, '556', NULL),
(330, 1, 50, 18, 3, 3, '15:45:00', '16:30:00', 1, 0, 135, '594', NULL),
(331, 1, 40, 18, 3, 3, '15:45:00', '16:30:00', 1, 0, 135, '594', NULL),
(332, 1, 50, 18, 3, 4, '16:30:00', '17:15:00', 0, 1, 135, '594', NULL),
(333, 1, 40, 18, 3, 4, '16:30:00', '17:15:00', 0, 1, 135, '594', NULL),
(334, 1, 41, 18, 2, 1, '14:00:00', '14:45:00', 1, 0, 136, '595', NULL),
(335, 1, 41, 18, 2, 2, '14:45:00', '15:30:00', 0, 1, 136, '595', NULL),
(336, 1, 42, 22, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '604', NULL),
(337, 1, 42, 22, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '605', NULL),
(338, 1, 42, 22, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '606', NULL),
(339, 1, 45, 23, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '616', NULL),
(340, 1, 45, 23, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '617', NULL),
(341, 1, 45, 23, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '618', NULL),
(342, 1, 11, 24, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '624', NULL),
(343, 1, 11, 24, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '625', NULL),
(344, 1, 11, 24, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '626', NULL),
(345, 1, 57, 25, 3, 5, '17:30:00', '18:15:00', 0, 0, NULL, '629', NULL),
(346, 1, 38, 7, 4, 3, '15:45:00', '16:30:00', 0, 0, NULL, '630', NULL),
(347, 1, 49, 21, 2, 5, '17:30:00', '18:15:00', 1, 0, 137, '640', NULL),
(348, 1, 49, 21, 2, 6, '18:15:00', '19:00:00', 0, 1, 137, '640', NULL),
(349, 1, 19, 3, 1, 1, '14:00:00', '14:45:00', 1, 0, 138, '692', NULL),
(350, 1, 19, 3, 1, 2, '14:45:00', '15:30:00', 0, 1, 138, '692', NULL),
(351, 1, 19, 3, 4, 1, '14:00:00', '14:45:00', 1, 0, 139, '693', NULL),
(352, 1, 19, 3, 4, 2, '14:45:00', '15:30:00', 0, 1, 139, '693', NULL),
(353, 1, 19, 3, 3, 2, '14:45:00', '15:30:00', 0, 0, NULL, '694', NULL),
(354, 1, 19, 3, 3, 3, '15:45:00', '16:30:00', 0, 0, NULL, '694', NULL),
(355, 1, 69, 7, 1, 5, '17:30:00', '18:15:00', 0, 0, NULL, '695', NULL),
(356, 1, 69, 8, 4, 4, '16:30:00', '17:15:00', 0, 0, NULL, '696', NULL),
(357, 1, 69, 8, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '696', NULL),
(358, 1, 69, 8, 5, 3, '15:45:00', '16:30:00', 1, 0, 140, '697', NULL),
(359, 1, 69, 8, 5, 4, '16:30:00', '17:15:00', 0, 1, 140, '697', NULL),
(360, 1, 69, 8, 2, 3, '15:45:00', '16:30:00', 1, 0, 141, '698', NULL),
(361, 1, 69, 8, 2, 4, '16:30:00', '17:15:00', 0, 1, 141, '698', NULL),
(362, 1, 45, 4, 1, 3, '15:45:00', '16:30:00', 1, 0, 142, '699', NULL),
(363, 1, 45, 4, 1, 4, '16:30:00', '17:15:00', 0, 1, 142, '699', NULL),
(364, 1, 45, 4, 2, 2, '14:45:00', '15:30:00', 0, 0, NULL, '700', NULL),
(365, 1, 5, 5, 3, 1, '14:00:00', '14:45:00', 1, 0, 143, '703', NULL),
(366, 1, 32, 6, 2, 1, '14:00:00', '14:45:00', 1, 0, 4, '704', NULL),
(367, 1, 31, 8, 5, 4, '16:30:00', '17:15:00', 0, 0, NULL, '706', NULL),
(368, 1, 57, 8, 5, 4, '16:30:00', '17:15:00', 0, 0, NULL, '706', NULL),
(369, 1, 31, 8, 5, 5, '17:30:00', '18:15:00', 0, 0, NULL, '706', NULL),
(370, 1, 57, 8, 5, 5, '17:30:00', '18:15:00', 0, 0, NULL, '706', NULL),
(371, 1, 31, 8, 3, 1, '14:00:00', '14:45:00', 1, 0, 144, '707', NULL),
(372, 1, 57, 8, 3, 1, '14:00:00', '14:45:00', 1, 0, 144, '707', NULL),
(373, 1, 31, 8, 3, 2, '14:45:00', '15:30:00', 0, 1, 144, '707', NULL),
(374, 1, 57, 8, 3, 2, '14:45:00', '15:30:00', 0, 1, 144, '707', NULL),
(375, 1, 31, 8, 4, 1, '14:00:00', '14:45:00', 1, 0, 145, '708', NULL),
(376, 1, 57, 8, 4, 1, '14:00:00', '14:45:00', 1, 0, 145, '708', NULL),
(377, 1, 31, 8, 4, 2, '14:45:00', '15:30:00', 0, 1, 145, '708', NULL),
(378, 1, 57, 8, 4, 2, '14:45:00', '15:30:00', 0, 1, 145, '708', NULL),
(379, 1, 57, 8, 5, 3, '15:45:00', '16:30:00', 0, 0, NULL, '709', NULL),
(380, 1, 57, 8, 3, 3, '15:45:00', '16:30:00', 1, 0, 146, '710', NULL),
(381, 1, 57, 8, 3, 4, '16:30:00', '17:15:00', 0, 1, 146, '710', NULL),
(382, 1, 57, 8, 1, 3, '15:45:00', '16:30:00', 1, 0, 147, '712', NULL),
(383, 1, 57, 8, 1, 4, '16:30:00', '17:15:00', 0, 1, 147, '712', NULL),
(384, 1, 34, 8, 3, 4, '16:30:00', '17:15:00', 0, 0, NULL, '715', NULL),
(385, 1, 34, 8, 3, 5, '17:30:00', '18:15:00', 0, 0, NULL, '715', NULL),
(386, 1, 20, 25, 1, 3, '15:45:00', '16:30:00', 0, 0, NULL, '716', NULL),
(387, 1, 18, 26, 1, 5, '17:30:00', '18:15:00', 1, 0, 148, '718', NULL),
(388, 1, 18, 26, 1, 6, '18:15:00', '19:00:00', 0, 1, 148, '718', NULL),
(389, 1, 42, 27, 1, 1, '14:00:00', '14:45:00', 1, 0, 149, '723', NULL),
(390, 1, 42, 27, 1, 2, '14:45:00', '15:30:00', 0, 1, 149, '723', NULL),
(391, 1, 4, 26, 2, 3, '15:45:00', '16:30:00', 1, 0, 150, '724', NULL),
(392, 1, 4, 26, 2, 4, '16:30:00', '17:15:00', 0, 1, 150, '724', NULL),
(393, 1, 15, 3, 3, 3, '15:45:00', '16:30:00', 1, 0, 151, '729', NULL),
(394, 1, 15, 3, 3, 4, '16:30:00', '17:15:00', 0, 1, 151, '729', NULL),
(395, 1, 15, 3, 2, 3, '15:45:00', '16:30:00', 1, 0, 152, '730', NULL),
(396, 1, 15, 3, 2, 4, '16:30:00', '17:15:00', 0, 1, 152, '730', NULL),
(397, 1, 35, 14, 4, 3, '15:45:00', '16:30:00', 1, 0, 153, '733', NULL),
(398, 1, 35, 14, 4, 4, '16:30:00', '17:15:00', 0, 1, 153, '733', NULL),
(399, 1, 51, 9, 2, 5, '17:30:00', '18:15:00', 1, 0, 154, '735', NULL),
(400, 1, 51, 9, 2, 6, '18:15:00', '19:00:00', 0, 1, 154, '735', NULL),
(401, 1, 51, 9, 3, 3, '15:45:00', '16:30:00', 1, 0, 155, '736', NULL),
(402, 1, 51, 9, 3, 4, '16:30:00', '17:15:00', 0, 1, 155, '736', NULL),
(403, 1, 49, 9, 3, 1, '14:00:00', '14:45:00', 1, 0, 156, '737', NULL),
(404, 1, 49, 9, 3, 2, '14:45:00', '15:30:00', 0, 1, 156, '737', NULL),
(405, 1, 5, 14, 1, 1, '14:00:00', '14:45:00', 1, 0, 157, '739', NULL),
(406, 1, 5, 14, 1, 2, '14:45:00', '15:30:00', 0, 1, 157, '739', NULL),
(407, 1, 6, 19, 1, 5, '17:30:00', '18:15:00', 1, 0, 158, '741', NULL),
(408, 1, 45, 19, 1, 5, '17:30:00', '18:15:00', 1, 0, 158, '741', NULL),
(409, 1, 6, 19, 1, 6, '18:15:00', '19:00:00', 0, 1, 158, '741', NULL),
(410, 1, 45, 19, 1, 6, '18:15:00', '19:00:00', 0, 1, 158, '741', NULL),
(411, 1, 35, 3, 3, 1, '14:00:00', '14:45:00', 1, 0, 159, '742', NULL),
(412, 1, 35, 3, 3, 2, '14:45:00', '15:30:00', 0, 1, 159, '742', NULL),
(413, 1, 51, 9, 1, 3, '15:45:00', '16:30:00', 1, 0, 160, '744', NULL),
(414, 1, 51, 9, 1, 4, '16:30:00', '17:15:00', 0, 1, 160, '744', NULL),
(415, 1, 14, 8, 4, 5, '17:30:00', '18:15:00', 1, 0, 161, '746', NULL),
(416, 1, 14, 8, 4, 6, '18:15:00', '19:00:00', 0, 1, 161, '746', NULL),
(417, 1, 14, 8, 2, 5, '17:30:00', '18:15:00', 1, 0, 162, '747', NULL),
(418, 1, 14, 8, 2, 6, '18:15:00', '19:00:00', 0, 1, 162, '747', NULL),
(419, 1, 5, 14, 4, 1, '14:00:00', '14:45:00', 1, 0, 163, '755', NULL),
(420, 1, 5, 14, 4, 2, '14:45:00', '15:30:00', 0, 1, 163, '755', NULL),
(421, 1, 5, 12, 2, 1, '14:00:00', '14:45:00', 1, 0, 164, '756', NULL),
(422, 1, 5, 12, 2, 2, '14:45:00', '15:30:00', 0, 1, 164, '756', NULL),
(423, 1, 47, 15, 3, 5, '17:30:00', '18:15:00', 1, 0, 165, '758', NULL),
(424, 1, 35, 15, 3, 5, '17:30:00', '18:15:00', 1, 0, 165, '758', NULL),
(425, 1, 47, 15, 3, 6, '18:15:00', '19:00:00', 0, 1, 165, '758', NULL),
(426, 1, 35, 15, 3, 6, '18:15:00', '19:00:00', 0, 1, 165, '758', NULL),
(427, 1, 41, 16, 3, 3, '15:45:00', '16:30:00', 1, 0, 166, '759', NULL),
(428, 1, 35, 16, 3, 3, '15:45:00', '16:30:00', 1, 0, 166, '759', NULL),
(429, 1, 41, 16, 3, 4, '16:30:00', '17:15:00', 0, 1, 166, '759', NULL),
(430, 1, 35, 16, 3, 4, '16:30:00', '17:15:00', 0, 1, 166, '759', NULL),
(431, 1, 12, 18, 1, 1, '14:00:00', '14:45:00', 1, 0, 167, '760', NULL),
(432, 1, 12, 18, 1, 2, '14:45:00', '15:30:00', 0, 1, 167, '760', NULL),
(433, 1, 14, 8, 1, 5, '17:30:00', '18:15:00', 1, 0, 168, '765', NULL),
(434, 1, 14, 8, 1, 6, '18:15:00', '19:00:00', 0, 1, 168, '765', NULL),
(435, 1, 14, 8, 3, 3, '15:45:00', '16:30:00', 1, 0, 169, '766', NULL),
(436, 1, 14, 8, 3, 4, '16:30:00', '17:15:00', 0, 1, 169, '766', NULL),
(437, 1, 15, 14, 1, 3, '15:45:00', '16:30:00', 1, 0, 170, '770', NULL),
(438, 1, 15, 14, 1, 4, '16:30:00', '17:15:00', 0, 1, 170, '770', NULL),
(439, 1, 49, 3, 4, 1, '14:00:00', '14:45:00', 1, 0, 171, '773', NULL),
(440, 1, 49, 3, 4, 2, '14:45:00', '15:30:00', 0, 1, 171, '773', NULL),
(441, 1, 49, 3, 5, 1, '14:00:00', '14:45:00', 1, 0, 172, '774', NULL),
(442, 1, 49, 3, 5, 2, '14:45:00', '15:30:00', 0, 1, 172, '774', NULL),
(443, 1, 23, 9, 2, 5, '17:30:00', '18:15:00', 1, 0, 173, '775', NULL),
(444, 1, 23, 9, 2, 6, '18:15:00', '19:00:00', 0, 1, 173, '775', NULL),
(445, 1, 62, 8, 3, 3, '15:45:00', '16:30:00', 1, 0, 174, '777', NULL),
(446, 1, 62, 8, 3, 4, '16:30:00', '17:15:00', 0, 1, 174, '777', NULL),
(447, 1, 62, 8, 1, 5, '17:30:00', '18:15:00', 1, 0, 175, '778', NULL),
(448, 1, 62, 8, 1, 6, '18:15:00', '19:00:00', 0, 1, 175, '778', NULL),
(449, 1, 5, 14, 1, 3, '15:45:00', '16:30:00', 1, 0, 176, '785', NULL),
(450, 1, 5, 14, 1, 4, '16:30:00', '17:15:00', 0, 1, 176, '785', NULL),
(451, 1, 61, 12, 2, 3, '15:45:00', '16:30:00', 1, 0, 177, '786', NULL),
(452, 1, 61, 12, 2, 4, '16:30:00', '17:15:00', 0, 1, 177, '786', NULL),
(453, 1, 43, 21, 5, 3, '15:45:00', '16:30:00', 1, 0, 178, '787', NULL),
(454, 1, 43, 21, 5, 4, '16:30:00', '17:15:00', 0, 1, 178, '787', NULL),
(455, 1, 22, 15, 4, 3, '15:45:00', '16:30:00', 1, 0, 179, '788', NULL),
(456, 1, 45, 15, 4, 3, '15:45:00', '16:30:00', 1, 0, 179, '788', NULL),
(457, 1, 22, 15, 4, 4, '16:30:00', '17:15:00', 0, 1, 179, '788', NULL),
(458, 1, 45, 15, 4, 4, '16:30:00', '17:15:00', 0, 1, 179, '788', NULL),
(459, 1, 41, 16, 3, 5, '17:30:00', '18:15:00', 1, 0, 180, '789', NULL),
(460, 1, 31, 16, 3, 5, '17:30:00', '18:15:00', 1, 0, 180, '789', NULL),
(461, 1, 63, 16, 3, 5, '17:30:00', '18:15:00', 1, 0, 180, '789', NULL),
(462, 1, 41, 16, 3, 6, '18:15:00', '19:00:00', 0, 1, 180, '789', NULL),
(463, 1, 31, 16, 3, 6, '18:15:00', '19:00:00', 0, 1, 180, '789', NULL),
(464, 1, 63, 16, 3, 6, '18:15:00', '19:00:00', 0, 1, 180, '789', NULL),
(465, 1, 32, 20, 2, 3, '15:45:00', '16:30:00', 1, 0, 181, '795', NULL),
(466, 1, 32, 20, 2, 4, '16:30:00', '17:15:00', 0, 1, 181, '795', NULL),
(467, 1, 41, 28, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '824', NULL),
(468, 1, 41, 28, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '825', NULL),
(469, 1, 41, 28, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '826', NULL),
(470, 1, 2, 3, 1, 1, '14:00:00', '14:45:00', 1, 0, 182, '828', NULL),
(471, 1, 2, 3, 1, 2, '14:45:00', '15:30:00', 0, 1, 182, '828', NULL),
(472, 1, 2, 3, 3, 1, '14:00:00', '14:45:00', 1, 0, 183, '829', NULL),
(473, 1, 2, 3, 3, 2, '14:45:00', '15:30:00', 0, 1, 183, '829', NULL),
(474, 1, 52, 9, 1, 5, '17:30:00', '18:15:00', 1, 0, 184, '830', NULL),
(475, 1, 52, 9, 1, 6, '18:15:00', '19:00:00', 0, 1, 184, '830', NULL),
(476, 1, 16, 8, 1, 3, '15:45:00', '16:30:00', 1, 0, 185, '832', NULL),
(477, 1, 39, 8, 1, 3, '15:45:00', '16:30:00', 1, 0, 185, '832', NULL),
(478, 1, 16, 8, 1, 4, '16:30:00', '17:15:00', 0, 1, 185, '832', NULL),
(479, 1, 39, 8, 1, 4, '16:30:00', '17:15:00', 0, 1, 185, '832', NULL),
(480, 1, 16, 8, 2, 5, '17:30:00', '18:15:00', 1, 0, 186, '833', NULL),
(481, 1, 39, 8, 2, 5, '17:30:00', '18:15:00', 1, 0, 186, '833', NULL),
(482, 1, 16, 8, 2, 6, '18:15:00', '19:00:00', 0, 1, 186, '833', NULL),
(483, 1, 39, 8, 2, 6, '18:15:00', '19:00:00', 0, 1, 186, '833', NULL),
(484, 1, 35, 15, 5, 3, '15:45:00', '16:30:00', 1, 0, 187, '840', NULL),
(485, 1, 40, 15, 5, 3, '15:45:00', '16:30:00', 1, 0, 187, '840', NULL),
(486, 1, 35, 15, 5, 4, '16:30:00', '17:15:00', 0, 1, 187, '840', NULL),
(487, 1, 40, 15, 5, 4, '16:30:00', '17:15:00', 0, 1, 187, '840', NULL),
(488, 1, 53, 16, 4, 3, '15:45:00', '16:30:00', 1, 0, 188, '841', NULL),
(489, 1, 13, 16, 4, 3, '15:45:00', '16:30:00', 1, 0, 188, '841', NULL),
(490, 1, 53, 16, 4, 4, '16:30:00', '17:15:00', 0, 1, 188, '841', NULL),
(491, 1, 13, 16, 4, 4, '16:30:00', '17:15:00', 0, 1, 188, '841', NULL),
(492, 1, 32, 20, 3, 3, '15:45:00', '16:30:00', 1, 0, 189, '844', NULL),
(493, 1, 32, 20, 3, 4, '16:30:00', '17:15:00', 0, 1, 189, '844', NULL),
(494, 1, 35, 14, 1, 1, '14:00:00', '14:45:00', 1, 0, 190, '850', NULL),
(495, 1, 35, 14, 1, 2, '14:45:00', '15:30:00', 0, 1, 190, '850', NULL),
(496, 1, 56, 12, 4, 5, '17:30:00', '18:15:00', 1, 0, 191, '851', NULL),
(497, 1, 56, 12, 4, 6, '18:15:00', '19:00:00', 0, 1, 191, '851', NULL),
(498, 1, 5, 5, 3, 2, '14:45:00', '15:30:00', 0, 1, 143, '855', NULL),
(499, 1, 67, 29, 5, 3, '15:45:00', '16:30:00', 1, 0, 192, '856', NULL),
(500, 1, 67, 29, 5, 4, '16:30:00', '17:15:00', 0, 1, 192, '856', NULL),
(501, 1, 37, 17, 4, 5, '17:30:00', '18:15:00', 1, 0, 193, '861', NULL),
(502, 1, 7, 17, 4, 5, '17:30:00', '18:15:00', 1, 0, 193, '861', NULL),
(503, 1, 37, 17, 4, 6, '18:15:00', '19:00:00', 0, 1, 193, '861', NULL),
(504, 1, 7, 17, 4, 6, '18:15:00', '19:00:00', 0, 1, 193, '861', NULL),
(505, 1, 37, 17, 3, 5, '17:30:00', '18:15:00', 1, 0, 194, '862', NULL),
(506, 1, 7, 17, 3, 5, '17:30:00', '18:15:00', 1, 0, 194, '862', NULL),
(507, 1, 37, 17, 3, 6, '18:15:00', '19:00:00', 0, 1, 194, '862', NULL),
(508, 1, 7, 17, 3, 6, '18:15:00', '19:00:00', 0, 1, 194, '862', NULL),
(509, 1, 10, 30, 1, 5, '17:30:00', '18:15:00', 1, 0, 195, '866', NULL),
(510, 1, 10, 30, 1, 6, '18:15:00', '19:00:00', 0, 1, 195, '866', NULL),
(511, 1, 10, 31, 3, 5, '17:30:00', '18:15:00', 0, 0, NULL, '867', NULL),
(512, 1, 48, 32, 2, 3, '15:45:00', '16:30:00', 1, 0, 196, '868', NULL),
(513, 1, 48, 32, 2, 4, '16:30:00', '17:15:00', 0, 1, 196, '868', NULL),
(514, 1, 48, 33, 3, 2, '14:45:00', '15:30:00', 0, 0, NULL, '869', NULL),
(515, 1, 62, 34, 1, 3, '15:45:00', '16:30:00', 1, 0, 197, '870', NULL),
(516, 1, 62, 34, 1, 4, '16:30:00', '17:15:00', 0, 1, 197, '870', NULL),
(517, 1, 62, 35, 4, 5, '17:30:00', '18:15:00', 0, 0, NULL, '871', NULL),
(518, 1, 37, 36, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '872', NULL),
(519, 1, 37, 36, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '873', NULL),
(520, 1, 37, 36, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '874', NULL),
(521, 1, 55, 37, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '875', NULL),
(522, 1, 55, 37, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '876', NULL),
(523, 1, 55, 37, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '877', NULL),
(524, 1, 67, 38, 3, 6, '18:15:00', '19:00:00', 0, 0, NULL, '878', NULL),
(525, 1, 31, 39, 3, 3, '15:45:00', '16:30:00', 1, 0, 198, '879', NULL),
(526, 1, 63, 39, 3, 3, '15:45:00', '16:30:00', 1, 0, 198, '879', NULL),
(527, 1, 31, 39, 3, 4, '16:30:00', '17:15:00', 0, 1, 198, '879', NULL),
(528, 1, 63, 39, 3, 4, '16:30:00', '17:15:00', 0, 1, 198, '879', NULL),
(529, 1, 40, 40, 5, 5, '17:30:00', '18:15:00', 1, 0, 199, '880', NULL),
(530, 1, 40, 40, 5, 6, '18:15:00', '19:00:00', 0, 1, 199, '880', NULL),
(531, 1, 40, 41, 5, 2, '14:45:00', '15:30:00', 0, 0, NULL, '881', NULL),
(532, 1, 40, 42, 4, 3, '15:45:00', '16:30:00', 1, 0, 200, '882', NULL),
(533, 1, 40, 42, 4, 4, '16:30:00', '17:15:00', 0, 1, 200, '882', NULL),
(534, 1, 26, 43, 4, 1, '14:00:00', '14:45:00', 1, 0, 201, '884', NULL),
(535, 1, 26, 43, 4, 2, '14:45:00', '15:30:00', 0, 1, 201, '884', NULL),
(536, 1, 29, 44, 2, 5, '17:30:00', '18:15:00', 1, 0, 202, '885', NULL),
(537, 1, 29, 44, 2, 6, '18:15:00', '19:00:00', 0, 1, 202, '885', NULL),
(538, 1, 9, 45, 3, 3, '15:45:00', '16:30:00', 1, 0, 203, '886', NULL),
(539, 1, 9, 45, 3, 4, '16:30:00', '17:15:00', 0, 1, 203, '886', NULL),
(540, 1, 9, 46, 1, 3, '15:45:00', '16:30:00', 0, 0, NULL, '887', NULL),
(541, 1, 4, 47, 3, 5, '17:30:00', '18:15:00', 1, 0, 204, '888', NULL),
(542, 1, 4, 47, 3, 6, '18:15:00', '19:00:00', 0, 1, 204, '888', NULL),
(543, 1, 4, 48, 1, 5, '17:30:00', '18:15:00', 0, 0, NULL, '889', NULL),
(544, 1, 72, 49, 2, 5, '17:30:00', '18:15:00', 1, 0, 205, '890', NULL),
(545, 1, 72, 49, 2, 6, '18:15:00', '19:00:00', 0, 1, 205, '890', NULL),
(546, 1, 72, 50, 4, 2, '14:45:00', '15:30:00', 0, 0, NULL, '891', NULL),
(547, 1, 7, 51, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '892', NULL),
(548, 1, 7, 51, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '893', NULL),
(549, 1, 7, 51, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '894', NULL),
(550, 1, 66, 52, 4, 3, '15:45:00', '16:30:00', 1, 0, 206, '895', NULL),
(551, 1, 66, 52, 4, 4, '16:30:00', '17:15:00', 0, 1, 206, '895', NULL),
(552, 1, 66, 53, 1, 4, '16:30:00', '17:15:00', 0, 0, NULL, '896', NULL),
(553, 1, 53, 54, 2, 1, '14:00:00', '14:45:00', 1, 0, 207, '897', NULL),
(554, 1, 53, 54, 2, 2, '14:45:00', '15:30:00', 0, 1, 207, '897', NULL),
(555, 1, 10, 55, 5, 5, '17:30:00', '18:15:00', 1, 0, 208, '898', NULL),
(556, 1, 10, 55, 5, 6, '18:15:00', '19:00:00', 0, 1, 208, '898', NULL),
(557, 1, 60, 56, 5, 3, '15:45:00', '16:30:00', 1, 0, 209, '899', NULL),
(558, 1, 60, 56, 5, 4, '16:30:00', '17:15:00', 0, 1, 209, '899', NULL),
(559, 1, 72, 57, 2, 3, '15:45:00', '16:30:00', 1, 0, 210, '900', NULL),
(560, 1, 72, 57, 2, 4, '16:30:00', '17:15:00', 0, 1, 210, '900', NULL),
(561, 1, 26, 58, 4, 5, '17:30:00', '18:15:00', 1, 0, 211, '901', NULL),
(562, 1, 26, 58, 4, 6, '18:15:00', '19:00:00', 0, 1, 211, '901', NULL),
(563, 1, 61, 59, 3, 1, '14:00:00', '14:45:00', 1, 0, 212, '902', NULL),
(564, 1, 61, 59, 3, 2, '14:45:00', '15:30:00', 0, 1, 212, '902', NULL),
(565, 1, 61, 22, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '935', NULL),
(566, 1, 61, 22, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '936', NULL),
(567, 1, 61, 22, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '937', NULL),
(568, 1, 71, 2, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '939', NULL),
(569, 1, 71, 2, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '940', NULL),
(570, 1, 71, 2, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '941', NULL),
(571, 1, 23, 60, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '943', NULL),
(572, 1, 23, 60, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '944', NULL),
(573, 1, 23, 60, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '945', NULL),
(574, 1, 51, 61, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '947', NULL),
(575, 1, 51, 61, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '948', NULL),
(576, 1, 51, 61, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '949', NULL),
(577, 1, 10, 62, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '951', NULL),
(578, 1, 10, 62, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '952', NULL),
(579, 1, 10, 62, 1, 7, '19:10:00', '19:55:00', 0, 0, NULL, '953', NULL),
(580, 1, 40, 63, 4, 7, '19:10:00', '19:55:00', 0, 0, NULL, '955', NULL),
(581, 1, 40, 63, 2, 7, '19:10:00', '19:55:00', 0, 0, NULL, '956', NULL),
(582, 1, 40, 63, 3, 7, '19:10:00', '19:55:00', 0, 0, NULL, '957', NULL),
(583, 1, 69, 9, 2, 5, '17:30:00', '18:15:00', 0, 0, NULL, '959', NULL),
(584, 1, 69, 9, 5, 5, '17:30:00', '18:15:00', 0, 0, NULL, '960', NULL),
(585, 1, 33, 3, 2, 5, '17:30:00', '18:15:00', 0, 0, NULL, '963', NULL),
(586, 1, 33, 3, 4, 4, '16:30:00', '17:15:00', 0, 0, NULL, '964', NULL),
(587, 1, 34, 8, 1, 4, '16:30:00', '17:15:00', 0, 0, NULL, '965', NULL),
(588, 1, 34, 8, 5, 3, '15:45:00', '16:30:00', 0, 0, NULL, '966', NULL),
(589, 1, 50, 18, 4, 5, '17:30:00', '18:15:00', 1, 0, 213, '967', NULL),
(590, 1, 53, 18, 4, 5, '17:30:00', '18:15:00', 1, 0, 213, '967', NULL),
(591, 1, 50, 18, 4, 6, '18:15:00', '19:00:00', 0, 1, 213, '967', NULL),
(592, 1, 53, 18, 4, 6, '18:15:00', '19:00:00', 0, 1, 213, '967', NULL),
(593, 1, 35, 18, 2, 3, '15:45:00', '16:30:00', 1, 0, 214, '968', NULL),
(594, 1, 41, 18, 2, 3, '15:45:00', '16:30:00', 1, 0, 214, '968', NULL),
(595, 1, 35, 18, 2, 4, '16:30:00', '17:15:00', 0, 1, 214, '968', NULL),
(596, 1, 41, 18, 2, 4, '16:30:00', '17:15:00', 0, 1, 214, '968', NULL),
(597, 1, 40, 18, 2, 3, '15:45:00', '16:30:00', 1, 0, 215, '970', NULL),
(598, 1, 17, 18, 2, 3, '15:45:00', '16:30:00', 1, 0, 215, '970', NULL),
(599, 1, 40, 18, 2, 4, '16:30:00', '17:15:00', 0, 1, 215, '970', NULL),
(600, 1, 17, 18, 2, 4, '16:30:00', '17:15:00', 0, 1, 215, '970', NULL),
(601, 1, 21, 4, 5, 3, '15:45:00', '16:30:00', 1, 0, 13, '971', NULL),
(602, 1, 21, 4, 1, 5, '17:30:00', '18:15:00', 0, 0, NULL, '972', NULL),
(603, 1, 21, 4, 2, 5, '17:30:00', '18:15:00', 0, 0, NULL, '973', NULL),
(604, 1, 70, 64, 1, 1, '14:00:00', '14:45:00', 1, 0, 216, '974', NULL),
(605, 1, 70, 64, 1, 2, '14:45:00', '15:30:00', 0, 1, 216, '974', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_entry_classes`
--

CREATE TABLE `timetable_entry_classes` (
  `entry_id` int UNSIGNED NOT NULL,
  `class_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Klassen pro Stundenplaneintrag (n:m)';

--
-- Dumping data for table `timetable_entry_classes`
--

INSERT INTO `timetable_entry_classes` (`entry_id`, `class_id`) VALUES
(10, 4),
(11, 4),
(12, 4),
(13, 4),
(14, 4),
(15, 4),
(16, 4),
(17, 4),
(18, 4),
(19, 4),
(20, 4),
(379, 4),
(380, 4),
(381, 4),
(382, 4),
(383, 4),
(21, 5),
(22, 5),
(23, 5),
(24, 5),
(25, 5),
(26, 5),
(27, 5),
(28, 5),
(29, 5),
(30, 5),
(31, 5),
(384, 5),
(385, 5),
(386, 5),
(587, 5),
(588, 5),
(32, 6),
(33, 6),
(34, 6),
(35, 6),
(36, 6),
(37, 6),
(38, 6),
(39, 6),
(40, 6),
(41, 6),
(42, 6),
(43, 6),
(44, 6),
(45, 6),
(94, 6),
(95, 6),
(345, 6),
(498, 6),
(585, 6),
(586, 6),
(46, 7),
(47, 7),
(48, 7),
(49, 7),
(50, 7),
(51, 7),
(52, 7),
(53, 7),
(54, 7),
(55, 7),
(56, 7),
(57, 7),
(58, 7),
(59, 7),
(367, 7),
(368, 7),
(369, 7),
(370, 7),
(371, 7),
(372, 7),
(373, 7),
(374, 7),
(375, 7),
(376, 7),
(377, 7),
(378, 7),
(60, 8),
(61, 8),
(62, 8),
(63, 8),
(64, 8),
(65, 8),
(66, 8),
(67, 8),
(68, 8),
(69, 8),
(70, 8),
(71, 8),
(72, 8),
(73, 8),
(74, 8),
(75, 8),
(346, 8),
(601, 8),
(602, 8),
(603, 8),
(76, 9),
(77, 9),
(78, 9),
(79, 9),
(80, 9),
(81, 9),
(82, 9),
(83, 9),
(84, 9),
(85, 9),
(86, 9),
(87, 9),
(88, 9),
(89, 9),
(90, 9),
(91, 9),
(92, 9),
(93, 9),
(324, 9),
(325, 9),
(387, 9),
(388, 9),
(96, 10),
(97, 10),
(98, 10),
(99, 10),
(100, 10),
(101, 10),
(102, 10),
(103, 10),
(104, 10),
(105, 10),
(106, 10),
(107, 10),
(108, 10),
(109, 10),
(110, 10),
(111, 10),
(112, 10),
(113, 10),
(114, 10),
(115, 10),
(116, 10),
(117, 10),
(389, 10),
(390, 10),
(118, 11),
(119, 11),
(120, 11),
(121, 11),
(122, 11),
(123, 11),
(124, 11),
(125, 11),
(126, 11),
(127, 11),
(128, 11),
(129, 11),
(130, 11),
(131, 11),
(132, 11),
(133, 11),
(134, 11),
(135, 11),
(136, 11),
(137, 11),
(391, 11),
(392, 11),
(138, 12),
(139, 12),
(140, 12),
(141, 12),
(142, 12),
(143, 12),
(144, 12),
(145, 12),
(146, 12),
(147, 12),
(148, 12),
(149, 12),
(150, 12),
(151, 12),
(152, 12),
(153, 12),
(154, 12),
(155, 12),
(156, 12),
(157, 12),
(326, 12),
(327, 12),
(158, 13),
(159, 13),
(160, 13),
(161, 13),
(162, 13),
(163, 13),
(164, 13),
(165, 13),
(166, 13),
(167, 13),
(168, 13),
(169, 13),
(170, 13),
(171, 13),
(172, 13),
(173, 13),
(174, 13),
(175, 13),
(176, 13),
(177, 13),
(589, 13),
(590, 13),
(591, 13),
(592, 13),
(178, 14),
(179, 14),
(180, 14),
(181, 14),
(182, 14),
(183, 14),
(184, 14),
(185, 14),
(186, 14),
(187, 14),
(188, 14),
(189, 14),
(190, 14),
(191, 14),
(192, 14),
(193, 14),
(328, 14),
(329, 14),
(393, 14),
(394, 14),
(395, 14),
(396, 14),
(397, 14),
(398, 14),
(194, 15),
(195, 15),
(196, 15),
(197, 15),
(198, 15),
(199, 15),
(200, 15),
(201, 15),
(202, 15),
(203, 15),
(204, 15),
(205, 15),
(206, 15),
(207, 15),
(208, 15),
(209, 15),
(210, 15),
(211, 15),
(212, 15),
(213, 15),
(399, 15),
(400, 15),
(401, 15),
(402, 15),
(407, 15),
(408, 15),
(409, 15),
(410, 15),
(494, 15),
(495, 15),
(496, 15),
(497, 15),
(200, 16),
(201, 16),
(202, 16),
(203, 16),
(214, 16),
(215, 16),
(216, 16),
(217, 16),
(218, 16),
(219, 16),
(220, 16),
(221, 16),
(222, 16),
(223, 16),
(224, 16),
(225, 16),
(226, 16),
(227, 16),
(228, 16),
(229, 16),
(230, 16),
(231, 16),
(347, 16),
(348, 16),
(403, 16),
(404, 16),
(405, 16),
(406, 16),
(407, 16),
(408, 16),
(409, 16),
(410, 16),
(200, 17),
(201, 17),
(202, 17),
(203, 17),
(407, 17),
(408, 17),
(409, 17),
(410, 17),
(411, 17),
(412, 17),
(413, 17),
(414, 17),
(415, 17),
(416, 17),
(417, 17),
(418, 17),
(419, 17),
(420, 17),
(421, 17),
(422, 17),
(423, 17),
(424, 17),
(425, 17),
(426, 17),
(427, 17),
(428, 17),
(429, 17),
(430, 17),
(431, 17),
(432, 17),
(232, 18),
(233, 18),
(234, 18),
(235, 18),
(236, 18),
(237, 18),
(238, 18),
(239, 18),
(240, 18),
(241, 18),
(242, 18),
(243, 18),
(244, 18),
(245, 18),
(246, 18),
(247, 18),
(248, 18),
(249, 18),
(250, 18),
(251, 18),
(252, 18),
(253, 18),
(254, 18),
(255, 18),
(256, 18),
(257, 18),
(258, 18),
(259, 18),
(260, 18),
(261, 18),
(593, 18),
(594, 18),
(595, 18),
(596, 18),
(244, 19),
(245, 19),
(246, 19),
(247, 19),
(248, 19),
(249, 19),
(250, 19),
(251, 19),
(252, 19),
(253, 19),
(262, 19),
(263, 19),
(264, 19),
(265, 19),
(266, 19),
(267, 19),
(268, 19),
(269, 19),
(270, 19),
(271, 19),
(272, 19),
(273, 19),
(274, 19),
(275, 19),
(276, 19),
(277, 19),
(278, 19),
(279, 19),
(433, 19),
(434, 19),
(435, 19),
(436, 19),
(437, 19),
(438, 19),
(244, 20),
(245, 20),
(246, 20),
(247, 20),
(248, 20),
(249, 20),
(250, 20),
(251, 20),
(252, 20),
(253, 20),
(439, 20),
(440, 20),
(441, 20),
(442, 20),
(443, 20),
(444, 20),
(445, 20),
(446, 20),
(447, 20),
(448, 20),
(449, 20),
(450, 20),
(451, 20),
(452, 20),
(453, 20),
(454, 20),
(455, 20),
(456, 20),
(457, 20),
(458, 20),
(459, 20),
(460, 20),
(461, 20),
(462, 20),
(463, 20),
(464, 20),
(280, 21),
(281, 21),
(282, 21),
(283, 21),
(284, 21),
(285, 21),
(286, 21),
(287, 21),
(288, 21),
(289, 21),
(290, 21),
(291, 21),
(292, 21),
(293, 21),
(294, 21),
(295, 21),
(296, 21),
(297, 21),
(298, 21),
(299, 21),
(300, 21),
(301, 21),
(302, 21),
(303, 21),
(330, 21),
(331, 21),
(332, 21),
(333, 21),
(501, 21),
(502, 21),
(503, 21),
(504, 21),
(505, 21),
(506, 21),
(507, 21),
(508, 21),
(290, 22),
(291, 22),
(304, 22),
(305, 22),
(306, 22),
(307, 22),
(308, 22),
(309, 22),
(310, 22),
(311, 22),
(312, 22),
(313, 22),
(314, 22),
(315, 22),
(316, 22),
(317, 22),
(318, 22),
(319, 22),
(320, 22),
(321, 22),
(322, 22),
(323, 22),
(334, 22),
(335, 22),
(465, 22),
(466, 22),
(501, 22),
(502, 22),
(503, 22),
(504, 22),
(505, 22),
(506, 22),
(507, 22),
(508, 22),
(290, 23),
(291, 23),
(470, 23),
(471, 23),
(472, 23),
(473, 23),
(474, 23),
(475, 23),
(476, 23),
(477, 23),
(478, 23),
(479, 23),
(480, 23),
(481, 23),
(482, 23),
(483, 23),
(484, 23),
(485, 23),
(486, 23),
(487, 23),
(488, 23),
(489, 23),
(490, 23),
(491, 23),
(492, 23),
(493, 23),
(501, 23),
(502, 23),
(503, 23),
(504, 23),
(505, 23),
(506, 23),
(507, 23),
(508, 23),
(597, 23),
(598, 23),
(599, 23),
(600, 23),
(349, 27),
(350, 27),
(351, 27),
(352, 27),
(353, 27),
(354, 27),
(355, 27),
(356, 27),
(357, 27),
(358, 27),
(359, 27),
(360, 27),
(361, 27),
(362, 27),
(363, 27),
(364, 27),
(365, 27),
(366, 27),
(583, 27),
(584, 27),
(499, 30),
(500, 30),
(509, 30),
(510, 30),
(511, 30),
(512, 30),
(513, 30),
(514, 30),
(515, 30),
(516, 30),
(517, 30),
(518, 30),
(519, 30),
(520, 30),
(604, 30),
(605, 30),
(499, 32),
(500, 32),
(509, 32),
(510, 32),
(511, 32),
(512, 32),
(513, 32),
(515, 32),
(516, 32),
(517, 32),
(531, 32),
(532, 32),
(533, 32),
(534, 32),
(535, 32),
(499, 33),
(500, 33),
(509, 33),
(510, 33),
(511, 33),
(512, 33),
(513, 33),
(514, 33),
(515, 33),
(516, 33),
(518, 33),
(519, 33),
(520, 33),
(524, 33),
(525, 33),
(526, 33),
(527, 33),
(528, 33),
(532, 33),
(533, 33),
(536, 33),
(537, 33),
(499, 34),
(500, 34),
(509, 34),
(510, 34),
(511, 34),
(512, 34),
(513, 34),
(514, 34),
(515, 34),
(516, 34),
(518, 34),
(519, 34),
(520, 34),
(524, 34),
(529, 34),
(530, 34),
(532, 34),
(533, 34),
(536, 34),
(537, 34),
(499, 35),
(500, 35),
(509, 35),
(510, 35),
(512, 35),
(513, 35),
(514, 35),
(515, 35),
(516, 35),
(529, 35),
(530, 35),
(531, 35),
(532, 35),
(533, 35),
(536, 35),
(537, 35),
(499, 37),
(500, 37),
(509, 37),
(510, 37),
(512, 37),
(513, 37),
(514, 37),
(515, 37),
(516, 37),
(517, 37),
(524, 37),
(532, 37),
(533, 37),
(534, 37),
(535, 37),
(604, 37),
(605, 37),
(499, 39),
(500, 39),
(509, 39),
(510, 39),
(512, 39),
(513, 39),
(514, 39),
(515, 39),
(516, 39),
(524, 39),
(529, 39),
(530, 39),
(532, 39),
(533, 39),
(536, 39),
(537, 39),
(499, 40),
(500, 40),
(509, 40),
(510, 40),
(512, 40),
(513, 40),
(514, 40),
(515, 40),
(516, 40),
(518, 40),
(519, 40),
(520, 40),
(529, 40),
(530, 40),
(531, 40),
(532, 40),
(533, 40),
(604, 40),
(605, 40),
(499, 41),
(500, 41),
(509, 41),
(510, 41),
(512, 41),
(513, 41),
(514, 41),
(515, 41),
(516, 41),
(517, 41),
(524, 41),
(525, 41),
(526, 41),
(527, 41),
(528, 41),
(532, 41),
(533, 41),
(499, 42),
(500, 42),
(509, 42),
(510, 42),
(512, 42),
(513, 42),
(514, 42),
(515, 42),
(516, 42),
(521, 42),
(522, 42),
(523, 42),
(524, 42),
(525, 42),
(526, 42),
(527, 42),
(528, 42),
(532, 42),
(533, 42),
(604, 42),
(605, 42),
(499, 44),
(500, 44),
(509, 44),
(510, 44),
(511, 44),
(512, 44),
(513, 44),
(515, 44),
(516, 44),
(517, 44),
(524, 44),
(529, 44),
(530, 44),
(536, 44),
(537, 44),
(604, 44),
(605, 44),
(499, 45),
(500, 45),
(509, 45),
(510, 45),
(511, 45),
(512, 45),
(513, 45),
(514, 45),
(515, 45),
(516, 45),
(521, 45),
(522, 45),
(523, 45),
(529, 45),
(530, 45),
(532, 45),
(533, 45),
(534, 45),
(535, 45),
(499, 46),
(500, 46),
(509, 46),
(510, 46),
(512, 46),
(513, 46),
(514, 46),
(515, 46),
(516, 46),
(518, 46),
(519, 46),
(520, 46),
(524, 46),
(529, 46),
(530, 46),
(531, 46),
(532, 46),
(533, 46),
(604, 46),
(605, 46),
(499, 47),
(500, 47),
(509, 47),
(510, 47),
(512, 47),
(513, 47),
(515, 47),
(516, 47),
(517, 47),
(529, 47),
(530, 47),
(531, 47),
(532, 47),
(533, 47),
(536, 47),
(537, 47),
(604, 47),
(605, 47),
(499, 48),
(500, 48),
(509, 48),
(510, 48),
(512, 48),
(513, 48),
(514, 48),
(515, 48),
(516, 48),
(521, 48),
(522, 48),
(523, 48),
(529, 48),
(530, 48),
(531, 48),
(532, 48),
(533, 48),
(536, 48),
(537, 48),
(499, 49),
(500, 49),
(509, 49),
(510, 49),
(512, 49),
(513, 49),
(514, 49),
(515, 49),
(516, 49),
(518, 49),
(519, 49),
(520, 49),
(524, 49),
(529, 49),
(530, 49),
(532, 49),
(533, 49),
(536, 49),
(537, 49),
(499, 50),
(500, 50),
(509, 50),
(510, 50),
(511, 50),
(512, 50),
(513, 50),
(514, 50),
(515, 50),
(516, 50),
(529, 50),
(530, 50),
(531, 50),
(532, 50),
(533, 50),
(534, 50),
(535, 50),
(509, 51),
(510, 51),
(512, 51),
(513, 51),
(514, 51),
(515, 51),
(516, 51),
(521, 51),
(522, 51),
(523, 51),
(529, 51),
(530, 51),
(531, 51),
(532, 51),
(533, 51),
(536, 51),
(537, 51),
(604, 51),
(605, 51),
(512, 52),
(513, 52),
(514, 52),
(515, 52),
(516, 52),
(529, 52),
(530, 52),
(531, 52),
(532, 52),
(533, 52),
(534, 52),
(535, 52),
(604, 52),
(605, 52),
(538, 53),
(539, 53),
(541, 53),
(542, 53),
(543, 53),
(544, 53),
(545, 53),
(546, 53),
(550, 53),
(551, 53),
(552, 53),
(555, 53),
(556, 53),
(557, 53),
(558, 53),
(563, 53),
(564, 53),
(538, 55),
(539, 55),
(540, 55),
(541, 55),
(542, 55),
(543, 55),
(544, 55),
(545, 55),
(546, 55),
(547, 55),
(548, 55),
(549, 55),
(550, 55),
(551, 55),
(555, 55),
(556, 55),
(557, 55),
(558, 55),
(563, 55),
(564, 55),
(538, 56),
(539, 56),
(540, 56),
(541, 56),
(542, 56),
(543, 56),
(544, 56),
(545, 56),
(546, 56),
(547, 56),
(548, 56),
(549, 56),
(550, 56),
(551, 56),
(555, 56),
(556, 56),
(557, 56),
(558, 56),
(563, 56),
(564, 56),
(538, 57),
(539, 57),
(540, 57),
(541, 57),
(542, 57),
(543, 57),
(544, 57),
(545, 57),
(546, 57),
(550, 57),
(551, 57),
(555, 57),
(556, 57),
(557, 57),
(558, 57),
(559, 57),
(560, 57),
(561, 57),
(562, 57),
(538, 59),
(539, 59),
(541, 59),
(542, 59),
(543, 59),
(544, 59),
(545, 59),
(546, 59),
(547, 59),
(548, 59),
(549, 59),
(550, 59),
(551, 59),
(552, 59),
(553, 59),
(554, 59),
(555, 59),
(556, 59),
(557, 59),
(558, 59),
(561, 59),
(562, 59),
(538, 60),
(539, 60),
(541, 60),
(542, 60),
(543, 60),
(544, 60),
(545, 60),
(546, 60),
(550, 60),
(551, 60),
(552, 60),
(553, 60),
(554, 60),
(555, 60),
(556, 60),
(557, 60),
(558, 60),
(538, 61),
(539, 61),
(540, 61),
(541, 61),
(542, 61),
(544, 61),
(545, 61),
(550, 61),
(551, 61),
(552, 61),
(555, 61),
(556, 61),
(561, 61),
(562, 61),
(563, 61),
(564, 61),
(538, 62),
(539, 62),
(541, 62),
(542, 62),
(543, 62),
(544, 62),
(545, 62),
(550, 62),
(551, 62),
(552, 62),
(555, 62),
(556, 62),
(557, 62),
(558, 62),
(563, 62),
(564, 62),
(538, 63),
(539, 63),
(541, 63),
(542, 63),
(543, 63),
(544, 63),
(545, 63),
(546, 63),
(547, 63),
(548, 63),
(549, 63),
(550, 63),
(551, 63),
(552, 63),
(555, 63),
(556, 63),
(557, 63),
(558, 63),
(559, 63),
(560, 63),
(538, 64),
(539, 64),
(540, 64),
(541, 64),
(542, 64),
(543, 64),
(544, 64),
(545, 64),
(546, 64),
(547, 64),
(548, 64),
(549, 64),
(550, 64),
(551, 64),
(555, 64),
(556, 64),
(557, 64),
(558, 64),
(559, 64),
(560, 64),
(538, 65),
(539, 65),
(540, 65),
(541, 65),
(542, 65),
(543, 65),
(544, 65),
(545, 65),
(547, 65),
(548, 65),
(549, 65),
(550, 65),
(551, 65),
(552, 65),
(555, 65),
(556, 65),
(557, 65),
(558, 65),
(561, 65),
(562, 65),
(538, 67),
(539, 67),
(540, 67),
(541, 67),
(542, 67),
(544, 67),
(545, 67),
(547, 67),
(548, 67),
(549, 67),
(550, 67),
(551, 67),
(552, 67),
(555, 67),
(556, 67),
(557, 67),
(558, 67),
(563, 67),
(564, 67),
(538, 69),
(539, 69),
(540, 69),
(541, 69),
(542, 69),
(543, 69),
(544, 69),
(545, 69),
(546, 69),
(547, 69),
(548, 69),
(549, 69),
(550, 69),
(551, 69),
(555, 69),
(556, 69),
(557, 69),
(558, 69),
(559, 69),
(560, 69),
(563, 69),
(564, 69),
(538, 70),
(539, 70),
(540, 70),
(541, 70),
(542, 70),
(543, 70),
(544, 70),
(545, 70),
(547, 70),
(548, 70),
(549, 70),
(550, 70),
(551, 70),
(552, 70),
(555, 70),
(556, 70),
(557, 70),
(558, 70),
(563, 70),
(564, 70),
(538, 71),
(539, 71),
(540, 71),
(541, 71),
(542, 71),
(543, 71),
(544, 71),
(545, 71),
(546, 71),
(547, 71),
(548, 71),
(549, 71),
(550, 71),
(551, 71),
(555, 71),
(556, 71),
(557, 71),
(558, 71),
(563, 71),
(564, 71),
(538, 72),
(539, 72),
(540, 72),
(541, 72),
(542, 72),
(543, 72),
(544, 72),
(545, 72),
(546, 72),
(550, 72),
(551, 72),
(555, 72),
(556, 72),
(557, 72),
(558, 72),
(561, 72),
(562, 72),
(577, 79),
(578, 79),
(579, 79),
(577, 80),
(578, 80),
(579, 80),
(577, 81),
(578, 81),
(579, 81),
(577, 83),
(578, 83),
(579, 83),
(580, 85),
(581, 85),
(582, 85),
(580, 86),
(581, 86),
(582, 86),
(580, 87),
(581, 87),
(582, 87),
(580, 88),
(581, 88),
(582, 88),
(580, 89),
(581, 89),
(582, 89),
(580, 90),
(581, 90),
(582, 90);

-- --------------------------------------------------------

--
-- Table structure for table `timetable_plans`
--

CREATE TABLE `timetable_plans` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. Stundenplan WS 2025/26',
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL COMMENT 'inklusive',
  `csv_file` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pfad zur Originaldatei',
  `uploaded_by` int UNSIGNED NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stundenplan-Versionen';

--
-- Dumping data for table `timetable_plans`
--

INSERT INTO `timetable_plans` (`id`, `name`, `valid_from`, `valid_until`, `csv_file`, `uploaded_by`, `uploaded_at`, `notes`) VALUES
(1, '2. Halbjahr 2025/26', '2026-02-01', '2026-07-24', 'G:\\PleskVhosts\\wvh-online.com\\deutsche-online-schule.com\\schooltools\\teachersbilling/uploads/tmp_tt_eoohlbhtf6vk53b5seo52fl1qh.csv', 1, '2026-03-01 17:17:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'z.B. sberner@wvh-online.com',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(120) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',`last_name`)) STORED,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Systembenutzer';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role_id`, `first_name`, `last_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin@wvh-online.com', '$2y$12$1/v1nkfOpDt3VaBgQyS5tugVLqFAlEyNV1/pMH2GDxwJAKOOBCCyK', 1, 'System', 'Administrator', 1, '2026-03-02 00:58:03', '2026-02-28 22:45:36', '2026-03-02 00:58:03'),
(2, 'verwaltung@wvh-online.com', '$2y$12$1/v1nkfOpDt3VaBgQyS5tugVLqFAlEyNV1/pMH2GDxwJAKOOBCCyK', 2, 'WvH', 'Verwaltung', 1, NULL, '2026-02-28 22:45:36', '2026-02-28 22:55:29'),
(3, 'lehrer@wvh-online.com', '$2y$12$lCZQKbzqR7aNHs7MYfdWK.54P1xikNDL03emEu5cU24OVgY5L9.s6', 3, 'Herr', 'Lehrer', 1, '2026-02-28 23:07:37', '2026-02-28 23:06:46', '2026-02-28 23:07:37'),
(4, 'abeck@wvh-online.com', '$2y$12$GGqkUC9AwKeNrLe9X7R3JOFxzkHJEon6sP9j5o3Tr7KjyE4MDURSC', 3, 'Andreas', 'Beck', 1, NULL, '2026-03-01 17:12:37', '2026-03-01 17:12:37'),
(5, 'akuepper@wvh-online.com', '$2y$12$FcFL1OFCfWhamF49tNCe6.pk3znBjlQCz58MUfdjPfDLTpZ7VuO/G', 3, 'Andreas', 'Küpper', 1, NULL, '2026-03-01 17:12:38', '2026-03-01 17:12:38'),
(6, 'asturm@wvh-online.com', '$2y$12$7/KGc9zzf/q3L.L/G50xbepPDone/lfnkCgV6ZY9fjwY6LEpnyFCK', 3, 'Anja', 'Sturm', 1, NULL, '2026-03-01 17:12:38', '2026-03-01 17:12:38'),
(7, 'anolte@wvh-online.com', '$2y$12$qYIyVf04d60gR5PBtTuyQuY/MrzP2KcJcAe7bV4ZzxsulZQh0pLnC', 3, 'Annegret', 'Nolte', 1, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(8, 'bbredow@wvh-online.com', '$2y$12$RdQ0PWea2H./zeM2ty.KB.7P4wSKjsL0vIm0P4ktJm5a5xyUNOBo2', 3, 'Björn', 'Bredow', 1, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(9, 'cjansen@wvh-online.com', '$2y$12$XQnW0cQ8SfNZsqCciay3POiErTwO391s.vxa5nbVA3NaY1Asc15xe', 3, 'Carolin', 'Jansen', 1, NULL, '2026-03-01 17:12:39', '2026-03-01 17:12:39'),
(10, 'cstroh@wvh-online.com', '$2y$12$ODM8En1KIFKlGeHN.orJO.xadxFU6pvh8.euaBF57eLm/3KnWr0Pe', 3, 'Charlotte', 'Stroh', 1, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(11, 'cgallardo@wvh-online.com', '$2y$12$TgYEI4LN1X2nWu9Rrf5fZOXUyOKddgqH35Qwqc5j5PpO8dDWUDkg2', 3, 'Christin', 'Gallardo', 1, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(12, 'cbrahmi@wvh-online.com', '$2y$12$AcmekmoMlw6/V61AdWbaW.IyrEnjMP0l2YL2kYGW5X9/U005DUJLS', 3, 'Claudia', 'Brahmi', 1, NULL, '2026-03-01 17:12:40', '2026-03-01 17:12:40'),
(13, 'dbuerling@wvh-online.com', '$2y$12$2Nk0EURH02y4EXYOUK.kF.pLFubmQL02MibW7rKG7WDWqR6XzGGSi', 3, 'Daniel', 'Bürling', 1, NULL, '2026-03-01 17:12:41', '2026-03-01 17:12:41'),
(14, 'dkonkol@wvh-online.com', '$2y$12$u3sK9YW5b6V874ytKFnIoO4ixNJTpfc4RN7xB5SKNITWcum2hK4la', 3, 'Daniel', 'Konkol', 1, NULL, '2026-03-01 17:12:41', '2026-03-01 17:12:41'),
(15, 'dstriffler@wvh-online.com', '$2y$12$6lLY2fM8Nhi5TQif4hEMdeyadkXe4qub1EHoh50Ov5QgquHfSuOne', 3, 'Daniel', 'Striffler', 1, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(16, 'egunjic@wvh-online.com', '$2y$12$Jp76W6SI6Weexj5a49zRLeILSp38.iLqGYc2tvh3LhU6aqUhN4Vpq', 3, 'Ela', 'Gunjic', 1, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(17, 'ebilchinski@wvh-online.com', '$2y$12$wFl6cfjbT1DmGyzDUTJ8OuoxC3CuI0.W39Zvx/maT/oqCL9uwd8fC', 3, 'Elena', 'Bilchinski', 1, NULL, '2026-03-01 17:12:42', '2026-03-01 17:12:42'),
(18, 'erecht@wvh-online.com', '$2y$12$9orhEOi8/9EoxxucyrC9su7IvuJVi7emTHHj9FsSXcldLo8yIxuaW', 3, 'Eugenia', 'Recht', 1, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(19, 'fkegel@wvh-online.com', '$2y$12$tCBrQf1m61.TaX6jPplYNO79FqstAUf7.Od4atBOP1XR0SV/4r8DC', 3, 'Frank', 'Kegel', 1, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(20, 'fbeck@wvh-online.com', '$2y$12$U0r9KM9JhpACvRnNXrzJuOqTUTzgfsHCorX8kycUKSVB5UCG77Im2', 3, 'Franziska', 'Beck', 1, NULL, '2026-03-01 17:12:43', '2026-03-01 17:12:43'),
(21, 'gmaerz@wvh-online.com', '$2y$12$MR3/EoyxdTLIQlDwUkkc5unvdfJD81byb3ncpn/fD/Rjict36j6Le', 3, 'Gloria', 'März', 1, NULL, '2026-03-01 17:12:44', '2026-03-01 17:12:44'),
(22, 'gkuessner@wvh-online.com', '$2y$12$zjYRG5pvdzQE/QhNLyrxJeQ6.tjvL9KYoIG0R5x100ogfIRueI6Vq', 3, 'Guido', 'Küßner', 1, NULL, '2026-03-01 17:12:44', '2026-03-01 17:12:44'),
(23, 'hriffer@wvh-online.com', '$2y$12$MG5SkKzWJ9W6avnfnw0O1ODYdc9p8eZOtVcny9Hd7R5i1ppvSA2mK', 3, 'Helena', 'Riffer', 1, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(24, 'hutz@wvh-online.com', '$2y$12$p6NVcMLQ8pGZUnN1uatVs.DyB3F1Xf1VjLgobkv8trS1eO8Oi84uq', 3, 'Herbert', 'Utz', 1, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(25, 'iguillaume@wvh-online.com', '$2y$12$E9ho26epQFQI5JDUbMCHK.CR54qox2xPtIY41bebqpnn0sdM6xgPO', 3, 'Isabelle', 'Guillaume', 1, NULL, '2026-03-01 17:12:45', '2026-03-01 17:12:45'),
(26, 'jhochreiter@wvh-online.com', '$2y$12$8FQnz/5E5lSYWJ4ScAYzh.TWrCq.u1PhrfzELs8Iejae/RYJRKwo2', 3, 'Jasmin', 'Hochreiter', 1, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(27, 'jhannemann@wvh-online.com', '$2y$12$LkyiYKe0xE/Hoxvj2jmNE.li7No8WKjdQTD49j.qDCAzJwDmfgs3i', 3, 'Jens', 'Hannemann', 1, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(28, 'jhettstedt@wvh-online.com', '$2y$12$a47m8S3tKJFv.rST0n6S0.2P5d7VwTEEiCqSOHNz62.bjLuKN6bWm', 3, 'Jens', 'Hettstedt', 1, NULL, '2026-03-01 17:12:46', '2026-03-01 17:12:46'),
(29, 'jhildebrandt@wvh-online.com', '$2y$12$16vq/wlsmQaFEmkvqw0dcObPdPxZmOMdZh1nE8dMAJDq643lbsQ3i', 3, 'Julia', 'Hildebrandt', 1, NULL, '2026-03-01 17:12:47', '2026-03-01 17:12:47'),
(30, 'jkerschhofer@wvh-online.com', '$2y$12$dpjySZIjotTN6JEsiyjAD.pxcmp97PIzRmPtPz4makjWM6U1LG2fa', 3, 'Julia', 'Kerschhofer', 1, NULL, '2026-03-01 17:12:47', '2026-03-01 17:12:47'),
(31, 'jmischke@wvh-online.com', '$2y$12$25PYJqZUxxoXKepK1CvQmeg7Tvn.Zx1a2FGRDHZ6dM1RPRmDuI5Pq', 3, 'Julika', 'Mischke', 1, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(32, 'kschroeder@wvh-online.com', '$2y$12$rfkx.pEtuq9B9tphavEZoukNkToXl/B82ygC.LZVgqI3swqaz1vbC', 3, 'Katharina', 'Schröder', 1, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(33, 'kenns@wvh-online.com', '$2y$12$xSJXZG6C6anQOoj8i2b9i.moRZg8YHu4hIe3G3JXbsTXCFdHwnqrS', 3, 'Katrin', 'Enns', 1, NULL, '2026-03-01 17:12:48', '2026-03-01 17:12:48'),
(34, 'kgleine@wvh-online.com', '$2y$12$Ppoy.Ufg/zjgT6.lRzejG.XBjOt69zQP4WB6cXXioW17LQeIG04eC', 3, 'Kerstin', 'Gleine', 1, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(35, 'kmeier-sigwart@wvh-online.com', '$2y$12$3uISYF4G93QcTzpLvUg4su3YFwMWCdCOE6dX0353CmC22zUgO3hVG', 3, 'Kerstin', 'Meier-Sigwart', 1, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(36, 'kreiser@wvh-online.com', '$2y$12$geCjsbC9JkVsBhG48EciQumJy3RTtBoIWcNFp3U8QEK6VXSqLiUFi', 3, 'Kirsten', 'Reiser', 1, NULL, '2026-03-01 17:12:49', '2026-03-01 17:12:49'),
(37, 'kschmidt@wvh-online.com', '$2y$12$fll3hv/8vsLJw/DgNkyQLOCKR/Yv7MiO.PZRlD1FhWG7RD4jxn2H6', 3, 'Kornelius', 'Schmidt', 1, NULL, '2026-03-01 17:12:50', '2026-03-01 17:12:50'),
(38, 'kaldibssi@wvh-online.com', '$2y$12$CpX.HwSPPXCgrPSqUl7DJOYTBW/NZytfQK5fVtH111pHQCJD89CvG', 3, 'Kulod', 'Aldibssi', 1, NULL, '2026-03-01 17:12:50', '2026-03-01 17:12:50'),
(39, 'lzuniga@wvh-online.com', '$2y$12$eaFb4xuzGthMCvJEU6oPqeDlrR22Ky5uDHsF21Atf1H1jP3A1woau', 3, 'Laura', 'Zuniga', 1, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(40, 'lschallenberg@wvh-online.com', '$2y$12$7huHqNiOdZnXVmsyNVzyUu0QfRqLeO3glQLAlghtNlFnyiAzqFX22', 3, 'Lydia', 'Schallenberg', 1, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(41, 'tmaamar@wvh-online.com', '$2y$12$Y.CQEjquM6Z8eRtFqPI1D.hS24eUSJWWgd3a8Xa/capHC91qXp96G', 3, 'Tavi', 'Maamar', 1, NULL, '2026-03-01 17:12:51', '2026-03-01 17:12:51'),
(42, 'mewald@wvh-online.com', '$2y$12$Lgm8vyBmeQ33v2PQGE9EEuerr6U9nzbhb.QzhsnKMHX.i7jNGeXCy', 3, 'Marina', 'Ewald', 1, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(43, 'mschreiber@wvh-online.com', '$2y$12$5eXeLgXzF2FAEvKfoNfjXOOvX8chLcRaYQPGM9IyJuF.CO8cEhVIC', 3, 'Markus', 'Schreiber', 1, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(44, 'mschneider@wvh-online.com', '$2y$12$eaASkHFRZamg/cDk/GAR/OUMh1wZ62oO2gnWIOxKFIJnpeqNjWrxa', 3, 'Max', 'Schneider', 1, NULL, '2026-03-01 17:12:52', '2026-03-01 17:12:52'),
(45, 'mherold@wvh-online.com', '$2y$12$wkzhHH1MpWTJJzGHRDLlquBZfrj/9lo2Txg7D3ZqR0Tb0IeCB3mHG', 3, 'Milan', 'Herold', 1, NULL, '2026-03-01 17:12:53', '2026-03-01 17:12:53'),
(46, 'nuntu@wvh-online.com', '$2y$12$NJhc/ZrTjh9jFXj9lkrgZOi/5aR1d6d0vLpaUrELwWz0MWjf7eXU2', 3, 'Nathalie', 'Untu', 1, NULL, '2026-03-01 17:12:53', '2026-03-01 17:12:53'),
(47, 'nschmidt@wvh-online.com', '$2y$12$TccEjSZzCTz63Ej5dzWQDOXHMq1GIJWYZdtOrkrEQLtx7dcfAZ.Wm', 3, 'Nele', 'Schmidt', 1, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(48, 'nblume@wvh-online.com', '$2y$12$M66KHLTn5ViPZkMIHzH4fO3Kie22YVyL9pdtF.FyKnTRc6IVqEG5y', 3, 'Nikolas', 'Blume', 1, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(49, 'pjurtschitsch@wvh-online.com', '$2y$12$KY/t85HxNEEBAP1LXKkYTOKp0jstQ6omzK0H5NWaOmn/t2P0QT2cu', 3, 'Paul', 'Jurtschitsch', 1, NULL, '2026-03-01 17:12:54', '2026-03-01 17:12:54'),
(50, 'pfellner@wvh-online.com', '$2y$12$7Hzg12tofa22VYVWhKJfVOXeF5JZfBc9x/xAiEWuBQXD3Yzx09UCO', 3, 'Peter', 'Fellner', 1, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(51, 'rmaul@wvh-online.com', '$2y$12$BE3ac1TohVMcvQnZ6jzyoe2hQNAquXrrsWFnBJME9NLcqoJpyJBa6', 3, 'Rebecca', 'Maul', 1, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(52, 'rbt@wvh-online.com', '$2y$12$JYYpjhIq0UFEifwKY0FqnOzhSh.Skw4X57NOalHc2k/wtsk6g1Wf.', 3, 'Regine', 'Behrmann-Thiele', 1, NULL, '2026-03-01 17:12:55', '2026-03-01 17:12:55'),
(53, 'rtoufani@wvh-online.com', '$2y$12$wT9LwCbv7/ulll5ZgbjGLOiK/eUrthWYjrd5RqesMxtcT/DEnnh8e', 3, 'Rojan', 'Toufani', 1, NULL, '2026-03-01 17:12:56', '2026-03-01 17:12:56'),
(54, 'rrousseau@wvh-online.com', '$2y$12$FUKrBP.mTLAnOQqdPAss9uXGobobbcWqCFFEIGe6p8dNjI1NhaBX6', 3, 'Ruth', 'Rousseau', 1, NULL, '2026-03-01 17:12:56', '2026-03-01 17:12:56'),
(55, 'sziegler@wvh-online.com', '$2y$12$g9gGuyxns3PG53v311hv/eevARtWE439ynBB9NPo77.GnHE1ObSJa', 3, 'Sandra', 'Ziegler', 1, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(56, 'sberner@wvh-online.com', '$2y$12$VMLLsGsn3S0GDpP7BEk8Nul5DC5SNkgX3/uaqk/XdRub1yNyyDecy', 3, 'Sascha', 'Berner', 1, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(57, 'sboehmer@wvh-online.com', '$2y$12$dJ.eyJfo9FWpVswhlDTUQOuHECYpRn0Lw1Q2UZxFe5aWFUyJSlfXO', 3, 'Sebastian', 'Böhmer', 1, NULL, '2026-03-01 17:12:57', '2026-03-01 17:12:57'),
(58, 'skolburan@wvh-online.com', '$2y$12$utndExd2ioR3sLq.oYgCr./iKL1mm6QRmzRQ1JBTQ4rmKIxnt05Pi', 3, 'Sitem', 'Kolburan', 1, NULL, '2026-03-01 17:12:58', '2026-03-01 17:12:58'),
(59, 'sbehrens@wvh-online.com', '$2y$12$0Ci9aKpso.iK1su3Wy43IeVWSX/gwdbEffj3s2aNYwoWO8yaX9xvO', 3, 'Sonja', 'Behrens', 1, '2026-03-02 00:32:48', '2026-03-01 17:12:58', '2026-03-02 00:32:48'),
(60, 'sehrenfeuchter@wvh-online.com', '$2y$12$HaRDWglGnjNHV9FxYobtiO4A5Q1s4BS03LbRKPd4KoEQ0Z.PM.CiK', 3, 'Sonja', 'Ehrenfeuchter', 1, NULL, '2026-03-01 17:12:58', '2026-03-01 17:12:58'),
(61, 'skuessner@wvh-online.com', '$2y$12$SEZTgESQyHbm0m7zHwGofur.yI7BzYcIE/mxcxBryT/EH1C17seni', 3, 'Sophie', 'Küßner', 1, NULL, '2026-03-01 17:12:59', '2026-03-01 17:12:59'),
(62, 'smenzel@wvh-online.com', '$2y$12$azU275fkAsdfaJjQT0RsPOIhceGCSFz4wnoR9du//h/BllpV1YrSC', 3, 'Stephan', 'Menzel', 1, NULL, '2026-03-01 17:12:59', '2026-03-01 17:12:59'),
(63, 'tkriegel@wvh-online.com', '$2y$12$Z5MMmWzfv5QNOXrN0Ep3AOzS33m2wS5NSM3lUVaNLd4pYvQmQL2ae', 3, 'Thomas', 'Kriegel', 1, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(64, 'ivoeltz@wvh-online.com', '$2y$12$n4I4eT5gW.GTeycSMF1q2.WBEmirAsrpVLTN/mKciJfeK2VNffrXu', 3, 'Inna', 'Völtz', 1, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(65, 'uelstner@wvh-online.com', '$2y$12$cktYmhMDNvQ21tso5cqTNeqjGS9Y7leeQqycza.61c0wdJ0entWzS', 3, 'Ulrike', 'Elstner', 1, NULL, '2026-03-01 17:13:00', '2026-03-01 17:13:00'),
(66, 'iromeroespejo@wvh-online.com', '$2y$12$Vgs2TBSz42ri2ns/cC5lL.Isfj4IquhTcoTg6/meTqSBOQJ.CGhDi', 3, 'Isabel', 'Romero', 1, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(67, 'fmarin@wvh-online.com', '$2y$12$DUZBHBhaH.8NldHaXsRbGuadrYeB2fBsg31A9DGghXLSZTMZ9dL0O', 3, 'Fenja', 'Marin', 1, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(68, 'srickert@wvh-online.com', '$2y$12$.LVjRyRR./gILa6iSsSgWek5ECh0.LfofGVa.9oheacvAFskRH1Ei', 3, 'Stephanie', 'Rickert', 1, NULL, '2026-03-01 17:13:01', '2026-03-01 17:13:01'),
(69, 'smarcialgomes@wvh-online.com', '$2y$12$jFdI0qJ6WL6CzVCNxyqsxe4smTQbYuXK6M36QIILOG9iCgFUNkrYK', 3, 'Sibylle', 'Marcial Gomes', 1, NULL, '2026-03-01 17:13:02', '2026-03-01 17:13:02'),
(70, 'youtadrarte@wvh-online.com', '$2y$12$DT1VGuRRQMpSP1q1h9UG3OLkMxdTDqcp/V//.r6wr7i6oWyFJ.w.C', 3, 'Yosef', 'Outadrarte', 1, NULL, '2026-03-01 17:13:02', '2026-03-01 17:13:02'),
(71, 'lkrueger@wvh-online.com', '$2y$12$j4WR36F7MFih14XZuvSRpeysumVrqCxS2fjWD0.jImqyc5kKGkvly', 3, 'Linda', 'Krüger', 1, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(72, 'spfenninger@wvh-online.com', '$2y$12$.6jHsgBhwwdOLHOEdAnXhet26jVd9h.OYJTdD5vfzA8K59ZguRNry', 3, 'Stefan', 'Pfenninger', 1, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(73, 'vschumann@wvh-online.com', '$2y$12$Bnn28h26b.6haWed461DHOnq5CVS/jyX5MfOO4ETDINvnmX5qYWYK', 3, 'Vanessa', 'Schumann', 1, NULL, '2026-03-01 17:13:03', '2026-03-01 17:13:03'),
(74, 'krumpel@wvh-online.com', '$2y$12$eAIzaYQPWo7KK4GeMX5vYe0/vDMJHSeMVLGY1y.GpBj0BD9HKrFGm', 3, 'Klaus', 'Rumpel', 1, NULL, '2026-03-01 17:13:04', '2026-03-01 17:13:04'),
(75, 'jwengler@wvh-online.com', '$2y$12$LozufGapfynLXe6r4a2jMO9w3IAlbZBPDxwWURzTfQlyC5pDCvvyi', 3, 'Jennifer', 'Wengler', 1, NULL, '2026-03-01 17:13:04', '2026-03-01 17:13:04');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_teachers`
-- (See below for the actual view)
--
CREATE TABLE `v_active_teachers` (
`teacher_id` int unsigned
,`email` varchar(120)
,`first_name` varchar(60)
,`last_name` varchar(60)
,`display_name` varchar(120)
,`employment_type` enum('honorar','festangestellt')
,`hourly_rate` decimal(8,2)
,`iban` varchar(34)
,`bic` varchar(11)
,`bank_data_approved` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_monthly_hours_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_monthly_hours_summary` (
`teacher_id` int unsigned
,`display_name` varchar(120)
,`billing_month` date
,`plan_hours` decimal(6,2)
,`released_hours` decimal(6,2)
,`substituted_hours` decimal(6,2)
,`effective_plan_hours` decimal(6,2)
,`gross_total` decimal(10,2)
,`record_status` enum('draft','pending_teacher','confirmed','final','paid')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_substitution_open`
-- (See below for the actual view)
--
CREATE TABLE `v_substitution_open` (
`substitution_id` bigint unsigned
,`lesson_id` bigint unsigned
,`lesson_date` date
,`covers_part` enum('full','first','second')
,`status` enum('open','pending_confirm','claimed','confirmed','conflict','admin_resolved','cancelled','locked')
,`released_at` datetime
,`original_teacher_id` int unsigned
,`original_teacher_name` varchar(120)
,`subject_id` int unsigned
,`subject_name` varchar(100)
,`weekday` tinyint unsigned
,`time_start` time
,`time_end` time
);

-- --------------------------------------------------------

--
-- Table structure for table `wise_exports`
--

CREATE TABLE `wise_exports` (
  `id` int UNSIGNED NOT NULL,
  `billing_month_id` int UNSIGNED NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `recipient_count` smallint UNSIGNED NOT NULL,
  `generated_by` int UNSIGNED NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Protokoll der Wise Batch Exporte';

-- --------------------------------------------------------

--
-- Table structure for table `wise_export_lines`
--

CREATE TABLE `wise_export_lines` (
  `id` int UNSIGNED NOT NULL,
  `export_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  `record_id` int UNSIGNED NOT NULL,
  `recipient_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iban` varchar(34) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bic` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `reference` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Einzelzeilen des Wise-Exports';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `async_hour_assignments`
--
ALTER TABLE `async_hour_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_async_teacher_type_month` (`teacher_id`,`type_id`,`billing_month`),
  ADD KEY `idx_async_month` (`billing_month`),
  ADD KEY `fk_async_type` (`type_id`),
  ADD KEY `fk_async_assigned_by` (`assigned_by`);

--
-- Indexes for table `async_hour_types`
--
ALTER TABLE `async_hour_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_asynctype_created_by` (`created_by`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `billing_line_items`
--
ALTER TABLE `billing_line_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_line_record` (`record_id`);

--
-- Indexes for table `billing_months`
--
ALTER TABLE `billing_months`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_billing_month` (`month`),
  ADD KEY `fk_bm_closed_by` (`closed_by`),
  ADD KEY `fk_bm_finalized_by` (`finalized_by`);

--
-- Indexes for table `billing_records`
--
ALTER TABLE `billing_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_billing_teacher_month_ver` (`billing_month_id`,`teacher_id`,`version`),
  ADD KEY `idx_billing_teacher` (`teacher_id`),
  ADD KEY `idx_billing_status` (`status`),
  ADD KEY `fk_br_corrects` (`corrects_record_id`);

--
-- Indexes for table `bonus_definitions`
--
ALTER TABLE `bonus_definitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bonus_teacher` (`teacher_id`),
  ADD KEY `fk_bonus_created_by` (`created_by`);

--
-- Indexes for table `calendar_event_classes`
--
ALTER TABLE `calendar_event_classes`
  ADD PRIMARY KEY (`event_id`,`class_id`),
  ADD KEY `fk_cec_class` (`class_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_classes_name` (`name`),
  ADD KEY `idx_classes_grade` (`grade_level`);

--
-- Indexes for table `deputate_assignments`
--
ALTER TABLE `deputate_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dep_teacher_month` (`teacher_id`,`billing_month`),
  ADD KEY `idx_dep_type` (`type_id`),
  ADD KEY `fk_dep_assigned_by` (`assigned_by`);

--
-- Indexes for table `deputate_types`
--
ALTER TABLE `deputate_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_deputate_name` (`name`),
  ADD KEY `fk_deptype_created_by` (`created_by`);

--
-- Indexes for table `lesson_instances`
--
ALTER TABLE `lesson_instances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lesson_entry_date` (`entry_id`,`lesson_date`),
  ADD KEY `idx_lesson_date` (`lesson_date`),
  ADD KEY `idx_lesson_status` (`status`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_email_time` (`email`,`attempted_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Indexes for table `school_calendar`
--
ALTER TABLE `school_calendar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cal_dates` (`date_from`,`date_until`),
  ADD KEY `idx_cal_type` (`type`),
  ADD KEY `fk_cal_created_by` (`created_by`);

--
-- Indexes for table `school_holidays`
--
ALTER TABLE `school_holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hol_dates` (`date_from`,`date_until`),
  ADD KEY `idx_hol_year` (`school_year`),
  ADD KEY `fk_hol_user` (`created_by`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subjects_name` (`name`);

--
-- Indexes for table `substitutions`
--
ALTER TABLE `substitutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sub_lesson` (`lesson_id`),
  ADD KEY `idx_sub_original_teacher` (`original_teacher_id`),
  ADD KEY `idx_sub_substitute` (`substitute_teacher_id`),
  ADD KEY `idx_sub_status` (`status`),
  ADD KEY `idx_sub_billing_month` (`billing_month`),
  ADD KEY `fk_sub_confirmed_by` (`confirmed_by`),
  ADD KEY `fk_sub_resolved_by` (`resolved_by`),
  ADD KEY `fk_sub_self_assigned` (`self_assigned_by`);

--
-- Indexes for table `substitution_notifications`
--
ALTER TABLE `substitution_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_recipient` (`recipient_id`,`is_read`),
  ADD KEY `idx_notif_sub` (`substitution_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teachers_user` (`user_id`),
  ADD KEY `idx_teachers_employment` (`employment_type`),
  ADD KEY `fk_teachers_approved_by` (`bank_data_approved_by`),
  ADD KEY `idx_teachers_wise_id` (`wise_recipient_id`);

--
-- Indexes for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_docs_teacher` (`teacher_id`),
  ADD KEY `fk_docs_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `teacher_rates`
--
ALTER TABLE `teacher_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rates_teacher_date` (`teacher_id`,`valid_from`),
  ADD KEY `fk_rates_created_by` (`created_by`);

--
-- Indexes for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entry_plan` (`plan_id`),
  ADD KEY `idx_entry_teacher` (`teacher_id`),
  ADD KEY `idx_entry_day_period` (`weekday`,`period_start`),
  ADD KEY `idx_entry_double_group` (`double_group_id`),
  ADD KEY `fk_entry_subject` (`subject_id`);

--
-- Indexes for table `timetable_entry_classes`
--
ALTER TABLE `timetable_entry_classes`
  ADD PRIMARY KEY (`entry_id`,`class_id`),
  ADD KEY `fk_tec_class` (`class_id`);

--
-- Indexes for table `timetable_plans`
--
ALTER TABLE `timetable_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan_dates` (`valid_from`,`valid_until`),
  ADD KEY `fk_plan_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `wise_exports`
--
ALTER TABLE `wise_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wise_month` (`billing_month_id`),
  ADD KEY `fk_wise_generated_by` (`generated_by`);

--
-- Indexes for table `wise_export_lines`
--
ALTER TABLE `wise_export_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wiseline_export` (`export_id`),
  ADD KEY `fk_wiseline_teacher` (`teacher_id`),
  ADD KEY `fk_wiseline_record` (`record_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `async_hour_assignments`
--
ALTER TABLE `async_hour_assignments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `async_hour_types`
--
ALTER TABLE `async_hour_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `billing_line_items`
--
ALTER TABLE `billing_line_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_months`
--
ALTER TABLE `billing_months`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_records`
--
ALTER TABLE `billing_records`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonus_definitions`
--
ALTER TABLE `bonus_definitions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `deputate_assignments`
--
ALTER TABLE `deputate_assignments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deputate_types`
--
ALTER TABLE `deputate_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `lesson_instances`
--
ALTER TABLE `lesson_instances`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `school_calendar`
--
ALTER TABLE `school_calendar`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_holidays`
--
ALTER TABLE `school_holidays`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `substitutions`
--
ALTER TABLE `substitutions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `substitution_notifications`
--
ALTER TABLE `substitution_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_rates`
--
ALTER TABLE `teacher_rates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=606;

--
-- AUTO_INCREMENT for table `timetable_plans`
--
ALTER TABLE `timetable_plans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `wise_exports`
--
ALTER TABLE `wise_exports`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wise_export_lines`
--
ALTER TABLE `wise_export_lines`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_active_teachers`
--
DROP TABLE IF EXISTS `v_active_teachers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`wvhadmin1`@`%` SQL SECURITY DEFINER VIEW `v_active_teachers`  AS SELECT `t`.`id` AS `teacher_id`, `u`.`email` AS `email`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`display_name` AS `display_name`, `t`.`employment_type` AS `employment_type`, `t`.`hourly_rate` AS `hourly_rate`, `t`.`iban` AS `iban`, `t`.`bic` AS `bic`, `t`.`bank_data_approved` AS `bank_data_approved` FROM (`teachers` `t` join `users` `u` on((`u`.`id` = `t`.`user_id`))) WHERE ((`u`.`is_active` = 1) AND (`t`.`active_from` <= curdate()) AND ((`t`.`active_until` is null) OR (`t`.`active_until` >= curdate()))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_monthly_hours_summary`
--
DROP TABLE IF EXISTS `v_monthly_hours_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`wvhadmin1`@`%` SQL SECURITY DEFINER VIEW `v_monthly_hours_summary`  AS SELECT `br`.`teacher_id` AS `teacher_id`, `u`.`display_name` AS `display_name`, `bm`.`month` AS `billing_month`, `br`.`plan_hours` AS `plan_hours`, `br`.`released_hours` AS `released_hours`, `br`.`substituted_hours` AS `substituted_hours`, `br`.`effective_plan_hours` AS `effective_plan_hours`, `br`.`gross_total` AS `gross_total`, `br`.`status` AS `record_status` FROM (((`billing_records` `br` join `billing_months` `bm` on((`bm`.`id` = `br`.`billing_month_id`))) join `teachers` `t` on((`t`.`id` = `br`.`teacher_id`))) join `users` `u` on((`u`.`id` = `t`.`user_id`))) WHERE (`br`.`is_correction` = 0) ;

-- --------------------------------------------------------

--
-- Structure for view `v_substitution_open`
--
DROP TABLE IF EXISTS `v_substitution_open`;

CREATE ALGORITHM=UNDEFINED DEFINER=`wvhadmin1`@`%` SQL SECURITY DEFINER VIEW `v_substitution_open`  AS SELECT `s`.`id` AS `substitution_id`, `s`.`lesson_id` AS `lesson_id`, `li`.`lesson_date` AS `lesson_date`, `s`.`covers_part` AS `covers_part`, `s`.`status` AS `status`, `s`.`released_at` AS `released_at`, `ot`.`id` AS `original_teacher_id`, `ot_u`.`display_name` AS `original_teacher_name`, `te`.`subject_id` AS `subject_id`, `sub`.`name` AS `subject_name`, `te`.`weekday` AS `weekday`, `te`.`time_start` AS `time_start`, `te`.`time_end` AS `time_end` FROM (((((`substitutions` `s` join `lesson_instances` `li` on((`li`.`id` = `s`.`lesson_id`))) join `timetable_entries` `te` on((`te`.`id` = `li`.`entry_id`))) join `subjects` `sub` on((`sub`.`id` = `te`.`subject_id`))) join `teachers` `ot` on((`ot`.`id` = `s`.`original_teacher_id`))) join `users` `ot_u` on((`ot_u`.`id` = `ot`.`user_id`))) WHERE (`s`.`status` in ('open','claimed','conflict')) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `async_hour_assignments`
--
ALTER TABLE `async_hour_assignments`
  ADD CONSTRAINT `fk_async_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_async_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`),
  ADD CONSTRAINT `fk_async_type` FOREIGN KEY (`type_id`) REFERENCES `async_hour_types` (`id`);

--
-- Constraints for table `async_hour_types`
--
ALTER TABLE `async_hour_types`
  ADD CONSTRAINT `fk_asynctype_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_line_items`
--
ALTER TABLE `billing_line_items`
  ADD CONSTRAINT `fk_line_record` FOREIGN KEY (`record_id`) REFERENCES `billing_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `billing_months`
--
ALTER TABLE `billing_months`
  ADD CONSTRAINT `fk_bm_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bm_finalized_by` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_records`
--
ALTER TABLE `billing_records`
  ADD CONSTRAINT `fk_br_billing_month` FOREIGN KEY (`billing_month_id`) REFERENCES `billing_months` (`id`),
  ADD CONSTRAINT `fk_br_corrects` FOREIGN KEY (`corrects_record_id`) REFERENCES `billing_records` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_br_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);

--
-- Constraints for table `bonus_definitions`
--
ALTER TABLE `bonus_definitions`
  ADD CONSTRAINT `fk_bonus_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_bonus_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);

--
-- Constraints for table `calendar_event_classes`
--
ALTER TABLE `calendar_event_classes`
  ADD CONSTRAINT `fk_cec_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `fk_cec_event` FOREIGN KEY (`event_id`) REFERENCES `school_calendar` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deputate_assignments`
--
ALTER TABLE `deputate_assignments`
  ADD CONSTRAINT `fk_dep_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_dep_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`),
  ADD CONSTRAINT `fk_dep_type` FOREIGN KEY (`type_id`) REFERENCES `deputate_types` (`id`);

--
-- Constraints for table `deputate_types`
--
ALTER TABLE `deputate_types`
  ADD CONSTRAINT `fk_deptype_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `lesson_instances`
--
ALTER TABLE `lesson_instances`
  ADD CONSTRAINT `fk_lesson_entry` FOREIGN KEY (`entry_id`) REFERENCES `timetable_entries` (`id`);

--
-- Constraints for table `school_calendar`
--
ALTER TABLE `school_calendar`
  ADD CONSTRAINT `fk_cal_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `school_holidays`
--
ALTER TABLE `school_holidays`
  ADD CONSTRAINT `fk_hol_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `substitutions`
--
ALTER TABLE `substitutions`
  ADD CONSTRAINT `fk_sub_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sub_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lesson_instances` (`id`),
  ADD CONSTRAINT `fk_sub_original` FOREIGN KEY (`original_teacher_id`) REFERENCES `teachers` (`id`),
  ADD CONSTRAINT `fk_sub_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sub_self_assigned` FOREIGN KEY (`self_assigned_by`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sub_substitute` FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `substitution_notifications`
--
ALTER TABLE `substitution_notifications`
  ADD CONSTRAINT `fk_notif_sub` FOREIGN KEY (`substitution_id`) REFERENCES `substitutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_approved_by` FOREIGN KEY (`bank_data_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_documents`
--
ALTER TABLE `teacher_documents`
  ADD CONSTRAINT `fk_docs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docs_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `teacher_rates`
--
ALTER TABLE `teacher_rates`
  ADD CONSTRAINT `fk_rates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_rates_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD CONSTRAINT `fk_entry_plan` FOREIGN KEY (`plan_id`) REFERENCES `timetable_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entry_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_entry_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);

--
-- Constraints for table `timetable_entry_classes`
--
ALTER TABLE `timetable_entry_classes`
  ADD CONSTRAINT `fk_tec_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `fk_tec_entry` FOREIGN KEY (`entry_id`) REFERENCES `timetable_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable_plans`
--
ALTER TABLE `timetable_plans`
  ADD CONSTRAINT `fk_plan_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `wise_exports`
--
ALTER TABLE `wise_exports`
  ADD CONSTRAINT `fk_wise_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_wise_month` FOREIGN KEY (`billing_month_id`) REFERENCES `billing_months` (`id`);

--
-- Constraints for table `wise_export_lines`
--
ALTER TABLE `wise_export_lines`
  ADD CONSTRAINT `fk_wiseline_export` FOREIGN KEY (`export_id`) REFERENCES `wise_exports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wiseline_record` FOREIGN KEY (`record_id`) REFERENCES `billing_records` (`id`),
  ADD CONSTRAINT `fk_wiseline_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
