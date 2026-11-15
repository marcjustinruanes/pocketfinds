import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'brand_wordmark.dart';
import 'message_icon.dart';

/// Compact, consistently sized rider tools; secondary destinations stay in a menu.
class RiderDashboardHeader extends StatelessWidget {
  final VoidCallback onNotifications, onMessages, onAssigned, onPickup;
  final int unreadCount;
  const RiderDashboardHeader({
    super.key,
    required this.onNotifications,
    required this.onMessages,
    required this.onAssigned,
    required this.onPickup,
    this.unreadCount = 0,
  });

  @override
  Widget build(BuildContext context) => SliverAppBar(
    pinned: true,
    floating: true,
    backgroundColor: AppColors.white,
    automaticallyImplyLeading: false,
    titleSpacing: 16,
    title: const FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: BrandWordmark(),
    ),
    actions: [
      IconButton(
        tooltip: 'Notifications',
        onPressed: onNotifications,
        icon: const Icon(Icons.notifications_outlined),
      ),
      IconButton(
        tooltip: 'Messages',
        onPressed: onMessages,
        icon: Badge(
          isLabelVisible: unreadCount > 0,
          label: Text(unreadCount > 9 ? '9+' : '$unreadCount'),
          child: MessageIcon(size: 22, color: AppColors.textPrimary),
        ),
      ),
      PopupMenuButton<String>(
        tooltip: 'Delivery tools',
        onSelected: (value) => value == 'assigned' ? onAssigned() : onPickup(),
        itemBuilder: (_) => const [
          PopupMenuItem(value: 'assigned', child: Text('Assigned deliveries')),
          PopupMenuItem(value: 'pickup', child: Text('Seller pickups')),
        ],
      ),
      const SizedBox(width: 4),
    ],
  );
}
