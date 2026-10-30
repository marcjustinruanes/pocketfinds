-- PocketFinds: manual GCash verification schema (MySQL 8).
-- Source: database/migrations/2026_10_14_000004_add_gcash_purchases.php
-- Contract: app/Services/GcashSchema.php
-- This file contains schema changes only, with no credentials or business records.
--
-- NEW GCASH INSTALLATIONS ONLY. Select the intended existing PocketFinds database
-- in your SQL client first. No database is selected or created by this script.
-- Do not import if GCash tables or gcash_purchase_id columns already exist.
-- For an existing/partial installation, use the Laravel migration's validation
-- and forward-recovery logic instead; this plain SQL does not implement it.
-- Stop on the first error. MySQL DDL is not rolled back as one transaction;
-- preserve evidence and reconcile a partial installation before any retry.
--
-- Prerequisites from database/pocketfinds-mysql-schema.sql:
--   users.id and payment_methods.id: signed BIGINT primary keys.
--   products.id and vouchers.id: CHAR(36), utf8mb4_unicode_ci primary keys.
--   Existing orders/messages tables and all referenced tables use InnoDB.
--   If the actual parent types/collations differ, reconcile before import.
-- Existing orders/messages receive nullable columns; existing rows stay unlinked.
-- No payment method is enabled, no receipt uploaded, and no payment verified here.
-- The Laravel migrations ledger is not modified. Do not run a destructive rollback.

CREATE TABLE `gcash_purchases` (
    `id` CHAR(36) NOT NULL,
    `buyer_id` BIGINT NOT NULL,
    `seller_id` BIGINT NOT NULL,
    `checkout_id` CHAR(36) NOT NULL,
    `request_hash` VARCHAR(64) NOT NULL,
    `payment_method_id` BIGINT NOT NULL,
    `snapshot` JSON NOT NULL,
    `amount_due` DECIMAL(12,2) NOT NULL,
    `payment_state` VARCHAR(32) NOT NULL DEFAULT 'awaiting_payment',
    `reservation_state` VARCHAR(24) NOT NULL DEFAULT 'initializing',
    `activated_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `payment_reference` VARCHAR(64) NULL DEFAULT NULL,
    `verified_amount` DECIMAL(12,2) NULL DEFAULT NULL,
    `verified_by` BIGINT NULL DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `revision` INT UNSIGNED NOT NULL DEFAULT 0,
    `generation` INT UNSIGNED NOT NULL DEFAULT 1,
    `instructions` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gcash_purchases_payment_reference_unique` (`payment_reference`),
    UNIQUE KEY `gcash_purchases_seller_id_payment_reference_unique` (`seller_id`, `payment_reference`),
    UNIQUE KEY `gcash_purchases_buyer_id_checkout_id_seller_id_unique` (`buyer_id`, `checkout_id`, `seller_id`),
    KEY `gcash_purchases_expires_at_index` (`expires_at`),
    KEY `gcash_purchases_seller_id_payment_state_index` (`seller_id`, `payment_state`),
    CONSTRAINT `gcash_purchases_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_purchases_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_purchases_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_purchases_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gcash_stock_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` CHAR(36) NOT NULL,
    `product_id` CHAR(36) NULL DEFAULT NULL,
    `product_reference` CHAR(36) NOT NULL,
    `generation` INT UNSIGNED NOT NULL,
    `option_key` VARCHAR(64) NOT NULL,
    `variation_group` VARCHAR(255) NULL DEFAULT NULL,
    `variation_value` VARCHAR(255) NULL DEFAULT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `state` VARCHAR(24) NOT NULL,
    `released_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gcash_stock_purchase_option_unique` (`purchase_id`, `generation`, `product_reference`, `option_key`),
    KEY `gcash_stock_allocations_product_id_state_index` (`product_id`, `state`),
    CONSTRAINT `gcash_stock_allocations_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_stock_allocations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gcash_voucher_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` CHAR(36) NOT NULL,
    `voucher_id` CHAR(36) NULL DEFAULT NULL,
    `voucher_reference` CHAR(36) NOT NULL,
    `generation` INT UNSIGNED NOT NULL,
    `state` VARCHAR(16) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gcash_voucher_allocations_purchase_id_generation_unique` (`purchase_id`, `generation`),
    KEY `gcash_voucher_allocations_voucher_id_state_index` (`voucher_id`, `state`),
    CONSTRAINT `gcash_voucher_allocations_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_voucher_allocations_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gcash_proofs` (
    `id` CHAR(36) NOT NULL,
    `purchase_id` CHAR(36) NOT NULL,
    `submission_id` CHAR(36) NOT NULL,
    `submitted_by` BIGINT NOT NULL,
    `content_hash` VARCHAR(64) NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `mime` VARCHAR(80) NOT NULL,
    `size` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `gcash_proofs_purchase_id_submission_id_unique` (`purchase_id`, `submission_id`),
    CONSTRAINT `gcash_proofs_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_proofs_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gcash_purchase_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` CHAR(36) NOT NULL,
    `actor_id` BIGINT NULL DEFAULT NULL,
    `event` VARCHAR(40) NOT NULL,
    `before` JSON NOT NULL,
    `after` JSON NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    KEY `gcash_purchase_history_purchase_id_id_index` (`purchase_id`, `id`),
    CONSTRAINT `gcash_purchase_history_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `gcash_purchase_history_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `orders`
    ADD COLUMN `gcash_purchase_id` CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    ADD UNIQUE KEY `orders_gcash_purchase_id_unique` (`gcash_purchase_id`),
    ADD CONSTRAINT `orders_gcash_purchase_id_foreign` FOREIGN KEY (`gcash_purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `messages`
    ADD COLUMN `gcash_purchase_id` CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    ADD KEY `messages_gcash_purchase_id_index` (`gcash_purchase_id`),
    ADD CONSTRAINT `messages_gcash_purchase_id_foreign` FOREIGN KEY (`gcash_purchase_id`) REFERENCES `gcash_purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
