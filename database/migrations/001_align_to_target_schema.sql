-- =====================================================================
-- BookBridge – UIU Used Textbook Marketplace
-- Migration: 001_align_to_target_schema.sql  (REVISION 3 – 2026-10-01)
--
-- Source  : bookbridge_db (phpMyAdmin backup 2026-10-01, MariaDB 10.4.32)
-- Target  : database/bookbridge.sql (agreed team schema)
-- Context : Explicitly selected database (DATABASE()) – NO hardcoded USE db.
--
-- REVISION 3 FIXES:
--   1. Removed hardcoded USE bookbridge_db statement so migration operates
--      strictly on whichever database is currently selected.
--   2. Reordered listings operations so admin_feedback is renamed from
--      admin_note BEFORE reviewed_by is added AFTER admin_feedback.
--   3. Successfully creates reviewed_by as INT UNSIGNED NULL before adding
--      its foreign key constraint.
--   4. Matched all foreign key column types and signedness to referenced PKs:
--      • listings.category_id      → INT UNSIGNED NULL (matches categories.id)
--      • listings.reviewed_by      → INT UNSIGNED NULL (matches users.id)
--      • reviews.purchase_request_id → INT UNSIGNED NULL (matches purchase_requests.id)
--      • reviews.listing_id        → INT UNSIGNED NULL (matches listings.id)
--   5. Modified categories.department and categories.subject to NULL DEFAULT NULL
--      before inserting parent Department rows, preserving all existing
--      department→subject relationships while avoiding strict-mode default errors.
--
-- DATA-PRESERVATION POLICY:
--   • No DROP TABLE, no TRUNCATE, no re-seed of any kind.
--   • users.department  → KEPT. Contains real student/faculty data.
--   • categories: original (department, subject) columns KEPT alongside new
--     (name, type) columns to preserve the department→subject mapping.
--   • purchase_requests: 0 rows confirmed in backup; payment_method converted
--     strictly to 'cash_on_meet' (Cash on Meet policy).
--
-- IDEMPOTENT: Uses information_schema guards for index & constraint steps.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- §1  USERS TABLE
--
-- Backup columns : id, name, email, password_hash, role, department, created_at
-- Target adds    : full_name (rename of name), student_id, phone,
--                  avatar_url, updated_at
-- Preserved extra: department  ← explicitly kept by policy decision
-- =====================================================================

-- 1a. Add full_name alongside old name (data preserved during transition)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`;

-- 1b. Copy name → full_name
UPDATE `users`
    SET `full_name` = `name`
    WHERE `full_name` = '' OR `full_name` IS NULL;

-- 1c. Drop old name column (data is now in full_name)
ALTER TABLE `users` DROP COLUMN IF EXISTS `name`;

-- 1d. NOTE: `department` column is intentionally NOT dropped.
--     It holds real data (e.g. 'Administration', 'CSE').

-- 1e. Add new optional profile columns (NULL-safe; existing rows unaffected)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `student_id`  VARCHAR(30)  NULL AFTER `role`,
    ADD COLUMN IF NOT EXISTS `phone`       VARCHAR(20)  NULL AFTER `student_id`,
    ADD COLUMN IF NOT EXISTS `avatar_url`  VARCHAR(255) NULL AFTER `phone`;

-- 1f. Add updated_at
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER `created_at`;

-- 1g. Align timestamp type and role default
ALTER TABLE `users`
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN `role` ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer';

-- 1h. Performance index
SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_role');
SET @s = IF(@ix = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_role` (`role`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =====================================================================
-- §2  CATEGORIES TABLE
--
-- Backup shape  : id (INT UNSIGNED), department VARCHAR(100), subject VARCHAR(150),
--                 created_at TIMESTAMP
--                 UNIQUE KEY unique_category (department, subject)
-- Backup data   :
--   (1, 'CSE', 'Data Structures and Algorithms')
--   (2, 'CSE', 'Object Oriented Programming')
--   (3, 'EEE', 'Signals and Systems')
--   (4, 'BBA', 'Financial Accounting')
--
-- Target shape  : id, name VARCHAR(100), type ENUM('Department','Subject'),
--                 created_at
--                 UNIQUE KEY uq_categories_name_type (name, type)
--
-- Mapping-preservation strategy:
--   • Keep `department` and `subject` columns alongside new `name`/`type`.
--   • Make `department` and `subject` NULL-able so parent Department rows
--     and future app categories can insert without missing default errors.
--   • Existing rows: name = subject value, type = 'Subject'.
--   • New Department rows: name = dept name, type = 'Department',
--     department = dept name, subject = NULL.
-- =====================================================================

-- 2a. Add name and type columns
ALTER TABLE `categories`
    ADD COLUMN IF NOT EXISTS `name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`,
    ADD COLUMN IF NOT EXISTS `type` ENUM('Department','Subject') NOT NULL
        DEFAULT 'Subject' AFTER `name`;

-- 2b. Populate name from subject (the specific human-readable label)
UPDATE `categories`
    SET `name` = `subject`
    WHERE `name` = '' OR `name` IS NULL;

-- 2c. Make department and subject NULL-able to prevent strict-mode default errors
ALTER TABLE `categories`
    MODIFY COLUMN `department` VARCHAR(100) NULL DEFAULT NULL,
    MODIFY COLUMN `subject`    VARCHAR(150) NULL DEFAULT NULL;

-- 2d. Replace unique key: drop old (department, subject), add new (name, type)
SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'
    AND INDEX_NAME = 'unique_category');
