/// A rider's vehicle/license info from `rider_profiles`, read via
/// `get_my_rider_profile()` (that table has RLS enabled with no direct
/// grants — only the caller's own row is ever returned).
class RiderProfile {
  final String vehicleType;
  final String? vehicleBrand;
  final String? vehicleModel;
  final String? plateNumber;
  final String? licenseNumber;

  const RiderProfile({
    required this.vehicleType,
    this.vehicleBrand,
    this.vehicleModel,
    this.plateNumber,
    this.licenseNumber,
  });

  String get vehicleLabel => [
    vehicleBrand,
    vehicleModel,
  ].where((v) => v != null && v.isNotEmpty).join(' ');

  factory RiderProfile.fromMap(Map<String, dynamic> map) {
    return RiderProfile(
      vehicleType: map['vehicle_type']?.toString() ?? 'Not set',
      vehicleBrand: map['vehicle_brand'] as String?,
      vehicleModel: map['vehicle_model'] as String?,
      plateNumber: map['plate_number'] as String?,
      licenseNumber: map['license_number'] as String?,
    );
  }
}
