<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios_regras.php';
$auth = auth_require_admin();
auth_require_post();
auth_csrf_check();

$ids = isset($_POST['usuarios']) && is_array($_POST['usuarios'])
    ? array_values(array_filter(array_map('intval', $_POST['usuarios']), fn($v) => $v > 0))
    : [];
if (!$ids) {
    $_SESSION['flash_usuarios'] = ['tipo' => 'erro', 'texto' => 'Nenhum usuário selecionado para remoção.'];
    header('Location: usuarios.php');
    exit();
}

$removidos = 0;
$erros = [];
foreach ($ids as $id) {
    if ($motivo = usuarios_motivo_bloqueio_exclusao($pdo, $auth, $id)) {
        $erros[] = $motivo;
        continue;
    }
    if ($erro = usuarios_excluir($pdo, $id)) {
        $erros[] = $erro;
        continue;
    }
    $removidos++;
}

$texto = $removidos . ' usuário(s) removido(s).' . ($erros ? ' ' . implode(' ', $erros) : '');
$_SESSION['flash_usuarios'] = ['tipo' => $erros ? 'erro' : 'ok', 'texto' => $texto];
header('Location: usuarios.php');
exit();
