import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/models/product.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/theme/theme_controller.dart';
import 'package:pocketfinds/widgets/app_search_bar.dart';
import 'package:pocketfinds/widgets/product_grid.dart';
import 'package:pocketfinds/widgets/storefront_hero.dart';
import 'package:pocketfinds/widgets/flash_sale_banner.dart';

void main() {
  const product = Product(
    id: 'test',
    name: 'Everyday product with a long name',
    price: 1234567,
    discountPrice: 999999,
    sellerId: 1,
    sellerName: 'Local shop',
    categoryName: 'Home',
    stock: 2,
    sellerLocation: 'Quezon City, Metro Manila',
  );

  for (final width in [320.0, 800.0]) {
    testWidgets('storefront fits a $width screen and large text', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var browsed = false;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.current,
          home: MediaQuery(
            data: MediaQueryData(
              size: Size(width, 1200),
              textScaler: const TextScaler.linear(1.3),
            ),
            child: Scaffold(
              body: SingleChildScrollView(
                child: Column(
                  children: [
                    StorefrontHero(onBrowse: () => browsed = true),
                    FlashSaleBanner(products: const [product], onTap: (_) {}),
                    ProductGrid(
                      products: const [product, product],
                      onTap: (_) {},
                      onAddToCart: (_) {},
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Browse Products'));
      expect(browsed, isTrue);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('search retains edits through parent rebuilds', (tester) async {
    Widget search() => MaterialApp(
      home: Scaffold(
        body: AppSearchBar(initialValue: 'home', onChanged: (_) {}),
      ),
    );
    await tester.pumpWidget(search());
    await tester.enterText(find.byType(TextField), 'garden');
    await tester.pumpWidget(search());
    expect(find.text('garden'), findsOneWidget);
  });

  testWidgets('primary action keeps white text in dark mode', (tester) async {
    ThemeController.isDarkNotifier.value = true;
    addTearDown(() => ThemeController.isDarkNotifier.value = false);
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.current,
        home: Scaffold(
          body: ElevatedButton(onPressed: () {}, child: const Text('Checkout')),
        ),
      ),
    );
    final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
    final color = Theme.of(tester.element(find.byWidget(button)))
        .elevatedButtonTheme
        .style!
        .foregroundColor!
        .resolve({});
    expect(color, Colors.white);
  });
}
