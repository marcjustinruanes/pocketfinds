import '../utils/backend_config.dart';

/// Basic public shop identity — from `public.users`' narrow public column
/// grant (the same one `ProductService` already relies on for seller info
/// on product cards).
class ShopProfile {
  final int sellerId;
  final String name;
  final String? location;
  final String? username;
  final String? photoUrl, bannerUrl, description;
  final DateTime? joined;

  const ShopProfile({
    required this.sellerId,
    required this.name,
    this.location,
    this.username,
    this.photoUrl,
    this.bannerUrl,
    this.description,
    this.joined,
  });

  factory ShopProfile.fromRow(Map<String, dynamic> row) {
    final businessName = row['business_name'] as String?;
    final name = (businessName != null && businessName.isNotEmpty)
        ? businessName
        : '${row['given_names']} ${row['last_name']}'.trim();
    final municipality = (row['municipality'] as String?)?.trim() ?? '';
    final province = (row['province'] as String?)?.trim() ?? '';
    final location = [
      municipality,
      province,
    ].where((v) => v.isNotEmpty).join(', ');

    final photo = row['profile_picture'] as String?;
    final category = photo == null
        ? null
        : RegExp(r'^category:(\d+)$').firstMatch(photo);
    final banner = row['store_banner'] as String?;
    return ShopProfile(
      sellerId: row['id'] as int,
      name: name.isEmpty ? 'PocketFinds Seller' : name,
      location: location.isEmpty ? null : location,
      username: row['username'] as String?,
      description: row['store_description'] as String?,
      joined: DateTime.tryParse('${row['created_at'] ?? ''}'),
      photoUrl: photo == null || photo.isEmpty
          ? null
          : category != null
          ? '${BackendConfig.url}/categories/${category.group(1)}/image'
          : BackendConfig.mediaUrl(photo, bucket: 'profile-images'),
      bannerUrl: banner == null || banner.isEmpty
          ? null
          : BackendConfig.mediaUrl(banner, bucket: 'profile-images'),
    );
  }
}
