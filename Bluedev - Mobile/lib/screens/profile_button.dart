import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';

/// Ícone de perfil do canto superior direito (ADM e FUNC).
///
/// Abre uma folha com os dados do usuário e o botão Sair. Cadastros,
/// relatórios e assinatura ficam no backoffice web; o app cuida apenas das
/// ocorrências.
class ProfileButton extends StatelessWidget {
  const ProfileButton({required this.session, super.key});

  final Session session;

  String get _initial =>
      session.greetingName.isEmpty ? '?' : session.greetingName[0];

  Future<void> _open(BuildContext context) async {
    final role = session.isAdmin ? 'Administrador' : 'Funcionário';
    final leave = await showModalBottomSheet<bool>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(BD.margin, 0, BD.margin, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _Avatar(initial: _initial, size: 64),
              const SizedBox(height: 12),
              Text(session.greetingName, style: BD.screenTitle),
              const SizedBox(height: 2),
              Text('Usuário: ${session.name} · $role', style: BD.caption),
              const SizedBox(height: 22),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: BD.red,
                  backgroundColor: BD.redBg,
                  side: BorderSide.none,
                ),
                onPressed: () => Navigator.pop(sheetContext, true),
                icon: const Icon(Icons.logout_rounded, size: 19),
                label: const Text('Sair'),
              ),
            ],
          ),
        ),
      ),
    );
    if (leave == true && context.mounted) await logout(context, session);
  }

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: 'Perfil',
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: () => _open(context),
        child: _Avatar(initial: _initial, size: 36),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initial, required this.size});

  final String initial;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: BD.iconBg,
        shape: BoxShape.circle,
      ),
      child: Text(
        initial,
        style: BD.cardTitle.copyWith(
          color: BD.primary,
          fontSize: size * 0.42,
        ),
      ),
    );
  }
}
