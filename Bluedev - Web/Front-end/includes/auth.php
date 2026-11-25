<?php
require_once __DIR__ . '/api_client.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['tipo_usuario'])) {
        header('Location: index.php');
        exit();
    }
}

function is_admin(): bool
{
    return ($_SESSION['tipo_usuario'] ?? '') === 'ADM';
}

function current_user_id(): int
{
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

function current_user_type(): string
{
    return (string) ($_SESSION['tipo_usuario'] ?? '');
}

function normalize_api_role(string $role): string
{
    return strtoupper($role) === 'ADMIN' ? 'ADM' : strtoupper($role);
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}
