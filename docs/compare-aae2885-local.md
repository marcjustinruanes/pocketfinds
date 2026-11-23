# Comparison: aae2885 and the local workspace

Target commit: `aae28851df0e8275745d78424a71bcd61a6081bf` ? `update`, November 16, 2026, 07:21 +08:00.
Local website baseline: `8ded30a`. The target is its direct child. This comparison includes local uncommitted tracked changes, not just HEAD.

93 tracked website files differ. The target commit introduces changes in 79 files relative to the local website baseline. There are also 16 locally modified tracked website files.

## Website updates present in the target commit

- Revised admin workspace navigation, tables, filters, registration review, settings and account screens.
- A dedicated platform vouchers page; the local version keeps voucher management within Products.
- Admin messaging uses the shared conversation workspace and adds a message-report route and structured polling response.
- Password-change links and Google account-email verification are extended to admin, logistics and rider website accounts.
- Revised logistics assignment details/loading/error UI, plus a hub-staff transfer-request modal with a destination-hub selector.
- Revised logistics account/team views and registration-style editing for rider and hub-staff accounts.
- Admin AI and seller AI review receive feature flags that default to disabled unless enabled in configuration.
- Barangay coverage editor routes/navigation are removed from the website interface. The underlying RiderDeliveryOffers service in this commit still applies barangay eligibility and 60-second offer expiry.

## Fixes present only in the local website working files

- Assignment eligibility uses hub municipality/province without the barangay-route restriction.
- Rider assignment offers have no confirmation deadline; expiration checks and countdown copy are removed from the relevant flows.
- Buyer messaging includes rider threads, opening/polling/replying to those threads rather than limiting buyers to sellers.
- RiderController supports the mobile adapter?s server-set photo-confirmation attribute while retaining the website?s existing verification path.
- Additional local regression tests and an untracked migration allow a fixed-fee shipping obligation without a regional rate ID.

## Native mobile application

The target commit does not contain `pocketfinds-app/`. That folder is a separate local Git repository on the mobile branch. Its native rider redesign, API adapters, camera/photo completion work, buyer web bridge, messaging fixes, assigned-hub information and regression tests are therefore not included in the target commit.

The target does contain `mobile/pocketfinds_scanner/`, which is a different application. Its tracked files are unchanged versus this local website working tree.

## Complete tracked website file list

Direction below is target commit ? current local files. `M` means different content; `D` means a target-tracked file is absent locally. Untracked local files are not included in Git diff counts.

