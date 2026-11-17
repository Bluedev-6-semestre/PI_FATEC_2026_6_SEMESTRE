import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

/// Aba "Perfil" (ADM e FUNC): dados do usuário e sair.
///
/// Cadastros, relatórios e assinatura ficam no backoffice web; o app cuida
/// apenas das ocorrências.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({required this.session, super.key});

  final Session session;

  @override
  Widget build(BuildContext context) {
    final role = session.isAdmin ? 'Administrador' : 'Funcionário';
    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(BD.margin, 16, BD.margin, 40),
        children: [
          const Align(alignment: Alignment.centerLeft, child: ClientLogo()),
          const SizedBox(height: 14),
          BDCard(
            child: Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: BD.iconBg,
                  child: Text(
                    session.greetingName.isEmpty
                        ? '?'
                        : session.greetingName[0],
                    style: BD.cardTitle.copyWith(color: BD.primary),
                  ),
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(session.greetingName, style: BD.cardTitle),
                    Text('Usuário: ${session.name} · $role',
                        style: BD.caption),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.logout_rounded,
            label: 'Sair',
            danger: true,
            onTap: () => logout(context, session),
          ),
        ],
      ),
    );
  }
}

class _MenuItem extends StatelessWidget {
  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? BD.red : BD.primary;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BDCard(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: danger ? BD.redBg : BD.iconBg,
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(icon, size: 17, color: color),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(label,
                  style: BD.cardTitle.copyWith(
                      fontSize: 13, color: danger ? BD.red : BD.navy)),
            ),
            const Icon(Icons.chevron_right_rounded, color: BD.muted),
          ],
        ),
      ),
    );
  }
}
