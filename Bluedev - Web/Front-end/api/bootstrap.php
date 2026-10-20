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
