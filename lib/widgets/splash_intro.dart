import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme/app_colors.dart';

/// One category icon in the intro's opening burst — where it starts on
/// screen (as a fraction of half-width/half-height, so values run roughly
/// -1.6..1.6) and how far into the timeline it begins converging.
class _SplashIcon {
  final IconData icon;
  final Alignment start;
  final double delay;
  final bool onGradient;

  const _SplashIcon(
    this.icon,
    this.start,
    this.delay, {
    this.onGradient = false,
  });
}

/// The app's one-shot opening animation: six category icons (matching
/// [iconForCategory]'s real keyword set — pets, electronics, women's/men's
/// apparel, kids/baby, home & garden) drift in from the edges, converge and
/// "mix" at the center, then hand off to the PocketFinds title.
///
/// Pure presentation — plays once and holds on the finished title; nothing
/// here decides when to navigate away. `main.dart` shows this behind the
/// real session resolution and waits out [totalDuration] before pushing the
/// resolved destination, so the reveal always finishes even on a fast
/// network and never gets cut short.
class SplashIntro extends StatefulWidget {
  static const totalDuration = Duration(milliseconds: 1800);

  const SplashIntro({super.key});

  @override
  State<SplashIntro> createState() => _SplashIntroState();
}

class _SplashIntroState extends State<SplashIntro>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  // Six icons, one per real category keyword group in `category_icons.dart`,
  // entering from spread-out corners/edges so the converge reads as
  // "gathering in from everywhere" rather than a single side.
  static const _icons = <_SplashIcon>[
    _SplashIcon(Icons.pets, Alignment(-1.5, -1.35), 0.00),
    _SplashIcon(
      Icons.devices_other,
      Alignment(1.5, -1.3),
      0.05,
      onGradient: true,
    ),
    _SplashIcon(Icons.checkroom, Alignment(0, -1.7), 0.10),
    _SplashIcon(
      Icons.checkroom_outlined,
      Alignment(-1.55, 1.3),
      0.05,
      onGradient: true,
    ),
    _SplashIcon(Icons.child_friendly, Alignment(1.55, 1.35), 0.10),
    _SplashIcon(
      Icons.chair_outlined,
      Alignment(0, 1.7),
      0.02,
      onGradient: true,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: SplashIntro.totalDuration,
    )..forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          final t = _controller.value;
          return Stack(
            fit: StackFit.expand,
            children: [
              for (final entry in _icons) _buildIcon(entry, t),
              _buildMixGlow(t),
              _buildTitle(t),
            ],
          );
        },
      ),
    );
  }

  Widget _buildIcon(_SplashIcon spec, double t) {
    // Each icon gets its own slice of the timeline (staggered by `delay`)
    // so they don't all move in lockstep — still one shared easing curve
    // everywhere, per the app's motion tokens.
    const span = 0.5;
    final start = 0.04 + spec.delay;
    final end = (start + span).clamp(0.0, 1.0);
    final progress = Curves.easeOutCubic.transform(
      ((t - start) / (end - start)).clamp(0.0, 1.0),
    );

    // Gentle floating drift while still out on its own, dying out as it
    // nears the center — a hint of life without looking like a bounce.
    final bob =
        math.sin((t * 2 * math.pi * 1.6) + spec.delay * 10) *
        0.05 *
        (1 - progress);
    final alignment = Alignment.lerp(spec.start, Alignment.center, progress)!;
    final drifted = Alignment(alignment.x, alignment.y + bob);

    final appear = ((t - start) / 0.14).clamp(0.0, 1.0);
    final mergeStart = end - 0.22;
    final merge = ((t - mergeStart) / (end - mergeStart)).clamp(0.0, 1.0);
    final opacity = t <= start ? 0.0 : (appear * (1 - merge)).clamp(0.0, 1.0);
    final scale = (0.55 + 0.55 * appear - 0.35 * merge).clamp(0.0, 1.1);
    final rotation = (1 - progress) * (spec.onGradient ? -0.12 : 0.12);

    if (opacity <= 0) return const SizedBox.shrink();

    return Align(
      alignment: drifted,
      child: Opacity(
        opacity: opacity,
        child: Transform.rotate(
          angle: rotation,
          child: Transform.scale(scale: scale, child: _iconChip(spec)),
        ),
      ),
    );
  }

  Widget _iconChip(_SplashIcon spec) {
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: spec.onGradient ? null : AppColors.white,
        gradient: spec.onGradient
            ? LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: AppColors.pinkGradient,
              )
            : null,
        border: spec.onGradient
            ? null
            : Border.all(color: AppColors.pinkBorder),
        boxShadow: [
          BoxShadow(
            color: AppColors.pink.withValues(alpha: 0.18),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Icon(
        spec.icon,
        size: 26,
        color: spec.onGradient ? AppColors.white : AppColors.pink,
      ),
    );
  }

  /// A soft radial burst right as the icons meet — the visual "mixing
  /// together" beat that bridges the icons dissolving and the title
  /// resolving in their place.
  Widget _buildMixGlow(double t) {
    const start = 0.42;
    const peak = 0.58;
    const end = 0.74;
    double intensity;
    if (t <= start || t >= end) {
      intensity = 0;
    } else if (t <= peak) {
      intensity = (t - start) / (peak - start);
    } else {
      intensity = 1 - (t - peak) / (end - peak);
    }
    intensity = intensity.clamp(0.0, 1.0);
    if (intensity <= 0) return const SizedBox.shrink();

    final size = 120 + 90 * intensity;
    return Align(
      alignment: Alignment.center,
      child: Opacity(
        opacity: intensity * 0.55,
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(
              colors: [
                AppColors.pink.withValues(alpha: 0.35),
                AppColors.pink.withValues(alpha: 0.0),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildTitle(double t) {
    const start = 0.5;
    const end = 0.78;
    final progress = Curves.easeOutCubic.transform(
      ((t - start) / (end - start)).clamp(0.0, 1.0),
    );
    if (progress <= 0) return const SizedBox.shrink();

    final scale = 0.85 + 0.15 * progress;

    return Align(
      alignment: Alignment.center,
      child: Opacity(
        opacity: progress,
        child: Transform.scale(
          scale: scale,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: AppColors.pinkGradient,
                  ),
                  boxShadow: [
                    // A restrained brand-pink glow — matches how the rest
                    // of the app treats its pink accents, just a touch
                    // softer and wider for this one reveal moment.
                    BoxShadow(
                      color: AppColors.pink.withValues(alpha: 0.35 * progress),
                      blurRadius: 28,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Image.asset(
                    'assets/brand/logo.png',
                    semanticLabel: 'PocketFinds logo',
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'PocketFinds',
                style: GoogleFonts.fraunces(
                  fontSize: 30,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
