import 'platform_payment_service.dart';

/// Native mobile presentation backed by the website's purchase and receipt records.
class WebsitePaymentService extends PlatformPaymentService {
  WebsitePaymentService({super.client});
  Map<String, dynamic>? _configuration;
  @override
  Future<Map<String, dynamic>> configuration() async =>
      _configuration ??
      Map<String, dynamic>.from(
        await client.request('gcash/configuration') as Map,
      );
  @override
  Future<Map<String, dynamic>> detail(String orderId) async {
    final data = Map<String, dynamic>.from(
      await client.request('gcash/order-payment', {'order_id': orderId}) as Map,
    );
    _configuration = Map<String, dynamic>.from(data['configuration'] as Map);
    return data;
  }

  @override
  Future<Map<String, dynamic>> submit({
    required String orderId,
    required String submissionId,
    required String reference,
    required String path,
    required String amount,
  }) async => Map<String, dynamic>.from(
    await client.request('gcash/order-proof', {
      'order_id': orderId,
      'submission_id': submissionId,
      'path': path,
    }) as Map,
  );
}
