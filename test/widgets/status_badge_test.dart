import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/theme/app_colors.dart';
import 'package:pocketfinds/widgets/status_badge.dart';

void main() {
  Future<void> pump(WidgetTester tester, String status, String label) {
    return tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StatusBadge(status: status, label: label),
        ),
      ),
    );
  }

  testWidgets('renders the given label', (tester) async {
    await pump(tester, 'to_ship', 'To Ship');
    expect(find.text('To Ship'), findsOneWidget);
  });

  testWidgets('colors "completed" as success', (tester) async {
    await pump(tester, 'completed', 'Completed');
    final container = tester.widget<Container>(find.byType(Container));
    final decoration = container.decoration as BoxDecoration;
    expect(decoration.color, AppColors.successSoft);
  });

  testWidgets('colors "cancelled" as danger', (tester) async {
    await pump(tester, 'cancelled', 'Cancelled');
    final container = tester.widget<Container>(find.byType(Container));
    final decoration = container.decoration as BoxDecoration;
    expect(decoration.color, AppColors.dangerSoft);
  });
}
