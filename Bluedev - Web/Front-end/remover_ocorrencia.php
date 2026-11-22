<?php
require_once __DIR__ . '/auth.php';
auth_require_admin();
auth_require_post();

if (empty($_POST['ocorrencias']) || !is_array($_POST['ocorrencias'])) {
    die("ID da ocorrência não informado.");
}

$ids = array_values(array_filter(array_map('intval', $_POST['ocorrencias']), fn($v) => $v > 0));
if (!$ids) {
    die("ID da ocorrência não informado.");
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM ocorrencias WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    header("Location: ocorrencias.php");
    exit;
} catch (PDOException $e) {
    die("Erro ao remover ocorrências: " . $e->getMessage());
}
