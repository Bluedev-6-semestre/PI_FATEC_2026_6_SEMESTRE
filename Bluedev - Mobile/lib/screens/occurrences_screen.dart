import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrence_detail_screen.dart';
import 'occurrence_form_screen.dart';
import 'remove_occurrences_screen.dart';

/// Status gravados no banco e o rótulo de filtro de cada um.
const statusFilters = <String?, String>{
  null: 'Todas',
  'Pendente': 'Pendentes',
  'Em andamento': 'Em andamento',
  'Concluída': 'Resolvidas',
};
const tipos = ['Preventiva', 'Corretiva'];

/// Filtros avançados (M08).
class OccurrenceFilters {
  const OccurrenceFilters({
    this.status,
    this.tipo,
    this.maquinaId,
    this.from,
    this.to,
  });

  final String? status;
  final String? tipo;
  final int? maquinaId;
  final DateTime? from;
  final DateTime? to;

  bool get hasAdvanced =>
      tipo != null || maquinaId != null || from != null || to != null;

  OccurrenceFilters withStatus(String? value) => OccurrenceFilters(
      status: value, tipo: tipo, maquinaId: maquinaId, from: from, to: to);

  bool matches(Map<String, dynamic> item) {
    final itemStatus = item['status'] as String?;
    if (status != null) {
      final same = status == 'Concluída'
          ? isResolved(itemStatus)
          : itemStatus == status;
      if (!same) return false;
    }
    if (tipo != null && item['tipo'] != tipo) return false;
    if (maquinaId != null && '${item['maquina_id']}' != '$maquinaId') {
      return false;
    }
    final date = parseDate(item['data_criacao']);
    if (from != null && (date == null || date.isBefore(from!))) return false;
    if (to != null &&
        (date == null || !date.isBefore(to!.add(const Duration(days: 1))))) {
      return false;
    }
    return true;
  }
}

/// M07 · Lista de ocorrências
/// M12 · Lista vazia
class OccurrencesScreen extends StatefulWidget {
  const OccurrencesScreen({
    required this.session,
    required this.onNew,
    super.key,
  });

  final Session session;
  final VoidCallback onNew;

  @override
  State<OccurrencesScreen> createState() => _OccurrencesScreenState();
}

