import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../services/backend_service.dart';
import '../../utils/friendly_error.dart';

class ReportContentScreen extends StatefulWidget {
  final String? productId, messageId;
  const ReportContentScreen({super.key, this.productId, this.messageId});
  @override
  State<ReportContentScreen> createState() => _ReportContentScreenState();
}

class _ReportContentScreenState extends State<ReportContentScreen> {
  final _details = TextEditingController();
  String? _reason, _evidence, _filename, _error;
  bool _busy = false;
  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _attach() async {
    try {
      final file = await ImagePicker().pickMedia(imageQuality: 85);
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (bytes.length > 20 * 1024 * 1024) {
        throw const AuthException('Evidence must be under 20 MB.');
      }
      if (mounted) {
        setState(() {
          _evidence = base64Encode(bytes);
          _filename = file.name;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
  }

  Future<void> _send() async {
    if (_reason == null) {
      setState(() => _error = 'Choose a reason.');
      return;
    }
    setState(() => _busy = true);
    try {
      await BackendService.client.request(
        'website-buyer/${widget.productId != null ? 'report-product' : 'report-message'}',
        {
          if (widget.productId != null) 'product_id': widget.productId,
          if (widget.messageId != null)
            'message_id': int.parse(widget.messageId!),
          'reason': _reason,
          'description': _details.text.trim(),
          if (_evidence != null) 'evidence_content': _evidence,
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Report sent to admin.')));
        Navigator.pop(context, true);
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.productId != null ? 'Report Product' : 'Report Message',
      ),
    ),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'Tell the administrator what happened. Reports are reviewed with the original product or message attached.',
        ),
        const SizedBox(height: 20),
        DropdownButtonFormField<String>(
          initialValue: _reason,
          decoration: const InputDecoration(labelText: 'Reason'),
          items:
              (widget.productId != null
                      ? [
                          'Counterfeit or fake item',
                          'Misleading description or photos',
                          'Prohibited or restricted item',
                          'Scam or fraud',
                          'Other',
                        ]
                      : [
                          'Harassment or abuse',
                          'Scam or fraud',
                          'Spam',
                          'Inappropriate content',
                          'Other',
                        ])
                  .map(
                    (value) =>
                        DropdownMenuItem(value: value, child: Text(value)),
                  )
                  .toList(),
          isExpanded: true,
          onChanged: _busy ? null : (value) => setState(() => _reason = value),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _details,
          maxLines: 5,
          maxLength: 3000,
          decoration: const InputDecoration(labelText: 'More details'),
        ),
        OutlinedButton.icon(
          onPressed: _busy ? null : _attach,
          icon: const Icon(Icons.attach_file),
          label: Text(_filename ?? 'Attach evidence (optional)'),
        ),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        const SizedBox(height: 18),
        FilledButton(
          onPressed: _busy ? null : _send,
          child: Text(_busy ? 'Sending…' : 'Send report'),
        ),
      ],
    ),
  );
}
