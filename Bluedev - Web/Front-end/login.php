<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$usuario = trim($_POST['usuario'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($usuario === '' || $senha === '') {
    api_flash_error('Informe usuário e senha.');
    header('Location: index.php');
    exit();
}

try {
    $resposta = api_request('POST', '/api/usuarios/login', [
        'usuario' => $usuario,
        'senha' => $senha,
    ]);

    if (!is_array($resposta) || empty($resposta['id']) || empty($resposta['tipoUsuario'])) {
        throw new ApiException('A API retornou dados de autenticação incompletos.');
    }

    session_regenerate_id(true);
    $_SESSION['loggedin'] = true;
    $_SESSION['usuario_id'] = (int) $resposta['id'];
    $_SESSION['usuario'] = (string) ($resposta['usuario'] ?? $usuario);
    $_SESSION['tipo_usuario'] = normalize_api_role((string) $resposta['tipoUsuario']);

    header('Location: ' . (is_admin() ? 'inicial_adm.php' : 'inicial_func.php'));
    exit();
} catch (Throwable $exception) {
    api_flash_error(api_user_message($exception));
    header('Location: index.php');
    exit();
}
