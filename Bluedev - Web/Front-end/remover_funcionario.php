<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
if ($tipo_usuario !== 'ADM') {
    die("Acesso negado.");
}

if (empty($_POST['funcionarios']) || !is_array($_POST['funcionarios'])) {
    die("Nenhum funcionário selecionado para remoção.");
}

$funcionarios = array_map('intval', $_POST['funcionarios']);

try {
    $placeholders = implode(',', array_fill(0, count($funcionarios), '?'));
    $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id IN ($placeholders)");
    $stmt->execute($funcionarios);

    header('Location: funcionarios.php');
    exit();
} catch (PDOException $e) {
    die("Erro ao remover funcionários: " . $e->getMessage());
}
?>
