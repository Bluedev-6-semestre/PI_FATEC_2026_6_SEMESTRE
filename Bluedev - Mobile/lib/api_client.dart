import 'dart:convert';
import 'dart:io';

class ApiException implements Exception {
  const ApiException(this.message, {this.status = 0});

  final String message;
  final int status;

  @override
  String toString() => message;
}

/// Cliente do API Gateway dos microsserviços (user, machine e
/// occurrence-service).
///
/// As telas usam o mesmo formato de dados de antes (`usuario_id`,
/// `nome_maquina`, `data_criacao`…); este cliente traduz as respostas dos
/// microsserviços para esse formato e completa o que a API não devolve:
/// nome da máquina, nome de quem criou e o filtro por usuário.
class ApiClient {
  ApiClient(this.baseUrl);

  final String baseUrl;
  final HttpClient _http = HttpClient()
    ..connectionTimeout = const Duration(seconds: 8);

  int? _userId;
  bool _isAdmin = false;

  Uri _uri(String path) {
    // Aceita o endereço do gateway com ou sem "/api" no final.
    final normalized = baseUrl
        .trim()
        .replaceFirst(RegExp(r'/+$'), '')
        .replaceFirst(RegExp(r'/api$'), '');
    if (normalized.isEmpty) {
      throw const ApiException('Informe o endereço do servidor do BlueDev.');
    }
    final uri = Uri.tryParse('$normalized/api/$path');
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw const ApiException('O endereço do servidor não é válido.');
    }
    return uri;
  }

  Future<dynamic> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final request = await _http.openUrl(method, _uri(path));
    request.headers.set(HttpHeaders.acceptHeader, 'application/json');
    if (body != null) {
      final bytes = utf8.encode(jsonEncode(body));
      request.headers.contentType = ContentType.json;
      request.contentLength = bytes.length;
      request.add(bytes);
    }

    final response = await request.close();
    final responseText = await response.transform(utf8.decoder).join();
    final status = response.statusCode;

    dynamic data;
    if (responseText.isNotEmpty) {
      try {
        data = jsonDecode(responseText);
      } on FormatException {
        if (status >= 200 && status < 300) {
          throw ApiException(
            'O servidor retornou uma resposta inválida (HTTP $status).',
            status: status,
          );
        }
      }
    }
    if (status < 200 || status >= 300) {
      throw ApiException(_friendlyMessage(status, data), status: status);
    }
    return data;
  }

  /// Mensagens no mesmo padrão do site (Front-end/includes/api_client.php).
  static String _friendlyMessage(int status, dynamic data) {
    final detail = data is Map<String, dynamic>
        ? (data['detail'] ?? data['message'] ?? data['error']) as String?
        : null;
    switch (status) {
      case 400:
      case 422:
        return 'Os dados informados são inválidos. Verifique os campos.';
      case 401:
        return 'Usuário ou senha inválidos.';
      case 403:
        return 'Você não tem permissão para realizar esta operação.';
      case 413:
        return 'A imagem deve ter no máximo 5 MB.';
      case 415:
        return 'Envie uma imagem JPEG, PNG ou WebP.';
      case 404:
        return detail ?? 'O registro solicitado não foi encontrado.';
      case 503:
        return detail ?? 'Um dos serviços está indisponível. Tente novamente.';
      default:
        return status >= 500
            ? 'O servidor está temporariamente indisponível. Tente novamente.'
            : 'Não foi possível concluir a operação (HTTP $status).';
    }
  }

  static int? _int(dynamic value) => int.tryParse('${value ?? ''}');

  /// O user-service devolve ADMIN/FUNCIONARIO; o app usa ADM/FUNC.
  static String _role(dynamic tipo) =>
      '$tipo'.toUpperCase() == 'ADMIN' ? 'ADM' : 'FUNC';

  Future<Map<String, dynamic>> login(String usuario, String senha) async {
    final result = await _request(
      'POST',
      'usuarios/login',
      body: {'usuario': usuario, 'senha': senha},
    ) as Map<String, dynamic>;
    _userId = _int(result['id']);
    _isAdmin = _role(result['tipoUsuario']) == 'ADM';
    return {
      'usuario': result['usuario'] ?? usuario,
      'usuario_id': _userId,
      'tipo_usuario': _role(result['tipoUsuario']),
    };
  }

  /// O gateway não mantém sessão; sair só esquece o usuário no aparelho.
  Future<void> logout() async {
    _userId = null;
    _isAdmin = false;
  }

  Future<Map<int, String>> _machineNames() async {
    final machines = await _request('GET', 'maquinas') as List<dynamic>;
    return {
      for (final m in machines.cast<Map<String, dynamic>>())
        if (_int(m['id']) != null) _int(m['id'])!: '${m['nome'] ?? ''}',
    };
  }

  /// Nomes dos autores; se a lista de usuários falhar, a tela segue sem eles.
  Future<Map<int, String>> _userNames() async {
    try {
      final users = await _request('GET', 'usuarios') as List<dynamic>;
      return {
        for (final u in users.cast<Map<String, dynamic>>())
          if (_int(u['id']) != null) _int(u['id'])!: '${u['usuario'] ?? ''}',
      };
    } on ApiException {
      return {};
    }
  }

  Map<String, dynamic> _occurrence(
    Map<String, dynamic> o,
    Map<int, String> machines,
    Map<int, String> users,
  ) {
    final maquinaId = _int(o['maquinaId']);
    final usuarioId = _int(o['usuarioId']);
    return {
      'id': _int(o['id']),
      'usuario_id': usuarioId,
      'usuario_nome': usuarioId == null ? null : users[usuarioId],
      'maquina_id': maquinaId,
      'nome_maquina': maquinaId == null ? null : machines[maquinaId],
      'titulo': o['titulo'],
      'tipo': o['tipo'],
      'status': o['status'],
      'descricao': o['descricao'],
      'imagem': o['imagem'],
      'data_criacao': o['dataCriacao'],
    };
  }

  /// Lista as ocorrências. O FUNC vê só as que ele registrou, como antes;
  /// a API não filtra por usuário nem por status, então o filtro é feito aqui.
  Future<List<Map<String, dynamic>>> getOcorrencias({String? status}) async {
    final results = await Future.wait([
      _request('GET', 'ocorrencias'),
      _machineNames(),
      _userNames(),
    ]);
    final machines = results[1] as Map<int, String>;
    final users = results[2] as Map<int, String>;
    final items = (results[0] as List<dynamic>)
        .cast<Map<String, dynamic>>()
        .map((o) => _occurrence(o, machines, users))
        .where((o) => _isAdmin || o['usuario_id'] == _userId)
        .where((o) {
      if (status == null || status.isEmpty) return true;
      if (status == 'Concluída' || status == 'Concluído') {
        return o['status'] == 'Concluída' || o['status'] == 'Concluído';
      }
      return o['status'] == status;
    }).toList()
      ..sort((a, b) => (b['id'] as int? ?? 0).compareTo(a['id'] as int? ?? 0));
    return items;
  }

  /// Máquinas para os selects. O FUNC vê só as dele, como no site: a lista
  /// resumida não traz o dono, então o detalhe de cada máquina é consultado.
  Future<List<Map<String, dynamic>>> getMaquinas() async {
    final machines = (await _request('GET', 'maquinas') as List<dynamic>)
        .cast<Map<String, dynamic>>();
    var visible = machines;
    if (!_isAdmin) {
      final details = await Future.wait(machines.map((m) async {
        try {
          return await _request('GET', 'maquinas/${m['id']}')
              as Map<String, dynamic>;
        } on ApiException {
          return <String, dynamic>{};
        }
      }));
      final mine = {
        for (final d in details)
          if (_int(d['usuarioId']) == _userId) _int(d['id']),
      };
      visible = machines.where((m) => mine.contains(_int(m['id']))).toList();
    }
    return [
      for (final m in visible) {'id': _int(m['id']), 'nome': m['nome']},
    ]..sort((a, b) => '${a['nome']}'.compareTo('${b['nome']}'));
  }

  /// Cria ou edita e devolve o id. Na edição mantém quem criou e a imagem
  /// já salva (a foto vai à parte, por [enviarImagem]).
  Future<int> saveOcorrencia(Map<String, dynamic> values, {int? id}) async {
    Map<String, dynamic>? current;
    if (id != null) {
      current =
          await _request('GET', 'ocorrencias/$id') as Map<String, dynamic>;
    }
    final descricao = '${values['descricao'] ?? ''}'.trim();
    final body = {
      'usuarioId': current?['usuarioId'] ?? _userId,
      'maquinaId': values['maquina_id'],
      'titulo': values['titulo'],
      'tipo': values['tipo'],
      'status': values['status'],
      'descricao': descricao.isEmpty ? null : descricao,
      'imagem': current?['imagem'],
    };
    final saved = await _request(
      id == null ? 'POST' : 'PUT',
      id == null ? 'ocorrencias' : 'ocorrencias/$id',
      body: body,
    );
    return _int(saved is Map<String, dynamic> ? saved['id'] : null) ?? id ?? 0;
  }

  /// Envia ou troca a foto da ocorrência (multipart, campo "arquivo").
  /// O serviço confere o tipo real (JPEG, PNG ou WebP) e o limite de 5 MB.
  Future<void> enviarImagem(int id, List<int> bytes) async {
    final boundary = 'bluedev-${DateTime.now().microsecondsSinceEpoch}';
    final body = <int>[
      ...utf8.encode('--$boundary\r\n'
          'Content-Disposition: form-data; name="arquivo"; filename="foto.jpg"\r\n'
          'Content-Type: application/octet-stream\r\n\r\n'),
      ...bytes,
      ...utf8.encode('\r\n--$boundary--\r\n'),
    ];
    final request = await _http.openUrl('POST', _uri('ocorrencias/$id/imagem'));
    request.headers.set(HttpHeaders.acceptHeader, 'application/json');
    request.headers.set(
        HttpHeaders.contentTypeHeader, 'multipart/form-data; boundary=$boundary');
    request.contentLength = body.length;
    request.add(body);
    final response = await request.close();
    final text = await response.transform(utf8.decoder).join();
    if (response.statusCode < 200 || response.statusCode >= 300) {
      dynamic data;
      try {
        data = text.isEmpty ? null : jsonDecode(text);
      } on FormatException {
        data = null;
      }
      throw ApiException(_friendlyMessage(response.statusCode, data),
          status: response.statusCode);
    }
  }

  Future<void> removerImagem(int id) async {
    await _request('DELETE', 'ocorrencias/$id/imagem');
  }

  /// Marca a ocorrência como concluída (ação "Validar" do administrador).
  Future<void> validarOcorrencia(Map<String, dynamic> item) async {
    await saveOcorrencia(
      {
        'titulo': item['titulo'],
        'tipo': item['tipo'],
        'status': 'Concluída',
        'descricao': item['descricao'],
        'maquina_id': item['maquina_id'],
      },
      id: _int(item['id']),
    );
  }

  Future<void> deleteOcorrencia(int id) async {
    await _request('DELETE', 'ocorrencias/$id');
  }

  /// Endereço da foto. As enviadas pela API ficam em
  /// "/api/ocorrencias/{id}/imagem?v=…" (servidas pelo gateway). Caminhos
  /// relativos antigos do site (ex.: "uploads/x.jpg") não são acessíveis
  /// daqui e são ignorados.
  String? imageUrl(String? path) {
    if (path == null || path.isEmpty) return null;
    if (path.startsWith('/api/')) {
      final root = baseUrl
          .trim()
          .replaceFirst(RegExp(r'/+$'), '')
          .replaceFirst(RegExp(r'/api$'), '');
      return '$root$path';
    }
    final uri = Uri.tryParse(path);
    return uri != null && uri.hasScheme ? path : null;
  }

  void close() => _http.close(force: true);
}
