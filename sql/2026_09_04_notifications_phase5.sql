-- Notifications: real "mark all read". Filters (Orders/System) are
-- computed client-side from the real `notification_type` values already
-- on the table — no schema change needed for those.

BEGIN;

CREATE OR REPLACE FUNCTION public.mark_all_notifications_read()
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  UPDATE public.notifications SET is_read = true
  WHERE user_id = public.my_legacy_user_id() AND is_read = false;
$$;
GRANT EXECUTE ON FUNCTION public.mark_all_notifications_read() TO authenticated;

COMMIT;
