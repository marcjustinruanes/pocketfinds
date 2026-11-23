import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../widgets/animated_indexed_stack.dart';
import '../../widgets/app_bottom_nav.dart';
import 'home_tab.dart';
import 'orders_tab.dart';
import 'notifications_screen.dart';
import 'profile_tab.dart';

/// Native mobile presentation; Laravel remains the business source of truth.
class BuyerShell extends StatefulWidget {
  final AppUser user;
  const BuyerShell({super.key, required this.user});
  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  int _index = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
    body: AnimatedIndexedStack(
      index: _index,
      children: [
        BuyerHomeTab(user: widget.user),
        OrdersTab(
          user: widget.user,
          onContinueShopping: () => setState(() => _index = 0),
        ),
        const NotificationsScreen(),
        ProfileTab(user: widget.user),
      ],
    ),
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
          icon: Icon(Icons.receipt_long_outlined),
          activeIcon: Icon(Icons.receipt_long),
          label: 'Orders',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.notifications_none),
          activeIcon: Icon(Icons.notifications),
          label: 'Notifications',
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
