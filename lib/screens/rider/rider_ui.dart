import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

/// Shared presentation for native rider pages, including notification deep links.
class RiderScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget body;
  final Color? backgroundColor;
  final Widget? bottomNavigationBar;
  const RiderScaffold({
    super.key,
    this.appBar,
    required this.body,
    this.backgroundColor,
    this.bottomNavigationBar,
  });

  @override
  Widget build(BuildContext context) => Theme(
    data: riderTheme(Theme.of(context)),
    child: Scaffold(
      backgroundColor: backgroundColor ?? AppColors.pageBackground,
      appBar: appBar,
      bottomNavigationBar: bottomNavigationBar,
      body: SafeArea(
        top: false,
        bottom: bottomNavigationBar == null,
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: body,
          ),
        ),
      ),
    ),
  );
}

ThemeData riderTheme(ThemeData base) => base.copyWith(
  appBarTheme: base.appBarTheme.copyWith(
    centerTitle: false,
    elevation: 0,
    scrolledUnderElevation: 0,
    titleTextStyle: TextStyle(
      fontFamily: base.textTheme.titleMedium?.fontFamily,
      fontSize: 18,
      fontWeight: FontWeight.w700,
      color: AppColors.textPrimary,
    ),
    backgroundColor: AppColors.pageBackground,
    shape: Border(bottom: BorderSide(color: AppColors.border)),
  ),
  cardTheme: base.cardTheme.copyWith(
    elevation: 0,
    margin: EdgeInsets.zero,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12),
      side: BorderSide(color: AppColors.border),
    ),
  ),
  dividerTheme: base.dividerTheme.copyWith(
    color: AppColors.border,
    thickness: 1,
    space: 1,
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      minimumSize: const Size(48, 48),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ),
  ),
  elevatedButtonTheme: ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      minimumSize: const Size(48, 48),
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
    ),
  ),
  outlinedButtonTheme: OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      minimumSize: const Size(48, 48),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      side: BorderSide(color: AppColors.border),
    ),
  ),
  textButtonTheme: TextButtonThemeData(
    style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
  ),
);

class RiderSection extends StatelessWidget {
  final String title;
  final Widget child;
  final Widget? trailing;
  const RiderSection({
    super.key,
    required this.title,
    required this.child,
    this.trailing,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            ?trailing,
          ],
        ),
        const SizedBox(height: 12),
        child,
      ],
    ),
  );
}

class RiderInfoRow extends StatelessWidget {
  final String label;
  final String value;
  const RiderInfoRow(this.label, this.value, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: LayoutBuilder(
      builder: (context, constraints) {
        final labelWidget = Text(
          label,
          style: TextStyle(fontSize: 13, color: AppColors.textMuted),
        );
        final valueWidget = Text(
          value,
          style: const TextStyle(fontSize: 14, height: 1.4),
        );
        if (constraints.maxWidth < 300 ||
            MediaQuery.textScalerOf(context).scale(14) > 20) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [labelWidget, const SizedBox(height: 4), valueWidget],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(flex: 2, child: labelWidget),
            const SizedBox(width: 16),
            Expanded(flex: 3, child: valueWidget),
          ],
        );
      },
    ),
  );
}
