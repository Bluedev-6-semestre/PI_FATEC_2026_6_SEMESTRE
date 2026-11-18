<?php
require_once __DIR__ . '/auth.php';
auth_require_admin();
auth_require_post();

if (empty($_POST['fornecedores']) || !is_array($_POST['fornecedores'])) {
    die("Nenhum fornecedor selecionado para remoção.");
}

$fornecedores = array_values(array_filter(array_map('intval', $_POST['fornecedores']), fn($v) => $v > 0));
if (!$fornecedores) {
    die("Nenhum fornecedor selecionado para remoção.");
}

try {
    $placeholders = implode(',', array_fill(0, count($fornecedores), '?'));
    $stmt = $pdo->prepare("DELETE FROM fornecedores WHERE id IN ($placeholders)");
    $stmt->execute($fornecedores);

    header('Location: fornecedores.php');
    exit();
} catch (PDOException $e) {
    die("Erro ao remover fornecedores: " . $e->getMessage());
}
