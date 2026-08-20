-- ============================================================
--  Denisa Hair — inicializace databáze
--  MySQL 5.7+ / MariaDB 10.3+ / MySQL 8 / TiDB Cloud
--
--  Lokálně:      mysql -u root -p < schema.sql
--  TiDB Cloud:   vlož obsah do SQL Editoru a spusť
--                (databázi `denisa_hair` tam vytvoř přes CREATE DATABASE níž)
--
--  Přihlášení do administrace: denisa / denisa2026 (heslo si pak změň).
--
--  Poznámka ke collation: používáme utf8mb4_unicode_ci, protože
--  utf8mb4_czech_ci TiDB nepodporuje. Na řazení v aplikaci to nemá
--  vliv — řadíme jen podle datumů, ne podle textu.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `denisa_hair`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `denisa_hair`;

-- ------------------------------------------------------------
--  Tabulka: users (administrátoři)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(100) DEFAULT NULL,
    `last_login`    DATETIME     DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Výchozí administrátor — účet je rovnou funkční.
--
--   jméno: denisa
--   heslo: denisa2026
--
-- Hash je bcrypt (cost 10), kompatibilní s password_verify().
-- HESLO SI PO PRVNÍM PŘIHLÁŠENÍ ZMĚŇ v /admin/setup.php.
INSERT INTO `users` (`username`, `password_hash`, `full_name`)
VALUES (
    'denisa',
    '$2y$10$df3MBAzLKTQaFVIpckr6P.0p4HI7Xy.k19yzrn4jbFhi.DSLq2Q.m',
    'Denisa Hrabalová'
);

-- ------------------------------------------------------------
--  Tabulka: bookings (rezervace / poptávky)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(100) NOT NULL,
    `phone`            VARCHAR(30)  NOT NULL,
    `email`            VARCHAR(120) DEFAULT NULL,
    `service`          ENUM('damske','panske','detske','barveni') NOT NULL,
    `appointment_date` DATE         NOT NULL,
    `appointment_time` TIME         NOT NULL,
    `note`             TEXT         DEFAULT NULL,
    `status`           ENUM('nova','potvrzena','dokoncena','zrusena') NOT NULL DEFAULT 'nova',
    `ip_address`       VARCHAR(45)  DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status`      (`status`),
    KEY `idx_service`     (`service`),
    KEY `idx_appointment` (`appointment_date`, `appointment_time`),
    KEY `idx_created`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Tabulka: sessions (přihlášení do administrace)
--
--  Nutná pro provoz na serverless hostingu (Vercel): každý požadavek
--  tam obslouží jiná instance s vlastním /tmp, takže výchozí souborové
--  session by náhodně vypadávaly. Ukládáme je proto do databáze.
--  Na klasickém hostingu tabulka nevadí — funguje stejně.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id`         VARCHAR(128) NOT NULL,
    `payload`    TEXT         NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Ukázková data (klidně smaž)
-- ------------------------------------------------------------
INSERT INTO `bookings`
    (`name`, `phone`, `email`, `service`, `appointment_date`, `appointment_time`, `note`, `status`)
VALUES
    ('Petra Nováková', '+420 777 123 456', 'petra@example.cz', 'damske',
     DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', 'Střih na mikádo, prosím.', 'nova'),
    ('Jan Dvořák',     '+420 606 987 654', NULL,               'panske',
     DATE_ADD(CURDATE(), INTERVAL 1 DAY), '15:30:00', NULL, 'potvrzena'),
    ('Klára Malá',     '+420 731 222 333', 'klara@example.cz', 'barveni',
     DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00', 'Melír – světlé odstíny.', 'dokoncena');