SET @s = IF(@ix > 0,
    'ALTER TABLE `categories` DROP INDEX `unique_category`',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 2e. Add new unique key (name, type)
SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'
    AND INDEX_NAME = 'uq_categories_name_type');
SET @s = IF(@ix = 0,
    'ALTER TABLE `categories` ADD UNIQUE KEY `uq_categories_name_type` (`name`, `type`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 2f. Align timestamp type
ALTER TABLE `categories`
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 2g. Insert parent Department rows (INSERT IGNORE is idempotent)
INSERT IGNORE INTO `categories` (`name`, `type`, `department`, `subject`, `created_at`) VALUES
    ('CSE',         'Department', 'CSE',         NULL, NOW()),
    ('EEE',         'Department', 'EEE',         NULL, NOW()),
    ('BBA',         'Department', 'BBA',         NULL, NOW()),
    ('Mathematics', 'Department', 'Mathematics', NULL, NOW()),
    ('Physics',     'Department', 'Physics',     NULL, NOW());

-- =====================================================================
-- §3  LISTINGS TABLE
--
-- Backup columns : id, seller_id, title, author, department, course_code,
--                  subject, item_type, item_condition, price, description,
--                  image_url,
--                  status ENUM('pending','available','changes_requested',
--                               'rejected','sold'),
--                  admin_note, approved_at, created_at, updated_at
-- Backup data    : 1 row, status='available'
--
-- Changes:
--   DATA:   'pending' → 'pending_approval' (BEFORE ENUM change)
--   RENAME: item_condition → condition_type
--   RENAME: admin_note     → admin_feedback (DONE BEFORE adding reviewed_by!)
--   RENAME: approved_at    → reviewed_at
--   ADD:    category_id INT UNSIGNED NULL (matches categories.id)
--   ADD:    edition VARCHAR(50) NULL
--   ADD:    reviewed_by INT UNSIGNED NULL AFTER admin_feedback (matches users.id)
--   ALTER:  status ENUM expanded, FK constraints upgraded to CASCADE/SET NULL
-- =====================================================================

-- 3a. DATA CONVERSION: must happen BEFORE ENUM alteration
UPDATE `listings` SET `status` = 'pending_approval' WHERE `status` = 'pending';

-- 3b. Expand status ENUM
ALTER TABLE `listings`
    MODIFY COLUMN `status`
        ENUM('pending_approval','available','changes_requested','rejected','sold')
        NOT NULL DEFAULT 'pending_approval';

-- 3c. Rename existing columns FIRST so admin_feedback exists before reviewed_by is added
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND COLUMN_NAME = 'item_condition');
SET @s = IF(@c > 0,
    "ALTER TABLE `listings` CHANGE COLUMN `item_condition` `condition_type` ENUM('New','Like New','Good','Fair','Poor') NOT NULL DEFAULT 'Good'",
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND COLUMN_NAME = 'admin_note');
SET @s = IF(@c > 0,
    "ALTER TABLE `listings` CHANGE COLUMN `admin_note` `admin_feedback` TEXT NULL COMMENT 'Feedback from admin when status is changes_requested or rejected'",
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND COLUMN_NAME = 'approved_at');
SET @s = IF(@c > 0,
    'ALTER TABLE `listings` CHANGE COLUMN `approved_at` `reviewed_at` DATETIME NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 3d. Add new nullable columns (using INT UNSIGNED to match referenced PKs)
ALTER TABLE `listings`
    ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED NULL AFTER `seller_id`,
    ADD COLUMN IF NOT EXISTS `edition`     VARCHAR(50)  NULL AFTER `author`;

ALTER TABLE `listings`
    ADD COLUMN IF NOT EXISTS `reviewed_by` INT UNSIGNED NULL
        COMMENT 'Admin user ID who reviewed this listing'
        AFTER `admin_feedback`;

-- 3e. Backfill category_id for existing listings matching category subject
UPDATE `listings` l
    JOIN `categories` c ON c.name = l.subject AND c.type = 'Subject'
    SET l.category_id = c.id
    WHERE l.category_id IS NULL;

-- 3f. Align timestamp types
ALTER TABLE `listings`
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP;

-- 3g. Upgrade FKs to CASCADE / SET NULL semantics
ALTER TABLE `listings` DROP FOREIGN KEY IF EXISTS `fk_listing_seller`;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND CONSTRAINT_NAME = 'fk_listings_seller');
SET @s = IF(@fk = 0,
    'ALTER TABLE `listings` ADD CONSTRAINT `fk_listings_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND CONSTRAINT_NAME = 'fk_listings_reviewer');
SET @s = IF(@fk = 0,
    'ALTER TABLE `listings` ADD CONSTRAINT `fk_listings_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND CONSTRAINT_NAME = 'fk_listings_category');
SET @s = IF(@fk = 0,
    'ALTER TABLE `listings` ADD CONSTRAINT `fk_listings_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 3h. Performance indexes
SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND INDEX_NAME = 'idx_listings_status');
SET @s = IF(@ix = 0,
    'ALTER TABLE `listings` ADD INDEX `idx_listings_status` (`status`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings'
    AND INDEX_NAME = 'idx_listings_course');
SET @s = IF(@ix = 0,
    'ALTER TABLE `listings` ADD INDEX `idx_listings_course` (`course_code`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =====================================================================
-- §4  PURCHASE_REQUESTS TABLE
--
-- Backup columns : id, listing_id, buyer_id, meeting_place VARCHAR(200),
--                  meeting_time DATETIME,
--                  payment_method ENUM('cash','bkash','nagad'),
--                  status ENUM('pending','accepted','completed','cancelled'),
--                  created_at TIMESTAMP
-- Backup rows    : 0 (confirmed)
-- =====================================================================

-- 4a. Convert any 'cash' values to 'cash_on_meet'
UPDATE `purchase_requests`
    SET `payment_method` = 'cash_on_meet'
    WHERE `payment_method` = 'cash';

-- 4b. Restrict payment_method ENUM strictly to 'cash_on_meet'
ALTER TABLE `purchase_requests`
    MODIFY COLUMN `payment_method`
        ENUM('cash_on_meet') NOT NULL DEFAULT 'cash_on_meet'
        COMMENT 'Strict Cash on Meet only – platform policy';

-- 4c. Add 'declined' to status ENUM
ALTER TABLE `purchase_requests`
    MODIFY COLUMN `status`
        ENUM('pending','accepted','declined','completed','cancelled')
        NOT NULL DEFAULT 'pending';

-- 4d. Rename meeting_place → meeting_location
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND COLUMN_NAME = 'meeting_place');
SET @s = IF(@c > 0,
    "ALTER TABLE `purchase_requests` CHANGE COLUMN `meeting_place` `meeting_location` VARCHAR(150) NOT NULL COMMENT 'e.g. UIU Main Gate, UIU Library, UIU Cafeteria'",
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 4e. Rename meeting_time DATETIME → preferred_date DATE
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND COLUMN_NAME = 'meeting_time');
SET @s = IF(@c > 0,
    'ALTER TABLE `purchase_requests` CHANGE COLUMN `meeting_time` `preferred_date` DATE NOT NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 4f. Add seller_id (INT UNSIGNED to match users.id)
ALTER TABLE `purchase_requests`
    ADD COLUMN IF NOT EXISTS `seller_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `buyer_id`;

UPDATE `purchase_requests` pr
    JOIN `listings` l ON l.id = pr.listing_id
    SET pr.seller_id = l.seller_id
    WHERE pr.seller_id = 0;

-- 4g. Add remaining new columns
ALTER TABLE `purchase_requests`
    ADD COLUMN IF NOT EXISTS `note`         TEXT     NULL AFTER `payment_method`,
    ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL AFTER `status`;

ALTER TABLE `purchase_requests`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER `created_at`;

ALTER TABLE `purchase_requests`
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 4h. Upgrade FKs to CASCADE
ALTER TABLE `purchase_requests` DROP FOREIGN KEY IF EXISTS `fk_purchase_listing`;
ALTER TABLE `purchase_requests` DROP FOREIGN KEY IF EXISTS `fk_purchase_buyer`;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND CONSTRAINT_NAME = 'fk_purchases_listing');
SET @s = IF(@fk = 0,
    'ALTER TABLE `purchase_requests` ADD CONSTRAINT `fk_purchases_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND CONSTRAINT_NAME = 'fk_purchases_buyer');
SET @s = IF(@fk = 0,
    'ALTER TABLE `purchase_requests` ADD CONSTRAINT `fk_purchases_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND CONSTRAINT_NAME = 'fk_purchases_seller');
SET @s = IF(@fk = 0,
    'ALTER TABLE `purchase_requests` ADD CONSTRAINT `fk_purchases_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND INDEX_NAME = 'idx_purchases_status');
SET @s = IF(@ix = 0,
    'ALTER TABLE `purchase_requests` ADD INDEX `idx_purchases_status` (`status`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND INDEX_NAME = 'idx_purchases_buyer');
SET @s = IF(@ix = 0,
    'ALTER TABLE `purchase_requests` ADD INDEX `idx_purchases_buyer` (`buyer_id`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_requests'
    AND INDEX_NAME = 'idx_purchases_seller');
SET @s = IF(@ix = 0,
    'ALTER TABLE `purchase_requests` ADD INDEX `idx_purchases_seller` (`seller_id`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =====================================================================
-- §5  WISHLISTS TABLE  (MISSING IN BACKUP – CREATE FRESH)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `wishlists` (
    `id`         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED   NOT NULL,
    `listing_id` INT UNSIGNED   NOT NULL,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_wishlist_user_listing` (`user_id`, `listing_id`),
    CONSTRAINT `fk_wishlist_user`
        FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wishlist_listing`
        FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- §6  MESSAGES TABLE
--
-- Backup columns : id, listing_id INT UNSIGNED NOT NULL, sender_id,
--                  receiver_id, message TEXT, created_at TIMESTAMP
-- Backup rows    : 0
-- =====================================================================

ALTER TABLE `messages`
    MODIFY COLUMN `listing_id` INT UNSIGNED NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND COLUMN_NAME = 'message');
SET @s = IF(@c > 0,
    'ALTER TABLE `messages` CHANGE COLUMN `message` `message_text` TEXT NOT NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

ALTER TABLE `messages`
    ADD COLUMN IF NOT EXISTS `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `message_text`;

ALTER TABLE `messages`
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Upgrade FKs
ALTER TABLE `messages` DROP FOREIGN KEY IF EXISTS `fk_message_listing`;
ALTER TABLE `messages` DROP FOREIGN KEY IF EXISTS `fk_message_sender`;
ALTER TABLE `messages` DROP FOREIGN KEY IF EXISTS `fk_message_receiver`;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND CONSTRAINT_NAME = 'fk_messages_sender');
SET @s = IF(@fk = 0,
    'ALTER TABLE `messages` ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND CONSTRAINT_NAME = 'fk_messages_receiver');
SET @s = IF(@fk = 0,
    'ALTER TABLE `messages` ADD CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND CONSTRAINT_NAME = 'fk_messages_listing');
SET @s = IF(@fk = 0,
    'ALTER TABLE `messages` ADD CONSTRAINT `fk_messages_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND INDEX_NAME = 'idx_messages_conversation');
SET @s = IF(@ix = 0,
    'ALTER TABLE `messages` ADD INDEX `idx_messages_conversation` (`sender_id`, `receiver_id`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
    AND INDEX_NAME = 'idx_messages_receiver_read');
SET @s = IF(@ix = 0,
    'ALTER TABLE `messages` ADD INDEX `idx_messages_receiver_read` (`receiver_id`, `is_read`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =====================================================================
-- §7  REVIEWS TABLE
--
-- Backup columns : id, buyer_id, seller_id, rating, comment, created_at
-- Backup rows    : 0
--
-- Changes:
--   ADD purchase_request_id INT UNSIGNED NULL (matches purchase_requests.id)
--   ADD listing_id INT UNSIGNED NULL (matches listings.id)
--   RENAME buyer_id → reviewer_id
-- =====================================================================

ALTER TABLE `reviews`
    ADD COLUMN IF NOT EXISTS `purchase_request_id` INT UNSIGNED NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `listing_id`          INT UNSIGNED NULL AFTER `purchase_request_id`;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND COLUMN_NAME = 'buyer_id');
SET @s = IF(@c > 0,
    "ALTER TABLE `reviews` CHANGE COLUMN `buyer_id` `reviewer_id` INT UNSIGNED NOT NULL COMMENT 'Buyer who purchased and inspected the book'",
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

ALTER TABLE `reviews`
    MODIFY COLUMN `seller_id` INT UNSIGNED NOT NULL
        COMMENT 'Seller receiving the rating',
    MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Upgrade FKs
ALTER TABLE `reviews` DROP FOREIGN KEY IF EXISTS `fk_review_buyer`;
ALTER TABLE `reviews` DROP FOREIGN KEY IF EXISTS `fk_review_seller`;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND CONSTRAINT_NAME = 'fk_reviews_reviewer');
SET @s = IF(@fk = 0,
    'ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND CONSTRAINT_NAME = 'fk_reviews_seller');
SET @s = IF(@fk = 0,
    'ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND CONSTRAINT_NAME = 'fk_reviews_purchase');
SET @s = IF(@fk = 0,
    'ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_purchase` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND CONSTRAINT_NAME = 'fk_reviews_listing');
SET @s = IF(@fk = 0,
    'ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'
    AND INDEX_NAME = 'idx_reviews_seller');
SET @s = IF(@ix = 0,
    'ALTER TABLE `reviews` ADD INDEX `idx_reviews_seller` (`seller_id`)',
    'SELECT 1');
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =====================================================================
-- §8  RE-ENABLE FOREIGN KEY CHECKS
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- §9  DOCUMENTED STRUCTURAL DIFFERENCES: MIGRATED DB vs FRESH INSTALL
-- =====================================================================
--
-- After this migration runs on bookbridge_db (the existing database),
-- it will differ from a fresh-install bookbridge_db in exactly these ways:
--
-- TABLE: users
--   Migrated has: extra column `department` VARCHAR(100) DEFAULT NULL
--   Fresh lacks : `department`
--   PHP impact  : NONE. setSessionUser() maps explicit fields only.
--                 `department` never enters the session or API response.
--
-- TABLE: categories
--   Migrated has: extra columns `department` VARCHAR(100) DEFAULT NULL,
--                               `subject`    VARCHAR(150) DEFAULT NULL
--   Fresh lacks : both of these
--   PHP impact  : NONE. PHP code queries `name` and `type` only.
--                 The extra columns preserve the department→subject mapping
--                 (e.g., which department each Subject row belongs to).
--
-- All other tables (listings, purchase_requests, wishlists, messages,
-- reviews) are structurally identical between migrated and fresh-install.
--
-- =====================================================================
-- END OF MIGRATION 001 (Revision 3)
-- =====================================================================