| Status | File |
| --- | --- |
| M | `app/Http/Controllers/AdminAssistantController.php` |
| M | `app/Http/Controllers/AdminController.php` |
| M | `app/Http/Controllers/BuyerController.php` |
| M | `app/Http/Controllers/CommerceController.php` |
| M | `app/Http/Controllers/HubTransferController.php` |
| M | `app/Http/Controllers/LogisticsController.php` |
| M | `app/Http/Controllers/LogisticsManagementController.php` |
| M | `app/Http/Controllers/PasswordResetController.php` |
| M | `app/Http/Controllers/RiderController.php` |
| M | `app/Http/Controllers/RiderCoverageController.php` |
| M | `app/Http/Controllers/SocialAuthController.php` |
| D | `app/Http/Middleware/AdminAiFeature.php` |
| M | `app/Services/AssignmentReassignment.php` |
| M | `app/Services/RiderDeliveryOffers.php` |
| M | `app/Support/LogisticsWorkspace.php` |
| D | `app/Support/PlatformWorkspace.php` |
| M | `bootstrap/app.php` |
| M | `config/features.php` |
| M | `public/css/assignment-workspace.css` |
| D | `public/css/logistics-team.css` |
| M | `public/css/logistics-ui.css` |
| D | `public/css/personnel-edit.css` |
| D | `public/css/platform-accounts.css` |
| D | `public/css/platform-settings.css` |
| D | `public/css/platform-workspace.css` |
| M | `public/js/admin.js` |
| M | `public/js/assignment-workspace.js` |
| M | `public/js/managed-registration.js` |
| D | `public/js/personnel-edit.js` |
| D | `public/js/personnel-registration-edit.js` |
| D | `public/js/platform-table-filters.js` |
| M | `resources/views/account/partials/conversation-workspace.blade.php` |
| M | `resources/views/admin/account.blade.php` |
| M | `resources/views/admin/ai-workspace.blade.php` |
| M | `resources/views/admin/announcements.blade.php` |
| M | `resources/views/admin/complaints.blade.php` |
| M | `resources/views/admin/dashboard.blade.php` |
| M | `resources/views/admin/faqs.blade.php` |
| M | `resources/views/admin/layout.blade.php` |
| M | `resources/views/admin/messages.blade.php` |
| M | `resources/views/admin/partials/ai-link.blade.php` |
| D | `resources/views/admin/partials/column-filter.blade.php` |
| D | `resources/views/admin/partials/returns-table.blade.php` |
| D | `resources/views/admin/partials/review-summary.blade.php` |
| M | `resources/views/admin/partials/sidebar.blade.php` |
| M | `resources/views/admin/partials/topbar.blade.php` |
| M | `resources/views/admin/partials/user-profile-body.blade.php` |
| D | `resources/views/admin/partials/workspace-tabs.blade.php` |
| M | `resources/views/admin/policies.blade.php` |
| M | `resources/views/admin/products.blade.php` |
| M | `resources/views/admin/registrations.blade.php` |
| M | `resources/views/admin/settings.blade.php` |
| M | `resources/views/admin/update-requests.blade.php` |
| M | `resources/views/admin/users.blade.php` |
| M | `resources/views/admin/vehicles.blade.php` |
| D | `resources/views/admin/vouchers.blade.php` |
| M | `resources/views/auth/register-logistics-staff.blade.php` |
| M | `resources/views/auth/register-rider.blade.php` |
| M | `resources/views/buyer/partials/assistant.blade.php` |
| M | `resources/views/buyer/partials/messages-panel.blade.php` |
| M | `resources/views/components/account-profile-card.blade.php` |
| M | `resources/views/components/hero-crop.blade.php` |
| M | `resources/views/gcash/index.blade.php` |
| M | `resources/views/gcash/partials/admin-purchases.blade.php` |
| M | `resources/views/logistics/account-edit.blade.php` |
| M | `resources/views/logistics/account.blade.php` |
| M | `resources/views/logistics/assign.blade.php` |
| M | `resources/views/logistics/hub-dashboard.blade.php` |
| M | `resources/views/logistics/partials/account-form.blade.php` |
| M | `resources/views/logistics/partials/assignment-parcel-details.blade.php` |
| M | `resources/views/logistics/partials/assignment-review-details.blade.php` |
| D | `resources/views/logistics/partials/personnel-registration-edit.blade.php` |
| M | `resources/views/logistics/partials/sidebar.blade.php` |
| D | `resources/views/logistics/partials/team-status-filter.blade.php` |
| M | `resources/views/logistics/partials/workspace-tabs.blade.php` |
| M | `resources/views/logistics/requests.blade.php` |
| M | `resources/views/logistics/rider-coverage.blade.php` |
| M | `resources/views/logistics/riders.blade.php` |
| M | `resources/views/logistics/staff.blade.php` |
| M | `resources/views/logistics/transfers.blade.php` |
| M | `resources/views/rider/account.blade.php` |
| M | `resources/views/rider/dashboard.blade.php` |
| M | `resources/views/rider/pickup-requests.blade.php` |
| M | `resources/views/rider/requests.blade.php` |
| M | `resources/views/seller/messages-embedded-layout.blade.php` |
| M | `routes/web.php` |
| M | `tests/Feature/AccountWorkspaceTest.php` |
| D | `tests/Feature/AdminAiFeatureTest.php` |
| M | `tests/Feature/AdminGcashFlowTest.php` |
| M | `tests/Feature/LogisticsWorkspaceTest.php` |
| M | `tests/Feature/PersonnelOnboardingTest.php` |
| M | `tests/Feature/RiderAssignmentWorkflowTest.php` |
| M | `tests/Feature/RiderBarangayCoverageTest.php` |

This is a source-code comparison. It does not compare live database records or deployed builds. Fetching the target commit did not switch branches, pull changes, or alter application files.
