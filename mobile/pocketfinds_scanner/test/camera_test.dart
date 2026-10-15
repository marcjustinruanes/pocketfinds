import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds_scanner/camera_screen.dart';

void main() {
  for (final mode in ['Standalone Scanner', 'Paired — send to computer']) {
    testWidgets('$mode shares camera input and ignores repeated detections', (
      tester,
    ) async {
      String? result;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () async {
                  result = await Navigator.push<String>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => CameraScreen(
                        title: mode,
                        previewBuilder: (onCode) => TextButton(
                          onPressed: () {
                            onCode(' TRACK ');
                            onCode('OTHER');
                          },
                          child: const Text('Mock detection'),
                        ),
                      ),
                    ),
                  );
                },
                child: const Text('Open scanner'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open scanner'));
      await tester.pumpAndSettle();
      expect(find.text(mode), findsOneWidget);
      await tester.tap(find.text('Mock detection'));
      await tester.pumpAndSettle();
      expect(result, 'TRACK');
    });
  }
  testWidgets('manual fallback works without a camera', (tester) async {
    String? result;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async {
                result = await Navigator.push<String>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CameraScreen(
                      title: 'Standalone Scanner',
                      previewBuilder: (_) => const Text('No camera'),
                    ),
                  ),
                );
              },
              child: const Text('Open'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'MANUAL-TRACK');
    await tester.tap(find.text('Use entered code'));
    await tester.pumpAndSettle();
    expect(result, 'MANUAL-TRACK');
  });
}
