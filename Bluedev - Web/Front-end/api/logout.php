<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

api_require_login();
$_SESSION = [];
session_destroy();

api_respond(['mensagem' => 'Sessão encerrada.']);
