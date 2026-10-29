import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:bluedev_mobile/main.dart';

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
}
