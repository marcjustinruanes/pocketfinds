# Mobile layout conventions

Login, registration and Rider use the existing PocketFinds colors, logo, routes and workflows. This pass changes presentation only.

- Page insets: 20 logical pixels for forms, 16 for dashboard lists. Cards use 16 padding and 12 corner radius. Related controls use 8/12 gaps; sections use 20/24.
- Buttons have a 48 minimum height, inputs 52, and controls can grow with text. `AppDimensions` and the shared theme own these minimums.
- `HeroAuthScaffold` keeps the app name in its toolbar. Its optional bottom action area sits above keyboard insets; forms scroll and dismiss the keyboard on drag. Registration actions stay outside the scrolling form.
- `AdaptiveCardGrid` reflows cards according to usable width and text size without imposing a fixed card height. Short form fields stack with large text.
- `RiderDashboardHeader` keeps notifications and messages visible and groups assigned deliveries/pickups under Delivery tools. Availability occupies a separate row. Current task precedes summary statistics.
- Delivery cards wrap badges and display up to two address lines. Full addresses remain in delivery details. Bottom navigation uses equal-width destinations, safe-area padding and extra height for enlarged labels.

## Verification

`mobile_layout_consistency_test.dart` exercises login (including keyboard insets), buyer/rider registration, rider header, statistic grid, delivery cards, delivery details and bottom navigation at widths 320, 393 and 430, with text scales 1.0 and 1.5. Companion tests cover authentication navigation, registration completion, buttons, progress and rider workflows. Pixel 6 is also used for visual inspection.
