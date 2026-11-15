import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// The Dashboard header's large, obvious Online/Offline switch — a single
/// tappable pill (not a tiny native `Switch`) so it reads clearly at a
/// glance and is easy to hit one-handed. [onChanged] is awaited by the
/// caller (persists via `ShipmentService.setOnlineStatus`); this widget
/// shows a brief loading state of its own while that's in flight so a slow
/// network doesn't make the tap feel like it did nothing.
class OnlineStatusToggle extends StatefulWidget {
  final bool online;
  final Future<void> Function(bool online) onChanged;

  const OnlineStatusToggle({
    super.key,
    required this.online,
    required this.onChanged,
  });

  @override
  State<OnlineStatusToggle> createState() => _OnlineStatusToggleState();
}

class _OnlineStatusToggleState extends State<OnlineStatusToggle> {
  bool _busy = false;

  Future<void> _toggle() async {
    setState(() => _busy = true);
    try {
      await widget.onChanged(!widget.online);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final online = widget.online;
    final color = online ? AppColors.success : AppColors.textMuted;
    final background = online ? AppColors.successSoft : AppColors.neutralSoft;

    return GestureDetector(
      onTap: _busy ? null : _toggle,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: 0.35)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_busy)
              SizedBox(
                width: 10,
                height: 10,
                child: CircularProgressIndicator(
                  strokeWidth: 1.6,
                  color: color,
                ),
              )
            else
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              ),
            const SizedBox(width: 8),
            Text(
              online ? 'Online' : 'Offline',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
            const SizedBox(width: 6),
            Icon(
              online ? Icons.toggle_on : Icons.toggle_off_outlined,
              color: color,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }
}
