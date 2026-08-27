import '../models/rider_profile.dart';
import '../models/shipment.dart';
import 'supabase_service.dart';

const _orderColumns = 'id, order_number, items, total, shipping_address';

/// Reads/writes deliveries for the signed-in rider, against the SAME
/// `shipments`/`delivery_assignments`/`orders` tables Laravel's
/// `LogisticsController` already uses — no separate rider-only tables.
/// Writes go through the `claim_shipment`/`update_delivery_status` RPCs,
/// which enforce assignment ownership and valid status transitions
/// server-side (not just in this UI).
class ShipmentService {
  String? get _myUid => SupabaseService.client.auth.currentUser?.id;

  Future<List<Shipment>> fetchAvailable() async {
    final rows = await SupabaseService.client
        .from('shipments')
        .select('id, order_id, shipping_status, courier_id, picked_up_at, out_for_delivery_at, delivered_at, created_at')
        .eq('shipping_status', 'available')
        .isFilter('courier_id', null)
        .order('created_at');
    return _joinOrders(rows);
  }

  Future<List<Shipment>> fetchMyActive() => _fetchMine([
    'accepted',
    'picked_up',
    'out_for_delivery',
  ]);

  Future<List<Shipment>> fetchMyHistory() => _fetchMine(['delivered', 'completed']);

  Future<List<Shipment>> _fetchMine(List<String> statuses) async {
    final uid = _myUid;
    if (uid == null) return [];
    final rows = await SupabaseService.client
        .from('shipments')
        .select('id, order_id, shipping_status, courier_id, picked_up_at, out_for_delivery_at, delivered_at, created_at')
        .eq('courier_id', uid)
        .inFilter('shipping_status', statuses)
        .order('created_at', ascending: false);
    return _joinOrders(rows);
  }

  Future<List<Shipment>> _joinOrders(List<Map<String, dynamic>> rows) async {
    if (rows.isEmpty) return [];
    final orderIds = rows.map((r) => r['order_id'] as String).toSet().toList();
    final orders = await SupabaseService.client
        .from('orders')
        .select(_orderColumns)
        .inFilter('id', orderIds);
    final ordersById = {for (final o in orders) o['id'] as String: o};

    return rows
        .where((r) => ordersById.containsKey(r['order_id']))
        .map((r) => Shipment.fromRow(r, ordersById[r['order_id']]!))
        .toList();
  }

  /// Claims an available shipment — atomic on the server (guards against
  /// two riders accepting the same delivery at once).
  Future<void> claim(String shipmentId) async {
    await SupabaseService.client.rpc(
      'claim_shipment',
      params: {'p_shipment_id': shipmentId},
    );
  }

  /// Advances a delivery's status. The server rejects any transition the
  /// rider isn't allowed to make (see `Shipment.nextStatus`) or shipments
  /// not assigned to the caller.
  Future<void> updateStatus(String shipmentId, String status) async {
    await SupabaseService.client.rpc(
      'update_delivery_status',
      params: {'p_shipment_id': shipmentId, 'p_status': status},
    );
  }

  /// Buyer name + contact number for a delivery — only returned if the
  /// caller is the courier assigned to it.
  Future<({String name, String contact})?> fetchBuyerContact(
    String shipmentId,
  ) async {
    final rows = await SupabaseService.client.rpc(
      'get_delivery_contact_info',
      params: {'p_shipment_id': shipmentId},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return null;
    return (
      name: list.first['buyer_name']?.toString() ?? 'Not provided',
      contact: list.first['buyer_contact']?.toString() ?? 'Not provided',
    );
  }

  /// The delivery status for a buyer's own order, if a shipment exists yet
  /// — used by Buyer's Order Details screen to show the rider's progress.
  Future<String?> fetchStatusForOrder(String orderId) async {
    final row = await SupabaseService.client
        .from('shipments')
        .select('shipping_status')
        .eq('order_id', orderId)
        .maybeSingle();
    return row?['shipping_status'] as String?;
  }

  Future<RiderProfile?> fetchMyRiderProfile() async {
    final row = await SupabaseService.client.rpc('get_my_rider_profile');
    if (row == null) return null;
    final map = Map<String, dynamic>.from(row as Map);
    if (map['user_id'] == null) return null;
    return RiderProfile.fromMap(map);
  }
}
