import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/shop_profile.dart';
import '../../services/shop_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/skeleton.dart';
import '../../widgets/staggered_fade_in.dart';
import 'shop_screen.dart';

/// The real shops [user] follows (`shop_follows`) — Follow is a real,
/// existing feature (`ShopScreen`); this just gives it a dedicated list +
/// a profile entry point, with real unfollow feedback right in the list.
class FollowingScreen extends StatefulWidget {
  final AppUser user;

  const FollowingScreen({super.key, required this.user});

  @override
  State<FollowingScreen> createState() => _FollowingScreenState();
}

class _FollowingScreenState extends State<FollowingScreen> {
  final _shopService = ShopService();
  late Future<List<ShopProfile>> _future;
  final Set<int> _unfollowing = {};
  final Set<int> _unfollowed = {};

  @override
  void initState() {
    super.initState();
    _future = _shopService.fetchFollowedShops(widget.user.id);
  }

  Future<void> _refresh() async {
    setState(() {
      _unfollowed.clear();
      _future = _shopService.fetchFollowedShops(widget.user.id);
    });
    await _future;
  }

  Future<void> _unfollow(ShopProfile shop) async {
    setState(() => _unfollowing.add(shop.sellerId));
    try {
      await _shopService.unfollow(widget.user.id, shop.sellerId);
      if (!mounted) return;
      setState(() => _unfollowed.add(shop.sellerId));
      AppSnackBar.success(context, 'Unfollowed ${shop.name}.');
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not unfollow: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _unfollowing.remove(shop.sellerId));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('Following shops', style: AppTypography.pageTitle),
        actions: [
          IconButton(
            tooltip: 'Refresh following shops',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<List<ShopProfile>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: 6,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (_, _) => const SkeletonListTile(),
            );
          }
          if (snapshot.hasError) {
            return ErrorState(
              message: 'Could not load following.',
              error: friendlyError(snapshot.error!),
              onRetry: _refresh,
            );
          }
          final shops = (snapshot.data ?? [])
              .where((s) => !_unfollowed.contains(s.sellerId))
              .toList();
          if (shops.isEmpty) {
            return const EmptyState(
              icon: Icons.storefront_outlined,
              title: 'Not following any shops yet',
              subtitle: 'Shops you follow will show up here.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: shops.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final shop = shops[index];
              return StaggeredFadeIn(
                index: index,
                child: _ShopRow(
                  shop: shop,
                  busy: _unfollowing.contains(shop.sellerId),
                  onTap: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ShopScreen(
                          user: widget.user,
                          sellerId: shop.sellerId,
                        ),
                      ),
                    );
                    if (mounted) await _refresh();
                  },
                  onUnfollow: () => _unfollow(shop),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _ShopRow extends StatelessWidget {
  final ShopProfile shop;
  final bool busy;
  final VoidCallback onTap;
  final VoidCallback onUnfollow;

  const _ShopRow({
    required this.shop,
    required this.busy,
    required this.onTap,
    required this.onUnfollow,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.card),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: AppColors.pinkGradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                shape: BoxShape.circle,
              ),
              child: Text(
                shop.name.isNotEmpty ? shop.name[0].toUpperCase() : '?',
                style: TextStyle(
                  color: AppColors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    shop.name,
                    style: AppTypography.productTitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (shop.location != null)
                    Text(
                      shop.location!,
                      style: AppTypography.caption,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            OutlinedButton(
              onPressed: busy ? null : onUnfollow,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.textMuted,
                side: BorderSide(color: AppColors.border),
                padding: const EdgeInsets.symmetric(horizontal: 14),
              ),
              child: busy
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Following'),
            ),
          ],
        ),
      ),
    );
  }
}
