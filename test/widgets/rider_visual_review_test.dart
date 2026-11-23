import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:pocketfinds/screens/rider/dashboard_tab.dart';
import 'package:pocketfinds/screens/rider/earnings_tab.dart';
import 'package:pocketfinds/screens/rider/history_tab.dart';
import 'package:pocketfinds/screens/rider/rider_profile_tab.dart';
import 'package:pocketfinds/screens/rider/tasks_tab.dart';
import 'package:pocketfinds/screens/rider/task_detail_screen.dart';
import 'package:pocketfinds/services/conversation_service.dart';
import 'package:pocketfinds/theme/app_theme.dart';

import 'rider_redesign_test.dart' as fixtures;

void main() {
  testWidgets('render rider screens for visual review', (tester) async {
    GoogleFonts.config.allowRuntimeFetching = false;
    await tester.runAsync(() async {
      final icons = FontLoader('MaterialIcons')
        ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
      await icons.load();
      for (final (weight, file) in [
        (FontWeight.w400, 'Regular'),
        (FontWeight.w500, 'Medium'),
        (FontWeight.w600, 'SemiBold'),
        (FontWeight.w700, 'Bold'),
      ]) {
        final font = FontLoader(
          GoogleFonts.inter(fontWeight: weight).fontFamily!,
        )..addFont(rootBundle.load('assets/brand/Inter-$file.ttf'));
        await font.load();
      }
    });
    tester.view.physicalSize = const Size(400, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final shipments = fixtures.Shipments();
    final pages = <String, Widget>{
      'home': RiderDashboardTab(
        user: fixtures.rider,
        shipmentService: shipments,
        conversationService: const EmptyConversationService(),
        moduleService: fixtures.Modules(),
        client: fixtures.fixtureClient(),
        onOpenTasks: () {},
        onOpenEarnings: () {},
      ),
      'tasks': TasksTab(user: fixtures.rider, service: shipments),
      'delivery': TaskDetailScreen(
        shipment: fixtures.parcel,
        riderId: 42,
        deliveryService: fixtures.Delivery(),
        shipmentService: shipments,
      ),
      'earnings': EarningsTab(service: fixtures.Modules()),
      'history': RiderHistoryTab(user: fixtures.rider, service: shipments),
      'account': RiderProfileTab(
        user: fixtures.rider,
        shipmentService: shipments,
        client: fixtures.fixtureClient(),
      ),
    };
    for (final page in pages.entries) {
      final boundary = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: boundary,
          child: MaterialApp(
            theme: AppTheme.current,
            home: page.value,
            debugShowCheckedModeBanner: false,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.runAsync(() async {
        final renderer =
            boundary.currentContext!.findRenderObject()!
                as RenderRepaintBoundary;
        final picture = await renderer.toImage();
        final bytes = await picture.toByteData(format: ui.ImageByteFormat.png);
        final file = File('.tmp/rider-previews/${page.key}.png');
        await file.parent.create(recursive: true);
        await file.writeAsBytes(bytes!.buffer.asUint8List());
        picture.dispose();
      });
      await tester.pumpWidget(const SizedBox.shrink());
    }
  }, skip: !const bool.fromEnvironment('RIDER_VISUAL_REVIEW'));
}
