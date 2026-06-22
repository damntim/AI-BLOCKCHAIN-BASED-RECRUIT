-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2026 at 12:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `recruitment_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `cv_hash_snap` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `status` enum('APPLIED','SCREENED','EXAM_INVITED','EXAM_DONE','INTERVIEW_INVITED','INTERVIEW_DONE','SELECTED','REJECTED') DEFAULT 'APPLIED',
  `applied_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `job_id`, `user_id`, `cv_hash_snap`, `icp_confirmed`, `status`, `applied_at`, `updated_at`) VALUES
(4, 6, 17, NULL, 1, 'EXAM_DONE', '2026-06-06 20:52:28', '2026-06-06 21:03:59'),
(5, 7, 17, NULL, 0, 'INTERVIEW_INVITED', '2026-06-07 15:19:43', '2026-06-07 15:30:45'),
(6, 8, 17, NULL, 0, 'EXAM_DONE', '2026-06-08 13:59:36', '2026-06-08 14:23:16');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `reg_number` varchar(100) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `reg_cert_path` varchar(500) DEFAULT NULL,
  `reg_cert_hash` char(64) DEFAULT NULL,
  `tax_doc_path` varchar(500) DEFAULT NULL,
  `tax_doc_hash` char(64) DEFAULT NULL,
  `verification_status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `company_hash` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `user_id`, `company_name`, `reg_number`, `industry`, `size`, `website`, `address`, `reg_cert_path`, `reg_cert_hash`, `tax_doc_path`, `tax_doc_hash`, `verification_status`, `verified_at`, `verified_by`, `rejection_reason`, `company_hash`, `icp_confirmed`, `created_at`, `updated_at`) VALUES
(1, 10, 'Inganzo labs', '11111111111', 'ict', '', '', '', 'storage/uploads/credentials/5234a7249b9d5fa268c9941ddb96e3cd.pdf', '8150e5f0285bff8f7df0741b095317d7ee71f84ae7271e669378e443dc53f4f7', 'storage/uploads/credentials/82407fdcd2975242c7b72ee2ca4ca307.pdf', 'd49f2fdbf8ce98797a9b5043e388e0681788cf0c2cbbebef952d51ec808755e7', 'APPROVED', NULL, 1, NULL, NULL, 1, '2026-05-29 06:50:14', '2026-05-29 06:55:51');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `questions_encrypted` longtext NOT NULL,
  `generated_at` timestamp NULL DEFAULT current_timestamp(),
  `total_points` int(10) UNSIGNED DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `job_id`, `questions_encrypted`, `generated_at`, `total_points`) VALUES
(4, 6, 'ZqCOC6aDVZCGO0iyV9RTpjQ1TWxVNmN0UHl6c2lYQm4wSE9FelVmUnlhMXlvWDVybTlvOWdMVEhWZG9wckc1UXowSVZjNkx6WThYVlFzSmNMWHMrRGNtU3dSd2N3M0hQYUVtb2RJZDVsZ2FSN2NwUmM0TmtlTU4wVWplblhBT2s1bjlyaTI3WUMvZ25GODB1WTNPdGVySnUyVEwzRlQyQndDVG1qd2tOa0lsdnNhUUJ3U3Z1eWtKU29VOFhkZGQvZWVjQitBQXpaTlhzRjFGc0F2QmNGSVFpSXFGd0h0MFd5eGhNRkI0MTFnYWdEMnVDVnd5eDd2OWJpZ2liSXRQSGZyT2VGSmhOUGVDWHNJUGlkOGc5OW41N3J2elRxNldncDdUREwrem9CTmtGVFFIMVFZQ3YzVWVlQ2czbGpZdFkybC9HelZMbzM4U1hpSjdyR21pSmZkT1RBKzBvTDIzRTBWWUFPdnZMNWpad0RoVXo2RlAydGNTc3g0NDc2eUtSRjBrQTFrRnl4VE4wQWZ2d1NTNlg3Y3V4L252L1JNZVNOdDNKQ1pDZ2ttQjF4UXVidi9vcm94ang0eWx5U2VPWGIrNkFnT1pKRXhvSUtMTWxGY3F2czVodXNOUTRsa3ZQcUV2VXZ1dXpsdldzY0FEN2tGajdRWitkSjZPQ3dsNFdUV0NvdlRmNStMdENMQUwybktadGtZajYrWlJLakFVL3M5Z05MRHFDSnVzUUVMOTFNd2NtR1FwMFdsWDd6cGdadTJJVWZ3cG82RG13UWZlTDluOUpuank3N0orMnhWakVKNktaUVM3VHRreFBDa1BzZzdFSUpzcWxHSEwvbzZPWHBFQS9sYlEwck83bVlnbjdhaFRVWjBxU094c2xJSWkxUmMrd2w0djcxTTVEWjhoSmhTb3FweG5SMVczVGcxSTcyM1luWWRnR2VpbFcyZXRJc3RCYnc5QTZxTE5Tb0VKMWRDQXl2V01HRmZmRGVKSjZhcDhvdVd0VTFiVnkzRlhsVFljZ08vTkd5MFJDYnFEbkROeU1rY1dNenUyS3BRSTNPb2Z6ZXR5TDR4ektEd3REUlpuYnRNRmhUcHpmR0Y3aTI2aENFaWVpeWp3aE1LUkVkWVEzZUlwVGR2cmN0M3NDSTdFZUJ4N2JaMjJWQVZ0OHlldWxISEZXQnQ0a1lNajdDRFJraTNqMDZvRktDVzUrNXBkRXozOWtjQzBsNjBhWU9VR2tTRUFxTGJBYm9nQzdGR2o3c3BiNkJDTnFHWEdGODA1RHJ2WjYzZWhSSDJ5OGhUUTNteTljN1NoeWVrNjIzZVMwSFhONnFQZzBOTjdhejlVcjhWU3pYNkJ3SlY3dWpsMm5qcnJzWFFEaUtQNkNnVFJMQUlUZHN2aFlJeFIzMHhGeVZQS2N6VW0xT1d1RmdNK2tKSDJGQkUxT1kwTFVBZkpadnJsOEhsS1hJdXcxQ2pXZk9qTnRPQ2JkK1NBNDBRckpxUHN1R1cxV3pRakxXdGNweEs5Sjd6dz0=', '2026-06-06 20:57:02', 15),
(5, 7, 'xMkIzBO9ibW1HKMZ3dhHqUlHc3FVaWtMaGM5eXBDWTY0aXYvVXlUNDU4L1loUHNVaUt4Z0U2OS81RDNMM0RvTGZVL3UyNnBGVjM1b01aTk9WOTMyclc4RGZkamI0b1NvZnZjbXgveXRrQ0F2b2pJSG85eVp6WFhPdTk0Z0ZWTUs1bCtwUnBUK2xzemdHUmFpdG1yR0N5cXJ5TmVBcFlmYXRCR09EOGhHVWppRlJiamI1NUY0Y0RNM3IvMlJzTkRGMUdTdEVpdUduS2lwVTI5a280VW1TN0d4eWpSN0d3K3FrV2toeXk3Z3o1RTFEeUcxb1pyZTlPMTZGdUVEZ2FuU0ErUkpUZkw1UDRPUFE1Z3cyVEluV0xxbkJ3WDRDZ2tKTHNzWXVrbnFzWmZSNldmbm9sK3JGaFhYMU0xU3VwallKbWtEa1NwRFFsaHdyajhBYnlBTDlTRzFhMEZTUzlmd0g4dlJFcXQvbWI4QkxOaERKdmcvU1FKVk9ZYmdCeVUveEw5aXUzNmRlUFYwbkdYZ1lhb1k1U1drVHhITWNoSVlGbUwxWTd4cUYrSW0vejA2dFY5bDBjTHdsYzhtUGx4RVBLU05hc28rTlpsNFE3WDJaZGFXNDZVdGhJWGJYVEl3ZWFxaFM5WmJPUG81R2lGN3dOZ2VNY0VNZVUwK1pCYTBjd3JJSlh3MTQ5T3EyWlVsWjBFSmZiODI5YnBtazl0NU0xV2RQNWhFNTVheVY0R0pnTklXeU9hMGoxRGZHaERDQ2hIYmVoSVRGOGhWVHdKRTJZRmtjWDlVMlNreUViNGJHQjAxcjlJSUpKUE56S1RBUkwwR0cwYkdndHlSZk1RVEtCaHdEbC9lUFg0aURsWmdIRysyT3FpZjdEalBBTGw0bnU4dVNNdzliWG1aZm51VEdyTmVuV0ZGeWozZDN5SnFZUmFUV2ljaE0xVENheE4xSjVVV29sYUx1RjdEUmhsWDJqbldZSnJTVW1jYmJoY3JvZk95Y1lSYmdCMmVvWjZrTWwrTWN0ZS9UYnNtbXE4MlI4RjJ5M0h4aE9oN01JLzhET3NTYmZKYkFGWWNpU3phbFZZZklQVjNTdHNCZzdiTlVmMXVTMFpZcHo1WWkxRUJVcWF0VGtvWWZuekxxNDNxRUw2MGpCWkVzWlZDWE1rbXpzQ1lhZlRrajV0SDFGYXllQ043MmxocHY2T1RjemE2NDVoUnRVdFJLMFZmMGpodEJEMGhHL2ZGZHNnRE43bVJRWHNaa1RiUHBTcW1mY3lQOWtaM2FkRks1cjBpeXJpVytjemI2UktuYlJqU2tzWk4zNHBmMUlrY1B4R3ZMUFBaUy9abWphbHpUazlqRmFGUDJjQWkxYnp5eCtRU245Rm9LT2UvditDNmozVEU1NmtURzVkN0hRSHJpUEZaUEZWOEtOSDc2RllCS3NscTBkRzZEbENUUlJ5VkJ4d0FFS1lOUXdLei9DbENsQkg5TGEwR1FpTnhpL0xLWU51aGxXcDQwVzN0bUJWWTljaz0=', '2026-06-07 15:23:10', 15),
(6, 8, 'Z5sihHiRGi939Mx5yS5tnEFnZDBrbGloeFVYQlFxRHpEVmdnZjA3bklUTDBIZ2RoQVBRZDJLR3NZcjVSZDQrUElZbXJKYW1JR1RuVEhwVTVkeE9uaHRWRDk5MmxJMUowaEkyT0lRU0tDQXk0NVVSdzg2dlZsL1ZhQTVuWkExVlI2dG1tRXM2WlRGdzR3TENUejIyRytzNXh5QnBxT2NrR1ZYaEIyNTdWWlNBNDhLcGZvc05ucVVCZ1BJWHZCMGNCNk9Sd0ZSSDN5MlY1a2RDbjgrZDM3aFFvTHRndFZNZituMWRoM3RjVlFjSGFyZWpSZjlVMm95VjAyQXBRSE9IV2JuWVZMZmFobS9UamtMUEhDczVkYUJXZ0FhWHhEVGdRM1dKTGtXV3NHbzZUQVQvOVZIR0pSUU9Xd0tTbHQrOTdNMVlOanlQMjU1R3Y1d21MSWplcGNVeTRjaEFSV1I0Yy9lYlNJeXR1QzdrbmRtSERpbk96STVzN3NCMmppb1V1amNKck5TK0hwMjNmbjNrRmpCam4xMUNhLzFNY2d2OGY5UDVwN0xVVnVzQ1BOR0J0Ly91a2Y3RUZWK3ZZeFo0a2NwL3JBSEt4U3Q3QUg2eGlESEN5NzFyS0R3aTRPb0huTUdIMVlCRTR1dUpldkpSMkFrZitYWHpNSzFXVytPUlNQVWdOeGZSbjd6bG96M0ZZMDNxdG1LWXBiOFY0bGRJRmxhR3BnWFA2UVIvQXZrWk02TVFidEpCZ2lNMVBiY0pBT2I0N3JxYXR0b1M1VTRjeTdEZXJNY0RaMU5MUmJjRFR6bmF0dUx4RFF3bGVseWlLYmFLUytWRFF6cGhnT1N2eHlZbjltTk9OSDVSdnNiSWZSWWx2OURGUHpTekRPeFBtM0NmaEpQU1VXbFg3UmY0RDFRR045bGFqa3dibER4dTJaRDRKaTM5VThkU2xsdFU0bWp6SWcwOExXdlRwNmFjeUkrQmxmSFNFZTZVUFFqdHBOdDdjbUpsc2FBTW9FSVRIenJ2ZklSRnBYZUFZbDVMc0NuenlNdUQxYlJKR1cyZTJ6WFkvV1h5ajE1MnFyN1JPR2V2SXlHOEt5UGd1ZldlMTNqTDV6d3dKSFZPa3NSRGtDdHpiK2o0NDdCeCsycFVJVGQzRkhKMVkvV0FiNmJiQ1dIQ05id1ppc2dWTEc4UHVRdzJDbUVYampjVlZmdmNUcm5GM21WMTYzTms3SVJHTTludllndTJqalExWXBrSk16L242QlFTU2ltV0Q4aHMxeWhzVVY4cFNoMG1tbjYzaHZuNGpnNno3VC94ZVVCbXl2RVJLUE5GbkpCM1BlRTJ6dzh6L1NMdEhQTk8wY0FOUDFFSlBzK2JIeDFNM1NpUWl3aDNYM1VheHNndnFmTGRuVElaTjcrOW1lOVdHcFF2OWFMTFhZK2ErdVBKenFrNFB6SENRTjZ4NXh3SlNVSXpObDZoVXJSYWlubythM1R0aHJ0MXhwZTZ0TFVBeWNMTnVSNEg2SjZMNnFnYz0=', '2026-06-08 14:03:22', 15);

-- --------------------------------------------------------

--
-- Table structure for table `exam_extensions`
--

CREATE TABLE `exam_extensions` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `extended_to` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_extensions`
--

