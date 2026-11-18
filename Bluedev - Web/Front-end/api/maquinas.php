<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

if ($method === 'GET') {
    $details = isset($_GET['detalhes']) && $_GET['detalhes'] === '1';
    // A lista simples (id + nome) também serve para vincular ocorrências.
    $podeLista = auth_can('ver_cadastros', $usuario) || auth_can('registrar_ocorrencias', $usuario)
        || auth_can('gerenciar_ocorrencias', $usuario);
    if (($details && !auth_can('ver_cadastros', $usuario)) || (!$details && !$podeLista)) {
        api_respond(['erro' => 'Você não tem permissão para consultar máquinas.'], 403);
    }
    $params = [];
    $conds = [];
    if ($details) {
        $busca = api_busca(['nome', 'codigo', 'modelo', 'fabricante', 'localizacao'], $params);
        if ($busca !== '') {
            $conds[] = $busca;
        }
        $status = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
        if ($status !== '') {
            $conds[] = 'status_maquina = :status';
            $params[':status'] = $status;
        }
        $sql = 'SELECT id, nome, codigo, modelo, fabricante, numero_serie, '
            . 'data_aquisicao, localizacao, status_maquina, descricao FROM maquinas'
            . ($conds ? ' WHERE ' . implode(' AND ', $conds) : '') . ' ORDER BY id DESC';
    } else {
        $sql = 'SELECT id, nome FROM maquinas ORDER BY nome';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Cadastro, edição e exclusão: somente administradores.
api_require_admin();

if ($method === 'POST' || $method === 'PUT') {
    $body = api_read_json();
    $nomeValue = isset($body['nome']) ? $body['nome'] : '';
    $codigoValue = isset($body['codigo']) ? $body['codigo'] : '';
    $nome = is_string($nomeValue) ? trim($nomeValue) : '';
    $codigo = is_string($codigoValue) ? trim($codigoValue) : '';
    $modeloValue = isset($body['modelo']) ? $body['modelo'] : '';
    $fabricanteValue = isset($body['fabricante']) ? $body['fabricante'] : '';
    $descricaoValue = isset($body['descricao']) ? $body['descricao'] : '';
    $modelo = is_string($modeloValue) && trim($modeloValue) !== ''
        ? trim($modeloValue) : null;
    $fabricante = is_string($fabricanteValue) && trim($fabricanteValue) !== ''
        ? trim($fabricanteValue) : null;
    $descricao = is_string($descricaoValue) ? trim($descricaoValue) : '';
    $status = isset($body['status_maquina']) ? $body['status_maquina'] : '';
    $dataValue = isset($body['data_aquisicao']) ? $body['data_aquisicao'] : '';
    $dataAquisicao = is_string($dataValue) ? trim($dataValue) : '';

    if (!is_string($modeloValue) || !is_string($fabricanteValue)) {
        api_respond(['erro' => 'Modelo e fabricante devem ser textos.'], 422);
    }
    if (!is_string($dataValue)) {
        api_respond(['erro' => 'A data de aquisição é inválida.'], 422);
    }
    if ($nome === '' || strlen($nome) > 100) {
        api_respond(['erro' => 'Informe o nome da máquina (até 100 caracteres).'], 422);
    }
    if ($codigo === '' || strlen($codigo) > 50) {
        api_respond(['erro' => 'Informe o código da máquina (até 50 caracteres).'], 422);
    }
    if (!is_string($status) ||
        !in_array($status, ['ativa', 'inativa', 'em manutenção'], true)) {
        api_respond(['erro' => 'Selecione um status válido para a máquina.'], 422);
    }
    if ($modelo !== null && strlen($modelo) > 100) {
        api_respond(['erro' => 'O modelo deve ter até 100 caracteres.'], 422);
    }
    if ($fabricante !== null && strlen($fabricante) > 100) {
        api_respond(['erro' => 'O fabricante deve ter até 100 caracteres.'], 422);
    }
    if (!is_string($descricaoValue)) {
        api_respond(['erro' => 'A descrição deve ser um texto.'], 422);
    }
    if ($dataAquisicao !== '' &&
        (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAquisicao) ||
            !checkdate(
                (int) substr($dataAquisicao, 5, 2),
                (int) substr($dataAquisicao, 8, 2),
                (int) substr($dataAquisicao, 0, 4)
            ))) {
        api_respond(['erro' => 'A data de aquisição é inválida.'], 422);
    }
    $dataAquisicao = $dataAquisicao === '' ? null : $dataAquisicao;

    $values = [
        ':nome' => $nome,
        ':codigo' => $codigo,
        ':modelo' => $modelo,
        ':fabricante' => $fabricante,
        ':data_aquisicao' => $dataAquisicao,
        ':status_maquina' => $status,
        ':descricao' => $descricao
    ];

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO maquinas '
            . '(usuario_id, nome, codigo, modelo, fabricante, numero_serie, '
            . 'data_aquisicao, localizacao, status_maquina, descricao) '
            . 'VALUES (:usuario_id, :nome, :codigo, :modelo, :fabricante, NULL, '
            . ':data_aquisicao, NULL, :status_maquina, :descricao)'
        );
        $stmt->execute([':usuario_id' => $usuario['id']] + $values);
        api_respond(['id' => (int) $pdo->lastInsertId()], 201);
    }

    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de máquina inválido.'], 400);
    }
    $where = 'id = :id';
    $check = $pdo->prepare('SELECT id FROM maquinas WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        api_respond(['erro' => 'Máquina não encontrada.'], 404);
    }

    $values[':id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE maquinas SET nome = :nome, codigo = :codigo, modelo = :modelo, '
        . 'fabricante = :fabricante, data_aquisicao = :data_aquisicao, '
        . 'status_maquina = :status_maquina, descricao = :descricao WHERE ' . $where
    );
    $stmt->execute($values);
    api_respond(['mensagem' => 'Máquina atualizada.']);
}

if ($method === 'DELETE') {
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de máquina inválido.'], 400);
    }
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM ocorrencias WHERE maquina_id = :maquina_id'
    );
    $check->execute([':maquina_id' => $id]);
    if ((int) $check->fetchColumn() > 0) {
        api_respond(
            ['erro' => 'Não é possível remover uma máquina vinculada a ocorrências.'],
            409
        );
    }
    $stmt = $pdo->prepare('DELETE FROM maquinas WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        api_respond(['erro' => 'Máquina não encontrada.'], 404);
    }
    api_respond(['mensagem' => 'Máquina removida.']);
}

api_respond(['erro' => 'Método não permitido.'], 405);
