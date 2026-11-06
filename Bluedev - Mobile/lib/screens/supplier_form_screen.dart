import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

String _formatCnpj(String value) {
  final digits = value.replaceAll(RegExp(r'\D'), '');
  final limited = digits.length > 14 ? digits.substring(0, 14) : digits;
  final result = StringBuffer();
  for (var index = 0; index < limited.length; index++) {
    if (index == 2 || index == 5) result.write('.');
    if (index == 8) result.write('/');
    if (index == 12) result.write('-');
    result.write(limited[index]);
  }
  return result.toString();
}

bool _isValidCnpj(String value) {
  final cnpj = value.replaceAll(RegExp(r'\D'), '');
  if (cnpj.length != 14 || RegExp(r'^(\d)\1{13}$').hasMatch(cnpj)) {
    return false;
  }
  var sum = 0;
  const firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  for (var index = 0; index < 12; index++) {
    sum += int.parse(cnpj[index]) * firstWeights[index];
  }
  final firstDigit = sum % 11 < 2 ? 0 : 11 - sum % 11;
  if (int.parse(cnpj[12]) != firstDigit) return false;

  sum = 0;
  const secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  for (var index = 0; index < 13; index++) {
    sum += int.parse(cnpj[index]) * secondWeights[index];
  }
  final secondDigit = sum % 11 < 2 ? 0 : 11 - sum % 11;
  return int.parse(cnpj[13]) == secondDigit;
}

class _CnpjInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final cursorPosition =
        newValue.selection.baseOffset.clamp(0, newValue.text.length).toInt();
    final digitsBeforeCursor = newValue.text
        .substring(0, cursorPosition)
        .replaceAll(RegExp(r'\D'), '')
        .length;
    final formatted = _formatCnpj(newValue.text);

    var cursor = 0;
    var digitsCounted = 0;
    while (cursor < formatted.length && digitsCounted < digitsBeforeCursor) {
      if (RegExp(r'\d').hasMatch(formatted[cursor])) digitsCounted++;
      cursor++;
    }
    while (cursor < formatted.length &&
        !RegExp(r'\d').hasMatch(formatted[cursor])) {
      cursor++;
    }

    return newValue.copyWith(
      text: formatted,
      selection: TextSelection.collapsed(offset: cursor),
      composing: TextRange.empty,
    );
  }
}

class SupplierFormScreen extends StatefulWidget {
  const SupplierFormScreen({
    required this.session,
    this.supplier,
    super.key,
  });

  final Session session;
  final Map<String, dynamic>? supplier;

  @override
  State<SupplierFormScreen> createState() => _SupplierFormScreenState();
}

class _SupplierFormScreenState extends State<SupplierFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _cnpj;
  late final TextEditingController _name;
  late final TextEditingController _category;
  late final TextEditingController _phone;
  late final TextEditingController _address;
  bool _saving = false;
  bool _lookingUpCnpj = false;

  bool get _editing => widget.supplier != null;

  @override
  void initState() {
    super.initState();
    final supplier = widget.supplier;
    _cnpj = TextEditingController(
      text: _formatCnpj(supplier?['cnpj'] as String? ?? ''),
    );
    _name = TextEditingController(text: supplier?['nome'] as String? ?? '');
    _category =
        TextEditingController(text: supplier?['categoria'] as String? ?? '');
    _phone =
        TextEditingController(text: supplier?['telefone'] as String? ?? '');
    _address =
        TextEditingController(text: supplier?['endereco'] as String? ?? '');
  }

  Future<void> _lookupCnpj() async {
    if (_lookingUpCnpj) return;
    final digits = _cnpj.text.replaceAll(RegExp(r'\D'), '');
    if (!_isValidCnpj(digits)) {
      showError(context, 'Digite um CNPJ válido com 14 números.');
      return;
    }
    setState(() => _lookingUpCnpj = true);
    try {
      final data = await widget.session.api.consultarCnpj(digits);
      if (!mounted) return;
      _name.text = data['nome'] as String? ?? '';
      _phone.text = data['telefone'] as String? ?? '';
      _address.text = data['endereco'] as String? ?? '';
      showInfo(context, 'Dados do CNPJ preenchidos.');
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _lookingUpCnpj = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.session.api.saveFornecedor({
        'nome': _name.text.trim(),
        'cnpj': _cnpj.text.trim(),
        'categoria': _category.text.trim(),
        'telefone': _phone.text.trim(),
        'endereco': _address.text.trim(),
      }, id: _editing ? int.parse('${widget.supplier!['id']}') : null);
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _cnpj.dispose();
    _name.dispose();
    _category.dispose();
    _phone.dispose();
    _address.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Editar fornecedor' : 'Novo fornecedor'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            const FieldLabel('CNPJ'),
            TextFormField(
              controller: _cnpj,
              keyboardType: TextInputType.number,
              inputFormatters: [_CnpjInputFormatter()],
              style: BD.body,
              decoration: const InputDecoration(
                hintText: '00.000.000/0001-00',
              ),
              validator: (value) => _isValidCnpj(value ?? '')
                  ? null
                  : 'Digite um CNPJ válido com 14 números.',
              onFieldSubmitted: (_) => _lookupCnpj(),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _lookingUpCnpj ? null : _lookupCnpj,
              icon: _lookingUpCnpj
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.search_rounded),
              label: const Text('Buscar Dados'),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Nome da empresa'),
            TextFormField(
              controller: _name,
              maxLength: 100,
              style: BD.body,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                hintText: 'Digite o nome da empresa',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o nome da empresa.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Categoria'),
            TextFormField(
              controller: _category,
              maxLength: 50,
              style: BD.body,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                hintText: 'Digite a categoria',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe a categoria.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Telefone'),
            TextFormField(
              controller: _phone,
              maxLength: 20,
              keyboardType: TextInputType.phone,
              style: BD.body,
              decoration: const InputDecoration(
                hintText: '(00) 00000-0000',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o telefone.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Endereço (CEP)'),
            TextFormField(
              controller: _address,
              maxLength: 255,
              style: BD.body,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                hintText: 'Digite o endereço ou CEP',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o endereço.'
                  : null,
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: _saving || _lookingUpCnpj ? null : _save,
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
