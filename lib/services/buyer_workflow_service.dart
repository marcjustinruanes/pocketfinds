import 'dart:convert';

import 'backend_service.dart';
import 'delivery_service.dart';

class BuyerWorkflowService {
  final BackendClient client;
  String? _buyAgainRequest;
  final _issueRequests = <String, String>{};
  BuyerWorkflowService({BackendClient? client})
    : client = client ?? BackendService.client;
  Future<Map<String, dynamic>> act(
    String action,
    Map<String, dynamic> data,
  ) async => Map<String, dynamic>.from(
    await client.request('buyer-api/$action', data) as Map,
  );
  Future<List<Map<String, dynamic>>> addresses() async =>
      (await client.request('buyer-api/addresses') as List)
          .map((a) => Map<String, dynamic>.from(a as Map))
          .toList();
  Future<Map<String, dynamic>> saveAddress(
    Map<String, dynamic> data, {
    int? id,
  }) async {
    final result = await act(id == null ? 'address-create' : 'address-update', {
      ...data,
      'id': ?id,
    });
    if (result['address'] is Map) {
      return Map<String, dynamic>.from(result['address'] as Map);
    }
    return (await addresses()).firstWhere((a) => a['id'] == id);
  }

  Future<Map<String, dynamic>> buyAgain(String id) async {
    _buyAgainRequest ??= DeliveryService.requestId();
    final result = await act('buy-again', {
      'order_id': id,
      'request_id': _buyAgainRequest,
    });
    _buyAgainRequest = null;
    return result;
  }

  Future<Map<String, dynamic>> reportIssue(
    String id,
    String kind,
    String description,
  ) async {
    final key = jsonEncode([id, kind, description]);
    final request = _issueRequests.putIfAbsent(key, DeliveryService.requestId);
    final result = await act('report-issue', {
      'order_id': id,
      'kind': kind,
      'description': description,
      'request_id': request,
    });
    _issueRequests.remove(key);
    return result;
  }
}
