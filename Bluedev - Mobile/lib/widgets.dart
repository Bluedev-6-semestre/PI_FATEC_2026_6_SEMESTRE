import 'package:flutter/material.dart';

import 'theme.dart';

// ---------------------------------------------------------------------------
// Status: o banco usa Pendente / Em andamento / Concluída; o protótipo mostra
// "Concluída" como "Resolvida".
// ---------------------------------------------------------------------------

bool isResolved(String? status) =>
    status == 'Concluída' || status == 'Concluído';

String statusLabel(String? status) {
  if (isResolved(status)) return 'Resolvida';
  return status == null || status.isEmpty ? 'Pendente' : status;
}

(Color, Color) statusColors(String? status) {
  if (isResolved(status)) return (BD.green, BD.greenBg);
  if (status == 'Em andamento') return (BD.primary, BD.blueBg);
  return (BD.amber, BD.amberBg);
}

/// Chip de status em pílula (Pendente âmbar, Resolvida verde).
class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final (fg, bg) = statusColors(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        statusLabel(status),
        style: BD.caption.copyWith(
          color: fg,
          fontSize: 10.5,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

/// Cartão branco com raio 14 e borda fina.
class BDCard extends StatelessWidget {
  const BDCard({
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.onTap,
    this.onLongPress,
    this.borderColor = BD.border,
    super.key,
  });

  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: BD.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(BD.cardRadius),
        side: BorderSide(
          color: borderColor,
          width: borderColor == BD.border ? 1 : 1.4,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        onLongPress: onLongPress,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

/// Botão de ícone quadrado com fundo claro (sino, filtros, lixeira).
class SquareIconButton extends StatelessWidget {
  const SquareIconButton({
    required this.icon,
    required this.onPressed,
    required this.tooltip,
    this.color = BD.navy,
    this.background = BD.background,
    super.key,
  });

  final IconData icon;
  final VoidCallback? onPressed;
  final String tooltip;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onPressed,
          child: SizedBox.square(
            dimension: 36,
            child: Icon(icon, size: 19, color: color),
          ),
        ),
      ),
    );
  }
}

/// Rótulo em negrito acima do campo ("Usuário", "Título da ocorrência").
class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(text, style: BD.label),
    );
  }
}

/// Pílula de filtro (Todas / Pendentes / Resolvidas). Selecionada = azul-marinho.
class FilterPill extends StatelessWidget {
  const FilterPill({
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? BD.navy : BD.surface,
      shape: StadiumBorder(
        side: BorderSide(color: selected ? BD.navy : BD.border),
      ),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
          child: Text(
            label,
            style: BD.caption.copyWith(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : BD.navy,
            ),
          ),
        ),
      ),
    );
  }
}

/// Banner do início (pagamento efetuado / teste grátis).
class InfoBanner extends StatelessWidget {
  const InfoBanner({
    required this.icon,
    required this.text,
    required this.foreground,
    required this.background,
    this.action,
    super.key,
  });

  final IconData icon;
  final String text;
  final Color foreground;
  final Color background;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(12, action == null ? 11 : 8, 8, 8),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(BD.fieldRadius),
      ),
      child: Row(
        children: [
          Icon(icon, size: 17, color: foreground),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: BD.caption.copyWith(
                color: foreground,
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (action != null) action!,
        ],
      ),
    );
  }
}

/// Cartão de ocorrência resumido (início e relatórios).
class OccurrenceSummaryCard extends StatelessWidget {
  const OccurrenceSummaryCard({
    required this.item,
    required this.onTap,
    super.key,
  });

  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return BDCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  item['titulo'] as String? ?? 'Sem título',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: BD.cardTitle.copyWith(fontSize: 13),
                ),
              ),
              const SizedBox(width: 8),
              StatusChip(item['status'] as String?),
            ],
          ),
          const SizedBox(height: 4),
          Text(occurrenceSubtitle(item), style: BD.caption),
        ],
      ),
    );
  }
}

String occurrenceSubtitle(Map<String, dynamic> item) =>
    '${item['tipo'] ?? 'Sem tipo'} · ${item['nome_maquina'] ?? 'Sem máquina'}';

DateTime? parseDate(dynamic value) => DateTime.tryParse('${value ?? ''}');

String _two(int n) => n.toString().padLeft(2, '0');

String formatDate(DateTime? date) =>
    date == null ? '—' : '${_two(date.day)}/${_two(date.month)}/${date.year}';

String formatDateTime(DateTime? date) => date == null
    ? '—'
    : '${formatDate(date)} · ${_two(date.hour)}:${_two(date.minute)}';

/// Logo do cliente (Arearty) usado no topo do início e do menu.
class ClientLogo extends StatelessWidget {
  const ClientLogo({this.height = 26, super.key});

  final double height;

  @override
  Widget build(BuildContext context) {
    return Image.asset('assets/images/logo_cliente.jpeg', height: height);
  }
}

void showError(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: BD.red));
}

void showInfo(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: BD.navy));
}

void showSoon(BuildContext context, String feature) =>
    showInfo(context, '$feature: tela ainda não implementada no app.');
