import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'brand_wordmark.dart';
import '../screens/guest/guest_home_screen.dart';

/// Compact website-modal styling on phones; extended brand panel on wide screens.
class HeroAuthScaffold extends StatelessWidget {
  final Widget child;
  final Widget? footer;
  final Widget? bottomActions;
  final VoidCallback? onBack;
  final String? brandTitle, brandDescription;
  final List<String> brandPoints;
  final bool registration;
  final ScrollController? scrollController;
  const HeroAuthScaffold({
    super.key,
    required this.child,
    this.footer,
    this.bottomActions,
    this.onBack,
    this.brandTitle,
    this.brandDescription,
    this.registration = false,
    this.scrollController,
    this.brandPoints = const [
      'Secure account access',
      'Buyer and Rider accounts',
      'Simple and responsive experience',
    ],
  });

  void _browseAsGuest(BuildContext context) {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const GuestHomeScreen()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(
      title: const FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.centerLeft,
        child: BrandWordmark(),
      ),
      titleSpacing: 4,
      backgroundColor: AppColors.pageBackground,
      leading: IconButton(
        tooltip: 'Back',
        icon: const Icon(Icons.arrow_back),
        onPressed:
            onBack ??
            () {
              final navigator = Navigator.of(context);
              if (navigator.canPop()) {
                navigator.pop();
              } else {
                _browseAsGuest(context);
              }
            },
      ),
    ),
    bottomNavigationBar: bottomActions == null
        ? null
        : Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: ColoredBox(
              color: AppColors.pageBackground,
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
                  child: bottomActions!,
                ),
              ),
            ),
          ),
    body: SafeArea(
      child: LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          controller: scrollController,
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: EdgeInsets.symmetric(
            horizontal: registration
                ? 20
                : (constraints.maxWidth < 560 ? 12 : 24),
            vertical: registration ? 24 : 12,
          ),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    clipBehavior: Clip.antiAlias,
                    decoration: BoxDecoration(
                      color: registration
                          ? AppColors.pageBackground
                          : AppColors.white,
                      border: registration
                          ? null
                          : Border.all(color: AppColors.border),
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: registration
                          ? const []
                          : const [
                              BoxShadow(
                                color: Color(0x0D4A0F2F),
                                blurRadius: 32,
                                offset: Offset(0, 12),
                              ),
                            ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (constraints.maxWidth >= 600 && !registration)
                          _AuthBrandPanel(
                            title:
                                brandTitle ??
                                'Everything you need, in one place.',
                            description: brandDescription ?? 'Sign in to manage your account and access the services available to your user type.',
                            points: brandPoints,
                          ),
                        Material(
                          color: registration
                              ? AppColors.pageBackground
                              : AppColors.white,
                          child: Padding(
                            padding: EdgeInsets.fromLTRB(
                              registration ? 0 : 22,
                              registration
                                  ? 0
                                  : (constraints.maxWidth < 600 ? 24 : 36),
                              registration ? 0 : 22,
                              registration ? 12 : 28,
                            ),
                            child: child,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (footer != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 22),
                      child: footer!,
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class _AuthBrandPanel extends StatelessWidget {
  final String title, description;
  final List<String> points;
  const _AuthBrandPanel({
    required this.title,
    required this.description,
    required this.points,
  });
  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF4A0F2F), Color(0xFF6F1A48), Color(0xFF93275C)],
      ),
    ),
    child: Stack(
      children: [
        Positioned(
          right: -50,
          top: -80,
          child: Container(
            width: 200,
            height: 200,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0x12FFFFFF),
            ),
          ),
        ),
        Positioned(
          left: -100,
          bottom: -140,
          child: Container(
            width: 260,
            height: 260,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0x0FFFFFFF),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Image.asset(
                    'assets/brand/logo.png',
                    width: 36,
                    height: 36,
                    excludeFromSemantics: true,
                  ),
                  const SizedBox(width: 10),
                  const Flexible(
                    child: Text.rich(
                      TextSpan(
                        text: 'Pocket',
                        children: [
                          TextSpan(
                            text: 'Finds',
                            style: TextStyle(color: Color(0xFFD9468F)),
                          ),
                        ],
                      ),
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 21,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 36),
              Text(
                title,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 36,
                  height: 1.05,
                  letterSpacing: -1.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                description,
                style: const TextStyle(
                  color: Color(0xB8FFFFFF),
                  fontSize: 14,
                  height: 1.6,
                ),
              ),
              const SizedBox(height: 20),
              for (final point in points)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 22,
                        height: 22,
                        decoration: const BoxDecoration(
                          color: Color(0x1FFFFFFF),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.check,
                          size: 13,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          point,
                          style: const TextStyle(
                            color: Color(0xD6FFFFFF),
                            fontSize: 14,
                            height: 1.5,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 24),
              Text(
                '\u00a9 ${DateTime.now().year} PocketFinds. All rights reserved.',
                style: const TextStyle(color: Color(0x85FFFFFF), fontSize: 12),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
