import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrences_screen.dart' show tipos;

/// Status gravados no banco e o rótulo mostrado no app.
const _statusOptions = {
  'Pendente': 'Pendente',
  'Em andamento': 'Em andamento',
  'Concluída': 'Resolvida',
};

/// M10 · Nova ocorrência — também usada para editar.
///
/// A API dos microsserviços guarda a imagem só como texto e não tem upload,
/// por isso o app não envia fotos.
class OccurrenceFormScreen extends StatefulWidget {
  const OccurrenceFormScreen({
    required this.session,
    this.occurrence,
    super.key,
  });

  final Session session;
  final Map<String, dynamic>? occurrence;

  @override
  State<OccurrenceFormScreen> createState() => _OccurrenceFormScreenState();
}

class _OccurrenceFormScreenState extends State<OccurrenceFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  String? _type;
  String? _status;
  int? _machineId;
  List<Map<String, dynamic>> _machines = [];
  bool _loadingMachines = true;
  bool _saving = false;

  bool get _editing => widget.occurrence != null;

  @override
  void initState() {
    super.initState();
    final item = widget.occurrence;
    _title = TextEditingController(text: item?['titulo'] as String? ?? '');
    _description =
        TextEditingController(text: item?['descricao'] as String? ?? '');
    _type = item?['tipo'] as String?;
    final status = item?['status'] as String?;
    _status = status == 'Concluído' ? 'Concluída' : status;
    _machineId = int.tryParse('${item?['maquina_id'] ?? ''}');
    _loadMachines();
  }

  Future<void> _loadMachines() async {
    try {
      final machines = await widget.session.api.getMaquinas();
      if (!mounted) return;
      setState(() {
        _machines = machines;
        if (_machineId != null &&
            !machines.any((m) => '${m['id']}' == '$_machineId')) {
          _machineId = null;
        }
      });
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _loadingMachines = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.session.api.saveOcorrencia(
        {
          'titulo': _title.text.trim(),
          'tipo': _type,
          'status': _status,
          'descricao': _description.text.trim(),
          'maquina_id': _machineId,
        },
        id: _editing ? int.parse('${widget.occurrence!['id']}') : null,
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  Widget _select<T>({
    required T? value,
    required String hint,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    required String error,
  }) {
    return DropdownButtonFormField<T>(
      initialValue: value,
      isExpanded: true,
      style: BD.body,
      icon: const Icon(Icons.keyboard_arrow_down_rounded),
      hint: Text(hint, style: BD.body.copyWith(color: BD.muted)),
      items: items,
      onChanged: onChanged,
      validator: (v) => v == null ? error : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Text(_editing ? 'Editar ocorrência' : 'Nova ocorrência'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            const FieldLabel('Título da ocorrência'),
            TextFormField(
              controller: _title,
              maxLength: 100,
              style: BD.body,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                hintText: 'Ex: Empilhadeira quebrou',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o título.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Tipo de manutenção'),
            _select<String>(
              value: _type,
              hint: 'Selecione',
              items: [
                for (final t in tipos)
                  DropdownMenuItem(value: t, child: Text(t)),
              ],
              onChanged: (v) => setState(() => _type = v),
              error: 'Selecione o tipo.',
            ),
            const SizedBox(height: 12),
            const FieldLabel('Status'),
            _select<String>(
              value: _status,
              hint: 'Selecione',
              items: [
                for (final entry in _statusOptions.entries)
                  DropdownMenuItem(value: entry.key, child: Text(entry.value)),
              ],
              onChanged: (v) => setState(() => _status = v),
              error: 'Selecione o status.',
            ),
            const SizedBox(height: 12),
            // O occurrence-service aceita ocorrência sem máquina.
            const FieldLabel('Máquina (opcional)'),
            DropdownButtonFormField<int?>(
              initialValue: _machineId,
              isExpanded: true,
              style: BD.body,
              icon: const Icon(Icons.keyboard_arrow_down_rounded),
              hint: Text('Carregando máquinas...',
                  style: BD.body.copyWith(color: BD.muted)),
              items: _loadingMachines
                  ? null
                  : [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text('Nenhuma máquina selecionada',
                            style: BD.body.copyWith(color: BD.muted)),
                      ),
                      for (final m in _machines)
                        DropdownMenuItem<int?>(
                          value: int.parse('${m['id']}'),
                          child: Text(m['nome'] as String? ?? 'Máquina'),
                        ),
                    ],
              onChanged: (v) => setState(() => _machineId = v),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Descrição'),
            TextFormField(
              controller: _description,
              minLines: 3,
              maxLines: 6,
              style: BD.body,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                hintText: 'Descreva o problema',
              ),
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : Text(_editing ? 'Salvar alterações' : 'Registrar ocorrência'),
            ),
          ],
        ),
      ),
    );
  }
}
