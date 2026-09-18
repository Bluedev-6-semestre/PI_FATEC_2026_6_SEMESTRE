<?php
session_start();
require 'bd.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_POST['maquinas']) || empty($_POST['maquinas'])) {
    die("Nenhuma máquina selecionada para remoção.");
}

$ids = $_POST['maquinas'];
$ids = array_map('intval', $ids); 

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("DELETE FROM maquinas WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    header("Location: maquinas.php");
    exit();

} catch (PDOException $e) {
    die("Erro ao remover máquinas: " . $e->getMessage());
}
?>
