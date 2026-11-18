<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$data = api_read_json();
$usuarioValue = isset($data['usuario']) ? $data['usuario'] : '';
$usuario = is_string($usuarioValue) ? trim($usuarioValue) : '';
$senha = isset($data['senha']) && is_string($data['senha']) ? $data['senha'] : '';

// Autenticação real: usuário e senha (hash) vêm da tabela usuarios.
$row = auth_autenticar($usuario, $senha);
if ($row === null) {
    api_respond(['erro' => 'Usuário ou senha inválidos.'], 401);
}

$tipo = auth_iniciar_sessao($row);
$user = auth_user();

api_respond([
    'usuario' => $row['usuario'],
    'usuario_id' => (int) $row['id'],
    'tipo_usuario' => $tipo,
    'nome' => $user['nome'],
    'permissoes' => $user['permissoes']
]);
