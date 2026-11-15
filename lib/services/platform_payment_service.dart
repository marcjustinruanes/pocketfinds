import 'backend_service.dart';

String paymentStatusLabel(String status) => switch (status) {
  'pending_verification' => 'Awaiting Payment Verification',
  'paid' => 'Payment Verified',
  'underpaid' => 'Additional Payment Needed',
  'rejected' => 'Payment Verification Failed',
  'refunded' => 'Refunded',
  'expired' => 'Payment Expired',
  _ => 'Pending Payment',
};

class PlatformPaymentService {
  final BackendClient client;
  PlatformPaymentService({BackendClient? client})
    : client = client ?? BackendService.client;
  Future<Map<String, dynamic>> configuration() async =>
      Map<String, dynamic>.from(await client.request('payments/config') as Map);
  Future<Map<String, dynamic>> detail(String orderId) async =>
      Map<String, dynamic>.from(
        await client.request('payments/detail', {'order_id': orderId}) as Map,
      );
  Future<Map<String, dynamic>> submit({
    required String orderId,
    required String submissionId,
    required String reference,
    required String path,
    required String amount,
  }) async => Map<String, dynamic>.from(
    await client.request('payments/submit', {
      'order_id': orderId,
      'submission_id': submissionId,
      'reference': reference,
      'path': path,
      'amount': amount,
    }) as Map,
  );
}
