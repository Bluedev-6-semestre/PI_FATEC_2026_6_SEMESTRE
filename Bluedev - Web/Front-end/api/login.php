<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$data = api_read_json();
$usuarioValue = isset($data['usuario']) ? $data['usuario'] : '';
$usuario = is_string($usuarioValue) ? strtoupper(trim($usuarioValue)) : '';
$senha = isset($data['senha']) && is_string($data['senha']) ? $data['senha'] : '';
$usuarios = [
    'ADM' => ['senha' => '1234', 'id' => 1],
    'FUNC' => ['senha' => '123', 'id' => 2]
];

if (!isset($usuarios[$usuario]) || $usuarios[$usuario]['senha'] !== $senha) {
    api_respond(['erro' => 'Usuário ou senha inválidos.'], 401);
}

session_start();
session_regenerate_id(true);
$_SESSION['loggedin'] = true;
$_SESSION['usuario'] = $usuario;
$_SESSION['usuario_id'] = $usuarios[$usuario]['id'];
$_SESSION['tipo_usuario'] = $usuario;

api_respond([
    'usuario' => $usuario,
    'usuario_id' => $usuarios[$usuario]['id'],
    'tipo_usuario' => $usuario
]);
