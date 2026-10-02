-- One authenticated account per database-calendar day. No historical backfill.
-- Legacy installations may use unsigned user IDs; match the actual referenced type.
SET @activity_user_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id');
SET @activity_ddl = CONCAT('CREATE TABLE IF NOT EXISTS `user_activity_daily` (
  `user_id` ', @activity_user_type, ' NOT NULL,
  `activity_date` DATE NOT NULL,
  PRIMARY KEY (`user_id`, `activity_date`),
  INDEX `idx_activity_date_user` (`activity_date`, `user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
PREPARE activity_statement FROM @activity_ddl;
EXECUTE activity_statement;
DEALLOCATE PREPARE activity_statement;

CREATE TABLE IF NOT EXISTS `activity_tracking_meta` (
  `id` TINYINT NOT NULL PRIMARY KEY,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `activity_tracking_meta` (`id`) VALUES (1);
