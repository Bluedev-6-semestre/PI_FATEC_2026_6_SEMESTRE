<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
$usuario_id   = $_SESSION['usuario_id'] ?? 0;

if ($tipo_usuario !== 'ADM') {
    die("Acesso negado.");
}

$id = $_GET['id'] ?? null;
$modo = $id ? 'editar' : 'adicionar';

$dados = [
    'nome' => '',
    'idade' => '',
    'funcao' => ''
];

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            die("Funcionário não encontrado.");
        }
    } catch (PDOException $e) {
        die("Erro ao buscar funcionário: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $idade = trim($_POST['idade'] ?? '');
    $funcao = trim($_POST['funcao'] ?? '');

    if (empty($nome) || empty($idade) || empty($funcao)) {
        $erro = "Todos os campos são obrigatórios.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE funcionarios 
                                       SET nome = :nome, idade = :idade, funcao = :funcao 
                                       WHERE id = :id");
                $stmt->execute([
                    ':nome' => $nome,
                    ':idade' => $idade,
                    ':funcao' => $funcao,
                    ':id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO funcionarios (usuario_id, nome, idade, funcao) 
                                       VALUES (:usuario_id, :nome, :idade, :funcao)");
                $stmt->execute([
                    ':usuario_id' => $usuario_id,
                    ':nome' => $nome,
                    ':idade' => $idade,
                    ':funcao' => $funcao
                ]);
            }

            header('Location: funcionarios.php');
            exit();
        } catch (PDOException $e) {
            $erro = "Erro ao salvar funcionário: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ucfirst($modo) ?> Funcionário</title>
<link rel="stylesheet" href="style/style_base.css">
</head>
<body>
    <div class="navbar">
        <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
        <a href="index.php" class="logout-icon">
            <img src="../Imagens/icone_sair.png" alt="Sair">
        </a>
        <a href="funcionarios.php" class="voltar-icon">
            <img src="../Imagens/voltar.png" alt="Voltar">
        </a>
    </div>

    <div class="container">
        <aside class="sidebar">
            <ul>
                <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
                <li><a href="fornecedores.php"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
                <li><a href="funcionarios.php" class="ativo"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
                <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="titulo-pagina">
                <h2><?= strtoupper($modo) ?> FUNCIONÁRIO</h2>
            </div>

            <?php if (!empty($erro)): ?>
                <p class="erro"><?= htmlspecialchars($erro) ?></p>
            <?php endif; ?>

            <form method="POST" class="form-funcionario">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" placeholder="Digite o nome" required
                       value="<?= htmlspecialchars($dados['nome']) ?>">

                <label for="idade">Idade</label>
                <input type="number" id="idade" name="idade" placeholder="Digite a idade" required min="0"
                       value="<?= htmlspecialchars($dados['idade']) ?>">

                <label for="funcao">Função</label>
                <input type="text" id="funcao" name="funcao" placeholder="Digite a função" required
                       value="<?= htmlspecialchars($dados['funcao']) ?>">

                <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
            </form>
        </main>
    </div>
</body>
</html>
