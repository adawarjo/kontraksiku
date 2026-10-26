-- =====================================================================
-- Migrasi 001: peran admin & manajemen pengguna
-- Jalankan SEKALI pada database yang dibuat sebelum fitur admin ada.
-- Instalasi baru tidak perlu ini (sudah termasuk di schema.sql).
--   mysql -u root kontraksiku < database/migrations/001_admin_roles.sql
-- =====================================================================
SET time_zone = '+00:00';

ALTER TABLE `users`
  ADD COLUMN `role` ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER `password`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `must_change_password`,
  ADD COLUMN `last_login_at` DATETIME NULL AFTER `session_version`,
  ADD KEY `idx_users_role` (`role`);

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
