import '../models/rider_profile.dart';
import '../models/shipment.dart';
import 'backend_service.dart';
import 'rider_service.dart';

const _orderColumns =
    'id, order_number, items, total, shipping_address, app_buyer_id';
const _shipmentColumns =
    'id, order_id, tracking_number, destination_hub, destination_province, shipping_status, courier_id, delivery_fee, picked_up_at, out_for_delivery_at, delivered_at, created_at';
const _sellerColumns =
    'id, given_names, last_name, business_name, municipality, province';

/// Reads/writes deliveries for the signed-in rider, against the SAME
/// `shipments`/`delivery_assignments`/`orders` tables Laravel's
/// `LogisticsController` already uses — no separate rider-only tables.
/// The rider's own identity/vehicle/license fields live directly on their
/// `public.users` row (see `RiderProfile`) — there is no separate
/// `rider_profiles` table. Writes go through the `claim_shipment`/
/// `update_delivery_status` RPCs, which enforce assignment ownership and
/// valid status transitions server-side (not just in this UI).
class ShipmentService {
  /// `shipments.courier_id` is the rider's legacy `public.users.id`
  /// (bigint) — NOT `auth.uid()`. An earlier pass used the auth uuid
  /// directly, matching the column's type at the time, but something
  /// outside this app (most likely Laravel's own courier-assignment code)
  /// kept recreating the column as bigint with real legacy-id values in
  /// it. `my_legacy_user_id()` is the same email-based bridge every other
  /// "my own row" lookup in this app already uses.
  Future<int?> _myLegacyId() async {
    final id = await BackendService.client.rpc('my_legacy_user_id');
    return id as int?;
  }

  Future<List<Shipment>> fetchAvailable() async {
    final rows = await BackendService.client
        .from('shipments')
        .select(_shipmentColumns)
        .eq('shipping_status', 'sorted')
        .isFilter('courier_id', null)
        .order('created_at');
    return _joinOrders(rows);
  }

  Future<List<Shipment>> fetchMyActive() => _fetchMine([
    'assigned_to_rider',
    'picked_up',
    'out_for_delivery',
    'delivery_failed', // still needs the rider to retry or return it
    'rescheduled',
    'return_in_transit',
  ]);

  Future<List<Shipment>> fetchMyHistory() async {
    final result = await BackendService.client.request('delivery/history', {});
    return _joinOrders(
      (result as List)
          .map((row) => Map<String, dynamic>.from(row as Map))
          .toList(),
    );
  }

  Future<List<Shipment>> _fetchMine(List<String> statuses) async {
    final id = await _myLegacyId();
    if (id == null) return [];
    final rows = await BackendService.client
        .from('shipments')
        .select(_shipmentColumns)
        .eq('courier_id', id)
        .inFilter('shipping_status', statuses)
        .order('created_at', ascending: false);
    return _joinOrders(rows);
  }

  Future<List<Shipment>> _joinOrders(List<Map<String, dynamic>> rows) async {
    if (rows.isEmpty) return [];
    final orderIds = rows.map((r) => r['order_id'] as String).toSet().toList();
    final orders = await BackendService.client
        .from('orders')
        .select(_orderColumns)
        .inFilter('id', orderIds);
    final ordersById = {for (final o in orders) o['id'] as String: o};

    final shipments = rows
        .where((r) => ordersById.containsKey(r['order_id']))
        .map((r) => Shipment.fromRow(r, ordersById[r['order_id']]!))
        .toList();

    return _joinPickupLocations(shipments);
  }

  /// Resolves each shipment's pickup location — the seller of its first
  /// line item — batched into one query, same pattern
  /// `ProductService._joinAndMap` already uses for seller info. Best-effort:
  /// a shipment whose item has no resolvable `product_id`/seller just keeps
  /// a null `pickupLocation` rather than failing the whole list.
  Future<List<Shipment>> _joinPickupLocations(List<Shipment> shipments) async {
    // Final-delivery pickup is at the destination sorting center, never the seller.
    if (shipments.every((s) => s.pickupLocation != null)) return shipments;
    final productIds = shipments
        .where((s) => s.items.isNotEmpty)
        .map((s) => s.items.first.productId)
        .whereType<String>()
        .toSet()
        .toList();
    if (productIds.isEmpty) return shipments;

    final products = await BackendService.client
        .from('products')
        .select('id, seller_id')
        .inFilter('id', productIds);
    final sellerIdByProduct = {
      for (final p in products) p['id'] as String: p['seller_id'] as int,
    };

    final sellerIds = sellerIdByProduct.values.toSet().toList();
    if (sellerIds.isEmpty) return shipments;

    final sellers = await BackendService.client
        .from('users')
        .select(_sellerColumns)
        .inFilter('id', sellerIds);
    final sellersById = {for (final s in sellers) s['id'] as int: s};

    return shipments.map((shipment) {
      final productId = shipment.items.isEmpty
          ? null
          : shipment.items.first.productId;
      final sellerId = sellerIdByProduct[productId];
      final seller = sellersById[sellerId];
      if (seller == null) return shipment;

      final businessName = seller['business_name'] as String?;
      final sellerName = (businessName != null && businessName.isNotEmpty)
          ? businessName
          : '${seller['given_names']} ${seller['last_name']}'.trim();
      final municipality = (seller['municipality'] as String?)?.trim() ?? '';
      final province = (seller['province'] as String?)?.trim() ?? '';
      final location = [
        municipality,
        province,
      ].where((v) => v.isNotEmpty).join(', ');

      return shipment.copyWith(
        sellerName: sellerName.isEmpty ? null : sellerName,
        pickupLocation: location.isEmpty ? null : location,
      );
    }).toList();
  }

