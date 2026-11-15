import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/widgets/step_progress_header.dart';

void main() {
  const buyerLabels = [
    'ID & Selfie',
    'Personal',
    'Contact',
    'Address',
    'Account',
  ];
  const riderLabels = [
    'ID & Selfie',
    'Personal',
    'Contact',
    'Address',
    'Vehicle',
    'License',
    'Account',
  ];

  Future<void> pump(WidgetTester tester, List<String> labels, int currentStep) {
    return tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StepProgressHeader(
            stepLabels: labels,
            currentStep: currentStep,
          ),
        ),
      ),
    );
  }

  testWidgets('renders 5 step labels for the buyer flow', (tester) async {
    await pump(tester, buyerLabels, 0);
    for (final label in buyerLabels) {
      expect(find.text(label), findsOneWidget);
    }
    expect(find.text('Vehicle'), findsNothing);
    expect(find.text('License'), findsNothing);
  });

  testWidgets('renders all 7 step labels for the rider flow', (tester) async {
    await pump(tester, riderLabels, 0);
    for (final label in riderLabels) {
      expect(find.text(label), findsOneWidget);
    }
  });

  testWidgets('shows a check mark for completed steps', (tester) async {
    await pump(tester, riderLabels, 3); // steps 0-2 done, step 3 current
    expect(find.byIcon(Icons.check), findsNWidgets(3));
  });
}
