<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$ids = $_POST['usuarios'] ?? [];
if (!is_array($ids) || !$ids) {
    api_flash_error('Selecione pelo menos um usuário para remover.');
    header('Location: usuarios.php');
    exit();
}
try {
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id > 0 && $id !== current_user_id()) api_request('DELETE', '/api/usuarios/' . $id);
    }
} catch (Throwable $exception) {
    api_flash_error(api_user_message($exception));
}
header('Location: usuarios.php');
exit();
