import 'package:flutter/material.dart';

const scannerPink = Color(0xffc43f72);

class ScannerLogin extends StatelessWidget {
  final TextEditingController email, password;
  final bool busy, enabled;
  final String message;
  final VoidCallback onLogin;
  final VoidCallback? onRetry;
  const ScannerLogin({
    super.key,
    required this.email,
    required this.password,
    required this.busy,
    required this.enabled,
    required this.message,
    required this.onLogin,
    this.onRetry,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: ListView(
            padding: const EdgeInsets.all(24),
            shrinkWrap: true,
            children: [
              const Icon(
                Icons.local_shipping_rounded,
                size: 54,
                color: scannerPink,
              ),
              const SizedBox(height: 12),
              Text(
                'PocketFinds',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const Text(
                'SCANNER',
                textAlign: TextAlign.center,
                style: TextStyle(
                  letterSpacing: 4,
                  color: scannerPink,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 32),
              Surface(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Welcome back',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Sign in with your Logistics Admin or Hub Staff account.',
                    ),
                    const SizedBox(height: 24),
                    TextField(
                      controller: email,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(
                        labelText: 'Email address',
                        prefixIcon: Icon(Icons.mail_outline),
                      ),
                    ),
                    const SizedBox(height: 16),
                    _LoginPasswordField(controller: password),
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      onPressed: busy || !enabled ? null : onLogin,
                      icon: const Icon(Icons.arrow_forward),
                      label: Text(busy ? 'Signing in...' : 'Sign in'),
                    ),
                  ],
                ),
              ),
              if (message.isNotEmpty) ...[
                const SizedBox(height: 16),
                Notice(message),
                if (onRetry != null)
                  OutlinedButton.icon(
                    onPressed: busy ? null : onRetry,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry connection'),
                  ),
              ],
              if (busy) const LinearProgressIndicator(),
              const SizedBox(height: 24),
              const Text(
                'One account. Two ways to scan.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xff73717b)),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _LoginPasswordField extends StatefulWidget {
  final TextEditingController controller;
  const _LoginPasswordField({required this.controller});

  @override
  State<_LoginPasswordField> createState() => _LoginPasswordFieldState();
}

class _LoginPasswordFieldState extends State<_LoginPasswordField> {
  bool hidden = true;

  @override
  Widget build(BuildContext context) => TextField(
    controller: widget.controller,
    obscureText: hidden,
    enableSuggestions: false,
    autocorrect: false,
    autofillHints: const [AutofillHints.password],
    decoration: InputDecoration(
      labelText: 'Password',
      prefixIcon: const Icon(Icons.lock_outline),
      suffixIcon: IconButton(
        tooltip: hidden ? 'Show password' : 'Hide password',
        onPressed: () => setState(() => hidden = !hidden),
        icon: Icon(
          hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
        ),
      ),
    ),
  );
}

class ScannerDashboard extends StatelessWidget {
  final VoidCallback? onRetry;
  final Map<String, dynamic> user;
  final Map<String, dynamic>? parcel;
  final List<Map<String, Object?>> recent;
  final String mode, code, message;
  final bool locked, online, syncing, uncertainAction, retryRelay;
  final String? pendingActionId;
  final int pending;
  final DateTime? lastSync;
  final VoidCallback onLogout,
      onStandalone,
      onPair,
      onScan,
      onLookup,
      onRelay,
      onHome,
      onSync;
  final ValueChanged<Map<String, dynamic>> onAction;
  const ScannerDashboard({
    super.key,
    required this.user,
    required this.parcel,
    required this.recent,
    required this.mode,
    required this.code,
    required this.message,
    required this.locked,
    required this.online,
    required this.syncing,
    required this.uncertainAction,
    required this.retryRelay,
    required this.pendingActionId,
    required this.pending,
    required this.lastSync,
    required this.onLogout,
    required this.onStandalone,
    required this.onPair,
    required this.onScan,
    required this.onLookup,
    required this.onRelay,
    required this.onHome,
    required this.onSync,
    required this.onAction,
    this.onRetry,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('PocketFinds Scanner'),
          Text(
            user['role'] == 'admin'
                ? 'PocketFinds Logistics'
                : 'PocketFinds Hub',
            style: const TextStyle(fontSize: 13, color: scannerPink),
          ),
        ],
      ),
      actions: [
        IconButton(
          tooltip: 'Sign out',
          onPressed: locked ? null : onLogout,
          icon: const Icon(Icons.logout_rounded),
        ),
      ],
    ),
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 700),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            children: [
              Text(
                mode == 'Home'
                    ? 'Ready to scan?'
                    : mode == 'Standalone'
                    ? 'Standalone scanner'
                    : 'Paired with computer',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                user['role'] == 'admin'
                    ? (user['company'] ?? '')
                    : (user['hub_municipality'] ?? user['company'] ?? ''),
                style: const TextStyle(color: Color(0xff73717b)),
              ),
              const SizedBox(height: 20),
              Surface(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          StatusBadge(
                            syncing
                                ? 'Syncing'
                                : online
                                ? 'Connected'
                                : 'Offline / unchecked',
                            color: online ? Colors.teal : Colors.orange,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            '$pending scans waiting to sync',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          if (lastSync != null)
                            Text(
                              'Last checked ${shortTime(lastSync!)}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xff73717b),
                              ),
                            ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Sync saved scans',
                      onPressed: locked ? null : onSync,
                      icon: const Icon(Icons.sync_rounded),
                    ),
                  ],
                ),
              ),
              if (locked) ...[
                const SizedBox(height: 8),
                const LinearProgressIndicator(),
              ],
              if (message.isNotEmpty) ...[
                const SizedBox(height: 12),
                Notice(message),
                if (onRetry != null)
                  OutlinedButton.icon(
                    onPressed: locked ? null : onRetry,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry connection'),
                  ),
              ],
              const SizedBox(height: 20),
              if (mode == 'Home') ...[
                ModeCard(
                  compact: true,
                  icon: Icons.qr_code_scanner_rounded,
                  title: 'Standalone Scanner',
                  subtitle:
                      'Scan, view parcel details, and confirm receipt on this phone.',
                  footnote:
                      'Offline barcodes are saved for later verification.',
                  label: 'Scan on phone',
                  onPressed: locked ? null : onStandalone,
                ),
                const SizedBox(height: 14),
                ModeCard(
                  compact: true,
                  icon: Icons.devices_rounded,
                  title: 'Pair with Computer',
                  subtitle:
                      'Use your phone as a wireless scanner. Review parcels and confirm actions on your computer.',
                  footnote:
                      'Open Scan Parcel on the computer and create a connection QR.',
                  label: 'Connect to computer',
                  onPressed: locked ? null : onPair,
                ),
              ],
              if (mode == 'Standalone') ...[
                ModeCard(
                  icon: Icons.qr_code_scanner_rounded,
                  title: 'Scan a parcel',
                  subtitle:
                      'Point the camera at its barcode or QR code. You can also enter the tracking number.',
                  footnote:
                      'Scanning alone does not mark a parcel as received.',
                  label: 'Open scanner',
                  onPressed: locked || uncertainAction ? null : onScan,
                ),
                if (code.isNotEmpty && parcel == null && !uncertainAction) ...[
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: locked ? null : onLookup,
                    icon: const Icon(Icons.search),
                    label: Text('Look up $code'),
                  ),
                ],
                if (parcel != null) ...[
                  const SizedBox(height: 16),
                  Surface(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'PARCEL DETAILS',
                          style: TextStyle(
                            letterSpacing: 1.5,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: scannerPink,
                          ),
                        ),
                        const SizedBox(height: 12),
                        SelectableText(
                          parcel!['parcel']['tracking_number'],
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 8),
                        Align(
                          alignment: Alignment.centerLeft,
                          child: StatusBadge(
                            parcel!['parcel']['status_label'],
                            color: Colors.indigo,
                          ),
                        ),
                        const SizedBox(height: 18),
                        RouteStop(
                          'From',
                          parcel!['parcel']['origin_hub'] ?? 'Not specified',
                          Icons.trip_origin,
                        ),
                        const SizedBox(height: 12),
                        RouteStop(
                          'To',
                          parcel!['parcel']['destination_hub'] ??
                              'Not specified',
                          Icons.location_on_outlined,
                        ),
                        const SizedBox(height: 18),
                        if ((parcel!['actions'] as List).isEmpty)
                          const Text(
                            'No receipt action is available for this parcel’s current status.',
                          ),
                        for (final action in parcel!['actions'])
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: FilledButton.icon(
                              onPressed:
                                  locked ||
                                      (!online && !uncertainAction) ||
                                      (uncertainAction &&
                                          pendingActionId != action['id'])
                                  ? null
                                  : () => onAction(
                                      Map<String, dynamic>.from(action),
                                    ),
                              icon: const Icon(Icons.inventory_2_outlined),
                              label: Text(
                                uncertainAction
                                    ? 'Retry same operation'
                                    : action['label'],
                              ),
                            ),
                          ),
                        const Text(
                          'You will be asked to confirm before a parcel is updated.',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xff73717b),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
              if (mode == 'Paired')
                ModeCard(
                  icon: Icons.connected_tv_rounded,
                  title: 'Your computer handles the parcel',
                  subtitle:
                      'Scan a parcel here. Its details appear on the paired computer, where you review and confirm the next action.',
                  footnote:
                      'Keep the computer’s Scan Parcel page open. Pairing expires after ten minutes and needs a connection.',
                  label: retryRelay ? 'Retry sending code' : 'Scan and send',
                  onPressed: locked ? null : onRelay,
                ),
              if (mode != 'Home') ...[
                const SizedBox(height: 12),
                TextButton.icon(
                  onPressed: locked || uncertainAction ? null : onHome,
                  icon: const Icon(Icons.arrow_back),
                  label: Text(
                    mode == 'Paired'
                        ? 'Disconnect from computer'
                        : 'Choose another mode',
                  ),
                ),
              ],
              const SizedBox(height: 24),
              Surface(
                child: ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: EdgeInsets.zero,
                  initiallyExpanded: recent.isEmpty,
                  title: const Text(
                    'Scan activity',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text('${recent.length} recent standalone scans'),
                  children: [
                    if (recent.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 20),
                        child: Column(
                          children: [
                            Icon(
                              Icons.history_rounded,
                              size: 32,
                              color: Color(0xff9995a2),
                            ),
                            SizedBox(height: 8),
                            Text('Your saved scans will appear here.'),
                            Text(
                              'Paired scans are reviewed on the computer.',
                              style: TextStyle(fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    for (final record in recent)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.qr_code_2, size: 20),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    record['code'] as String,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                                StatusBadge(
                                  record['state'] == 'Accepted'
                                      ? 'Verified'
                                      : record['state'] as String,
                                  color: record['state'] == 'Accepted'
                                      ? Colors.teal
                                      : record['state'] == 'Rejected'
                                      ? Colors.red
                                      : Colors.orange,
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              shortTime(
                                DateTime.parse(record['scanned_at'] as String),
                              ),
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xff73717b),
                              ),
                            ),
                            if (record['error'] != null)
                              Text(
                                record['error'] as String,
                                style: const TextStyle(
                                  color: Colors.red,
                                  fontSize: 12,
                                ),
                              ),
                            if (record['state'] == 'Accepted')
                              const Text(
                                'Barcode verified; this is not a receipt confirmation.',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Color(0xff73717b),
                                ),
                              ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

String shortTime(DateTime time) {
  final local = time.toLocal();
  final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
  final period = local.hour < 12 ? 'AM' : 'PM';
  return '${local.day}/${local.month} · $hour:${local.minute.toString().padLeft(2, '0')} $period';
}

class Surface extends StatelessWidget {
  final Widget child;
  const Surface({super.key, required this.child});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: const Color(0xffece8ee)),
    ),
    child: child,
  );
}

class Notice extends StatelessWidget {
  final String text;
  const Notice(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: const Color(0xfff2edf5),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline, size: 18, color: scannerPink),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 13))),
      ],
    ),
  );
}

