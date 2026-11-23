import 'package:flutter/material.dart';

import '../../models/app_user.dart';
import '../../models/chat_ui.dart';
import '../../services/auth_service.dart';
import '../../services/conversation_service.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_spacing.dart';
import '../../theme/app_typography.dart';
import '../../utils/friendly_error.dart';
import '../../widgets/app_snackbar.dart';
import '../../widgets/message_icon.dart';
import '../../widgets/primary_button.dart';
import 'conversation_detail_screen.dart';
import 'policies_screen.dart';
import 'website_content_screen.dart';

/// Help Center — a real chat thread with an actual admin account (reuses
/// the same messaging system Buyer/Rider already use with each other), a
/// real formal complaint record, and a link to the app's policies. Used by
/// both Buyer and Rider (`AppUser.accountType` decides nothing here — the
/// same real admin contact and `complaints` table serve either role).
class HelpCenterScreen extends StatefulWidget {
  final AppUser user;

  const HelpCenterScreen({super.key, required this.user});

  @override
  State<HelpCenterScreen> createState() => _HelpCenterScreenState();
}

class _HelpCenterScreenState extends State<HelpCenterScreen> {
  final _authService = AuthService();
  bool _openingChat = false;

  Future<void> _messageSupport() async {
    setState(() => _openingChat = true);
    try {
      final admin = await _authService.fetchAdminContact();
      if (admin == null) {
        if (!mounted) return;
        AppSnackBar.info(context, 'Support chat is not available right now.');
        return;
      }
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ConversationDetailScreen(
            conversation: ConversationSummary(
              id: admin.adminId.toString(),
              participantName: admin.adminName,
              participantAvatarInitial: admin.adminName.isNotEmpty
                  ? admin.adminName[0].toUpperCase()
                  : '?',
              lastMessagePreview: '',
              lastMessageAt: DateTime.now(),
              participantRole: 'admin',
            ),
            service: BackendConversationService(myUserId: widget.user.id),
            user: widget.user,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      AppSnackBar.error(
        context,
        'Could not open support chat: ${friendlyError(e)}',
      );
    } finally {
      if (mounted) setState(() => _openingChat = false);
    }
  }

  Future<void> _submitComplaint() async {
    String type = 'general';
    final subjectController = TextEditingController();
    final descriptionController = TextEditingController();

    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
                left: AppSpacing.xl,
                right: AppSpacing.xl,
                top: AppSpacing.xl,
              ),
              child: _ComplaintForm(
                type: type,
                onTypeChanged: (v) => setSheetState(() => type = v),
                subjectController: subjectController,
                descriptionController: descriptionController,
              ),
            );
          },
        );
      },
    );
    if (submitted == true && mounted) {
      AppSnackBar.success(
        context,
        'Your complaint was submitted — support will follow up.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text('Help Center', style: AppTypography.pageTitle),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _HelpTile(
            iconWidget: MessageIcon(size: 20, color: AppColors.pink),
            title: 'Message Support',
            subtitle: 'Chat directly with a PocketFinds admin.',
            loading: _openingChat,
            onTap: _messageSupport,
          ),
          const SizedBox(height: AppSpacing.md),
          _HelpTile(
            icon: Icons.report_problem_outlined,
            title: 'Submit a Complaint',
            subtitle: 'File a formal complaint for our team to review.',
            onTap: _submitComplaint,
          ),
          const SizedBox(height: AppSpacing.md),
          if (widget.user.isBuyer) ...[
            _HelpTile(
              icon: Icons.help_outline,
              title: 'Buyer FAQs',
              subtitle: 'Answers published by PocketFinds.',
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const WebsiteContentScreen(kind: 'faqs'),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          _HelpTile(
            icon: Icons.gavel_outlined,
            title: 'App Policies & Rules',
            subtitle: 'Terms of Service and Privacy Policy.',
            onTap: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const PoliciesScreen())),
          ),
        ],
      ),
    );
  }
}

class _HelpTile extends StatelessWidget {
  final IconData? icon;
  final Widget? iconWidget;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool loading;

  const _HelpTile({
    this.icon,
    this.iconWidget,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.loading = false,
  }) : assert(icon != null || iconWidget != null);

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: loading ? null : onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.pinkSoft,
                shape: BoxShape.circle,
              ),
              child: iconWidget ?? Icon(icon, color: AppColors.pink, size: 20),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppTypography.productTitle),
                  Text(subtitle, style: AppTypography.caption),
                ],
              ),
            ),
            if (loading)
              const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              Icon(Icons.chevron_right, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}

class _ComplaintForm extends StatefulWidget {
  final String type;
  final ValueChanged<String> onTypeChanged;
  final TextEditingController subjectController;
  final TextEditingController descriptionController;

  const _ComplaintForm({
    required this.type,
    required this.onTypeChanged,
    required this.subjectController,
    required this.descriptionController,
  });

  @override
  State<_ComplaintForm> createState() => _ComplaintFormState();
}

class _ComplaintFormState extends State<_ComplaintForm> {
  final _authService = AuthService();
  bool _submitting = false;
  String? _error;

  static const _types = {
    'general': 'General',
    'order': 'Order Issue',
    'product': 'Product Issue',
    'account': 'Account Issue',
  };

  Future<void> _submit() async {
    if (widget.subjectController.text.trim().isEmpty) {
      setState(() => _error = 'Please enter a subject.');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await _authService.submitComplaint(
        type: widget.type,
        subject: widget.subjectController.text.trim(),
        description: widget.descriptionController.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = friendlyError(e));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Submit a Complaint', style: AppTypography.sectionTitle),
        const SizedBox(height: AppSpacing.lg),
        DropdownButtonFormField<String>(
          initialValue: widget.type,
          decoration: const InputDecoration(labelText: 'Type'),
          items: _types.entries
              .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
              .toList(),
          onChanged: (v) => widget.onTypeChanged(v!),
        ),
        const SizedBox(height: AppSpacing.md),
        TextField(
          controller: widget.subjectController,
          decoration: const InputDecoration(labelText: 'Subject'),
        ),
        const SizedBox(height: AppSpacing.md),
        TextField(
          controller: widget.descriptionController,
          maxLines: 4,
          decoration: const InputDecoration(labelText: 'Details (optional)'),
        ),
        if (_error != null) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            _error!,
            style: TextStyle(color: AppColors.danger, fontSize: 12),
          ),
        ],
        const SizedBox(height: AppSpacing.lg),
        PrimaryButton(
          label: 'Submit',
          loading: _submitting,
          onPressed: _submit,
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }
}
