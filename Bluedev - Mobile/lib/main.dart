import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'api_client.dart';
import 'screens/login_screen.dart';
import 'screens/main_shell.dart';
import 'theme.dart';
import 'widgets.dart';

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
      theme: BD.theme(),
      locale: const Locale('pt', 'BR'),
      supportedLocales: const [Locale('pt', 'BR')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      home: const LoginScreen(),
    );
  }
}

enum PlanStatus { pago, teste }

/// Usuário logado + cliente da API, repassado entre as telas.
class Session {
  Session({required this.api, required this.user});

  final ApiClient api;
  final Map<String, dynamic> user;

  /// Aumenta sempre que uma ocorrência é criada, editada ou removida,
  /// para as abas recarregarem.
  final revision = ValueNotifier<int>(0);

  void changed() => revision.value++;

  bool get isAdmin => user['tipo_usuario'] == 'ADM';
  String get name => '${user['usuario'] ?? ''}';
  String get greetingName => isAdmin ? 'Administrador' : _capitalize(name);

  static String _capitalize(String value) => value.isEmpty
      ? value
      : value[0].toUpperCase() + value.substring(1).toLowerCase();

  /// A API ainda não informa plano. Usa `plano` da resposta do login se vier,
  /// ou `--dart-define=PLANO=teste` para ver a tela M03; o padrão é pago (M02).
  PlanStatus get plan {
    const override = String.fromEnvironment('PLANO');
    final value = override.isNotEmpty ? override : '${user['plano'] ?? 'pago'}';
    return value == 'teste' ? PlanStatus.teste : PlanStatus.pago;
  }

  int get trialDaysLeft {
    const override = int.fromEnvironment('DIAS_TESTE', defaultValue: -1);
    if (override >= 0) return override;
    return int.tryParse('${user['dias_teste'] ?? ''}') ?? 10;
  }
}

Widget homeFor(Session session) => MainShell(session: session);

Future<void> logout(BuildContext context, Session session) async {
  try {
    await session.api.logout();
  } catch (error) {
    if (context.mounted) showError(context, friendlyError(error));
    return;
  }
  session.api.close();
  if (!context.mounted) return;
  Navigator.of(context).pushAndRemoveUntil(
    MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
    (_) => false,
  );
}

String friendlyError(Object error) {
  if (error is ApiException) return error.message;
  if (error is SocketException) {
    return 'Não foi possível conectar ao servidor. Verifique a rede.';
  }
  return 'Algo deu errado: $error';
}
