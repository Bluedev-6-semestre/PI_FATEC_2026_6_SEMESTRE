import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'employees_screen.dart';
import 'machines_screen.dart';
import 'suppliers_screen.dart';

/// Aba "Mais" do ADM (M06): cadastros, assinatura e sair.
class MoreScreen extends StatelessWidget {
  const MoreScreen({required this.session, super.key});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mais')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(BD.margin, 16, BD.margin, 40),
        children: [
          const Align(alignment: Alignment.centerLeft, child: ClientLogo()),
          const SizedBox(height: 14),
          _MenuItem(
            icon: Icons.local_shipping_outlined,
            label: 'Fornecedores',
            onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
              builder: (_) => SuppliersScreen(session: session),
            )),
          ),
          _MenuItem(
            icon: Icons.group_outlined,
            label: 'Funcionários',
            onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
              builder: (_) => EmployeesScreen(session: session),
            )),
          ),
          _MenuItem(
            icon: Icons.settings_suggest_outlined,
            label: 'Máquinas',
            onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
              builder: (_) => MachinesScreen(session: session),
            )),
          ),
          _MenuItem(
            icon: Icons.bar_chart_rounded,
            label: 'Relatórios',
            onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
              builder: (_) => const ReportsPlaceholder(),
            )),
          ),
          _MenuItem(
            icon: Icons.credit_card_rounded,
            label: 'Assinatura',
            onTap: () => _openPlaceholder(context, 'Assinatura'),
          ),
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

void _openPlaceholder(BuildContext context, String title) {
  Navigator.of(context).push(MaterialPageRoute<void>(
    builder: (_) => _FeaturePlaceholderScreen(title: title),
  ));
}

class _FeaturePlaceholderScreen extends StatelessWidget {
  const _FeaturePlaceholderScreen({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Text('Tela em preparação.', style: BD.body),
      ),
    );
  }
}

/// Aba "Perfil" do FUNC: dados do usuário e sair.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({required this.session, super.key});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(BD.margin, 16, BD.margin, 40),
        children: [
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
                    Text('Usuário: ${session.name} · Funcionário',
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

/// Aba Relatórios (M19/M20): ainda não faz parte desta entrega.
class ReportsPlaceholder extends StatelessWidget {
  const ReportsPlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Relatórios')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: BD.iconBg,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.bar_chart_rounded,
                    color: BD.primary, size: 28),
              ),
              const SizedBox(height: 14),
              Text('Relatórios em breve', style: BD.cardTitle),
              const SizedBox(height: 4),
              Text(
                'Os gráficos por tipo, status e mês ainda não foram implementados no app.',
                textAlign: TextAlign.center,
                style: BD.caption,
              ),
            ],
          ),
        ),
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