class _OccurrencesScreenState extends State<OccurrencesScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _items = [];
  OccurrenceFilters _filters = const OccurrenceFilters();
  String? _error;
  bool _loading = true;

  Session get _session => widget.session;

  @override
  void initState() {
    super.initState();
    _session.revision.addListener(_load);
    _search.addListener(() => setState(() {}));
    _load();
  }

  @override
  void dispose() {
    _session.revision.removeListener(_load);
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final items = await _session.api.getOcorrencias();
      if (mounted) setState(() => _items = items);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visible {
    final query = _search.text.trim().toLowerCase();
    return _items.where((item) {
      if (!_filters.matches(item)) return false;
      if (query.isEmpty) return true;
      final text = '${item['titulo'] ?? ''} ${item['nome_maquina'] ?? ''}'
          .toLowerCase();
      return text.contains(query);
    }).toList();
  }

  bool get _filtering =>
      _search.text.trim().isNotEmpty ||
      _filters.status != null ||
      _filters.hasAdvanced;

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => OccurrenceDetailScreen(session: _session, occurrence: item),
    ));
    if (changed == true) _session.changed();
  }

  Future<void> _openEdit(Map<String, dynamic> item) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => OccurrenceFormScreen(session: _session, occurrence: item),
    ));
    if (saved == true && mounted) {
      _session.changed();
      showInfo(context, 'Ocorrência atualizada.');
    }
  }

  Future<void> _openRemove(Map<String, dynamic> item) async {
    final removed = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => RemoveOccurrencesScreen(
        session: _session,
        items: _visible,
        initiallySelected: int.parse('${item['id']}'),
      ),
    ));
    if (removed == true) _session.changed();
  }

  Future<void> _openFilters() async {
    final result = await showModalBottomSheet<OccurrenceFilters>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _FilterSheet(session: _session, initial: _filters),
    );
    if (result != null) setState(() => _filters = result);
  }

  void _clearFilters() {
    _search.clear();
    setState(() => _filters = const OccurrenceFilters());
  }

  @override
  Widget build(BuildContext context) {
    final items = _visible;
    final showEmpty = !_loading && _error == null && items.isEmpty;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ocorrências'),
        actions: [
          SquareIconButton(
            icon: Icons.tune_rounded,
            tooltip: 'Filtros',
            color: _filters.hasAdvanced ? Colors.white : BD.navy,
            background: _filters.hasAdvanced ? BD.primary : BD.background,
            onPressed: _openFilters,
          ),
          const SizedBox(width: BD.margin),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty && _error == null
              ? _EmptyState(onAdd: widget.onNew)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(
                        BD.margin, 14, BD.margin, 40),
                    children: [
                      TextField(
                        controller: _search,
                        style: BD.body,
                        decoration: InputDecoration(
                          hintText: 'Buscar por título ou máquina',
                          prefixIcon:
                              const Icon(Icons.search_rounded, size: 19),
                          suffixIcon: _search.text.isEmpty
                              ? null
                              : IconButton(
                                  icon: const Icon(Icons.close_rounded,
                                      size: 18),
                                  onPressed: _search.clear,
                                ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            for (final entry in statusFilters.entries)
                              Padding(
                                padding: const EdgeInsets.only(right: 6),
                                child: FilterPill(
                                  label: entry.value,
                                  selected: _filters.status == entry.key,
                                  onTap: () => setState(() => _filters =
                                      _filters.withStatus(entry.key)),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (_error != null)
                        InfoBanner(
                          icon: Icons.wifi_off_rounded,
                          text: _error!,
                          foreground: BD.red,
                          background: BD.redBg,
                          action: TextButton(
                              onPressed: _load, child: const Text('Tentar')),
                        )
                      else if (showEmpty)
                        _NoResults(onClear: _clearFilters, filtering: _filtering)
                      else
                        for (final item in items)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _OccurrenceCard(
                              item: item,
                              onTap: () => _openDetail(item),
                              onView: () => _openDetail(item),
                              onEdit: () => _openEdit(item),
                              onLongPress: _session.isAdmin
                                  ? () => _openRemove(item)
                                  : null,
                            ),
                          ),
                    ],
                  ),
                ),
    );
  }
}

class _OccurrenceCard extends StatelessWidget {
  const _OccurrenceCard({
    required this.item,
    required this.onTap,
    required this.onView,
    required this.onEdit,
    this.onLongPress,
  });

  final Map<String, dynamic> item;
  final VoidCallback onTap;
  final VoidCallback onView;
  final VoidCallback onEdit;
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    return BDCard(
      onTap: onTap,
      onLongPress: onLongPress,
      padding: const EdgeInsets.fromLTRB(14, 12, 6, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    item['titulo'] as String? ?? 'Sem título',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: BD.cardTitle.copyWith(fontSize: 13),
                  ),
                ),
                const SizedBox(width: 8),
                StatusChip(item['status'] as String?),
              ],
            ),
          ),
          const SizedBox(height: 3),
          Text(occurrenceSubtitle(item), style: BD.caption),
          Row(
            children: [
              Expanded(
                child: Text(formatDate(parseDate(item['data_criacao'])),
                    style: BD.caption),
              ),
              IconButton(
                tooltip: 'Ver',
                visualDensity: VisualDensity.compact,
                onPressed: onView,
                icon: const Icon(Icons.visibility_outlined,
                    size: 19, color: BD.primary),
              ),
              IconButton(
                tooltip: 'Editar',
                visualDensity: VisualDensity.compact,
                onPressed: onEdit,
                icon: const Icon(Icons.edit_outlined,
                    size: 18, color: BD.primary),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Nenhum item bate com a busca ou os filtros.
class _NoResults extends StatelessWidget {
  const _NoResults({required this.onClear, required this.filtering});

  final VoidCallback onClear;
  final bool filtering;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 32),
      child: Column(
        children: [
          Text('Nenhum resultado para esta busca.',
              textAlign: TextAlign.center, style: BD.cardTitle),
          const SizedBox(height: 4),
          Text('Tente outro termo ou limpe os filtros.',
              textAlign: TextAlign.center, style: BD.caption),
          if (filtering) ...[
            const SizedBox(height: 12),
            TextButton(onPressed: onClear, child: const Text('Limpar filtros')),
          ],
        ],
      ),
    );
  }
}

