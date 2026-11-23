import 'dart:io';

import 'backend_service.dart';

/// One order's real payment-verification state — see
/// `sql/2026_09_10_marketplace_revamp.sql`'s `payment_verifications` table.
class PaymentVerification {
  final String orderId;
  final String referenceNumber;
  final String proofPath;
  final String
  status; // pending | submitted | under_review | confirmed | rejected
  final String? rejectionReason;
  final DateTime submittedAt;

  const PaymentVerification({
    required this.orderId,
    required this.referenceNumber,
    required this.proofPath,
    required this.status,
    this.rejectionReason,
    required this.submittedAt,
  });

  factory PaymentVerification.fromRow(Map<String, dynamic> row) {
    return PaymentVerification(
      orderId: row['order_id'] as String,
      referenceNumber: row['reference_number'] as String,
      proofPath: row['proof_path'] as String,
      status: row['status'] as String,
      rejectionReason: row['rejection_reason'] as String?,
      submittedAt:
          DateTime.tryParse(row['submitted_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }

  String get proofUrl => BackendService.client.storage
      .from('payment-proofs')
      .getPublicUrl(proofPath);
}

class PaymentVerificationService {
  static const bucket = 'payment-proofs';

  Future<String> uploadProof(File file) async {
    final authUid = BackendService.client.auth.currentUser?.id;
    if (authUid == null) throw StateError('No active session.');
    final dot = file.path.lastIndexOf('.');
    final ext = dot == -1 ? 'jpg' : file.path.substring(dot + 1).toLowerCase();
    final path =
        '$authUid/${DateTime.now().millisecondsSinceEpoch}_proof.${ext.isEmpty ? 'jpg' : ext}';
    await BackendService.client.storage
        .from(bucket)
        .upload(path, file, fileOptions: const FileOptions(upsert: true));
    return path;
  }

  Future<PaymentVerification> submit({
    required String orderId,
    required String referenceNumber,
    required String proofPath,
  }) async {
    final row = await BackendService.client.rpc(
      'submit_payment_verification',
      params: {
        'p_order_id': orderId,
        'p_reference_number': referenceNumber,
        'p_proof_path': proofPath,
      },
    );
    return PaymentVerification.fromRow(Map<String, dynamic>.from(row as Map));
  }

  Future<PaymentVerification?> fetch(String orderId) async {
    final row = await BackendService.client.rpc(
      'fetch_payment_verification',
      params: {'p_order_id': orderId},
    );
    if (row == null) return null;
    final map = Map<String, dynamic>.from(row as Map);
    if (map['order_id'] == null) return null;
    return PaymentVerification.fromRow(map);
  }
}
