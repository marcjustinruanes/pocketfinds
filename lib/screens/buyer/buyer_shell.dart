import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../widgets/app_bottom_nav.dart';
import 'cart_tab.dart';
import 'categories_tab.dart';
import 'home_tab.dart';
import 'orders_tab.dart';
import 'profile_tab.dart';

/// The signed-in buyer's mobile shell: Home | Categories | Cart | Orders |
/// Profile, matching the website's buyer navigation but as a bottom nav bar
/// instead of a sidebar. Tabs are kept alive via IndexedStack so switching
/// back doesn't re-fetch everything.
class BuyerShell extends StatefulWidget {
  final AppUser user;

  const BuyerShell({super.key, required this.user});

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final tabs = [
      BuyerHomeTab(user: widget.user),
      CategoriesTab(user: widget.user),
      CartTab(user: widget.user),
      OrdersTab(user: widget.user),
      ProfileTab(user: widget.user),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: tabs),
      bottomNavigationBar: AppBottomNav(
        currentIndex: _index,
        onTap: (index) => setState(() => _index = index),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home_outlined), activeIcon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.grid_view_outlined), activeIcon: Icon(Icons.grid_view), label: 'Categories'),
          BottomNavigationBarItem(icon: Icon(Icons.shopping_cart_outlined), activeIcon: Icon(Icons.shopping_cart), label: 'Cart'),
          BottomNavigationBarItem(icon: Icon(Icons.receipt_long_outlined), activeIcon: Icon(Icons.receipt_long), label: 'Orders'),
          BottomNavigationBarItem(icon: Icon(Icons.person_outline), activeIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}
