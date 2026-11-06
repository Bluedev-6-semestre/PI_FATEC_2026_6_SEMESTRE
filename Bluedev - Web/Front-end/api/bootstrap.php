<?php
header('Content-Type: application/json; charset=utf-8');

function api_respond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_read_json()
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        api_respond(['erro' => 'Corpo JSON inválido.'], 400);
    }
    return $data;
}

function api_require_login()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['usuario_id']) || empty($_SESSION['tipo_usuario'])) {
        api_respond(['erro' => 'Autenticação necessária.'], 401);
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'tipo' => $_SESSION['tipo_usuario']
    ];
}

function api_validate_occurrence($data, $usuario)
{
    $tituloValue = isset($data['titulo']) ? $data['titulo'] : '';
    $titulo = is_string($tituloValue) ? trim($tituloValue) : '';
    $tipo = isset($data['tipo']) ? $data['tipo'] : '';
    $status = isset($data['status']) ? $data['status'] : '';
    $descricao = isset($data['descricao']) ? $data['descricao'] : '';
    $maquinaValue = isset($data['maquina_id']) ? $data['maquina_id'] : null;
    $maquinaId = null;
    if ($maquinaValue !== null && $maquinaValue !== '') {
        if (!is_string($maquinaValue) && !is_int($maquinaValue)) {
            api_respond(['erro' => 'Máquina inválida.'], 422);
        }
        $maquinaId = filter_var($maquinaValue, FILTER_VALIDATE_INT);
    }

    if ($titulo === '' || strlen($titulo) > 100) {
        api_respond(['erro' => 'Informe um título com até 100 caracteres.'], 422);
    }
    if (!in_array($tipo, ['Preventiva', 'Corretiva'], true)) {
        api_respond(['erro' => 'Tipo de manutenção inválido.'], 422);
    }
    if (!in_array($status, ['Pendente', 'Em andamento', 'Concluída', 'Concluído'], true)) {
        api_respond(['erro' => 'Status inválido.'], 422);
    }
    if (!is_string($descricao)) {
        api_respond(['erro' => 'A descrição deve ser um texto.'], 422);
    }
    if ($maquinaId === false) {
        api_respond(['erro' => 'Máquina inválida.'], 422);
    }

    return [
        'titulo' => $titulo,
        'tipo' => $tipo,
        'status' => $status,
        'descricao' => $descricao,
        'maquina_id' => $maquinaId
    ];
}

// Salva a foto enviada pelo app (base64) em uploads/ e devolve o caminho relativo.
// Aceita só JPEG, PNG ou WebP de verdade (checa o MIME do conteúdo) e até 5 MB.
function api_save_image($data)
{
    if (!isset($data['imagem_base64']) || $data['imagem_base64'] === '') {
        return null;
    }
    if (!is_string($data['imagem_base64'])) {
        api_respond(['erro' => 'Imagem inválida.'], 422);
    }
    $bytes = base64_decode($data['imagem_base64'], true);
    if ($bytes === false || $bytes === '') {
        api_respond(['erro' => 'Imagem inválida.'], 422);
    }
    if (strlen($bytes) > 5 * 1024 * 1024) {
        api_respond(['erro' => 'A imagem deve ter no máximo 5 MB.'], 422);
    }
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!isset($extensoes[$mime])) {
        api_respond(['erro' => 'Formato de imagem não permitido.'], 422);
    }
    $nome = 'oc_' . bin2hex(random_bytes(12)) . '.' . $extensoes[$mime];
    $pasta = dirname(__DIR__) . '/uploads/';
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }
    if (file_put_contents($pasta . $nome, $bytes) === false) {
        api_respond(['erro' => 'Não foi possível salvar a imagem.'], 500);
    }
    return 'uploads/' . $nome;
}

function api_verify_machine($pdo, $maquinaId, $usuario)
{
    if ($maquinaId === null) {
        return;
    }

    $sql = 'SELECT id FROM maquinas WHERE id = :id';
    if ($usuario['tipo'] !== 'ADM') {
        $sql .= ' AND usuario_id = :usuario_id';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $maquinaId, PDO::PARAM_INT);
    if ($usuario['tipo'] !== 'ADM') {
        $stmt->bindValue(':usuario_id', $usuario['id'], PDO::PARAM_INT);
    }
    $stmt->execute();

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        api_respond(['erro' => 'Máquina não encontrada ou sem permissão de acesso.'], 422);
    }
}
