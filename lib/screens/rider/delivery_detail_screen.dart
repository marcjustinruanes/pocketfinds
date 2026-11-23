import 'package:flutter/material.dart';
import '../../models/shipment.dart';
import '../../services/shipment_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../utils/friendly_error.dart';
import '../../utils/status_style.dart';
import '../../widgets/titled_card.dart';

const _statusLabels = {
  'available': 'Available',
  'accepted': 'Accepted',
  'picked_up': 'Picked Up',
  'out_for_delivery': 'Out for Delivery',
  'delivered': 'Delivered',
  'completed': 'Completed',
};

// The rider-visible delivery stages, in order — used to render a progress
// stepper once a delivery is assigned (mirrors the same stage sequence
// `Shipment.nextStatus` walks through).
const _deliveryStages = ['accepted', 'picked_up', 'out_for_delivery', 'delivered'];

/// Delivery details for a rider — mirrors the info Laravel's
/// `LogisticsController` shipment views show, scoped to what a rider (not
/// logistics staff) should see: order items/total/delivery area always,
/// and buyer contact info only once the delivery is actually assigned to
/// this rider (matches `get_delivery_contact_info()`'s own enforcement).
class DeliveryDetailScreen extends StatefulWidget {
  final Shipment shipment;

  const DeliveryDetailScreen({super.key, required this.shipment});

  @override
  State<DeliveryDetailScreen> createState() => _DeliveryDetailScreenState();
}

class _DeliveryDetailScreenState extends State<DeliveryDetailScreen> {
  final _shipmentService = ShipmentService();
  late Shipment _shipment = widget.shipment;
  bool _busy = false;
  ({String name, String contact})? _contact;

  bool get _isAssignedToMe => _shipment.courierId != null;

  @override
  void initState() {
    super.initState();
    if (_isAssignedToMe) _loadContact();
  }

  Future<void> _loadContact() async {
    try {
      final contact = await _shipmentService.fetchBuyerContact(_shipment.id);
      if (mounted) setState(() => _contact = contact);
    } catch (_) {
      // Non-fatal — contact info just won't show.
    }
  }

  Future<void> _claim() async {
    setState(() => _busy = true);
    try {
      await _shipmentService.claim(_shipment.id);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Delivery accepted!')));
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not accept delivery: ${friendlyError(e)}')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _advanceStatus() async {
    final next = _shipment.nextStatus;
    if (next == null) return;
    setState(() => _busy = true);
    try {
      await _shipmentService.updateStatus(_shipment.id, next);
      if (!mounted) return;
      setState(() {
        _shipment = Shipment(
          id: _shipment.id,
          orderId: _shipment.orderId,
          shippingStatus: next,
          orderNumber: _shipment.orderNumber,
          items: _shipment.items,
          total: _shipment.total,
          shippingAddress: _shipment.shippingAddress,
          createdAt: _shipment.createdAt,
          courierId: _shipment.courierId,
        );
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Status updated to ${_statusLabels[next] ?? next}')),
      );
      if (next == 'delivered') Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Could not update status: ${friendlyError(e)}')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_shipment.orderNumber)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TitledCard(
            title: 'Status',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Builder(builder: (context) {
                  final style = StatusStyle.forOrderStatus(_shipment.shippingStatus);
                  return Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: style.background,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      _statusLabels[_shipment.shippingStatus] ?? _shipment.statusLabel,
                      style: TextStyle(fontWeight: FontWeight.w700, color: style.color, fontSize: 12),
                    ),
                  );
                }),
                if (_isAssignedToMe) ...[
                  const SizedBox(height: AppSpacing.lg),
                  _buildStepper(),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (_isAssignedToMe)
            TitledCard(
              title: 'Buyer contact',
              child: _contact == null
                  ? Text(
                      'Loading…',
                      style: TextStyle(color: AppColors.textMuted),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(_contact!.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                        Text(_contact!.contact, style: TextStyle(color: AppColors.textMuted)),
                      ],
                    ),
            ),
          if (_isAssignedToMe) const SizedBox(height: 16),
          TitledCard(
            title: 'Delivery address',
            child: Text(_shipment.deliverTo.isEmpty ? 'Not provided' : _shipment.deliverTo),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Items',
            child: Column(
              children: _shipment.items.map((item) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Expanded(child: Text('${item.name} × ${item.qty}', style: const TextStyle(fontSize: 13))),
                      Text(formatPeso(item.price * item.qty)),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 16),
          TitledCard(
            title: 'Order total',
            child: Text(
              formatPeso(_shipment.total),
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.pinkDark),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: _buildActionButton(),
        ),
      ),
    );
  }

  Widget _buildStepper() {
    final currentIndex = _deliveryStages.indexOf(_shipment.shippingStatus);
    return Row(
      children: List.generate(_deliveryStages.length, (index) {
        final done = currentIndex >= 0 && index <= currentIndex;
        final isLast = index == _deliveryStages.length - 1;
        return Expanded(
          child: Row(
            children: [
              Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: done ? AppColors.pink : AppColors.border,
                ),
                alignment: Alignment.center,
                child: done
                    ? const Icon(Icons.check, size: 13, color: Colors.white)
                    : null,
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    height: 3,
                    color: currentIndex > index ? AppColors.pink : AppColors.border,
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildActionButton() {
    if (!_isAssignedToMe) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: _busy ? null : _claim,
          child: _busy
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Accept Delivery'),
        ),
      );
    }
    final next = _shipment.nextStatus;
    if (next == null) {
      return const SizedBox.shrink();
    }
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: _busy ? null : _advanceStatus,
        child: _busy
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
            : Text('Mark as ${_statusLabels[next] ?? next}'),
      ),
    );
  }

}
