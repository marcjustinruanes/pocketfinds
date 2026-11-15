import 'package:flutter/material.dart';
import '../../models/app_user.dart';
import '../../models/rider_profile.dart';
import '../../services/auth_service.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../widgets/edit_address_sheet.dart';
import '../../widgets/profile_info_card.dart';

/// Mobile translation of the rider's account info — reuses the same
/// `AppUser`/`AuthService` as Buyer's Profile tab (name/contact/address,
/// edit, logout), plus the rider-only vehicle info from `rider_profiles`.
/// Profile picture upload isn't wired up (no Supabase Storage bucket yet —
/// same gap noted for Buyer).
class RiderProfileTab extends StatefulWidget {
  final AppUser user;

  const RiderProfileTab({super.key, required this.user});

  @override
  State<RiderProfileTab> createState() => _RiderProfileTabState();
}

class _RiderProfileTabState extends State<RiderProfileTab> {
  final _authService = AuthService();
  final _shipmentService = ShipmentService();
  late AppUser _user = widget.user;
  late Future<RiderProfile?> _riderProfileFuture;

  @override
  void initState() {
    super.initState();
    _riderProfileFuture = _shipmentService.fetchMyRiderProfile();
  }

  Future<void> _logout() => _authService.signOut();

  Future<void> _editAddress() async {
    final updated = await showEditAddressSheet(context, _user);
    if (updated != null && mounted) setState(() => _user = updated);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Account')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: Column(
              children: [
                Container(
                  width: 80,
                  height: 80,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      colors: AppColors.pinkGradient,
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    _user.givenNames.isNotEmpty ? _user.givenNames[0].toUpperCase() : '?',
                    style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: AppColors.white),
                  ),
                ),
                const SizedBox(height: 12),
                Text(_user.displayName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                Text(_user.email, style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
              ],
            ),
          ),
          const SizedBox(height: 24),
          ProfileInfoCard(
            title: 'Account',
            rows: [
              ProfileInfoRow('Username', _user.username ?? 'Not set'),
              const ProfileInfoRow('Account type', 'Rider'),
              ProfileInfoRow('Status', _user.status),
            ],
          ),
          const SizedBox(height: 16),
          ProfileInfoCard(
            title: 'Contact & address',
            trailing: TextButton(onPressed: _editAddress, child: const Text('Edit')),
            rows: [
              ProfileInfoRow('Contact number', _user.contactNo ?? 'Not provided'),
              ProfileInfoRow('Address', _user.fullAddress.isEmpty ? 'Not provided' : _user.fullAddress),
            ],
          ),
          const SizedBox(height: 16),
          FutureBuilder<RiderProfile?>(
            future: _riderProfileFuture,
            builder: (context, snapshot) {
              final rider = snapshot.data;
              if (rider == null) return const SizedBox.shrink();
              return ProfileInfoCard(
                title: 'Vehicle',
                rows: [
                  ProfileInfoRow('Type', rider.vehicleType),
                  if (rider.vehicleLabel.isNotEmpty) ProfileInfoRow('Vehicle', rider.vehicleLabel),
                  if (rider.plateNumber != null && rider.plateNumber!.isNotEmpty)
                    ProfileInfoRow('Plate number', rider.plateNumber!),
                ],
              );
            },
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _logout,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.danger,
                side: BorderSide(color: AppColors.danger.withValues(alpha: 0.4)),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Log out'),
            ),
          ),
        ],
      ),
    );
  }
}
