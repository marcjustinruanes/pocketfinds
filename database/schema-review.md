# PocketFinds database review

Source: live configured Supabase/PostgreSQL database, `public` schema. Captured 2026-10-04T05:42:12+00:00. 43 tables, 534 columns. No records exported and no database changes made. Supabase-managed schemas such as auth/storage are outside this application-table inventory.

## Before choosing what to remove

- MySQL Workbench is a client; the database will reside on a MySQL server.
- MySQL types below are proposed equivalents, not executable migration SQL. IDs and foreign keys must use matching signedness. PostgreSQL sequences/UUID defaults, casts, CHECK constraints, RLS policies, and timestamp defaults need separate conversion.
- Current migrations include duplicate table creation and PostgreSQL-specific SQL. Do not run the existing migrations unchanged against MySQL.
- `rider_profiles`: User.php describes this as a previously merged/dropped table, but it still exists live. Review for retirement after checking data and dependencies.
- `document_update_requests`: legacy table still exists alongside `account_update_requests`; review whether its records have been carried forward.
- `message_reactions` and `messages.reactions`: two reaction representations coexist; choose one after checking usage and retained data.
- `users.age` duplicates information derivable from birthday. Several identity/document path columns also overlap; verify which paths are used before removing them.
- `orders` contains legacy/new pairs including buyer_id/app_buyer_id, seller_id/app_seller_id, and shipping_fee/shipping_amount. Review their types, constraints, and application usage before consolidating.
- `products.image`, `products.images`, and `product_images` coexist. Determine the intended primary/gallery storage before removing any.
- `cache`/`cache_locks` and `sessions` are Laravel infrastructure. Removal depends on cache/session driver configuration. `migrations` tracks migration history.
- The live public schema has no `order_items` table despite `reviews.order_item_id` and an OrderItem model. Resolve this mismatch when designing the target schema.
- The following report lists structure only. It does not establish that a table is empty or safe to delete. Back up and validate the MySQL import before removing Supabase.

## Table index

