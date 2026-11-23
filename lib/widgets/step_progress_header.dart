import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// The numbered-circle stepper across the top of the registration wizard —
/// 5 steps for Buyer, 7 for Rider (see [RegistrationWizardScreen]). Filled/
/// current/upcoming circles connected by a line, with a label under each,
/// matching the reference design.
class StepProgressHeader extends StatelessWidget {
  final List<String> stepLabels;
  final int currentStep; // 0-based
  final bool numbered;

  const StepProgressHeader({
    super.key,
    required this.stepLabels,
    required this.currentStep,
    this.numbered = true,
  });

  @override
  Widget build(BuildContext context) {
    if (numbered) {
      return Semantics(
        label:
            'Step ${currentStep + 1} of ${stepLabels.length}: ${stepLabels[currentStep]}',
        child: LayoutBuilder(
          builder: (context, constraints) {
            final scale = MediaQuery.textScalerOf(context).scale(12) / 12;
            final showLabels =
                constraints.maxWidth / stepLabels.length >= 56 * scale;
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: List.generate(stepLabels.length, (index) {
                final active = index <= currentStep;
                return Expanded(
                  child: Semantics(
                    label: stepLabels[index],
                    child: Column(
                      children: [
                        Container(
                          width: 30,
                          height: 30,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: active ? AppColors.pink : AppColors.white,
                            border: Border.all(
                              color: active ? AppColors.pink : AppColors.border,
                            ),
                          ),
                          child: index < currentStep
                              ? const Icon(
                                  Icons.check,
                                  size: 18,
                                  color: Colors.white,
                                )
                              : Text(
                                  '${index + 1}',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: active
                                        ? Colors.white
                                        : AppColors.textMuted,
                                  ),
                                ),
                        ),
                        if (showLabels) ...[
                          const SizedBox(height: 6),
                          Text(
                            stepLabels[index],
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 10,
                              color: active
                                  ? AppColors.pink
                                  : AppColors.textMuted,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }),
            );
          },
        ),
      );
    }

    return Semantics(
      label:
          'Step ${currentStep + 1} of ${stepLabels.length}: ${stepLabels[currentStep]}',
      child: Row(
        children: List.generate(
          stepLabels.length,
          (index) => Expanded(
            child: Padding(
              padding: EdgeInsets.only(
                right: index == stepLabels.length - 1 ? 0 : 6,
              ),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                height: 5,
                decoration: BoxDecoration(
                  color: index <= currentStep
                      ? AppColors.pink
                      : AppColors.border,
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
