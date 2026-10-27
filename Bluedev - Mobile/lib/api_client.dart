import 'dart:convert';
import 'dart:io';

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this.baseUrl);

  final String baseUrl;
  final HttpClient _http = HttpClient();
  String? _sessionCookie;

  Uri _uri(String path, [Map<String, String>? query]) {
    final normalized = baseUrl.trim().replaceFirst(RegExp(r'/+$'), '');
    if (normalized.isEmpty) {
      throw const ApiException('Informe o endereço da API do BlueDev.');
    }
    final uri = Uri.tryParse('$normalized/$path');
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw const ApiException('O endereço da API não é válido.');
    }
    return uri.replace(queryParameters: query);
  }

  Future<dynamic> _request(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) async {
    final request = await _http.openUrl(method, _uri(path, query));
    request.headers.set(HttpHeaders.acceptHeader, 'application/json');
    if (body != null) {
      request.headers.contentType = ContentType.json;
      request.write(jsonEncode(body));
    }
    if (authenticated && _sessionCookie != null) {
      request.headers.set(HttpHeaders.cookieHeader, _sessionCookie!);
    }

    final response = await request.close();
    final responseText = await response.transform(utf8.decoder).join();
    final cookies = response.headers[HttpHeaders.setCookieHeader];
    if (cookies != null && cookies.isNotEmpty) {
      _sessionCookie = cookies.first.split(';').first;
    }

    dynamic data;
    if (responseText.isNotEmpty) {
      try {
        data = jsonDecode(responseText);
      } on FormatException {
        throw ApiException(
          'O servidor retornou uma resposta inválida (HTTP ${response.statusCode}).',
        );
      }
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message = data is Map<String, dynamic>
          ? data['erro'] as String? ?? 'Erro HTTP ${response.statusCode}.'
          : 'Erro HTTP ${response.statusCode}.';
      throw ApiException(message);
    }
    return data;
  }

  Future<Map<String, dynamic>> login(String usuario, String senha) async {
    final result = await _request(
      'POST',
      'login.php',
      body: {'usuario': usuario, 'senha': senha},
      authenticated: false,
    );
    return result as Map<String, dynamic>;
  }

  Future<void> logout() async {
    try {
      await _request('POST', 'logout.php');
    } finally {
      _sessionCookie = null;
    }
  }

  Future<List<Map<String, dynamic>>> getOcorrencias({String? status}) async {
    final result = await _request(
      'GET',
      'ocorrencias.php',
      query: status == null || status.isEmpty ? null : {'status': status},
    );
    return (result as List<dynamic>).cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> getMaquinas() async {
    final result = await _request('GET', 'maquinas.php');
    return (result as List<dynamic>).cast<Map<String, dynamic>>();
  }

  Future<void> saveOcorrencia(
    Map<String, dynamic> values, {
    int? id,
    List<int>? imageBytes,
  }) async {
    await _request(
      id == null ? 'POST' : 'PUT',
      'ocorrencias.php',
      query: id == null ? null : {'id': '$id'},
      body: {
        ...values,
        if (imageBytes != null) 'imagem_base64': base64Encode(imageBytes),
      },
    );
  }

  /// Marca a ocorrência como concluída (ação "Validar" do administrador).
  Future<void> validarOcorrencia(Map<String, dynamic> item) async {
    await saveOcorrencia(
      {
        'titulo': item['titulo'],
        'tipo': item['tipo'],
        'status': 'Concluída',
        'descricao': item['descricao'] ?? '',
        'maquina_id': item['maquina_id'],
      },
      id: int.parse('${item['id']}'),
    );
  }

  /// Endereço público de uma imagem salva em uploads/ (a API fica em .../api).
  String? imageUrl(String? path) {
    if (path == null || path.isEmpty) return null;
    final root = baseUrl.trim().replaceFirst(RegExp(r'/+$'), '');
    final site = root.replaceFirst(RegExp(r'/api$'), '');
    final relative = path.startsWith('uploads/') ? path : 'uploads/$path';
    return '$site/$relative';
  }

  Future<void> deleteOcorrencia(int id) async {
    await _request('DELETE', 'ocorrencias.php', query: {'id': '$id'});
  }

  void close() => _http.close(force: true);
}
