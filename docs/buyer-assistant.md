# Buyer AI Assistant

Shopping chat now also offers a non-AI Automated mode. See `automated-assistant.md`
for its topics, privacy boundaries and mode-switch behavior. The Groq details below
apply only when the user chooses AI chat.

The floating assistant appears on guest home/product/shop pages, the buyer dashboard
and all buyer account pages. Guest requests always use public context, even if the
visitor is signed in; guest order-sharing requests are rejected.
It supports shopping and gift suggestions, budget comparisons and store navigation.
Natural-language shopping queries rank up to 200 eligible catalog candidates locally
(including a few common English/Filipino category synonyms), then share the most
relevant 24 with AI. This is bounded retrieval, not an exhaustive semantic search index.
Product-page visitors can opt in to include that product for related alternatives.
Only chat preferences and that explicit selection personalize suggestions: no browsing
history, cross-site tracking or persistent personalization profile is collected.

Answers include published platform buyer-policy excerpts and active payment-method
names. Pending policy edits, private seller documents and payment-account details are
excluded. Policies may be truncated; unknown exceptions must go to a human. Shipping
fees remain seller-specific and are shown at checkout; no delivery dates are invented.
Contact support opens a separate form. Sending requires an explicit user submission,
goes through existing support email (3 requests/minute), and never shares the email or
issue form with Groq. Mail failure keeps the user's draft; no ticket/response-time
promise is made. Configure `support_email` and SMTP before relying on escalation.
Buyers can opt in to share up to ten of their own recent order statuses. It cannot
place/cancel orders, issue refunds or change account details.

## Setup

Set these in the server's private `.env` (never commit an API key):

```dotenv
GROQ_API_KEY=your-server-side-groq-key
GROQ_MODEL=openai/gpt-oss-20b
```

Create a Groq account at https://console.groq.com and generate an API key in API Keys.
Keep the account on its Free plan. Run `php artisan config:clear` after setting the key.
Every message makes one Groq Chat Completions API call. Free-plan quotas apply and
are shared across the Groq organization, not per buyer. Requests are not unlimited:
https://console.groq.com/docs/rate-limits
The app never falls back to a paid provider or automatically upgrades a plan.
No new database migration is needed for shopping chat itself.
Without a key the assistant displays an honest unavailable state; navigation still works.

## Data And Limits

Messages, up to two prior exchanges (each prior message capped at 1,000 characters),
and a snapshot of up to 24 relevant active products from approved sellers are sent
to Groq. Descriptions are shortened and internal URLs are omitted from API input.
The catalog snapshot may not
include every product. The optional order context contains only order number,
status, total and date, not addresses, contact details or payment information.
Changing order sharing clears the conversation both on screen and server-side.
History is scoped to the authenticated buyer or a separate guest Laravel session;
New chat clears it server-side. Guest history never includes private order context.
The widget does not persist conversation text in browser storage.

Review Groq's data controls and your privacy notice before production use, especially
before enabling order sharing: https://console.groq.com/docs/your-data
Do not promise zero provider retention unless the account is configured accordingly.

Endpoint authentication, CSRF protection, 1,500-character message limits and
10-request/minute buyer throttling (6 for guests) apply. Product links/prices are constructed server-side
from eligible catalog entries. Replies are rendered as text, never generated HTML.
Errors do not log conversation content or provider response bodies. Requests time
out after 30 seconds and are not automatically retried. A provider quota error shows
a usage-limit notice and keeps the draft for retry. Shorter context reduces quota
usage but does not guarantee every request fits within the free tier's token limit.

## Verification

Run `php artisan test --compact`. Provider tests use `Http::fake`; no paid calls
are needed. Live answer quality still requires a configured Groq key and manual review.
