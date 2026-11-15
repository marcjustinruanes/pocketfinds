/// The logged-in user's extended profile — mirrors the relevant columns of
/// the existing `public.users` table (fetched via the `get_my_profile()` /
/// `complete_registration()` Supabase RPC functions, never queried directly
/// from the client).
library;

import '../utils/backend_config.dart';

class AppUser {
  final int id;
  final String accountType; // buyer, rider, seller, admin, logistics
  final String status; // pending, approved, rejected, suspended
  final String email;
  final String givenNames;
  final String lastName;
  final String? middleName;
  final String? username;
  final String? profilePicture;
  final String? contactNo;
  final String? houseNo;
  final String? street;
  final String? barangay;
  final String? municipality;
  final String? province;
  final String? region;
  final String? facebookUrl;
  final String? instagramUrl;
  final String? twitterUrl;
  final String? tiktokUrl;
  final bool notifyEnabled;

  const AppUser({
    required this.id,
    required this.accountType,
    required this.status,
    required this.email,
    required this.givenNames,
    required this.lastName,
    this.middleName,
    this.username,
    this.profilePicture,
    this.contactNo,
    this.houseNo,
    this.street,
    this.barangay,
    this.municipality,
    this.province,
    this.region,
    this.facebookUrl,
    this.instagramUrl,
    this.twitterUrl,
    this.tiktokUrl,
    this.notifyEnabled = true,
  });

  factory AppUser.fromMap(Map<String, dynamic> map) {
    return AppUser(
      id: map['id'] as int,
      accountType: map['account_type'] as String,
      status: map['status'] as String,
      email: map['email'] as String,
      givenNames: map['given_names'] as String? ?? '',
      lastName: map['last_name'] as String? ?? '',
      middleName: map['middle_name'] as String?,
      username: map['username'] as String?,
      profilePicture:
          map['profile_picture'] == null || '${map['profile_picture']}'.isEmpty
          ? null
          : BackendConfig.mediaUrl(
              '${map['profile_picture']}',
              bucket: 'profile-images',
            ),
      contactNo: map['contact_no'] as String?,
      houseNo: map['house_no'] as String?,
      street: map['street'] as String?,
      barangay: map['barangay'] as String?,
      municipality: map['municipality'] as String?,
      province: map['province'] as String?,
      region: map['region'] as String?,
      facebookUrl: map['facebook_url'] as String?,
      instagramUrl: map['instagram_url'] as String?,
      twitterUrl: map['twitter_url'] as String?,
      tiktokUrl: map['tiktok_url'] as String?,
      notifyEnabled: map['notify_enabled'] as bool? ?? true,
    );
  }

  String get fullAddress => [
    houseNo,
    street,
    barangay,
    municipality,
    province,
  ].where((v) => v != null && v.trim().isNotEmpty).join(', ');

  String get displayName {
    final name = '$givenNames $lastName'.trim();
    return name.isEmpty ? (username ?? email) : name;
  }

  bool get isBuyer => accountType == 'buyer';
  bool get isRider => accountType == 'rider';
  bool get isApproved => status == 'approved';
  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';

  /// Also the real status a self-service "Deactivate Account" sets — see
  /// `deactivate_my_account()`. Reuses the same word the ERP spec itself
  /// uses for admin's "Activate, Suspend, or Deactivate" action rather than
  /// inventing a separate status value outside the DB's check constraint.
  bool get isSuspended => status == 'suspended';
}
