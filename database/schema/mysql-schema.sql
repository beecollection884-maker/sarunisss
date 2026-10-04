/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `academic_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_calendars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `semester` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'event_sekolah',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_holiday` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_calendars_created_by_foreign` (`created_by`),
  KEY `academic_calendars_academic_year_semester_start_date_index` (`academic_year`,`semester`,`start_date`),
  KEY `academic_calendars_academic_year_semester_type_index` (`academic_year`,`semester`,`type`),
  CONSTRAINT `academic_calendars_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_verification_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `portal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'password_reset',
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reset_token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_verification_codes_email_portal_purpose_index` (`email`,`portal`,`purpose`),
  KEY `auth_verification_codes_email_index` (`email`),
  KEY `auth_verification_codes_portal_index` (`portal`),
  KEY `auth_verification_codes_purpose_index` (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `school_class_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `recorded_by_teacher_id` bigint unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_att_unique_day` (`school_class_id`,`student_id`,`attendance_date`),
  KEY `class_attendances_student_id_foreign` (`student_id`),
  KEY `class_attendances_recorded_by_teacher_id_foreign` (`recorded_by_teacher_id`),
  CONSTRAINT `class_attendances_recorded_by_teacher_id_foreign` FOREIGN KEY (`recorded_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_attendances_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `offline_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `offline_device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `school_class_id` bigint unsigned NOT NULL,
  `teaching_assignment_id` bigint unsigned DEFAULT NULL,
  `attendance_type` enum('class','subject') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'class',
  `attendance_date` date NOT NULL,
  `status` enum('hadir','sakit','izin','alfa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hadir',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `recorded_at` datetime NOT NULL,
  `synced` tinyint(1) NOT NULL DEFAULT '0',
  `synced_at` datetime DEFAULT NULL,
  `sync_error` text COLLATE utf8mb4_unicode_ci,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offline_attendances_uuid_unique` (`uuid`),
  KEY `offline_attendances_teacher_id_foreign` (`teacher_id`),
  KEY `offline_attendances_school_class_id_foreign` (`school_class_id`),
  KEY `offline_attendances_teaching_assignment_id_foreign` (`teaching_assignment_id`),
  KEY `offline_attendances_offline_device_id_index` (`offline_device_id`),
  KEY `offline_attendances_student_id_index` (`student_id`),
  KEY `offline_attendances_attendance_date_index` (`attendance_date`),
  KEY `offline_attendances_synced_index` (`synced`),
  KEY `offline_attendances_synced_created_at_index` (`synced`,`created_at`),
  CONSTRAINT `offline_attendances_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`),
  CONSTRAINT `offline_attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `offline_attendances_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`),
  CONSTRAINT `offline_attendances_teaching_assignment_id_foreign` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schedule_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_generations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_by_user_id` bigint unsigned DEFAULT NULL,
  `total_classes` int unsigned NOT NULL DEFAULT '0',
  `total_assignments` int unsigned NOT NULL DEFAULT '0',
  `successful_slots` int unsigned NOT NULL DEFAULT '0',
  `failed_slots` int unsigned NOT NULL DEFAULT '0',
  `conflicts_detected` int unsigned NOT NULL DEFAULT '0',
  `result_data` json DEFAULT NULL,
  `status` enum('pending','in_progress','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedule_generations_generated_by_user_id_foreign` (`generated_by_user_id`),
  KEY `schedule_generations_academic_year_index` (`academic_year`),
  KEY `schedule_generations_status_index` (`status`),
  CONSTRAINT `schedule_generations_generated_by_user_id_foreign` FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_class_subject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_class_subject` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `school_class_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_class_subject_school_class_id_subject_id_unique` (`school_class_id`,`subject_id`),
  KEY `school_class_subject_subject_id_foreign` (`subject_id`),
  CONSTRAINT `school_class_subject_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `school_class_subject_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `homeroom_teacher_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_classes_name_academic_year_unique` (`name`,`academic_year`),
  KEY `school_classes_homeroom_teacher_id_foreign` (`homeroom_teacher_id`),
  CONSTRAINT `school_classes_homeroom_teacher_id_foreign` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `semester_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `semester_locks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `semester` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked_at` timestamp NOT NULL,
  `locked_by` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `semester_locks_academic_year_semester_unique` (`academic_year`,`semester`),
  KEY `semester_locks_locked_by_foreign` (`locked_by`),
  CONSTRAINT `semester_locks_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `religion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_street` text COLLATE utf8mb4_unicode_ci,
  `address_village` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_province` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_education` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_education` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_address` text COLLATE utf8mb4_unicode_ci,
  `parent_province` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_school` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_details_student_id_unique` (`student_id`),
  CONSTRAINT `student_details_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'umum',
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `follow_up_at` date DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_notes_teacher_id_foreign` (`teacher_id`),
  KEY `student_notes_user_id_foreign` (`user_id`),
  KEY `student_notes_student_id_category_index` (`student_id`,`category`),
  KEY `student_notes_resolved_at_index` (`resolved_at`),
  CONSTRAINT `student_notes_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_notes_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_violations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `reported_by_id` bigint unsigned DEFAULT NULL,
  `violation_date` date NOT NULL,
  `violation_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int NOT NULL DEFAULT '0',
  `action_taken` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_violations_student_id_foreign` (`student_id`),
  KEY `student_violations_reported_by_id_foreign` (`reported_by_id`),
  CONSTRAINT `student_violations_reported_by_id_foreign` FOREIGN KEY (`reported_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_violations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `school_class_id` bigint unsigned DEFAULT NULL,
  `nik` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nisn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_nis_unique` (`nik`),
  UNIQUE KEY `students_user_id_unique` (`user_id`),
  UNIQUE KEY `students_nisn_unique` (`nisn`),
  KEY `students_school_class_id_foreign` (`school_class_id`),
  KEY `students_parent_user_id_foreign` (`parent_user_id`),
  CONSTRAINT `students_parent_user_id_foreign` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subject_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subject_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teaching_assignment_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `recorded_by_teacher_id` bigint unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subj_att_unique_day` (`teaching_assignment_id`,`student_id`,`attendance_date`),
  KEY `subject_attendances_student_id_foreign` (`student_id`),
  KEY `subject_attendances_recorded_by_teacher_id_foreign` (`recorded_by_teacher_id`),
  CONSTRAINT `subject_attendances_recorded_by_teacher_id_foreign` FOREIGN KEY (`recorded_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_attendances_teaching_assignment_id_foreign` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subject_teacher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subject_teacher` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_teacher_subject_id_teacher_id_unique` (`subject_id`,`teacher_id`),
  KEY `subject_teacher_teacher_id_foreign` (`teacher_id`),
  CONSTRAINT `subject_teacher_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_teacher_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lesson_hours` tinyint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subjects_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `nik` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `religion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `last_education` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `major` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `university` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_subject_teacher` tinyint(1) NOT NULL DEFAULT '1',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teachers_nip_unique` (`nip`),
  UNIQUE KEY `teachers_user_id_unique` (`user_id`),
  UNIQUE KEY `teachers_nik_unique` (`nik`),
  CONSTRAINT `teachers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teaching_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teaching_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `school_class_id` bigint unsigned NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teaching_assignments_unique_schedule` (`teacher_id`,`subject_id`,`school_class_id`,`academic_year`,`day_of_week`,`start_time`),
  KEY `teaching_assignments_subject_id_foreign` (`subject_id`),
  KEY `teaching_assignments_school_class_id_foreign` (`school_class_id`),
  CONSTRAINT `teaching_assignments_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teaching_assignments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teaching_assignments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_03_20_000001_add_roles_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_20_000002_create_teachers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_20_000003_create_school_classes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_20_000004_create_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_20_000005_create_subjects_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_20_000006_create_teaching_assignments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_20_000007_create_subject_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_20_000008_create_class_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_20_000009_add_photo_to_teachers_and_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_22_000010_create_subject_teacher_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_22_000011_create_school_class_subject_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_22_000012_add_lesson_hours_to_subjects_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_25_000013_create_student_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_25_000014_rename_nis_to_nik_on_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_25_000015_add_profile_fields_to_teachers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_22_000001_create_auth_verification_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_22_000002_create_app_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_22_000003_create_student_notes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_05_23_000001_create_academic_calendars_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_05_23_000002_create_semester_locks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_05_23_000003_add_type_to_academic_calendars_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_05_25_000001_create_schedule_generations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_25_000005_create_offline_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_06_30_090053_create_student_violations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_03_115558_add_parent_user_id_to_students_table',1);
