import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../models.dart';

class StudentNotificationsScreen extends StatefulWidget {
  const StudentNotificationsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<StudentNotificationsScreen> createState() => _StudentNotificationsScreenState();
}

class _StudentNotificationsScreenState extends State<StudentNotificationsScreen> {
  final _idController = TextEditingController(text: '1');
  final _formKey = GlobalKey<FormState>();

  bool _loading = false;
  String? _error;
  List<NotificationItem>? _items;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _idController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final id = int.parse(_idController.text.trim());
      final items = await widget.api.getNotifications(id);
      if (!mounted) return;
      setState(() {
        _items = items;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _items = null;
        _error = e.statusCode == 404 ? 'No applicant found with that ID.' : e.message;
        _loading = false;
      });
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
              Text('My notifications', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              Form(
                key: _formKey,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _idController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Applicant ID (demo)',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) {
                          final n = int.tryParse((v ?? '').trim());
                          if (n == null || n <= 0) return 'Enter a positive whole number';
                          return null;
                        },
                        onFieldSubmitted: (_) => _load(),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: FilledButton(
                        onPressed: _loading ? null : _load,
                        child: const Text('Load'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
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
      return _MessageCard(
        icon: Icons.error_outline,
        text: _error!,
        actionLabel: 'Try again',
        onAction: _load,
      );
    }
    final items = _items;
    if (items == null) {
      return const _MessageCard(
        icon: Icons.info_outline,
        text: 'Enter an applicant ID and press Load.',
      );
    }
    if (items.isEmpty) {
      return const _MessageCard(
        icon: Icons.notifications_none,
        text: 'No notifications yet.',
      );
    }
    return ListView.separated(
      itemCount: items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, i) {
        final n = items[i];
        return ListTile(
          leading: const Icon(Icons.notifications),
          title: Text(n.message),
          subtitle: Text(n.sentAt ?? ''),
        );
      },
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.text,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 40),
          const SizedBox(height: 8),
          Text(text, textAlign: TextAlign.center),
          if (actionLabel != null) ...[
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}