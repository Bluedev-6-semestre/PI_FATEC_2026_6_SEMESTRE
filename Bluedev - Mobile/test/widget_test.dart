import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:bluedev_mobile/api_client.dart';
import 'package:bluedev_mobile/main.dart';
import 'package:bluedev_mobile/screens/notifications_screen.dart';

void main() {
  // Nos testes não há rede; a fonte cai para a padrão.
  GoogleFonts.config.allowRuntimeFetching = false;

  testWidgets('abre na tela de login (M01)', (tester) async {
    await tester.pumpWidget(const BlueDevApp());

    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Usuário'), findsOneWidget);
    expect(find.text('Senha'), findsOneWidget);
    expect(find.text('Entrar'), findsOneWidget);
  });

  testWidgets('mostra e permite limpar notificações de funcionários',
      (tester) async {
    final session = Session(
      api: ApiClient(''),
      user: const {
        'usuario': 'ADM',
        'usuario_id': 1,
        'tipo_usuario': 'ADM',
      },
    );
    var readAll = false;

    await tester.pumpWidget(MaterialApp(
      home: NotificationsScreen(
        session: session,
        notifications: const [
          {
            'id': 42,
            'usuario_id': 2,
            'usuario_nome': 'FUNC',
            'titulo': 'Empilhadeira quebrou',
            'nome_maquina': 'Empilhadeira A',
            'data_criacao': '2026-11-03 10:00:00',
          },
        ],
        onReadThrough: (_) async {},
        onReadAll: () async {
          readAll = true;
        },
      ),
    ));

    expect(find.text('Empilhadeira quebrou'), findsOneWidget);
    expect(find.text('Criada por FUNC'), findsOneWidget);
    await tester.tap(find.text('Ler todas'));
    await tester.pumpAndSettle();

    expect(readAll, isTrue);
    expect(find.text('Empilhadeira quebrou'), findsNothing);
    expect(find.textContaining('Você está em dia.'), findsOneWidget);
    session.api.close();
  });
}
