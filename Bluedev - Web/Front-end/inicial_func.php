<?php
require_once __DIR__ . '/auth.php';
$auth = auth_require_login();
if ($auth['tipo'] === 'ADM') {
    header("Location: inicial_adm.php");
    exit();
}
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
        <?php if (auth_can('ver_cadastros', $auth)): ?>
        <li>
            <a href="fornecedores.php">
                <img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores
            </a>
        </li>
        <li>
            <a href="funcionarios.php">
                <img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários
            </a>
        </li>
        <li>
            <a href="maquinas.php">
                <img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas
            </a>
        </li>
        <?php endif; ?>
        <?php if (auth_can('ver_relatorios', $auth)): ?>
        <li>
            <a href="relatorios.php">
                <img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
        <main class="main-content">
            <h1>SEJA BEM-VINDO!</h1>
            <img src="../Imagens/logo_cliente.jpeg" alt="Logo Arearty" class="logo">
        </main>
    </div>
</body>
</html>
