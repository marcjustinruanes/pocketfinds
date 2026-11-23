import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'app_colors.dart';

/// Website typography: DM Serif Display headings, Inter body and prices,
/// and IBM Plex Mono for operational labels.
class AppTypography {
  AppTypography._();

  /// Hero-scale headline (Home welcome banner, empty-state hero text).
  static TextStyle get display => GoogleFonts.dmSerifDisplay(
    fontSize: 32,
    fontWeight: FontWeight.w400,
    color: AppColors.textPrimary,
    height: 1.15,
    letterSpacing: -0.5,
  );

  /// Screen/page titles (AppBar titles, top-of-screen headers).
  static TextStyle get pageTitle => GoogleFonts.inter(
    fontSize: 22,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    letterSpacing: -0.3,
  );

  /// Card/section headers (`SectionHeader`, `TitledCard` titles).
  static TextStyle get sectionTitle => GoogleFonts.inter(
    fontSize: 17,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
  );

  /// Product names, list-item titles.
  static TextStyle get productTitle => GoogleFonts.inter(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: AppColors.textPrimary,
    height: 1.3,
  );

  /// Ordinary body text.
  static TextStyle get body => GoogleFonts.inter(
    fontSize: 13.5,
    fontWeight: FontWeight.w400,
    color: AppColors.textPrimary,
    height: 1.4,
  );

  /// Muted meta text — seller location, dates, item counts.
  static TextStyle get caption => GoogleFonts.inter(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    color: AppColors.textMuted,
  );

  /// Button/action label text.
  static TextStyle get label => GoogleFonts.inter(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: AppColors.textPrimary,
  );

  /// Money amounts and other numeric values (prices, totals, KPI values).
  static TextStyle get price => GoogleFonts.inter(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    color: AppColors.pinkDark,
    letterSpacing: -0.2,
  );

  /// A larger price/stat variant for hero/KPI-scale numbers.
  static TextStyle get priceLarge =>
      price.copyWith(fontSize: 24, fontWeight: FontWeight.w800);

  /// Uppercase mono field labels ("AVAILABLE", "ACTIVE", stat captions).
  static TextStyle get statLabel => GoogleFonts.ibmPlexMono(
    fontSize: 10.5,
    fontWeight: FontWeight.w500,
    color: AppColors.textMuted,
    letterSpacing: 1.0,
  );
}