/// M12 · Lista vazia
class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(BD.margin, 48, BD.margin, 40),
      children: [
        Text('Nenhum resultado disponível no momento.',
            textAlign: TextAlign.center,
            style: BD.cardTitle.copyWith(fontSize: 13)),
        const SizedBox(height: 20),
        Image.asset('assets/images/lista_vazia.png', height: 220),
        const SizedBox(height: 20),
        Text('Registre a primeira ocorrência da sua equipe.',
            textAlign: TextAlign.center, style: BD.caption),
        const SizedBox(height: 14),
        Center(
          child: FilledButton.icon(
            style: FilledButton.styleFrom(
              minimumSize: const Size(150, BD.smallButtonHeight),
            ),
            onPressed: onAdd,
            icon: const Icon(Icons.add_rounded, size: 18),
            label: const Text('Adicionar'),
          ),
        ),
      ],
    );
  }
}

/// M08 · Filtros (bottom sheet)
class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.session, required this.initial});

  final Session session;
  final OccurrenceFilters initial;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String? _status = widget.initial.status;
  late String? _tipo = widget.initial.tipo;
  late int? _maquinaId = widget.initial.maquinaId;
  late DateTime? _from = widget.initial.from;
  late DateTime? _to = widget.initial.to;
  List<Map<String, dynamic>> _machines = [];

  @override
  void initState() {
    super.initState();
    widget.session.api.getMaquinas().then((machines) {
      if (mounted) setState(() => _machines = machines);
    }).catchError((_) {});
  }

  Future<void> _pickDate(bool isFrom) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: (isFrom ? _from : _to) ?? now,
      firstDate: DateTime(2020),
      lastDate: DateTime(now.year + 1, 12, 31),
    );
    if (picked != null) setState(() => isFrom ? _from = picked : _to = picked);
  }

  Widget _pills<T>(Map<T?, String> options, T? value, ValueChanged<T?> onTap) {
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final entry in options.entries)
          FilterPill(
            label: entry.value,
            selected: value == entry.key,
            onTap: () => onTap(entry.key),
          ),
      ],
    );
  }

  Widget _dateField(String label, DateTime? value, bool isFrom) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          FieldLabel(label),
          InkWell(
            borderRadius: BorderRadius.circular(BD.fieldRadius),
            onTap: () => _pickDate(isFrom),
            child: InputDecorator(
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.calendar_today_outlined, size: 16),
              ),
              child: Text(
                value == null ? 'dd/mm/aaaa' : formatDate(value),
                style: value == null
                    ? BD.body.copyWith(color: BD.muted)
                    : BD.body,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(BD.margin, 0, BD.margin, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                    child: Text('Filtros',
                        style: BD.screenTitle.copyWith(fontSize: 16))),
                IconButton(
                  tooltip: 'Fechar',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const FieldLabel('Status'),
            _pills<String>(
                statusFilters, _status, (v) => setState(() => _status = v)),
            const SizedBox(height: 14),
            const FieldLabel('Tipo de manutenção'),
            _pills<String>({null: 'Todos', for (final t in tipos) t: t}, _tipo,
                (v) => setState(() => _tipo = v)),
            const SizedBox(height: 14),
            const FieldLabel('Máquina'),
            DropdownButtonFormField<int?>(
              initialValue: _maquinaId,
              isExpanded: true,
              style: BD.body,
              icon: const Icon(Icons.keyboard_arrow_down_rounded),
              items: [
                const DropdownMenuItem<int?>(
                    value: null, child: Text('Todas as máquinas')),
                for (final m in _machines)
                  DropdownMenuItem<int?>(
                    value: int.parse('${m['id']}'),
                    child: Text(m['nome'] as String? ?? 'Máquina'),
                  ),
              ],
              onChanged: (value) => setState(() => _maquinaId = value),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                _dateField('De', _from, true),
                const SizedBox(width: 10),
                _dateField('Até', _to, false),
              ],
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () =>
                        Navigator.pop(context, const OccurrenceFilters()),
                    child: const Text('Limpar'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () => Navigator.pop(
                      context,
                      OccurrenceFilters(
                        status: _status,
                        tipo: _tipo,
                        maquinaId: _maquinaId,
                        from: _from,
                        to: _to,
                      ),
                    ),
                    child: const Text('Aplicar'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
