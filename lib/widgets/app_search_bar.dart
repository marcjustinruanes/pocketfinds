import 'package:flutter/material.dart';

/// The search box from the site header
/// ("Search for products, brands and categories"). On Guest Home it filters
/// the already-loaded product list client-side ([onChanged]); on Buyer Home
/// submitting ([onSubmitted]) runs a real server-side search across all
/// active products.
class AppSearchBar extends StatelessWidget {
  final ValueChanged<String> onChanged;
  final ValueChanged<String>? onSubmitted;
  final String? initialValue;

  const AppSearchBar({
    super.key,
    required this.onChanged,
    this.onSubmitted,
    this.initialValue,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: initialValue == null
          ? null
          : TextEditingController(text: initialValue),
      onChanged: onChanged,
      onSubmitted: onSubmitted,
      textInputAction: TextInputAction.search,
      decoration: const InputDecoration(
        hintText: 'Search for products, brands and categories',
        prefixIcon: Icon(Icons.search),
      ),
    );
  }
}
