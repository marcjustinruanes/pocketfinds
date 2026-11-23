import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/widgets/segmented_control.dart';

void main() {
  const options = [
    SegmentedControlOption(0, 'Available'),
    SegmentedControlOption(1, 'My Deliveries'),
    SegmentedControlOption(2, 'History'),
  ];

  testWidgets('renders every option label', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SegmentedControl<int>(
            options: options,
            value: 0,
            onChanged: (_) {},
          ),
        ),
      ),
    );

    for (final option in options) {
      expect(find.text(option.label), findsOneWidget);
    }
  });

  testWidgets('tapping a segment calls onChanged with its value', (
    tester,
  ) async {
    int? selected;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SegmentedControl<int>(
            options: options,
            value: 0,
            onChanged: (v) => selected = v,
          ),
        ),
      ),
    );

    await tester.tap(find.text('History'));
    expect(selected, 2);
  });
}
