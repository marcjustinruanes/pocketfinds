-- Additive mobile API support for the EXISTING MySQL 8 pocketfinds database.
-- Run once (or repeat safely). No tables or existing account data are dropped.
USE `pocketfinds`;

CREATE TABLE IF NOT EXISTS `mobile_checkouts` (
  `buyer_id` BIGINT NOT NULL,
  `checkout_id` CHAR(36) NOT NULL,
  `request_hash` CHAR(64) NOT NULL,
  `result` JSON NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`buyer_id`, `checkout_id`),
  CONSTRAINT `mobile_checkout_buyer_fk` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_email_challenges` (
  `email` VARCHAR(255) NOT NULL,
  `purpose` VARCHAR(32) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_registration_tokens` (
  `id` CHAR(36) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `user_id` BIGINT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_registration_token_unique` (`token_hash`),
  CONSTRAINT `mobile_registration_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The website's current reviews table lacks the old Flutter media fields.
SET @mobile_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reviews' AND column_name = 'media_url') = 0,
  'ALTER TABLE `reviews` ADD COLUMN `media_url` VARCHAR(2048) NULL', 'SELECT 1');
PREPARE mobile_statement FROM @mobile_ddl;
EXECUTE mobile_statement;
DEALLOCATE PREPARE mobile_statement;
SET @mobile_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reviews' AND column_name = 'is_anonymous') = 0,
  'ALTER TABLE `reviews` ADD COLUMN `is_anonymous` BOOLEAN NOT NULL DEFAULT FALSE', 'SELECT 1');
PREPARE mobile_statement FROM @mobile_ddl;
EXECUTE mobile_statement;
DEALLOCATE PREPARE mobile_statement;

-- The website now stores wishlist snapshots in wishlist_entries.
INSERT INTO `wishlist_entries` (`id`, `buyer_id`, `product_id`, `product_name`, `image`, `price`, `created_at`, `updated_at`)
SELECT w.id, w.buyer_id, w.product_id, p.name, p.image, COALESCE(p.discount_price, p.price), w.created_at, w.created_at
FROM wishlist_items w JOIN products p ON p.id = w.product_id
WHERE NOT EXISTS (SELECT 1 FROM wishlist_entries e WHERE e.buyer_id = w.buyer_id AND e.product_id = w.product_id);
