import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_shadows.dart';

/// The heart toggle used on `ProductCard`, Product Detail, and the
/// Wishlist grid — a real, persisted toggle (`WishlistService`), not just
/// a visual state. [onChanged] is awaited so a slow network doesn't make
/// the tap feel like it did nothing.
class WishlistButton extends StatefulWidget {
  final bool wishlisted;
  final Future<void> Function(bool wishlisted) onChanged;
  final double size;

  const WishlistButton({
    super.key,
    required this.wishlisted,
    required this.onChanged,
    this.size = 18,
  });

  @override
  State<WishlistButton> createState() => _WishlistButtonState();
}

class _WishlistButtonState extends State<WishlistButton> {
  bool _busy = false;

  Future<void> _toggle() async {
    setState(() => _busy = true);
    try {
      await widget.onChanged(!widget.wishlisted);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _busy ? null : _toggle,
      child: Container(
        width: widget.size + 20,
        height: widget.size + 20,
        decoration: BoxDecoration(
          color: AppColors.white,
          shape: BoxShape.circle,
          boxShadow: AppShadows.sm,
        ),
        child: Center(
          child: _busy
              ? SizedBox(
                  width: widget.size * 0.7,
                  height: widget.size * 0.7,
                  child: CircularProgressIndicator(
                    strokeWidth: 1.8,
                    color: AppColors.pink,
                  ),
                )
              : Icon(
                  widget.wishlisted ? Icons.favorite : Icons.favorite_border,
                  size: widget.size,
                  color: widget.wishlisted
                      ? AppColors.pink
                      : AppColors.textMuted,
                ),
        ),
      ),
    );
  }
}
