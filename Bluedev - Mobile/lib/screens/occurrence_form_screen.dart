import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../main.dart';
import '../theme.dart';
import '../widgets.dart';
import 'occurrences_screen.dart' show tipos;

/// Status gravados no banco e o rótulo mostrado no app.
const _statusOptions = {
  'Pendente': 'Pendente',
  'Em andamento': 'Em andamento',
  'Concluída': 'Resolvida',
};

/// M10 · Nova ocorrência (câmera) — também usada para editar.
///
/// A ocorrência é salva primeiro (JSON) e a foto vai depois, em
/// POST /api/ocorrencias/{id}/imagem. Se só a foto falhar, a ocorrência já
/// existe: o formulário continua aberto para tentar de novo.
class OccurrenceFormScreen extends StatefulWidget {
  const OccurrenceFormScreen({
    required this.session,
    this.occurrence,
    super.key,
  });

  final Session session;
  final Map<String, dynamic>? occurrence;

  @override
  State<OccurrenceFormScreen> createState() => _OccurrenceFormScreenState();
}

class _OccurrenceFormScreenState extends State<OccurrenceFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _picker = ImagePicker();
  late final TextEditingController _title;
  late final TextEditingController _description;
  String? _type;
  String? _status;
  int? _machineId;
  List<Map<String, dynamic>> _machines = [];
  bool _loadingMachines = true;
  bool _saving = false;
  Uint8List? _photo;
  bool _removeExistingPhoto = false;

  /// Preenchido quando a ocorrência foi criada mas a foto falhou.
  int? _createdId;

  int? get _id =>
      _createdId ?? int.tryParse('${widget.occurrence?['id'] ?? ''}');
  bool get _editing => _id != null;
  String? get _existingUrl => _removeExistingPhoto
      ? null
      : widget.session.api.imageUrl(widget.occurrence?['imagem'] as String?);

  @override
  void initState() {
    super.initState();
    final item = widget.occurrence;
    _title = TextEditingController(text: item?['titulo'] as String? ?? '');
    _description =
        TextEditingController(text: item?['descricao'] as String? ?? '');
    _type = item?['tipo'] as String?;
    final status = item?['status'] as String?;
    _status = status == 'Concluído' ? 'Concluída' : status;
    _machineId = int.tryParse('${item?['maquina_id'] ?? ''}');
    _loadMachines();
  }

  Future<void> _loadMachines() async {
    try {
      final machines = await widget.session.api.getMaquinas();
      if (!mounted) return;
      setState(() {
        _machines = machines;
        if (_machineId != null &&
            !machines.any((m) => '${m['id']}' == '$_machineId')) {
          _machineId = null;
        }
      });
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _loadingMachines = false);
    }
  }

  Future<void> _takePhoto() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(BD.margin, 0, BD.margin, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined,
                    color: BD.primary),
                title: Text('Tirar foto agora', style: BD.cardTitle),
                onTap: () => Navigator.pop(sheetContext, ImageSource.camera),
              ),
              ListTile(
                leading:
                    const Icon(Icons.photo_library_outlined, color: BD.primary),
                title: Text('Escolher da galeria', style: BD.cardTitle),
                onTap: () => Navigator.pop(sheetContext, ImageSource.gallery),
              ),
              if (_photo != null || _existingUrl != null)
                ListTile(
                  leading: const Icon(Icons.delete_outline, color: BD.red),
                  title: Text('Remover foto',
                      style: BD.cardTitle.copyWith(color: BD.red)),
                  onTap: () {
                    setState(() {
                      _photo = null;
                      _removeExistingPhoto = true;
                    });
                    Navigator.pop(sheetContext);
                  },
                ),
            ],
          ),
        ),
      ),
    );
    if (source == null) return;
    try {
      // Reduz no aparelho: o serviço aceita até 5 MB.
      final file = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 75,
      );
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (mounted) setState(() => _photo = bytes);
    } catch (error) {
      if (mounted) showError(context, 'Não foi possível abrir a câmera.');
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    final api = widget.session.api;
    try {
      final id = await api.saveOcorrencia(
        {
          'titulo': _title.text.trim(),
          'tipo': _type,
          'status': _status,
          'descricao': _description.text.trim(),
          'maquina_id': _machineId,
        },
        id: _id,
      );
      try {
        if (_photo != null) {
          await api.enviarImagem(id, _photo!);
        } else if (_removeExistingPhoto) {
          await api.removerImagem(id);
        }
      } catch (error) {
        // A ocorrência já foi salva; só a foto falhou.
        if (mounted) {
          setState(() => _createdId = id);
          showError(context,
              'Ocorrência salva, mas a foto não foi enviada: ${friendlyError(error)}');
        }
        return;
      }
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) showError(context, friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  Widget _select<T>({
    required T? value,
    required String hint,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    required String error,
  }) {
    return DropdownButtonFormField<T>(
      initialValue: value,
      isExpanded: true,
      style: BD.body,
      icon: const Icon(Icons.keyboard_arrow_down_rounded),
      hint: Text(hint, style: BD.body.copyWith(color: BD.muted)),
      items: items,
      onChanged: onChanged,
      validator: (v) => v == null ? error : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Text(_editing ? 'Editar ocorrência' : 'Nova ocorrência'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(BD.margin, 14, BD.margin, 24),
          children: [
            _PhotoBox(
              photo: _photo,
              existingUrl: _existingUrl,
              onTap: _takePhoto,
            ),
            const SizedBox(height: 14),
            const FieldLabel('Título da ocorrência'),
            TextFormField(
              controller: _title,
              maxLength: 100,
              style: BD.body,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                hintText: 'Ex: Empilhadeira quebrou',
                counterText: '',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Informe o título.'
                  : null,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Tipo de manutenção'),
            _select<String>(
              value: _type,
              hint: 'Selecione',
              items: [
                for (final t in tipos)
                  DropdownMenuItem(value: t, child: Text(t)),
              ],
              onChanged: (v) => setState(() => _type = v),
              error: 'Selecione o tipo.',
            ),
            const SizedBox(height: 12),
            const FieldLabel('Status'),
            _select<String>(
              value: _status,
              hint: 'Selecione',
              items: [
                for (final entry in _statusOptions.entries)
                  DropdownMenuItem(value: entry.key, child: Text(entry.value)),
              ],
              onChanged: (v) => setState(() => _status = v),
              error: 'Selecione o status.',
            ),
            const SizedBox(height: 12),
            // O occurrence-service aceita ocorrência sem máquina.
            const FieldLabel('Máquina (opcional)'),
            DropdownButtonFormField<int?>(
              initialValue: _machineId,
              isExpanded: true,
              style: BD.body,
              icon: const Icon(Icons.keyboard_arrow_down_rounded),
              hint: Text('Carregando máquinas...',
                  style: BD.body.copyWith(color: BD.muted)),
              items: _loadingMachines
                  ? null
                  : [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text('Nenhuma máquina selecionada',
                            style: BD.body.copyWith(color: BD.muted)),
                      ),
                      for (final m in _machines)
                        DropdownMenuItem<int?>(
                          value: int.parse('${m['id']}'),
                          child: Text(m['nome'] as String? ?? 'Máquina'),
                        ),
                    ],
              onChanged: (v) => setState(() => _machineId = v),
            ),
            const SizedBox(height: 12),
            const FieldLabel('Descrição'),
            TextFormField(
              controller: _description,
              minLines: 3,
              maxLines: 6,
              style: BD.body,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                hintText: 'Descreva o problema',
              ),
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : Text(_editing ? 'Salvar alterações' : 'Registrar ocorrência'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Área tracejada "Tirar foto · opcional" ou a prévia da foto.
class _PhotoBox extends StatelessWidget {
  const _PhotoBox({
    required this.photo,
    required this.existingUrl,
    required this.onTap,
  });

  final Uint8List? photo;
  final String? existingUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final Widget? image = photo != null
        ? Image.memory(photo!, fit: BoxFit.cover)
        : existingUrl != null
            ? Image.network(existingUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink())
            : null;

    if (image != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(BD.cardRadius),
        child: AspectRatio(
          aspectRatio: 16 / 10,
          child: Stack(
            fit: StackFit.expand,
            children: [
              image,
              Positioned(
                right: 10,
                bottom: 10,
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, 36),
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                  ),
                  onPressed: onTap,
                  icon: const Icon(Icons.photo_camera_outlined, size: 16),
                  label: const Text('Trocar foto'),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(BD.cardRadius),
      child: CustomPaint(
        painter: _DashedBorderPainter(),
        child: Container(
          height: 130,
          decoration: BoxDecoration(
            color: BD.background,
            borderRadius: BorderRadius.circular(BD.cardRadius),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: const BoxDecoration(
                  color: BD.primary,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.photo_camera_outlined,
                    color: Colors.white, size: 18),
              ),
              const SizedBox(height: 8),
              Text('Tirar foto',
                  style: BD.caption.copyWith(
                      color: BD.primary,
                      fontWeight: FontWeight.w700,
                      fontSize: 12)),
              Text('opcional', style: BD.caption.copyWith(fontSize: 10)),
            ],
          ),
        ),
      ),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = BD.primary.withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    final path = Path()
      ..addRRect(RRect.fromRectAndRadius(
        Offset.zero & size,
        const Radius.circular(BD.cardRadius),
      ));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + 5), paint);
        distance += 9;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
