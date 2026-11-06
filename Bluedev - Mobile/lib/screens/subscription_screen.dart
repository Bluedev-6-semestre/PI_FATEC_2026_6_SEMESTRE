import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api_client.dart';
import '../main.dart';
import '../theme.dart';
import '../widgets.dart';

class SubscriptionScreen extends StatefulWidget {
  const SubscriptionScreen({required this.session, super.key});

  final Session session;

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen>
    with WidgetsBindingObserver {
  Map<String, dynamic>? _subscription;
  String? _error;
  bool _loading = true;
  bool _creating = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadStatus();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _loadStatus();
  }

  Future<void> _loadStatus() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final subscription = await widget.session.api.getAssinatura();
      if (mounted) setState(() => _subscription = subscription);
    } catch (error) {
      if (mounted) setState(() => _error = friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _subscribe() async {
    setState(() {
      _creating = true;
      _error = null;
    });
    try {
      final result = await widget.session.api.criarAssinatura();
      final checkout = result['checkout_url'] as String?;
      final uri = checkout == null ? null : Uri.tryParse(checkout);
      if (uri == null ||
          uri.scheme != 'https' ||
          !RegExp(r'(^|\.)mercadopago\.com(\.br)?$', caseSensitive: false)
              .hasMatch(uri.host)) {
        throw const ApiException(
          'O servidor não retornou uma URL de checkout válida.',
        );
      }
      final opened = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (!opened) {
        throw const ApiException(
          'Não foi possível abrir o checkout do Mercado Pago.',
        );
      }
    } catch (error) {
      if (mounted) {
        final message = friendlyError(error);
        setState(() => _error = message);
        showError(context, message);
      }
    } finally {
      if (mounted) setState(() => _creating = false);
    }
  }

  String get _status {
    final value = _subscription?['status'];
    return value is String ? value : 'none';
  }

  String get _statusLabel {
    switch (_status) {
      case 'pending':
        return 'Pendente';
      case 'approved':
        return 'Aprovada';
      case 'rejected':
        return 'Recusada';
      case 'canceled':
        return 'Cancelada';
      case 'paused':
        return 'Pausada';
      default:
        return 'Sem assinatura';
    }
  }

  Color get _statusColor {
    switch (_status) {
      case 'approved':
        return BD.green;
      case 'pending':
      case 'paused':
        return BD.amber;
      case 'rejected':
      case 'canceled':
        return BD.red;
      default:
        return BD.muted;
    }
  }

  String get _planName {
    final value = _subscription?['plano'];
    return value is String && value.isNotEmpty ? value : 'BlueDev';
  }

  String get _priceLabel {
    final value = _subscription?['valor'];
    if (value is! num) return 'Valor indisponível';
    return 'R\$ ${value.toStringAsFixed(2).replaceAll('.', ',')} / mês';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Assinatura'),
        actions: [
          IconButton(
            tooltip: 'Atualizar status',
            onPressed: _loading || _creating ? null : _loadStatus,
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadStatus,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(
                  BD.margin,
                  16,
                  BD.margin,
                  32,
                ),
                children: [
                  BDCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Plano', style: BD.caption),
                        const SizedBox(height: 4),
                        Text(_planName, style: BD.cardTitle.copyWith(fontSize: 17)),
                        const SizedBox(height: 8),
                        Text(_priceLabel, style: BD.body),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Text('Status atual', style: BD.caption),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 5,
                              ),
                              decoration: BoxDecoration(
                                color: _statusColor.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                _statusLabel,
                                style: BD.caption.copyWith(
                                  color: _statusColor,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'A cobrança é mensal e o pagamento é concluído no checkout seguro do Mercado Pago.',
                    style: BD.caption,
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    InfoBanner(
                      icon: Icons.wifi_off_rounded,
                      text: _error!,
                      foreground: BD.red,
                      background: BD.redBg,
                      action: TextButton(
                        onPressed: _loadStatus,
                        child: const Text('Tentar novamente'),
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: _creating || _status == 'approved'
                        ? null
                        : _subscribe,
                    style: FilledButton.styleFrom(
                      backgroundColor: BD.mercadoPago,
                    ),
                    child: _creating
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: BD.surface,
                            ),
                          )
                        : Text(
                            _status == 'approved'
                                ? 'Assinatura aprovada'
                                : 'Assinar',
                          ),
                  ),
                ],
              ),
            ),
    );
  }
}
