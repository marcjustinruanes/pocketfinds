import 'package:flutter/material.dart';

import '../theme/app_motion.dart';

/// A drop-in replacement for [IndexedStack] that fades in the newly-selected
/// child instead of an instant cut, while keeping `IndexedStack`'s real
/// point — every child is built once and kept alive (so switching bottom-nav
/// tabs doesn't re-fetch each tab's data), it just also looks intentional
/// when you switch.
class AnimatedIndexedStack extends StatefulWidget {
  final int index;
  final List<Widget> children;

  const AnimatedIndexedStack({
    super.key,
    required this.index,
    required this.children,
  });

  @override
  State<AnimatedIndexedStack> createState() => _AnimatedIndexedStackState();
}

class _AnimatedIndexedStackState extends State<AnimatedIndexedStack> {
  int? _fadingIndex;

  @override
  void didUpdateWidget(AnimatedIndexedStack oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.index != widget.index) {
      setState(() => _fadingIndex = widget.index);
    }
  }

  @override
  Widget build(BuildContext context) {
    return IndexedStack(
      index: widget.index,
      children: List.generate(widget.children.length, (i) {
        final child = TickerMode(
          enabled: i == widget.index,
          child: widget.children[i],
        );
        if (i != widget.index || i != _fadingIndex) return child;
        return TweenAnimationBuilder<double>(
          key: ValueKey(widget.index),
          tween: Tween(begin: 0, end: 1),
          duration: AppMotion.medium,
          curve: AppMotion.curve,
          onEnd: () {
            if (mounted) setState(() => _fadingIndex = null);
          },
          builder: (context, value, c) => Opacity(opacity: value, child: c),
          child: child,
        );
      }),
    );
  }
}
