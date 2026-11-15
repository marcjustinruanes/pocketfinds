import 'dart:async';

import 'package:flutter/material.dart';

import '../services/rider_service.dart';
import '../services/delivery_service.dart';
import '../utils/friendly_error.dart';

class RiderAvailability extends StatefulWidget {
  const RiderAvailability({super.key});
  @override
  State<RiderAvailability> createState() => _RiderAvailabilityState();
}

class _RiderAvailabilityState extends State<RiderAvailability> {
  bool _busy = false;
  Future<void> _toggle() async {
    setState(() => _busy = true);
    try {
      await RiderService.availability(!RiderService.isAvailable.value);
      DeliveryService.updates.add(null);
    } catch (e) {
      if (mounted) {
        await showDialog<void>(
          context: context,
          builder: (c) => AlertDialog(
            title: const Text('Availability'),
            content: Text(friendlyError(e)),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(c),
                child: const Text('Close'),
              ),
            ],
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<bool>(
    valueListenable: RiderService.isAvailable,
    builder: (_, online, _) => OutlinedButton.icon(
      onPressed: _busy ? null : _toggle,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(48, 48),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      ),
      icon: _busy
          ? const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Icon(
              online ? Icons.pause_circle_outline : Icons.play_circle_outline,
              size: 20,
            ),
      label: Text(
        _busy
            ? 'Updating…'
            : online
            ? 'Go Offline'
            : 'Go Online',
      ),
    ),
  );
}

class RiderOfferCountdown extends StatefulWidget {
  final String shipmentId;
  const RiderOfferCountdown({super.key, required this.shipmentId});
  @override
  State<RiderOfferCountdown> createState() => _RiderOfferCountdownState();
}

class _RiderOfferCountdownState extends State<RiderOfferCountdown> {
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final offer = RiderService.offers[widget.shipmentId];
    if (offer == null) return const SizedBox.shrink();
    final raw = '${offer['expires_at']}';
    final end = DateTime.tryParse(
      raw.contains('T') ? raw : '${raw.replaceFirst(' ', 'T')}Z',
    );
    final seconds = end?.difference(DateTime.now()).inSeconds ?? 0;
    return Text(
      seconds > 0
          ? 'Offer expires in ${seconds}s · ${offer['leg'] == 'pickup' ? '₱30 pickup' : '₱50 delivery'}'
          : 'Offer expired — refresh jobs',
    );
  }
}
