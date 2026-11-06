import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({required this.session, super.key});

  final Session session;

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  final _search = TextEditingController();
  late DateTime _start;
  late DateTime _end;
  Map<String, dynamic>? _report;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    _end = DateTime(today.year, today.month, today.day);
    _start = _end.subtract(const Duration(days: 29));
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await widget.session.api.getRelatorios(
        inicio: _start,
        fim: _end,
        busca: _search.text,
      );
      if (mounted) setState(() => _report = report);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickRange() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(1900),
      lastDate: DateTime(now.year + 5, 12, 31),
      initialDateRange: DateTimeRange(start: _start, end: _end),
      helpText: 'Selecione o período',
      fieldStartLabelText: 'Data inicial',
      fieldEndLabelText: 'Data final',
      saveText: 'Aplicar',
    );
    if (picked == null || !mounted) return;
    setState(() {
      _start =
          DateTime(picked.start.year, picked.start.month, picked.start.day);
      _end = DateTime(picked.end.year, picked.end.month, picked.end.day);
    });
    await _load();
  }

  List<Map<String, dynamic>> _rows(String key) {
    final values = _report?[key] as List<dynamic>? ?? const [];
    return values.cast<Map<String, dynamic>>();
  }

  List<Map<String, dynamic>> get _occurrences => _rows('ocorrencias');

  int _count(Map<String, dynamic> row) => row['total'] as int;

  List<Color> get _chartColors =>
      [BD.primary, BD.navy, BD.green, BD.amber, BD.red];

  Widget _chartCard({
    required String title,
    required String description,
    required Widget chart,
    required List<Widget> values,
  }) {
    return BDCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: BD.cardTitle),
          const SizedBox(height: 3),
          Text(description, style: BD.caption),
          const SizedBox(height: 14),
          chart,
          if (values.isNotEmpty) ...[
            const SizedBox(height: 10),
            ...values,
          ],
        ],
      ),
    );
  }

  Widget _emptyChart(String message) => SizedBox(
        height: 100,
        child: Center(child: Text(message, style: BD.caption)),
      );

  List<Widget> _legend(List<Map<String, dynamic>> rows, String labelKey) {
    return [
      for (var index = 0; index < rows.length; index++)
        Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Row(
            children: [
              Container(
                width: 9,
                height: 9,
                decoration: BoxDecoration(
                  color: _chartColors[index % _chartColors.length],
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  '${rows[index][labelKey]}',
                  style: BD.caption.copyWith(color: BD.navy),
                ),
              ),
              Text(
                '${_count(rows[index])} ocorrências',
                style: BD.caption.copyWith(
                  color: BD.navy,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
    ];
  }

  Widget _typeChart(List<Map<String, dynamic>> rows) {
    if (rows.isEmpty) return _emptyChart('Sem ocorrências no período.');
    return SizedBox(
      height: 185,
      child: PieChart(
        PieChartData(
          centerSpaceRadius: 28,
          sectionsSpace: 2,
          sections: [
            for (var index = 0; index < rows.length; index++)
              PieChartSectionData(
                value: _count(rows[index]).toDouble(),
                color: _chartColors[index % _chartColors.length],
                radius: 68,
                title: '${_count(rows[index])}',
                titleStyle: BD.caption.copyWith(
                  color: BD.surface,
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      ),
    );
  }

  double _chartMax(List<Map<String, dynamic>> rows) {
    final largest = rows.map(_count).fold<int>(0, math.max);
    return math.max(1, largest * 1.2);
  }

  String _monthLabel(String value) {
    final parts = value.split('-');
    if (parts.length != 2) return value;
    final month = int.tryParse(parts[1]);
    if (month == null || month < 1 || month > 12) return value;
    const names = [
      'jan',
      'fev',
      'mar',
      'abr',
      'mai',
      'jun',
      'jul',
      'ago',
      'set',
      'out',
      'nov',
      'dez',
    ];
    return '${names[month - 1]}/${parts[0].substring(2)}';
  }

  Widget _barChart({
    required List<Map<String, dynamic>> rows,
    required String labelKey,
    bool months = false,
  }) {
    if (rows.isEmpty) return _emptyChart('Sem ocorrências no período.');
    final maxY = _chartMax(rows);
    final interval = math.max(1, (maxY / 4).ceil()).toDouble();
    final labels = [
      for (final row in rows)
        months ? _monthLabel('${row[labelKey]}') : '${row[labelKey]}',
    ];

    return SizedBox(
      height: 250,
      child: BarChart(
        BarChartData(
          minY: 0,
          maxY: maxY,
          alignment: BarChartAlignment.spaceAround,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            getDrawingHorizontalLine: (_) =>
                const FlLine(color: BD.border, strokeWidth: 1),
          ),
          borderData: FlBorderData(show: false),
          barGroups: [
            for (var index = 0; index < rows.length; index++)
              BarChartGroupData(
                x: index,
                barRods: [
                  BarChartRodData(
                    fromY: 0,
                    toY: _count(rows[index]).toDouble(),
                    color: BD.primary,
                    width: 22,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ],
              ),
          ],
          titlesData: FlTitlesData(
            leftTitles: AxisTitles(
              axisNameWidget: Text('Ocorrências', style: BD.caption),
              axisNameSize: 22,
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 34,
                interval: interval,
                getTitlesWidget: (value, meta) => Text(
                  value.toInt().toString(),
                  style: BD.caption.copyWith(fontSize: 9),
                ),
              ),
            ),
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 38,
                getTitlesWidget: (value, meta) {
                  final index = value.toInt();
                  if (index < 0 || index >= labels.length) {
                    return const SizedBox.shrink();
                  }
                  return SideTitleWidget(
                    meta: meta,
                    child: SizedBox(
                      width: 56,
                      child: Text(
                        labels[index],
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: BD.caption.copyWith(fontSize: 9),
                      ),
                    ),
                  );
                },
              ),
            ),
            topTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            rightTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
          ),
        ),
      ),
    );
  }

  Widget _occurrenceCard(Map<String, dynamic> item) {
    final description = (item['descricao'] as String?)?.trim() ?? '';
    return BDCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item['titulo'] as String? ?? 'Sem título',
            style: BD.cardTitle.copyWith(fontSize: 13),
          ),
          const SizedBox(height: 4),
          Text('Tipo: ${item['tipo'] ?? '—'}', style: BD.caption),
          Text('Status: ${item['status'] ?? '—'}', style: BD.caption),
          Text(
            'Data: ${formatDateTime(parseDate(item['data_criacao']))}',
            style: BD.caption,
          ),
          Text(
            'Descrição: ${description.isEmpty ? '—' : description}',
            style: BD.caption,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final byType = _rows('por_tipo');
    final byStatus = _rows('por_status');
    final byMonth = _rows('por_mes');

    return Scaffold(
      appBar: AppBar(title: const Text('Relatórios')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(
                  BD.margin,
                  14,
                  BD.margin,
                  40,
                ),
                children: [
                  OutlinedButton.icon(
                    onPressed: _pickRange,
                    icon: const Icon(Icons.date_range_rounded),
                    label: Text('${formatDate(_start)} – ${formatDate(_end)}'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _search,
                    style: BD.body,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _load(),
                    decoration: InputDecoration(
                      hintText: 'Buscar por título, tipo ou status',
                      prefixIcon: const Icon(Icons.search_rounded, size: 19),
                      suffixIcon: _search.text.isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Limpar busca',
                              icon: const Icon(Icons.close_rounded, size: 18),
                              onPressed: () {
                                _search.clear();
                                _load();
                              },
                            ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: _load,
                    child: const Text('Aplicar filtros'),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    InfoBanner(
                      icon: Icons.wifi_off_rounded,
                      text: _error!,
                      foreground: BD.red,
                      background: BD.redBg,
                      action: TextButton(
                        onPressed: _load,
                        child: const Text('Tentar novamente'),
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  _chartCard(
                    title: 'Ocorrências por tipo',
                    description: 'Distribuição no período selecionado.',
                    chart: _typeChart(byType),
                    values: _legend(byType, 'categoria'),
                  ),
                  const SizedBox(height: 10),
                  _chartCard(
                    title: 'Ocorrências por status',
                    description: 'Eixo vertical: número de ocorrências.',
                    chart: _barChart(rows: byStatus, labelKey: 'categoria'),
                    values: _legend(byStatus, 'categoria'),
                  ),
                  const SizedBox(height: 10),
                  _chartCard(
                    title: 'Ocorrências por mês',
                    description:
                        'Eixo vertical: número de ocorrências; mostra os 5 meses mais recentes do período.',
                    chart: _barChart(
                      rows: byMonth,
                      labelKey: 'mes',
                      months: true,
                    ),
                    values: _legend(byMonth, 'mes'),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    _occurrences.isEmpty
                        ? 'Nenhuma ocorrência encontrada no período selecionado.'
                        : 'Ocorrências (${_occurrences.length})',
                    style: BD.cardTitle,
                  ),
                  const SizedBox(height: 10),
                  if (_occurrences.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      child: Text(
                        'Tente ajustar o período ou a busca.',
                        textAlign: TextAlign.center,
                        style: BD.caption,
                      ),
                    )
                  else
                    for (final item in _occurrences) ...[
                      _occurrenceCard(item),
                      const SizedBox(height: 10),
                    ],
                ],
              ),
            ),
    );
  }
}
