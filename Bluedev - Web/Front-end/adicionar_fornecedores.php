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
    'cnpj' => '',
    'categoria' => '',
    'telefone' => '',
    'endereco' => ''
];

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuario_id
        ]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            die("Fornecedor não encontrado.");
        }
    } catch (PDOException $e) {
        die("Erro ao buscar fornecedor: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE fornecedores 
                                   SET nome = :nome, cnpj = :cnpj, categoria = :categoria, telefone = :telefone, endereco = :endereco
                                   WHERE id = :id AND usuario_id = :usuario_id");
            $stmt->execute([
                ':nome' => $nome,
                ':cnpj' => $cnpj,
                ':categoria' => $categoria,
                ':telefone' => $telefone,
                ':endereco' => $endereco,
                ':id' => $id,
                ':usuario_id' => $usuario_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO fornecedores (usuario_id, nome, cnpj, categoria, telefone, endereco) 
                                   VALUES (:usuario_id, :nome, :cnpj, :categoria, :telefone, :endereco)");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':nome' => $nome,
                ':cnpj' => $cnpj,
                ':categoria' => $categoria,
                ':telefone' => $telefone,
                ':endereco' => $endereco
            ]);
        }

        header('Location: fornecedores.php');
        exit();
    } catch (PDOException $e) {
        die("Erro ao salvar fornecedor: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo === 'editar' ? 'Editar' : 'Adicionar' ?> Fornecedor</title>
    <link rel="stylesheet" href="style/style_base.css">
</head>
<body>
    <div class="navbar">
        <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
        <a href="index.php" class="logout-icon">
            <img src="../Imagens/icone_sair.png" alt="Sair">
        </a>
        <a href="fornecedores.php" class="voltar-icon">
            <img src="../Imagens/voltar.png" alt="Voltar">
        </a>
    </div>

    <div class="container">
        <aside class="sidebar">
            <ul>
                <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
                <li><a href="fornecedores.php" class="ativo"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
                <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
                <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="titulo-pagina">
                <h2><?= strtoupper($modo) ?> FORNECEDOR</h2>
            </div>

            <form method="POST" class="form-fornecedor">
                <label for="cnpj">CNPJ</label>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0001-00" required
                           value="<?= htmlspecialchars($dados['cnpj']) ?>" style="flex:1;">
                    <button type="button" onclick="buscarCNPJ()" style="padding:6px 10px;">Buscar Dados</button>
                </div>

                <label for="nome">Nome da empresa</label>
                <input type="text" id="nome" name="nome" placeholder="Digite o nome da empresa" required
                       value="<?= htmlspecialchars($dados['nome']) ?>">

                <label for="categoria">Categoria</label>
                <input type="text" id="categoria" name="categoria" placeholder="Digite a categoria" required
                       value="<?= htmlspecialchars($dados['categoria']) ?>">

                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" required
                       value="<?= htmlspecialchars($dados['telefone']) ?>">

                <label for="endereco">Endereço (CEP)</label>
                <input type="text" id="endereco" name="endereco" placeholder="Digite o endereço ou CEP" required
                       value="<?= htmlspecialchars($dados['endereco']) ?>">

                <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
            </form>
        </main>
    </div>

    <script>
    async function buscarCNPJ() {
        const cnpj = document.getElementById('cnpj').value.replace(/\D/g, '');
        if (cnpj.length !== 14) {
            alert("Digite um CNPJ válido com 14 números.");
            return;
        }

        try {
            const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`);
            if (!response.ok) throw new Error('CNPJ não encontrado');

            const data = await response.json();
            console.log(data);

            document.getElementById('nome').value = data.razao_social || '';
            document.getElementById('telefone').value = data.ddd_telefone_1 || '';
            document.getElementById('endereco').value = 
                `${data.logradouro || ''}, ${data.numero || ''} - ${data.bairro || ''}, ${data.municipio || ''} - ${data.uf}`;

        } catch (error) {
            console.error(error);
            alert("Erro ao buscar dados do CNPJ. Verifique o número e tente novamente.");
        }
    }

    document.getElementById('cnpj').addEventListener('blur', buscarCNPJ);
    </script>
</body>
</html>
