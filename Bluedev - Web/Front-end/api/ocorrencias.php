<?php
require_once __DIR__ . '/bootstrap.php';

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sql = 'SELECT o.id, o.titulo, o.tipo, o.status, o.descricao, o.imagem, '
        . 'o.maquina_id, o.data_criacao, m.nome AS nome_maquina '
        . 'FROM ocorrencias o LEFT JOIN maquinas m ON o.maquina_id = m.id';
    $conditions = [];
    $params = [];

    if ($usuario['tipo'] !== 'ADM') {
        $conditions[] = 'o.usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuario['id'];
    }
    if ($status !== '') {
        if ($status === 'Concluída' || $status === 'Concluído') {
            $conditions[] = "o.status IN ('Concluída', 'Concluído')";
        } else {
            $conditions[] = 'o.status = :status';
            $params[':status'] = $status;
        }
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY o.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($method === 'POST' || $method === 'PUT') {
    $data = api_validate_occurrence(api_read_json(), $usuario);
    api_verify_machine($pdo, $data['maquina_id'], $usuario);

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO ocorrencias (usuario_id, maquina_id, titulo, tipo, status, descricao) '
            . 'VALUES (:usuario_id, :maquina_id, :titulo, :tipo, :status, :descricao)'
        );
        $stmt->execute([
            ':usuario_id' => $usuario['id'],
            ':maquina_id' => $data['maquina_id'],
            ':titulo' => $data['titulo'],
            ':tipo' => $data['tipo'],
            ':status' => $data['status'],
            ':descricao' => $data['descricao']
        ]);
        api_respond(['id' => (int) $pdo->lastInsertId()], 201);
    }

    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id) {
        api_respond(['erro' => 'Identificador de ocorrência inválido.'], 400);
    }
    $where = 'id = :id';
    $params = [
        ':id' => $id,
        ':maquina_id' => $data['maquina_id'],
        ':titulo' => $data['titulo'],
        ':tipo' => $data['tipo'],
        ':status' => $data['status'],
        ':descricao' => $data['descricao']
    ];
    if ($usuario['tipo'] !== 'ADM') {
        $where .= ' AND usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuario['id'];
    }
    $stmt = $pdo->prepare(
        'UPDATE ocorrencias SET maquina_id = :maquina_id, titulo = :titulo, tipo = :tipo, '
        . 'status = :status, descricao = :descricao WHERE ' . $where
    );
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM ocorrencias WHERE id = :id' . (
            $usuario['tipo'] === 'ADM' ? '' : ' AND usuario_id = :usuario_id'
        ));
        $checkParams = [':id' => $id];
        if ($usuario['tipo'] !== 'ADM') {
            $checkParams[':usuario_id'] = $usuario['id'];
        }
        $check->execute($checkParams);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            api_respond(['erro' => 'Ocorrência não encontrada ou sem permissão de acesso.'], 404);
        }
    }
    api_respond(['mensagem' => 'Ocorrência atualizada.']);
}

if ($method === 'DELETE') {
    if ($usuario['tipo'] !== 'ADM') {
        api_respond(['erro' => 'Somente administradores podem excluir ocorrências.'], 403);
    }
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id) {
        api_respond(['erro' => 'Identificador de ocorrência inválido.'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM ocorrencias WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        api_respond(['erro' => 'Ocorrência não encontrada.'], 404);
    }
    api_respond(['mensagem' => 'Ocorrência removida.']);
}

api_respond(['erro' => 'Método não permitido.'], 405);
