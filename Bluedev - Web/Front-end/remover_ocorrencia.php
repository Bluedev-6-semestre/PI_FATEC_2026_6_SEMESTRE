<?php
session_start();
require 'bd.php';

if (empty($_POST['ocorrencias'])) {
    die("ID da ocorrência não informado.");
}

$ids = array_map('intval', $_POST['ocorrencias']);

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM ocorrencias WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    header("Location: ocorrencias.php");
    exit;
} catch (PDOException $e) {
    die("Erro ao remover ocorrências: " . $e->getMessage());
}
