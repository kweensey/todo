CREATE TABLE `users` (
  `id` BINARY(16) NOT NULL,
  `uuid` CHAR(36) GENERATED ALWAYS AS (
    UPPER(CONCAT(
      SUBSTR(HEX(`id`), 1, 8), '-',
      SUBSTR(HEX(`id`), 9, 4), '-',
      SUBSTR(HEX(`id`), 13, 4), '-',
      SUBSTR(HEX(`id`), 17, 4), '-',
      SUBSTR(HEX(`id`), 21, 12)
    ))
  ) VIRTUAL,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,

  `created_by_id` BINARY(16) DEFAULT NULL,

  `status` TINYINT UNSIGNED NOT NULL,
  `role` TINYINT UNSIGNED DEFAULT NULL,

  `email` VARCHAR(128) NOT NULL,
  `email_active` VARCHAR(128) GENERATED ALWAYS AS (
    CASE WHEN `deleted_at` IS NULL THEN LOWER(`email`) ELSE NULL END
  ) VIRTUAL,
  `email_new` VARCHAR(128) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,

  `invite_date` DATETIME DEFAULT NULL,
  `invite_email` VARCHAR(128) DEFAULT NULL,

  `register_date` DATETIME DEFAULT NULL,
  `login_date` DATETIME DEFAULT NULL,
  
  `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_login_at` DATETIME DEFAULT NULL,

  `otp_failed_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `otp_started_at` DATETIME DEFAULT NULL,

  `otp_issued_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `otp_issuance_started_at` DATETIME DEFAULT NULL,

  `invite_token_hash` BINARY(32) DEFAULT NULL,

  `recovery_token_hash` BINARY(32) DEFAULT NULL,
  `recovery_issued_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `recovery_issuance_started_at` DATETIME DEFAULT NULL,
  `recovery_requested_at` DATETIME DEFAULT NULL,

  PRIMARY KEY (`id`),

  UNIQUE KEY `users_email_active_unique` (`email_active`),

  KEY `users_uuid_index` (`uuid`),
  KEY `users_created_by_id_index` (`created_by_id`),
  KEY `users_email_index` (`email`),
  KEY `users_invite_token_hash_index` (`invite_token_hash`),
  KEY `users_recovery_token_hash_index` (`recovery_token_hash`),

  CONSTRAINT `users_created_by_id_foreign`
    FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `refresh_tokens` (
  `token_id` CHAR(32) NOT NULL,
  `access_jti` CHAR(32) DEFAULT NULL,
  `rotated_to_token_id` CHAR(32) DEFAULT NULL,

  `issued_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,

  `token_hash` BINARY(32) NOT NULL,
  `user_id` BINARY(16) NOT NULL,
  `family_id` CHAR(32) NOT NULL,

  PRIMARY KEY (`token_id`),

  KEY `refresh_tokens_user_id_index` (`user_id`),
  KEY `refresh_tokens_family_id_index` (`family_id`),
  KEY `refresh_tokens_expires_at_index` (`expires_at`),
  KEY `refresh_tokens_rotated_to_token_id_index` (`rotated_to_token_id`),

  CONSTRAINT `refresh_tokens_rotated_to_token_id_foreign`
    FOREIGN KEY (`rotated_to_token_id`) REFERENCES `refresh_tokens` (`token_id`),

  CONSTRAINT `refresh_tokens_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `challenges` (
  `user_id` BINARY(16) NOT NULL,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,

  `recipient` VARCHAR(128) NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `code_hash` BINARY(32) NOT NULL,

  PRIMARY KEY (`user_id`),

  CONSTRAINT `challenges_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tasks` (
  `id` BINARY(16) NOT NULL,
  `uuid` CHAR(36) GENERATED ALWAYS AS (
    UPPER(CONCAT(
      SUBSTR(HEX(`id`), 1, 8), '-',
      SUBSTR(HEX(`id`), 9, 4), '-',
      SUBSTR(HEX(`id`), 13, 4), '-',
      SUBSTR(HEX(`id`), 17, 4), '-',
      SUBSTR(HEX(`id`), 21, 12)
    ))
  ) VIRTUAL,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,

  `user_id` BINARY(16) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `completed` TINYINT(1) NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),

  KEY `tasks_uuid_index` (`uuid`),

  CONSTRAINT `tasks_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
