-- ACS Atletis – gestiune membri
-- Importă acest fișier în phpMyAdmin, în baza de date creată din cPanel.
-- Compatibil MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS members (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name               VARCHAR(100) NOT NULL,
    last_name                VARCHAR(100) NOT NULL,
    birth_date               DATE NULL,
    gender                   ENUM('M','F') NULL,
    email                    VARCHAR(190) NULL,
    phone                    VARCHAR(40)  NULL,
    address                  VARCHAR(255) NULL,
    category                 VARCHAR(100) NULL,
    coach                    VARCHAR(100) NULL,
    joined_on                DATE NULL,
    strava_url               VARCHAR(255) NULL,
    subscription_expires_on  DATE NULL,
    notes                    TEXT NULL,
    is_active                TINYINT(1) NOT NULL DEFAULT 1,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_members_name (last_name, first_name),
    KEY idx_members_subscription (subscription_expires_on),
    KEY idx_members_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conturi de acces. Acum doar admin; rolul 'member' + member_id sunt
-- pregătite pentru logarea membrilor pe viitor.
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','member') NOT NULL DEFAULT 'member',
    member_id      INT UNSIGNED NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at  DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_member (member_id),
    CONSTRAINT fk_users_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jurnalul emailurilor trimise, ca să nu trimitem același mesaj de două ori.
CREATE TABLE IF NOT EXISTS notification_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id   INT UNSIGNED NULL,
    type        VARCHAR(40)  NOT NULL,
    ref_date    DATE NOT NULL,
    recipient   VARCHAR(190) NOT NULL,
    status      ENUM('sent','failed') NOT NULL,
    error       VARCHAR(500) NULL,
    sent_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_lookup (type, member_id, ref_date, status),
    CONSTRAINT fk_notif_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protecție împotriva încercărilor repetate de ghicire a parolei.
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip            VARCHAR(45)  NOT NULL,
    email         VARCHAR(190) NOT NULL,
    attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_ip (ip, attempted_at),
    KEY idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
