import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../services/backend_service.dart';
import '../../services/auth_service.dart';
import '../../services/delivery_service.dart';
import '../../theme/app_colors.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/logout_confirmation.dart';
import 'saved_addresses_screen.dart';
import 'settings_screen.dart';
import 'wishlist_screen.dart';
import 'vouchers_screen.dart';
import 'my_reviews_screen.dart';
import 'following_screen.dart';
import 'website_content_screen.dart';
import 'connect_gcash_screen.dart';

import 'package:image_picker/image_picker.dart';

import '../../services/buyer_photo_service.dart';

/// Mobile version of the website's My Account cards and approval workflow.
class WebsiteAccountScreen extends StatefulWidget {
  final AppUser user;
  final BackendClient? client;
  final String? section;
  const WebsiteAccountScreen({
    super.key,
    required this.user,
    this.client,
    this.section,
  });
  @override
  State<WebsiteAccountScreen> createState() => _WebsiteAccountScreenState();
}

class _WebsiteAccountScreenState extends State<WebsiteAccountScreen>
    with WidgetsBindingObserver, DeliveryRefresh<WebsiteAccountScreen> {
  late AppUser _user = widget.user;
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  Future<void> _logout() async {
    if (await confirmLogout(context)) await AuthService().signOut();
  }

  late final _client = widget.client ?? BackendService.client;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Future<void> refreshDeliveryData() async {
    if (!_busy) await _load();
  }

  Future<void> _load() async {
    try {
      final data = await _client.request('website-buyer/account', {});
      if (mounted) {
        setState(() {
          _data = Map<String, dynamic>.from(data as Map);
          _error = null;
          final profile = Map<String, dynamic>.from(_data!['profile'] as Map);
          _user = AppUser.fromMap({
            ...profile,
            'id': _user.id,
            'account_type': _user.accountType,
          });
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  Future<bool> _act(String action, Map<String, dynamic> fields) async {
    setState(() => _busy = true);
    try {
      await _client.request('website-buyer/$action', fields);
      await _load();
      return true;
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
      return false;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _edit(bool address) async {
    final profile = Map<String, dynamic>.from(_data!['profile'] as Map);
    final fields = address
        ? {
            'region': 'Region',
            'postal_code': 'Postal code',
            'province': 'Province',
            'municipality': 'City / municipality',
            'barangay': 'Barangay',
            'house_no': 'House / unit',
            'street': 'Street',
          }
        : {
            'given_names': 'First name',
            'last_name': 'Last name',
            'middle_name': 'Middle name (optional)',
            'contact_no': 'Contact number',
          };
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (context) => _AccountEditor(
          title: address ? 'Address Information' : 'Profile Information',
          fields: fields,
          values: {for (final key in fields.keys) key: '${profile[key] ?? ''}'},
          onSubmit: (values) async {
            await _client.request(
              'website-buyer/${address ? 'address' : 'profile'}',
              values,
            );
          },
        ),
      ),
    );
    if (result == true && mounted) await _load();
  }

  Future<void> _changePhoto() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final picked = await ImagePicker().pickImage(source: ImageSource.gallery);
      if (picked == null) return;
      final photo = await BuyerPhotoService.prepare(picked);
      final path = await BuyerPhotoService.upload(photo, 'profile-images');
      final url = BackendService.client.storage
          .from('profile-images')
          .getPublicUrl(path);
      await AuthService().updateProfileDetails(
        givenNames: _user.givenNames,
        lastName: _user.lastName,
        profilePicture: url,
      );
      if (mounted) await _load();
    } catch (e) {
      if (mounted) setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _connect() async {
    final connected = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ConnectGcashScreen(client: _client)),
    );
    if (connected == true && mounted) await _load();
  }

  Widget _card(String title, String subtitle, List<Widget> children) => Card(
    margin: const EdgeInsets.only(bottom: 16),
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(subtitle, style: TextStyle(color: AppColors.textMuted)),
          ],
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    ),
  );
  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    if (mounted) await _load();
  }

  void _section(String section) => _open(
    WebsiteAccountScreen(user: _user, client: _client, section: section),
  );
  Widget _menuRow(String title, IconData icon, VoidCallback onTap) => ListTile(
    minVerticalPadding: 16,
    leading: Icon(icon, color: const Color(0xFF9D8493)),
    title: Text(
      title,
      style: const TextStyle(
        fontWeight: FontWeight.w600,
        color: Color(0xFF705866),
      ),
    ),
    trailing: Icon(Icons.chevron_right, color: AppColors.textMuted, size: 20),
    onTap: onTap,
  );
  @override
  Widget build(BuildContext context) {
    final p = (_data?['profile'] as Map?) ?? {};
    final pending = _data?['pending_request'];
    final last = _data?['last_request'] as Map?;
    final name =
        '${p['given_names'] ?? widget.user.givenNames} ${p['last_name'] ?? widget.user.lastName}'
            .trim();
    if (widget.section == null) {
      final initials = name
          .split(RegExp(r'\s+'))
          .where((part) => part.isNotEmpty)
          .take(2)
          .map((part) => part[0].toUpperCase())
          .join();
      return Scaffold(
        appBar: AppBar(
          title: const Text('My Account'),
          actions: [
            IconButton(
              tooltip: 'Settings',
              icon: const Icon(Icons.settings_outlined),
              onPressed: () => _open(SettingsScreen(user: _user)),
            ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 24,
                  ),
                  child: Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.pinkBorder),
                        ),
                        child: CircleAvatar(
                          radius: 36,
                          backgroundColor: AppColors.pinkSoft,
                          foregroundImage: _user.profilePicture == null
                              ? null
                              : NetworkImage(_user.profilePicture!),
                          child: Text(
                            initials,
                            style: TextStyle(
                              fontSize: 25,
                              fontWeight: FontWeight.w700,
                              color: AppColors.pinkDark,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        name,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 23,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF563747),
                        ),
                      ),
                      const SizedBox(height: 24),
                      const Divider(),
                      _menuRow(
                        'My profile',
                        Icons.person_outline,
                        () => _section('My profile'),
                      ),
                      const Divider(height: 1),
                      _menuRow(
                        'Login & security',
                        Icons.verified_user_outlined,
                        () => _open(SettingsScreen(user: _user)),
                      ),
                      const Divider(height: 1),
                      _menuRow(
                        'My addresses',
                        Icons.home_outlined,
                        () => _section('My addresses'),
                      ),
                      const Divider(height: 1),
                      _menuRow(
                        'My vouchers',
                        Icons.confirmation_number_outlined,
                        () => _open(const VouchersScreen()),
                      ),
                      const Divider(height: 1),
                      _menuRow(
                        'Following shops',
                        Icons.favorite_border,
                        () => _open(FollowingScreen(user: _user)),
                      ),
                    ],
                  ),
                ),
              ),
              if (_data == null && _error == null)
                const LinearProgressIndicator(),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(_error!),
                ),
              if (pending != null)
                _card(
                  'Update Request Pending',
                  'Your requested changes await admin review.',
                  [],
                ),
              const SizedBox(height: 12),
              _menuRow(
                'Payment methods',
                Icons.account_balance_wallet_outlined,
                () => _section('Payment methods'),
              ),
              _menuRow(
                'Settings',
                Icons.settings_outlined,
                () => _open(SettingsScreen(user: _user)),
              ),
              _menuRow(
                'More account options',
                Icons.more_horiz,
                () => _section('More account options'),
              ),
              TextButton(onPressed: _logout, child: const Text('Log out')),
            ],
          ),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(widget.section!)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              '${p['given_names'] ?? widget.user.givenNames} ${p['last_name'] ?? widget.user.lastName}',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            Text('${p['email'] ?? widget.user.email}'),
            const SizedBox(height: 6),
            Text('Buyer · ${p['status'] ?? widget.user.status}'),
            const SizedBox(height: 18),
            if (_busy || (_data == null && _error == null))
              const LinearProgressIndicator(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (pending != null)
              _card(
                'Update Request Pending',
                'Your requested changes await admin review. Your current account details remain in effect.',
                [],
              )
            else if (last?['status'] == 'rejected')
              _card(
                'Last Request Rejected',
                '${last?['note'] ?? 'You may submit a new request.'}',
                [],
              ),
            if (widget.section == 'My profile')
              OutlinedButton.icon(
                onPressed: _busy ? null : _changePhoto,
                icon: const Icon(Icons.add_a_photo_outlined),
                label: const Text('Change profile photo'),
              ),
            if (widget.section == 'My profile')
              _card(
                'Profile Information',
                'Changes are reviewed by an admin before they take effect.',
                [
                  Text('First name: ${p['given_names'] ?? ''}'),
                  Text('Last name: ${p['last_name'] ?? ''}'),
                  Text('Middle name: ${p['middle_name'] ?? '—'}'),
                  Text('Contact: ${p['contact_no'] ?? '—'}'),
                  OutlinedButton(
                    onPressed: _busy || _data == null || pending != null
                        ? null
                        : () => _edit(false),
                    child: const Text('Request profile changes'),
                  ),
                ],
              ),
            if (widget.section == 'My addresses')
              _card(
                'Address Information',
                'Profile address changes require admin approval.',
                [
                  Text(
                    [
                          'house_no',
                          'street',
                          'barangay',
                          'municipality',
                          'province',
                          'postal_code',
                        ]
                        .map((key) => '${p[key] ?? ''}')
                        .where((value) => value.isNotEmpty)
                        .join(', '),
                  ),
                  OutlinedButton(
                    onPressed: _busy || _data == null || pending != null
                        ? null
                        : () => _edit(true),
                    child: const Text('Request address changes'),
                  ),
                ],
              ),
            if (widget.section == 'My addresses')
              _card(
                'Delivery Address',
                'Choose where your orders ship at checkout.',
                [
                  OutlinedButton(
                    onPressed: () => _open(SavedAddressesScreen(user: _user)),
                    child: const Text('Manage delivery addresses'),
                  ),
                ],
              ),
            if (widget.section == 'Payment methods')
              _card(
                'Payment Methods',
                'Email verification confirms a GCash profile entry, not receipt of payment.',
                [
                  for (final account
                      in (_data?['payment_accounts'] as List? ?? []))
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('${account['account_name']}'),
                      subtitle: Text('GCash · ${account['account_number']}'),
                      trailing: IconButton(
                        tooltip: 'Remove account',
                        icon: const Icon(Icons.delete_outline),
                        onPressed: _busy
                            ? null
                            : () async {
                                final yes = await showDialog<bool>(
                                  context: context,
                                  builder: (c) => AlertDialog(
                                    title: const Text(
                                      'Remove this GCash account?',
                                    ),
                                    actions: [
                                      TextButton(
                                        onPressed: () =>
                                            Navigator.pop(c, false),
                                        child: const Text('Cancel'),
                                      ),
                                      TextButton(
                                        onPressed: () => Navigator.pop(c, true),
                                        child: const Text('Remove'),
                                      ),
                                    ],
                                  ),
                                );
                                if (yes == true && mounted) {
                                  await _act('payment-remove', {
                                    'id': account['id'],
                                  });
                                }
                              },
                      ),
                    ),
                  if ((_data?['payment_accounts'] as List? ?? []).isEmpty)
                    const Text('No payment accounts connected yet.'),
                  OutlinedButton(
                    onPressed: _busy ? null : _connect,
                    child: const Text('Connect GCash'),
                  ),
                ],
              ),
            if (widget.section == 'My profile')
              _card('Account Details', '', [
                Text('Account type: Buyer'),
                Text('Status: ${p['status'] ?? widget.user.status}'),
                Text('Member since: ${p['created_at'] ?? '—'}'),
                TextButton(
                  onPressed: () => _open(SettingsScreen(user: _user)),
                  child: const Text('Password, email and account settings'),
                ),
              ]),
            if (widget.section == 'More account options')
              _card('Your PocketFinds', '', [
                ListTile(
                  title: const Text('Wishlist'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _open(WishlistScreen(user: _user)),
                ),
                ListTile(
                  title: const Text('Vouchers'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _open(const VouchersScreen()),
                ),
                ListTile(
                  title: const Text('My Reviews'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _open(MyReviewsScreen(user: _user)),
                ),
                ListTile(
                  title: const Text('Following'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _open(FollowingScreen(user: _user)),
                ),
                ListTile(
                  title: const Text('Announcements'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () =>
                      _open(const WebsiteContentScreen(kind: 'announcements')),
                ),
                ListTile(
                  title: const Text('FAQs'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _open(const WebsiteContentScreen(kind: 'faqs')),
                ),
              ]),
            TextButton(
              onPressed: _busy ? null : _logout,
              child: const Text('Log out'),
            ),
          ],
        ),
      ),
    );
  }
}

class _AccountEditor extends StatefulWidget {
  final String title;
  final Map<String, String> fields;
  final Map<String, String> values;
  final Future<void> Function(Map<String, dynamic>) onSubmit;
  const _AccountEditor({
    required this.title,
    required this.fields,
    required this.values,
    required this.onSubmit,
  });
  @override
  State<_AccountEditor> createState() => _AccountEditorState();
}

class _AccountEditorState extends State<_AccountEditor> {
  bool _busy = false;
  String? _error;
  late final controllers = {
    for (final entry in widget.values.entries)
      entry.key: TextEditingController(text: entry.value),
  };
  @override
  void dispose() {
    for (final controller in controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onSubmit({
        for (final entry in controllers.entries)
          entry.key: entry.value.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text('Submit your changes for administrator review.'),
        const SizedBox(height: 18),
        for (final field in widget.fields.entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: TextField(
              controller: controllers[field.key],
              readOnly: _busy,
              decoration: InputDecoration(labelText: field.value),
            ),
          ),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: Text(_busy ? 'Submitting...' : 'Submit for review'),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 16),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
      ],
    ),
  );
}
