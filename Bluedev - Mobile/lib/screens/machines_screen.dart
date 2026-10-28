import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'machine_form_screen.dart';

class MachinesScreen extends StatefulWidget {
  const MachinesScreen({required this.session, super.key});

  final Session session;

  @override
  State<MachinesScreen> createState() => _MachinesScreenState();
}

class _MachinesScreenState extends State<MachinesScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _machines = [];
  String? _error;
  bool _loading = true;
  bool _deleting = false;

  @override
  void initState() {
    super.initState();
    _search.addListener(() => setState(() {}));
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _error = null;
        if (_machines.isEmpty) _loading = true;
      });
    }
    try {
      final machines = await widget.session.api.getMaquinasDetalhadas();
      if (mounted) setState(() => _machines = machines);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visibleMachines {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return _machines;
    return _machines.where((machine) {
      final values = [
        machine['nome'],
        machine['codigo'],
        machine['modelo'],
        machine['fabricante'],
      ];
      return values.any((value) => '$value'.toLowerCase().contains(query));
    }).toList();
  }

  Future<void> _openForm([Map<String, dynamic>? machine]) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => MachineFormScreen(
        session: widget.session,
        machine: machine,
      ),
    ));
    if (saved == true && mounted) {
      await _load();
      if (mounted) {
        showInfo(
          context,
          machine == null ? 'Máquina adicionada.' : 'Máquina atualizada.',
        );
      }
    }
  }

  Future<void> _delete(Map<String, dynamic> machine) async {
    final name = machine['nome'] as String? ?? 'esta máquina';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Remover máquina?', style: BD.cardTitle),
        content: Text(
          'Deseja remover "$name"? Esta ação não pode ser desfeita.',
          style: BD.body,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: BD.red),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Remover'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _deleting = true);
    try {
      await widget.session.api.deleteMaquina(int.parse('${machine['id']}'));
      if (!mounted) return;
      await _load();
      if (mounted) showInfo(context, 'Máquina removida.');
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  Widget _machineCard(Map<String, dynamic> machine) {
    final name = machine['nome'] as String? ?? 'Sem nome';
    final status = machine['status_maquina'] as String? ?? '';
    final description = (machine['descricao'] as String?)?.trim() ?? '';

    return BDCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: BD.cardTitle.copyWith(fontSize: 13),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                status.isEmpty ? '—' : status,
                style: BD.caption.copyWith(
                  color: status == 'ativa' ? BD.green : BD.amber,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('Código: ${machine['codigo'] ?? '—'}', style: BD.caption),
          Text('Modelo: ${machine['modelo'] ?? '—'}', style: BD.caption),
          Text('Fabricante: ${machine['fabricante'] ?? '—'}',
              style: BD.caption),
          if (machine['numero_serie'] != null &&
              '${machine['numero_serie']}'.isNotEmpty)
            Text('Nº de Série: ${machine['numero_serie']}', style: BD.caption),
          if (machine['data_aquisicao'] != null &&
              '${machine['data_aquisicao']}'.isNotEmpty)
            Text(
              'Data de Aquisição: ${formatDate(parseDate(machine['data_aquisicao']))}',
              style: BD.caption,
            ),
          if (description.isNotEmpty)
            Text(
              'Descrição: $description',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: BD.caption,
            ),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              IconButton(
                tooltip: 'Editar máquina',
                onPressed: _deleting ? null : () => _openForm(machine),
                icon: const Icon(
                  Icons.edit_outlined,
                  size: 19,
                  color: BD.primary,
                ),
              ),
              IconButton(
                tooltip: 'Remover máquina',
                onPressed: _deleting ? null : () => _delete(machine),
                icon: const Icon(
                  Icons.delete_outline_rounded,
                  size: 19,
                  color: BD.red,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final machines = _visibleMachines;
    return Scaffold(
      appBar: AppBar(title: const Text('Máquinas')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding:
                    const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 40),
                children: [
                  FilledButton.icon(
                    onPressed: () => _openForm(),
                    icon: const Icon(Icons.add_rounded),
                    label: const Text('Adicionar máquina'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _search,
                    style: BD.body,
                    decoration: InputDecoration(
                      hintText: 'Buscar máquina',
                      prefixIcon: const Icon(Icons.search_rounded, size: 19),
                      suffixIcon: _search.text.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Limpar busca',
                              icon: const Icon(Icons.close_rounded, size: 18),
                              onPressed: _search.clear,
                            ),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    InfoBanner(
                      icon: Icons.wifi_off_rounded,
                      text: _error!,
                      foreground: BD.red,
                      background: BD.redBg,
                      action: TextButton(
                        onPressed: _load,
                        child: const Text('Tentar novamente'),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  if (machines.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 30),
                      child: Text(
                        _error == null
                            ? 'Nenhuma máquina cadastrada no momento.'
                            : 'Não foi possível carregar as máquinas.',
                        textAlign: TextAlign.center,
                        style: BD.caption,
                      ),
                    )
                  else
                    for (final machine in machines) ...[
                      _machineCard(machine),
                      const SizedBox(height: 10),
                    ],
                ],
              ),
            ),
    );
  }
}
