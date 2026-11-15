/// The logged-in user's extended profile — mirrors the relevant columns of
/// the existing `public.users` table (fetched via the `get_my_profile()` /
/// `complete_registration()` Supabase RPC functions, never queried directly
/// from the client).
class AppUser {
  final int id;
  final String accountType; // buyer, rider, seller, admin, logistics
  final String status; // pending, approved, rejected
  final String email;
  final String givenNames;
  final String lastName;
  final String? username;
  final String? profilePicture;
  final String? contactNo;
  final String? houseNo;
  final String? street;
  final String? barangay;
  final String? municipality;
  final String? province;

  const AppUser({
    required this.id,
    required this.accountType,
    required this.status,
    required this.email,
    required this.givenNames,
    required this.lastName,
    this.username,
    this.profilePicture,
    this.contactNo,
    this.houseNo,
    this.street,
    this.barangay,
    this.municipality,
    this.province,
  });

  factory AppUser.fromMap(Map<String, dynamic> map) {
    return AppUser(
      id: map['id'] as int,
      accountType: map['account_type'] as String,
      status: map['status'] as String,
      email: map['email'] as String,
      givenNames: map['given_names'] as String? ?? '',
      lastName: map['last_name'] as String? ?? '',
      username: map['username'] as String?,
      profilePicture: map['profile_picture'] as String?,
      contactNo: map['contact_no'] as String?,
      houseNo: map['house_no'] as String?,
      street: map['street'] as String?,
      barangay: map['barangay'] as String?,
      municipality: map['municipality'] as String?,
      province: map['province'] as String?,
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
}
