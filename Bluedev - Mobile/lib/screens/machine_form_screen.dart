import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

class MachineFormScreen extends StatefulWidget {
  const MachineFormScreen({
    required this.session,
    this.machine,
    super.key,
  });

  final Session session;
  final Map<String, dynamic>? machine;

  @override
  State<MachineFormScreen> createState() => _MachineFormScreenState();
}

class _MachineFormScreenState extends State<MachineFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _code;
  late final TextEditingController _model;
  late final TextEditingController _manufacturer;
  late final TextEditingController _description;
  DateTime? _acquisitionDate;
  String? _status;
  bool _saving = false;

  bool get _editing => widget.machine != null;

  @override
  void initState() {
    super.initState();
    final machine = widget.machine;
    _name = TextEditingController(text: machine?['nome'] as String? ?? '');
    _code = TextEditingController(text: machine?['codigo'] as String? ?? '');
    _model = TextEditingController(text: machine?['modelo'] as String? ?? '');
    _manufacturer =
        TextEditingController(text: machine?['fabricante'] as String? ?? '');
    _description =
        TextEditingController(text: machine?['descricao'] as String? ?? '');
    _acquisitionDate = parseDate(machine?['data_aquisicao']);
    _status = machine?['status_maquina'] as String?;
  }

  Future<void> _pickAcquisitionDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _acquisitionDate ?? now,
      firstDate: DateTime(1900),
      lastDate: DateTime(now.year + 1, 12, 31),
    );
    if (picked != null && mounted) {
      setState(() => _acquisitionDate = picked);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.session.api.saveMaquina(
        {
          'nome': _name.text.trim(),
          'codigo': _code.text.trim(),
          'modelo': _model.text.trim(),
          'fabricante': _manufacturer.text.trim(),
          'data_aquisicao': _acquisitionDate == null
              ? ''
              : _acquisitionDate!.toIso8601String().substring(0, 10),
          'status_maquina': _status,
          'descricao': _description.text.trim(),
        },
        id: _editing ? int.parse('${widget.machine!['id']}') : null,
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
    _name.dispose();
    _code.dispose();
    _model.dispose();
    _manufacturer.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Editar máquina' : 'Nova máquina'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            const FieldLabel('Nome'),
            TextFormField(
              controller: _name,
              maxLength: 100,
              style: BD.body,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                hintText: 'Nome da máquina',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o nome.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Código'),
            TextFormField(
              controller: _code,
              maxLength: 50,
              style: BD.body,
              decoration: const InputDecoration(
                hintText: 'Código da máquina',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o código.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Modelo'),
            TextFormField(
              controller: _model,
              maxLength: 100,
              style: BD.body,
              decoration: const InputDecoration(hintText: 'Modelo'),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Fabricante'),
            TextFormField(
              controller: _manufacturer,
              maxLength: 100,
              style: BD.body,
              decoration: const InputDecoration(hintText: 'Fabricante'),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Data de Aquisição'),
            InkWell(
              borderRadius: BorderRadius.circular(BD.fieldRadius),
              onTap: _pickAcquisitionDate,
              child: InputDecorator(
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.calendar_today_outlined),
                  suffixIcon: _acquisitionDate == null
                      ? null
                      : IconButton(
                          tooltip: 'Limpar data',
                          onPressed: () =>
                              setState(() => _acquisitionDate = null),
                          icon: const Icon(Icons.close_rounded),
                        ),
                ),
                child: Text(
                  _acquisitionDate == null
                      ? 'Selecione uma data'
                      : formatDate(_acquisitionDate),
                  style: _acquisitionDate == null
                      ? BD.body.copyWith(color: BD.muted)
                      : BD.body,
                ),
              ),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Status da Máquina'),
            DropdownButtonFormField<String>(
              initialValue: _status,
              isExpanded: true,
              style: BD.body,
              icon: const Icon(Icons.keyboard_arrow_down_rounded),
              hint: Text('Selecione', style: BD.body.copyWith(color: BD.muted)),
              items: const [
                DropdownMenuItem(value: 'ativa', child: Text('Ativa')),
                DropdownMenuItem(value: 'inativa', child: Text('Inativa')),
                DropdownMenuItem(
                  value: 'em manutenção',
                  child: Text('Em manutenção'),
                ),
              ],
              onChanged: (value) => setState(() => _status = value),
              validator: (value) =>
                  value == null ? 'Selecione o status.' : null,
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
                hintText: 'Descrição da máquina',
              ),
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(_editing ? 'Salvar alterações' : 'Adicionar'),
            ),
          ],
        ),
      ),
    );
  }
}
