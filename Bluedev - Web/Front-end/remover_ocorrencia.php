<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!is_admin()) {
    http_response_code(403);
    exit('Somente administradores podem excluir ocorrências.');
}
$ids = $_POST['ocorrencias'] ?? [];
if (!is_array($ids) || !$ids) {
    api_flash_error('Selecione pelo menos uma ocorrência para remover.');
    header('Location: ocorrencias.php');
    exit();
}
try {
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id > 0) api_request('DELETE', '/api/ocorrencias/' . $id);
    }
} catch (Throwable $exception) {
    api_flash_error(api_user_message($exception));
}
header('Location: ocorrencias.php');
exit();
