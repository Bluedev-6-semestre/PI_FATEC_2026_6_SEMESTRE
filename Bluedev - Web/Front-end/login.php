<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
$senha   = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

// Autenticação pelo user-service (via gateway).
$dados = auth_autenticar($usuario, $senha);

if ($dados === null) {
    echo "Usuário ou senha inválidos.";
    exit();
}

$tipo = auth_iniciar_sessao($dados);
header("Location: " . ($tipo === 'ADM' ? 'inicial_adm.php' : 'inicial_func.php'));
exit();
