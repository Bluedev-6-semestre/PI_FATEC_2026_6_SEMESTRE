<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$usuario_id = current_user_id();
$tipo_usuario = current_user_type();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Inicial</title>
    <link rel="stylesheet" href="style/inicial.css">
</head>
<body>
    <div class="navbar">
    <a href="logout.php" class="logout-icon">
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
