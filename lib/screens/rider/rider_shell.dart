import 'package:flutter/material.dart';

import 'dart:async';

import '../../services/rider_service.dart';

import '../../models/app_user.dart';
import '../../widgets/animated_indexed_stack.dart';
import '../../widgets/app_bottom_nav.dart';
import 'dashboard_tab.dart';
import 'earnings_tab.dart';
import 'history_tab.dart';
import 'rider_profile_tab.dart';
import 'tasks_tab.dart';

/// The signed-in Courier/Rider's mobile shell: Home | Tasks | Earnings |
/// History | Profile. "Tasks" covers both pickup-from-seller and
/// deliver-to-customer work — Courier and Rider are one role, one account
/// (see the redesign brief), never separate nav sections.
class RiderShell extends StatefulWidget {
  final AppUser user;

  const RiderShell({super.key, required this.user});

  @override
  State<RiderShell> createState() => _RiderShellState();
}

class _RiderShellState extends State<RiderShell> with WidgetsBindingObserver {
  int _index = 0;
  Timer? _availabilityPoll;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    unawaited(_refreshAvailability());
    _availabilityPoll = Timer.periodic(const Duration(seconds: 30), (_) {
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        unawaited(_refreshAvailability());
      }
    });
  }

  Future<void> _refreshAvailability() async {
    try {
      await RiderService.refreshAvailability();
    } catch (_) {}
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(_refreshAvailability());
  }

  @override
  void dispose() {
    _availabilityPoll?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tabs = [
      RiderDashboardTab(
        user: widget.user,
        onOpenTasks: () => setState(() => _index = 1),
        onOpenEarnings: () => setState(() => _index = 2),
      ),
      TasksTab(user: widget.user),
      const EarningsTab(),
      RiderHistoryTab(user: widget.user),
      RiderProfileTab(user: widget.user),
    ];

    return Scaffold(
      body: AnimatedIndexedStack(index: _index, children: tabs),
      bottomNavigationBar: AppBottomNav(
        currentIndex: _index,
        onTap: (index) => setState(() => _index = index),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home),
            label: 'Home',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.assignment_outlined),
            activeIcon: Icon(Icons.assignment),
            label: 'Tasks',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.payments_outlined),
            activeIcon: Icon(Icons.payments),
            label: 'Earnings',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.history_outlined),
            activeIcon: Icon(Icons.history),
            label: 'History',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}
