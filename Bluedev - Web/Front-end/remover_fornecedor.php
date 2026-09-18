<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
if ($tipo_usuario !== 'ADM') {
    die("Acesso negado.");
}

if (empty($_POST['fornecedores']) || !is_array($_POST['fornecedores'])) {
    die("Nenhum fornecedor selecionado para remoção.");
}

$fornecedores = $_POST['fornecedores'];

try {
    $placeholders = implode(',', array_fill(0, count($fornecedores), '?'));
    $stmt = $pdo->prepare("DELETE FROM fornecedores WHERE id IN ($placeholders)");
    $stmt->execute($fornecedores);

    header('Location: fornecedores.php');
    exit();
} catch (PDOException $e) {
    die("Erro ao remover fornecedores: " . $e->getMessage());
}
?>
