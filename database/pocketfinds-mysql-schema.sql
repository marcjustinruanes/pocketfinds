-- PocketFinds: MySQL Workbench review model, based on live public schema.
-- Schema only: 43 tables / 534 columns. No data migration or Supabase deletion.
-- Import with File > Import > Reverse Engineer MySQL Create Script.
-- Review adaptations: CHECK value lists become ENUM; UUID defaults require application generation.
-- Unbounded numeric becomes DECIMAL(65,20); vector(48) becomes JSON (search redesign required).
-- Timestamptz becomes DATETIME(6); convert existing data to UTC on migration.
-- Indexed PostgreSQL text becomes VARCHAR(255); validate existing lengths before migration.
-- RLS, functions, triggers, and non-constraint indexes are outside this review model.

CREATE SCHEMA IF NOT EXISTS `pocketfinds` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pocketfinds`;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `account_update_requests` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT NULL,
  `reviewed_at` DATETIME(0) NULL,
  `note` LONGTEXT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `requested_changes` JSON NULL,
  `requested_documents` JSON NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `account_update_requests_document_update_requests_re_60cc8efcf59d` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_update_requests_document_update_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `announcements` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `title` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `created_by` BIGINT NULL,
  `is_active` BOOLEAN NULL DEFAULT true,
  `published_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `expires_at` DATETIME(6) NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `audience` VARCHAR(255) NOT NULL DEFAULT 'all',
  CONSTRAINT `announcements_announcements_created_by_fkey` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `blocked_users` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `blocker_id` BIGINT NOT NULL,
  `blocked_id` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `blocked_users_blocked_users_blocked_id_fkey` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `blocked_users_blocker_id_blocked_id_key` (`blocker_id`, `blocked_id`),
  CONSTRAINT `blocked_users_blocked_users_blocker_id_fkey` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `buyer_addresses` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `buyer_id` BIGINT NOT NULL,
  `label` VARCHAR(255) NULL,
  `recipient_name` VARCHAR(255) NOT NULL,
  `contact_no` VARCHAR(255) NULL,
  `province` VARCHAR(255) NOT NULL,
  `municipality` VARCHAR(255) NOT NULL,
  `barangay` VARCHAR(255) NOT NULL,
  `house_no` VARCHAR(255) NULL,
  `street` VARCHAR(255) NULL,
  `is_default` BOOLEAN NOT NULL DEFAULT false,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  CONSTRAINT `buyer_addresses_buyer_addresses_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `buyer_payment_accounts` (
  `id` CHAR(36) NOT NULL,
  `buyer_id` BIGINT NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(255) NOT NULL,
  `bank_name` VARCHAR(255) NULL,
  `verified` BOOLEAN NOT NULL DEFAULT false,
  `verified_at` DATETIME(0) NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  CONSTRAINT `buyer_payment_accounts_buyer_payment_accounts_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `cart_items` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `buyer_id` BIGINT NOT NULL,
  `product_id` CHAR(36) NOT NULL,
  `seller_slug` VARCHAR(255) NOT NULL,
  `seller` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `img` VARCHAR(255) NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `qty` INT NOT NULL,
  `variation_value` VARCHAR(255) NOT NULL DEFAULT '',
  `variation_group` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  CONSTRAINT `cart_items_cart_items_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `cart_items_buyer_product_variation_unique` (`buyer_id`, `product_id`, `variation_value`, `variation_group`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `categories` (
  `id` BIGINT NOT NULL,
  `name` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `category_seller` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `category_id` BIGINT NOT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  CONSTRAINT `category_seller_category_seller_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_seller_user_id_category_id_unique` (`user_id`, `category_id`),
  CONSTRAINT `category_seller_category_seller_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `coin_transactions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `user_id` BIGINT NOT NULL,
  `delta` BIGINT NOT NULL,
  `reason` LONGTEXT NOT NULL,
  `order_id` CHAR(36) NULL,
  `review_id` CHAR(36) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `coin_transactions_coin_transactions_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `coin_transactions_coin_transactions_review_id_fkey` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE SET NULL,
  CONSTRAINT `coin_transactions_coin_transactions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `commissions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_id` CHAR(36) NOT NULL,
  `seller_id` BIGINT NOT NULL,
  `order_amount` DECIMAL(12,2) NOT NULL,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `commission_amount` DECIMAL(12,2) NOT NULL,
  `seller_earnings` DECIMAL(12,2) NOT NULL,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `commissions_commissions_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `commissions_commissions_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `company_vehicles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(255) NOT NULL,
  `logistics_hub_id` BIGINT NOT NULL,
  `vehicle_type` VARCHAR(255) NOT NULL,
  `brand` VARCHAR(255) NOT NULL,
  `model` VARCHAR(255) NOT NULL,
  `plate_number` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `status_reason` LONGTEXT NULL,
  `is_available` BOOLEAN NOT NULL DEFAULT true,
  `submitted_by` BIGINT NOT NULL,
  `reviewed_by` BIGINT NULL,
  `reviewed_at` DATETIME(0) NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `platform_status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `platform_status_reason` LONGTEXT NULL,
  `platform_reviewed_by` BIGINT NULL,
  `platform_reviewed_at` DATETIME(0) NULL,
  CONSTRAINT `company_vehicles_company_vehicles_logistics_hub_id_foreign` FOREIGN KEY (`logistics_hub_id`) REFERENCES `logistics_hubs` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_vehicles_plate_number_unique` (`plate_number`),
  CONSTRAINT `company_vehicles_company_vehicles_platform_reviewed_by_foreign` FOREIGN KEY (`platform_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_vehicles_company_vehicles_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_vehicles_company_vehicles_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `complaints` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_id` CHAR(36) NULL,
  `complainant_id` BIGINT NOT NULL,
  `respondent_id` BIGINT NULL,
  `complaint_type` VARCHAR(100) NULL,
  `subject` VARCHAR(255) NULL,
  `description` LONGTEXT NULL,
  `status` VARCHAR(30) NULL DEFAULT 'open',
  `resolution` LONGTEXT NULL,
  `handled_by` BIGINT NULL,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `resolved_at` DATETIME(6) NULL,
  `message_id` BIGINT NULL,
  `shop_name` VARCHAR(255) NULL,
  `message_body` LONGTEXT NULL,
  `message_type` VARCHAR(255) NULL,
  `evidence_path` VARCHAR(255) NULL,
  `evidence_name` VARCHAR(255) NULL,
  `evidence_mime` VARCHAR(255) NULL,
  `evidence_type` VARCHAR(255) NULL,
  `evidence_size` BIGINT NULL,
  CONSTRAINT `complaints_complaints_complainant_id_fkey` FOREIGN KEY (`complainant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `complaints_complaints_handled_by_fkey` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `complaints_complaints_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `complaints_complaints_respondent_id_fkey` FOREIGN KEY (`respondent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `delivery_assignments` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `shipment_id` CHAR(36) NOT NULL,
  `courier_id` BIGINT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'requested',
  `requested_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `accepted_at` DATETIME(6) NULL,
  `picked_up_at` DATETIME(6) NULL,
  `delivered_at` DATETIME(6) NULL,
  `scheduled_pickup_at` DATETIME(0) NULL,
  `leg` VARCHAR(255) NOT NULL DEFAULT 'delivery',
  CONSTRAINT `delivery_assignments_delivery_assignments_courier_id_fkey` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `delivery_assignments_delivery_assignments_shipment_id_fkey` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `document_update_requests` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `id_type_id` VARCHAR(255) NULL,
  `id_file` VARCHAR(255) NULL,
  `business_permit_file` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT NULL,
  `reviewed_at` DATETIME(0) NULL,
  `note` LONGTEXT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `document_update_requests_document_update_requests_r_530e3ca6b9c0` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_update_requests_document_update_requests_u_5a260462d2cd` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `id_types` (
  `id` BIGINT NOT NULL,
  `name` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `logistics_centers` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `address` LONGTEXT NULL,
  `contact_no` VARCHAR(255) NULL,
  `hours_note` VARCHAR(255) NULL,
  `updated_by` BIGINT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `location` LONGTEXT NULL,
  `accepting_applications` BOOLEAN NOT NULL DEFAULT true,
  `available_slots` INT NOT NULL DEFAULT 0,
  `service_area` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `logistics_centers_logistics_centers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `logistics_hubs` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(255) NOT NULL,
  `province` VARCHAR(255) NOT NULL,
  `municipality` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `is_regional_hub` BOOLEAN NOT NULL DEFAULT false,
  `is_hiring` BOOLEAN NOT NULL DEFAULT true,
  UNIQUE KEY `logistics_hubs_company_name_province_municipality_unique` (`company_name`, `province`, `municipality`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `message_reactions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `message_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `emoji` LONGTEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `message_reactions_message_reactions_message_id_fkey` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `message_reactions_message_id_user_id_key` (`message_id`, `user_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `message_reactions_message_reactions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `messages` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `sender_id` BIGINT NOT NULL,
  `receiver_id` BIGINT NOT NULL,
  `body` LONGTEXT NULL,
  `read` BOOLEAN NOT NULL DEFAULT false,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `product_id` CHAR(36) NULL,
  `attachment_path` VARCHAR(255) NULL,
  `attachment_name` VARCHAR(255) NULL,
  `attachment_type` VARCHAR(255) NULL,
  `attachment_mime` VARCHAR(255) NULL,
  `attachment_size` BIGINT NULL,
  `variation_label` VARCHAR(255) NULL,
  `variation_price` DECIMAL(10,2) NULL,
  `variation_image` VARCHAR(255) NULL,
  `reply_to_id` BIGINT NULL,
  `order_id` CHAR(36) NULL,
  `reactions` JSON NULL,
  CONSTRAINT `messages_messages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `messages_messages_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_messages_receiver_id_foreign` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_messages_reply_to_id_fkey` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `migrations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `notifications` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `user_id` BIGINT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` LONGTEXT NOT NULL,
  `notification_type` VARCHAR(50) NULL,
  `reference_id` CHAR(36) NULL,
  `is_read` BOOLEAN NULL DEFAULT false,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  CONSTRAINT `notifications_notifications_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `order_status_history` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_id` CHAR(36) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `changed_by` BIGINT NULL,
  `notes` LONGTEXT NULL,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `order_status_history_order_status_history_changed_by_fkey` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_status_history_order_status_history_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `orders` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `buyer_id` BIGINT NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(50) NOT NULL,
  `updated_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `order_number` VARCHAR(255) NULL,
  `seller_id` BIGINT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'to_ship',
  `items` JSON NULL,
  `shipping_amount` DECIMAL(12,2) NOT NULL DEFAULT '0',
  `total` DECIMAL(12,2) NOT NULL DEFAULT '0',
  `shipping_address` JSON NULL,
  `payment_method_id` BIGINT NULL,
  `created_at` DATETIME(0) NULL,
  `cancellation_reason` VARCHAR(255) NULL,
  `cancellation_note` LONGTEXT NULL,
  `buyer_note` LONGTEXT NULL,
  `voucher_code` VARCHAR(255) NULL,
  `app_buyer_id` BIGINT NULL,
  `app_seller_id` BIGINT NULL,
  `coins_used` BIGINT NOT NULL DEFAULT 0,
  `coin_discount_amount` DECIMAL(65,20) NOT NULL DEFAULT 0,
  `platform_voucher_id` CHAR(36) NULL,
  `platform_discount_amount` DECIMAL(65,20) NOT NULL DEFAULT 0,
  CONSTRAINT `orders_orders_app_buyer_id_fkey` FOREIGN KEY (`app_buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_orders_app_seller_id_fkey` FOREIGN KEY (`app_seller_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `orders_orders_platform_voucher_id_fkey` FOREIGN KEY (`platform_voucher_id`) REFERENCES `platform_vouchers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `payment_methods` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT true,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'other',
  `requires_verification` BOOLEAN NOT NULL DEFAULT false,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `payment_verifications` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_id` CHAR(36) NOT NULL,
  `buyer_id` BIGINT NOT NULL,
  `reference_number` LONGTEXT NOT NULL,
  `proof_path` LONGTEXT NOT NULL,
  `status` ENUM('pending', 'submitted', 'under_review', 'confirmed', 'rejected') NOT NULL DEFAULT 'submitted',
  `rejection_reason` LONGTEXT NULL,
  `submitted_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `reviewed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `payment_verifications_payment_verifications_buyer_id_fkey` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_verifications_payment_verifications_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `payment_verifications_order_id_key` (`order_id`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `platform_vouchers` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `title` LONGTEXT NOT NULL,
  `description` LONGTEXT NULL,
  `type` ENUM('free_shipping', 'discount', 'coins_cashback') NOT NULL,
  `discount_amount` DECIMAL(65,20) NULL,
  `discount_percent` DECIMAL(65,20) NULL,
  `minimum_spend` DECIMAL(65,20) NULL,
  `expires_at` DATE NULL,
  `usage_limit` INT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `is_active` BOOLEAN NOT NULL DEFAULT true,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `policies` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NULL,
  `updated_by` BIGINT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `history` JSON NULL,
  `account_type` VARCHAR(255) NULL,
  `company_name` VARCHAR(255) NULL,
  `pending_content` LONGTEXT NULL,
  `pending_submitted_by` BIGINT NULL,
  `pending_submitted_at` DATETIME(0) NULL,
  `rejection_reason` VARCHAR(255) NULL,
  CONSTRAINT `policies_policies_pending_submitted_by_foreign` FOREIGN KEY (`pending_submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `policies_type_account_type_unique` (`type`, `account_type`),
  CONSTRAINT `policies_policies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `product_images` (
  `id` CHAR(36) NOT NULL,
  `product_id` CHAR(36) NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `is_primary` BOOLEAN NOT NULL DEFAULT false,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `product_images_product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `products` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `seller_id` BIGINT NOT NULL,
  `category_id` BIGINT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `sku` VARCHAR(100) NULL,
  `status` ENUM('active', 'archived', 'pending', 'rejected') NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `rejection_note` LONGTEXT NULL,
  `image` VARCHAR(255) NULL,
  `variations` JSON NULL,
  `details` JSON NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `weight_grams` INT NOT NULL DEFAULT 0,
  `length_cm` DECIMAL(6,1) NULL,
  `width_cm` DECIMAL(6,1) NULL,
  `height_cm` DECIMAL(6,1) NULL,
  `condition` VARCHAR(255) NOT NULL DEFAULT 'new',
  `images` JSON NULL,
  `video` VARCHAR(255) NULL,
  `weight_grams_max` INT NULL,
  `discount_price` DECIMAL(10,2) NULL,
  `restock_date` DATE NULL,
  `image_signature` JSON NULL COMMENT 'PostgreSQL vector(48); JSON array of 48 numbers; redesign vector search',
  PRIMARY KEY (`id`),
  CONSTRAINT `products_products_price_check` CHECK ((price >= (0))),
  CONSTRAINT `products_products_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `products_sku_key` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `reviews` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_item_id` CHAR(36) NULL,
  `buyer_id` BIGINT NOT NULL,
  `seller_id` BIGINT NULL,
  `product_id` CHAR(36) NOT NULL,
  `rating` INT NOT NULL,
  `comment` LONGTEXT NULL,
  `seller_reply` LONGTEXT NULL,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `order_id` CHAR(36) NULL,
  CONSTRAINT `reviews_reviews_buyer_id_fkey` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_reviews_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `reviews_reviews_product_id_fkey` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `reviews_reviews_rating_check` CHECK (((rating >= 1) AND (rating <= 5))),
  CONSTRAINT `reviews_reviews_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `rider_profiles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `auth_method` ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
  `google_id` VARCHAR(255) NULL,
  `username` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `given_names` VARCHAR(255) NOT NULL,
  `middle_name` VARCHAR(255) NULL,
  `sex` ENUM('male', 'female') NOT NULL,
  `birthday` DATE NOT NULL,
  `age` SMALLINT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `contact_no` VARCHAR(11) NOT NULL,
  `province` VARCHAR(255) NOT NULL,
  `municipality` VARCHAR(255) NOT NULL,
  `barangay` VARCHAR(255) NOT NULL,
  `house_no` VARCHAR(255) NULL,
  `street` VARCHAR(255) NULL,
  `password` VARCHAR(255) NULL,
  `id_file` VARCHAR(255) NULL,
  `id_type_id` BIGINT NULL,
  `selfie_file` VARCHAR(255) NULL,
  `vehicle_type` ENUM('motorcycle', 'bicycle', 'car_van') NOT NULL,
  `vehicle_brand` VARCHAR(255) NULL,
  `vehicle_model` VARCHAR(255) NULL,
  `plate_number` VARCHAR(255) NULL,
  `or_file` VARCHAR(255) NULL,
  `cr_file` VARCHAR(255) NULL,
  `license_number` VARCHAR(255) NULL,
  `license_expiry` DATE NULL,
  `license_file` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  UNIQUE KEY `rider_profiles_email_unique` (`email`),
  PRIMARY KEY (`id`),
  CONSTRAINT `rider_profiles_rider_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` LONGTEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `settings` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `platform_name` VARCHAR(255) NOT NULL DEFAULT 'PocketFinds',
  `support_email` VARCHAR(255) NOT NULL DEFAULT 'anchetanicole1020@gmail.com',
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT '10',
  `google_signin_enabled` BOOLEAN NOT NULL DEFAULT true,
  `new_registrations_enabled` BOOLEAN NOT NULL DEFAULT true,
  `maintenance_mode` BOOLEAN NOT NULL DEFAULT false,
  `email_notifications_enabled` BOOLEAN NOT NULL DEFAULT true,
  `updated_by` BIGINT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `hero_image` VARCHAR(255) NULL,
  `hero_label` VARCHAR(100) NULL DEFAULT 'Local Marketplace · Philippines',
  `hero_tagline` VARCHAR(200) NULL DEFAULT 'Find It. Love It. Pocket It.',
  `hero_subtitle` VARCHAR(500) NULL DEFAULT 'Browse products from verified local sellers — pet supplies, electronics, fashion, home essentials, and more.',
  `hero_cta_text` VARCHAR(80) NULL DEFAULT 'Browse Products',
  `hero_overlay` VARCHAR(30) NULL DEFAULT 'dark',
  PRIMARY KEY (`id`),
  CONSTRAINT `settings_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `shipment_hub_legs` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `shipment_id` CHAR(36) NOT NULL,
  `sequence` INT NOT NULL,
  `leg_type` VARCHAR(255) NOT NULL,
  `from_hub` VARCHAR(255) NOT NULL,
  `to_hub` VARCHAR(255) NOT NULL,
  `rider_id` BIGINT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME(0) NULL,
  `completed_at` DATETIME(0) NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `requested_at` DATETIME(0) NULL,
  `requested_by` BIGINT NULL,
  `approved_at` DATETIME(0) NULL,
  `approved_by` BIGINT NULL,
  `rider_confirmed_pickup_at` DATETIME(0) NULL,
  CONSTRAINT `shipment_hub_legs_shipment_hub_legs_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `shipment_hub_legs_shipment_hub_legs_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipment_hub_legs_shipment_hub_legs_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipment_hub_legs_shipment_hub_legs_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `shipment_hub_legs_shipment_id_sequence_unique` (`shipment_id`, `sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `shipments` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `order_id` CHAR(36) NOT NULL,
  `tracking_number` VARCHAR(100) NULL,
  `courier_id` BIGINT NULL,
  `shipping_status` VARCHAR(30) NULL DEFAULT 'pending',
  `picked_up_at` DATETIME(6) NULL,
  `in_transit_at` DATETIME(6) NULL,
  `out_for_delivery_at` DATETIME(6) NULL,
  `delivered_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(0) NULL,
  `scheduled_pickup_at` DATETIME(0) NULL,
  `delivery_fee` DECIMAL(65,20) NULL,
  `pickup_rider_id` BIGINT NULL,
  `sorted_area` VARCHAR(255) NULL,
  `delivery_failed_reason` LONGTEXT NULL,
  `at_sorting_center_at` DATETIME(0) NULL,
  `sorted_at` DATETIME(0) NULL,
  `assigned_at` DATETIME(0) NULL,
  `delivery_failed_at` DATETIME(0) NULL,
  `returned_at` DATETIME(0) NULL,
  `pickup_approved_at` DATETIME(0) NULL,
  `logistics_company` VARCHAR(255) NULL,
  `origin_hub` VARCHAR(255) NULL,
  `destination_hub` VARCHAR(255) NULL,
  `hub_transfer_rider_id` BIGINT NULL,
  `hub_transfer_started_at` DATETIME(0) NULL,
  `hub_transfer_completed_at` DATETIME(0) NULL,
  `origin_province` VARCHAR(255) NULL,
  `destination_province` VARCHAR(255) NULL,
  `seller_confirmed_pickup_at` DATETIME(0) NULL,
  `rider_confirmed_pickup_at` DATETIME(0) NULL,
  CONSTRAINT `shipments_shipments_courier_id_fkey` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`),
  CONSTRAINT `shipments_shipments_hub_transfer_rider_id_foreign` FOREIGN KEY (`hub_transfer_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_shipments_order_id_fkey` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  UNIQUE KEY `shipments_order_id_key` (`order_id`),
  CONSTRAINT `shipments_shipments_pickup_rider_id_foreign` FOREIGN KEY (`pickup_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_tracking_number_key` (`tracking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `shop_follows` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `buyer_id` BIGINT NOT NULL,
  `seller_id` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `shop_follows_shop_follows_buyer_id_fkey` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `shop_follows_buyer_id_seller_id_key` (`buyer_id`, `seller_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `shop_follows_shop_follows_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `unserviceable_areas` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `municipality` VARCHAR(255) NOT NULL,
  `note` LONGTEXT NULL,
  `added_by` BIGINT NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  CONSTRAINT `unserviceable_areas_unserviceable_areas_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `unserviceable_areas_municipality_unique` (`municipality`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `account_type` ENUM('buyer', 'rider', 'seller', 'admin', 'logistics') NOT NULL DEFAULT 'buyer',
  `auth_method` ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
  `google_id` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `given_names` VARCHAR(255) NOT NULL,
  `middle_name` VARCHAR(50) NULL,
  `sex` ENUM('male', 'female') NOT NULL,
  `birthday` DATE NOT NULL,
  `age` SMALLINT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `contact_no` VARCHAR(11) NOT NULL,
  `province` VARCHAR(255) NOT NULL,
  `municipality` VARCHAR(255) NOT NULL,
  `barangay` VARCHAR(255) NOT NULL,
  `house_no` VARCHAR(255) NULL,
  `street` VARCHAR(255) NULL,
  `password` VARCHAR(255) NULL,
  `id_file` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'suspended', 'interview') NOT NULL DEFAULT 'pending',
  `remember_token` VARCHAR(100) NULL,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `is_admin` BOOLEAN NOT NULL DEFAULT false,
  `is_logistics` BOOLEAN NOT NULL DEFAULT false,
  `profile_picture` VARCHAR(255) NULL,
  `username` VARCHAR(255) NULL,
  `id_type_id` BIGINT NULL,
  `selfie_file` VARCHAR(255) NULL,
  `business_name` VARCHAR(150) NULL,
  `business_permit_file` VARCHAR(255) NULL,
  `category_id` BIGINT NULL,
  `category_other` VARCHAR(255) NULL,
  `vehicle_type` VARCHAR(255) NULL,
  `vehicle_brand` VARCHAR(255) NULL,
  `vehicle_model` VARCHAR(255) NULL,
  `plate_number` VARCHAR(255) NULL,
  `or_file` VARCHAR(255) NULL,
  `cr_file` VARCHAR(255) NULL,
  `license_number` VARCHAR(255) NULL,
  `license_expiry` DATE NULL,
  `license_file` VARCHAR(255) NULL,
  `shipping_fee` DECIMAL(8,2) NULL,
  `notify_new_requests` BOOLEAN NOT NULL DEFAULT true,
  `notify_unassigned_shipments` BOOLEAN NOT NULL DEFAULT true,
  `preferred_scanner` VARCHAR(255) NOT NULL DEFAULT 'both',
  `id_type` LONGTEXT NULL,
  `id_photo_path` LONGTEXT NULL,
  `selfie_photo_path` LONGTEXT NULL,
  `last_active_at` DATETIME(6) NULL,
  `facebook_url` LONGTEXT NULL,
  `instagram_url` LONGTEXT NULL,
  `twitter_url` LONGTEXT NULL,
  `tiktok_url` LONGTEXT NULL,
  `notify_enabled` BOOLEAN NOT NULL DEFAULT true,
  `region` LONGTEXT NULL,
  `vehicle_ownership` VARCHAR(255) NULL,
  `company_logo` VARCHAR(255) NULL,
  `status_reason` LONGTEXT NULL,
  `is_online` BOOLEAN NOT NULL DEFAULT false,
  `logistics_center_id` BIGINT NULL,
  `or_cr_path` LONGTEXT NULL,
  `license_photo_path` LONGTEXT NULL,
  `suffix` ENUM('Jr.', 'Sr.', 'II', 'III', 'IV', 'V') NULL,
  `logistics_role` VARCHAR(255) NULL,
  `logistics_hub_id` BIGINT NULL,
  `resume_file` VARCHAR(2048) NULL,
  `interview_scheduled_at` DATETIME(0) NULL,
  `interview_location` VARCHAR(255) NULL,
  `theme` VARCHAR(10) NOT NULL DEFAULT 'system',
  `preferred_language` VARCHAR(10) NOT NULL DEFAULT 'en',
  CONSTRAINT `users_users_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  CONSTRAINT `users_users_logistics_center_id_fkey` FOREIGN KEY (`logistics_center_id`) REFERENCES `logistics_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_users_logistics_hub_id_foreign` FOREIGN KEY (`logistics_hub_id`) REFERENCES `logistics_hubs` (`id`) ON DELETE SET NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `vehicle_types` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `requires_documents` BOOLEAN NOT NULL DEFAULT true,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_types_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `vouchers` (
  `id` CHAR(36) NOT NULL,
  `seller_id` BIGINT NOT NULL,
  `code` VARCHAR(255) NOT NULL,
  `discount_amount` DECIMAL(10,2) NULL,
  `minimum_spend` DECIMAL(10,2) NOT NULL DEFAULT '0',
  `usage_limit` INT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `expires_at` DATE NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT true,
  `created_at` DATETIME(0) NULL,
  `updated_at` DATETIME(0) NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'amount',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_seller_id_code_unique` (`seller_id`, `code`),
  CONSTRAINT `vouchers_vouchers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `wishlist_items` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `buyer_id` BIGINT NOT NULL,
  `product_id` CHAR(36) NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT `wishlist_items_wishlist_items_buyer_id_fkey` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `wishlist_items_buyer_id_product_id_key` (`buyer_id`, `product_id`),
  PRIMARY KEY (`id`),
  CONSTRAINT `wishlist_items_wishlist_items_product_id_fkey` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Preserve source indexes that were not represented by table constraints.
ALTER TABLE `sessions` ADD INDEX `sessions_user_id_index` (`user_id`);
ALTER TABLE `sessions` ADD INDEX `sessions_last_activity_index` (`last_activity`);
ALTER TABLE `orders` ADD INDEX `orders_status_index` (`status`);
CREATE UNIQUE INDEX `policies_company_terms_unique` ON `policies`
  ((CASE WHEN `type` = 'logistics_company_terms' THEN `company_name` ELSE NULL END));
