import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'supplier_form_screen.dart';

class SuppliersScreen extends StatefulWidget {
  const SuppliersScreen({required this.session, super.key});

  final Session session;

  @override
  State<SuppliersScreen> createState() => _SuppliersScreenState();
}

class _SuppliersScreenState extends State<SuppliersScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _suppliers = [];
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
        if (_suppliers.isEmpty) _loading = true;
      });
    }
    try {
      final suppliers = await widget.session.api.getFornecedores();
      if (mounted) setState(() => _suppliers = suppliers);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visibleSuppliers {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return _suppliers;
    return _suppliers.where((supplier) {
      final values = [
        supplier['nome'],
        supplier['cnpj'],
        supplier['categoria'],
      ];
      return values.any((value) => '$value'.toLowerCase().contains(query));
    }).toList();
  }

  Future<void> _openForm([Map<String, dynamic>? supplier]) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => SupplierFormScreen(
        session: widget.session,
        supplier: supplier,
      ),
    ));
    if (saved == true && mounted) {
      await _load();
      if (mounted) {
        showInfo(
          context,
          supplier == null
              ? 'Fornecedor adicionado.'
              : 'Fornecedor atualizado.',
        );
      }
    }
  }

  Future<void> _delete(Map<String, dynamic> supplier) async {
    final name = supplier['nome'] as String? ?? 'este fornecedor';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Remover fornecedor?', style: BD.cardTitle),
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
      await widget.session.api.deleteFornecedor(int.parse('${supplier['id']}'));
      if (!mounted) return;
      await _load();
      if (mounted) showInfo(context, 'Fornecedor removido.');
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  Widget _supplierCard(Map<String, dynamic> supplier) {
    return BDCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 8, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            supplier['nome'] as String? ?? 'Sem nome',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: BD.cardTitle.copyWith(fontSize: 13),
          ),
          const SizedBox(height: 4),
          Text('CNPJ: ${supplier['cnpj'] ?? '—'}', style: BD.caption),
          Text('Categoria: ${supplier['categoria'] ?? '—'}', style: BD.caption),
          Text('Telefone: ${supplier['telefone'] ?? '—'}', style: BD.caption),
          Text('Endereço (CEP): ${supplier['endereco'] ?? '—'}',
              style: BD.caption),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              IconButton(
                tooltip: 'Editar fornecedor',
                onPressed: _deleting ? null : () => _openForm(supplier),
                icon: const Icon(
                  Icons.edit_outlined,
                  size: 19,
                  color: BD.primary,
                ),
              ),
              IconButton(
                tooltip: 'Remover fornecedor',
                onPressed: _deleting ? null : () => _delete(supplier),
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
    final suppliers = _visibleSuppliers;
    return Scaffold(
      appBar: AppBar(title: const Text('Fornecedores')),
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
                    label: const Text('Adicionar fornecedor'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _search,
                    style: BD.body,
                    decoration: InputDecoration(
                      hintText: 'Buscar fornecedor ou CNPJ',
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
                  if (suppliers.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 30),
                      child: Text(
                        _error == null
                            ? _suppliers.isEmpty
                                ? 'Nenhum fornecedor cadastrado no momento.'
                                : 'Nenhum resultado para esta busca.'
                            : 'Não foi possível carregar os fornecedores.',
                        textAlign: TextAlign.center,
                        style: BD.caption,
                      ),
                    )
                  else
                    for (final supplier in suppliers) ...[
                      _supplierCard(supplier),
                      const SizedBox(height: 10),
                    ],
                ],
              ),
            ),
    );
  }
}
