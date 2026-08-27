import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../widgets/app_bottom_nav.dart';
import 'dashboard_tab.dart';
import 'deliveries_tab.dart';
import 'history_tab.dart';
import 'rider_profile_tab.dart';

/// The signed-in rider's mobile shell: Dashboard | Deliveries | History |
/// Profile — same shell pattern as `BuyerShell`.
class RiderShell extends StatefulWidget {
  final AppUser user;

  const RiderShell({super.key, required this.user});

  @override
  State<RiderShell> createState() => _RiderShellState();
}

class _RiderShellState extends State<RiderShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final tabs = [
      RiderDashboardTab(user: widget.user),
      const DeliveriesTab(),
      const HistoryTab(),
      RiderProfileTab(user: widget.user),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: tabs),
      bottomNavigationBar: AppBottomNav(
        currentIndex: _index,
        onTap: (index) => setState(() => _index = index),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.dashboard_outlined), activeIcon: Icon(Icons.dashboard), label: 'Dashboard'),
          BottomNavigationBarItem(icon: Icon(Icons.local_shipping_outlined), activeIcon: Icon(Icons.local_shipping), label: 'Deliveries'),
          BottomNavigationBarItem(icon: Icon(Icons.history_outlined), activeIcon: Icon(Icons.history), label: 'History'),
          BottomNavigationBarItem(icon: Icon(Icons.person_outline), activeIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}
