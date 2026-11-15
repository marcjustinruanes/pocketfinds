-- public.cart_items.id (uuid PK) has no server-side default, unlike every
-- other uuid PK in this schema (orders.id, shipments.id, products.id all
-- default to gen_random_uuid()) — inserts must supply an id explicitly or
-- fail with a NOT NULL violation. Bring it in line with the rest of the
-- schema instead of generating ids client-side in Flutter.
--
-- Run this in the Supabase SQL editor.

ALTER TABLE public.cart_items ALTER COLUMN id SET DEFAULT gen_random_uuid();
