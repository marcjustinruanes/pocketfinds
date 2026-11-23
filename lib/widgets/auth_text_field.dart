import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_colors.dart';
import '../theme/app_motion.dart';
import '../theme/app_spacing.dart';
import '../theme/app_typography.dart';

/// A labeled input matching the site's `.auth-label` + `.auth-input` — a
/// soft filled background rather than a bare bordered box, with an
/// animated focus ring (border color/width transition to brand pink) and,
/// for password fields, an icon-button show/hide toggle in place of a
/// plain text button. On a failed validation it flips the border to danger
/// red and plays a short shake — real feedback instead of Flutter's default
/// silent error-text swap.
class AuthTextField extends StatefulWidget {
  final String label;
  final bool showLabel;
  final TextEditingController controller;
  final String? hint;
  final bool obscureText;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final String? Function(String?)? validator;
  final void Function(String)? onChanged;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onFieldSubmitted;
  final TextCapitalization? textCapitalization;

  /// A leading icon (envelope, lock, etc.) — purely decorative, matches
  /// the pill-input look on Login/Register.
  final IconData? prefixIcon;

  const AuthTextField({
    super.key,
    required this.label,
    this.showLabel = true,
    required this.controller,
    this.hint,
    this.obscureText = false,
    this.keyboardType,
    this.inputFormatters,
    this.validator,
    this.onChanged,
    this.autofillHints,
    this.textInputAction,
    this.onFieldSubmitted,
    this.textCapitalization,
    this.prefixIcon,
  });

  @override
  State<AuthTextField> createState() => _AuthTextFieldState();
}

class _AuthTextFieldState extends State<AuthTextField>
    with SingleTickerProviderStateMixin {
  late bool _obscured = widget.obscureText;
  final _focusNode = FocusNode();
  bool _focused = false;
  bool _hasError = false;

  late final AnimationController _shakeController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 400),
  );
  late final Animation<double> _shakeAnim = TweenSequence<double>([
    TweenSequenceItem(tween: Tween(begin: 0, end: 8), weight: 1),
    TweenSequenceItem(tween: Tween(begin: 8, end: -8), weight: 1),
    TweenSequenceItem(tween: Tween(begin: -8, end: 6), weight: 1),
    TweenSequenceItem(tween: Tween(begin: 6, end: -6), weight: 1),
    TweenSequenceItem(tween: Tween(begin: -6, end: 0), weight: 1),
  ]).animate(CurvedAnimation(parent: _shakeController, curve: Curves.easeOut));

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(
      () => setState(() => _focused = _focusNode.hasFocus),
    );
  }

  @override
  void dispose() {
    _focusNode.dispose();
    _shakeController.dispose();
    super.dispose();
  }

  /// Wraps the caller's validator so we can tell when this field just
  /// transitioned from valid to invalid (e.g. right after a failed
  /// `Form.validate()`) and react to it — deferred to after this build/
  /// validate pass finishes, since `validator` itself runs synchronously
  /// during someone else's build.
  String? _wrappedValidator(String? value) {
    final error = widget.validator?.call(value);
    final nowError = error != null;
    if (nowError != _hasError) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        setState(() => _hasError = nowError);
        if (nowError) _shakeController.forward(from: 0);
      });
    }
    return error;
  }

  @override
  Widget build(BuildContext context) {
    final borderColor = _hasError
        ? AppColors.danger
        : (_focused ? AppColors.pink : AppColors.border);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (widget.showLabel) ...[
          Text(widget.label, style: AppTypography.label),
          const SizedBox(height: 6),
        ],
        AnimatedBuilder(
          animation: _shakeAnim,
          builder: (context, child) => Transform.translate(
            offset: Offset(_shakeAnim.value, 0),
            child: child,
          ),
          child: AnimatedContainer(
            duration: AppMotion.fast,
            curve: AppMotion.curve,
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(
                color: borderColor,
                width: (_focused || _hasError) ? 1.6 : 1,
              ),
              boxShadow: _focused
                  ? [
                      BoxShadow(
                        color: AppColors.pink.withValues(alpha: 0.12),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ]
                  : null,
            ),
            child: TextFormField(
              controller: widget.controller,
              focusNode: _focusNode,
              obscureText: _obscured,
              autocorrect: !widget.obscureText,
              enableSuggestions: !widget.obscureText,
              textCapitalization:
                  widget.textCapitalization ??
                  (widget.obscureText
                      ? TextCapitalization.none
                      : TextCapitalization.words),
              autofillHints:
                  widget.autofillHints ??
                  (widget.label.toLowerCase().contains('code')
                      ? const [AutofillHints.oneTimeCode]
                      : null),
              textInputAction: widget.textInputAction,
              onFieldSubmitted: widget.onFieldSubmitted,
              onTapOutside: (_) => _focusNode.unfocus(),
              keyboardType: widget.keyboardType,
              inputFormatters: widget.inputFormatters,
              validator: _wrappedValidator,
              onChanged: widget.onChanged,
              style: AppTypography.body,
              decoration: InputDecoration(
                hintText: widget.hint,
                hintStyle: AppTypography.body.copyWith(
                  color: AppColors.textMuted,
                ),
                prefixIcon: widget.prefixIcon == null
                    ? null
                    : Icon(widget.prefixIcon, size: 20, color: AppColors.pink),
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                errorBorder: InputBorder.none,
                focusedErrorBorder: InputBorder.none,
                contentPadding:
                    Theme.of(context).inputDecorationTheme.contentPadding ??
                    const EdgeInsets.symmetric(
                      horizontal: AppSpacing.lg,
                      vertical: 14,
                    ),
                suffixIcon: widget.obscureText
                    ? IconButton(
                        onPressed: () => setState(() => _obscured = !_obscured),
                        icon: Icon(
                          _obscured
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                          size: 19,
                          color: AppColors.textMuted,
                        ),
                      )
                    : null,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
