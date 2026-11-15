import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:pocketfinds/services/backend_service.dart';
import 'package:pocketfinds/services/review_service.dart';

void main() {
  for (final orderId in [null, 'eligible-order']) {
    test(
      'review eligibility uses authenticated backend purchase history: $orderId',
      () async {
        final service = ReviewService(
          client: BackendClient(
            transport: MockClient((request) async {
              expect(
                request.url.path,
                endsWith('/rpc/reviewable_product_order'),
              );
              expect(jsonDecode(request.body), {'p_product_id': 'product'});
              return http.Response(jsonEncode({'order_id': orderId}), 200);
            }),
          ),
        );
        expect(await service.findReviewableOrderId(42, 'product'), orderId);
      },
    );
  }
}
