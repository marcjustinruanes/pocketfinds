-- Rider UI/UX redesign: small, real backend additions for earnings and
-- online/offline status (not fabricated data — see the plan's Context
-- section for why these two specifically got real columns while ratings/
-- live distance were deliberately left out of this pass).
--
-- Run this in the Supabase SQL editor if it wasn't already applied directly.

BEGIN;

-- ============================================================================
-- 1) shipments.delivery_fee — the real shipping fee charged at checkout,
--    now also recorded per-shipment so a rider's earnings are a real sum,
--    not a fabricated number. OrderService.createOrders() now writes this
--    on every new shipment; backfill the one pre-existing row from its
--    order's shipping_amount.
-- ============================================================================
ALTER TABLE public.shipments ADD COLUMN IF NOT EXISTS delivery_fee numeric;

UPDATE public.shipments s
SET delivery_fee = o.shipping_amount
FROM public.orders o
WHERE s.order_id = o.id AND s.delivery_fee IS NULL;

-- ============================================================================
-- 2) rider_profiles.is_online — the dashboard's Online/Offline toggle.
-- ============================================================================
ALTER TABLE public.rider_profiles ADD COLUMN IF NOT EXISTS is_online boolean NOT NULL DEFAULT false;

CREATE OR REPLACE FUNCTION public.set_online_status(p_online boolean)
RETURNS void
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  UPDATE public.rider_profiles
  SET is_online = p_online, updated_at = now()
  WHERE lower(email) = lower(auth.email());
$$;
GRANT EXECUTE ON FUNCTION public.set_online_status(boolean) TO authenticated;

COMMIT;
