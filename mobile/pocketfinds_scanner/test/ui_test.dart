import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds_scanner/scanner_ui.dart';

void main() {
  Widget dashboard({
    String mode = 'Home',
    bool locked = false,
    bool uncertain = false,
    Map<String, dynamic>? parcel,
    List<Map<String, Object?>> recent = const [],
    VoidCallback? standalone,
    VoidCallback? pair,
    VoidCallback? home,
    ValueChanged<Map<String, dynamic>>? action,
  }) => MaterialApp(
    home: ScannerDashboard(
      user: const {
        'name': 'Test Staff',
        'company': 'PocketFinds Logistics',
        'role': 'hub_staff',
      },
      parcel: parcel,
      recent: recent,
      mode: mode,
      code: '',
      message: '',
      locked: locked,
      online: true,
      syncing: locked,
      uncertainAction: uncertain,
      retryRelay: false,
      pendingActionId: uncertain ? 'receive' : null,
      pending: 0,
      lastSync: null,
      onLogout: () {},
      onStandalone: standalone ?? () {},
      onPair: pair ?? () {},
      onScan: () {},
      onLookup: () {},
      onRelay: () {},
      onHome: home ?? () {},
      onSync: () {},
      onAction: action ?? (_) {},
    ),
  );
  testWidgets(
    'home explains both modes and sends choices to their own callbacks',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var standalone = 0, pair = 0;
      await tester.pumpWidget(
        dashboard(standalone: () => standalone++, pair: () => pair++),
      );
      expect(find.text('Standalone Scanner'), findsOneWidget);
      await tester.tap(find.text('Scan on phone'));
      expect(standalone, 1);
      expect(pair, 0);
      await tester.scrollUntilVisible(
        find.text('Connect to computer'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Connect to computer'));
      expect(pair, 1);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('busy home prevents overlapping mode changes', (tester) async {
    await tester.pumpWidget(dashboard(locked: true));
    expect(
      tester
          .widget<FilledButton>(
            find.ancestor(
              of: find.text('Scan on phone'),
              matching: find.byWidgetPredicate((w) => w is FilledButton),
            ),
          )
          .onPressed,
      isNull,
    );
    expect(find.byType(LinearProgressIndicator), findsOneWidget);
  });
  testWidgets(
    'standalone parcel actions retain confirmation callback and pending guard',
    (tester) async {
      var called = 0;
      final parcel = {
        'parcel': {
          'tracking_number': 'PF-123',
          'status_label': 'Picked up',
          'origin_hub': 'Pila',
          'destination_hub': 'Cavinti',
        },
        'actions': [
          {'id': 'receive', 'label': 'Receive and sort'},
        ],
      };
      await tester.pumpWidget(
        dashboard(
          mode: 'Standalone',
          parcel: parcel,
          uncertain: true,
          action: (_) => called++,
        ),
      );
      expect(
        tester
            .widget<FilledButton>(
              find.ancestor(
                of: find.text('Open scanner'),
                matching: find.byWidgetPredicate((w) => w is FilledButton),
              ),
            )
            .onPressed,
        isNull,
      );
      await tester.scrollUntilVisible(
        find.text('Retry same operation'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Retry same operation'));
      expect(called, 1);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('paired screen explains that computer confirms actions', (
    tester,
  ) async {
    await tester.pumpWidget(dashboard(mode: 'Paired'));
    expect(find.text('Your computer handles the parcel'), findsOneWidget);
    expect(find.text('Scan and send'), findsOneWidget);
    expect(
      find.textContaining('review and confirm the next action'),
      findsOneWidget,
    );
  });
  testWidgets(
    'paired mode offers explicit disconnect instead of silently changing modes',
    (tester) async {
      var disconnects = 0;
      await tester.pumpWidget(
        dashboard(mode: 'Paired', home: () => disconnects++),
      );
      final button = find.text('Disconnect from computer');
      await tester.scrollUntilVisible(
        button,
        250,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(button);
      expect(disconnects, 1);
      expect(find.text('Choose another mode'), findsNothing);
      await tester.pumpWidget(
        dashboard(mode: 'Paired', locked: true, home: () => disconnects++),
      );
      await tester.scrollUntilVisible(
        button,
        250,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(button);
      expect(disconnects, 1);
    },
  );
  testWidgets('login uses configured server without an address setup field', (
    tester,
  ) async {
    final email = TextEditingController(), password = TextEditingController();
    addTearDown(() {
      email.dispose();
      password.dispose();
    });
    await tester.pumpWidget(
      MaterialApp(
        home: ScannerLogin(
          email: email,
          password: password,
          busy: false,
          enabled: true,
          message: '',
          onLogin: () {},
        ),
      ),
    );
    expect(find.text('Welcome back'), findsOneWidget);
    expect(find.text('Email address'), findsOneWidget);
    expect(find.text('Server API address'), findsNothing);
    expect(find.text('Server connection'), findsNothing);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'connection failure offers Retry without exposing server settings',
    (tester) async {
      final email = TextEditingController(), password = TextEditingController();
      addTearDown(() {
        email.dispose();
        password.dispose();
      });
      var retries = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: ScannerLogin(
            email: email,
            password: password,
            busy: false,
            enabled: true,
            message: 'Unable to connect to PocketFinds.',
            onLogin: () {},
            onRetry: () => retries++,
          ),
        ),
      );
      await tester.scrollUntilVisible(
        find.text('Retry connection'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Retry connection'));
      expect(retries, 1);
      expect(find.text('Server API address'), findsNothing);
    },
  );
}
