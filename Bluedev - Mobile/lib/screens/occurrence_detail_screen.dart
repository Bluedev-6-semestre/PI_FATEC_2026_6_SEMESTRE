import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrence_form_screen.dart';

/// M09 · Detalhe da ocorrência (Editar / Validar). Devolve true se mudou algo.
class OccurrenceDetailScreen extends StatefulWidget {
  const OccurrenceDetailScreen({
    required this.session,
    required this.occurrence,
    super.key,
  });

  final Session session;
  final Map<String, dynamic> occurrence;

  @override
  State<OccurrenceDetailScreen> createState() => _OccurrenceDetailScreenState();
}

class _OccurrenceDetailScreenState extends State<OccurrenceDetailScreen> {
  late Map<String, dynamic> _item = widget.occurrence;
  bool _changed = false;
  bool _busy = false;

  Session get _session => widget.session;
  bool get _done => isResolved(_item['status'] as String?);

  Future<void> _refresh() async {
    try {
      final items = await _session.api.getOcorrencias();
      final fresh = items.where((i) => '${i['id']}' == '${_item['id']}');
      if (mounted && fresh.isNotEmpty) setState(() => _item = fresh.first);
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    }
  }

  Future<void> _edit() async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => OccurrenceFormScreen(session: _session, occurrence: _item),
    ));
    if (saved == true) {
      _changed = true;
      await _refresh();
      if (mounted) showInfo(context, 'Ocorrência atualizada.');
    }
  }

  Future<bool> _confirm(String title, String message, String action,
      Color color) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title, style: BD.cardTitle.copyWith(fontSize: 16)),
        content: Text(message, style: BD.body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: color,
              minimumSize: const Size(0, BD.smallButtonHeight),
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(action),
          ),
        ],
      ),
    );
    return result == true;
  }

  Future<void> _validate() async {
    if (!await _confirm(
        'Validar ocorrência?',
        'Ela será marcada como Resolvida.',
        'Validar',
        BD.primary)) {
      return;
    }
    setState(() => _busy = true);
    try {
      await _session.api.validarOcorrencia(_item);
      _changed = true;
      await _refresh();
      if (mounted) showInfo(context, 'Ocorrência validada.');
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    if (!await _confirm('Remover ocorrência?',
        'Esta ação não pode ser desfeita.', 'Remover', BD.red)) {
      return;
    }
    setState(() => _busy = true);
    try {
      await _session.api.deleteOcorrencia(int.parse('${_item['id']}'));
      if (!mounted) return;
      showInfo(context, 'Ocorrência removida.');
      Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() => _busy = false);
        showError(context, friendlyError(error));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = _session.api.imageUrl(_item['imagem'] as String?);
    final description = (_item['descricao'] as String?)?.trim() ?? '';

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.pop(context, _changed);
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Ocorrência'),
          titleSpacing: 0,
          actions: [
            if (_session.isAdmin)
              SquareIconButton(
                icon: Icons.delete_outline_rounded,
                tooltip: 'Remover',
                color: BD.red,
                background: BD.redBg,
                onPressed: _busy ? null : _delete,
              ),
            const SizedBox(width: BD.margin),
          ],
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(BD.cardRadius),
              child: AspectRatio(
                aspectRatio: 16 / 10,
                child: imageUrl == null
                    ? const _PhotoPlaceholder()
                    : Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const _PhotoPlaceholder(),
                      ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(_item['titulo'] as String? ?? 'Sem título',
                      style: BD.cardTitle.copyWith(fontSize: 15)),
                ),
                const SizedBox(width: 8),
                StatusChip(_item['status'] as String?),
              ],
            ),
            const SizedBox(height: 8),
            _InfoRow('Tipo de manutenção', '${_item['tipo'] ?? '—'}'),
            _InfoRow('Máquina', '${_item['nome_maquina'] ?? '—'}'),
            _InfoRow('Registrada em',
                formatDateTime(parseDate(_item['data_criacao']))),
            const SizedBox(height: 14),
            Text('Descrição', style: BD.label),
            const SizedBox(height: 4),
            Text(description.isEmpty ? 'Sem descrição.' : description,
                style: BD.body.copyWith(fontSize: 12.5)),
          ],
        ),
        bottomNavigationBar: SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(BD.margin, 10, BD.margin, 12),
            decoration: const BoxDecoration(
              color: BD.surface,
              border: Border(top: BorderSide(color: BD.border)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(BD.smallButtonHeight),
                      side: const BorderSide(color: BD.primary),
                      foregroundColor: BD.navy,
                    ),
                    onPressed: _busy ? null : _edit,
                    icon: const Icon(Icons.edit_outlined, size: 17),
                    label: const Text('Editar'),
                  ),
                ),
                if (_session.isAdmin) ...[
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        minimumSize:
                            const Size.fromHeight(BD.smallButtonHeight),
                      ),
                      onPressed: _done || _busy ? null : _validate,
                      icon: _busy
                          ? const SizedBox.square(
                              dimension: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.check_rounded, size: 18),
                      label: Text(_done ? 'Validada' : 'Validar'),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PhotoPlaceholder extends StatelessWidget {
  const _PhotoPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFDDE6F3),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.image_outlined, color: BD.muted, size: 26),
          const SizedBox(height: 4),
          Text('Foto da ocorrência', style: BD.caption.copyWith(fontSize: 10.5)),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: BD.border)),
      ),
      child: Row(
        children: [
          Expanded(child: Text(label, style: BD.caption)),
          Text(value,
              style: BD.caption.copyWith(
                  color: BD.navy, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
