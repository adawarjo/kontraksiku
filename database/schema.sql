-- =====================================================================
-- KontraksiKu - Skema Database
-- Kompatibel: MySQL 8.0+ dan MariaDB 10.4+
-- Semua kolom DATETIME disimpan dalam UTC. Konversi ke Asia/Jakarta
-- dilakukan di aplikasi saat ditampilkan.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `kontraksiku`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kontraksiku`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- Pengguna
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  -- 'admin' dapat mengelola akun & mereset password; tidak dapat melihat data kontraksi pengguna lain
  `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  -- 1 = pengguna wajib mengganti password (setelah direset/dibuat oleh admin)
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  -- dinaikkan saat password diganti/direset/akun dinonaktifkan -> semua sesi login lama tidak berlaku
  `session_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Profil kehamilan (satu profil per pengguna)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pregnancy_profiles` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED NOT NULL,
  `nickname`            VARCHAR(60)  NULL,
  `estimated_due_date`  DATE         NULL,
  `tracking_start_date` DATE         NULL,
  `notes`               TEXT         NULL,
  `companion_name`      VARCHAR(100) NULL,
  `companion_contact`   VARCHAR(60)  NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profiles_user` (`user_id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sesi pencatatan kontraksi
-- active_user_id berisi user_id hanya ketika status = 'active', selain itu NULL.
-- UNIQUE pada kolom ini mencegah dua sesi aktif untuk pengguna yang sama
-- (NULL boleh berulang).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contraction_sessions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `started_at`     DATETIME NOT NULL,
  `ended_at`       DATETIME NULL,
  `status`         ENUM('active','completed') NOT NULL DEFAULT 'active',
  `active_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_one_active` (`active_user_id`),
  KEY `idx_sessions_user_started` (`user_id`, `started_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kontraksi
-- ended_at NULL = kontraksi sedang berlangsung.
-- active_user_id = user_id selama kontraksi berlangsung (UNIQUE mencegah
-- dua kontraksi aktif bersamaan untuk satu pengguna).
-- uuid dibuat di perangkat; UNIQUE mencegah duplikasi saat sinkronisasi.
-- interval_seconds = started_at saat ini - started_at kontraksi sebelumnya
-- di sesi yang sama (NULL untuk kontraksi pertama).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contractions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `session_id`       INT UNSIGNED NOT NULL,
  `uuid`             CHAR(36) NOT NULL,
  `started_at`       DATETIME NOT NULL,
  `ended_at`         DATETIME NULL,
  `duration_seconds` INT UNSIGNED NULL,
  `interval_seconds` INT UNSIGNED NULL,
  `notes`            VARCHAR(500) NULL,
  `active_user_id`   INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contractions_uuid` (`uuid`),
  UNIQUE KEY `uq_contractions_one_active` (`active_user_id`),
  KEY `idx_contractions_user_started` (`user_id`, `started_at`),
  KEY `idx_contractions_session_started` (`session_id`, `started_at`),
  CONSTRAINT `fk_contractions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractions_session` FOREIGN KEY (`session_id`)
    REFERENCES `contraction_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Percobaan login (pembatasan brute force)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(190) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email_time` (`email`, `attempted_at`),
  KEY `idx_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Log audit tindakan admin (tanpa data kesehatan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`       INT UNSIGNED NULL,
  `action`         VARCHAR(40)  NOT NULL,
  `target_user_id` INT UNSIGNED NULL,
  `target_email`   VARCHAR(190) NULL,
  `details`        VARCHAR(255) NULL,
  `ip_address`     VARCHAR(45)  NOT NULL,
  `created_at`     DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
