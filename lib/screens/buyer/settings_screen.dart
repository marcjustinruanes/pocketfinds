import '../../services/push_service.dart';
import 'verify_contact_change_screen.dart';
import 'saved_addresses_screen.dart';

import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_shadows.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../theme/theme_controller.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/auth_text_field.dart';
import '../../widgets/edit_sheet.dart';
import '../../widgets/pressable.dart';
import 'about_screen.dart';
import 'help_center_screen.dart';
import 'payment_methods_info_screen.dart';
import 'policies_screen.dart';
import 'vouchers_screen.dart';
import 'wishlist_screen.dart';

class SettingsScreen extends StatefulWidget {
  final AppUser user;

  const SettingsScreen({super.key, required this.user});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _authService = AuthService();
  late AppUser _user = widget.user;
  bool _busy = false;

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
          title: 'Personal Info & Socials',
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
              label: 'Middle name (optional)',
              controller: middleController,
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
      await _refreshUser();
      if (mounted) {
        AppSnackBar.success(
          context,
          'Saved. Name changes require admin review; social links update immediately.',
        );
      }
    }
  }

  Future<void> _changeUsername() async {
    final controller = TextEditingController(text: _user.username ?? '');
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
        ),
        child: EditSheet(
          title: 'Change Username',
          onSave: () async {
            final username = controller.text.trim();
            if (username == _user.username) return;
            final available = await _authService.checkUsernameAvailable(
              username,
            );
            if (!available) throw Exception('That username is already taken.');
            await _authService.updateUsername(username);
          },
          fields: [
            AuthTextField(
              label: 'Username',
              controller: controller,
              prefixIcon: Icons.alternate_email,
            ),
          ],
        ),
      ),
    );
    if (saved == true) await _refreshUser();
  }

  Future<void> _changeEmail() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VerifyContactChangeScreen()),
    );
    if (mounted) await _refreshUser();
  }

  Future<void> _changePassword() async {
    final currentController = TextEditingController();
    final controller = TextEditingController();
    final confirmController = TextEditingController();
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
        ),
        child: EditSheet(
          title: 'Change Password',
          onSave: () async {
            if (controller.text.length < 8) {
              throw Exception(
                'Password must be at least 8 characters with uppercase, lowercase, a number and a symbol.',
              );
            }
            if (controller.text != confirmController.text) {
              throw Exception('Passwords do not match.');
            }
            await _authService.changePassword(
              controller.text,
              currentPassword: currentController.text,
            );
          },
          fields: [
            AuthTextField(
              label: 'Current password',
              controller: currentController,
              obscureText: true,
              prefixIcon: Icons.lock_outline,
            ),
            AuthTextField(
              label: 'New password',
              controller: controller,
              obscureText: true,
              prefixIcon: Icons.lock_outline,
            ),
            AuthTextField(
              label: 'Confirm new password',
              controller: confirmController,
              obscureText: true,
              prefixIcon: Icons.lock_outline,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refreshUser() async {
    // The RPCs already return the updated row, but the sheets above don't
    // thread it back out — simplest correct refresh is re-fetching.
    try {
      final refreshed = await _authService.fetchMyProfile();
      if (refreshed != null && mounted) setState(() => _user = refreshed);
    } catch (e) {
      if (mounted) AppSnackBar.error(context, friendlyError(e));
    }
  }

  Future<void> _toggleNotify(bool value) async {
    setState(() => _busy = true);
    try {
      final updated = await _authService.updateProfileDetails(
        givenNames: _user.givenNames,
        lastName: _user.lastName,
        notifyEnabled: value,
      );
      if (!mounted) return;
      setState(() => _user = updated);
      if (value) {
        await PushService.instance?.enable();
      } else {
        await PushService.instance?.disable();
      }
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(context, 'Could not update: ${friendlyError(e)}');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _deactivate() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Deactivate Account?'),
        content: const Text(
          "You'll be signed out and won't be able to log back in until it's reactivated. "
          'Contact support if you actually want your data permanently deleted.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              'Deactivate',
              style: TextStyle(color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _busy = true);
    try {
      await _authService.deactivateAccount();
      // The app-wide auth listener (main.dart) takes it from here — signing
      // out fires an auth-state event that routes back to Guest Home.
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      AppSnackBar.error(context, 'Could not deactivate: ${friendlyError(e)}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        Navigator.of(context).pop(_user);
      },
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: Text('Settings', style: AppTypography.pageTitle)),
        body: AbsorbPointer(
          absorbing: _busy,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _SectionLabel('Account'),
              _SettingsCard(
                children: [
                  _SettingsTile(
                    icon: Icons.badge_outlined,
                    label: 'Personal Info & Socials',
                    onTap: _editPersonalInfo,
                  ),
                  _SettingsTile(
                    icon: Icons.alternate_email,
                    label: 'Change Username',
                    onTap: _changeUsername,
                  ),
                  _SettingsTile(
                    icon: Icons.email_outlined,
                    label: 'Change Email',
                    onTap: _changeEmail,
                  ),
                  _SettingsTile(
                    icon: Icons.phone_outlined,
                    label: 'Change Mobile Number',
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              const VerifyContactChangeScreen(phone: true),
                        ),
                      );
                      if (mounted) await _refreshUser();
                    },
                  ),
                  _SettingsTile(
                    icon: Icons.lock_outline,
                    label: 'Change Password',
                    onTap: _changePassword,
                  ),
                  _SettingsTile(
                    icon: Icons.location_on_outlined,
                    label: 'Address',
                    onTap: () async {
                      final updated = await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => SavedAddressesScreen(user: _user),
                        ),
                      );
                      if (updated != null && mounted) {
                        await _refreshUser();
                      }
                    },
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              _SectionLabel('Shopping'),
              _SettingsCard(
                children: [
                  SwitchListTile(
                    value: _user.notifyEnabled,
                    onChanged: _toggleNotify,
                    title: Text(
                      'Push Notifications',
                      style: AppTypography.body,
                    ),
                    subtitle: Text(
                      'Order updates, messages, and offers',
                      style: AppTypography.caption,
                    ),
                    activeThumbColor: AppColors.pink,
                  ),
                  const Divider(height: 1),
                  _SettingsTile(
                    icon: Icons.payment_outlined,
                    label: 'Payment Methods',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const PaymentMethodsInfoScreen(),
                      ),
                    ),
                  ),
                  _SettingsTile(
                    icon: Icons.favorite_border,
                    label: 'Wishlist',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => WishlistScreen(user: _user),
                      ),
                    ),
                  ),
                  _SettingsTile(
                    icon: Icons.sell_outlined,
                    label: 'Vouchers',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const VouchersScreen()),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              _SectionLabel('Preferences'),
              _SettingsCard(
                children: [
                  ValueListenableBuilder<bool>(
                    valueListenable: ThemeController.isDarkNotifier,
                    builder: (context, isDark, _) {
                      return SwitchListTile(
                        value: isDark,
                        onChanged: ThemeController.setDark,
                        title: Text('Dark Mode', style: AppTypography.body),
                        subtitle: Text(
                          isDark ? 'On' : 'Off',
                          style: AppTypography.caption,
                        ),
                        activeThumbColor: AppColors.pink,
                      );
                    },
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              _SectionLabel('Support'),
              _SettingsCard(
                children: [
                  _SettingsTile(
                    icon: Icons.support_agent_outlined,
                    label: 'Help Center',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => HelpCenterScreen(user: _user),
                      ),
                    ),
                  ),
                  _SettingsTile(
                    icon: Icons.gavel_outlined,
                    label: 'App Policies & Rules',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const PoliciesScreen()),
                    ),
                  ),
                  _SettingsTile(
                    icon: Icons.info_outline,
                    label: 'About',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const AboutScreen()),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              _SectionLabel('Danger Zone'),
              _SettingsCard(
                children: [
                  _SettingsTile(
                    icon: Icons.person_off_outlined,
                    label: 'Deactivate Account',
                    danger: true,
                    onTap: _deactivate,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 6),
      child: Text(
        text,
        style: AppTypography.caption.copyWith(fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _SettingsCard extends StatelessWidget {
  final List<Widget> children;
  const _SettingsCard({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.sm,
      ),
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: Column(children: children),
      ),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  const _SettingsTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = danger ? AppColors.danger : AppColors.textPrimary;
    return Pressable(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: AppTypography.body.copyWith(color: color),
              ),
            ),
            Icon(Icons.chevron_right, size: 20, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}
