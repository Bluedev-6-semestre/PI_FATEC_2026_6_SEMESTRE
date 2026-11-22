<?php
require_once __DIR__ . '/auth.php';
auth_require_admin();
auth_require_post();

if (empty($_POST['funcionarios']) || !is_array($_POST['funcionarios'])) {
    die("Nenhum funcionário selecionado para remoção.");
}

$funcionarios = array_values(array_filter(array_map('intval', $_POST['funcionarios']), fn($v) => $v > 0));
if (!$funcionarios) {
    die("Nenhum funcionário selecionado para remoção.");
}

try {
    $placeholders = implode(',', array_fill(0, count($funcionarios), '?'));
    $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id IN ($placeholders)");
    $stmt->execute($funcionarios);

    header('Location: funcionarios.php');
    exit();
} catch (PDOException $e) {
    die("Erro ao remover funcionários: " . $e->getMessage());
}