INSERT INTO `exam_extensions` (`id`, `job_id`, `extended_to`, `reason`, `created_at`) VALUES
(1, 8, '2026-06-08 16:30:00', NULL, '2026-06-08 14:16:17');

-- --------------------------------------------------------

--
-- Table structure for table `exam_sessions`
--

CREATE TABLE `exam_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `exam_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `cheat_score` int(11) DEFAULT 0,
  `outcome` enum('IN_PROGRESS','CLEAN','FLAGGED','TERMINATED') DEFAULT 'IN_PROGRESS',
  `anti_cheat_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`anti_cheat_log`)),
  `anti_cheat_hash` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `admin_override` tinyint(1) DEFAULT 0,
  `override_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `warning_count` tinyint(3) UNSIGNED DEFAULT 0,
  `violation_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`violation_log`)),
  `word_counts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`word_counts`)),
  `time_per_question` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`time_per_question`)),
  `answer_analytics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answer_analytics`)),
  `is_suspicious` tinyint(1) DEFAULT 0,
  `termination_reason` varchar(255) DEFAULT NULL,
  `tamper_flag` tinyint(1) NOT NULL DEFAULT 0,
  `tamper_detected_at` datetime DEFAULT NULL,
  `verify_code` char(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_sessions`
--

INSERT INTO `exam_sessions` (`id`, `exam_id`, `user_id`, `started_at`, `submitted_at`, `total_score`, `cheat_score`, `outcome`, `anti_cheat_log`, `anti_cheat_hash`, `icp_confirmed`, `admin_override`, `override_reason`, `created_at`, `updated_at`, `warning_count`, `violation_log`, `word_counts`, `time_per_question`, `answer_analytics`, `is_suspicious`, `termination_reason`, `tamper_flag`, `tamper_detected_at`, `verify_code`) VALUES
(2, 4, 17, '2026-06-06 23:02:51', '2026-06-06 23:03:59', 0.00, 0, 'FLAGGED', NULL, NULL, 1, 0, NULL, '2026-06-06 21:02:51', '2026-06-09 11:29:27', 3, '[{\"type\":\"WINDOW_BLUR\",\"msg\":\"Window focus lost \\u2014 possible app switch\",\"ts\":\"2026-06-06T21:02:55.747Z\"},{\"type\":\"WINDOW_BLUR\",\"msg\":\"Window focus lost \\u2014 possible app switch\",\"ts\":\"2026-06-06T21:03:21.011Z\"},{\"type\":\"TAB_SWITCH\",\"msg\":\"Tab or window switch detected\",\"ts\":\"2026-06-06T21:03:21.019Z\"}]', '[]', '[]', '[{\"question_index\":0,\"score\":0,\"feedback\":\"No answer provided.\"},{\"question_index\":1,\"score\":0,\"feedback\":\"No answer provided.\"}]', 1, 'Auto-submitted after 2 warnings', 0, NULL, 'RC-03BA43DBE8'),
(3, 5, 17, '2026-06-07 17:26:16', '2026-06-07 17:29:21', 20.00, 0, 'CLEAN', NULL, NULL, 0, 0, NULL, '2026-06-07 15:26:16', '2026-06-09 11:29:27', 1, '[{\"type\":\"RIGHT_CLICK\",\"msg\":\"Right-click is not allowed during the exam\",\"ts\":\"2026-06-07T15:28:26.092Z\"}]', '[]', '[]', '[{\"question_index\":0,\"score\":0,\"feedback\":\"No answer provided.\"},{\"question_index\":1,\"score\":0,\"feedback\":\"No answer provided.\"}]', 0, 'Voluntarily left exam', 0, NULL, 'RC-E3E5F26003'),
(4, 6, 17, '2026-06-08 16:22:41', '2026-06-08 16:23:16', 40.00, 0, 'FLAGGED', NULL, NULL, 0, 0, NULL, '2026-06-08 14:22:41', '2026-06-09 11:29:27', 2, '{\"violations\":[{\"type\":\"VOICE_DETECTED\",\"msg\":\"Audio\\/voice detected in exam environment\",\"reason\":\"\",\"ts\":\"2026-06-08T14:22:56.333Z\"},{\"type\":\"FACE_NOT_DETECTED\",\"msg\":\"Candidate not visible for extended period\",\"reason\":\"\",\"ts\":\"2026-06-08T14:23:08.664Z\"}],\"voice_log\":[{\"ts\":\"2026-06-08T14:22:56.333Z\",\"volume\":29},{\"ts\":\"2026-06-08T14:22:59.332Z\",\"volume\":44}]}', '[]', '[10]', '[{\"question_index\":0,\"score\":0,\"feedback\":\"No answer provided. Expected: Listen actively, discuss calmly, find a compromise, escalate if needed.\"},{\"question_index\":1,\"score\":0,\"feedback\":\"No answer provided. Expected: Improves productivity, meets deadlines, reduces stress, shows professionalism.\"}]', 1, 'Auto-submitted after 2 warnings', 0, NULL, 'RC-C75C3E8981');

-- --------------------------------------------------------

--
-- Table structure for table `hiring_results`
--

CREATE TABLE `hiring_results` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `winner_user_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`winner_user_ids`)),
  `final_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`final_scores`)),
  `ai_report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_report`)),
  `decided_at` datetime DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `verification_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `tamper_flag` tinyint(1) NOT NULL DEFAULT 0,
  `tamper_detected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integrity_audit_log`
