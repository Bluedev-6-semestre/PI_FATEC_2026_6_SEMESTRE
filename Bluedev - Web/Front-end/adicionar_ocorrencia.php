<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$usuario_id = current_user_id();
$tipo_usuario = current_user_type();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$modo = $id ? 'editar' : 'adicionar';
$dados = ['usuarioId' => $usuario_id, 'maquinaId' => null, 'titulo' => '', 'tipo' => '', 'status' => '', 'descricao' => '', 'imagem' => '', 'dataCriacao' => ''];
$maquinas = [];
$erro = null;

try {
    $resumo = api_request('GET', '/api/maquinas');

    $maquinas = is_array($resumo) ? $resumo : [];

    if ($id) {
        $dados = api_request('GET', '/api/ocorrencias/' . $id);

        if ($tipo_usuario !== 'ADM' && (int) ($dados['usuarioId'] ?? 0) !== $usuario_id) {
            http_response_code(403);
            exit('Você não tem permissão para editar esta ocorrência.');
        }
    }
} catch (Throwable $exception) {
    http_response_code($exception instanceof ApiException && $exception->getHttpStatus() === 404 ? 404 : 502);
    exit(htmlspecialchars(api_user_message($exception), ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagem = (string) ($dados['imagem'] ?? '');
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK || $_FILES['imagem']['size'] > 5 * 1024 * 1024) {
            $erro = 'Não foi possível enviar a imagem. O limite é de 5 MB.';
        } elseif (@getimagesize($_FILES['imagem']['tmp_name']) === false) {
            $erro = 'O arquivo enviado não é uma imagem válida.';
        } else {
            $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extensao, $permitidas, true)) {
                $erro = 'Formato de imagem não permitido.';
            } else {
                $nomeArquivo = bin2hex(random_bytes(12)) . '.' . $extensao;
                $diretorioUploads = __DIR__ . '/uploads/';
                if (!is_dir($diretorioUploads)) mkdir($diretorioUploads, 0755, true);
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $diretorioUploads . $nomeArquivo)) {
                    $imagem = 'uploads/' . $nomeArquivo;
                } else {
                    $erro = 'Não foi possível salvar a imagem enviada.';
                }
            }
        }
    }

    $payload = [
        'usuarioId' => (int) ($dados['usuarioId'] ?? $usuario_id),
        'maquinaId' => ($_POST['maquina_id'] ?? '') !== '' ? (int) $_POST['maquina_id'] : null,
        'titulo' => trim($_POST['titulo'] ?? ''),
        'tipo' => trim($_POST['tipo'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
        'descricao' => trim($_POST['descricao'] ?? ''),
        'imagem' => $imagem !== '' ? $imagem : null,
    ];

    if ($erro === null) {
        try {
            if ($id) api_request('PUT', '/api/ocorrencias/' . $id, $payload);
            else {
                $payload['usuarioId'] = $usuario_id;
                api_request('POST', '/api/ocorrencias', $payload);
            }
            header('Location: ocorrencias.php');
            exit();
        } catch (Throwable $exception) {
            $erro = api_user_message($exception);
        }
    }
    $dados = array_merge($dados, $payload);
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
        <a href="logout.php" class="logout-icon">
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
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png"> Máquinas</a></li>
                <li><a href="usuarios.php"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png"> Relatórios</a></li>
        </ul>
    </aside>


        <main class="main-content">
            <div class="titulo-pagina">
                <h2><?= strtoupper($modo) ?> OCORRÊNCIA</h2>
            </div>

            <form method="POST" enctype="multipart/form-data" class="form-ocorrencia">
                <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
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
                        <option value="<?= $m['id'] ?>" <?= $dados['maquinaId'] == $m['id'] ? 'selected' : '' ?>>
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
