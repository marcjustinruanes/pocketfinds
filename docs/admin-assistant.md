# Admin AI Workspace

Ask dashboard also has a rule-based Automated mode that works without a Groq key.
See `automated-assistant.md`. AI review, analytics and draft generation still require
the configured provider and explicit data-sharing consent.

Open `/admin/ai` while signed in as a platform admin. Existing dashboard, reports,
complaints, product reviews, registrations and announcements have AI entry points.
Uses the same private `GROQ_API_KEY` as the shopping assistant. Run `php artisan migrate`
to add `inventory_movements`; existing product/order records are not replaced.

Tasks: dashboard summaries; oldest-open complaint triage; seller application field
checks; product text screening with optional first-photo analysis; editable
announcement drafts; database-grounded dashboard questions with short session history;
sales analytics, inventory recommendations, anomaly explanations and product-copy drafts.

## Business Insights

Today, 7-day, 30-day and 90-day windows provide computed daily order values, change
from the previous equally sized period, best-selling products and aggregate repeat
customer behavior. Product units/value come from the historical JSON order items,
not current catalog prices. Item values are before order-level discounts and shipping.
CSV reports are admin-only, contain no customer contact details, and escape formula
cells from seller-entered names. Export does not call the AI provider.

Forecasts require at least 28 observed days and 20 delivered/completed orders. They
use a daily historical average, include zero-sales days and ignore seasonality; they
are explicitly estimates, not guarantees. Missing results mean insufficient evidence.
Product stock-cover/reorder estimates also require 5 delivered/completed orders for
that product. The 30-day reorder target does not know supplier lead times. Low-stock
threshold is 5 units; overstock is potential cover above 90 days. Older unsold listings
are review candidates, not proven poor conversion: impressions are not recorded.

Explainable review rules flag >=5 orders/account/day, >=3 cancelled orders/account in
the period, and order value above 5 times the completed-order median (20-order minimum).
Account references sent to Groq are HMAC pseudonyms, not names/contacts. Server-supplied
order references open an admin-only read-only investigation screen. These heuristics
may flag legitimate shopping; they never suspend accounts, cancel orders or prove fraud.
Payment failures and abnormal logins are not detected: the app does not record payment
attempts or login events. A failed delivery is not a failed payment.

Stock operations through `Product::deductStock` now record before/after balances,
requested/applied quantities, internal actor and route. Records roll back with the
transaction. Only product names, balances, reason and timestamp are shared with AI;
actor IDs stay internal. Large changes (>=20 units and >=50% of prior stock), quantity
mismatches and differences from the latest logged balance become review warnings.
No historic ledger is reconstructed. Direct SQL imports and manual listing edits are
not logged by this path. Insights sample the latest 50 movements, so reconcile physical
stock manually. Without the migration existing commerce still works, with a clear gap.

Product-copy drafts suggest title, description, tags and an existing category. They
remain editable/copyable only; they do not write product fields or publish a listing.
The admin must review claims and have the seller revise the existing listing.

## Human Decisions

This is advisory, not a certification or automated moderation engine. No AI endpoint
approves/rejects products or sellers, resolves complaints, suspends accounts, issues
refunds or publishes announcements. Existing manual workflows remain unchanged.
Product classifications are No obvious concerns, Needs review and Potential policy
violation. No obvious concerns does not mean safe, legal or authentic. AI may miss
hazards or flag legitimate listings. Read the evidence, inspect the original listing
and apply your actual policy. Sellers can revise/resubmit through existing workflows;
this feature does not create a new automated rejection or appeal system.

Seller reviews use presence flags only, not government IDs, selfies, permits,
addresses, email addresses or phone numbers. Presence cannot establish authenticity.
Complaint descriptions have emails and likely phone numbers masked, but automatic
redaction is imperfect: free text can still contain names or other sensitive content.
Review your privacy notice and Groq data controls before production use.

## Photos

Photo sharing is off by default and requires explicit selection. Uses
`GROQ_VISION_MODEL=qwen/qwen3.8-27b` (default); the text model stays `GROQ_MODEL`.
Only a local JPEG/PNG/WebP within the public storage disk is eligible: maximum
4 MB, maximum dimension 4096 px. External URLs, traversal paths and symlinks outside
the storage root are not read/fetched. No private identity documents are sent.
The first eligible product photo is encoded inline for Groq. Missing/unsupported
photos yield a clearly marked text-only review. A photo cannot prove ingredients,
authenticity, standards compliance or physical safety. Vision consumes additional
free-tier tokens; there is no automatic retry or paid fallback.
Docs: https://console.groq.com/docs/vision

## Grounding And Scope

All analytics use predefined read-only queries, never generated SQL. Date windows
are 1/7/30/90 days using the application timezone; current-month shop rankings are calculated separately. Sales are
order totals for delivered/completed orders, grouped by placement date, not net
profit or platform revenue. Refunds are not deducted. Low stock is <=5 units using
the product model's variation-aware total-stock accessor. Low-stock examples and
oldest open complaints are bounded samples, not full listings. Duplicate candidates
are exact name/SKU matches, not proof of wrongdoing. Seller policy excerpts can be
truncated, so review the complete policy manually.

Each request requires data-sharing consent, admin authorization and CSRF protection.
Six AI requests/minute per admin; Groq organization-wide free-tier quotas also apply.
Context/output are length-limited. Responses and drafts are rendered as plain text.
AI links are restricted to server-supplied sources. API errors do not log request
data or response bodies. Admin chat retains up to two exchanges in the admin's
Laravel session and can be cleared. Summaries and reviews are otherwise not saved.

Announcement drafts can be edited/copied or transferred to the existing form via
a session flash. Transfer does not publish or notify anyone. Only the existing
Post Announcement action creates the announcement.

## Checks

`php artisan test --compact` uses fake providers and in-memory SQLite schemas.
No production records or paid API calls are modified by the test suite. Review
real model output manually before relying on it. Free quotas/model access vary;
see https://console.groq.com/docs/rate-limits and https://console.groq.com/docs/your-data.
