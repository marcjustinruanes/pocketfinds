import '../models/logistics_center.dart';
import 'backend_service.dart';

/// Reads `public.logistics_centers` — public read (no auth needed), since
/// the registration wizard's Choose Center step runs before a session
/// exists.
class LogisticsCenterService {
  Future<List<LogisticsCenter>> fetchCenters() async {
    final rows = await BackendService.client
        .from('logistics_centers')
        .select(
          'id, name, location, address, hours_note, accepting_applications, available_slots, service_area',
        )
        .order('id');
    return rows.map(LogisticsCenter.fromRow).toList();
  }

  Future<LogisticsCenter?> fetchById(int id) async {
    final row = await BackendService.client
        .from('logistics_centers')
        .select(
          'id, name, location, address, hours_note, accepting_applications, available_slots, service_area',
        )
        .eq('id', id)
        .maybeSingle();
    return row == null ? null : LogisticsCenter.fromRow(row);
  }
}
