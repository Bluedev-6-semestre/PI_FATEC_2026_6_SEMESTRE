import 'dart:io';

import 'package:flutter/material.dart';

import 'api_client.dart';

const _blue = Color(0xFF174B83);
const _statuses = ['Pendente', 'Em andamento', 'Concluída'];

void main() {
  runApp(const BlueDevApp());
}

class BlueDevApp extends StatelessWidget {
  const BlueDevApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BlueDev',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: _blue),
        scaffoldBackgroundColor: const Color(0xFFF4F7FB),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(
          border: OutlineInputBorder(),
        ),
      ),
      home: const LoginPage(),
    );
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _url = TextEditingController(
    text: const String.fromEnvironment('API_BASE_URL'),
  );
  final _usuario = TextEditingController();
  final _senha = TextEditingController();
  bool _loading = false;

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    final api = ApiClient(_url.text);
    try {
      final user = await api.login(_usuario.text.trim(), _senha.text);
      if (!mounted) {
        api.close();
        return;
      }
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => OccurrencesPage(
            api: api,
            user: user,
          ),
        ),
      );
    } on ApiException catch (error) {
      api.close();
      _showError(error.message);
    } on SocketException {
      api.close();
      _showError(
          'Não foi possível conectar ao servidor. Verifique a URL e a rede.');
    } catch (error) {
      api.close();
      _showError('Falha ao entrar: $error');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red.shade700),
    );
  }

  @override
  void dispose() {
    _url.dispose();
    _usuario.dispose();
    _senha.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Icon(Icons.build_circle, size: 72, color: _blue),
                    const SizedBox(height: 12),
                    Text(
                      'BlueDev',
                      textAlign: TextAlign.center,
                      style:
                          Theme.of(context).textTheme.headlineLarge?.copyWith(
                                color: _blue,
                                fontWeight: FontWeight.bold,
                              ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Gestão de ocorrências e manutenção',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _url,
                      keyboardType: TextInputType.url,
                      decoration: const InputDecoration(
                        labelText: 'Endereço da API',
                        hintText: 'https://servidor/Front-end/api',
                        prefixIcon: Icon(Icons.link),
                      ),
                      validator: (value) {
                        final uri = Uri.tryParse((value ?? '').trim());
                        if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
                          return 'Informe a URL completa da API.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _usuario,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(
                        labelText: 'Usuário',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                              ? 'Informe o usuário.'
                              : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _senha,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Senha',
                        prefixIcon: Icon(Icons.lock_outline),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Informe a senha.'
                          : null,
                      onFieldSubmitted: (_) => _login(),
                    ),
                    const SizedBox(height: 24),
                    FilledButton.icon(
                      onPressed: _loading ? null : _login,
                      icon: _loading
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.login),
                      label: Text(_loading ? 'Conectando...' : 'Entrar'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class OccurrencesPage extends StatefulWidget {
  const OccurrencesPage({
    required this.api,
    required this.user,
    super.key,
  });

  final ApiClient api;
  final Map<String, dynamic> user;

  @override
  State<OccurrencesPage> createState() => _OccurrencesPageState();
}

class _OccurrencesPageState extends State<OccurrencesPage> {
  List<Map<String, dynamic>> _items = [];
  String? _filter;
  String? _error;
  bool _loading = true;

  bool get _isAdmin => widget.user['tipo_usuario'] == 'ADM';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await widget.api.getOcorrencias(status: _filter);
      if (mounted) setState(() => _items = items);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on SocketException {
      if (mounted) setState(() => _error = 'Falha de conexão com o servidor.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openForm([Map<String, dynamic>? occurrence]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => OccurrenceFormPage(
          api: widget.api,
          occurrence: occurrence,
        ),
      ),
    );
    if (saved == true) _load();
  }

  Future<void> _logout() async {
    try {
      await widget.api.logout();
    } on ApiException catch (error) {
      _notify(
          'Não foi possível encerrar a sessão no servidor: ${error.message}');
      return;
    } on SocketException {
      _notify('Sem conexão: não foi possível encerrar a sessão no servidor.');
      return;
    }
    widget.api.close();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginPage()),
      (_) => false,
    );
  }

  Future<void> _showDetails(Map<String, dynamic> occurrence) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 8, 24, 28),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                occurrence['titulo'] as String? ?? 'Ocorrência',
                style: Theme.of(sheetContext).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _DetailRow(label: 'Tipo', value: occurrence['tipo']),
              _DetailRow(label: 'Status', value: occurrence['status']),
              _DetailRow(
                  label: 'Máquina', value: occurrence['nome_maquina'] ?? '—'),
              _DetailRow(
                label: 'Descrição',
                value: (occurrence['descricao'] as String?)?.isNotEmpty == true
                    ? occurrence['descricao']
                    : 'Sem descrição.',
              ),
              const SizedBox(height: 16),
              Wrap(
                alignment: WrapAlignment.end,
                spacing: 8,
                children: [
                  if (_isAdmin)
                    TextButton.icon(
                      onPressed: () async {
                        final confirm = await showDialog<bool>(
                          context: sheetContext,
                          builder: (dialogContext) => AlertDialog(
                            title: const Text('Excluir ocorrência?'),
                            content:
                                const Text('Esta ação não pode ser desfeita.'),
                            actions: [
                              TextButton(
                                onPressed: () =>
                                    Navigator.pop(dialogContext, false),
                                child: const Text('Cancelar'),
                              ),
                              FilledButton(
                                onPressed: () =>
                                    Navigator.pop(dialogContext, true),
                                child: const Text('Excluir'),
                              ),
                            ],
                          ),
                        );
                        if (confirm != true || !sheetContext.mounted) return;
                        try {
                          await widget.api.deleteOcorrencia(
                            int.parse('${occurrence['id']}'),
                          );
                          if (!mounted || !sheetContext.mounted) return;
                          Navigator.pop(sheetContext);
                          _load();
                        } on ApiException catch (error) {
                          _notify(error.message);
                        } on SocketException {
                          _notify('Falha de conexão ao excluir a ocorrência.');
                        }
                      },
                      icon: const Icon(Icons.delete_outline),
                      label: const Text('Excluir'),
                    ),
                  TextButton.icon(
                    onPressed: () {
                      Navigator.pop(sheetContext);
                      _openForm(occurrence);
                    },
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Editar'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _notify(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Concluído':
      case 'Concluída':
        return Colors.green.shade700;
      case 'Em andamento':
        return Colors.orange.shade800;
      default:
        return Colors.blueGrey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ocorrências'),
        actions: [
          IconButton(
            tooltip: 'Sair',
            onPressed: _logout,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: DropdownButtonFormField<String?>(
              initialValue: _filter,
              decoration: const InputDecoration(
                labelText: 'Filtrar por status',
                prefixIcon: Icon(Icons.filter_list),
              ),
              items: [
                const DropdownMenuItem<String?>(
                    value: null, child: Text('Todos')),
                ..._statuses.map(
                  (status) => DropdownMenuItem<String?>(
                    value: status,
                    child: Text(status),
                  ),
                ),
              ],
              onChanged: (value) {
                setState(() => _filter = value);
                _load();
              },
            ),
          ),
          Expanded(child: _buildList()),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('Nova ocorrência'),
      ),
    );
  }

  Widget _buildList() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh),
                label: const Text('Tentar novamente'),
              ),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return const Center(child: Text('Nenhuma ocorrência encontrada.'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
        itemCount: _items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final item = _items[index];
          final status = item['status'] as String? ?? 'Pendente';
          return Card(
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              title: Text(
                item['titulo'] as String? ?? 'Sem título',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              subtitle: Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                        '${item['tipo'] ?? 'Tipo não informado'} · ${item['nome_maquina'] ?? 'Sem máquina'}'),
                    const SizedBox(height: 6),
                    Text(
                      status,
                      style: TextStyle(
                        color: _statusColor(status),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _showDetails(item),
            ),
          );
        },
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final dynamic value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Text('$label: ${value ?? '—'}'),
    );
  }
}

