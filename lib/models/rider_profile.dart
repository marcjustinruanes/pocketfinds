class RiderProfile {
  final String vehicleType;
  final String? vehicleBrand;
  final String? vehicleModel;
  final String? plateNumber;
  final String? licenseNumber;
  final String status; // pending, approved, rejected, suspended
  final bool isAvailable;
  final int? logisticsCenterId;
  final String? hubMunicipality, hubProvince, hubCompany;

  const RiderProfile({
    required this.vehicleType,
    this.vehicleBrand,
    this.vehicleModel,
    this.plateNumber,
    this.licenseNumber,
    this.status = 'pending',
    this.isAvailable = false,
    this.logisticsCenterId,
    this.hubMunicipality,
    this.hubProvince,
    this.hubCompany,
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
      status: map['status']?.toString() ?? 'pending',
      isAvailable: map['is_available'] as bool? ?? false,
      logisticsCenterId: map['logistics_center_id'] as int?,
      hubMunicipality: map['hub_municipality']?.toString(),
      hubProvince: map['hub_province']?.toString(),
      hubCompany: map['hub_company']?.toString(),
    );
  }
}
