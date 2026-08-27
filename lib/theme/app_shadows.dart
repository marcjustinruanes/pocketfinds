import 'package:flutter/material.dart';

/// Shadow presets copied directly from the website's CSS custom properties
/// (`--shadow-sm`, `--shadow-md`, `--shadow-lg` in `public/css/buyer.css`),
/// so cards/sheets/buttons get the same soft, dark-tinted elevation the
/// site uses instead of Flutter's default Material shadow.
class AppShadows {
  AppShadows._();

  static const List<BoxShadow> sm = [
    BoxShadow(color: Color(0x0F1B1620), offset: Offset(0, 1), blurRadius: 2),
  ];

  static const List<BoxShadow> md = [
    BoxShadow(color: Color(0x1A1B1620), offset: Offset(0, 10), blurRadius: 30),
  ];

  static const List<BoxShadow> lg = [
    BoxShadow(color: Color(0x2E1B1620), offset: Offset(0, 24), blurRadius: 60),
  ];
}
