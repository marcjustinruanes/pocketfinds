import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/app_user.dart';
import '../../models/logistics_center.dart';
import '../../models/rider_profile.dart';
import '../../services/auth_service.dart';
import '../../services/conversation_service.dart';
import '../../services/logistics_center_service.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/product_image.dart';
import '../../widgets/profile_info_card.dart';
import '../../widgets/status_badge.dart';
import '../buyer/help_center_screen.dart';
import '../buyer/settings_screen.dart';
import '../shared/address_form_screen.dart';
import 'messages_tab.dart';
import 'rider_vehicle_screen.dart';

/// Rider Profile — real `AppUser`/`RiderProfile` data, now including the
/// real assigned Logistics Center (joined from `logistics_centers` via
/// `RiderProfile.logisticsCenterId`, set at registration). No star rating
/// — there's no ratings/reviews-of-riders system in this app.
class RiderProfileTab extends StatefulWidget {
  final AppUser user;

  const RiderProfileTab({super.key, required this.user});

  @override
  State<RiderProfileTab> createState() => _RiderProfileTabState();
}

class _RiderProfileTabState extends State<RiderProfileTab> {
  final _authService = AuthService();
  final _shipmentService = ShipmentService();
  final _centerService = LogisticsCenterService();
  final _imagePicker = ImagePicker();
  late AppUser _user = widget.user;
  late Future<_ProfileData> _future;
  bool _uploadingAvatar = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_ProfileData> _load() async {
    final results = await Future.wait([
      _shipmentService.fetchMyRiderProfile(),
      _shipmentService.fetchMyHistory(),
    ]);
    final riderProfile = results[0] as RiderProfile?;
    final totalTasks = (results[1] as List).length;

    LogisticsCenter? center;
    if (riderProfile?.logisticsCenterId != null) {
      center = await _centerService.fetchById(riderProfile!.logisticsCenterId!);
    }

    return _ProfileData(
      riderProfile: riderProfile,
      totalTasks: totalTasks,
      center: center,
    );
  }

  Future<void> _logout() => _authService.signOut();

  Future<void> _editAddress() async {
    final updated = await Navigator.of(context).push<AppUser>(
      MaterialPageRoute(builder: (_) => AddressFormScreen(user: _user)),
    );
    if (updated != null && mounted) setState(() => _user = updated);
  }

  void _openMessages() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => RiderMessagesTab(
          user: _user,
          service: BackendConversationService(myUserId: _user.id),
        ),
      ),
    );
  }

  void _openHelp() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => HelpCenterScreen(user: _user)));
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
    final picked = await _imagePicker.pickImage(
      source: source,
      imageQuality: 85,
    );
    if (picked == null) return;

    setState(() => _uploadingAvatar = true);
    try {
      final url = await _authService.uploadAvatar(File(picked.path));
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
      body: FutureBuilder<_ProfileData>(
        future: _future,
        builder: (context, snapshot) {
          final rider = snapshot.data?.riderProfile;
          final totalTasks = snapshot.data?.totalTasks;
          final center = snapshot.data?.center;

          return ListView(
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
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Rider ID #${_user.id}',
                          style: AppTypography.caption,
                        ),
                        const SizedBox(width: 8),
                        StatusBadge(
                          status: rider?.status ?? _user.status,
                          label: _verificationLabel(
                            rider?.status ?? _user.status,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (snapshot.hasError) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.warningSoft,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.warningLine),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.error_outline,
                        size: 18,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          "Couldn't load your latest rider details — showing what's cached.",
                          style: AppTypography.caption.copyWith(
                            color: AppColors.warning,
                          ),
                        ),
                      ),
                      TextButton(
                        onPressed: () => setState(() {
                          _future = _load();
                        }),
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],
              ProfileInfoCard(
                title: 'Account',
                rows: [
                  ProfileInfoRow('Username', _user.username ?? 'Not set'),
                  const ProfileInfoRow('Account type', 'Courier / Rider'),
                  ProfileInfoRow(
                    'Total tasks completed',
                    totalTasks == null ? '—' : '$totalTasks',
                  ),
                  ProfileInfoRow(
                    'Assigned logistics center',
                    center?.name ?? 'Not set',
                  ),
                  if (center?.serviceArea != null)
                    ProfileInfoRow('Service area', center!.serviceArea!),
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
                    _user.fullAddress.isEmpty
                        ? 'Not provided'
                        : _user.fullAddress,
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (rider != null)
                ProfileInfoCard(
                  title: 'Vehicle',
                  rows: [
                    ProfileInfoRow('Type', rider.vehicleType),
                    if (rider.vehicleLabel.isNotEmpty)
                      ProfileInfoRow('Vehicle', rider.vehicleLabel),
                    if (rider.plateNumber != null &&
                        rider.plateNumber!.isNotEmpty)
                      ProfileInfoRow('Plate number', rider.plateNumber!),
                    if (rider.licenseNumber != null &&
                        rider.licenseNumber!.isNotEmpty)
                      ProfileInfoRow('License number', rider.licenseNumber!),
                  ],
                ),
              if (rider != null) const SizedBox(height: 16),
              TextButton(
                onPressed: () =>
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const RiderVehicleScreen(),
                      ),
                    ).then((_) {
                      if (mounted) {
                        setState(() {
                          _future = _load();
                        });
                      }
                    }),
                child: const Text('View / update vehicle and documents'),
              ),
              _MenuCard(
                children: [
                  _MenuTile(
                    iconWidget: MessageIcon(
                      size: 20,
                      color: AppColors.textPrimary,
                    ),
                    label: 'Messages',
                    onTap: _openMessages,
                  ),
                  _MenuTile(
                    icon: Icons.support_agent_outlined,
                    label: 'Help Center',
                    onTap: _openHelp,
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
          );
        },
      ),
    );
  }

  String _verificationLabel(String status) {
    switch (status) {
      case 'approved':
        return 'Verified';
      case 'pending':
        return 'Pending verification';
      case 'rejected':
        return 'Verification rejected';
      case 'suspended':
        return 'Suspended';
      default:
        return status;
    }
  }
}

class _MenuCard extends StatelessWidget {
  final List<Widget> children;

  const _MenuCard({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(children: children),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData? icon;
  final Widget? iconWidget;
  final String label;
  final VoidCallback onTap;

  const _MenuTile({
    this.icon,
    this.iconWidget,
    required this.label,
    required this.onTap,
  }) : assert(icon != null || iconWidget != null);

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            iconWidget ?? Icon(icon, size: 20, color: AppColors.textPrimary),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: AppTypography.body)),
            Icon(Icons.chevron_right, size: 20, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}

class _ProfileData {
  final RiderProfile? riderProfile;
  final int totalTasks;
  final LogisticsCenter? center;

  const _ProfileData({
    required this.riderProfile,
    required this.totalTasks,
    required this.center,
  });
}
