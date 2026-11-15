# Native buyer checkout alignment

The native product and checkout screens reuse the pinned website runtime and Laravel controllers. `cart/add` now delegates to `BuyerController::cartAdd`, wrapped in the mobile request journal and database transaction. Quote and checkout continue through `BuyerCommerce` and `CommerceController`, including server pricing, stock, vouchers, COD and admin-reviewed GCash.

Website combination variations retain their named axes and exact SKU attributes. Buyers choose color and size separately; the app sends the matching website group/value, never an invented pair. Zero-stock and unconfigured combinations cannot be purchased. Older alternative SKU groups remain exclusive choices.

Product details no longer show voucher claim lists above the image. Flash offers come from website commerce records. Checkout displays seller names, thumbnails, shipping warnings and actionable submission errors, and requires an address and payment method. Quantities follow the website's 99-item limit.

Validation: `php api/tests/native-buyer-commerce.php` uses rollback-only fixtures for combination cart/quote/COD, retries, stock protection and the shared GCash approval/proof flow. Flutter product option and native checkout tests cover combination resolution, legacy choices and 320/412 px layouts.

## Native HTTP routing

Native buyer operations use `/buyer-api/*`. Website `/buyer/*` remains under its cookie/CSRF kernel; older native bearer requests to `/buyer/*` still route to the JSON adapter. This prevents checkout address requests from receiving the website's 419 HTML error. Failed initialization displays a retry action and reloads both addresses and payment methods.

Run `php api/tests/native-checkout-http.php` with the localhost API running to test actual routing, authentication, address/payment loading, quote serialization and stale-summary rejection. This test removes its committed temporary fixtures in `finally`.
