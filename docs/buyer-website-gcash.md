Buyer screens use the native Flutter mobile UI. The buyer shell, categories, favorites, profile, products, cart, checkout, payment and order tracking do not open the website UI.

Laravel website services remain the source of truth. Native checkout requests set website_checkout and website_payment; NativeBuyerCommerce calls BuyerCommerce for quotes and CommerceController for checkout, retaining server validation, stock reservation, discounts and checkout idempotency.

New GCash orders use gcash_purchases, gcash_proofs and gcash_purchase_history. WebsitePaymentService adapts these records for the existing native GCashPaymentScreen. Receipt submission calls GcashPurchaseController, so approval, rejection, correction and duplicate protection remain shared with the website. Buyer reference entry is not added to this website workflow. Legacy platform_payment orders retain their original payment service and records.

Receiving details come from the website settings and are snapshotted per purchase. The native payment screen uses that snapshot. api/config/gcash.php remains for legacy app payment records. The website does not currently configure a QR code; native screens display the configured account details without inventing one.

App Settings, the PocketFinds logo and existing native styling are retained. The shopping assistant remains excluded. Incoming notifications and product/cart checkout links open native screens.

Validation: api/tests/native-buyer-commerce.php checks shared pricing, order creation, stock reservation, checkout retries, receipt submission/retries, rejection/correction, admin verification and ownership using rolled-back fixtures. test/widgets/native_website_payment_test.dart checks native account/receipt screens and the payment request contract.

App and website must share the deployed backend/database, Laravel key and receipt storage for live synchronization.
