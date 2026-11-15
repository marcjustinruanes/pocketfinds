import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_motion.dart';
import '../theme/app_spacing.dart';

/// A reusable "pick one from a searchable list" bottom sheet — same visual
/// language as the checkout payment-method picker (title, filter field,
/// ListTile rows), reused for the address form's Region/Province/City/
/// Barangay pickers instead of pulling in a new dropdown-search package.
class PickerOption {
  final String value;
  final String label;
  const PickerOption(this.value, this.label);
}

Future<String?> showSearchablePicker({
  required BuildContext context,
  required String title,
  required List<PickerOption> options,
  String? selectedValue,
  String emptyMessage = 'No results.',
}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (sheetContext) => _SearchablePickerSheet(
      title: title,
      options: options,
      selectedValue: selectedValue,
      emptyMessage: emptyMessage,
    ),
  );
}

class _SearchablePickerSheet extends StatefulWidget {
  final String title;
  final List<PickerOption> options;
  final String? selectedValue;
  final String emptyMessage;

  const _SearchablePickerSheet({
    required this.title,
    required this.options,
    required this.selectedValue,
    required this.emptyMessage,
  });

  @override
  State<_SearchablePickerSheet> createState() => _SearchablePickerSheetState();
}

class _SearchablePickerSheetState extends State<_SearchablePickerSheet> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _query.isEmpty
        ? widget.options
        : widget.options
              .where(
                (o) => o.label.toLowerCase().contains(_query.toLowerCase()),
              )
              .toList();

    return AnimatedPadding(
      duration: AppMotion.fast,
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.75,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.title,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: TextField(
                controller: _searchController,
                onChanged: (v) => setState(() => _query = v),
                decoration: const InputDecoration(
                  hintText: 'Search',
                  prefixIcon: Icon(Icons.search),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Expanded(
              child: widget.options.isEmpty
                  ? Center(
                      child: Text(
                        widget.emptyMessage,
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    )
                  : filtered.isEmpty
                  ? Center(
                      child: Text(
                        'No matches for "$_query".',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    )
                  : ListView.builder(
                      itemCount: filtered.length,
                      itemBuilder: (context, index) {
                        final option = filtered[index];
                        final selected = option.value == widget.selectedValue;
                        return ListTile(
                          title: Text(option.label),
                          trailing: selected
                              ? Icon(Icons.check_circle, color: AppColors.pink)
                              : null,
                          onTap: () => Navigator.of(context).pop(option.value),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
