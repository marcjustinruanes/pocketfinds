import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import 'backend_service.dart';
import 'delivery_service.dart';
import '../utils/friendly_error.dart';

class RiderService {
  static final isAvailable = ValueNotifier<bool>(false);
  static final offers = <String, Map<String, dynamic>>{};
  static Future<Position> location() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const UserVisibleException(
        'Location is turned off. Turn on Location, then retry. Your delivery has not been confirmed.',
      );
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const UserVisibleException(
        'Allow PocketFinds to use your location in App settings, then retry. Your delivery has not been confirmed.',
      );
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
    final online = (profile as Map)['is_available'] == true;
    isAvailable.value = online;
    // Re-post while online to keep availability_updated_at fresh (server
    // treats the timestamp as stale after 2 hours and hides the rider from
    // the eligible dropdown even if is_available is still true in the DB).
    if (online) {
      try {
        await BackendService.client.request('rider/availability', {
          'is_available': true,
        });
      } catch (_) {
        // Best-effort heartbeat — do not surface errors to the rider.
      }
    }
  }

  static Future<void> refreshOffers() async {
    final rows = await BackendService.client.request('rider/assignments', {});
    offers.clear();
    for (final row in rows as List) {
      offers['${row['shipment_id']}'] = Map<String, dynamic>.from(row as Map);
    }
  }

  static Future<void> decline(String shipment) async {
    var offer = offers[shipment];
    if (offer == null) {
      await refreshOffers();
      offer = offers[shipment];
    }
    if (offer == null) {
      throw Exception(
        'This request is no longer available. Refresh your tasks.',
      );
    }
    await BackendService.client.request('rider/decline', {'id': offer['id']});
    offers.remove(shipment);
    DeliveryService.updates.add(null);
  }

  static Future<Map<String, dynamic>> earnings() async =>
      Map<String, dynamic>.from(
        await BackendService.client.request('rider/earnings', {}) as Map,
      );
}
