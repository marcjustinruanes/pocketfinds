import 'backend_service.dart';
import 'delivery_service.dart';

class RiderWebModuleService {
  final BackendClient client;
  RiderWebModuleService({BackendClient? client})
    : client = client ?? BackendService.client;
  Future<Map<String, dynamic>> call(
    String action, [
    Map<String, dynamic> data = const {},
  ]) async => Map<String, dynamic>.from(
    await client.request('rider/web-module', {'action': action, ...data})
        as Map,
  );

  Future<Map<String, dynamic>> transfers() => call('transfers');
  Future<Map<String, dynamic>> payouts() => call('payouts');
  Future<void> confirmTransfer(String id, String requestId) async {
    await call('confirm-transfer', {'id': id, 'request_id': requestId});
    DeliveryService.updates.add(null);
  }

  Future<void> destination(String name, String number, String requestId) async {
    await call('destination', {
      'channel': 'gcash',
      'institution': 'GCash',
      'account_name': name.trim(),
      'account_number': number.trim(),
      'request_id': requestId,
    });
  }

  Future<void> acknowledge(String id, String source) async {
    await call('acknowledge', {'id': id, 'source': source, 'received': true});
    DeliveryService.updates.add(null);
  }
}
