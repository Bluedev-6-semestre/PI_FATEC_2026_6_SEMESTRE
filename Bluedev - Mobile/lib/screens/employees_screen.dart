import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'employee_form_screen.dart';

class EmployeesScreen extends StatefulWidget {
  const EmployeesScreen({required this.session, super.key});

  final Session session;

  @override
  State<EmployeesScreen> createState() => _EmployeesScreenState();
}

class _EmployeesScreenState extends State<EmployeesScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _employees = [];
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
        if (_employees.isEmpty) _loading = true;
      });
    }
    try {
      final employees = await widget.session.api.getFuncionarios();
      if (mounted) setState(() => _employees = employees);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visibleEmployees {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return _employees;
    return _employees.where((employee) {
      final name = '${employee['nome'] ?? ''}'.toLowerCase();
      final role = '${employee['funcao'] ?? ''}'.toLowerCase();
      return name.contains(query) || role.contains(query);
    }).toList();
  }

  Future<void> _openForm([Map<String, dynamic>? employee]) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => EmployeeFormScreen(
        session: widget.session,
        employee: employee,
      ),
    ));
    if (saved == true && mounted) {
      await _load();
      if (mounted) {
        showInfo(
          context,
          employee == null
              ? 'Funcionário adicionado.'
              : 'Funcionário atualizado.',
        );
      }
    }
  }

  Future<void> _delete(Map<String, dynamic> employee) async {
    final name = employee['nome'] as String? ?? 'este funcionário';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Remover funcionário?', style: BD.cardTitle),
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
      await widget.session.api
          .deleteFuncionario(int.parse('${employee['id']}'));
      if (!mounted) return;
      await _load();
      if (mounted) showInfo(context, 'Funcionário removido.');
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  Widget _employeeCard(Map<String, dynamic> employee) {
    return BDCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            employee['nome'] as String? ?? 'Sem nome',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: BD.cardTitle.copyWith(fontSize: 13),
          ),
          const SizedBox(height: 4),
          Text('Idade: ${employee['idade'] ?? '—'}', style: BD.caption),
          Text('Função: ${employee['funcao'] ?? '—'}', style: BD.caption),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              IconButton(
                tooltip: 'Editar funcionário',
                onPressed: _deleting ? null : () => _openForm(employee),
                icon: const Icon(
                  Icons.edit_outlined,
                  size: 19,
                  color: BD.primary,
                ),
              ),
              IconButton(
                tooltip: 'Remover funcionário',
                onPressed: _deleting ? null : () => _delete(employee),
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
    final employees = _visibleEmployees;
    return Scaffold(
      appBar: AppBar(title: const Text('Funcionários')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(
                  BD.margin,
                  14,
                  BD.margin,
                  40,
                ),
                children: [
                  FilledButton.icon(
                    onPressed: () => _openForm(),
                    icon: const Icon(Icons.add_rounded),
                    label: const Text('Adicionar funcionário'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _search,
                    style: BD.body,
                    decoration: InputDecoration(
                      hintText: 'Buscar funcionário',
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
                  if (employees.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 30),
                      child: Text(
                        _error == null
                            ? 'Nenhum funcionário cadastrado no momento.'
                            : 'Não foi possível carregar os funcionários.',
                        textAlign: TextAlign.center,
                        style: BD.caption,
                      ),
                    )
                  else
                    for (final employee in employees) ...[
                      _employeeCard(employee),
                      const SizedBox(height: 10),
                    ],
                ],
              ),
            ),
    );
  }
}
