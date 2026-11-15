import 'package:flutter/material.dart';

/// A one-shot scale+fade "pop" for the app's real success moments (order
/// placed, delivery confirmed) — plays once on first build instead of the
/// icon just appearing instantly.
class SuccessPop extends StatelessWidget {
  final Widget child;

  const SuccessPop({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 500),
      curve: Curves.easeOutBack,
      builder: (context, value, child) => Opacity(
        opacity: value.clamp(0.0, 1.0),
        child: Transform.scale(scale: value, child: child),
      ),
      child: child,
    );
  }
}
