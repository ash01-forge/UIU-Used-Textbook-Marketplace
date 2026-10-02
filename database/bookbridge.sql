-- =====================================================================
-- BookBridge - UIU Used Textbook Marketplace
-- Shared Database Schema Definition (database/bookbridge.sql)
--
-- Target Database: MySQL / MariaDB (XAMPP Default)
-- Database Name  : bookbridge_db  (agreed name for this project)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
--
-- NOTE: This file is the reference schema definition.
--       For an existing database, run database/migrations/ instead.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `bookbridge_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bookbridge_db`;

-- ---------------------------------------------------------------------
-- Table 1: users
-- Stores registered students, sellers, and administrators.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
  `student_id` VARCHAR(30) NULL,
  `department` VARCHAR(100) NULL,
  `phone` VARCHAR(20) NULL,
  `avatar_url` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 2: categories
-- Stores marketplace departments and subject groupings.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('Department', 'Subject') NOT NULL,
  `department` VARCHAR(100) NULL,
  `subject` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_categories_name_type` (`name`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 3: listings
-- Stores textbooks, notes, and lab manuals submitted by sellers.
-- Status lifecycle:
--   'pending_approval'  => Waiting for Admin review
--   'available'         => Approved by Admin, live in marketplace
--   'changes_requested' => Admin requested changes; requires seller edit
--   'rejected'          => Rejected by Admin with written feedback
--   'sold'              => Transacted and handed over on campus
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `listings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `seller_id` INT NOT NULL,
  `category_id` INT NULL,
  `title` VARCHAR(200) NOT NULL,
  `author` VARCHAR(150) NULL,
  `edition` VARCHAR(50) NULL,
  `course_code` VARCHAR(30) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(100) NULL,
  `item_type` ENUM('Textbook', 'Notes', 'Lab Manual') NOT NULL DEFAULT 'Textbook',
  `condition_type` ENUM('New', 'Like New', 'Good', 'Fair', 'Poor') NOT NULL DEFAULT 'Good',
  `price` DECIMAL(10, 2) NOT NULL,
  `description` TEXT NOT NULL,
  `image_url` VARCHAR(500) NULL,
  `status` ENUM('pending_approval', 'available', 'changes_requested', 'rejected', 'sold') NOT NULL DEFAULT 'pending_approval',
  `admin_feedback` TEXT NULL COMMENT 'Written feedback provided when status is changes_requested or rejected',
  `reviewed_by` INT NULL COMMENT 'Admin user ID who approved/rejected',
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_listings_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listings_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_listings_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  INDEX `idx_listings_status` (`status`),
  INDEX `idx_listings_dept_type` (`department`, `item_type`),
  INDEX `idx_listings_course` (`course_code`),
  INDEX `idx_listings_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 4: purchase_requests
-- Stores buyer requests to purchase a listing.
-- Strict Policy: Cash on Meet only!
-- Status lifecycle:
--   'pending'   => Request sent by buyer
--   'accepted'  => Seller accepted meetup proposal
--   'declined'  => Seller declined proposal
--   'completed' => Cash on Meet concluded, listing marked sold
--   'cancelled' => Buyer or seller cancelled before meeting
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `listing_id` INT NOT NULL,
  `buyer_id` INT NOT NULL,
  `seller_id` INT NOT NULL,
  `meeting_location` VARCHAR(150) NOT NULL COMMENT 'e.g. UIU Main Gate, UIU Library, UIU Cafeteria',
  `preferred_date` DATE NOT NULL,
  `payment_method` ENUM('cash_on_meet') NOT NULL DEFAULT 'cash_on_meet' COMMENT 'Strict Cash on Meet only',
  `note` TEXT NULL,
  `status` ENUM('pending', 'accepted', 'declined', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `completed_at` DATETIME NULL,
  `sale_price` DECIMAL(10,2) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_purchases_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchases_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchases_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_purchases_status` (`status`),
  INDEX `idx_purchases_buyer` (`buyer_id`),
  INDEX `idx_purchases_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 5: wishlists
-- Stores items saved by buyers for quick reference.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `listing_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_wishlist_user_listing` (`user_id`, `listing_id`),
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 6: messages
-- Stores campus coordination chat messages between buyers and sellers.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `listing_id` INT NULL,
  `message_text` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  INDEX `idx_messages_conversation` (`sender_id`, `receiver_id`),
  INDEX `idx_messages_receiver_read` (`receiver_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 7: reviews
-- Stores ratings and reviews given by buyers after a completed meetup.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_request_id` INT NULL,
  `listing_id` INT NULL,
  `reviewer_id` INT NOT NULL COMMENT 'Buyer who purchased and inspected the book',
  `seller_id` INT NOT NULL COMMENT 'Seller receiving the rating',
  `rating` TINYINT NOT NULL COMMENT 'Score between 1 and 5 stars',
  `comment` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reviews_purchase` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_reviews_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA (Default demo accounts, categories, and sample listings)
