import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrence_detail_screen.dart';

/// M02 · Início ADM (pagamento validado)
/// M03 · Início ADM (teste grátis)
/// M05 · Início FUNC
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    required this.session,
    required this.onSeeAll,
    required this.onReports,
    super.key,
  });

  final Session session;
  final VoidCallback onSeeAll;
  final VoidCallback onReports;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Map<String, dynamic>>? _items;
  String? _error;

  Session get _session => widget.session;

  @override
  void initState() {
    super.initState();
    _session.revision.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    _session.revision.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final items = await _session.api.getOcorrencias();
      if (mounted) {
        setState(() {
          _items = items;
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    }
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
      builder: (_) => OccurrenceDetailScreen(session: _session, occurrence: item),
    ));
    if (changed == true) _session.changed();
  }

  @override
  Widget build(BuildContext context) {
    final trial = _session.isAdmin && _session.plan == PlanStatus.teste;
    final recent = (_items ?? []).take(3).toList();

    return Scaffold(
      appBar: AppBar(
        title: const ClientLogo(),
        actions: [
          SquareIconButton(
            icon: Icons.notifications_none_rounded,
            tooltip: 'Notificações',
            onPressed: () => showSoon(context, 'Notificações'),
          ),
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
            if (_session.isAdmin) ...[
              trial
                  ? InfoBanner(
                      icon: Icons.warning_amber_rounded,
                      text:
                          'Teste grátis vence em ${_session.trialDaysLeft} dias',
                      foreground: BD.amber,
                      background: BD.amberBg,
                      action: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: BD.amber,
                          minimumSize: const Size(0, 34),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          textStyle: BD.caption.copyWith(
                              fontWeight: FontWeight.w600, fontSize: 11),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () => showSoon(context, 'Assinatura'),
                        child: const Text('Renovar agora'),
                      ),
                    )
                  : const InfoBanner(
                      icon: Icons.check_rounded,
                      text: 'Pagamento efetuado',
                      foreground: BD.green,
                      background: BD.greenBg,
                    ),
              const SizedBox(height: 12),
            ],
            if (_error != null)
              InfoBanner(
                icon: Icons.wifi_off_rounded,
                text: _error!,
                foreground: BD.red,
                background: BD.redBg,
                action: TextButton(onPressed: _load, child: const Text('Tentar')),
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
            if (_session.isAdmin) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  _Shortcut(
                    icon: Icons.local_shipping_outlined,
                    label: 'Fornec.',
                    onTap: () => showSoon(context, 'Fornecedores'),
                  ),
                  const SizedBox(width: 8),
                  _Shortcut(
                    icon: Icons.group_outlined,
                    label: 'Funcion.',
                    onTap: () => showSoon(context, 'Funcionários'),
                  ),
                  const SizedBox(width: 8),
                  _Shortcut(
                    icon: Icons.settings_suggest_outlined,
                    label: 'Máquinas',
                    onTap: () => showSoon(context, 'Máquinas'),
                  ),
                  const SizedBox(width: 8),
                  _Shortcut(
                    icon: Icons.bar_chart_rounded,
                    label: 'Relatórios',
                    onTap: widget.onReports,
                  ),
                ],
              ),
            ],
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

class _Shortcut extends StatelessWidget {
  const _Shortcut({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: BDCard(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: BD.iconBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: BD.primary),
            ),
            const SizedBox(height: 6),
            Text(label,
                style: BD.caption.copyWith(
                    fontSize: 10.5,
                    color: BD.navy,
                    fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}
