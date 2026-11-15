import '../../services/buyer_photo_service.dart';

import 'package:geolocator/geolocator.dart';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/app_user.dart';
import '../../services/auth_service.dart';
import '../../services/review_service.dart';
import '../../services/shop_service.dart';
import '../../services/backend_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/auth_text_field.dart';
import '../../widgets/edit_sheet.dart';
import '../../widgets/pressable.dart';
import '../../widgets/product_image.dart';
import '../../widgets/profile_info_card.dart';
import '../../widgets/logout_confirmation.dart';
import 'saved_addresses_screen.dart';
import 'following_screen.dart';
import 'my_reviews_screen.dart';
import 'notifications_screen.dart';
import 'orders_tab.dart';
import 'settings_screen.dart';
import 'vouchers_screen.dart';
import 'wishlist_screen.dart';

/// Mobile translation of `resources/views/buyer/account.blade.php` —
/// profile info, a real avatar (upload via `profile-images`, public to
/// everyone — same bucket a seller's shop card/rider's rider-info card
/// already read from), editable contact/address, logout, real quick stats
/// (Orders/Wishlist/Reviews, all live queries), and a Settings entry point
/// for everything else (personal info, username/email/password, dark mode,
/// notifications, Help Center, deactivation).
class ProfileTab extends StatefulWidget {
  final AppUser user;

  const ProfileTab({super.key, required this.user});

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  final _authService = AuthService();
  final _reviewService = ReviewService();
  final _shopService = ShopService();
  final _imagePicker = ImagePicker();
  late AppUser _user = widget.user;
  late Future<_ProfileStats> _statsFuture;
  bool _uploadingAvatar = false;

  @override
  void initState() {
    super.initState();
    _statsFuture = _loadStats();
  }

  Future<_ProfileStats> _loadStats() async {
    final orders = await BackendService.client
        .from('orders')
        .select('id')
        .eq('app_buyer_id', _user.id);
    final results = await Future.wait([
      _reviewService.fetchMyReviews(),
      _shopService.fetchFollowedShops(_user.id),
    ]);
    return _ProfileStats(
      orderCount: orders.length,
      reviewCount: (results[0] as List).length,
      followingCount: (results[1] as List).length,
    );
  }

