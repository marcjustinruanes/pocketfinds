# Buyer Support Conversations

Buyer storefront pages use `/buyer/assistant/conversations` for database-backed support chat. The older `/buyer/assistant` session endpoints remain compatible with older clients; the current buyer widget does not use them. Guest and admin chat behavior is unchanged.

Any surviving buyer session transcript is imported once when the conversation list is first opened. Previously pruned messages or expired sessions cannot be recovered. Imported text is retained but excluded from new AI context.

## Reply Modes

- Smart: recognized shopping FAQs use local rules; unmatched questions go to Gemini. Complex comparisons and recommendations bypass basic keyword rules.
- Automated: local responses only, including unmatched-topic guidance. No AI API calls.
- AI chat: Gemini responses grounded in a fresh, bounded database snapshot. See `gemini-setup.md` for configuration, quotas and privacy.

The widget discloses external processing before messages are submitted. Orders are opt-in and always scoped to the authenticated buyer. Changing mode or order sharing starts a new provider-context segment without deleting saved messages. Only the last four AI messages within that segment are forwarded; unrelated conversations and automated/fallback transcripts are excluded.

## Knowledge Sources

Each question retrieves approved sellers' active products across the catalog, ranks up to 200 matching candidates and supplies up to 24 listings. Prices, variation stock, configured per-seller shipping fees, categories, enabled payment methods and published buyer-policy excerpts come from the current database. Policy paragraphs are ranked by question keywords. Pending policy changes are not used.

Published buyer FAQs are managed by authorized admins in a separate support knowledge table. Operational FAQ guidance describes existing order, checkout and support flows; return terms come only from published policies and approved FAQs. Missing information is escalated, not invented. This is retrieval-based grounding, not model fine-tuning. AI responses still need caution and are not guaranteed correct. Historical chat prices/statuses remain historical; fresh answers use a new snapshot.

## Storage And Access

Run `php artisan migrate --path=database/migrations/2026_10_10_000002_create_buyer_conversations.php` on other environments.

`buyer_conversations` owns a buyer's transcript and mode. `buyer_chat_messages` retains all messages and response links. Titles, message text and payloads use Laravel encrypted casts. Preserve `APP_KEY` securely with database backups; changing it without appropriate previous-key configuration prevents decryption. Never commit `.env`, keys or chat/database exports.

All conversation endpoints enforce buyer ownership, exclude admin accounts, use authentication/CSRF and rate limits, and return private no-store responses. Creating a new chat never deletes previous chats. Explicit deletion removes only that conversation and cascades its messages. No automatic pruning occurs. Lists paginate at 30 conversations; transcripts at 50 messages, with older messages accessible on demand.

AI outage/quota errors produce a saved support fallback. Contact support opens the existing manual support form; sending a message requires buyer submission and working mail configuration. No automatic ticket or email is claimed.

## Verification

`php artisan test --compact` covers ownership, session-independent history, encryption, CRUD, pagination, response routing, provider-context separation, fresh database values and retrieval of older matching catalog entries. Provider requests are faked; tests do not send paid requests or mutate the production database.
