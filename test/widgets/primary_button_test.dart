import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/theme/app_theme.dart';
import 'package:pocketfinds/widgets/primary_button.dart';

void main() {
  Widget wrap(Widget child) => MaterialApp(
    theme: AppTheme.current,
    home: Scaffold(body: child),
  );

  testWidgets('PrimaryButton fires onPressed when tapped', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      wrap(PrimaryButton(label: 'Go', onPressed: () => tapped = true)),
    );

    await tester.tap(find.text('Go'));
    expect(tapped, isTrue);
  });

  testWidgets(
    'PrimaryButton shows a spinner instead of the label while loading',
    (tester) async {
      await tester.pumpWidget(
        wrap(PrimaryButton(label: 'Go', loading: true, onPressed: () {})),
      );

      expect(find.text('Go'), findsNothing);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    },
  );

  testWidgets(
    'PrimaryButton is disabled while loading, even with onPressed set',
    (tester) async {
      var tapped = false;
      await tester.pumpWidget(
        wrap(
          PrimaryButton(
            label: 'Go',
            loading: true,
            onPressed: () => tapped = true,
          ),
        ),
      );

      final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      expect(button.onPressed, isNull);
      expect(tapped, isFalse);
    },
  );

  testWidgets('PrimaryButton is disabled when onPressed is null', (
    tester,
  ) async {
    await tester.pumpWidget(
      wrap(const PrimaryButton(label: 'Go', onPressed: null)),
    );

    final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
    expect(button.onPressed, isNull);
  });

  testWidgets('SecondaryButton fires onPressed when tapped', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      wrap(SecondaryButton(label: 'Go', onPressed: () => tapped = true)),
    );

    await tester.tap(find.text('Go'));
    expect(tapped, isTrue);
  });
}