--

CREATE TABLE `integrity_audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `action` varchar(40) NOT NULL,
  `detail` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_sessions`
--

CREATE TABLE `interview_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `transcript` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`transcript`)),
  `violation_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`violation_log`)),
  `behavioral_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`behavioral_log`)),
  `technical_score` decimal(5,2) DEFAULT NULL,
  `communication_score` decimal(5,2) DEFAULT NULL,
  `behavioral_score` decimal(5,2) DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `anomaly_score` decimal(5,2) DEFAULT NULL,
  `attention_score` decimal(5,2) DEFAULT NULL,
  `total_violations` smallint(5) UNSIGNED DEFAULT 0,
  `problem_solving_score` decimal(5,2) DEFAULT NULL,
  `professionalism_score` decimal(5,2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `ai_report` text DEFAULT NULL,
  `transcript_hash` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tamper_flag` tinyint(1) NOT NULL DEFAULT 0,
  `tamper_detected_at` datetime DEFAULT NULL,
  `verify_code` char(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interview_sessions`
--

INSERT INTO `interview_sessions` (`id`, `job_id`, `user_id`, `scheduled_at`, `started_at`, `ended_at`, `transcript`, `violation_log`, `behavioral_log`, `technical_score`, `communication_score`, `behavioral_score`, `confidence_score`, `anomaly_score`, `attention_score`, `total_violations`, `problem_solving_score`, `professionalism_score`, `total_score`, `ai_report`, `transcript_hash`, `icp_confirmed`, `created_at`, `updated_at`, `tamper_flag`, `tamper_detected_at`, `verify_code`) VALUES
(1, 7, 17, NULL, '2026-06-07 17:31:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, '2026-06-07 15:31:19', '2026-06-07 15:31:19', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `company_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `description` longtext NOT NULL,
  `responsibilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`responsibilities`)),
  `required_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_skills`)),
  `required_education` varchar(100) DEFAULT NULL,
  `required_certs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_certs`)),
  `min_experience` int(11) DEFAULT 0,
  `positions_count` int(11) DEFAULT 1,
  `deadline` datetime NOT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `job_type` enum('REMOTE','ONSITE','HYBRID') DEFAULT 'ONSITE',
  `employment_type` enum('FULL_TIME','PART_TIME','CONTRACT') DEFAULT 'FULL_TIME',
  `location` varchar(255) DEFAULT NULL,
  `job_hash` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `marks_published` tinyint(1) DEFAULT 0,
  `status` enum('ACTIVE','SCREENING','EXAMINING','INTERVIEWING','COMPLETED') DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `eligible_educations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligible_educations`)),
  `exam_num_questions` int(10) UNSIGNED DEFAULT 10,
  `exam_time_limit_min` int(10) UNSIGNED DEFAULT 60,
  `exam_open_ended` int(10) UNSIGNED DEFAULT 3,
  `exam_closed_ended` int(10) UNSIGNED DEFAULT 7,
  `exam_sample_doc_path` varchar(500) DEFAULT NULL,
  `exam_start_at` datetime DEFAULT NULL,
  `exam_end_at` datetime DEFAULT NULL,
  `interview_shortlist_n` int(10) UNSIGNED DEFAULT 5,
  `interview_start_at` datetime DEFAULT NULL,
  `interview_time_limit_min` int(10) UNSIGNED DEFAULT 5,
  `interview_max_questions` int(10) UNSIGNED DEFAULT 5,
  `interview_end_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `company_id`, `title`, `department`, `description`, `responsibilities`, `required_skills`, `required_education`, `required_certs`, `min_experience`, `positions_count`, `deadline`, `salary_min`, `salary_max`, `job_type`, `employment_type`, `location`, `job_hash`, `icp_confirmed`, `marks_published`, `status`, `created_at`, `updated_at`, `eligible_educations`, `exam_num_questions`, `exam_time_limit_min`, `exam_open_ended`, `exam_closed_ended`, `exam_sample_doc_path`, `exam_start_at`, `exam_end_at`, `interview_shortlist_n`, `interview_start_at`, `interview_time_limit_min`, `interview_max_questions`, `interview_end_at`) VALUES
