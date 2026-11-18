<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$ids = $_POST['maquinas'] ?? [];
if (!is_array($ids) || !$ids) {
    api_flash_error('Selecione pelo menos uma máquina para remover.');
    header('Location: maquinas.php');
    exit();
}

try {
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id > 0) api_request('DELETE', '/api/maquinas/' . $id);
    }
} catch (Throwable $exception) {
    api_flash_error(api_user_message($exception));
}
header('Location: maquinas.php');
exit();
