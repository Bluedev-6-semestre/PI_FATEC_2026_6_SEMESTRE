<?php
session_start();
require 'bd.php';

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
$id = $_GET['id'] ?? null;
$modo = $id ? 'editar' : 'adicionar';

$dados = [
    'titulo' => '',
    'tipo' => '',
    'status' => '',
    'descricao' => '',
    'imagem' => '',
    'maquina_id' => ''
];

try {
    if ($tipo_usuario === 'ADM') {
        $stmtMaquinas = $pdo->query("SELECT id, nome FROM maquinas ORDER BY nome");
    } else {
        $stmtMaquinas = $pdo->prepare("SELECT id, nome FROM maquinas WHERE usuario_id = :usuario_id ORDER BY nome");
        $stmtMaquinas->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmtMaquinas->execute();
    }
    $maquinas = $stmtMaquinas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar máquinas: " . $e->getMessage());
}

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ocorrencias WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dados) die("Ocorrência não encontrada.");
    } catch (PDOException $e) {
        die("Erro ao buscar ocorrência: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = $_POST['titulo'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $status = $_POST['status'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $maquina_id = !empty($_POST['maquina_id']) ? $_POST['maquina_id'] : null;
    $imagem = $dados['imagem'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid('img_') . '.' . strtolower($extensao);

        $diretorioUploads = __DIR__ . '/uploads/';
        if (!is_dir($diretorioUploads)) mkdir($diretorioUploads, 0755, true);

        $caminhoDestino = $diretorioUploads . $nomeArquivo;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoDestino)) {
            $imagem = 'uploads/' . $nomeArquivo;
        } else {
            die("Erro ao fazer upload da imagem.");
        }
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE ocorrencias 
                SET maquina_id = :maquina_id,
                    titulo = :titulo,
                    tipo = :tipo,
                    status = :status,
                    descricao = :descricao,
                    imagem = :imagem
                WHERE id = :id
            ");

            $stmt->execute([
                ':maquina_id' => $maquina_id,
                ':titulo' => $titulo,
                ':tipo' => $tipo,
                ':status' => $status,
                ':descricao' => $descricao,
                ':imagem' => $imagem,
                ':id' => $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ocorrencias 
                (maquina_id, titulo, tipo, status, descricao, imagem, usuario_id)
                VALUES (:maquina_id, :titulo, :tipo, :status, :descricao, :imagem, :usuario_id)
            ");

            $stmt->execute([
                ':maquina_id' => $maquina_id,
                ':titulo' => $titulo,
                ':tipo' => $tipo,
                ':status' => $status,
                ':descricao' => $descricao,
                ':imagem' => $imagem,
                ':usuario_id' => $usuario_id
            ]);
        }

        header('Location: ocorrencias.php');
        exit();
    } catch (PDOException $e) {
        die("Erro ao salvar ocorrência: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo === 'editar' ? 'Editar' : 'Adicionar' ?> Ocorrência</title>
    <link rel="stylesheet" href="style/style_base.css">
</head>
<body>
    <div class="navbar">
        <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
        <a href="index.php" class="logout-icon">
            <img src="../Imagens/icone_sair.png" alt="Sair">
        </a>
        <a href="ocorrencias.php" class="voltar-icon">
            <img src="../Imagens/voltar.png" alt="Voltar">
        </a>
    </div>

    <div class="container">
    <aside class="sidebar">
        <ul>
            <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png"> Ocorrências</a></li>
            <?php if ($tipo_usuario === 'ADM'): ?>
                <li><a href="fornecedores.php"><img src="../Imagens/fornecedor_icone.png"> Fornecedores</a></li>
                <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png"> Máquinas</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png"> Relatórios</a></li>
        </ul>
    </aside>


        <main class="main-content">
            <div class="titulo-pagina">
                <h2><?= strtoupper($modo) ?> OCORRÊNCIA</h2>
            </div>

            <form method="POST" enctype="multipart/form-data" class="form-ocorrencia">
                <label for="titulo">Título da ocorrência</label>
                <input type="text" id="titulo" name="titulo" required value="<?= htmlspecialchars($dados['titulo']) ?>">

                <label for="tipo">Tipo de manutenção</label>
                <select id="tipo" name="tipo" required>
                    <option value="" disabled <?= $dados['tipo'] == '' ? 'selected' : '' ?>>Selecione</option>
                    <option value="Preventiva" <?= $dados['tipo'] == 'Preventiva' ? 'selected' : '' ?>>Preventiva</option>
                    <option value="Corretiva" <?= $dados['tipo'] == 'Corretiva' ? 'selected' : '' ?>>Corretiva</option>
                </select>

                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="" disabled <?= $dados['status'] == '' ? 'selected' : '' ?>>Selecione</option>
                    <option value="Pendente" <?= $dados['status'] == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="Em andamento" <?= $dados['status'] == 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                    <option value="Concluída" <?= $dados['status'] == 'Concluída' ? 'selected' : '' ?>>Concluída</option>
                </select>

                <label for="maquina_id">Máquina (opcional)</label>
                <select id="maquina_id" name="maquina_id">
                    <option value="">Nenhuma máquina selecionada</option>
                    <?php foreach ($maquinas as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $dados['maquina_id'] == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" rows="4"><?= htmlspecialchars($dados['descricao']) ?></textarea>

                <label for="imagem">Imagem (opcional)</label>
                <input type="file" id="imagem" name="imagem" accept="image/*">

                <?php if (!empty($dados['imagem'])): ?>
                    <div class="imagem-preview">
                        <p>Imagem atual:</p>
                        <img src="<?= htmlspecialchars($dados['imagem']) ?>" alt="Imagem da ocorrência" style="max-width:200px; border-radius:8px;">
                    </div>
                <?php endif; ?>

                <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
            </form>
        </main>
    </div>
</body>
</html>
