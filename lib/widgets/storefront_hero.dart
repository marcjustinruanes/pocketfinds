import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// The website's photo banner, serif headline and handwritten brand signature.
class StorefrontHero extends StatelessWidget {
  final VoidCallback onBrowse;
  const StorefrontHero({super.key, required this.onBrowse});

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final wide = constraints.maxWidth >= 600;
      return Container(
        width: double.infinity,
        clipBehavior: Clip.antiAlias,
        decoration: const BoxDecoration(color: Color(0xFF321125)),
        child: Stack(
          children: [
            Positioned.fill(
              child: Image.asset(
                'assets/brand/storefront.jpg',
                fit: BoxFit.cover,
                alignment: const Alignment(0.35, 0),
                excludeFromSemantics: true,
              ),
            ),
            const Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [
                      Color(0xE8231020),
                      Color(0xB3231020),
                      Color(0x40231020),
                    ],
                    stops: [0, 0.55, 1],
                  ),
                ),
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(
                horizontal: wide ? 40 : 20,
                vertical: wide ? 40 : 24,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: wide ? 560 : 400),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShaderMask(
                      blendMode: BlendMode.srcIn,
                      shaderCallback: (bounds) => const LinearGradient(
                        colors: [
                          Color(0xFFFFF5DF),
                          Color(0xFFFFE3ED),
                          Color(0xFFF5A6CF),
                        ],
                      ).createShader(bounds),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Find It. Love It.',
                            style: GoogleFonts.dmSerifDisplay(
                              fontSize: wide ? 48 : 32,
                              height: 1.12,
                              color: Colors.white,
                            ),
                          ),
                          Text(
                            'Pocket It.',
                            style: GoogleFonts.allura(
                              fontSize: wide ? 64 : 48,
                              height: 1.1,
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      'Discover everyday finds that fit your needs and budget. Simple, convenient shopping—all in one place.',
                      style: GoogleFonts.inter(
                        color: const Color(0xFFD5C5CE),
                        fontSize: 12.5,
                        height: 1.5,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: onBrowse,
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFBF3C7D),
                        foregroundColor: Colors.white,
                        minimumSize: const Size(0, 48),
                        padding: const EdgeInsets.symmetric(horizontal: 24),
                        shape: const StadiumBorder(
                          side: BorderSide(color: Color(0x40FFFFFF)),
                        ),
                      ),
                      child: const Text(
                        'Browse Products',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    },
  );
}
