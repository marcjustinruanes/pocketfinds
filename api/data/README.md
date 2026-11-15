# Registration postal records

`registration-postal-codes.json` is a snapshot of https://psgc.cloud/api/cities and https://psgc.cloud/api/municipalities retrieved on 2026-11-14. Only `code`, `name`, and `zip_code` are retained.

The mobile registration service matches the municipality PSGC code and normalized name before returning a four-digit ZIP. Unknown or missing ZIP codes remain optional manual entries. The same service handles lookup and final submission, without relying on a live provider call. Refresh both datasets together when updating Philippine address records.
