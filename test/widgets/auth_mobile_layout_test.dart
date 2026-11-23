import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:pocketfinds/widgets/hero_auth_scaffold.dart';
import 'package:pocketfinds/widgets/step_progress_header.dart';

void main() {
  setUp(() => GoogleFonts.config.allowRuntimeFetching = false);
  for (final width in [320.0, 430.0]) {
    testWidgets(
      'auth panel and registration steps fit $width with large text',
      (tester) async {
        tester.view.physicalSize = Size(width, 800);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(
            home: MediaQuery(
              data: MediaQueryData(
                size: Size(width, 800),
                textScaler: const TextScaler.linear(1.5),
              ),
              child: const HeroAuthScaffold(
                child: Column(
                  children: [
                    StepProgressHeader(
                      stepLabels: [
                        'ID & Selfie',
                        'Personal',
                        'Contact',
                        'Address',
                        'Account',
                      ],
                      currentStep: 4,
                    ),
                    SizedBox(height: 24),
                    Text('Registration form'),
                  ],
                ),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.byType(StepProgressHeader));
        await tester.pumpAndSettle();
        final bounds = tester.getRect(find.byType(StepProgressHeader));
        expect(bounds.left, greaterThanOrEqualTo(0));
        expect(bounds.right, lessThanOrEqualTo(width));
        expect(find.text('Everything you need, in one place.'), findsNothing);
        expect(
          tester.getTopLeft(find.byType(StepProgressHeader)).dy,
          lessThan(300),
        );
        expect(tester.takeException(), isNull);
      },
    );
  }
}
