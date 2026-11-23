import 'package:flutter/material.dart';

/// Existing header actions with the website's circular outline treatment.
class StorefrontHeaderAction extends StatelessWidget {
  final Widget icon;
  final String tooltip;
  final VoidCallback onPressed;
  const StorefrontHeaderAction({
    super.key,
    required this.icon,
    required this.tooltip,
    required this.onPressed,
  });

  static ButtonStyle get buttonStyle => IconButton.styleFrom(
    fixedSize: const Size(44, 44),
    padding: const EdgeInsets.all(10),
    foregroundColor: const Color(0xFF8D7A85),
    side: const BorderSide(color: Color(0xFFE4E4E4), width: 1.3),
    shape: const CircleBorder(),
  );

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 3),
    child: IconButton(
      tooltip: tooltip,
      onPressed: onPressed,
      style: buttonStyle,
      icon: icon,
    ),
  );
}
