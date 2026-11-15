import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import 'backend_service.dart';
import 'delivery_service.dart';

class RiderService {
  static final isAvailable = ValueNotifier<bool>(false);
  static final offers = <String, Map<String, dynamic>>{};
  static Future<Position> location() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw Exception('Turn on location services to receive jobs.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception('Location permission is needed. Enable it in Settings.');
    }
    return Geolocator.getCurrentPosition().timeout(const Duration(seconds: 20));
  }

  static Future<void> availability(bool enabled) async {
    try {
      await BackendService.client.request('rider/availability', {
        'is_available': enabled,
      });
      isAvailable.value = enabled;
    } catch (_) {
      // A failed request must not silently change the rider's web availability.
      rethrow;
    }
  }

  static Future<void> refreshAvailability() async {
    final profile = await BackendService.client.rpc('get_my_rider_profile');
    isAvailable.value = (profile as Map)['is_available'] == true;
  }

  static Future<void> refreshOffers() async {
    final rows = await BackendService.client.request('rider/assignments', {});
    offers.clear();
    for (final row in rows as List) {
      offers['${row['shipment_id']}'] = Map<String, dynamic>.from(row as Map);
    }
  }

  static Future<void> decline(String shipment) async {
    final offer = offers[shipment];
    if (offer == null) return;
    await BackendService.client.request('rider/decline', {'id': offer['id']});
    offers.remove(shipment);
    DeliveryService.updates.add(null);
  }

  static Future<Map<String, dynamic>> earnings() async =>
      Map<String, dynamic>.from(
        await BackendService.client.request('rider/earnings', {}) as Map,
      );
}
