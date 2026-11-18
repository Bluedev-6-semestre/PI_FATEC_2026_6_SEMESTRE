<?php
require_once __DIR__ . '/bootstrap.php';

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
    $sql = 'SELECT o.id, o.usuario_id, u.usuario AS usuario_nome, o.titulo, '
        . 'o.tipo, o.status, o.descricao, o.imagem, o.maquina_id, '
        . 'o.data_criacao, m.nome AS nome_maquina '
        . 'FROM ocorrencias o LEFT JOIN maquinas m ON o.maquina_id = m.id '
        . 'LEFT JOIN usuarios u ON o.usuario_id = u.id';
    $conditions = [];
    $params = [];

    // Administrador enxerga todas; funcionário, apenas as próprias.
    if ($usuario['tipo'] !== 'ADM') {
        $conditions[] = 'o.usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuario['id'];
    } elseif (isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '') {
        $filtroUsuario = filter_var($_GET['usuario_id'], FILTER_VALIDATE_INT);
        if ($filtroUsuario === false) {
            api_respond(['erro' => 'Filtro de usuário inválido.'], 422);
        }
        $conditions[] = 'o.usuario_id = :filtro_usuario';
        $params[':filtro_usuario'] = $filtroUsuario;
    }
    if ($status !== '') {
        if ($status === 'Concluída' || $status === 'Concluído') {
            $conditions[] = "o.status IN ('Concluída', 'Concluído')";
        } else {
            $conditions[] = 'o.status = :status';
            $params[':status'] = $status;
        }
    }
    $tipoFiltro = isset($_GET['tipo']) && is_string($_GET['tipo']) ? trim($_GET['tipo']) : '';
    if ($tipoFiltro !== '') {
        $conditions[] = 'o.tipo = :tipo';
        $params[':tipo'] = $tipoFiltro;
    }
    if (isset($_GET['maquina_id']) && $_GET['maquina_id'] !== '') {
        $filtroMaquina = filter_var($_GET['maquina_id'], FILTER_VALIDATE_INT);
        if ($filtroMaquina === false) {
            api_respond(['erro' => 'Filtro de máquina inválido.'], 422);
        }
        $conditions[] = 'o.maquina_id = :filtro_maquina';
        $params[':filtro_maquina'] = $filtroMaquina;
    }
    $busca = api_busca(['o.titulo', 'o.descricao', 'm.nome', 'u.usuario'], $params);
    if ($busca !== '') {
        $conditions[] = $busca;
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
    if ($method === 'POST') {
        api_require_permission('registrar_ocorrencias', 'Você não tem permissão para registrar ocorrências.');
    } elseif (!auth_is_admin($usuario)) {
        api_require_permission('gerenciar_ocorrencias', 'Você não tem permissão para atualizar ocorrências.');
    }
    $body = api_read_json();
    $data = api_validate_occurrence($body, $usuario);
    api_verify_machine($pdo, $data['maquina_id'], $usuario);

    if ($method === 'POST') {
        $imagem = api_save_image($body);
        $stmt = $pdo->prepare(
            'INSERT INTO ocorrencias (usuario_id, maquina_id, titulo, tipo, status, descricao, imagem) '
            . 'VALUES (:usuario_id, :maquina_id, :titulo, :tipo, :status, :descricao, :imagem)'
        );
        $stmt->execute([
            ':usuario_id' => $usuario['id'],
            ':maquina_id' => $data['maquina_id'],
            ':titulo' => $data['titulo'],
            ':tipo' => $data['tipo'],
            ':status' => $data['status'],
            ':descricao' => $data['descricao'],
            ':imagem' => $imagem
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
    $setImagem = '';
    $imagem = api_save_image($body);
    if ($imagem !== null) {
        $setImagem = ', imagem = :imagem';
        $params[':imagem'] = $imagem;
    }
    $stmt = $pdo->prepare(
        'UPDATE ocorrencias SET maquina_id = :maquina_id, titulo = :titulo, tipo = :tipo, '
        . 'status = :status, descricao = :descricao' . $setImagem . ' WHERE ' . $where
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
    api_require_admin();
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
