-- MySQL query reference for the Flutter services and local API.
-- The PHP implementations in api/ bind parameters and enforce account ownership.
-- This file contains read-only examples. Set variables to your own test IDs.
USE `pocketfinds`;
SET @buyer_id = NULL;
SET @rider_id = NULL;
SET @seller_id = NULL;
SET @product_id = NULL;
SET @order_id = NULL;
SET @counterpart_id = NULL;
SET @search = '%';
SET @subtotal = 0;

-- ProductService: catalog/search (optional category_id and seller_id filters).
SELECT p.id, p.seller_id, p.category_id, p.name, p.description, p.image,
       p.price, p.discount_price, p.stock, p.variations, p.created_at
FROM products p JOIN users seller ON seller.id = p.seller_id
WHERE p.status = 'active' AND seller.account_type = 'seller'
  AND seller.status = 'approved' AND p.name LIKE @search
ORDER BY p.created_at DESC LIMIT 1000;

-- CategoryService and seller display fields. Never select passwords publicly.
SELECT id, name FROM categories ORDER BY name;
SELECT id, given_names, last_name, business_name, username, municipality, province
FROM users WHERE account_type = 'seller' AND status = 'approved' AND id = @seller_id;

-- LogisticsCenterService: current website hubs are adapted to the mobile center
-- display. HubHiring computes intake eligibility and remaining approved slots.
SELECT id, name, company_name, province, municipality, address, is_operational,
       rider_hiring_open, rider_hire_limit, rider_hiring_closes_at FROM logistics_hubs ORDER BY id;

-- CartService: the website and mobile app share cart_items.
SELECT id, product_id, qty, variation_group, variation_value, price
FROM cart_items WHERE buyer_id = @buyer_id ORDER BY created_at;

-- OrderService: native website identities, not the old app_* mirror columns.
SELECT id, order_number, status, items, subtotal, shipping_amount, total,
       shipping_address, payment_method_id, seller_id AS app_seller_id,
       discount_amount, voucher_code, created_at
FROM orders WHERE buyer_id = @buyer_id ORDER BY created_at DESC;
-- The API groups placed/confirmed/preparing/ready_for_pickup under To Ship;
-- picked_up/at_sorting_center/hub_transfer/sorted/assigned_to_rider under In Transit.
SELECT h.* FROM order_status_history h JOIN orders o ON o.id = h.order_id
WHERE h.order_id = @order_id AND o.buyer_id = @buyer_id ORDER BY h.created_at;

-- Checkout: shipping fees and stock are always read again on the server.
SELECT id, shipping_fee FROM users WHERE id = @seller_id AND status = 'approved';
-- Actual checkout runs BEGIN, SELECT ... FOR UPDATE on buyer/products/cart,
-- validates current prices, reserves stock, inserts orders/history, and deletes
-- only the buyer's selected cart rows, then COMMIT. On failure it rolls back.
-- See api/Commerce.php and native Laravel Product/Order models for write queries.
-- mobile_checkouts protects retries from charging coins or reserving stock twice.
SELECT checkout_id, result FROM mobile_checkouts WHERE buyer_id = @buyer_id;

-- WishlistService: current website wishlist snapshots (wishlist_entries).
SELECT id, product_id FROM wishlist_entries WHERE buyer_id = @buyer_id ORDER BY created_at DESC;
-- ShopService.
SELECT seller_id, created_at FROM shop_follows WHERE buyer_id = @buyer_id ORDER BY created_at DESC;
SELECT COUNT(*) AS follower_count FROM shop_follows WHERE seller_id = @seller_id;
SELECT COUNT(*) AS product_count FROM products WHERE seller_id = @seller_id AND status = 'active';
SELECT COALESCE(AVG(rating), 0) AS average_rating, COUNT(*) AS review_count
FROM reviews WHERE seller_id = @seller_id;

