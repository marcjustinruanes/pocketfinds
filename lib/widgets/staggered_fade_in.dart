import 'package:flutter/material.dart';

import '../theme/app_motion.dart';

/// Wraps one item of a list/grid so it fades in and rises slightly on
/// first appearance, staggered by [index] — the "cards appearing with
/// subtle motion" piece of the app's motion system. Runs once per item
/// (a `TweenAnimationBuilder` driven straight from 0→1 the moment this
/// widget is first built), not on every rebuild/scroll.
///
/// The per-item delay is capped so a long list doesn't take forever to
/// finish appearing — everything past [maxStaggeredIndex] starts at the
/// same (maximum) delay instead of a strictly increasing one.
class StaggeredFadeIn extends StatelessWidget {
  final int index;
  final Widget child;

  static const int maxStaggeredIndex = 8;
  static const Duration _step = Duration(milliseconds: 40);
  static const Duration _duration = Duration(milliseconds: 500);

  const StaggeredFadeIn({super.key, required this.index, required this.child});

  @override
  Widget build(BuildContext context) {
    final delayMs = _step.inMilliseconds * index.clamp(0, maxStaggeredIndex);
    final start = (delayMs / _duration.inMilliseconds).clamp(0.0, 0.9);

    return TweenAnimationBuilder<double>(
      key: ValueKey(index),
      tween: Tween(begin: 0, end: 1),
      duration: _duration,
      curve: Interval(start, 1.0, curve: AppMotion.curve),
      builder: (context, value, child) {
        return Opacity(
          opacity: value,
          child: Transform.translate(
            offset: Offset(0, (1 - value) * 12),
            child: child!,
          ),
        );
      },
      child: child,
    );
  }
}
