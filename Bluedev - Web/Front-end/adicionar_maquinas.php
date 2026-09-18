<?php
session_start();
require 'bd.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header('Location: login.php');
    exit();
}

$id = $_GET['id'] ?? null;
$modo = $id ? 'editar' : 'adicionar';

$dados = [
    'nome' => '',
    'codigo' => '',
    'modelo' => '',
    'fabricante' => '',
    'data_aquisicao' => '',
    'status_maquina' => '',
    'descricao' => ''
];

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM maquinas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            die("Máquina não encontrada.");
        }
    } catch (PDOException $e) {
        die("Erro ao buscar máquina: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $fabricante = trim($_POST['fabricante'] ?? '');
    $data_aquisicao = trim($_POST['data_aquisicao'] ?? '');
    $status_maquina = trim($_POST['status_maquina'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE maquinas 
                                   SET nome = :nome, codigo = :codigo, modelo = :modelo, fabricante = :fabricante, 
                                       data_aquisicao = :data_aquisicao, status_maquina = :status_maquina, descricao = :descricao 
                                   WHERE id = :id");
            $stmt->execute([
                ':nome' => $nome,
                ':codigo' => $codigo,
                ':modelo' => $modelo,
                ':fabricante' => $fabricante,
                ':data_aquisicao' => $data_aquisicao,
                ':status_maquina' => $status_maquina,
                ':descricao' => $descricao,
                ':id' => $id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO maquinas (usuario_id, nome, codigo, modelo, fabricante, data_aquisicao, status_maquina, descricao) 
                                   VALUES (:usuario_id, :nome, :codigo, :modelo, :fabricante, :data_aquisicao, :status_maquina, :descricao)");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':nome' => $nome,
                ':codigo' => $codigo,
                ':modelo' => $modelo,
                ':fabricante' => $fabricante,
                ':data_aquisicao' => $data_aquisicao,
                ':status_maquina' => $status_maquina,
                ':descricao' => $descricao
            ]);
        }

        header('Location: maquinas.php');
        exit();
    } catch (PDOException $e) {
        die("Erro ao salvar máquina: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $modo === 'editar' ? 'Editar' : 'Adicionar' ?> Máquina</title>
<link rel="stylesheet" href="style/style_base.css">
</head>
<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="index.php" class="logout-icon">
        <img src="../Imagens/icone_sair.png" alt="Sair">
    </a>
    <a href="maquinas.php" class="voltar-icon">
        <img src="../Imagens/voltar.png" alt="Voltar">
    </a>
</div>

<div class="container">
    <aside class="sidebar">
        <ul>
            <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
            <li><a href="fornecedores.php"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
            <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
            <li><a href="maquinas.php" class="ativo"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2><?= strtoupper($modo) ?> MÁQUINA</h2>
        </div>

        <form method="POST" class="form-fornecedor">
            <label>Nome</label>
            <input type="text" name="nome" required value="<?= htmlspecialchars($dados['nome']) ?>">

            <label>Código</label>
            <input type="text" name="codigo" required value="<?= htmlspecialchars($dados['codigo']) ?>">

            <label>Modelo</label>
            <input type="text" name="modelo" value="<?= htmlspecialchars($dados['modelo']) ?>">

            <label>Fabricante</label>
            <input type="text" name="fabricante" value="<?= htmlspecialchars($dados['fabricante']) ?>">

            <label>Data de Aquisição</label>
            <input type="date" name="data_aquisicao" value="<?= htmlspecialchars($dados['data_aquisicao']) ?>">

            <label>Status da Máquina</label>
            <select name="status_maquina" required>
                <option value="">Selecione</option>
                <option value="ativa" <?= $dados['status_maquina']=='ativa'?'selected':'' ?>>Ativa</option>
                <option value="inativa" <?= $dados['status_maquina']=='inativa'?'selected':'' ?>>Inativa</option>
                <option value="em manutenção" <?= $dados['status_maquina']=='em manutenção'?'selected':'' ?>>Em manutenção</option>
            </select>

            <label>Descrição</label>
            <textarea name="descricao" rows="4"><?= htmlspecialchars($dados['descricao']) ?></textarea>

            <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
        </form>
    </main>
</div>
</body>
</html>