  void _openFollowing() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => FollowingScreen(user: _user)));
  }

  Future<void> _logout() async {
    if (await confirmLogout(context)) await _authService.signOut();
  }

  Future<void> _editAddress() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => SavedAddressesScreen(user: _user)),
    );
  }

  /// Same real flow Settings' "Personal Info & Socials" tile uses (shared
  /// `EditSheet` + `AuthTextField`), reachable from a direct "Edit Profile"
  /// button here instead of only through Settings.
  Future<void> _editPersonalInfo() async {
    final givenController = TextEditingController(text: _user.givenNames);
    final lastController = TextEditingController(text: _user.lastName);
    final middleController = TextEditingController(text: _user.middleName);
    final fbController = TextEditingController(text: _user.facebookUrl ?? '');
    final igController = TextEditingController(text: _user.instagramUrl ?? '');
    final twController = TextEditingController(text: _user.twitterUrl ?? '');
    final ttController = TextEditingController(text: _user.tiktokUrl ?? '');

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
        ),
        child: EditSheet(
          title: 'Edit Profile',
          onSave: () async {
            await _authService.updateProfileDetails(
              givenNames: givenController.text.trim(),
              lastName: lastController.text.trim(),
              middleName: middleController.text.trim(),
              facebookUrl: fbController.text.trim(),
              instagramUrl: igController.text.trim(),
              twitterUrl: twController.text.trim(),
              tiktokUrl: ttController.text.trim(),
            );
          },
          fields: [
            AuthTextField(
              label: 'First name',
              controller: givenController,
              prefixIcon: Icons.person_outline,
            ),
            AuthTextField(
              label: 'Last name',
              controller: lastController,
              prefixIcon: Icons.person_outline,
            ),
            AuthTextField(
              label: 'Facebook link',
              controller: fbController,
              hint: 'https://facebook.com/...',
              prefixIcon: Icons.link,
            ),
            AuthTextField(
              label: 'Instagram link',
              controller: igController,
              hint: 'https://instagram.com/...',
              prefixIcon: Icons.link,
            ),
            AuthTextField(
              label: 'X / Twitter link',
              controller: twController,
              hint: 'https://x.com/...',
              prefixIcon: Icons.link,
            ),
            AuthTextField(
              label: 'TikTok link',
              controller: ttController,
              hint: 'https://tiktok.com/@...',
              prefixIcon: Icons.link,
            ),
          ],
        ),
      ),
    );
    if (saved == true) {
      final refreshed = await _authService.fetchMyProfile();
      if (refreshed != null && mounted) setState(() => _user = refreshed);
    }
  }

  void _hintUseBottomNav(String tabName) {
    AppSnackBar.info(context, 'See the $tabName tab below.');
  }

  void _openNotifications() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
  }

  Future<void> _openSettings() async {
    final updated = await Navigator.of(context).push<AppUser>(
      MaterialPageRoute(builder: (_) => SettingsScreen(user: _user)),
    );
    if (updated != null && mounted) setState(() => _user = updated);
  }

  Future<void> _changeAvatar() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    setState(() => _uploadingAvatar = true);
    try {
      final picked = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
      );
      if (picked == null) return;
      final photo = await BuyerPhotoService.prepare(
        picked,
        camera: source == ImageSource.camera,
      );
      final path = await BuyerPhotoService.upload(photo, 'profile-images');
      final url = BackendService.client.storage
          .from('profile-images')
          .getPublicUrl(path);
      final updated = await _authService.updateProfileDetails(
        givenNames: _user.givenNames,
        lastName: _user.lastName,
        profilePicture: url,
      );
      if (!mounted) return;
      setState(() => _user = updated);
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(
        context,
        'Could not update your photo: ${friendlyError(e)}',
      );
      final open = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Photo access'),
          content: const Text(
            'Check camera and photo access in Settings. Gallery remains available when camera access is off.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Open Settings'),
            ),
          ],
        ),
      );
      if (open == true) await Geolocator.openAppSettings();
    } finally {
      if (mounted) setState(() => _uploadingAvatar = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('My Account', style: AppTypography.pageTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Settings',
            onPressed: _openSettings,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: Column(
              children: [
                GestureDetector(
                  onTap: _uploadingAvatar ? null : _changeAvatar,
                  child: Stack(
                    children: [
                      Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: _user.profilePicture == null
                              ? LinearGradient(
                                  colors: AppColors.pinkGradient,
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                )
                              : null,
                          boxShadow: AppShadows.sm,
                        ),
                        alignment: Alignment.center,
                        child: _uploadingAvatar
                            ? const CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              )
                            : _user.profilePicture != null
                            ? ClipRRect(
                                borderRadius: BorderRadius.circular(40),
                                child: ProductImage(
                                  imageUrl: _user.profilePicture,
                                  fit: BoxFit.cover,
                                ),
                              )
                            : Text(
                                _user.givenNames.isNotEmpty
                                    ? _user.givenNames[0].toUpperCase()
                                    : '?',
                                style: TextStyle(
                                  fontSize: 32,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.white,
                                ),
                              ),
                      ),
                      Positioned(
                        bottom: 0,
                        right: 0,
                        child: Container(
                          width: 26,
                          height: 26,
                          decoration: BoxDecoration(
                            color: AppColors.pink,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppColors.pageBackground,
                              width: 2,
                            ),
                          ),
                          child: const Icon(
                            Icons.camera_alt,
                            size: 13,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _user.displayName,
                  style: AppTypography.pageTitle.copyWith(fontSize: 18),
                ),
                Text(_user.email, style: AppTypography.caption),
                const SizedBox(height: 8),
                const SizedBox(height: 14),
                OutlinedButton.icon(
                  onPressed: _editPersonalInfo,
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  label: const Text('Edit Profile'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.pinkDark,
                    side: BorderSide(color: AppColors.pinkBorder),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 8,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          FutureBuilder<_ProfileStats>(
            future: _statsFuture,
            builder: (context, snapshot) {
              final stats = snapshot.data;
              return Container(
                padding: const EdgeInsets.symmetric(vertical: 14),
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: _statItem(
                        '${stats?.orderCount ?? '—'}',
                        'Orders',
                        () => _hintUseBottomNav('Orders'),
                      ),
                    ),
                    _divider(),
                    Expanded(
                      child: _statItem(
                        '${stats?.followingCount ?? '—'}',
                        'Following',
                        _openFollowing,
                      ),
                    ),
                    _divider(),
                    Expanded(
                      child: _statItem(
                        '${stats?.reviewCount ?? '—'}',
                        'Reviews',
                        null,
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 20),
          ProfileInfoCard(
            title: 'My Account',
            rows: [
              ProfileInfoRow('Username', _user.username ?? 'Not set'),
              const ProfileInfoRow('Account type', 'Buyer'),
              ProfileInfoRow('Status', _user.status),
            ],
          ),
          const SizedBox(height: 16),
          ProfileInfoCard(
            title: 'Contact & address',
            trailing: TextButton(
              onPressed: _editAddress,
              child: const Text('Edit'),
            ),
            rows: [
              ProfileInfoRow(
                'Contact number',
                _user.contactNo ?? 'Not provided',
              ),
              ProfileInfoRow(
                'Address',
                _user.fullAddress.isEmpty ? 'Not provided' : _user.fullAddress,
              ),
            ],
          ),
          const SizedBox(height: 16),
          _MenuCard(
            title: 'Shopping',
            children: [
              _MenuTile(
                icon: Icons.location_on_outlined,
                label: 'My addresses',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => SavedAddressesScreen(user: _user),
                  ),
                ),
              ),
              _MenuTile(
                icon: Icons.receipt_long_outlined,
                label: 'My Orders',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => OrdersTab(user: _user)),
                ),
              ),
              _MenuTile(
                icon: Icons.rate_review_outlined,
                label: 'My Reviews',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => MyReviewsScreen(user: _user),
                  ),
                ),
              ),
              _MenuTile(
                icon: Icons.favorite_border,
                label: 'Wishlist',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => WishlistScreen(user: _user),
                  ),
                ),
              ),
              _MenuTile(
                icon: Icons.sell_outlined,
                label: 'Vouchers',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const VouchersScreen()),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _MenuCard(
            title: 'Preferences',
            children: [
              _MenuTile(
                icon: Icons.notifications_outlined,
                label: 'Notifications',
                onTap: _openNotifications,
              ),
              _MenuTile(
                icon: Icons.settings_outlined,
                label: 'Settings',
                onTap: _openSettings,
              ),
            ],
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _logout,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.danger,
                side: BorderSide(
                  color: AppColors.danger.withValues(alpha: 0.4),
                ),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Log out'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() => Container(width: 1, height: 32, color: AppColors.border);

  Widget _statItem(String value, String label, VoidCallback? onTap) {
    return InkWell(
      onTap: onTap,
      child: Column(
        children: [
          Text(value, style: AppTypography.productTitle.copyWith(fontSize: 17)),
          Text(label, style: AppTypography.caption),
        ],
      ),
    );
  }
}

class _ProfileStats {
  final int orderCount;
  final int reviewCount;
  final int followingCount;

  const _ProfileStats({
    required this.orderCount,
    required this.reviewCount,
    required this.followingCount,
  });
}

class _MenuCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _MenuCard({required this.title, required this.children});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 6),
          child: Text(
            title,
            style: AppTypography.caption.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(children: children),
        ),
      ],
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MenuTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Pressable(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Icon(icon, size: 20, color: AppColors.textPrimary),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: AppTypography.body)),
            Icon(Icons.chevron_right, size: 20, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}
