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
    if (!$details) {
        if ($usuario['tipo'] === 'ADM') {
            $stmt = $pdo->query('SELECT id, nome FROM maquinas ORDER BY nome');
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, nome FROM maquinas WHERE usuario_id = :usuario_id ORDER BY nome'
            );
            $stmt->execute([':usuario_id' => $usuario['id']]);
        }
    } else {
        $sql = 'SELECT id, nome, codigo, modelo, fabricante, numero_serie, '
            . 'data_aquisicao, localizacao, status_maquina, descricao FROM maquinas';
        if ($usuario['tipo'] === 'ADM') {
            $sql .= ' ORDER BY id DESC';
            $stmt = $pdo->query($sql);
        } else {
            $sql .= ' WHERE usuario_id = :usuario_id ORDER BY id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':usuario_id' => $usuario['id']]);
        }
    }
    api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

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
    if ($usuario['tipo'] !== 'ADM') {
        $where .= ' AND usuario_id = :usuario_id';
        $values[':usuario_id'] = $usuario['id'];
    }
    $check = $pdo->prepare('SELECT id FROM maquinas WHERE ' . $where);
    $check->execute([':id' => $id] + (
        $usuario['tipo'] === 'ADM' ? [] : [':usuario_id' => $usuario['id']]
    ));
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        api_respond(['erro' => 'Máquina não encontrada ou sem permissão de acesso.'], 404);
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
    if ($usuario['tipo'] !== 'ADM') {
        api_respond(['erro' => 'Somente administradores podem excluir máquinas.'], 403);
    }
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
