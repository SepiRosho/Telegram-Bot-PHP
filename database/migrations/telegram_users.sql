CREATE TABLE IF NOT EXISTS `telegram_users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `telegram_id`       BIGINT UNSIGNED NOT NULL,
    `chat_id`           BIGINT          NOT NULL,
    `first_name`        VARCHAR(255)    NOT NULL,
    `last_name`         VARCHAR(255)    NULL,
    `username`          VARCHAR(255)    NULL,
    `language_code`     VARCHAR(10)     NULL,
    `role`              VARCHAR(50)     NOT NULL DEFAULT 'user',
    `permissions`       JSON            NULL,
    `is_banned`         TINYINT(1)      NOT NULL DEFAULT 0,
    `ban_reason`        TEXT            NULL,
    `banned_at`         TIMESTAMP       NULL,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `step`              VARCHAR(255)    NULL,
    `temp_data`         JSON            NULL,
    `referral_code`     VARCHAR(32)     NULL,
    `invited_by`        BIGINT UNSIGNED NULL,
    `joined_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `rate_hits`         JSON            NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `telegram_users_telegram_id_unique` (`telegram_id`),
    UNIQUE KEY `telegram_users_referral_code_unique` (`referral_code`),
    KEY `telegram_users_invited_by_foreign` (`invited_by`),
    CONSTRAINT `telegram_users_invited_by_foreign`
        FOREIGN KEY (`invited_by`) REFERENCES `telegram_users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
