-- CRITICAL FIX + new feature: public.messages currently has RLS disabled
-- AND `anon` (fully unauthenticated) has SELECT/INSERT/UPDATE/DELETE/
-- TRUNCATE on it — anyone on the internet can currently read, forge, or
-- wipe every user's private messages with no login at all. Run PART 1
-- immediately regardless of whether the Messages feature ships.
--
-- Run this in the Supabase SQL editor. Not executed by the app or any
-- agent — no Supabase CLI/MCP access from this environment.
--
-- ============================================================================
-- PART 1 — Lock down public.messages (same pattern as public.users /
-- public.rider_profiles: revoke direct grants, enable RLS with zero public
-- policies, only SECURITY DEFINER RPCs may touch it)
-- ============================================================================

revoke all on public.messages from anon, authenticated;
alter table public.messages enable row level security;
-- No policies added on purpose — every access below goes through RPCs.

-- ============================================================================
-- PART 2 — current_app_user_id(): resolves the caller's public.users.id
-- (bigint) from their verified Supabase Auth email. Same email-based
-- linkage complete_registration()/get_my_profile() already rely on.
-- ============================================================================

create or replace function public.current_app_user_id()
returns bigint
language sql
security definer
set search_path = public
as $$
  select id from public.users where lower(email) = lower(auth.email());
$$;

grant execute on function public.current_app_user_id() to authenticated;

-- ============================================================================
-- PART 3 — Messaging RPCs
--
-- Design notes:
-- - `messages` has no conversation_id — a "conversation" is derived as all
--   messages between the caller and one counterpart, grouped by counterpart
--   only (not split per product_id — product_id is carried per-message as
--   context, not used to fork separate threads for the same two people).
-- - No DELETE is exposed anywhere (message history is immutable other than
--   the `read` flag) — a deliberate, conservative default; nothing in this
--   pass asked for message deletion.
-- - `created_at`/`updated_at` have no DB-side default, so `send_message`
--   sets them explicitly.
-- ============================================================================

-- One row per conversation partner: latest message + unread count.
create or replace function public.fetch_my_conversations()
returns table (
  counterpart_id bigint,
  counterpart_name text,
  counterpart_role text, -- buyer/seller/rider/admin/logistics — lets the UI label "Seller" vs "PocketFinds Courier"
  last_message_body text,
  last_message_at timestamp,
  unread_count bigint
)
language sql
security definer
set search_path = public
as $$
  with me as (select public.current_app_user_id() as id),
  mine as (
    select
      m.*,
      case when m.sender_id = (select id from me) then m.receiver_id else m.sender_id end as counterpart_id
    from public.messages m
    where m.sender_id = (select id from me) or m.receiver_id = (select id from me)
  ),
  latest as (
    select distinct on (counterpart_id)
      counterpart_id, body as last_message_body, created_at as last_message_at
    from mine
    order by counterpart_id, created_at desc nulls last, id desc
  ),
  unread as (
    select counterpart_id, count(*) as unread_count
    from mine
    where receiver_id = (select id from me) and read = false
    group by counterpart_id
  )
  select
    l.counterpart_id,
    u.given_names || ' ' || u.last_name as counterpart_name,
    u.account_type as counterpart_role,
    l.last_message_body,
    l.last_message_at,
    coalesce(un.unread_count, 0) as unread_count
  from latest l
  join public.users u on u.id = l.counterpart_id
  left join unread un on un.counterpart_id = l.counterpart_id
  order by l.last_message_at desc nulls last;
$$;

grant execute on function public.fetch_my_conversations() to authenticated;

-- All messages between the caller and one counterpart, oldest first.
create or replace function public.fetch_conversation_messages(p_counterpart_id bigint)
returns setof public.messages
language sql
security definer
set search_path = public
as $$
  select *
  from public.messages
  where (sender_id = public.current_app_user_id() and receiver_id = p_counterpart_id)
     or (sender_id = p_counterpart_id and receiver_id = public.current_app_user_id())
  order by created_at asc nulls last, id asc;
$$;

grant execute on function public.fetch_conversation_messages(bigint) to authenticated;

-- Sends a message from the caller to p_receiver_id.
create or replace function public.send_message(p_receiver_id bigint, p_body text, p_product_id uuid default null)
returns public.messages
language plpgsql
security definer
set search_path = public
as $$
declare
  v_me bigint := public.current_app_user_id();
  v_row public.messages;
begin
  if v_me is null then
    raise exception 'Not a registered PocketFinds user.';
  end if;
  if p_body is null or btrim(p_body) = '' then
    raise exception 'Message body cannot be empty.';
  end if;
  if not exists (select 1 from public.users where id = p_receiver_id) then
    raise exception 'Recipient does not exist.';
  end if;

  insert into public.messages (sender_id, receiver_id, body, product_id, read, created_at, updated_at)
  values (v_me, p_receiver_id, p_body, p_product_id, false, now(), now())
  returning * into v_row;

  return v_row;
end;
$$;

grant execute on function public.send_message(bigint, text, uuid) to authenticated;

-- Marks every unread message FROM p_counterpart_id TO the caller as read.
create or replace function public.mark_messages_read(p_counterpart_id bigint)
returns void
language sql
security definer
set search_path = public
as $$
  update public.messages
  set read = true, updated_at = now()
  where receiver_id = public.current_app_user_id()
    and sender_id = p_counterpart_id
    and read = false;
$$;

grant execute on function public.mark_messages_read(bigint) to authenticated;
