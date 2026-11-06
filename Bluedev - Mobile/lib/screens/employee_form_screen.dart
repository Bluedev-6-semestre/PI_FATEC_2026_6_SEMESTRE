import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

class EmployeeFormScreen extends StatefulWidget {
  const EmployeeFormScreen({
    required this.session,
    this.employee,
    super.key,
  });

  final Session session;
  final Map<String, dynamic>? employee;

  @override
  State<EmployeeFormScreen> createState() => _EmployeeFormScreenState();
}

class _EmployeeFormScreenState extends State<EmployeeFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _age;
  late final TextEditingController _role;
  bool _saving = false;

  bool get _editing => widget.employee != null;

  @override
  void initState() {
    super.initState();
    final employee = widget.employee;
    _name = TextEditingController(text: employee?['nome'] as String? ?? '');
    _age = TextEditingController(text: '${employee?['idade'] ?? ''}');
    _role = TextEditingController(text: employee?['funcao'] as String? ?? '');
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.session.api.saveFuncionario(
        {
          'nome': _name.text.trim(),
          'idade': int.parse(_age.text.trim()),
          'funcao': _role.text.trim(),
        },
        id: _editing ? int.parse('${widget.employee!['id']}') : null,
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
    _age.dispose();
    _role.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Editar funcionário' : 'Novo funcionário'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            const FieldLabel('Nome'),
            TextFormField(
              controller: _name,
              maxLength: 255,
              style: BD.body,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                hintText: 'Digite o nome',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o nome.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Idade'),
            TextFormField(
              controller: _age,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: BD.body,
              decoration: const InputDecoration(hintText: 'Digite a idade'),
              validator: (value) {
                final age = int.tryParse(value?.trim() ?? '');
                if (age == null) return 'Informe a idade.';
                if (age < 0) return 'A idade deve ser maior ou igual a zero.';
                return null;
              },
            ),
            const SizedBox(height: 12),
            const FieldLabel('Função'),
            TextFormField(
              controller: _role,
              maxLength: 100,
              style: BD.body,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                hintText: 'Digite a função',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe a função.'
                  : null,
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
