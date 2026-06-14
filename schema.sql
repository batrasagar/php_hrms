-- HRMS Database Schema
-- Run this on a fresh database, or use migrate.php for automatic migration with versioning.
CREATE DATABASE IF NOT EXISTS `u988681325_hr` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `u988681325_hr`;

CREATE TABLE IF NOT EXISTS `tblUser` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Name`          VARCHAR(100) NOT NULL,
    `Email`         VARCHAR(150) NOT NULL UNIQUE,
    `Password`      VARCHAR(255) NOT NULL,
    `Role`          ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
    `Status`        ENUM('pending','active','rejected') NOT NULL DEFAULT 'active',
    `CompanyLimit`  INT NOT NULL DEFAULT 1 COMMENT '-1 = unlimited',
    `ParentAdminId` INT UNSIGNED NULL DEFAULT NULL,
    `IsActive`      TINYINT(1) NOT NULL DEFAULT 1,
    `CreatedAt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default super-admin: batrasagar@gmail.com / 1234@@
INSERT IGNORE INTO `tblUser` (`Name`,`Email`,`Password`,`Role`,`Status`) VALUES
('Super Admin','batrasagar@gmail.com','$2y$10$mBFepdju5KPEBAUfZ.3yt.TyDgcQD4hUgLKy0UZeSH9SteIr.mbua','superadmin','active');

CREATE TABLE IF NOT EXISTS `tblDevices` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Company`      VARCHAR(150) NOT NULL DEFAULT '',
    `SerialNumber` VARCHAR(100) NOT NULL UNIQUE,
    `LastPing`     DATETIME NULL DEFAULT NULL,
    `Stamp`        VARCHAR(20) NOT NULL DEFAULT '1541180497',
    `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tblApiKeys` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Label`     VARCHAR(100) NOT NULL,
    `Company`   VARCHAR(150) NOT NULL,
    `RawKey`    CHAR(64)     NOT NULL UNIQUE,
    `KeyHash`   CHAR(64)     NOT NULL UNIQUE COMMENT 'SHA-256 hex of the raw key',
    `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
    `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tblCompany` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `AdminId`   INT UNSIGNED NOT NULL,
    `Name`      VARCHAR(150) NOT NULL,
    `Address`   TEXT NULL,
    `Phone`     VARCHAR(20) NULL DEFAULT NULL,
    `Email`     VARCHAR(150) NULL DEFAULT NULL,
    `IsActive`  TINYINT(1) NOT NULL DEFAULT 1,
    `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_admin` (`AdminId`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tblEmployee` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `CompanyId`    INT UNSIGNED NOT NULL,
    `EmployeeCode` VARCHAR(50)  NOT NULL DEFAULT '',
    `EnrollId`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'Maps to biometric device EnrollId',
    `Name`         VARCHAR(100) NOT NULL,
    `Email`        VARCHAR(150) NULL DEFAULT NULL,
    `Phone`        VARCHAR(20)  NULL DEFAULT NULL,
    `Department`   VARCHAR(100) NULL DEFAULT NULL,
    `Designation`  VARCHAR(100) NULL DEFAULT NULL,
    `JoinDate`     DATE         NULL DEFAULT NULL,
    `Status`       ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    `CreatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`CompanyId`),
    INDEX `idx_enroll`  (`EnrollId`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tblAdmsCredentials` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `Label`     VARCHAR(100) NOT NULL,
    `Endpoint`  VARCHAR(500) NOT NULL COMMENT 'Base URL of the AttnLog server, no trailing slash',
    `ApiKey`    VARCHAR(100) NOT NULL COMMENT 'X-Api-Key issued by the AttnLog server',
    `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
    `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tblDeviceLog` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `SerialNumber`  VARCHAR(100) NOT NULL,
    `EnrollId`      VARCHAR(50)  NOT NULL,
    `PunchDateTime` DATETIME     NOT NULL,
    `Mode`          VARCHAR(10)  NOT NULL DEFAULT '',
    `Stamp`         VARCHAR(20)  NOT NULL DEFAULT '',
    `blSms`         TINYINT(1)   NOT NULL DEFAULT 0,
    `blEmail`       TINYINT(1)   NOT NULL DEFAULT 0,
    `CreatedAt`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_punch` (`SerialNumber`,`EnrollId`,`PunchDateTime`)
) ENGINE=InnoDB;
