import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'notifications_screen.dart';
import 'occurrence_detail_screen.dart';
import 'profile_button.dart';

/// Início (ADM e FUNC): resumo e ocorrências recentes.
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    required this.session,
    required this.onSeeAll,
    super.key,
  });

  final Session session;
  final VoidCallback onSeeAll;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  List<Map<String, dynamic>>? _items;
  List<Map<String, dynamic>> _notifications = [];
  final Set<int> _announcedNotificationIds = {};
  Timer? _pollTimer;
  int? _lastReadOccurrenceId;
  int? _latestOccurrenceId;
  bool _loadingOccurrences = false;
  String? _error;

  Session get _session => widget.session;
  String get _notificationCursorKey =>
      'bluedev_last_read_occurrence_${_session.user['usuario_id'] ?? _session.name}';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _session.revision.addListener(_load);
    _load();
    if (_session.isAdmin) {
      _pollTimer = Timer.periodic(
        const Duration(seconds: 30),
        (_) {
          if (WidgetsBinding.instance.lifecycleState ==
              AppLifecycleState.resumed) {
            _load();
          }
        },
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pollTimer?.cancel();
    _session.revision.removeListener(_load);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load();
  }

  Future<void> _load() async {
    if (_loadingOccurrences) return;
    _loadingOccurrences = true;
    try {
      final items = await _session.api.getOcorrencias();
      if (_session.isAdmin) {
        final preferences = await SharedPreferences.getInstance();
        final storedCursor = preferences.getInt(_notificationCursorKey);
        final occurrenceIds = items
            .map((item) => int.tryParse('${item['id']}'))
            .whereType<int>()
            .toList();
        final latestId = occurrenceIds.isEmpty
            ? 0
            : occurrenceIds.reduce((a, b) => a > b ? a : b);
        _latestOccurrenceId = latestId;
        _lastReadOccurrenceId = storedCursor ?? latestId;
        if (storedCursor == null) {
          await preferences.setInt(_notificationCursorKey, latestId);
        }

        final currentUserId =
            int.tryParse('${_session.user['usuario_id'] ?? ''}');
        final unread = items.where((item) {
          final id = int.tryParse('${item['id']}');
          final creatorId = int.tryParse('${item['usuario_id'] ?? ''}');
          return id != null &&
              id > _lastReadOccurrenceId! &&
              creatorId != null &&
              creatorId != currentUserId;
        }).toList();
        final unreadIds = unread
            .map((item) => int.tryParse('${item['id']}'))
            .whereType<int>()
            .toSet();
        final newlyCreated =
            unreadIds.difference(_announcedNotificationIds).isNotEmpty;
        _announcedNotificationIds.addAll(unreadIds);
        if (mounted) {
          setState(() => _notifications = unread);
          if (newlyCreated) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  unread.length == 1
                      ? 'Um funcionário registrou uma nova ocorrência.'
                      : 'Novas ocorrências registradas por funcionários.',
                ),
                action: SnackBarAction(
                  label: 'Ver',
                  onPressed: _openNotifications,
                ),
              ),
            );
          }
        }
      }
      if (mounted) {
        setState(() {
          _items = items;
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      _loadingOccurrences = false;
    }
  }

  Future<void> _markReadThrough(int occurrenceId) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setInt(_notificationCursorKey, occurrenceId);
    if (!mounted) return;
    setState(() {
      _lastReadOccurrenceId = occurrenceId;
      _notifications.removeWhere(
        (item) {
          final id = int.tryParse('${item['id']}');
          return id != null && id <= occurrenceId;
        },
      );
      _announcedNotificationIds.removeWhere((id) => id <= occurrenceId);
    });
  }

  Future<void> _markAllRead() async {
    final latestId = _latestOccurrenceId;
    if (latestId == null) return;
    await _markReadThrough(latestId);
  }

  Future<void> _openNotifications() async {
    await _load();
    if (!mounted) return;
    await Navigator.of(context).push<void>(MaterialPageRoute(
      builder: (_) => NotificationsScreen(
        session: _session,
        notifications: _notifications,
        onReadThrough: _markReadThrough,
        onReadAll: _markAllRead,
      ),
    ));
  }

  int get _pending =>
      (_items ?? []).where((i) => !isResolved(i['status'] as String?)).length;

  /// A API não guarda a data da resolução; conta as resolvidas registradas no mês.
  int get _resolvedThisMonth {
    final now = DateTime.now();
    return (_items ?? []).where((i) {
      final date = parseDate(i['data_criacao']);
      return isResolved(i['status'] as String?) &&
          date != null &&
          date.year == now.year &&
          date.month == now.month;
    }).length;
  }

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) =>
          OccurrenceDetailScreen(session: _session, occurrence: item),
    ));
    if (changed == true) _session.changed();
  }

  @override
  Widget build(BuildContext context) {
    final recent = (_items ?? []).take(3).toList();

    return Scaffold(
      appBar: AppBar(
        title: const ClientLogo(),
        actions: [
          if (_session.isAdmin)
            Stack(
              clipBehavior: Clip.none,
              children: [
                SquareIconButton(
                  icon: Icons.notifications_none_rounded,
                  tooltip: 'Notificações',
                  onPressed: _openNotifications,
                ),
                if (_notifications.isNotEmpty)
                  Positioned(
                    top: -4,
                    right: -4,
                    child: Container(
                      constraints: const BoxConstraints(
                        minWidth: 17,
                        minHeight: 17,
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      decoration: const BoxDecoration(
                        color: BD.red,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        _notifications.length > 99
                            ? '99+'
                            : '${_notifications.length}',
                        style: BD.caption.copyWith(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          if (_session.isAdmin) const SizedBox(width: 10),
          ProfileButton(session: _session),
          const SizedBox(width: BD.margin),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 16, BD.margin, 40),
          children: [
            Text('Olá, ${_session.greetingName}', style: BD.caption),
            Text('Seja bem-vindo!', style: BD.screenTitle),
            const SizedBox(height: 12),
            if (_error != null)
              InfoBanner(
                icon: Icons.wifi_off_rounded,
                text: _error!,
                foreground: BD.red,
                background: BD.redBg,
                action:
                    TextButton(onPressed: _load, child: const Text('Tentar')),
              )
            else
              Row(
                children: [
                  _StatCard(
                    label: 'Pendentes',
                    value: _items == null ? null : _pending,
                  ),
                  const SizedBox(width: 10),
                  _StatCard(
                    label: 'Resolvidas (mês)',
                    value: _items == null ? null : _resolvedThisMonth,
                  ),
                ],
              ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text('Ocorrências recentes',
                      style: BD.cardTitle.copyWith(fontSize: 13)),
                ),
                GestureDetector(
                  onTap: widget.onSeeAll,
                  child: Text('Ver todas',
                      style: BD.caption.copyWith(
                          color: BD.primary, fontWeight: FontWeight.w600)),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (_items == null && _error == null)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (recent.isEmpty && _error == null)
              BDCard(
                child: Text('Nenhuma ocorrência registrada ainda.',
                    style: BD.caption),
              )
            else
              for (final item in recent)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: OccurrenceSummaryCard(
                    item: item,
                    onTap: () => _openDetail(item),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final int? value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: BDCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: BD.caption),
            const SizedBox(height: 2),
            Text(value == null ? '–' : '$value',
                style: BD.screenTitle.copyWith(fontSize: 20)),
          ],
        ),
      ),
    );
  }
}