(6, 1, 'Full Stack Web Developer', 'Software Development', 'We are seeking a talented Full Stack Web Developer to design, develop, and maintain web applications. The successful candidate will work closely with designers, project managers, and other developers to create efficient, scalable, and user-friendly software solutions. This role involves both frontend and backend development, troubleshooting issues, and implementing new features to improve system performance and user experience.', '[\"Develop and maintain web applications\",\"Design and implement database structures\",\"Write clean, efficient, and well-documented code\",\"Collaborate with UI\\/UX designers and project teams\",\"Debug, test, and optimize applications\",\"Integrate third-party APIs and services\",\"Perform code reviews and ensure coding standards\",\"Maintain application security and data protection\"]', '[\"PHP\",\"Laravel\",\"MySQL\",\"JavaScript\",\"HTML\",\"CSS\",\"Bootstrap\",\"REST APIs\",\"Git\",\"Docker\",\"Problem Solving\",\"Teamwork\"]', 'FROM A1 IN SOFTWARE RELATED FEILD', NULL, 0, 1, '2026-06-06 23:11:00', 10000.00, 100000.00, 'REMOTE', 'FULL_TIME', 'kigali', '75e85a4107178a17f1991b687500ed1ea321c71402571696cdaad4b8aa337ee5', 1, 1, 'EXAMINING', '2026-06-06 20:49:25', '2026-06-06 21:08:00', '[{\"level\":\"ADVANCED DIPLOMA IN ICT\",\"min_experience\":0}]', 5, 18, 5, 10, NULL, '2026-06-06 23:02:00', NULL, 1, NULL, 5, 5, NULL),
(7, 1, 'Software Developer', 'Engineering', 'Develop, maintain, and improve web and software applications. Work closely with product teams to build scalable and reliable solutions.', '[\"Develop and maintain software applications\",\"Write clean and efficient code\",\"Debug and troubleshoot technical issues\",\"Collaborate with designers and product managers\",\"Conduct code reviews\",\"Document technical processes\"]', '[\"PHP\",\"MySQL\",\"JavaScript\",\"Laravel\",\"Git\",\"Docker\"]', 'advanced diploma in ict', NULL, 0, 1, '2026-06-07 17:23:00', NULL, NULL, 'ONSITE', 'FULL_TIME', 'kigali', '0387c314f64797137d63e83ca53415938b11ab8e6899eb0e962f60376036640f', 0, 1, 'INTERVIEWING', '2026-06-07 15:17:22', '2026-06-07 15:30:54', '[{\"level\":\"advanced diploma in ict\",\"min_experience\":0}]', 5, 18, 5, 10, NULL, '2026-06-07 17:26:00', NULL, 5, NULL, 5, 5, NULL),
(8, 1, 'Full Stack Web Developer', 'Software Development', 'We are seeking a talented Full Stack Web Developer to design, develop, and maintain web applications. The successful candidate will work closely with designers, project managers, and other developers to create efficient, scalable, and user-friendly software solutions. This role involves both frontend and backend development, troubleshooting issues, and implementing new features to improve system performance and user experience.', '[\"Develop and maintain web applications\",\"Design and implement database structures\",\"Write clean, efficient, and well-documented code\",\"Collaborate with UI\\/UX designers and project teams\",\"Debug, test, and optimize applications\",\"Integrate third-party APIs and services\",\"Perform code reviews and ensure coding standards\",\"Maintain application security and data protection\"]', '[\"PHP\",\"Laravel\",\"MySQL\",\"JavaScript\",\"HTML\",\"CSS\",\"Bootstrap\",\"REST APIs\",\"Git\",\"Docker\",\"Problem Solving\",\"Teamwork\"]', 'FROM A1 IN SOFTWARE RELATED FEILD', NULL, 0, 1, '2026-06-08 16:03:00', NULL, NULL, 'ONSITE', 'FULL_TIME', '', '65fab20e5866513b6f233f0cdc672faa6c27914cd26e02a976d508defc24df6f', 0, 0, 'EXAMINING', '2026-06-08 13:57:20', '2026-06-08 14:46:57', '[{\"level\":\"FROM A1 IN SOFTWARE RELATED FEILD\",\"min_experience\":0}]', 5, 18, 5, 10, NULL, '2026-06-08 16:10:00', '2026-06-08 16:30:00', 5, NULL, 5, 5, NULL),
(9, 1, 'FULL STACK DEVELOPER', 'Engineering', 'We are seeking a talented Full Stack Web Developer to design, develop, and maintain web applications. The successful candidate will work closely with designers, project managers, and other developers to create efficient, scalable, and user-friendly software solutions. This role involves both frontend and backend development, troubleshooting issues, and implementing new features to improve system performance and user experience.', '[\"Develop and maintain web applications\",\"Design and implement database structures\",\"Write clean, efficient, and well-documented code\",\"Collaborate with UI\\/UX designers and project teams\",\"Debug, test, and optimize applications\",\"Integrate third-party APIs and services\",\"Perform code reviews and ensure coding standards\",\"Maintain application security and data protection\"]', '[\"PHP\",\"Laravel\",\"MySQL\",\"JavaScript\",\"HTML\",\"CSS\",\"Bootstrap\",\"REST APIs\",\"Git\",\"Docker\",\"Problem Solving\",\"Teamwork\"]', 'Bachelor\'s degree in Computer Science, Software Engineering, or related field.', NULL, 0, 1, '2026-06-11 12:24:00', NULL, NULL, 'ONSITE', 'FULL_TIME', '', 'd02b907a4b199caa5ea7e6ed2ae487f92c837181af79a205657b1a6c874b53d1', 1, 0, 'ACTIVE', '2026-06-09 10:24:28', '2026-06-09 10:24:31', '[{\"level\":\"Bachelor\'s degree in Computer Science, Software Engineering, or related field.\",\"min_experience\":0}]', 15, 90, 5, 10, NULL, NULL, NULL, 5, NULL, 5, 5, NULL),
(10, 1, 'hjjjjjj', 'kjjjjjjjjjjj', 'jkkkkkkkkkkkkkkkkkh', '[\"jhhhh\",\"hjjjjjjj\"]', '[\"PHP\",\"Laravel\",\"MySQL\",\"JavaScript\",\"HTML\",\"CSS\",\"Bootstrap\",\"REST APIs\",\"Git\",\"Docker\",\"Problem Solving\",\"Teamwork\"]', 'Bachelor\'s degree in Computer Science, Software Engineering, or related field.', NULL, 0, 1, '2026-06-27 11:46:00', NULL, NULL, 'ONSITE', 'FULL_TIME', '', 'ef94d934456124d7723e52d394dd1248aec14ac3334816725fe34796624e001c', 1, 0, 'ACTIVE', '2026-06-11 09:46:23', '2026-06-11 09:46:26', '[{\"level\":\"Bachelor\'s degree in Computer Science, Software Engineering, or related field.\",\"min_experience\":0}]', 15, 90, 5, 10, NULL, NULL, NULL, 5, NULL, 5, 5, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `queue_jobs`
--

CREATE TABLE `queue_jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status` enum('PENDING','PROCESSING','DONE','FAILED') DEFAULT 'PENDING',
  `error` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue_jobs`
--

INSERT INTO `queue_jobs` (`id`, `type`, `payload`, `status`, `error`, `started_at`, `finished_at`, `created_at`) VALUES
(18, 'trigger_ai_screening', '{\"jobId\":3}', 'DONE', NULL, '2026-06-05 10:22:09', '2026-06-05 10:22:09', '2026-06-05 08:22:09'),
(19, 'trigger_ai_screening', '{\"jobId\":3}', 'DONE', NULL, '2026-06-05 10:22:39', '2026-06-05 10:22:39', '2026-06-05 08:22:39'),
(20, 'trigger_ai_screening', '{\"jobId\":4}', 'DONE', NULL, '2026-06-05 10:30:09', '2026-06-05 10:30:09', '2026-06-05 08:30:09'),
(21, 'trigger_ai_screening', '{\"jobId\":5}', 'DONE', NULL, '2026-06-05 10:33:09', '2026-06-05 10:33:37', '2026-06-05 08:33:09'),
(22, 'generate_exam', '{\"jobId\":5}', 'FAILED', 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'total_points\' in \'field list\'', '2026-06-05 10:33:37', '2026-06-05 10:33:38', '2026-06-05 08:33:37'),
(23, 'trigger_ai_screening', '{\"jobId\":5}', 'DONE', NULL, '2026-06-05 10:36:08', '2026-06-05 10:36:30', '2026-06-05 08:35:40'),
(24, 'generate_exam', '{\"jobId\":5}', 'FAILED', 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'total_points\' in \'field list\'', '2026-06-05 10:36:30', '2026-06-05 10:36:30', '2026-06-05 08:36:30'),
(25, 'generate_exam', '{\"job_id\":5}', 'FAILED', 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'total_points\' in \'field list\'', '2026-06-05 10:38:00', '2026-06-05 10:38:00', '2026-06-05 08:37:44'),
(26, 'trigger_ai_screening', '{\"jobId\":5}', 'DONE', NULL, '2026-06-05 10:43:31', '2026-06-05 10:43:48', '2026-06-05 08:43:02'),
(27, 'generate_exam', '{\"jobId\":5}', 'FAILED', 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'total_points\' in \'field list\'', '2026-06-05 10:43:48', '2026-06-05 10:43:48', '2026-06-05 08:43:48'),
(28, 'trigger_ai_screening', '{\"jobId\":5}', 'DONE', NULL, '2026-06-05 10:51:48', '2026-06-05 10:52:02', '2026-06-05 08:51:21'),
(29, 'generate_exam', '{\"jobId\":5}', 'DONE', NULL, '2026-06-05 10:52:02', '2026-06-05 10:52:02', '2026-06-05 08:52:02'),
(30, 'trigger_ai_screening', '{\"jobId\":6}', 'DONE', NULL, '2026-06-06 22:56:51', '2026-06-06 22:57:02', '2026-06-06 20:56:30'),
(31, 'generate_exam', '{\"jobId\":6}', 'DONE', NULL, '2026-06-06 22:57:02', '2026-06-06 22:57:02', '2026-06-06 20:57:02'),
(32, 'trigger_ai_screening', '{\"jobId\":7}', 'DONE', NULL, '2026-06-07 17:23:01', '2026-06-07 17:23:10', '2026-06-07 15:23:01'),
(33, 'generate_exam', '{\"jobId\":7}', 'DONE', NULL, '2026-06-07 17:23:10', '2026-06-07 17:23:10', '2026-06-07 15:23:10'),
(34, 'trigger_ai_screening', '{\"jobId\":8}', 'DONE', NULL, '2026-06-08 16:03:09', '2026-06-08 16:03:21', '2026-06-08 14:03:09'),
(35, 'generate_exam', '{\"jobId\":8}', 'DONE', NULL, '2026-06-08 16:03:21', '2026-06-08 16:03:22', '2026-06-08 14:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `screening_results`
--

CREATE TABLE `screening_results` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `skills_score` decimal(5,2) DEFAULT NULL,
  `experience_score` decimal(5,2) DEFAULT NULL,
  `education_score` decimal(5,2) DEFAULT NULL,
  `credentials_score` decimal(5,2) DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `is_shortlisted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screening_results`
--

INSERT INTO `screening_results` (`id`, `application_id`, `job_id`, `total_score`, `skills_score`, `experience_score`, `education_score`, `credentials_score`, `explanation`, `is_shortlisted`, `created_at`) VALUES
(15, 4, 6, 82.00, 22.00, 18.00, 22.00, 20.00, 'The candidate demonstrates strong alignment with required skills including PHP, JavaScript, MySQL, HTML, CSS, REST APIs, Git, Docker fundamentals, and problem-solving. They have hands-on freelance experience building full-stack web applications, though formal work experience is listed as 0 years. Their Advanced Diploma in ICT meets the education requirement, and while no specific certifications are listed, their project portfolio and technical breadth support a solid credentials score.', 1, '2026-06-06 20:57:02'),
(17, 5, 7, 72.50, 20.00, 17.50, 25.00, 10.00, 'The candidate holds an Advanced Diploma in ICT, perfectly matching the required education. They demonstrate strong skills in PHP, MySQL, JavaScript, and Docker fundamentals, though Laravel is not explicitly mentioned. Their freelance projects show real-world full-stack development experience, but the lack of formal years of experience and no listed certifications limits those scores.', 1, '2026-06-07 15:23:10'),
(19, 6, 8, 82.00, 22.00, 18.00, 20.00, 22.00, 'The candidate demonstrates strong alignment with required skills including PHP, JavaScript, MySQL, HTML/CSS, REST APIs, Git, Docker fundamentals, and problem-solving. They have hands-on freelance experience building full-stack web applications, though formal work experience is limited. Their ICT education is in progress at a relevant institution, and while no formal certifications are listed, their project portfolio serves as strong practical credentials.', 1, '2026-06-08 14:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `data` text DEFAULT NULL,
  `timestamp` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('SEEKER','COMPANY','ADMIN') NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `face_descriptor` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`face_descriptor`)),
  `face_verified` tinyint(1) DEFAULT 0,
  `face_image_path` varchar(500) DEFAULT NULL,
  `cv_path` varchar(500) DEFAULT NULL,
  `cv_hash` char(64) DEFAULT NULL,
  `identity_hash` char(64) DEFAULT NULL,
  `icp_confirmed` tinyint(1) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspended_reason` text DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `national_id` varchar(50) DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `residence_district` varchar(100) DEFAULT NULL,
  `residence_sector` varchar(100) DEFAULT NULL,
  `residence_cell` varchar(100) DEFAULT NULL,
  `residence_village` varchar(100) DEFAULT NULL,
  `profile_complete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `full_name`, `phone`, `country`, `date_of_birth`, `face_descriptor`, `face_verified`, `face_image_path`, `cv_path`, `cv_hash`, `identity_hash`, `icp_confirmed`, `is_suspended`, `suspended_reason`, `login_attempts`, `locked_until`, `created_at`, `updated_at`, `national_id`, `father_name`, `mother_name`, `gender`, `place_of_birth`, `residence_district`, `residence_sector`, `residence_cell`, `residence_village`, `profile_complete`) VALUES
(1, 'danny@gmail.com', '$2y$10$AX0Vi7TUbBTcoetg1o4TKOfiQ6Q6nfvvRMv.04jDdMuXTBQWAGlve', 'ADMIN', 'System Admin', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0, 0, NULL, 4, NULL, '2026-05-14 12:52:19', '2026-05-29 06:53:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(10, 'ykdann54@gmail.com', '$2y$10$zkeag2JVVgzfjwnCVPJWGuvAKVb5UJV56qzFHaF4GqKm5A1Eak7ea', 'COMPANY', 'danny', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, '2026-05-29 06:50:14', '2026-06-01 22:16:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(17, 'ykdann53@gmail.com', '$2y$10$bCmzAIw.tV/3FRgam/Wa9uXp5qcMyTExHVoZAVHYdfGU3KFu1kPNi', 'SEEKER', 'Mbarushimana Danny', '0785498054', NULL, '2003-09-25', '[[-0.14733587205410004,0.14200763404369354,0.10724661499261856,-0.04465266689658165,-0.04914010688662529,-0.09473782777786255,0.08790718019008636,-0.10636626183986664,0.19991742074489594,-0.06835486739873886,0.24219360947608948,-0.0348924957215786,-0.14981609582901,-0.15384870767593384,0.07127126306295395,0.13951878249645233,-0.17300161719322205,-0.15939469635486603,-0.025634748861193657,-0.1169331818819046,0.002772608771920204,0.0008092154748737812,0.0006309003802016377,0.047109220176935196,-0.07551425695419312,-0.26252830028533936,-0.10870326310396194,-0.19440406560897827,0.10468649864196777,-0.12024331092834473,0.04270995408296585,0.077288918197155,-0.17690107226371765,-0.09053648263216019,-0.029990900307893753,0.035176150500774384,-0.013476002030074596,-0.030407739803195,0.14902687072753906,0.0243140310049057,-0.17966309189796448,-0.09113234281539917,-0.049733806401491165,0.29975488781929016,0.15796029567718506,-0.004765787161886692,-0.0021750752348452806,-0.003595725866034627,0.02266085334122181,-0.12707959115505219,0.05324448272585869,0.10349248349666595,0.15269386768341064,0.062159061431884766,-0.055051859468221664,-0.12973952293395996,-0.004953226540237665,0.03230540081858635,-0.23426496982574463,0.03372345492243767,0.13357862830162048,-0.13574673235416412,-0.09281516820192337,0.003214367898181081,0.2534531354904175,0.11724873632192612,-0.08649325370788574,-0.14611558616161346,0.22066310048103333,-0.12968160212039948,-0.06234697252511978,0.09557872265577316,-0.1353015899658203,-0.10012193024158478,-0.1851501315832138,0.08080818504095078,0.3825378119945526,0.07801505923271179,-0.1417950838804245,-0.05611032247543335,-0.2200818806886673,0.015139265917241573,-0.03775520622730255,0.07487672567367554,-0.09110061079263687,0.040035296231508255,-0.06442811340093613,-0.017026111483573914,0.12478423118591309,-0.0273748729377985,-0.055651914328336716,0.1988675445318222,-0.03272607550024986,-0.019726967439055443,-0.016443859785795212,-0.08136196434497833,0.013546518981456757,0.022956550121307373,-0.07141584903001785,-0.11485011130571365,0.02252693474292755,-0.13077056407928467,-0.020269734784960747,0.1355505734682083,-0.24581760168075562,0.18856225907802582,0.06752369552850723,0.017935454845428467,-0.005152533296495676,0.04783789440989494,-0.0590052455663681,-0.07001367956399918,0.19190660119056702,-0.1868651956319809,0.20112256705760956,0.2203780561685562,0.07855651527643204,0.1320032775402069,0.03358544036746025,0.09908907115459442,-0.018945999443531036,-0.11181586980819702,-0.14452993869781494,0.00026578200049698353,0.09625586122274399,-0.023328620940446854,0.03177928924560547,0.047105129808187485],[-0.14632584154605865,0.16850265860557556,0.08511876314878464,-0.03146478533744812,-0.05717158317565918,-0.04403281956911087,0.09930327534675598,-0.10331442952156067,0.20749863982200623,-0.07687894254922867,0.24582907557487488,-0.011115235276520252,-0.1569119244813919,-0.15291480720043182,0.09752319753170013,0.1589231938123703,-0.20004452764987946,-0.18815527856349945,-0.057269398123025894,-0.07303240150213242,-0.010263732634484768,0.01587873511016369,0.004751367028802633,0.08788236230611801,-0.11445032805204391,-0.2788752615451813,-0.10250954329967499,-0.1958359181880951,0.15273912250995636,-0.07934939861297607,0.01627824455499649,0.061792392283678055,-0.18489086627960205,-0.037864405661821365,-0.017069632187485695,0.030143696814775467,0.004568684846162796,-0.04360730201005936,0.1409851610660553,-0.008756065741181374,-0.23057176172733307,-0.06723363697528839,0.004043171182274818,0.283473402261734,0.20170457661151886,-0.02493077889084816,-0.0037792790681123734,0.001151916105300188,0.053161416202783585,-0.12727531790733337,0.06982146948575974,0.08586855977773666,0.1653067022562027,0.05720824375748634,-0.024546684697270393,-0.1639544665813446,-0.022004833444952965,0.047618597745895386,-0.21982479095458984,0.019611230120062828,0.08786292374134064,-0.15484501421451569,-0.0798954963684082,0.0010787341743707657,0.26012325286865234,0.09988255053758621,-0.10391172766685486,-0.17342375218868256,0.2125900238752365,-0.16913896799087524,-0.026451651006937027,0.12384436279535294,-0.15666836500167847,-0.06141502782702446,-0.2678561508655548,0.06787994503974915,0.37051743268966675,0.054972320795059204,-0.12525279819965363,-0.0002985231694765389,-0.16441768407821655,0.04026130959391594,-0.0462624654173851,0.13361801207065582,-0.09049269556999207,-0.014622913673520088,-0.09646518528461456,-0.015181956812739372,0.1442306637763977,-0.035480108112096786,-0.02039410173892975,0.20212841033935547,-0.038861896842718124,-0.048186998814344406,-0.03075324557721615,-0.05773492157459259,-0.012520145624876022,-0.034961599856615067,-0.03311162814497948,-0.07971438020467758,-0.01698066107928753,-0.09007392823696136,-0.0382416807115078,0.1474325805902481,-0.24071744084358215,0.12680700421333313,0.05955233797430992,0.029751794412732124,0.027964048087596893,0.041790805757045746,-0.047913286834955215,-0.11195435374975204,0.14884786307811737,-0.2185571789741516,0.1924530565738678,0.17697471380233765,0.04495209455490112,0.10908053815364838,0.012440033257007599,0.11085352301597595,-0.04307038336992264,-0.06929784268140793,-0.1452665477991104,0.015975015237927437,0.1150922030210495,-0.08560921251773834,0.02054482512176037,0.0321083664894104],[-0.14910253882408142,0.16486988961696625,0.1051882803440094,-0.033344343304634094,-0.08397859334945679,-0.05792488902807236,0.09052034467458725,-0.1114809662103653,0.23217114806175232,-0.08551214635372162,0.2417783886194229,-0.006927966605871916,-0.15444882214069366,-0.12596161663532257,0.07346548140048981,0.16714982688426971,-0.19727487862110138,-0.19557777047157288,-0.05536071956157684,-0.0832248106598854,-0.010275471955537796,-0.005132913123816252,-0.010076623409986496,0.058529578149318695,-0.11707556992769241,-0.2733415365219116,-0.10983770340681076,-0.18840326368808746,0.0994352251291275,-0.09881357103586197,0.01937813311815262,0.07905855029821396,-0.17534123361110687,-0.056247781962156296,-0.024035964161157608,0.03589293733239174,-0.020555665716528893,-0.04850570112466812,0.10529349744319916,-0.016582105308771133,-0.21729470789432526,-0.06725836545228958,-0.0017569129122421145,0.27238014340400696,0.21184130012989044,-0.011519832536578178,-0.005121121648699045,-0.017799001187086105,0.03497755527496338,-0.12997056543827057,0.055633384734392166,0.07881423830986023,0.12197007238864899,0.07538814842700958,-0.010781570337712765,-0.1574895977973938,-0.016408951953053474,0.04072599858045578,-0.20475177466869354,0.0075383312068879604,0.08384360373020172,-0.16237998008728027,-0.09087612479925156,-0.011108462698757648,0.26326555013656616,0.12006870657205582,-0.08754770457744598,-0.15956860780715942,0.2165864109992981,-0.15825049579143524,-0.06169845536351204,0.09160693734884262,-0.15553182363510132,-0.07588264346122742,-0.25565069913864136,0.05490386113524437,0.3893672227859497,0.04612024873495102,-0.12974092364311218,-0.003034485736861825,-0.15024811029434204,0.028369300067424774,-0.05571725592017174,0.12349846959114075,-0.09201472997665405,0.0013091647997498512,-0.08950864523649216,-0.018920594826340675,0.1571335345506668,-0.03834306448698044,-0.02855270355939865,0.192661851644516,-0.051438264548778534,-0.06473841518163681,-0.007626502308994532,-0.05944886431097984,-0.00306471879594028,-0.022403337061405182,-0.06497955322265625,-0.11900390684604645,0.008447257801890373,-0.1065705344080925,-0.0664139986038208,0.13474878668785095,-0.23689717054367065,0.13094155490398407,0.06006447225809097,0.028804386034607887,0.0005578113486990333,0.016838248819112778,-0.05080311372876167,-0.0814051479101181,0.17535468935966492,-0.20477284491062164,0.20622673630714417,0.1910007894039154,0.05212843045592308,0.12483347952365875,0.011357936076819897,0.0968380719423294,-0.047420162707567215,-0.07098419219255447,-0.13445310294628143,0.01199624128639698,0.11046987771987915,-0.05957166850566864,0.027530519291758537,0.04564102739095688],[-0.13524234294891357,0.15066443383693695,0.09564054012298584,-0.0460566021502018,-0.07224497199058533,-0.06024464964866638,0.08352124691009521,-0.12425999343395233,0.2296130657196045,-0.09534377604722977,0.25014859437942505,0.012213265523314476,-0.1600179523229599,-0.12935484945774078,0.05431918427348137,0.1769615262746811,-0.22171640396118164,-0.1944272816181183,-0.05368535965681076,-0.08845125883817673,-0.006401756778359413,0.019194740802049637,0.014836649410426617,0.07311713695526123,-0.11850499361753464,-0.27011963725090027,-0.10883650928735733,-0.186509370803833,0.12347736954689026,-0.07397553324699402,0.00903328787535429,0.08596360683441162,-0.1905156522989273,-0.03995462879538536,-0.006616481579840183,0.021147536113858223,-0.0053492020815610886,-0.05690772458910942,0.11054766178131104,-0.01239826437085867,-0.2216341495513916,-0.07667765021324158,0.017360525205731392,0.2564539611339569,0.2004934400320053,-0.017092181369662285,-0.006500506307929754,-0.031116507947444916,0.027285415679216385,-0.12798355519771576,0.058916110545396805,0.0742901861667633,0.12948772311210632,0.06783375889062881,-0.01689339242875576,-0.18061517179012299,-0.026062361896038055,0.04739491268992424,-0.20110812783241272,0.008141819387674332,0.06153136119246483,-0.16592685878276825,-0.09146086126565933,-0.026943761855363846,0.26833438873291016,0.10817628353834152,-0.10863855481147766,-0.1549937129020691,0.22148974239826202,-0.16125069558620453,-0.045347943902015686,0.10447035729885101,-0.14863304793834686,-0.07176563888788223,-0.2584572434425354,0.033622901886701584,0.38303112983703613,0.048423219472169876,-0.12075517326593399,0.01501515507698059,-0.15551549196243286,0.027696454897522926,-0.06789236515760422,0.13818134367465973,-0.0970892459154129,0.016727611422538757,-0.07583290338516235,-0.018539898097515106,0.14969226717948914,-0.0367058627307415,-0.026580151170492172,0.21289250254631042,-0.06094736233353615,-0.05054512247443199,-0.008713558316230774,-0.05528297275304794,-0.007486790884286165,-0.038071323186159134,-0.055182911455631256,-0.10390107333660126,-0.02032453939318657,-0.10623069107532501,-0.06379158049821854,0.12938790023326874,-0.23197384178638458,0.10887745022773743,0.06194962561130524,0.01761399582028389,0.018441302701830864,0.025758761912584305,-0.04661672189831734,-0.1091068759560585,0.1594303399324417,-0.22632211446762085,0.20868441462516785,0.19140058755874634,0.06260165572166443,0.14299513399600983,0.005920272320508957,0.10748203843832016,-0.05412788689136505,-0.08362418413162231,-0.13136553764343262,0.013824664987623692,0.12561310827732086,-0.09164255857467651,0.0344039648771286,0.035429153591394424],[-0.1421363651752472,0.14968232810497284,0.0994039848446846,-0.01472214050590992,-0.07607179135084152,-0.06734874844551086,0.09135375916957855,-0.11961156874895096,0.2231871634721756,-0.09354183077812195,0.23452246189117432,-0.0011951462365686893,-0.15171600878238678,-0.13982059061527252,0.04827776178717613,0.18288488686084747,-0.21745944023132324,-0.19408173859119415,-0.06076119467616081,-0.09708469361066818,-0.008084078319370747,0.02033168636262417,-0.006694153416901827,0.06280187517404556,-0.12640705704689026,-0.2616807222366333,-0.10340521484613419,-0.1792675107717514,0.1125173270702362,-0.08628778159618378,0.02660040743649006,0.0993509367108345,-0.18050070106983185,-0.042730920016765594,-0.016139086335897446,0.023679787293076515,0.009587586857378483,-0.032915882766246796,0.11075893044471741,-0.01757524535059929,-0.21685928106307983,-0.060875385999679565,0.0028704162687063217,0.2668692171573639,0.20661909878253937,-0.008822744712233543,-0.015736281871795654,-0.01678813062608242,0.03308673948049545,-0.13895617425441742,0.04579758644104004,0.0793137326836586,0.12103428691625595,0.07007903605699539,-0.0351417139172554,-0.1660223752260208,-0.040026500821113586,0.04820762574672699,-0.20555265247821808,0.004576260223984718,0.07088332623243332,-0.17236272990703583,-0.08132785558700562,-0.0025474466383457184,0.25618690252304077,0.10781555622816086,-0.09796424955129623,-0.15361066162586212,0.22826509177684784,-0.18142572045326233,-0.037513647228479385,0.09927402436733246,-0.13710173964500427,-0.06226187199354172,-0.27144184708595276,0.04761048033833504,0.3919296860694885,0.05194400995969772,-0.12467726320028305,-0.01641765981912613,-0.1614961177110672,0.047524262219667435,-0.05765169486403465,0.11263006180524826,-0.10111962258815765,-0.006403276231139898,-0.0933704674243927,-0.025928808376193047,0.15204140543937683,-0.02236815355718136,-0.034816961735486984,0.20938868820667267,-0.035696517676115036,-0.06424277275800705,-0.009716962464153767,-0.05761765316128731,-0.00465499609708786,-0.05048239231109619,-0.05063723772764206,-0.1093461886048317,-0.015458166599273682,-0.09343378245830536,-0.04864116385579109,0.15163345634937286,-0.20634080469608307,0.12972204387187958,0.04550689086318016,0.027584265917539597,0.0073226564563810825,0.04927586019039154,-0.0459921658039093,-0.11083082109689713,0.16390570998191833,-0.1934884786605835,0.1891731172800064,0.18154321610927582,0.043770600110292435,0.13548770546913147,-9.763825073605403e-5,0.1250193864107132,-0.07838306576013565,-0.07441256195306778,-0.1158275306224823,0.005245477892458439,0.11727766692638397,-0.07863082736730576,0.04411039501428604,0.038472674787044525]]', 1, NULL, 'storage/uploads/cv/5d3a9ba283bcef38233f10d24927e39c.pdf', 'edbbae0189448a6a7289e204fb5685ea2cabc2b04580970dd220fa7a1b5943e3', '47f63ed5c3b7c0b70190233a8cfc45810fe51f186b1ff70f36e6475cd336fd3a', 0, 0, NULL, 0, NULL, '2026-06-01 19:23:12', '2026-06-05 08:41:20', '1200380077954088', 'HIRWA Jean Claude', 'Mukantigura Anastasie', 'Male', 'Rwanda', NULL, NULL, NULL, NULL, 0),
(18, 'admin@recruitchain.app', '$2y$10$GQLkb4qwKvEf3Wk2rU3RV.TzO/2qkHaDfvab8ATXrSHnLpeU80z0a', 'ADMIN', 'System Admin', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, '2026-06-01 19:25:00', '2026-06-01 19:25:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_certificates`
--

CREATE TABLE `user_certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `issuer` varchar(255) NOT NULL,
  `year_issued` year(4) DEFAULT NULL,
  `cert_path` varchar(500) DEFAULT NULL,
  `cert_hash` char(64) DEFAULT NULL,
  `ai_suggested_title` varchar(255) DEFAULT NULL,
  `ai_match_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `ai_match_ok` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ai_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_certificates`
--

INSERT INTO `user_certificates` (`id`, `user_id`, `title`, `issuer`, `year_issued`, `cert_path`, `cert_hash`, `ai_suggested_title`, `ai_match_score`, `ai_match_ok`, `created_at`, `ai_notes`) VALUES
(1, 17, 'Responsible AI', 'Microsoft Learn', '2026', 'storage/uploads/credentials/a5746ea556d8834153412f28.pdf', 'd49f2fdbf8ce98797a9b5043e388e0681788cf0c2cbbebef952d51ec808755e7', 'Responsible AI', 100, 1, '2026-06-01 21:00:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_disability`
--

CREATE TABLE `user_disability` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `has_disability` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_disability`
--

INSERT INTO `user_disability` (`id`, `user_id`, `has_disability`, `description`, `created_at`) VALUES
(1, 17, 0, NULL, '2026-06-01 20:41:18');

-- --------------------------------------------------------

--
-- Table structure for table `user_education`
--

CREATE TABLE `user_education` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `degree_title` varchar(255) NOT NULL,
  `institution` varchar(255) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `year_completed` year(4) DEFAULT NULL,
  `cert_path` varchar(500) DEFAULT NULL,
  `cert_hash` char(64) DEFAULT NULL,
  `ai_suggested_title` varchar(255) DEFAULT NULL,
  `ai_match_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `ai_match_ok` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ai_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_education`
--

INSERT INTO `user_education` (`id`, `user_id`, `degree_title`, `institution`, `country`, `year_completed`, `cert_path`, `cert_hash`, `ai_suggested_title`, `ai_match_score`, `ai_match_ok`, `created_at`, `ai_notes`) VALUES
(3, 17, 'ADVANCED DIPLOMA IN ICT', 'RP-KARONGI COLLEGE', 'RWANDA', '2025', 'storage/uploads/credentials/74de2c23479686047eae0d9b.pdf', '8150e5f0285bff8f7df0741b095317d7ee71f84ae7271e669378e443dc53f4f7', 'ADVANCED DIPLOMA IN INFORMATION AND COMMUNICATION TECHNOLOGY', 82, 1, '2026-06-05 08:13:59', 'The document presents as an Advanced Diploma from Rwanda Polytechnic, featuring numerous strong authenticity indicators including official seals, logos, signatures, a QR code, and a verification URL. While the award year and \'true copy\' date are in the future, aligning with the user\'s provided completion year, there are no visible signs of digital manipulation or AI generation. The overall presentation suggests a credible document, pending external verification via the provided URL.');

-- --------------------------------------------------------

--
-- Table structure for table `user_experience`
--

CREATE TABLE `user_experience` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_languages`
--

CREATE TABLE `user_languages` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `language` varchar(100) NOT NULL,
  `reading` enum('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
  `writing` enum('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
  `speaking` enum('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_languages`
--

INSERT INTO `user_languages` (`id`, `user_id`, `language`, `reading`, `writing`, `speaking`, `created_at`) VALUES
(1, 17, 'ENGLISH', 'Excellent', 'Excellent', 'Very Good', '2026-06-01 20:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_publications`
--

CREATE TABLE `user_publications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(500) NOT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `year_published` year(4) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_referees`
--

CREATE TABLE `user_referees` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_referees`
--

INSERT INTO `user_referees` (`id`, `user_id`, `full_name`, `position`, `organization`, `phone`, `email`, `created_at`) VALUES
(1, 17, 'Danny Mbarushimana', 'CEO', 'INGANZO LABS', '0785498054', 'ykdann53@gmail.com', '2026-06-05 08:21:08'),
(2, 17, 'Kimenyi Emable', 'CEO', 'algo', '0785498054', 'ykdann53@gmail.com', '2026-06-05 08:21:39'),
(3, 17, 'Viviana Marcel', 'CEO', 'bus', '0785498054', 'ykdann53@gmail.com', '2026-06-05 08:22:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_application` (`job_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_id` (`job_id`);

--
-- Indexes for table `exam_extensions`
--
ALTER TABLE `exam_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `verify_code` (`verify_code`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `hiring_results`
--
ALTER TABLE `hiring_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_id` (`job_id`);

--
-- Indexes for table `integrity_audit_log`
--
ALTER TABLE `integrity_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `interview_sessions`
--
ALTER TABLE `interview_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `verify_code` (`verify_code`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);
ALTER TABLE `jobs` ADD FULLTEXT KEY `ft_search` (`title`,`description`);

--
-- Indexes for table `queue_jobs`
--
ALTER TABLE `queue_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `screening_results`
--
ALTER TABLE `screening_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_screening_application` (`application_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_certificates`
--
ALTER TABLE `user_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_disability`
--
ALTER TABLE `user_disability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_education`
--
ALTER TABLE `user_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_experience`
--
ALTER TABLE `user_experience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_languages`
--
ALTER TABLE `user_languages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_publications`
--
ALTER TABLE `user_publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_referees`
--
ALTER TABLE `user_referees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `exam_extensions`
--
ALTER TABLE `exam_extensions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hiring_results`
--
ALTER TABLE `hiring_results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integrity_audit_log`
--
ALTER TABLE `integrity_audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interview_sessions`
--
ALTER TABLE `interview_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `queue_jobs`
--
ALTER TABLE `queue_jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `screening_results`
--
ALTER TABLE `screening_results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_certificates`
--
ALTER TABLE `user_certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_disability`
--
ALTER TABLE `user_disability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_education`
--
ALTER TABLE `user_education`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_experience`
--
ALTER TABLE `user_experience`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_languages`
--
ALTER TABLE `user_languages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_publications`
--
ALTER TABLE `user_publications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_referees`
--
ALTER TABLE `user_referees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`),
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`);

--
-- Constraints for table `exam_extensions`
--
ALTER TABLE `exam_extensions`
  ADD CONSTRAINT `exam_extensions_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_sessions`
--
ALTER TABLE `exam_sessions`
  ADD CONSTRAINT `exam_sessions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `exam_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `hiring_results`
--
ALTER TABLE `hiring_results`
  ADD CONSTRAINT `hiring_results_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`);

--
-- Constraints for table `interview_sessions`
--
ALTER TABLE `interview_sessions`
  ADD CONSTRAINT `interview_sessions_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`),
  ADD CONSTRAINT `interview_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `screening_results`
--
ALTER TABLE `screening_results`
  ADD CONSTRAINT `screening_results_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`);

--
-- Constraints for table `user_certificates`
--
ALTER TABLE `user_certificates`
  ADD CONSTRAINT `user_certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_disability`
--
ALTER TABLE `user_disability`
  ADD CONSTRAINT `user_disability_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_education`
--
ALTER TABLE `user_education`
  ADD CONSTRAINT `user_education_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_experience`
--
ALTER TABLE `user_experience`
  ADD CONSTRAINT `user_experience_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_languages`
--
ALTER TABLE `user_languages`
  ADD CONSTRAINT `user_languages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_publications`
--
ALTER TABLE `user_publications`
  ADD CONSTRAINT `user_publications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_referees`
--
ALTER TABLE `user_referees`
  ADD CONSTRAINT `user_referees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
