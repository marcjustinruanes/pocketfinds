# Gemini Buyer Chat And FAQs

## Model And Free Tier

Verified October 10, 2026 using Google's official documentation:

- Default: `gemini-3.5-flash-lite`, a stable text-output model with structured outputs: https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite
- Free text input/output is listed for this model; other currently listed free-tier text models include Gemini 3.8 Flash and 3.1 Flash-Lite: https://ai.google.dev/gemini-api/docs/pricing
- Exact requests/minute, tokens/minute and requests/day limits depend on your project/tier and must be checked in AI Studio. Published capacities are not guaranteed: https://ai.google.dev/gemini-api/docs/rate-limits
- Free-tier content may be used to improve Google's products. Do not send sensitive customer information to a free project. Check current terms before deployment: https://ai.google.dev/gemini-api/docs/terms

No paid-only model fallback, search grounding, file upload, retries or paid provider switch is enabled. The configured local defaults (5 requests/minute and 100 requests per rolling 24 hours) are application-wide safety caps, NOT a claim about Google's free allowance. Lower them to your actual project's limits. Usage from other applications sharing that project also counts against Google's quota. Local caps cannot guarantee free usage if you enable billing or select a paid model.

## Get And Configure A Key

1. Sign in at https://aistudio.google.com/api-keys and create/select a project with free-tier access. Check the project's active model and quota availability. Do not enable billing merely to run this feature.
2. Create a Gemini API key and restrict it to the Gemini/Generative Language API as supported by your project. See https://ai.google.dev/gemini-api/docs/api-key for current key requirements and supported restrictions.
3. Set these backend variables in your local `.env` and deployment secrets:

```dotenv
GEMINI_API_KEY=your_private_key
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_LOCAL_RPM=5
GEMINI_LOCAL_DAILY_REQUESTS=100
```

4. Run `php artisan config:clear` locally. Production deployments can use `php artisan config:cache` after setting secrets. Restart long-running PHP workers if used.
5. On another installation, apply these migrations in order:

```powershell
php artisan migrate --path=database/migrations/2026_10_10_000002_create_buyer_conversations.php
php artisan migrate --path=database/migrations/2026_10_10_000003_create_support_faqs.php
php artisan migrate --path=database/migrations/2026_10_10_000004_add_buyer_chat_provider.php
```

6. Sign into a buyer account and open Ask PocketFinds. Smart mode answers recognized questions locally; AI chat calls the real `generateContent` REST API. Ask for a product comparison and verify an AI response. Missing keys, quota errors, blocked output and outages produce visibly labeled saved fallback replies, not pretend AI answers.

Never commit `.env`, paste a key into chat, add it to JavaScript/VITE variables or put it in a request URL. This integration sends the key in a backend `x-goog-api-key` header. Google OAuth sign-in credentials are unrelated to Gemini keys. Existing Groq admin/guest functionality is preserved; current database-backed buyer chat uses Gemini only.

## Knowledge And Privacy

Catalog retrieval uses approved active listings, current prices, variation stock, categories and seller shipping fees. Published buyer FAQs and buyer-policy excerpts are fetched on each question; drafts/admin FAQs never enter buyer AI context. English, Tagalog, Taglish and neutral respectful wording are requested in the system instructions, but AI accuracy/language fluency is not guaranteed.

Manage Buyer/Admin FAQ records from Admin > FAQs. Enter real approved answers and optional comma-separated matching keywords, then publish. No dummy FAQ records are seeded. Editing/unpublishing/deleting takes effect on the next question. Specific exact FAQ questions and configured keyword matches are answered locally in Smart/Automated mode. Automated admin chat also uses published admin FAQs.

Gemini receives up to 12 public products, 3 policy excerpts, 6 published buyer FAQs and the last 4 Gemini-only messages in the active consent/mode segment. Outputs are capped at 1,024 tokens. No cross-user cache is used, so private replies cannot leak through cached answers. Order records are intentionally removed from Gemini snapshots: use Smart/Automated order status or My Orders for private account data. Avoid typing personal details in AI chat; typed messages themselves are sent to Google. Existing Groq transcripts are never forwarded to Gemini.

Conversations remain encrypted in MySQL until individually deleted by their owner. Preserve `APP_KEY` securely with database backups. Guest sessions/admin AI features are not migrated to Gemini. The legacy session-only buyer endpoint remains available for old clients; the buyer UI exclusively uses the new persistent Gemini endpoint.

## Errors And Testing

Local/global caps reject requests before contacting Google. A provider HTTP 429 activates a one-minute shared cooldown. No automatic retry consumes extra quota. Unauthorized keys, non-STOP safety/truncation results, malformed JSON and network failures result in support fallback. Contact support requires manual submission and working mail configuration.

Run `php artisan test --compact`. Tests use HTTP fakes only in the test suite; production calls the genuine Gemini endpoint. A live end-to-end test requires your configured key and eligible project, and consumes a request.
