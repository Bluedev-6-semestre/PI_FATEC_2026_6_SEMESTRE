<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

if ($method === 'GET') {
    if ($usuario['tipo'] === 'ADM') {
        $stmt = $pdo->query(
            'SELECT id, nome, idade, funcao, usuario_id, created_at '
            . 'FROM funcionarios ORDER BY id DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, nome, idade, funcao, usuario_id, created_at '
            . 'FROM funcionarios WHERE usuario_id = :usuario_id ORDER BY id DESC'
        );
        $stmt->execute([':usuario_id' => $usuario['id']]);
    }
    api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($usuario['tipo'] !== 'ADM') {
    api_respond(['erro' => 'Somente administradores podem alterar funcionários.'], 403);
}

if ($method === 'POST' || $method === 'PUT') {
    $body = api_read_json();
    $nomeValue = isset($body['nome']) ? $body['nome'] : '';
    $funcaoValue = isset($body['funcao']) ? $body['funcao'] : '';
    $idadeValue = isset($body['idade']) ? $body['idade'] : null;
    $nome = is_string($nomeValue) ? trim($nomeValue) : '';
    $funcao = is_string($funcaoValue) ? trim($funcaoValue) : '';

    if ($nome === '' || !preg_match('/^.{1,255}$/us', $nome)) {
        api_respond(['erro' => 'Informe o nome do funcionário.'], 422);
    }
    if ($funcao === '' || !preg_match('/^.{1,100}$/us', $funcao)) {
        api_respond(['erro' => 'Informe a função do funcionário.'], 422);
    }
    if (is_int($idadeValue)) {
        $idade = $idadeValue;
    } elseif (is_string($idadeValue) && preg_match('/^\d+$/', $idadeValue)) {
        $idade = filter_var($idadeValue, FILTER_VALIDATE_INT);
    } else {
        $idade = false;
    }
    if ($idade === false || $idade < 0 || $idade > 2147483647) {
        api_respond(['erro' => 'Informe uma idade válida (mínimo 0).'], 422);
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO funcionarios (usuario_id, nome, idade, funcao) '
            . 'VALUES (:usuario_id, :nome, :idade, :funcao)'
        );
        $stmt->execute([
            ':usuario_id' => $usuario['id'],
            ':nome' => $nome,
            ':idade' => $idade,
            ':funcao' => $funcao
        ]);
        api_respond(['id' => (int) $pdo->lastInsertId()], 201);
    }

    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de funcionário inválido.'], 400);
    }
    $check = $pdo->prepare('SELECT id FROM funcionarios WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        api_respond(['erro' => 'Funcionário não encontrado.'], 404);
    }
    $stmt = $pdo->prepare(
        'UPDATE funcionarios SET nome = :nome, idade = :idade, funcao = :funcao '
        . 'WHERE id = :id'
    );
    $stmt->execute([
        ':nome' => $nome,
        ':idade' => $idade,
        ':funcao' => $funcao,
        ':id' => $id
    ]);
    api_respond(['mensagem' => 'Funcionário atualizado.']);
}

if ($method === 'DELETE') {
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de funcionário inválido.'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM funcionarios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        api_respond(['erro' => 'Funcionário não encontrado.'], 404);
    }
    api_respond(['mensagem' => 'Funcionário removido.']);
}

api_respond(['erro' => 'Método não permitido.'], 405);
