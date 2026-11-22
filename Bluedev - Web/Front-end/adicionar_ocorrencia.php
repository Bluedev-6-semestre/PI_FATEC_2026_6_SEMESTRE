<?php
require_once __DIR__ . '/auth.php';
$auth = auth_require_login();
$usuario_id = $auth['id'];
$tipo_usuario = $auth['tipo'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$modo = $id ? 'editar' : 'adicionar';

$dados = [
    'titulo' => '',
    'tipo' => '',
    'status' => '',
    'descricao' => '',
    'imagem' => '',
    'maquina_id' => ''
];

// Registrar exige permissão própria; atualizar: admin (qualquer uma) ou
// funcionário (somente as próprias e com permissão).
if ($id) {
    if (!auth_is_admin($auth)) {
        auth_require_permission('gerenciar_ocorrencias');
    }
} else {
    auth_require_permission('registrar_ocorrencias');
}

try {
    // Máquinas são cadastradas pelo administrador; todos podem vinculá-las.
    $stmtMaquinas = $pdo->query("SELECT id, nome FROM maquinas ORDER BY nome");
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
        if (!auth_is_admin($auth) && (int) $dados['usuario_id'] !== $usuario_id) {
            auth_negar('Você só pode alterar as suas próprias ocorrências.');
        }
    } catch (PDOException $e) {
        die("Erro ao buscar ocorrência: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = is_string($_POST['titulo'] ?? null) ? trim($_POST['titulo']) : '';
    $tipo = $_POST['tipo'] ?? '';
    $status = $_POST['status'] ?? '';
    $descricao = is_string($_POST['descricao'] ?? null) ? $_POST['descricao'] : '';
    $maquina_id = !empty($_POST['maquina_id']) ? (int) $_POST['maquina_id'] : null;
    $imagem = $dados['imagem'];

    if ($titulo === '' || mb_strlen($titulo) > 100) {
        die("Informe um título com até 100 caracteres.");
    }
    if (!in_array($tipo, ['Preventiva', 'Corretiva'], true)) {
        die("Tipo de manutenção inválido.");
    }
    if (!in_array($status, ['Pendente', 'Em andamento', 'Concluída', 'Concluído'], true)) {
        die("Status inválido.");
    }
    if ($maquina_id !== null) {
        $chk = $pdo->prepare("SELECT id FROM maquinas WHERE id = :id");
        $chk->execute([':id' => $maquina_id]);
        if (!$chk->fetch()) {
            die("Máquina não encontrada.");
        }
    }

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Aceita somente imagens reais (JPEG, PNG ou WebP) de até 5 MB.
        $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK || $_FILES['imagem']['size'] > 5 * 1024 * 1024) {
            die("Erro ao enviar a imagem (máximo de 5 MB).");
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['imagem']['tmp_name']);
        if (!isset($permitidos[$mime])) {
            die("Formato de imagem não permitido.");
        }
        $nomeArquivo = 'img_' . bin2hex(random_bytes(12)) . '.' . $permitidos[$mime];

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
<link rel="stylesheet" href="style/admin.css">
</head>
<body>
    <div class="navbar">
        <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
        <a href="logout.php" class="logout-icon">
            <img src="../Imagens/icone_sair.png" alt="Sair">
        </a>
        <a href="ocorrencias.php" class="voltar-icon">
            <img src="../Imagens/voltar.png" alt="Voltar">
        </a>
    </div>

    <div class="container">
    <?php $menuAtivo = 'ocorrencias'; include 'menu.php'; ?>


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