class OccurrenceFormPage extends StatefulWidget {
  const OccurrenceFormPage({
    required this.api,
    this.occurrence,
    super.key,
  });

  final ApiClient api;
  final Map<String, dynamic>? occurrence;

  @override
  State<OccurrenceFormPage> createState() => _OccurrenceFormPageState();
}

class _OccurrenceFormPageState extends State<OccurrenceFormPage> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  String? _type;
  String? _status;
  int? _machineId;
  List<Map<String, dynamic>> _machines = [];
  bool _loading = true;
  bool _saving = false;
  String? _loadError;

  bool get _editing => widget.occurrence != null;

  @override
  void initState() {
    super.initState();
    final item = widget.occurrence;
    _title = TextEditingController(text: item?['titulo'] as String? ?? '');
    _description =
        TextEditingController(text: item?['descricao'] as String? ?? '');
    _type = item?['tipo'] as String?;
    _status = item?['status'] as String?;
    _machineId = int.tryParse('${item?['maquina_id'] ?? ''}');
    _loadMachines();
  }

  Future<void> _loadMachines() async {
    try {
      final machines = await widget.api.getMaquinas();
      if (mounted) {
        setState(() {
          _machines = machines;
          if (_machineId != null &&
              !_machines
                  .any((machine) => '${machine['id']}' == '$_machineId')) {
            _machineId = null;
          }
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _loadError = error.message);
    } on SocketException {
      if (mounted) setState(() => _loadError = 'Falha ao carregar máquinas.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.api.saveOcorrencia(
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
    } on ApiException catch (error) {
      _showError(error.message);
    } on SocketException {
      _showError('Falha de conexão ao salvar a ocorrência.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red.shade700),
    );
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
          title: Text(_editing ? 'Editar ocorrência' : 'Nova ocorrência')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null
              ? Center(child: Text(_loadError!))
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      TextFormField(
                        controller: _title,
                        maxLength: 100,
                        decoration: const InputDecoration(labelText: 'Título'),
                        validator: (value) =>
                            value == null || value.trim().isEmpty
                                ? 'Informe o título.'
                                : null,
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue: _type,
                        decoration: const InputDecoration(
                            labelText: 'Tipo de manutenção'),
                        items: const [
                          DropdownMenuItem(
                              value: 'Preventiva', child: Text('Preventiva')),
                          DropdownMenuItem(
                              value: 'Corretiva', child: Text('Corretiva')),
                        ],
                        onChanged: (value) => setState(() => _type = value),
                        validator: (value) =>
                            value == null ? 'Selecione o tipo.' : null,
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue:
                            _status == 'Concluído' ? 'Concluída' : _status,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: _statuses
                            .map((status) => DropdownMenuItem(
                                value: status, child: Text(status)))
                            .toList(),
                        onChanged: (value) => setState(() => _status = value),
                        validator: (value) =>
                            value == null ? 'Selecione o status.' : null,
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<int?>(
                        initialValue: _machineId,
                        decoration: const InputDecoration(
                            labelText: 'Máquina (opcional)'),
                        items: [
                          const DropdownMenuItem<int?>(
                              value: null, child: Text('Nenhuma')),
                          ..._machines.map(
                            (machine) => DropdownMenuItem<int?>(
                              value: int.parse('${machine['id']}'),
                              child:
                                  Text(machine['nome'] as String? ?? 'Máquina'),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _machineId = value),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _description,
                        minLines: 4,
                        maxLines: 7,
                        decoration: const InputDecoration(
                          labelText: 'Descrição',
                          alignLabelWithHint: true,
                        ),
                      ),
                      const SizedBox(height: 24),
                      FilledButton.icon(
                        onPressed: _saving ? null : _save,
                        icon: _saving
                            ? const SizedBox.square(
                                dimension: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.save_outlined),
                        label:
                            Text(_saving ? 'Salvando...' : 'Salvar ocorrência'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
