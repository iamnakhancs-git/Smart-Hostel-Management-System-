-- SHMS CLEAN DATABASE - 12 October 2026
-- Import FIRST into a new/empty centralized_hostel_db.
-- Does not drop an existing database. Do not import into an existing installation.
-- Latest schema plus Admin/Mess accounts, room types and initial meal defaults.
-- No residents, rooms, allocations, menus, attendance or bills.
-- Optional demo data: import SHMS_DEMO_SEP_OCT.sql AFTER this file.

CREATE DATABASE IF NOT EXISTS `centralized_hostel_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `centralized_hostel_db`;

-- ============================================================
-- TABLE: admins
-- ============================================================
CREATE TABLE `admins` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `phone`      VARCHAR(20),
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: mess_incharges
-- ============================================================
CREATE TABLE `mess_incharges` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `phone`      VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: room_types
-- ============================================================
CREATE TABLE `room_types` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `type_name`   VARCHAR(10) NOT NULL,   -- 'A', 'B', 'C'
  `description` VARCHAR(100),
  `beds`        TINYINT NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: rooms
-- ============================================================
CREATE TABLE `rooms` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `room_number`  VARCHAR(10) NOT NULL UNIQUE,
  `room_type_id` INT(11) NOT NULL,
  `floor`        TINYINT DEFAULT 1,
  `status`       ENUM('available','occupied','maintenance') DEFAULT 'available',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`room_type_id`) REFERENCES `room_types`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: residents
-- ============================================================
CREATE TABLE `residents` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `email`            VARCHAR(100) NOT NULL UNIQUE,
  `password`         VARCHAR(255) NOT NULL,
  `phone`            VARCHAR(20),
  `emergency_contact`VARCHAR(20),
  `address`          TEXT,
  `id_proof_type`    VARCHAR(50),
  `id_proof_number`  VARCHAR(50),
  `join_date`        DATE,
  `status`           ENUM('active','inactive') DEFAULT 'active',
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_resident_phone` (`phone`),
  UNIQUE KEY `unique_resident_id_proof` (`id_proof_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: room_allocations
-- ============================================================
CREATE TABLE `room_allocations` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`  INT(11) NOT NULL,
  `room_id`      INT(11) NOT NULL,
  `alloc_date`   DATE NOT NULL,
  `vacate_date`  DATE DEFAULT NULL,
  `is_active`    TINYINT(1) DEFAULT 1,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`)     REFERENCES `rooms`(`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: complaints
-- ============================================================
CREATE TABLE `complaints` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `subject`     VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `status`      ENUM('pending','in_progress','resolved') DEFAULT 'pending',
  `admin_remark`TEXT,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_mime` VARCHAR(100) DEFAULT NULL,
  `resolution_attachment_path` VARCHAR(255) DEFAULT NULL,
  `resolution_attachment_name` VARCHAR(255) DEFAULT NULL,
  `resolution_attachment_mime` VARCHAR(100) DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: mess_menu
-- ============================================================
CREATE TABLE `mess_menu` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `month`      TINYINT NOT NULL,
  `year`       YEAR NOT NULL,
  `day_of_week`ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `breakfast`  TEXT,
  `lunch`      TEXT,
  `snacks`     TEXT,
  `dinner`     TEXT,
  `updated_by` INT(11),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_menu` (`month`,`year`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: meal_prices
-- ============================================================
CREATE TABLE `meal_prices` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `meal_type`     ENUM('breakfast','lunch','snacks','dinner') NOT NULL UNIQUE,
  `price`         DECIMAL(10,2) NOT NULL,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: mess_daily_menu (actual date menu + historical prices)
-- ============================================================
CREATE TABLE `mess_daily_menu` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `menu_date`       DATE NOT NULL,
  `breakfast`       VARCHAR(500),
  `lunch`           VARCHAR(500),
  `snacks`          VARCHAR(500),
  `dinner`          VARCHAR(500),
  `breakfast_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `lunch_enabled`     TINYINT(1) NOT NULL DEFAULT 0,
  `snacks_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
  `dinner_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
  `breakfast_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `lunch_price`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `snacks_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `dinner_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_by`      INT(11),
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_menu_date` (`menu_date`),
  KEY `daily_menu_period` (`menu_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: mess_attendance
-- ============================================================
CREATE TABLE `mess_attendance` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `date`        DATE NOT NULL,
  `breakfast`   TINYINT(1) DEFAULT 0,
  `breakfast_rate` DECIMAL(10,2) DEFAULT NULL,
  `lunch`       TINYINT(1) DEFAULT 0,
  `lunch_rate`  DECIMAL(10,2) DEFAULT NULL,
  `snacks`      TINYINT(1) DEFAULT 0,
  `snacks_rate` DECIMAL(10,2) DEFAULT NULL,
  `dinner`      TINYINT(1) DEFAULT 0,
  `dinner_rate` DECIMAL(10,2) DEFAULT NULL,
  `recorded_by` INT(11),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`resident_id`,`date`),
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: miscellaneous_charges
-- ============================================================
CREATE TABLE `miscellaneous_charges` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `month`       TINYINT NOT NULL,
  `year`        YEAR NOT NULL,
  `description` VARCHAR(200) NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `added_by`    INT(11),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: bills
-- ============================================================
CREATE TABLE `bills` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id`     INT(11) NOT NULL,
  `month`           TINYINT NOT NULL,
  `year`            YEAR NOT NULL,
  `room_rent`       DECIMAL(10,2) DEFAULT 0.00,
  `previous_dues`   DECIMAL(10,2) DEFAULT 0.00,
  `misc_charges`    DECIMAL(10,2) DEFAULT 0.00,
  `mess_charges`    DECIMAL(10,2) DEFAULT 0.00,
  `total_amount`    DECIMAL(10,2) DEFAULT 0.00,
  `payment_status`  ENUM('unpaid','paid') DEFAULT 'unpaid',
  `payment_date`    DATE DEFAULT NULL,
  `due_date`        DATE DEFAULT NULL,
  `generated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bill` (`resident_id`,`month`,`year`),
  FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Frozen meal line-items keep every generated bill historically stable.
CREATE TABLE `bill_mess_details` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `bill_id`         INT(11) NOT NULL,
  `attendance_date` DATE NOT NULL,
  `meal_type`       ENUM('breakfast','lunch','snacks','dinner') NOT NULL,
  `charged_rate`    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bill_meal` (`bill_id`,`attendance_date`,`meal_type`),
  FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SYSTEM TABLES
-- ============================================================

CREATE TABLE `notices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `raw_information` TEXT,
  `is_important` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `created_by` INT(11) DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `notices_visibility` (`status`,`is_important`,`published_at`),
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `hostel_expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` TEXT,
  `amount` DECIMAL(12,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `remarks` VARCHAR(500),
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `expenses_period` (`expense_date`,`category`),
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin account (password: admin123)
INSERT INTO `admins` (`name`,`email`,`password`,`phone`) VALUES
('Super Admin','admin@shms.com','$2y$10$zTEEUH1yv7QnYuMybGaP.e0Qy53ZuCruYnxsh1C8J.liquvhO8bDC','9000000000');

-- Mess Incharge (password: mess123)
INSERT INTO `mess_incharges` (`name`,`email`,`password`,`phone`) VALUES
('Mess Incharge','mess@shms.com','$2y$10$bNq3v1wXd5IuFWmgwJEWHOmfl/um4oZ8cPjnlXkbz1YNXwCVihgai','9111111111');

-- Room Types
INSERT INTO `room_types` (`type_name`,`description`,`beds`,`price`) VALUES
('A','Single Bed Room - Premium',1,5000.00),
('B','Double Bed Room - Standard',2,3500.00),
('C','Triple Bed Room - Economy',3,2500.00);


-- Initial meal defaults (configure actual daily prices in Mess Menu)
INSERT INTO `meal_prices` (`meal_type`,`price`) VALUES
('breakfast',50.00),
('lunch',80.00),
('snacks',30.00),
('dinner',80.00);
