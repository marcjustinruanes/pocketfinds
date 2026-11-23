/// A website logistics hub with its current rider hiring availability.
class LogisticsCenter {
  final int id;
  final String name;
  final String location;
  final String address;
  final String? operatingHours;
  final bool acceptingApplications;
  final int? availableSlots;
  final String? serviceArea;

  const LogisticsCenter({
    required this.id,
    required this.name,
    required this.location,
    required this.address,
    this.operatingHours,
    required this.acceptingApplications,
    required this.availableSlots,
    this.serviceArea,
  });

  String get recruitmentLabel => acceptingApplications
      ? 'Accepting Applications'
      : 'Not Accepting Applications';

  factory LogisticsCenter.fromRow(Map<String, dynamic> row) {
    return LogisticsCenter(
      id: row['id'] as int,
      name: row['name']?.toString() ?? 'PocketFinds Logistics Center',
      location: row['location']?.toString() ?? '',
      address: row['address']?.toString() ?? '',
      operatingHours: row['hours_note'] as String?,
      acceptingApplications: row['accepting_applications'] as bool? ?? true,
      availableSlots: (row['available_slots'] as num?)?.toInt(),
      serviceArea: row['service_area'] as String?,
    );
  }
}
