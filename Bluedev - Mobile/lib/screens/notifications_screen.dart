import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrence_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({
    required this.session,
    required this.notifications,
    required this.onReadThrough,
    required this.onReadAll,
    super.key,
  });

  final Session session;
  final List<Map<String, dynamic>> notifications;
  final Future<void> Function(int occurrenceId) onReadThrough;
  final Future<void> Function() onReadAll;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late List<Map<String, dynamic>> _notifications = widget.notifications;
  bool _markingRead = false;

  @override
  void didUpdateWidget(covariant NotificationsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.notifications != widget.notifications) {
      _notifications = widget.notifications;
    }
  }

  Future<void> _open(Map<String, dynamic> notification) async {
    final id = int.tryParse('${notification['id']}');
    if (id == null) return;
    setState(() => _markingRead = true);
    try {
      await widget.onReadThrough(id);
      if (!mounted) return;
      setState(() {
        _notifications.removeWhere((item) {
          final itemId = int.tryParse('${item['id']}');
          return itemId != null && itemId <= id;
        });
      });
      final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
        builder: (_) => OccurrenceDetailScreen(
          session: widget.session,
          occurrence: notification,
        ),
      ));
      if (changed == true) widget.session.changed();
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _markingRead = false);
    }
  }

  Future<void> _readAll() async {
    setState(() => _markingRead = true);
    try {
      await widget.onReadAll();
      if (mounted) setState(() => _notifications = []);
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _markingRead = false);
    }
  }

  Widget _notificationCard(Map<String, dynamic> notification) {
    final creator = notification['usuario_nome'] as String? ?? 'Funcionário';
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BDCard(
        onTap: _markingRead ? null : () => _open(notification),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: BD.blueBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.notification_important_outlined,
                color: BD.primary,
                size: 19,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification['titulo'] as String? ?? 'Nova ocorrência',
                    style: BD.cardTitle.copyWith(fontSize: 13),
                  ),
                  const SizedBox(height: 3),
                  Text('Criada por $creator', style: BD.caption),
                  Text(
                    '${notification['nome_maquina'] ?? 'Máquina não informada'}'
                    ' · ${formatDateTime(parseDate(notification['data_criacao']))}',
                    style: BD.caption,
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: BD.muted),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notificações'),
        actions: [
          if (_notifications.isNotEmpty)
            TextButton(
              onPressed: _markingRead ? null : _readAll,
              child: const Text('Ler todas'),
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: _notifications.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(BD.margin),
                child: Text(
                  'Você está em dia. Novas ocorrências de funcionários '
                  'aparecerão aqui.',
                  textAlign: TextAlign.center,
                  style: BD.body.copyWith(color: BD.muted),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(
                BD.margin,
                14,
                BD.margin,
                32,
              ),
              children: [
                Text(
                  'Ocorrências registradas por funcionários',
                  style: BD.caption.copyWith(color: BD.muted),
                ),
                const SizedBox(height: 10),
                for (final notification in _notifications)
                  _notificationCard(notification),
              ],
            ),
    );
  }
}