| Table | Columns | Decision |
| --- | ---: | --- |
| [account_update_requests](#account-update-requests) | 10 | Keep / Change / Remove |
| [announcements](#announcements) | 10 | Keep / Change / Remove |
| [blocked_users](#blocked-users) | 4 | Keep / Change / Remove |
| [buyer_addresses](#buyer-addresses) | 13 | Keep / Change / Remove |
| [buyer_payment_accounts](#buyer-payment-accounts) | 10 | Keep / Change / Remove |
| [cache](#cache) | 3 | Keep / Change / Remove |
| [cache_locks](#cache-locks) | 3 | Keep / Change / Remove |
| [cart_items](#cart-items) | 13 | Keep / Change / Remove |
| [categories](#categories) | 2 | Keep / Change / Remove |
| [category_seller](#category-seller) | 5 | Keep / Change / Remove |
| [coin_transactions](#coin-transactions) | 7 | Keep / Change / Remove |
| [commissions](#commissions) | 8 | Keep / Change / Remove |
| [company_vehicles](#company-vehicles) | 19 | Keep / Change / Remove |
| [complaints](#complaints) | 21 | Keep / Change / Remove |
| [delivery_assignments](#delivery-assignments) | 10 | Keep / Change / Remove |
| [document_update_requests](#document-update-requests) | 11 | Keep / Change / Remove |
| [id_types](#id-types) | 2 | Keep / Change / Remove |
| [logistics_centers](#logistics-centers) | 12 | Keep / Change / Remove |
| [logistics_hubs](#logistics-hubs) | 8 | Keep / Change / Remove |
| [message_reactions](#message-reactions) | 5 | Keep / Change / Remove |
| [messages](#messages) | 19 | Keep / Change / Remove |
| [migrations](#migrations) | 3 | Keep / Change / Remove |
| [notifications](#notifications) | 8 | Keep / Change / Remove |
| [order_status_history](#order-status-history) | 6 | Keep / Change / Remove |
| [orders](#orders) | 26 | Keep / Change / Remove |
| [payment_methods](#payment-methods) | 9 | Keep / Change / Remove |
| [payment_verifications](#payment-verifications) | 11 | Keep / Change / Remove |
| [platform_vouchers](#platform-vouchers) | 12 | Keep / Change / Remove |
| [policies](#policies) | 14 | Keep / Change / Remove |
| [product_images](#product-images) | 5 | Keep / Change / Remove |
| [products](#products) | 26 | Keep / Change / Remove |
| [reviews](#reviews) | 10 | Keep / Change / Remove |
| [rider_profiles](#rider-profiles) | 34 | Keep / Change / Remove |
| [sessions](#sessions) | 6 | Keep / Change / Remove |
| [settings](#settings) | 17 | Keep / Change / Remove |
| [shipment_hub_legs](#shipment-hub-legs) | 17 | Keep / Change / Remove |
| [shipments](#shipments) | 32 | Keep / Change / Remove |
| [shop_follows](#shop-follows) | 4 | Keep / Change / Remove |
| [unserviceable_areas](#unserviceable-areas) | 6 | Keep / Change / Remove |
| [users](#users) | 71 | Keep / Change / Remove |
| [vehicle_types](#vehicle-types) | 6 | Keep / Change / Remove |
| [vouchers](#vouchers) | 12 | Keep / Change / Remove |
| [wishlist_items](#wishlist-items) | 4 | Keep / Change / Remove |

## account_update_requests

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('document_update_requests_id_seq'::regclass) |
| user_id | bigint | BIGINT | No | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| reviewed_by | bigint | BIGINT | Yes | — |
| reviewed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| note | text | LONGTEXT (review size) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| requested_changes | json | JSON | Yes | — |
| requested_documents | json | JSON | Yes | — |

Constraints (current PostgreSQL definitions):

- `document_update_requests_pkey`: `PRIMARY KEY (id)`
- `document_update_requests_reviewed_by_foreign`: `FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL`
- `document_update_requests_status_check`: `CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))`
- `document_update_requests_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## announcements

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| title | character varying(255) | VARCHAR(255) | No | — |
| body | text | LONGTEXT (review size) | No | — |
| created_by | bigint | BIGINT | Yes | — |
| is_active | boolean | BOOLEAN (TINYINT(1)) | Yes | true |
| published_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| expires_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| audience | character varying(255) | VARCHAR(255) | No | 'all'::character varying |

Constraints (current PostgreSQL definitions):

- `announcements_created_by_fkey`: `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL`
- `announcements_pkey`: `PRIMARY KEY (id)`

## blocked_users

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| blocker_id | bigint | BIGINT | No | — |
| blocked_id | bigint | BIGINT | No | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `blocked_users_blocked_id_fkey`: `FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE`
- `blocked_users_blocker_id_blocked_id_key`: `UNIQUE (blocker_id, blocked_id)`
- `blocked_users_blocker_id_fkey`: `FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE`
- `blocked_users_pkey`: `PRIMARY KEY (id)`

## buyer_addresses

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('buyer_addresses_id_seq'::regclass) |
| buyer_id | bigint | BIGINT | No | — |
| label | character varying(255) | VARCHAR(255) | Yes | — |
| recipient_name | character varying(255) | VARCHAR(255) | No | — |
| contact_no | character varying(255) | VARCHAR(255) | Yes | — |
| province | character varying(255) | VARCHAR(255) | No | — |
| municipality | character varying(255) | VARCHAR(255) | No | — |
| barangay | character varying(255) | VARCHAR(255) | No | — |
| house_no | character varying(255) | VARCHAR(255) | Yes | — |
| street | character varying(255) | VARCHAR(255) | Yes | — |
| is_default | boolean | BOOLEAN (TINYINT(1)) | No | false |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `buyer_addresses_buyer_id_foreign`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `buyer_addresses_pkey`: `PRIMARY KEY (id)`

## buyer_payment_accounts

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | — |
| buyer_id | bigint | BIGINT | No | — |
| type | character varying(255) | VARCHAR(255) | No | — |
| account_name | character varying(255) | VARCHAR(255) | No | — |
| account_number | character varying(255) | VARCHAR(255) | No | — |
| bank_name | character varying(255) | VARCHAR(255) | Yes | — |
| verified | boolean | BOOLEAN (TINYINT(1)) | No | false |
| verified_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `buyer_payment_accounts_buyer_id_foreign`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `buyer_payment_accounts_pkey`: `PRIMARY KEY (id)`

## cache

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| key | character varying(255) | VARCHAR(255) | No | — |
| value | text | LONGTEXT (review size) | No | — |
| expiration | integer | INT | No | — |

Constraints (current PostgreSQL definitions):

- `cache_pkey`: `PRIMARY KEY (key)`

## cache_locks

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| key | character varying(255) | VARCHAR(255) | No | — |
| owner | character varying(255) | VARCHAR(255) | No | — |
| expiration | integer | INT | No | — |

Constraints (current PostgreSQL definitions):

- `cache_locks_pkey`: `PRIMARY KEY (key)`

## cart_items

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| buyer_id | bigint | BIGINT | No | — |
| product_id | uuid | CHAR(36) | No | — |
| seller_slug | character varying(255) | VARCHAR(255) | No | — |
| seller | character varying(255) | VARCHAR(255) | No | — |
| name | character varying(255) | VARCHAR(255) | No | — |
| img | character varying(255) | VARCHAR(255) | Yes | — |
| price | numeric(10,2) | DECIMAL(10,2) | No | — |
| qty | integer | INT | No | — |
| variation_value | character varying(255) | VARCHAR(255) | No | ''::character varying |
| variation_group | character varying(255) | VARCHAR(255) | No | ''::character varying |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `cart_items_buyer_id_foreign`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `cart_items_buyer_product_variation_unique`: `UNIQUE (buyer_id, product_id, variation_value, variation_group)`
- `cart_items_pkey`: `PRIMARY KEY (id)`

## categories

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | — |
| name | text | LONGTEXT (review size) | No | — |

Constraints (current PostgreSQL definitions):

- `categories_pkey`: `PRIMARY KEY (id)`

## category_seller

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('category_seller_id_seq'::regclass) |
| user_id | bigint | BIGINT | No | — |
| category_id | bigint | BIGINT | No | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `category_seller_category_id_foreign`: `FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE`
- `category_seller_pkey`: `PRIMARY KEY (id)`
- `category_seller_user_id_category_id_unique`: `UNIQUE (user_id, category_id)`
- `category_seller_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## coin_transactions

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| user_id | bigint | BIGINT | No | — |
| delta | bigint | BIGINT | No | — |
| reason | text | LONGTEXT (review size) | No | — |
| order_id | uuid | CHAR(36) | Yes | — |
| review_id | uuid | CHAR(36) | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `coin_transactions_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL`
- `coin_transactions_pkey`: `PRIMARY KEY (id)`
- `coin_transactions_review_id_fkey`: `FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE SET NULL`
- `coin_transactions_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## commissions

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_id | uuid | CHAR(36) | No | — |
| seller_id | bigint | BIGINT | No | — |
| order_amount | numeric(12,2) | DECIMAL(12,2) | No | — |
| commission_rate | numeric(5,2) | DECIMAL(5,2) | No | 10.00 |
| commission_amount | numeric(12,2) | DECIMAL(12,2) | No | — |
| seller_earnings | numeric(12,2) | DECIMAL(12,2) | No | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |

Constraints (current PostgreSQL definitions):

- `commissions_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id)`
- `commissions_pkey`: `PRIMARY KEY (id)`
- `commissions_seller_id_fkey`: `FOREIGN KEY (seller_id) REFERENCES users(id)`

## company_vehicles

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('company_vehicles_id_seq'::regclass) |
| company_name | character varying(255) | VARCHAR(255) | No | — |
| logistics_hub_id | bigint | BIGINT | No | — |
| vehicle_type | character varying(255) | VARCHAR(255) | No | — |
| brand | character varying(255) | VARCHAR(255) | No | — |
| model | character varying(255) | VARCHAR(255) | No | — |
| plate_number | character varying(255) | VARCHAR(255) | No | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| status_reason | text | LONGTEXT (review size) | Yes | — |
| is_available | boolean | BOOLEAN (TINYINT(1)) | No | true |
| submitted_by | bigint | BIGINT | No | — |
| reviewed_by | bigint | BIGINT | Yes | — |
| reviewed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| platform_status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| platform_status_reason | text | LONGTEXT (review size) | Yes | — |
| platform_reviewed_by | bigint | BIGINT | Yes | — |
| platform_reviewed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `company_vehicles_logistics_hub_id_foreign`: `FOREIGN KEY (logistics_hub_id) REFERENCES logistics_hubs(id) ON DELETE CASCADE`
- `company_vehicles_pkey`: `PRIMARY KEY (id)`
- `company_vehicles_plate_number_unique`: `UNIQUE (plate_number)`
- `company_vehicles_platform_reviewed_by_foreign`: `FOREIGN KEY (platform_reviewed_by) REFERENCES users(id) ON DELETE SET NULL`
- `company_vehicles_reviewed_by_foreign`: `FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL`
- `company_vehicles_submitted_by_foreign`: `FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE`

## complaints

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_id | uuid | CHAR(36) | Yes | — |
| complainant_id | bigint | BIGINT | No | — |
| respondent_id | bigint | BIGINT | Yes | — |
| complaint_type | character varying(100) | VARCHAR(100) | Yes | — |
| subject | character varying(255) | VARCHAR(255) | Yes | — |
| description | text | LONGTEXT (review size) | Yes | — |
| status | character varying(30) | VARCHAR(30) | Yes | 'open'::character varying |
| resolution | text | LONGTEXT (review size) | Yes | — |
| handled_by | bigint | BIGINT | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| resolved_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| message_id | bigint | BIGINT | Yes | — |
| shop_name | character varying(255) | VARCHAR(255) | Yes | — |
| message_body | text | LONGTEXT (review size) | Yes | — |
| message_type | character varying(255) | VARCHAR(255) | Yes | — |
| evidence_path | character varying(255) | VARCHAR(255) | Yes | — |
| evidence_name | character varying(255) | VARCHAR(255) | Yes | — |
| evidence_mime | character varying(255) | VARCHAR(255) | Yes | — |
| evidence_type | character varying(255) | VARCHAR(255) | Yes | — |
| evidence_size | bigint | BIGINT | Yes | — |

Constraints (current PostgreSQL definitions):

- `complaints_complainant_id_fkey`: `FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE`
- `complaints_handled_by_fkey`: `FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL`
- `complaints_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id)`
- `complaints_pkey`: `PRIMARY KEY (id)`
- `complaints_respondent_id_fkey`: `FOREIGN KEY (respondent_id) REFERENCES users(id) ON DELETE SET NULL`

## delivery_assignments

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| shipment_id | uuid | CHAR(36) | No | — |
| courier_id | bigint | BIGINT | Yes | — |
| status | character varying(30) | VARCHAR(30) | No | 'requested'::character varying |
| requested_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| accepted_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| picked_up_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| delivered_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| scheduled_pickup_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| leg | character varying(255) | VARCHAR(255) | No | 'delivery'::character varying |

Constraints (current PostgreSQL definitions):

- `delivery_assignments_courier_id_fkey`: `FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL`
- `delivery_assignments_pkey`: `PRIMARY KEY (id)`
- `delivery_assignments_shipment_id_fkey`: `FOREIGN KEY (shipment_id) REFERENCES shipments(id)`

## document_update_requests

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('document_update_requests_id_seq1'::regclass) |
| user_id | bigint | BIGINT | No | — |
| id_type_id | character varying(255) | VARCHAR(255) | Yes | — |
| id_file | character varying(255) | VARCHAR(255) | Yes | — |
| business_permit_file | character varying(255) | VARCHAR(255) | Yes | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| reviewed_by | bigint | BIGINT | Yes | — |
| reviewed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| note | text | LONGTEXT (review size) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `document_update_requests_pkey1`: `PRIMARY KEY (id)`
- `document_update_requests_reviewed_by_foreign`: `FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL`
- `document_update_requests_status_check1`: `CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))`
- `document_update_requests_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## id_types

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | — |
| name | text | LONGTEXT (review size) | No | — |

Constraints (current PostgreSQL definitions):

- `id_types_pkey`: `PRIMARY KEY (id)`

## logistics_centers

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('logistics_centers_id_seq'::regclass) |
| name | character varying(255) | VARCHAR(255) | Yes | — |
| address | text | LONGTEXT (review size) | Yes | — |
| contact_no | character varying(255) | VARCHAR(255) | Yes | — |
| hours_note | character varying(255) | VARCHAR(255) | Yes | — |
| updated_by | bigint | BIGINT | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| location | text | LONGTEXT (review size) | Yes | — |
| accepting_applications | boolean | BOOLEAN (TINYINT(1)) | No | true |
| available_slots | integer | INT | No | 0 |
| service_area | text | LONGTEXT (review size) | Yes | — |

Constraints (current PostgreSQL definitions):

- `logistics_centers_pkey`: `PRIMARY KEY (id)`
- `logistics_centers_updated_by_foreign`: `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL`

## logistics_hubs

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('logistics_hubs_id_seq'::regclass) |
| company_name | character varying(255) | VARCHAR(255) | No | — |
| province | character varying(255) | VARCHAR(255) | No | — |
| municipality | character varying(255) | VARCHAR(255) | No | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| is_regional_hub | boolean | BOOLEAN (TINYINT(1)) | No | false |
| is_hiring | boolean | BOOLEAN (TINYINT(1)) | No | true |

Constraints (current PostgreSQL definitions):

- `logistics_hubs_company_name_province_municipality_unique`: `UNIQUE (company_name, province, municipality)`
- `logistics_hubs_pkey`: `PRIMARY KEY (id)`

## message_reactions

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| message_id | bigint | BIGINT | No | — |
| user_id | bigint | BIGINT | No | — |
| emoji | text | LONGTEXT (review size) | No | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `message_reactions_message_id_fkey`: `FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE`
- `message_reactions_message_id_user_id_key`: `UNIQUE (message_id, user_id)`
- `message_reactions_pkey`: `PRIMARY KEY (id)`
- `message_reactions_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## messages

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('messages_id_seq'::regclass) |
| sender_id | bigint | BIGINT | No | — |
| receiver_id | bigint | BIGINT | No | — |
| body | text | LONGTEXT (review size) | Yes | — |
| read | boolean | BOOLEAN (TINYINT(1)) | No | false |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| product_id | uuid | CHAR(36) | Yes | — |
| attachment_path | character varying(255) | VARCHAR(255) | Yes | — |
| attachment_name | character varying(255) | VARCHAR(255) | Yes | — |
| attachment_type | character varying(255) | VARCHAR(255) | Yes | — |
| attachment_mime | character varying(255) | VARCHAR(255) | Yes | — |
| attachment_size | bigint | BIGINT | Yes | — |
| variation_label | character varying(255) | VARCHAR(255) | Yes | — |
| variation_price | numeric(10,2) | DECIMAL(10,2) | Yes | — |
| variation_image | character varying(255) | VARCHAR(255) | Yes | — |
| reply_to_id | bigint | BIGINT | Yes | — |
| order_id | uuid | CHAR(36) | Yes | — |
| reactions | json | JSON | Yes | — |

Constraints (current PostgreSQL definitions):

- `messages_order_id_foreign`: `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL`
- `messages_pkey`: `PRIMARY KEY (id)`
- `messages_product_id_foreign`: `FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL`
- `messages_receiver_id_foreign`: `FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE`
- `messages_reply_to_id_fkey`: `FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL`
- `messages_sender_id_foreign`: `FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE`

## migrations

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | integer | INT | No | nextval('migrations_id_seq'::regclass) |
| migration | character varying(255) | VARCHAR(255) | No | — |
| batch | integer | INT | No | — |

Constraints (current PostgreSQL definitions):

- `migrations_pkey`: `PRIMARY KEY (id)`

## notifications

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| user_id | bigint | BIGINT | No | — |
| title | character varying(255) | VARCHAR(255) | No | — |
| message | text | LONGTEXT (review size) | No | — |
| notification_type | character varying(50) | VARCHAR(50) | Yes | — |
| reference_id | uuid | CHAR(36) | Yes | — |
| is_read | boolean | BOOLEAN (TINYINT(1)) | Yes | false |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |

Constraints (current PostgreSQL definitions):

- `notifications_pkey`: `PRIMARY KEY (id)`
- `notifications_user_id_fkey`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`

## order_status_history

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_id | uuid | CHAR(36) | No | — |
| status | character varying(30) | VARCHAR(30) | No | — |
| changed_by | bigint | BIGINT | Yes | — |
| notes | text | LONGTEXT (review size) | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |

Constraints (current PostgreSQL definitions):

- `order_status_history_changed_by_fkey`: `FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL`
- `order_status_history_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE`
- `order_status_history_pkey`: `PRIMARY KEY (id)`

## orders

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| buyer_id | bigint | BIGINT | No | — |
| subtotal | numeric(12,2) | DECIMAL(12,2) | No | 0 |
| discount_amount | numeric(12,2) | DECIMAL(12,2) | No | 0 |
| shipping_fee | numeric(12,2) | DECIMAL(12,2) | No | 0 |
| payment_method | character varying(50) | VARCHAR(50) | No | — |
| updated_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| order_number | character varying(255) | VARCHAR(255) | Yes | — |
| seller_id | bigint | BIGINT | Yes | — |
| status | character varying(255) | VARCHAR(255) | No | 'to_ship'::character varying |
| items | json | JSON | Yes | — |
| shipping_amount | numeric(12,2) | DECIMAL(12,2) | No | '0'::numeric |
| total | numeric(12,2) | DECIMAL(12,2) | No | '0'::numeric |
| shipping_address | json | JSON | Yes | — |
| payment_method_id | bigint | BIGINT | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| cancellation_reason | character varying(255) | VARCHAR(255) | Yes | — |
| cancellation_note | text | LONGTEXT (review size) | Yes | — |
| buyer_note | text | LONGTEXT (review size) | Yes | — |
| voucher_code | character varying(255) | VARCHAR(255) | Yes | — |
| app_buyer_id | bigint | BIGINT | Yes | — |
| app_seller_id | bigint | BIGINT | Yes | — |
| coins_used | bigint | BIGINT | No | 0 |
| coin_discount_amount | numeric | DECIMAL (choose precision/scale) | No | 0 |
| platform_voucher_id | uuid | CHAR(36) | Yes | — |
| platform_discount_amount | numeric | DECIMAL (choose precision/scale) | No | 0 |

Constraints (current PostgreSQL definitions):

- `orders_app_buyer_id_fkey`: `FOREIGN KEY (app_buyer_id) REFERENCES users(id)`
- `orders_app_seller_id_fkey`: `FOREIGN KEY (app_seller_id) REFERENCES users(id)`
- `orders_buyer_id_foreign`: `FOREIGN KEY (buyer_id) REFERENCES users(id)`
- `orders_pkey`: `PRIMARY KEY (id)`
- `orders_platform_voucher_id_fkey`: `FOREIGN KEY (platform_voucher_id) REFERENCES platform_vouchers(id)`

## payment_methods

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('payment_methods_id_seq'::regclass) |
| name | character varying(255) | VARCHAR(255) | No | — |
| description | character varying(255) | VARCHAR(255) | Yes | — |
| is_active | boolean | BOOLEAN (TINYINT(1)) | No | true |
| sort_order | integer | INT | No | 0 |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| type | character varying(255) | VARCHAR(255) | No | 'other'::character varying |
| requires_verification | boolean | BOOLEAN (TINYINT(1)) | No | false |

Constraints (current PostgreSQL definitions):

- `payment_methods_pkey`: `PRIMARY KEY (id)`

## payment_verifications

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_id | uuid | CHAR(36) | No | — |
| buyer_id | bigint | BIGINT | No | — |
| reference_number | text | LONGTEXT (review size) | No | — |
| proof_path | text | LONGTEXT (review size) | No | — |
| status | text | LONGTEXT (review size) | No | 'submitted'::text |
| rejection_reason | text | LONGTEXT (review size) | Yes | — |
| submitted_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |
| reviewed_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |
| updated_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `payment_verifications_buyer_id_fkey`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `payment_verifications_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE`
- `payment_verifications_order_id_key`: `UNIQUE (order_id)`
- `payment_verifications_pkey`: `PRIMARY KEY (id)`
- `payment_verifications_status_check`: `CHECK ((status = ANY (ARRAY['pending'::text, 'submitted'::text, 'under_review'::text, 'confirmed'::text, 'rejected'::text])))`

## platform_vouchers

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| title | text | LONGTEXT (review size) | No | — |
| description | text | LONGTEXT (review size) | Yes | — |
| type | text | LONGTEXT (review size) | No | — |
| discount_amount | numeric | DECIMAL (choose precision/scale) | Yes | — |
| discount_percent | numeric | DECIMAL (choose precision/scale) | Yes | — |
| minimum_spend | numeric | DECIMAL (choose precision/scale) | Yes | — |
| expires_at | date | DATE | Yes | — |
| usage_limit | integer | INT | Yes | — |
| used_count | integer | INT | No | 0 |
| is_active | boolean | BOOLEAN (TINYINT(1)) | No | true |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `platform_vouchers_pkey`: `PRIMARY KEY (id)`
- `platform_vouchers_type_check`: `CHECK ((type = ANY (ARRAY['free_shipping'::text, 'discount'::text, 'coins_cashback'::text])))`

## policies

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('policies_id_seq'::regclass) |
| type | character varying(255) | VARCHAR(255) | No | — |
| title | character varying(255) | VARCHAR(255) | No | — |
| content | text | LONGTEXT (review size) | Yes | — |
| updated_by | bigint | BIGINT | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| history | json | JSON | Yes | — |
| account_type | character varying(255) | VARCHAR(255) | Yes | — |
| company_name | character varying(255) | VARCHAR(255) | Yes | — |
| pending_content | text | LONGTEXT (review size) | Yes | — |
| pending_submitted_by | bigint | BIGINT | Yes | — |
| pending_submitted_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| rejection_reason | character varying(255) | VARCHAR(255) | Yes | — |

Constraints (current PostgreSQL definitions):

- `policies_pending_submitted_by_foreign`: `FOREIGN KEY (pending_submitted_by) REFERENCES users(id) ON DELETE SET NULL`
- `policies_pkey`: `PRIMARY KEY (id)`
- `policies_type_account_type_unique`: `UNIQUE (type, account_type)`
- `policies_updated_by_foreign`: `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL`

## product_images

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | — |
| product_id | uuid | CHAR(36) | No | — |
| image_url | character varying(255) | VARCHAR(255) | No | — |
| is_primary | boolean | BOOLEAN (TINYINT(1)) | No | false |
| sort_order | integer | INT | No | 0 |

Constraints (current PostgreSQL definitions):

- `product_images_pkey`: `PRIMARY KEY (id)`
- `product_images_product_id_foreign`: `FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE`

## products

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| seller_id | bigint | BIGINT | No | — |
| category_id | bigint | BIGINT | No | — |
| name | character varying(255) | VARCHAR(255) | No | — |
| description | text | LONGTEXT (review size) | Yes | — |
| price | numeric(12,2) | DECIMAL(12,2) | No | — |
| sku | character varying(100) | VARCHAR(100) | Yes | — |
| status | character varying(20) | VARCHAR(20) | No | 'active'::character varying |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| updated_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| rejection_note | text | LONGTEXT (review size) | Yes | — |
| image | character varying(255) | VARCHAR(255) | Yes | — |
| variations | jsonb | JSON | Yes | — |
| details | jsonb | JSON | Yes | — |
| stock | integer | INT | No | 0 |
| weight_grams | integer | INT | No | 0 |
| length_cm | numeric(6,1) | DECIMAL(6,1) | Yes | — |
| width_cm | numeric(6,1) | DECIMAL(6,1) | Yes | — |
| height_cm | numeric(6,1) | DECIMAL(6,1) | Yes | — |
| condition | character varying(255) | VARCHAR(255) | No | 'new'::character varying |
| images | jsonb | JSON | Yes | — |
| video | character varying(255) | VARCHAR(255) | Yes | — |
| weight_grams_max | integer | INT | Yes | — |
| discount_price | numeric(10,2) | DECIMAL(10,2) | Yes | — |
| restock_date | date | DATE | Yes | — |
| image_signature | vector(48) | JSON (requires vector/search redesign) | Yes | — |

Constraints (current PostgreSQL definitions):

- `products_pkey`: `PRIMARY KEY (id)`
- `products_price_check`: `CHECK ((price >= (0)::numeric))`
- `products_seller_id_fkey`: `FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE`
- `products_sku_key`: `UNIQUE (sku)`
- `products_status_check`: `CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'archived'::character varying, 'pending'::character varying, 'rejected'::character varying])::text[])))`

## reviews

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_item_id | uuid | CHAR(36) | Yes | — |
| buyer_id | bigint | BIGINT | No | — |
| seller_id | bigint | BIGINT | Yes | — |
| product_id | uuid | CHAR(36) | No | — |
| rating | integer | INT | No | — |
| comment | text | LONGTEXT (review size) | Yes | — |
| seller_reply | text | LONGTEXT (review size) | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| order_id | uuid | CHAR(36) | Yes | — |

Constraints (current PostgreSQL definitions):

- `reviews_buyer_id_fkey`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `reviews_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL`
- `reviews_pkey`: `PRIMARY KEY (id)`
- `reviews_product_id_fkey`: `FOREIGN KEY (product_id) REFERENCES products(id)`
- `reviews_rating_check`: `CHECK (((rating >= 1) AND (rating <= 5)))`
- `reviews_seller_id_fkey`: `FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL`

## rider_profiles

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('rider_profiles_id_seq'::regclass) |
| user_id | bigint | BIGINT | No | — |
| auth_method | character varying(255) | VARCHAR(255) | No | 'manual'::character varying |
| google_id | character varying(255) | VARCHAR(255) | Yes | — |
| username | character varying(255) | VARCHAR(255) | Yes | — |
| last_name | character varying(255) | VARCHAR(255) | No | — |
| given_names | character varying(255) | VARCHAR(255) | No | — |
| middle_name | character varying(255) | VARCHAR(255) | Yes | — |
| sex | character varying(255) | VARCHAR(255) | No | — |
| birthday | date | DATE | No | — |
| age | smallint | SMALLINT | No | — |
| email | character varying(255) | VARCHAR(255) | No | — |
| contact_no | character varying(11) | VARCHAR(11) | No | — |
| province | character varying(255) | VARCHAR(255) | No | — |
| municipality | character varying(255) | VARCHAR(255) | No | — |
| barangay | character varying(255) | VARCHAR(255) | No | — |
| house_no | character varying(255) | VARCHAR(255) | Yes | — |
| street | character varying(255) | VARCHAR(255) | Yes | — |
| password | character varying(255) | VARCHAR(255) | Yes | — |
| id_file | character varying(255) | VARCHAR(255) | Yes | — |
| id_type_id | bigint | BIGINT | Yes | — |
| selfie_file | character varying(255) | VARCHAR(255) | Yes | — |
| vehicle_type | character varying(255) | VARCHAR(255) | No | — |
| vehicle_brand | character varying(255) | VARCHAR(255) | Yes | — |
| vehicle_model | character varying(255) | VARCHAR(255) | Yes | — |
| plate_number | character varying(255) | VARCHAR(255) | Yes | — |
| or_file | character varying(255) | VARCHAR(255) | Yes | — |
| cr_file | character varying(255) | VARCHAR(255) | Yes | — |
| license_number | character varying(255) | VARCHAR(255) | Yes | — |
| license_expiry | date | DATE | Yes | — |
| license_file | character varying(255) | VARCHAR(255) | Yes | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `rider_profiles_auth_method_check`: `CHECK (((auth_method)::text = ANY ((ARRAY['manual'::character varying, 'google'::character varying])::text[])))`
- `rider_profiles_email_unique`: `UNIQUE (email)`
- `rider_profiles_pkey`: `PRIMARY KEY (id)`
- `rider_profiles_sex_check`: `CHECK (((sex)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying])::text[])))`
- `rider_profiles_status_check`: `CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))`
- `rider_profiles_user_id_foreign`: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `rider_profiles_vehicle_type_check`: `CHECK (((vehicle_type)::text = ANY ((ARRAY['motorcycle'::character varying, 'bicycle'::character varying, 'car_van'::character varying])::text[])))`

## sessions

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | character varying(255) | VARCHAR(255) | No | — |
| user_id | bigint | BIGINT | Yes | — |
| ip_address | character varying(45) | VARCHAR(45) | Yes | — |
| user_agent | text | LONGTEXT (review size) | Yes | — |
| payload | text | LONGTEXT (review size) | No | — |
| last_activity | integer | INT | No | — |

Constraints (current PostgreSQL definitions):

- `sessions_pkey`: `PRIMARY KEY (id)`

## settings

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('settings_id_seq'::regclass) |
| platform_name | character varying(255) | VARCHAR(255) | No | 'PocketFinds'::character varying |
| support_email | character varying(255) | VARCHAR(255) | No | 'anchetanicole1020@gmail.com'::character varying |
| commission_rate | numeric(5,2) | DECIMAL(5,2) | No | '10'::numeric |
| google_signin_enabled | boolean | BOOLEAN (TINYINT(1)) | No | true |
| new_registrations_enabled | boolean | BOOLEAN (TINYINT(1)) | No | true |
| maintenance_mode | boolean | BOOLEAN (TINYINT(1)) | No | false |
| email_notifications_enabled | boolean | BOOLEAN (TINYINT(1)) | No | true |
| updated_by | bigint | BIGINT | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| hero_image | character varying(255) | VARCHAR(255) | Yes | — |
| hero_label | character varying(100) | VARCHAR(100) | Yes | 'Local Marketplace · Philippines'::character varying |
| hero_tagline | character varying(200) | VARCHAR(200) | Yes | 'Find It. Love It. Pocket It.'::character varying |
| hero_subtitle | character varying(500) | VARCHAR(500) | Yes | 'Browse products from verified local sellers — pet supplies, electronics, fashion, home essentials, and more.'::character varying |
| hero_cta_text | character varying(80) | VARCHAR(80) | Yes | 'Browse Products'::character varying |
| hero_overlay | character varying(30) | VARCHAR(30) | Yes | 'dark'::character varying |

Constraints (current PostgreSQL definitions):

- `settings_pkey`: `PRIMARY KEY (id)`
- `settings_updated_by_foreign`: `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL`

## shipment_hub_legs

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| shipment_id | uuid | CHAR(36) | No | — |
| sequence | integer | INT | No | — |
| leg_type | character varying(255) | VARCHAR(255) | No | — |
| from_hub | character varying(255) | VARCHAR(255) | No | — |
| to_hub | character varying(255) | VARCHAR(255) | No | — |
| rider_id | bigint | BIGINT | Yes | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| started_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| completed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| requested_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| requested_by | bigint | BIGINT | Yes | — |
| approved_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| approved_by | bigint | BIGINT | Yes | — |
| rider_confirmed_pickup_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `shipment_hub_legs_approved_by_foreign`: `FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL`
- `shipment_hub_legs_pkey`: `PRIMARY KEY (id)`
- `shipment_hub_legs_requested_by_foreign`: `FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL`
- `shipment_hub_legs_rider_id_foreign`: `FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE SET NULL`
- `shipment_hub_legs_shipment_id_foreign`: `FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE`
- `shipment_hub_legs_shipment_id_sequence_unique`: `UNIQUE (shipment_id, sequence)`

## shipments

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| order_id | uuid | CHAR(36) | No | — |
| tracking_number | character varying(100) | VARCHAR(100) | Yes | — |
| courier_id | bigint | BIGINT | Yes | — |
| shipping_status | character varying(30) | VARCHAR(30) | Yes | 'pending'::character varying |
| picked_up_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| in_transit_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| out_for_delivery_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| delivered_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | now() |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| scheduled_pickup_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| delivery_fee | numeric | DECIMAL (choose precision/scale) | Yes | — |
| pickup_rider_id | bigint | BIGINT | Yes | — |
| sorted_area | character varying(255) | VARCHAR(255) | Yes | — |
| delivery_failed_reason | text | LONGTEXT (review size) | Yes | — |
| at_sorting_center_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| sorted_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| assigned_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| delivery_failed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| returned_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| pickup_approved_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| logistics_company | character varying(255) | VARCHAR(255) | Yes | — |
| origin_hub | character varying(255) | VARCHAR(255) | Yes | — |
| destination_hub | character varying(255) | VARCHAR(255) | Yes | — |
| hub_transfer_rider_id | bigint | BIGINT | Yes | — |
| hub_transfer_started_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| hub_transfer_completed_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| origin_province | character varying(255) | VARCHAR(255) | Yes | — |
| destination_province | character varying(255) | VARCHAR(255) | Yes | — |
| seller_confirmed_pickup_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| rider_confirmed_pickup_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `shipments_courier_id_fkey`: `FOREIGN KEY (courier_id) REFERENCES users(id)`
- `shipments_hub_transfer_rider_id_foreign`: `FOREIGN KEY (hub_transfer_rider_id) REFERENCES users(id) ON DELETE SET NULL`
- `shipments_order_id_fkey`: `FOREIGN KEY (order_id) REFERENCES orders(id)`
- `shipments_order_id_key`: `UNIQUE (order_id)`
- `shipments_pickup_rider_id_foreign`: `FOREIGN KEY (pickup_rider_id) REFERENCES users(id) ON DELETE SET NULL`
- `shipments_pkey`: `PRIMARY KEY (id)`
- `shipments_tracking_number_key`: `UNIQUE (tracking_number)`

## shop_follows

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| buyer_id | bigint | BIGINT | No | — |
| seller_id | bigint | BIGINT | No | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `shop_follows_buyer_id_fkey`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `shop_follows_buyer_id_seller_id_key`: `UNIQUE (buyer_id, seller_id)`
- `shop_follows_pkey`: `PRIMARY KEY (id)`
- `shop_follows_seller_id_fkey`: `FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE`

## unserviceable_areas

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('unserviceable_areas_id_seq'::regclass) |
| municipality | character varying(255) | VARCHAR(255) | No | — |
| note | text | LONGTEXT (review size) | Yes | — |
| added_by | bigint | BIGINT | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `unserviceable_areas_added_by_foreign`: `FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL`
- `unserviceable_areas_municipality_unique`: `UNIQUE (municipality)`
- `unserviceable_areas_pkey`: `PRIMARY KEY (id)`

## users

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('users_id_seq'::regclass) |
| account_type | character varying(255) | VARCHAR(255) | No | 'buyer'::character varying |
| auth_method | character varying(255) | VARCHAR(255) | No | 'manual'::character varying |
| google_id | character varying(255) | VARCHAR(255) | Yes | — |
| last_name | character varying(255) | VARCHAR(255) | No | — |
| given_names | character varying(255) | VARCHAR(255) | No | — |
| middle_name | character varying(50) | VARCHAR(50) | Yes | — |
| sex | character varying(255) | VARCHAR(255) | No | — |
| birthday | date | DATE | No | — |
| age | smallint | SMALLINT | No | — |
| email | character varying(255) | VARCHAR(255) | No | — |
| contact_no | character varying(11) | VARCHAR(11) | No | — |
| province | character varying(255) | VARCHAR(255) | No | — |
| municipality | character varying(255) | VARCHAR(255) | No | — |
| barangay | character varying(255) | VARCHAR(255) | No | — |
| house_no | character varying(255) | VARCHAR(255) | Yes | — |
| street | character varying(255) | VARCHAR(255) | Yes | — |
| password | character varying(255) | VARCHAR(255) | Yes | — |
| id_file | character varying(255) | VARCHAR(255) | Yes | — |
| status | character varying(255) | VARCHAR(255) | No | 'pending'::character varying |
| remember_token | character varying(100) | VARCHAR(100) | Yes | — |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| is_admin | boolean | BOOLEAN (TINYINT(1)) | No | false |
| is_logistics | boolean | BOOLEAN (TINYINT(1)) | No | false |
| profile_picture | character varying(255) | VARCHAR(255) | Yes | — |
| username | character varying(255) | VARCHAR(255) | Yes | — |
| id_type_id | bigint | BIGINT | Yes | — |
| selfie_file | character varying(255) | VARCHAR(255) | Yes | — |
| business_name | character varying(150) | VARCHAR(150) | Yes | — |
| business_permit_file | character varying(255) | VARCHAR(255) | Yes | — |
| category_id | bigint | BIGINT | Yes | — |
| category_other | character varying(255) | VARCHAR(255) | Yes | — |
| vehicle_type | character varying(255) | VARCHAR(255) | Yes | — |
| vehicle_brand | character varying(255) | VARCHAR(255) | Yes | — |
| vehicle_model | character varying(255) | VARCHAR(255) | Yes | — |
| plate_number | character varying(255) | VARCHAR(255) | Yes | — |
| or_file | character varying(255) | VARCHAR(255) | Yes | — |
| cr_file | character varying(255) | VARCHAR(255) | Yes | — |
| license_number | character varying(255) | VARCHAR(255) | Yes | — |
| license_expiry | date | DATE | Yes | — |
| license_file | character varying(255) | VARCHAR(255) | Yes | — |
| shipping_fee | numeric(8,2) | DECIMAL(8,2) | Yes | — |
| notify_new_requests | boolean | BOOLEAN (TINYINT(1)) | No | true |
| notify_unassigned_shipments | boolean | BOOLEAN (TINYINT(1)) | No | true |
| preferred_scanner | character varying(255) | VARCHAR(255) | No | 'both'::character varying |
| id_type | text | LONGTEXT (review size) | Yes | — |
| id_photo_path | text | LONGTEXT (review size) | Yes | — |
| selfie_photo_path | text | LONGTEXT (review size) | Yes | — |
| last_active_at | timestamp with time zone | DATETIME(6), normalize to UTC | Yes | — |
| facebook_url | text | LONGTEXT (review size) | Yes | — |
| instagram_url | text | LONGTEXT (review size) | Yes | — |
| twitter_url | text | LONGTEXT (review size) | Yes | — |
| tiktok_url | text | LONGTEXT (review size) | Yes | — |
| notify_enabled | boolean | BOOLEAN (TINYINT(1)) | No | true |
| region | text | LONGTEXT (review size) | Yes | — |
| vehicle_ownership | character varying(255) | VARCHAR(255) | Yes | — |
| company_logo | character varying(255) | VARCHAR(255) | Yes | — |
| status_reason | text | LONGTEXT (review size) | Yes | — |
| is_online | boolean | BOOLEAN (TINYINT(1)) | No | false |
| logistics_center_id | bigint | BIGINT | Yes | — |
| or_cr_path | text | LONGTEXT (review size) | Yes | — |
| license_photo_path | text | LONGTEXT (review size) | Yes | — |
| suffix | character varying(255) | VARCHAR(255) | Yes | — |
| logistics_role | character varying(255) | VARCHAR(255) | Yes | — |
| logistics_hub_id | bigint | BIGINT | Yes | — |
| resume_file | character varying(2048) | VARCHAR(2048) | Yes | — |
| interview_scheduled_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| interview_location | character varying(255) | VARCHAR(255) | Yes | — |
| theme | character varying(10) | VARCHAR(10) | No | 'system'::character varying |
| preferred_language | character varying(10) | VARCHAR(10) | No | 'en'::character varying |

Constraints (current PostgreSQL definitions):

- `users_account_type_check`: `CHECK (((account_type)::text = ANY (ARRAY['buyer'::text, 'rider'::text, 'seller'::text, 'admin'::text, 'logistics'::text])))`
- `users_auth_method_check`: `CHECK (((auth_method)::text = ANY ((ARRAY['manual'::character varying, 'google'::character varying])::text[])))`
- `users_category_id_foreign`: `FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL`
- `users_email_unique`: `UNIQUE (email)`
- `users_google_id_unique`: `UNIQUE (google_id)`
- `users_logistics_center_id_fkey`: `FOREIGN KEY (logistics_center_id) REFERENCES logistics_centers(id) ON DELETE SET NULL`
- `users_logistics_hub_id_foreign`: `FOREIGN KEY (logistics_hub_id) REFERENCES logistics_hubs(id) ON DELETE SET NULL`
- `users_pkey`: `PRIMARY KEY (id)`
- `users_sex_check`: `CHECK (((sex)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying])::text[])))`
- `users_status_check`: `CHECK (((status)::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'suspended'::text, 'interview'::text])))`
- `users_suffix_check`: `CHECK (((suffix)::text = ANY ((ARRAY['Jr.'::character varying, 'Sr.'::character varying, 'II'::character varying, 'III'::character varying, 'IV'::character varying, 'V'::character varying])::text[])))`
- `users_username_unique`: `UNIQUE (username)`

## vehicle_types

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | bigint | BIGINT | No | nextval('vehicle_types_id_seq'::regclass) |
| slug | character varying(255) | VARCHAR(255) | No | — |
| name | character varying(255) | VARCHAR(255) | No | — |
| requires_documents | boolean | BOOLEAN (TINYINT(1)) | No | true |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |

Constraints (current PostgreSQL definitions):

- `vehicle_types_pkey`: `PRIMARY KEY (id)`
- `vehicle_types_slug_unique`: `UNIQUE (slug)`

## vouchers

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | — |
| seller_id | bigint | BIGINT | No | — |
| code | character varying(255) | VARCHAR(255) | No | — |
| discount_amount | numeric(10,2) | DECIMAL(10,2) | Yes | — |
| minimum_spend | numeric(10,2) | DECIMAL(10,2) | No | '0'::numeric |
| usage_limit | integer | INT | Yes | — |
| used_count | integer | INT | No | 0 |
| expires_at | date | DATE | Yes | — |
| is_active | boolean | BOOLEAN (TINYINT(1)) | No | true |
| created_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| updated_at | timestamp(0) without time zone | DATETIME(0) | Yes | — |
| type | character varying(255) | VARCHAR(255) | No | 'amount'::character varying |

Constraints (current PostgreSQL definitions):

- `vouchers_pkey`: `PRIMARY KEY (id)`
- `vouchers_seller_id_code_unique`: `UNIQUE (seller_id, code)`
- `vouchers_seller_id_foreign`: `FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE`

## wishlist_items

| Column | Current PostgreSQL type | Suggested MySQL type | Nullable | Current default |
| --- | --- | --- | --- | --- |
| id | uuid | CHAR(36) | No | gen_random_uuid() |
| buyer_id | bigint | BIGINT | No | — |
| product_id | uuid | CHAR(36) | No | — |
| created_at | timestamp with time zone | DATETIME(6), normalize to UTC | No | now() |

Constraints (current PostgreSQL definitions):

- `wishlist_items_buyer_id_fkey`: `FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE`
- `wishlist_items_buyer_id_product_id_key`: `UNIQUE (buyer_id, product_id)`
- `wishlist_items_pkey`: `PRIMARY KEY (id)`
- `wishlist_items_product_id_fkey`: `FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE`
