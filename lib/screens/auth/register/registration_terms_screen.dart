import 'package:flutter/material.dart';

/// Published website terms, with acknowledgement after reading to the end.
class RegistrationTermsScreen extends StatefulWidget {
  final String title, content;
  const RegistrationTermsScreen({
    super.key,
    required this.title,
    required this.content,
  });
  @override
  State<RegistrationTermsScreen> createState() =>
      _RegistrationTermsScreenState();
}

class _RegistrationTermsScreenState extends State<RegistrationTermsScreen> {
  final _scroll = ScrollController();
  bool _read = false;
  void _check() {
    if (mounted &&
        !_read &&
        _scroll.hasClients &&
        _scroll.position.extentAfter < 20) {
      setState(() => _read = true);
    }
  }

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_check);
    WidgetsBinding.instance.addPostFrameCallback((_) => _check());
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            controller: _scroll,
            padding: const EdgeInsets.all(24),
            child: SelectableText(widget.content),
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: FilledButton(
              onPressed: _read ? () => Navigator.pop(context, true) : null,
              child: Text(
                _read ? 'I have read the terms' : 'Read to the end to continue',
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
