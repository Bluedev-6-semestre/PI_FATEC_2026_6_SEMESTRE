<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

if ($usuario['tipo'] === 'ADM') {
    $stmt = $pdo->query('SELECT id, nome FROM maquinas ORDER BY nome');
} else {
    $stmt = $pdo->prepare('SELECT id, nome FROM maquinas WHERE usuario_id = :usuario_id ORDER BY nome');
    $stmt->execute([':usuario_id' => $usuario['id']]);
}

api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
