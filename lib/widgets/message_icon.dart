import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// The app's "Messages" icon — a real hand-drawn SVG (two overlapping chat
/// bubbles, the front one with 3 dots) instead of a stock Material glyph,
/// shared everywhere a "go to Messages" affordance appears (Buyer Home's
/// header, Rider Dashboard's bell) so it's the same icon everywhere.
class MessageIcon extends StatelessWidget {
  final double size;
  final Color color;

  const MessageIcon({super.key, this.size = 20, required this.color});

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      'assets/icons/message_icon.svg',
      width: size,
      height: size,
      colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
    );
  }
}