-- ReviewService: anonymous names are masked by the API before returning data.
SELECT id, rating, comment, seller_reply, created_at, media_url, is_anonymous
FROM reviews WHERE product_id = @product_id ORDER BY created_at DESC;
SELECT COALESCE(AVG(rating), 0) AS average, COUNT(*) AS review_count
FROM reviews WHERE product_id = @product_id;
SELECT * FROM reviews WHERE buyer_id = @buyer_id ORDER BY created_at DESC;

-- CoinService and platform voucher eligibility.
SELECT COALESCE(SUM(delta), 0) AS balance FROM coin_transactions WHERE user_id = @buyer_id;
SELECT id, delta, reason, order_id, created_at FROM coin_transactions
WHERE user_id = @buyer_id ORDER BY created_at DESC;
SELECT *, minimum_spend <= @subtotal AS eligible FROM platform_vouchers
WHERE is_active = TRUE AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
  AND (usage_limit IS NULL OR used_count < usage_limit);

-- NotificationService.
SELECT * FROM notifications WHERE user_id = @buyer_id ORDER BY created_at DESC LIMIT 200;
SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = @buyer_id AND is_read = FALSE;

-- ConversationService. Only the authenticated participants may read a thread.
SELECT * FROM messages
WHERE (sender_id = @buyer_id AND receiver_id = @counterpart_id)
   OR (sender_id = @counterpart_id AND receiver_id = @buyer_id)
ORDER BY id DESC LIMIT 300;
SELECT blocked_id AS user_id FROM blocked_users WHERE blocker_id = @buyer_id;

-- ShipmentService: open requests additionally require the website's company/hub
-- service-area policy (LogisticsAccess); do not expose every sorted parcel.
SELECT id, order_id, shipping_status, courier_id, delivery_fee, picked_up_at,
       out_for_delivery_at, delivered_at, created_at
FROM shipments WHERE courier_id = @rider_id ORDER BY created_at DESC;

-- PaymentVerificationService and the current GCash reservation workflow.
SELECT * FROM payment_verifications WHERE buyer_id = @buyer_id AND order_id = @order_id;
SELECT id, seller_id, amount_due, payment_state, reservation_state, instructions,
       revision, expires_at FROM gcash_purchases WHERE buyer_id = @buyer_id ORDER BY created_at DESC;

-- VisualSearchService: image_signature is JSON in MySQL. The API computes
-- Euclidean distance over 48 numeric components instead of using pgvector.
SELECT id, image_signature FROM products WHERE status = 'active' AND image_signature IS NOT NULL;

-- ERP applications: email verification leaves pending; existing admin/logistics
-- review screens authorize approval and send the decision email. Password and
-- document contents are never exposed by public catalog/profile queries.
SELECT id, account_type, given_names, last_name, middle_name, sex, birthday, age,
       province, municipality, barangay, house_no, street, status, logistics_hub_id
FROM users WHERE id = @applicant_id;

-- Seller pickup is a different leg from final buyer delivery. The API adds
-- LogisticsAccess company, operational hub, vehicle and origin-area constraints.
SELECT id, order_id, tracking_number, pickup_rider_id, origin_hub, origin_province,
       rider_confirmed_pickup_at, seller_confirmed_pickup_at, shipping_status
FROM shipments WHERE pickup_rider_id = @rider_id ORDER BY updated_at DESC;

-- The mutation performs this current locked read before checking unclaimed
-- ownership. This prevents repeatable-read snapshots from overwriting a winner.
SELECT id, pickup_rider_id, shipping_status, pickup_approved_at
FROM shipments WHERE id = @shipment_id FOR UPDATE;

-- Hub receipt is progress, not proof of physical return to the seller.
SELECT shipment_id, return_requested_at, return_received_at, return_received_by
FROM delivery_verifications WHERE shipment_id = @shipment_id;
SELECT actor_id, action, status, created_at FROM delivery_events
WHERE shipment_id = @shipment_id ORDER BY created_at;
