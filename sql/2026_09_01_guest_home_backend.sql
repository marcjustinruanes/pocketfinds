-- Guest/Landing Home redesign backend: exactly one new RPC, for a real
-- "Discover Shops" rail. Everything else the Guest Home rebuild needs
-- (New Arrivals, Deals) is already real (products.created_at/discount_price)
-- and needs no schema changes.
--
-- Run this in the Supabase SQL editor (same pattern as every prior
-- migration this session — no Supabase CLI/MCP access from this
-- environment).

BEGIN;

-- ============================================================================
-- list_shops — public. Every seller with >=1 active product, with the same
-- real aggregates get_shop_stats() computes per-seller (see
-- sql/2026_08_31_buyer_redesign_backend.sql), batched across all sellers so
-- Guest Home can show a "Discover Shops" rail without per-seller round trips.
-- Ordered by real average rating, then real product count — no fabricated
-- "trending"/"featured" ordering.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.list_shops(p_limit int DEFAULT 10)
RETURNS TABLE (
  seller_id bigint,
  name text,
  location text,
  product_count bigint,
  average_rating numeric
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT
    u.id AS seller_id,
    CASE
      WHEN u.business_name IS NOT NULL AND u.business_name <> ''
        THEN u.business_name
      ELSE trim(u.given_names || ' ' || u.last_name)
    END AS name,
    NULLIF(trim(concat_ws(', ', NULLIF(u.municipality, ''), NULLIF(u.province, ''))), '') AS location,
    p.product_count,
    coalesce(r.average_rating, 0)::numeric(3,2) AS average_rating
  FROM public.users u
  JOIN (
    SELECT seller_id, count(*) AS product_count
    FROM public.products
    WHERE status = 'active'
    GROUP BY seller_id
  ) p ON p.seller_id = u.id
  LEFT JOIN (
    SELECT seller_id, avg(rating) AS average_rating
    FROM public.reviews
    GROUP BY seller_id
  ) r ON r.seller_id = u.id
  ORDER BY average_rating DESC NULLS LAST, p.product_count DESC
  LIMIT p_limit;
$$;

GRANT EXECUTE ON FUNCTION public.list_shops(int) TO anon, authenticated;

COMMIT;
