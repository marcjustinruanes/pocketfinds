-- Additive app support. Existing website source and business records retained.
USE `pocketfinds`;
CREATE TABLE IF NOT EXISTS mobile_address_details (
 address_id BIGINT NOT NULL PRIMARY KEY, postal_code CHAR(4) NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_order_quotes (
 subject_id CHAR(36) NOT NULL PRIMARY KEY, buyer_id BIGINT NOT NULL,
 checkout_id CHAR(36) NOT NULL, snapshot JSON NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_request_journal (
 scope VARCHAR(100) NOT NULL, request_id CHAR(36) NOT NULL,
 request_hash CHAR(64) NOT NULL, result JSON NULL, created_at DATETIME NOT NULL,
 PRIMARY KEY(scope,request_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_buyer_reviews (
 order_id CHAR(36) NOT NULL, item_key INT NOT NULL, review_id CHAR(36) NOT NULL,
 photos JSON NOT NULL, edited_at DATETIME NULL,
 PRIMARY KEY(order_id,item_key), UNIQUE KEY(review_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_product_rules (
 product_id CHAR(36) NOT NULL PRIMARY KEY, per_buyer_limit INT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_refunds (
 id CHAR(36) NOT NULL PRIMARY KEY, order_id CHAR(36) NOT NULL,
 buyer_id BIGINT NOT NULL, seller_id BIGINT NOT NULL, amount DECIMAL(12,2) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending_reconciliation',
 destination VARCHAR(255) NULL, reference VARCHAR(255) NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE KEY(order_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_push_devices (
 token VARCHAR(255) NOT NULL PRIMARY KEY, user_id BIGINT NOT NULL,
 session_hash CHAR(64) NULL,
 platform VARCHAR(10) NOT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_message_alerts (
 message_id BIGINT NOT NULL PRIMARY KEY, created_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_push_outbox (
 notification_id CHAR(36) NOT NULL, device_token VARCHAR(255) NOT NULL,
 user_id BIGINT NOT NULL, payload JSON NOT NULL, sent_at DATETIME NULL,
 attempts INT NOT NULL DEFAULT 0, last_error VARCHAR(255) NULL,
 created_at DATETIME NOT NULL, PRIMARY KEY(notification_id,device_token)
) ENGINE=InnoDB;

-- App commerce tables (generated from the installed app schema).
CREATE TABLE IF NOT EXISTS `mobile_commerce_wishlist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` bigint NOT NULL,
  `product_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_wishlist_buyer_id_product_id_unique` (`buyer_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('store','platform','shipping') COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` bigint DEFAULT NULL,
  `legacy_store_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_platform_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legacy_used_count` int unsigned NOT NULL DEFAULT '0',
  `discount_type` enum('fixed','percent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `cap` decimal(12,2) DEFAULT NULL,
  `minimum_spend` decimal(12,2) NOT NULL DEFAULT '0.00',
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `usage_limit` int unsigned NOT NULL,
  `per_buyer_limit` int unsigned NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_vouchers_code_unique` (`code`),
  UNIQUE KEY `mobile_commerce_vouchers_legacy_store_id_unique` (`legacy_store_id`),
  UNIQUE KEY `mobile_commerce_vouchers_legacy_platform_id_unique` (`legacy_platform_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` bigint NOT NULL,
  `voucher_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_claims_buyer_id_voucher_id_unique` (`buyer_id`,`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usage_key` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_id` bigint NOT NULL,
  `voucher_id` bigint unsigned DEFAULT NULL,
  `flash_sale_id` bigint unsigned DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '0',
  `state` enum('held','used','released') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'held',
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mobile_commerce_allocations_subject_id_index` (`subject_id`),
  KEY `mobile_commerce_allocations_usage_key_index` (`usage_key`),
  KEY `mobile_commerce_allocations_voucher_id_index` (`voucher_id`),
  KEY `mobile_commerce_allocations_flash_sale_id_index` (`flash_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_flash_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint NOT NULL,
  `product_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `quantity` int unsigned NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `mobile_commerce_flash_sales_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `buyer_id` bigint NOT NULL,
  `flash_sale_id` bigint unsigned NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_reminders_buyer_id_flash_sale_id_unique` (`buyer_id`,`flash_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_shipping_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint NOT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_kg` decimal(12,2) NOT NULL,
  `additional_kg` decimal(12,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_shipping_rates_seller_id_region_unique` (`seller_id`,`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_checkouts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_id` bigint NOT NULL,
  `request_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_commerce_returns` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_id` bigint NOT NULL,
  `seller_id` bigint NOT NULL,
  `item_keys` json NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `photos` json NOT NULL,
  `status` enum('pending','approved','rejected','escalated','received','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(12,2) NOT NULL,
  `refund_destination` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `decision_reason` text COLLATE utf8mb4_unicode_ci,
  `decided_at` timestamp NULL DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `refund_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_commerce_returns_order_id_unique` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

