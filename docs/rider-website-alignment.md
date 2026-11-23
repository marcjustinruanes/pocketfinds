The rider API now loads the same pinned Laravel runtime as the embedded buyer website. Deploy that runtime with the API; do not point rider requests back at an older website checkout.

Shared records and actions:

- Availability: `users.is_available` and `availability_updated_at`. Flutter calls `rider/availability` with `is_available`. App resume reads the server value; GPS and app heartbeats no longer override website availability.
- Offers: `delivery_assignments`, using the website's `RiderDeliveryOffers` for eligibility, expiration, acceptance and decline. Both pickup and final delivery use this service. Existing notification records and the push outbox deliver `delivery_assigned` alerts.
- Shipment states: native clients receive `sorted` and `assigned_to_rider`, matching the web. Old request filters and endpoint aliases remain accepted for older installed APKs.
- OTP: `shipments.delivery_otp_hash`, `delivery_otp_ciphertext`, `delivery_otp_attempts`, and `delivery_otp_locked_until`. Starting delivery invokes `RiderController::advance`, which generates the code and sends the website's buyer notification. The buyer reads it in their order; the app does not generate or email a second code.
- Proof: Flutter sends `delivery_otp` and `delivery_proof`. The camera upload is temporarily staged on `sensitive_documents`; the website controller validates and stores the final file through `MediaLifecycle` and sets `shipments.delivery_proof_file`. Committed submissions remove staging files. The existing website proof controller can serve the result with its ownership checks.
- Completion: the same website controller updates shipments, assignments, orders, notifications, COD collections and compensation. Native completion no longer creates the old fixed delivery credit. Failed OTP attempts are committed before returning the API error, preventing transaction rollback from resetting the shared attempt counter.

`delivery_verifications` is retained for app pickup/return audit metadata, not OTP verification. Legacy `mobile_rider_offers`, `mobile_rider_otp`, and `mobile_rider_proofs` remain as historical data and are no longer the source for new operations. Existing legacy earnings/remittance history has not been rewritten by this change. Custom app GCash verification remains unchanged.

Before deploying against an existing app database, run:

```powershell
php scripts/align-rider-website-records.php
php scripts/align-rider-website-records.php --apply
```

The first command is read-only. The second migrates eligible active legacy records in a transaction, copies existing proof files without deleting originals, preserves web evidence, and records an audit entry. The current local database dry run found no records requiring migration. SQL alone cannot copy uploaded proof images or encryption keys: app and web must use the same Laravel key and private storage, as well as the same MySQL database.

Verification: `php api/tests/rider-website-compatibility.php` uses rollback-only fixtures to check web offers, ownership, replay protection, shared OTP, lockouts, proof storage/readability through the website controller, exact COD collection, and absence of duplicate mobile OTP/proof/pay records. Existing hub/payout and buyer GCash checkout checks also pass.

This change aligns delivery verification and rider availability/offers. It does not claim that every other legacy rider feature or a separately hosted website deployment has been migrated or tested end to end.
