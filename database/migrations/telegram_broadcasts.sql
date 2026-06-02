CREATE TABLE IF NOT EXISTS `telegram_broadcasts` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message`           TEXT            NOT NULL,
    `type`              VARCHAR(50)     NOT NULL DEFAULT 'text',
    `options`           JSON            NULL,
    `status`            ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    `total_recipients`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `sent_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `failed_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `scheduled_at`      TIMESTAMP       NULL,
    `started_at`        TIMESTAMP       NULL,
    `completed_at`      TIMESTAMP       NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `telegram_broadcasts_status_index` (`status`),
    KEY `telegram_broadcasts_scheduled_at_index` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
