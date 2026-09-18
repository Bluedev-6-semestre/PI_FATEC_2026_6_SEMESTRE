<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
require 'bd.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Inicial Funcionários</title>
    <link rel="stylesheet" href="style/inicial.css">
</head>
<body>
    <div class="navbar">
    <a href="index.php" class="logout-icon">
        <img src="../Imagens/icone_sair.png" alt="Sair">
    </a>
</div>
    <div class="container">
      <aside class="sidebar">
    <ul>
        <li>
            <a href="ocorrencias.php">
                <img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências
            </a>
        </li>
        <li>
            <a href="relatorios.php">
                <img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios
            </a>
        </li>
    </ul>
</aside>
        <main class="main-content">
            <h1>SEJA BEM-VINDO!</h1>
            <img src="../Imagens/logo_cliente.jpeg" alt="Logo Arearty" class="logo">
        </main>
    </div>
</body>
</html>
