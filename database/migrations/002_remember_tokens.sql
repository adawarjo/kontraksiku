-- =====================================================================
-- Migrasi 002: login "ingat saya" (percayakan perangkat 30 hari)
-- Jalankan SEKALI pada database yang dibuat sebelum fitur ini ada.
-- Instalasi baru tidak perlu ini (sudah termasuk di schema.sql).
--   mysql -u root kontraksiku < database/migrations/002_remember_tokens.sql
-- =====================================================================
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `selector`        CHAR(24) NOT NULL,
  `token_hash`      CHAR(64) NOT NULL,
  `session_version` INT UNSIGNED NOT NULL,
  `expires_at`      DATETIME NOT NULL,
  `last_used_at`    DATETIME NULL,
  `created_at`      DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  KEY `idx_remember_expires` (`expires_at`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
