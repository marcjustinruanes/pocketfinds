# Automated And AI Chat

Shopping chat (buyer and guest) has an Automated / AI chat selector. Admins have
the same selector in AI Workspace > Ask dashboard. A new conversation defaults
to Automated. Existing AI conversations retain their AI label when restored.
Other admin review/draft tools remain AI-only. Main dashboard review alerts remain hidden.

Automated mode is a server-side rule bot: fixed intents, static response templates,
known website links and actual database snapshots. It does not use a model, a Groq
key, an external API, background inference or paid tokens. It is not an offline bot:
the website/database must be reachable. Unknown questions show topic choices rather
than fabricated answers. It never automatically falls back to AI.

Buyer topics: catalog/budget information, own recent order statuses, cancellation
navigation, shipping information, active payments, published buyer-policy excerpts
and human support. Product results are a limited snapshot, not exhaustive semantic
matches. Order access is still opt-in and account-scoped. Guests cannot share or
access private orders. Policies are excerpts, not refund guarantees. Human contact
still requires an explicit form submission. No messages, refunds or order changes
are initiated by the bot.

Admin topics: delivered/completed sales value, low stock, best sellers, current-month
shop rankings, pending seller/product reviews, complaint counts and data exports.
Queries are predefined/read-only. No generated SQL or automated approval is used.
Normal chat remains protected by admin authorization even without an AI key.

Requests specify `engine=automated` or `engine=ai`. Existing API callers omitting
engine continue to use AI for backwards compatibility; the new UI explicitly sends
the selection. Provider availability in history responses refers to AI only.
Session history records its engine. A mode switch clears the visible/server chat and
unchecks shopping context/order sharing and admin Groq consent. The server also
discards mismatched-engine history, so automated messages are not forwarded to AI
even if the UI clear request is bypassed. Explicit new AI questions still go to Groq
with the selected current context. Draft text remains when switching or on failure.

Existing session/CSRF/role validation, length limits and per-route throttles apply
to both modes. Responses and topic labels render as text, not HTML. No new migration
or chatbot credential is required. Run `php artisan test --compact` for the rule,
consent, privacy, no-provider-call and AI-mode-isolation tests.
