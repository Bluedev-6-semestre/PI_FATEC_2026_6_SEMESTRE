// Testa o app inteiro no emulador contra a API real.
//
// Pré-requisitos: MySQL e `php -S 127.0.0.1:8000 -t "Bluedev - Web/Front-end"`
// rodando no computador, e um emulador Android ligado.
//
//   flutter test integration_test -d emulator-5554
//
// Os registros criados levam a marca [IT] e são removidos no final.
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

import 'package:bluedev_mobile/api_client.dart';
import 'package:bluedev_mobile/main.dart';

const apiUrl = 'http://10.0.2.2:8000/api';
final stamp = DateTime.now().millisecondsSinceEpoch % 100000;
final occurrenceTitle = '[IT] Vazamento $stamp';
final funcOccurrenceTitle = '[IT] Correia FUNC $stamp';
final supplierName = '[IT] Fornecedor $stamp';
final employeeName = '[IT] Funcionario $stamp';
final machineName = '[IT] Maquina $stamp';

/// Só textos exibidos (o `find.text` também acha o que foi digitado em campos).
Finder label(String text) =>
    find.byWidgetPredicate((w) => w is Text && w.data == text,
        description: 'texto "$text"');

/// Textos visíveis na tela, para entender uma falha.
String screenTexts() => find
    .byType(Text)
    .evaluate()
    .map((e) => (e.widget as Text).data)
    .whereType<String>()
    .where((t) => t.trim().isNotEmpty)
    .toSet()
    .join(' | ');

/// Espera até [finder] aparecer (as telas dependem da rede).
Future<void> waitFor(WidgetTester tester, Finder finder,
    {Duration timeout = const Duration(seconds: 30)}) async {
  final end = DateTime.now().add(timeout);
  while (DateTime.now().isBefore(end)) {
    await tester.pump(const Duration(milliseconds: 250));
    try {
      if (finder.evaluate().isNotEmpty) return;
    } on StateError {
      // `.first` de um localizador ainda vazio: continua esperando.
    }
  }
  throw TestFailure('Não apareceu na tela: $finder\nNa tela: ${screenTexts()}');
}

Future<void> waitGone(WidgetTester tester, Finder finder,
    {Duration timeout = const Duration(seconds: 30)}) async {
  final end = DateTime.now().add(timeout);
  while (DateTime.now().isBefore(end)) {
    await tester.pump(const Duration(milliseconds: 250));
    if (finder.evaluate().isEmpty) return;
  }
  throw TestFailure('Não sumiu da tela: $finder\nNa tela: ${screenTexts()}');
}

Future<void> tap(WidgetTester tester, Finder finder) async {
  await waitFor(tester, finder);
  // No aparelho o teclado virtual fica aberto e cobre os botões.
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pump(const Duration(milliseconds: 600));
  await tester.ensureVisible(finder.first);
  await tester.pump(const Duration(milliseconds: 200));
  await tester.tap(finder.first, warnIfMissed: false);
  await tester.pump(const Duration(milliseconds: 400));
}

Future<void> typeInto(WidgetTester tester, String hint, String text) async {
  final field = find.widgetWithText(TextField, hint);
  await waitFor(tester, field);
  await tester.ensureVisible(field.first);
  for (var attempt = 0; attempt < 3; attempt++) {
    await tester.tap(field.first, warnIfMissed: false);
    await tester.pump(const Duration(milliseconds: 300));
    await tester.enterText(field.first, text);
    await tester.pump(const Duration(milliseconds: 300));
    // Ignora a formatação de máscaras (CNPJ, telefone).
    String plain(String? s) =>
        (s ?? '').replaceAll(RegExp(r'[^\p{L}\p{N}]', unicode: true), '');
    final typed = tester.widget<TextField>(field.first).controller?.text;
    if (plain(typed) == plain(text)) return;
  }
  throw TestFailure('Não consegui digitar "$text" no campo "$hint"');
}

/// Abre um dropdown pela dica e escolhe [option].
Future<void> choose(WidgetTester tester, Finder dropdown, String option) async {
  await tap(tester, dropdown);
  await tap(tester, find.text(option).last);
  await tester.pump(const Duration(milliseconds: 600));
}

Future<void> goBack(WidgetTester tester) async {
  await tap(tester, find.byType(BackButton));
  await tester.pump(const Duration(milliseconds: 600));
}

Future<void> login(WidgetTester tester, String user, String password) async {
  await tester.pumpWidget(const BlueDevApp());
  await waitFor(tester, find.text('Entrar'));
  await typeInto(tester, 'Seu usuário', user);
  await typeInto(tester, '••••••••', password);
  await tap(tester, find.text('Entrar'));
  await waitFor(tester, find.text('Seja bem-vindo!'));
}

