import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pocketfinds/services/delivery_service.dart';

class _View extends StatefulWidget {
  final VoidCallback refreshed;
  const _View(this.refreshed);
  @override
  State<_View> createState() => _ViewState();
}

class _ViewState extends State<_View>
    with WidgetsBindingObserver, DeliveryRefresh<_View> {
  @override
  Future<void> refreshDeliveryData() async => widget.refreshed();
  @override
  Widget build(BuildContext context) => const SizedBox();
}

void main() {
  testWidgets('hidden delivery views stop polling and refresh when reopened', (
    tester,
  ) async {
    var refreshes = 0;
    final view = _View(() => refreshes++);
    Widget host(bool enabled) => MaterialApp(
      home: TickerMode(enabled: enabled, child: view),
    );
    await tester.pumpWidget(host(false));
    await tester.pump(const Duration(seconds: 21));
    expect(refreshes, 0);
    await tester.pumpWidget(host(true));
    await tester.pump();
    expect(refreshes, 1);
    await tester.pump(const Duration(seconds: 20));
    expect(refreshes, 2);
    await tester.pumpWidget(host(false));
    await tester.pump(const Duration(seconds: 21));
    expect(refreshes, 2);
    await tester.pumpWidget(const SizedBox());
  });
}
