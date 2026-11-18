<?php
/**
 * Controle centralizado de acesso da aplicação Web.
 * Mantém o visual existente e valida a permissão no servidor.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login()
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['tipo_usuario'])) {
        header('Location: index.php');
        exit();
    }
}

function require_role($role)
{
    require_login();

    if (($_SESSION['tipo_usuario'] ?? '') !== $role) {
        http_response_code(403);
        echo 'Acesso negado. Esta área é restrita ao perfil autorizado.';
        exit();
    }
}

function require_admin()
{
    require_role('ADM');
}
