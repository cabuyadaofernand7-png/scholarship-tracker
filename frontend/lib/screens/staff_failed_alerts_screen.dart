import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../models.dart';

class StaffFailedAlertsScreen extends StatefulWidget {
  const StaffFailedAlertsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<StaffFailedAlertsScreen> createState() => _StaffFailedAlertsScreenState();
}

class _StaffFailedAlertsScreenState extends State<StaffFailedAlertsScreen> {
  bool _loading = true;
  String? _error;
  List<FailedAlertItem> _items = [];
  final Set<int> _resolving = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await widget.api.getFailedAlerts();
      if (!mounted) return;
      setState(() {
        _items = items;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _resolve(FailedAlertItem item) async {
    setState(() => _resolving.add(item.id));
    try {
      await widget.api.resolveFailedAlert(item.id);
      if (!mounted) return;
      setState(() {
        _items = _items.where((e) => e.id != item.id).toList();
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _resolving.remove(item.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text('Failed alerts needing action',
                        style: Theme.of(context).textTheme.headlineSmall),
                  ),
                  IconButton(
                    tooltip: 'Refresh',
                    onPressed: _loading ? null : _load,
                    icon: const Icon(Icons.refresh),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Expanded(child: _buildBody()),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 40),
            const SizedBox(height: 8),
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _load, child: const Text('Try again')),
          ],
        ),
      );
    }
    if (_items.isEmpty) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle_outline, size: 40),
            SizedBox(height: 8),
            Text('No failed alerts. Nothing needs action.'),
          ],
        ),
      );
    }
    return ListView.separated(
      itemCount: _items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, i) {
        final item = _items[i];
        final busy = _resolving.contains(item.id);
        return ListTile(
          leading: const Icon(Icons.warning_amber_rounded),
          title: Text(item.friendlyReason),
          subtitle: Text(
            'Application #${item.applicationId ?? '-'} · ${item.channel} · '
                '${item.type} · attempts: ${item.attempts}',
          ),
          trailing: FilledButton.tonal(
            onPressed: busy ? null : () => _resolve(item),
            child: busy
                ? const SizedBox(
                width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Mark resolved'),
          ),
        );
      },
    );
  }
}