-- Default password for demo accounts: "password123"
-- Password hash generated using password_hash('password123', PASSWORD_BCRYPT)
-- =====================================================================

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `student_id`, `phone`)
VALUES
  (1, 'Admin User', 'admin@uiu.ac.bd', '$2y$10$QoITxuE.T0Baw91XQLFq2uvvH/jbWYvt9tj8o41sVSMIShkpZgOpa', 'admin', '011200001', '01700000000'),
  (2, 'Rafiul Islam', 'seller@uiu.ac.bd', '$2y$10$QoITxuE.T0Baw91XQLFq2uvvH/jbWYvt9tj8o41sVSMIShkpZgOpa', 'seller', '011211054', '01811111111'),
  (3, 'Zahir Raihan', 'buyer@uiu.ac.bd', '$2y$10$QoITxuE.T0Baw91XQLFq2uvvH/jbWYvt9tj8o41sVSMIShkpZgOpa', 'buyer', '011211088', '01922222222'),
  (4, 'Nusrat Jahan', 'nusrat@uiu.ac.bd', '$2y$10$QoITxuE.T0Baw91XQLFq2uvvH/jbWYvt9tj8o41sVSMIShkpZgOpa', 'seller', '011212030', '01733333333'),
  (5, 'Tanvir Ahmed', 'tanvir@uiu.ac.bd', '$2y$10$QoITxuE.T0Baw91XQLFq2uvvH/jbWYvt9tj8o41sVSMIShkpZgOpa', 'buyer', '011213012', '01644444444')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `categories` (`id`, `name`, `type`)
VALUES
  (1, 'CSE', 'Department'),
  (2, 'EEE', 'Department'),
  (3, 'BBA', 'Department'),
  (4, 'Mathematics', 'Department'),
  (5, 'Physics', 'Department'),
  (6, 'Data Structures', 'Subject'),
  (7, 'Calculus', 'Subject'),
  (8, 'Electronics', 'Subject'),
  (9, 'Business', 'Subject'),
  (10, 'OOP in Java', 'Subject')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `listings` (
  `id`, `seller_id`, `category_id`, `title`, `author`, `edition`, `course_code`, 
  `department`, `subject`, `item_type`, `condition_type`, `price`, `description`, 
  `image_url`, `status`, `reviewed_by`, `reviewed_at`
)
VALUES
  (1, 2, 1, 'Data Structures and Algorithms', 'Cormen, Leiserson, Rivest', '3rd Edition', 'CSE-2101', 'CSE', 'Data Structures', 'Textbook', 'Like New', 450.00, 'Third edition. All pages intact with minimal highlighting.', 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (2, 4, 4, 'Calculus: Early Transcendentals', 'James Stewart', '8th Edition', 'MAT-1101', 'Mathematics', 'Calculus', 'Textbook', 'Good', 380.00, 'Covers single-variable and multi-variable calculus.', 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (3, 2, 2, 'Digital Electronics Handwritten Notes', 'Rafiul Islam', NULL, 'EEE-2203', 'EEE', 'Electronics', 'Notes', 'New', 150.00, 'Complete handwritten lecture notes and solved exam questions.', 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (4, 4, 3, 'Business Communication', 'Kitty O. Locker', '11th Edition', 'BUS-1105', 'BBA', 'Business', 'Textbook', 'Fair', 200.00, 'Used for BBA business communication course.', 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (5, 2, 1, 'Object Oriented Programming in Java', 'Herbert Schildt', '10th Edition', 'CSE-1201', 'CSE', 'OOP in Java', 'Textbook', 'Like New', 320.00, 'Clear code examples and core Java concepts.', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (6, 4, 2, 'Engineering Physics Lab Manual', 'UIU Department of Physics', '2025 Edition', 'PHY-1101', 'EEE', 'Physics', 'Lab Manual', 'Good', 120.00, 'Physics 1 laboratory guidelines with completed sample observations.', 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&h=400&fit=crop', 'available', 1, NOW()),
  (7, 2, 1, 'Computer Networking: A Top-Down Approach', 'Kurose & Ross', '7th Edition', 'CSE-3201', 'CSE', 'Networking', 'Textbook', 'Good', 400.00, 'Submitted for Admin review. Excellent condition with neat binding.', 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=400&q=80', 'pending_approval', NULL, NULL)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `purchase_requests` (`id`, `listing_id`, `buyer_id`, `seller_id`, `meeting_location`, `preferred_date`, `payment_method`, `note`, `status`)
VALUES
  (1, 1, 3, 2, 'UIU Library', CURDATE() + INTERVAL 1 DAY, 'cash_on_meet', 'I would like to check the book condition before paying cash.', 'pending')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `wishlists` (`id`, `user_id`, `listing_id`)
VALUES
  (1, 3, 1),
  (2, 3, 2)
ON DUPLICATE KEY UPDATE `listing_id` = VALUES(`listing_id`);

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `listing_id`, `message_text`, `is_read`)
VALUES
  (1, 3, 2, 1, 'Hi! Is this Data Structures book still available?', 1),
  (2, 2, 3, 1, 'Yes, it is! Would you like to meet on campus tomorrow?', 1),
  (3, 3, 2, 1, 'Sure, let us meet at the UIU Library at 2:00 PM.', 0)
ON DUPLICATE KEY UPDATE `message_text` = VALUES(`message_text`);

INSERT INTO `reviews` (`id`, `purchase_request_id`, `listing_id`, `reviewer_id`, `seller_id`, `rating`, `comment`)
VALUES
  (1, NULL, 1, 3, 2, 5, 'Great seller! The textbook was exactly in Like New condition as described.')
ON DUPLICATE KEY UPDATE `rating` = VALUES(`rating`);
