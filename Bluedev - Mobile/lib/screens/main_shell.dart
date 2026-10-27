import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'home_screen.dart';
import 'more_screen.dart';
import 'occurrence_form_screen.dart';
import 'occurrences_screen.dart';

/// Estrutura principal: abas Início, Ocorrências, Relatórios e Mais/Perfil,
/// com o botão central (+) que sempre abre Nova ocorrência (M10).
class MainShell extends StatefulWidget {
  const MainShell({required this.session, super.key});

  final Session session;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _tab = 0;

  Session get _session => widget.session;

  Future<void> _newOccurrence() async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => OccurrenceFormScreen(session: _session),
    ));
    if (saved == true && mounted) {
      _session.changed();
      showInfo(context, 'Ocorrência registrada.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeScreen(
        session: _session,
        onSeeAll: () => setState(() => _tab = 1),
        onReports: () => setState(() => _tab = 2),
      ),
      OccurrencesScreen(session: _session, onNew: _newOccurrence),
      const ReportsPlaceholder(),
      _session.isAdmin
          ? MoreScreen(session: _session)
          : ProfileScreen(session: _session),
    ];
    return Scaffold(
      body: IndexedStack(index: _tab, children: pages),
      bottomNavigationBar: _BottomBar(
        index: _tab,
        lastLabel: _session.isAdmin ? 'Mais' : 'Perfil',
        lastIcon: _session.isAdmin
            ? Icons.grid_view_rounded
            : Icons.person_outline_rounded,
        onSelect: (i) => setState(() => _tab = i),
        onCenter: _newOccurrence,
      ),
    );
  }
}

/// Barra inferior de 84 com o botão central de 58 elevado.
class _BottomBar extends StatelessWidget {
  const _BottomBar({
    required this.index,
    required this.lastLabel,
    required this.lastIcon,
    required this.onSelect,
    required this.onCenter,
  });

  final int index;
  final String lastLabel;
  final IconData lastIcon;
  final ValueChanged<int> onSelect;
  final VoidCallback onCenter;

  Widget _item(int i, IconData icon, String label) {
    final active = index == i;
    final color = active ? BD.primary : BD.muted;
    return Expanded(
      child: InkWell(
        onTap: () => onSelect(i),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 22, color: color),
            const SizedBox(height: 3),
            Text(
              label,
              style: BD.caption.copyWith(
                fontSize: 10.5,
                color: color,
                fontWeight: active ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.paddingOf(context).bottom;
    return SizedBox(
      height: 64 + bottomInset,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: Container(
              padding: EdgeInsets.only(bottom: bottomInset),
              decoration: const BoxDecoration(
                color: BD.surface,
                border: Border(top: BorderSide(color: BD.border)),
              ),
              child: Row(
                children: [
                  _item(0, Icons.home_outlined, 'Início'),
                  _item(1, Icons.format_list_bulleted_rounded, 'Ocorrências'),
                  const Expanded(child: SizedBox()),
                  _item(2, Icons.bar_chart_rounded, 'Relatórios'),
                  _item(3, lastIcon, lastLabel),
                ],
              ),
            ),
          ),
          Positioned(
            top: -22,
            left: 0,
            right: 0,
            child: Center(
              child: Tooltip(
                message: 'Nova ocorrência',
                child: Material(
                  color: BD.primary,
                  shape: const CircleBorder(
                    side: BorderSide(color: BD.surface, width: 4),
                  ),
                  elevation: 4,
                  shadowColor: BD.primary.withValues(alpha: 0.4),
                  child: InkWell(
                    customBorder: const CircleBorder(),
                    onTap: onCenter,
                    child: const SizedBox.square(
                      dimension: 58,
                      child: Icon(Icons.add_rounded,
                          color: Colors.white, size: 30),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
