import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

/// M11 · Remover ocorrências (modo seleção). Devolve true se removeu algo.
class RemoveOccurrencesScreen extends StatefulWidget {
  const RemoveOccurrencesScreen({
    required this.session,
    required this.items,
    this.initiallySelected,
    super.key,
  });

  final Session session;
  final List<Map<String, dynamic>> items;
  final int? initiallySelected;

  @override
  State<RemoveOccurrencesScreen> createState() =>
      _RemoveOccurrencesScreenState();
}

class _RemoveOccurrencesScreenState extends State<RemoveOccurrencesScreen> {
  late final Set<int> _selected = {
    if (widget.initiallySelected != null) widget.initiallySelected!,
  };
  late List<Map<String, dynamic>> _items = widget.items;
  bool _deleting = false;
  bool _removedAny = false;

  int _id(Map<String, dynamic> item) => int.parse('${item['id']}');

  Future<void> _remove() async {
    final count = _selected.length;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          count == 1 ? 'Remover 1 ocorrência?' : 'Remover $count ocorrências?',
          style: BD.cardTitle.copyWith(fontSize: 16),
        ),
        content: Text('Esta ação não pode ser desfeita.', style: BD.body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: BD.red,
              minimumSize: const Size(0, BD.smallButtonHeight),
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Remover'),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    setState(() => _deleting = true);
    var removed = 0;
    String? failure;
    for (final id in _selected.toList()) {
      try {
        await widget.session.api.deleteOcorrencia(id);
        removed++;
        _selected.remove(id);
        _items = _items.where((item) => _id(item) != id).toList();
      } catch (error) {
        failure = friendlyError(error);
      }
    }
    if (removed > 0) _removedAny = true;
    if (!mounted) return;
    setState(() => _deleting = false);
    if (failure != null) {
      showError(context, 'Removidas $removed de $count. $failure');
      return;
    }
    showInfo(context,
        removed == 1 ? '1 ocorrência removida.' : '$removed ocorrências removidas.');
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final count = _selected.length;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.pop(context, _removedAny);
      },
      child: Scaffold(
        appBar: AppBar(
          backgroundColor: BD.navy,
          foregroundColor: Colors.white,
          systemOverlayStyle: SystemUiOverlayStyle.light,
          shape: const Border(),
          titleSpacing: 0,
          leading: Padding(
            padding: const EdgeInsets.all(10),
            child: SquareIconButton(
              icon: Icons.close_rounded,
              tooltip: 'Cancelar',
              color: Colors.white,
              background: Colors.white.withValues(alpha: 0.12),
              onPressed: () => Navigator.pop(context, _removedAny),
            ),
          ),
          title: Text(
            count == 1 ? '1 selecionada' : '$count selecionadas',
            style: BD.cardTitle.copyWith(color: Colors.white, fontSize: 15),
          ),
        ),
        body: ListView.separated(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          itemCount: _items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final item = _items[index];
            final selected = _selected.contains(_id(item));
            return BDCard(
              borderColor: selected ? BD.primary : BD.border,
              padding: const EdgeInsets.fromLTRB(6, 6, 12, 6),
              onTap: () => setState(() {
                if (!_selected.remove(_id(item))) _selected.add(_id(item));
              }),
              child: Row(
                children: [
                  Checkbox(
                    value: selected,
                    activeColor: BD.primary,
                    side: const BorderSide(color: BD.border, width: 1.5),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(5)),
                    onChanged: (_) => setState(() {
                      if (!_selected.remove(_id(item))) {
                        _selected.add(_id(item));
                      }
                    }),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['titulo'] as String? ?? 'Sem título',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: BD.cardTitle.copyWith(fontSize: 13),
                        ),
                        Text(occurrenceSubtitle(item), style: BD.caption),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  StatusChip(item['status'] as String?),
                ],
              ),
            );
          },
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(BD.margin, 8, BD.margin, 12),
            child: FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: BD.red),
              onPressed: count == 0 || _deleting ? null : _remove,
              icon: _deleting
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.delete_outline_rounded, size: 19),
              label: Text('Remover ($count)'),
            ),
          ),
        ),
      ),
    );
  }
}
