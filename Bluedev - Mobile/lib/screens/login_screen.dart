import 'package:flutter/material.dart';

import '../api_client.dart';
import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

/// M01 · Login
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  String _apiUrl = const String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );
  final _usuario = TextEditingController();
  final _senha = TextEditingController();
  bool _loading = false;

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    final api = ApiClient(_apiUrl);
    try {
      final user = await api.login(_usuario.text.trim(), _senha.text);
      if (!mounted) {
        api.close();
        return;
      }
      final session = Session(api: api, user: user);
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(builder: (_) => homeFor(session)),
      );
    } catch (error) {
      api.close();
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// "Precisa de ajuda?": orientações e endereço do servidor da API.
  Future<void> _help() async {
    final url = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _HelpSheet(currentUrl: _apiUrl),
    );
    if (url != null) setState(() => _apiUrl = url);
  }

  @override
  void dispose() {
    _usuario.dispose();
    _senha.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BD.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: BD.margin, vertical: 20),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Image.asset('assets/images/logo_bluedev.png',
                        height: 48),
                  ),
                  const SizedBox(height: 14),
                  Center(
                    child: Image.asset('assets/images/login_ilustracao.png',
                        height: 190),
                  ),
                  const SizedBox(height: 14),
                  Text('Login',
                      textAlign: TextAlign.center,
                      style: BD.screenTitle.copyWith(fontSize: 22)),
                  const SizedBox(height: 2),
                  Text('Gestão de ocorrências',
                      textAlign: TextAlign.center, style: BD.caption),
                  const SizedBox(height: 18),
                  const FieldLabel('Usuário'),
                  TextFormField(
                    controller: _usuario,
                    textCapitalization: TextCapitalization.characters,
                    textInputAction: TextInputAction.next,
                    style: BD.body,
                    decoration: const InputDecoration(
                      hintText: 'Seu usuário',
                      prefixIcon: Icon(Icons.person_outline_rounded, size: 19),
                    ),
                    validator: (value) => value == null || value.trim().isEmpty
                        ? 'Informe o usuário.'
                        : null,
                  ),
                  const SizedBox(height: 12),
                  const FieldLabel('Senha'),
                  TextFormField(
                    controller: _senha,
                    obscureText: true,
                    style: BD.body,
                    decoration: const InputDecoration(
                      hintText: '••••••••',
                      prefixIcon: Icon(Icons.lock_outline_rounded, size: 19),
                    ),
                    validator: (value) =>
                        value == null || value.isEmpty ? 'Informe a senha.' : null,
                    onFieldSubmitted: (_) => _login(),
                  ),
                  const SizedBox(height: 10),
                  Center(
                    child: TextButton(
                      onPressed: _help,
                      child: const Text(
                        'Precisa de ajuda?',
                        style: TextStyle(decoration: TextDecoration.underline),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  FilledButton(
                    onPressed: _loading ? null : _login,
                    child: _loading
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : const Text('Entrar'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _HelpSheet extends StatefulWidget {
  const _HelpSheet({required this.currentUrl});

  final String currentUrl;

  @override
  State<_HelpSheet> createState() => _HelpSheetState();
}

class _HelpSheetState extends State<_HelpSheet> {
  late final _url = TextEditingController(text: widget.currentUrl);
  String? _error;

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  void _save() {
    final uri = Uri.tryParse(_url.text.trim());
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      setState(() => _error = 'Informe a URL completa da API.');
      return;
    }
    Navigator.pop(context, _url.text.trim());
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
          BD.margin, 0, BD.margin, MediaQuery.viewInsetsOf(context).bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Precisa de ajuda?', style: BD.screenTitle),
          const SizedBox(height: 6),
          Text(
            'Use o mesmo usuário e senha do sistema web do BlueDev. '
            'Se não conseguir entrar, fale com o administrador da sua empresa.',
            style: BD.body.copyWith(color: BD.muted),
          ),
          const SizedBox(height: 18),
          const FieldLabel('Endereço do servidor (API)'),
          TextField(
            controller: _url,
            keyboardType: TextInputType.url,
            style: BD.body,
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.dns_outlined, size: 19),
              errorText: _error,
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: _save, child: const Text('Salvar')),
        ],
      ),
    );
  }
}