Future<void> logoutFrom(WidgetTester tester, String lastTab) async {
  await tap(tester, find.text(lastTab).last);
  await tap(tester, find.text('Sair').last);
  await waitFor(tester, find.text('Entrar'));
}

Future<void> createOccurrence(
    WidgetTester tester, String title, String machine) async {
  await tap(tester, find.byTooltip('Nova ocorrência'));
  await waitFor(tester, find.text('Registrar ocorrência'));
  await waitGone(tester, find.text('Carregando máquinas...'));
  await typeInto(tester, 'Ex: Empilhadeira quebrou', title);
  await choose(tester, find.text('Selecione').first, 'Corretiva');
  await choose(tester, find.text('Selecione').first, 'Pendente');
  await choose(tester, find.text('Nenhuma máquina selecionada'), machine);
  await typeInto(tester, 'Descreva o problema', 'Criada pelo teste de integração');
  await tap(tester, find.text('Registrar ocorrência'));
  await waitFor(tester, find.text('Ocorrência registrada.'));
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  // Remove qualquer sobra [IT] pela API, mesmo se algum teste falhar no meio.
  tearDownAll(() async {
    final api = ApiClient(apiUrl);
    try {
      await api.login('ADM', '1234');
      for (final o in await api.getOcorrencias()) {
        if ('${o['titulo']}'.startsWith('[IT]')) {
          await api.deleteOcorrencia(int.parse('${o['id']}'));
        }
      }
      for (final s in await api.getFornecedores()) {
        if ('${s['nome']}'.startsWith('[IT]')) {
          await api.deleteFornecedor(int.parse('${s['id']}'));
        }
      }
      for (final e in await api.getFuncionarios()) {
        if ('${e['nome']}'.startsWith('[IT]')) {
          await api.deleteFuncionario(int.parse('${e['id']}'));
        }
      }
      for (final m in await api.getMaquinasDetalhadas()) {
        if ('${m['nome']}'.startsWith('[IT]')) {
          await api.deleteMaquina(int.parse('${m['id']}'));
        }
      }
    } finally {
      api.close();
    }
  });

  testWidgets('M01 · login recusa senha errada', (tester) async {
    await tester.pumpWidget(const BlueDevApp());
    await waitFor(tester, find.text('Entrar'));
    await tap(tester, find.text('Entrar'));
    expect(find.text('Informe o usuário.'), findsOneWidget);
    await typeInto(tester, 'Seu usuário', 'ADM');
    await typeInto(tester, '••••••••', 'errada');
    await tap(tester, find.text('Entrar'));
    await waitFor(tester, find.textContaining('inválidos'));
  });

  testWidgets('ADM · início, cadastros e assinatura', (tester) async {
    await login(tester, 'ADM', '1234');
    expect(find.text('Olá, Administrador'), findsOneWidget);
    expect(find.text('Pagamento efetuado'), findsOneWidget);
    expect(find.text('Pendentes'), findsOneWidget);
    expect(find.text('Ocorrências recentes'), findsOneWidget);

    // M13/M14 · Fornecedores
    await tap(tester, find.text('Fornec.'));
    await waitFor(tester, find.byTooltip('Adicionar fornecedor'));
    await tap(tester, find.byTooltip('Adicionar fornecedor'));
    await typeInto(tester, '00.000.000/0001-00', '11222333000181');
    await typeInto(tester, 'Digite o nome da empresa', supplierName);
    await typeInto(tester, 'Digite a categoria', 'Peças');
    await typeInto(tester, '(00) 00000-0000', '19999990000');
    await typeInto(tester, 'Digite o endereço ou CEP', 'Rua do Teste, 10');
    await tap(tester, find.text('Adicionar'));
    await waitFor(tester, label(supplierName));
    await tap(tester, find.byTooltip('Remover fornecedor'));
    await tap(tester, find.text('Remover').last);
    await waitGone(tester, label(supplierName));
    await goBack(tester);

    // M15/M16 · Funcionários
    await tap(tester, find.text('Funcion.'));
    await tap(tester, find.byTooltip('Adicionar funcionário'));
    await typeInto(tester, 'Digite o nome', employeeName);
    await typeInto(tester, 'Digite a idade', '28');
    await typeInto(tester, 'Digite a função', 'Operador');
    await tap(tester, find.text('Adicionar'));
    await waitFor(tester, label(employeeName));
    await tap(tester, find.byTooltip('Remover funcionário'));
    await tap(tester, find.text('Remover').last);
    await waitGone(tester, label(employeeName));
    await goBack(tester);

    // M17/M18 · Máquinas
    await tap(tester, find.text('Máquinas'));
    await tap(tester, find.byTooltip('Adicionar máquina'));
    await typeInto(tester, 'Nome da máquina', machineName);
    await typeInto(tester, 'Código da máquina', 'IT-$stamp');
    await choose(tester, find.text('Selecione'), 'Ativa');
    await tap(tester, find.text('Adicionar'));
    await waitFor(tester, label(machineName));
    await tap(tester, find.byTooltip('Remover máquina'));
    await tap(tester, find.text('Remover').last);
    await waitGone(tester, label(machineName));
    await goBack(tester);

    // M06 · Mais → M04 Assinatura
    await tap(tester, find.text('Mais').last);
    await tap(tester, find.text('Assinatura'));
    await waitFor(tester, find.text('Plano'));
    await goBack(tester);

    await logoutFrom(tester, 'Mais');
  });

  testWidgets('ADM · ocorrências: criar, buscar, filtrar, validar, remover',
      (tester) async {
    await login(tester, 'ADM', '1234');

    // M10 · Nova ocorrência (botão central)
    await createOccurrence(tester, occurrenceTitle, 'Torno CNC 01');

    // M07 · Lista + busca + pílulas
    await tap(tester, find.text('Ocorrências').last);
    await typeInto(tester, 'Buscar por título ou máquina', occurrenceTitle);
    await waitFor(tester, label(occurrenceTitle));
    await tap(tester, find.text('Resolvidas'));
    await waitGone(tester, label(occurrenceTitle));
    await tap(tester, find.text('Pendentes'));
    await waitFor(tester, label(occurrenceTitle));

    // M08 · Filtros
    await tap(tester, find.byTooltip('Filtros'));
    await waitFor(tester, find.text('Aplicar'));
    await tap(tester, find.text('Preventiva').last);
    await tap(tester, find.text('Aplicar'));
    await waitGone(tester, label(occurrenceTitle));
    await tap(tester, find.byTooltip('Filtros'));
    await tap(tester, find.text('Limpar').last);
    await waitFor(tester, label(occurrenceTitle));

    // Busca sem resultado
    await typeInto(tester, 'Buscar por título ou máquina', 'zzz-nada-$stamp');
    await waitFor(tester, find.text('Nenhum resultado para esta busca.'));
    await typeInto(tester, 'Buscar por título ou máquina', occurrenceTitle);

    // M09 · Detalhe → Editar → Validar
    await tap(tester, label(occurrenceTitle));
    await waitFor(tester, find.text('Validar'));
    await tap(tester, find.text('Editar'));
    await waitFor(tester, find.text('Salvar alterações'));
    await typeInto(tester, 'Descreva o problema', 'Editada pelo teste');
    await tap(tester, find.text('Salvar alterações'));
    await waitFor(tester, label('Editada pelo teste'));
    await tap(tester, find.text('Validar'));
    await tap(tester, find.text('Validar').last);
    await waitFor(tester, find.text('Validada'));
    await goBack(tester);

    // M11 · Remover (toque longo)
    await waitFor(tester, label(occurrenceTitle));
    await tester.longPress(label(occurrenceTitle));
    await tester.pump(const Duration(milliseconds: 600));
    await waitFor(tester, find.text('Remover (1)'));
    await tap(tester, find.text('Remover (1)'));
    await tap(tester, find.text('Remover').last);
    await waitGone(tester, label(occurrenceTitle));

    // M19 · Relatórios
    await tap(tester, find.text('Relatórios').last);
    await waitFor(tester, find.text('Ocorrências por tipo'));
    expect(find.text('Ocorrências por status'), findsOneWidget);

    // Notificações (sino)
    await tap(tester, find.text('Início').last);
    await tap(tester, find.byTooltip('Notificações'));
    await waitFor(tester, find.text('Notificações'));
    await goBack(tester);

    await logoutFrom(tester, 'Mais');
  });

  testWidgets('FUNC · início, nova ocorrência e permissões', (tester) async {
    await login(tester, 'FUNC', '123');
    expect(find.text('Pagamento efetuado'), findsNothing);
    expect(find.text('Fornec.'), findsNothing);
    expect(find.byTooltip('Notificações'), findsNothing);
    expect(find.text('Perfil'), findsOneWidget);

    await createOccurrence(tester, funcOccurrenceTitle, 'Esteira Transportadora 03');

    await tap(tester, find.text('Ocorrências').last);
    await typeInto(tester, 'Buscar por título ou máquina', funcOccurrenceTitle);
    await tap(tester, label(funcOccurrenceTitle));
    await waitFor(tester, find.text('Editar'));
    expect(find.text('Validar'), findsNothing);
    expect(find.byTooltip('Remover'), findsNothing);
    await goBack(tester);

    await tap(tester, find.text('Relatórios').last);
    await waitFor(tester, find.text('Ocorrências por tipo'));

    await logoutFrom(tester, 'Perfil');
  });
}
