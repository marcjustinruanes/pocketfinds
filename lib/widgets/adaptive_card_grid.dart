import 'package:flutter/material.dart';

/// Equal-width cards that gain height with content and reflow with large text.
class AdaptiveCardGrid extends StatelessWidget {
  final List<Widget> children;
  final double minimumWidth;
  final int maxColumns;
  const AdaptiveCardGrid({
    super.key,
    required this.children,
    this.minimumWidth = 104,
    this.maxColumns = 3,
  });

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, size) {
      final scale = MediaQuery.textScalerOf(context).scale(14) / 14;
      final columns = ((size.maxWidth + 12) / (minimumWidth * scale + 12))
          .floor()
          .clamp(1, maxColumns);
      return Column(
        children: [
          for (var start = 0; start < children.length; start += columns) ...[
            if (start > 0) const SizedBox(height: 12),
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (var col = 0; col < columns; col++) ...[
                    if (col > 0) const SizedBox(width: 12),
                    Expanded(
                      child: start + col < children.length
                          ? children[start + col]
                          : const SizedBox.shrink(),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ],
      );
    },
  );
}