  /// Claims an available shipment — atomic on the server (guards against
  /// two riders accepting the same delivery at once).
  Future<void> claim(String shipmentId) async {
    await BackendService.client.rpc(
      'claim_shipment',
      params: {'p_shipment_id': shipmentId},
    );
  }

  /// Advances a delivery's status. The server rejects any transition the
  /// rider isn't allowed to make (see `Shipment.nextStatus`) or shipments
  /// not assigned to the caller. [reason] is required by the server when
  /// [status] is `delivery_failed` (see `update_delivery_status()`).
  Future<void> updateStatus(
    String shipmentId,
    String status, {
    String? reason,
  }) async {
    await BackendService.client.rpc(
      'update_delivery_status',
      params: {
        'p_shipment_id': shipmentId,
        'p_status': status,
        'p_reason': ?reason,
      },
    );
  }

  /// The buyer's real "Confirm Order Received" action — only succeeds if
  /// the caller is that order's buyer and the shipment is `delivered`.
  Future<void> confirmReceived(String shipmentId) async {
    await BackendService.client.rpc(
      'confirm_order_received',
      params: {'p_shipment_id': shipmentId},
    );
  }

  /// Buyer name + contact number for a delivery — only returned if the
  /// caller is the courier assigned to it.
  Future<({String name, String contact})?> fetchBuyerContact(
    String shipmentId,
  ) async {
    final rows = await BackendService.client.rpc(
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

  /// The assigned rider's name/contact for a buyer's own order — only
  /// returned once `courier_id` is set (see `get_my_delivery_rider()`).
  /// Buyer-side use; mirrors `fetchBuyerContact` in reverse.
  Future<({int riderId, String name, String contact, String? photo})?>
  fetchMyRiderInfo(String shipmentId) async {
    final rows = await BackendService.client.rpc(
      'get_my_delivery_rider',
      params: {'p_shipment_id': shipmentId},
    );
    final list = List<Map<String, dynamic>>.from(rows as List);
    if (list.isEmpty) return null;
    return (
      riderId: list.first['rider_id'] as int,
      name: list.first['rider_name']?.toString() ?? 'Not provided',
      contact: list.first['rider_contact']?.toString() ?? '',
      photo: list.first['rider_photo'] == null
          ? null
          : BackendService.client.storage
                .from('profile-images')
                .getPublicUrl('${list.first['rider_photo']}'),
    );
  }

  /// The shipment id/status/courier presence for a buyer's own order, if a
  /// shipment exists yet — used by Buyer's Order Detail screen for the
  /// delivery journey card and (once a rider's assigned) the rider info card.
  Future<({String id, String status, bool hasRider})?>
  fetchShipmentInfoForOrder(String orderId) async {
    final row = await BackendService.client
        .from('shipments')
        .select('id, shipping_status, courier_id')
        .eq('order_id', orderId)
        .maybeSingle();
    if (row == null) return null;
    return (
      id: row['id'] as String,
      status: row['shipping_status']?.toString() ?? 'sorted',
      hasRider: row['courier_id'] != null,
    );
  }

  Future<RiderProfile?> fetchMyRiderProfile() async {
    final row = await BackendService.client.rpc('get_my_rider_profile');
    if (row == null) return null;
    final map = Map<String, dynamic>.from(row as Map);
    if (map['user_id'] == null) return null;
    return RiderProfile.fromMap(map);
  }

  /// Toggles the rider's Online/Offline status shown on the Dashboard.
  Future<void> setOnlineStatus(bool online) {
    return RiderService.availability(online);
  }
}
