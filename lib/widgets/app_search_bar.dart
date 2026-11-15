import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// The search box from the site header
/// ("Search for products, brands and categories"). On Guest Home it filters
/// the already-loaded product list client-side ([onChanged]); on Buyer Home
/// submitting ([onSubmitted]) runs a real server-side search across all
/// active products. [onCameraTap], when given, adds a real "search by
/// photo" camera icon inside the field (visual product search) — omitted
/// entirely at call sites that don't wire it up, so this stays a no-op
/// change everywhere else.
class AppSearchBar extends StatefulWidget {
  final ValueChanged<String> onChanged;
  final ValueChanged<String>? onSubmitted;
  final String? initialValue;
  final VoidCallback? onCameraTap;
  final bool readOnly;
  final VoidCallback? onTap;

  const AppSearchBar({
    super.key,
    required this.onChanged,
    this.onSubmitted,
    this.initialValue,
    this.onCameraTap,
    this.readOnly = false,
    this.onTap,
  });

  @override
  State<AppSearchBar> createState() => _AppSearchBarState();
}

class _AppSearchBarState extends State<AppSearchBar> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialValue,
  );

  @override
  void didUpdateWidget(covariant AppSearchBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialValue != oldWidget.initialValue) {
      _controller.text = widget.initialValue ?? '';
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(color: AppColors.border),
    );
    return TextField(
      readOnly: widget.readOnly,
      onTap: widget.onTap,
      controller: _controller,
      onChanged: widget.onChanged,
      onSubmitted: widget.onSubmitted,
      textInputAction: TextInputAction.search,
      style: const TextStyle(fontSize: 13),
      decoration: InputDecoration(
        hintText: 'Search products and categories',
        hintStyle: TextStyle(fontSize: 12, color: AppColors.textMuted),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 12,
        ),
        border: border,
        enabledBorder: border,
        focusedBorder: border.copyWith(
          borderSide: BorderSide(color: AppColors.pink, width: 1.5),
        ),
        prefixIcon: Container(
          width: 44,
          margin: const EdgeInsets.only(right: 4),
          decoration: BoxDecoration(
            color: AppColors.pinkSoft,
            border: Border(right: BorderSide(color: AppColors.border)),
            borderRadius: const BorderRadius.horizontal(
              left: Radius.circular(7),
            ),
          ),
          child: Icon(Icons.search, size: 20, color: AppColors.pinkDark),
        ),
        prefixIconConstraints: const BoxConstraints(
          minWidth: 44,
          minHeight: 46,
        ),
        suffixIcon: widget.onCameraTap == null
            ? null
            : IconButton(
                icon: Icon(
                  Icons.camera_alt_outlined,
                  color: AppColors.pink,
                  size: 20,
                ),
                tooltip: 'Search by photo',
                onPressed: widget.onCameraTap,
              ),
      ),
    );
  }
}
