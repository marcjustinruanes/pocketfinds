import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/widgets/order_progress_timeline.dart';

void main() {
  const stages = ['to_ship', 'in_transit', 'completed'];
  const labels = ['To Ship', 'In Transit', 'Completed'];

  testWidgets('renders all stage labels', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: OrderProgressTimeline(
            stages: stages,
            stageLabels: labels,
            currentStatus: 'in_transit',
          ),
        ),
      ),
    );

    for (final label in labels) {
      expect(find.text(label), findsOneWidget);
    }
  });

  testWidgets('shows a check mark for every completed-or-current stage', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: OrderProgressTimeline(
            stages: stages,
            stageLabels: labels,
            currentStatus: 'in_transit',
          ),
        ),
      ),
    );

    // to_ship and in_transit are done/current; completed is not reached yet.
    expect(find.byIcon(Icons.check), findsNWidgets(2));
  });

  testWidgets('cancelled renders a distinct terminal message, no stepper', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: OrderProgressTimeline(
            stages: stages,
            stageLabels: labels,
            currentStatus: 'cancelled',
            cancelled: true,
          ),
        ),
      ),
    );

    expect(find.text('This order was cancelled.'), findsOneWidget);
    for (final label in labels) {
      expect(find.text(label), findsNothing);
    }
  });
}