class StatusBadge extends StatelessWidget {
  final String label;
  final Color color;
  const StatusBadge(this.label, {super.key, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .09),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700),
    ),
  );
}

class ModeCard extends StatelessWidget {
  final bool compact;
  final IconData icon;
  final String title, subtitle, footnote, label;
  final VoidCallback? onPressed;
  const ModeCard({
    this.compact = false,
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.footnote,
    required this.label,
    required this.onPressed,
  });
  @override
  Widget build(BuildContext context) {
    if (compact) {
      return Surface(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(icon, color: scannerPink, size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(subtitle),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: onPressed,
              icon: const Icon(Icons.arrow_forward),
              label: Text(label),
            ),
          ],
        ),
      );
    }
    return Surface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xfffaedf3),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: scannerPink, size: 30),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          Text(subtitle),
          const SizedBox(height: 10),
          Text(
            footnote,
            style: const TextStyle(fontSize: 12, color: Color(0xff73717b)),
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: onPressed,
            icon: Icon(icon),
            label: Text(label),
          ),
        ],
      ),
    );
  }
}

class RouteStop extends StatelessWidget {
  final String label, value;
  final IconData icon;
  const RouteStop(this.label, this.value, this.icon, {super.key});
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 20, color: scannerPink),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: Color(0xff73717b)),
            ),
            Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    ],
  );
}
