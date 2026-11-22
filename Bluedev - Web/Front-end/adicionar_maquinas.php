<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$tipo_usuario = current_user_type();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$modo = $id ? 'editar' : 'adicionar';
$dados = [
    'usuarioId' => current_user_id(), 'nome' => '', 'codigo' => '', 'modelo' => '',
    'fabricante' => '', 'numeroSerie' => '', 'dataAquisicao' => '',
    'localizacao' => '', 'statusMaquina' => '', 'descricao' => ''
];
$erro = null;

if ($id) {
    try {
        $dados = api_request('GET', '/api/maquinas/' . $id);
        if (!is_admin() && (int) ($dados['usuarioId'] ?? 0) !== current_user_id()) {
            http_response_code(403);
            exit('Você não tem permissão para editar esta máquina.');
        }
    } catch (Throwable $exception) {
        http_response_code($exception instanceof ApiException && $exception->getHttpStatus() === 404 ? 404 : 502);
        exit(htmlspecialchars(api_user_message($exception), ENT_QUOTES, 'UTF-8'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'usuarioId' => (int) ($dados['usuarioId'] ?? current_user_id()),
        'nome' => trim($_POST['nome'] ?? ''),
        'codigo' => trim($_POST['codigo'] ?? ''),
        'modelo' => trim($_POST['modelo'] ?? ''),
        'fabricante' => trim($_POST['fabricante'] ?? ''),
        'numeroSerie' => trim($_POST['numero_serie'] ?? ''),
        'dataAquisicao' => trim($_POST['data_aquisicao'] ?? ''),
        'localizacao' => trim($_POST['localizacao'] ?? ''),
        'statusMaquina' => trim($_POST['status_maquina'] ?? ''),
        'descricao' => trim($_POST['descricao'] ?? '')
    ];

    try {
        if ($id) {
            api_request('PUT', '/api/maquinas/' . $id, $payload);
        } else {
            $payload['usuarioId'] = current_user_id();
            api_request('POST', '/api/maquinas', $payload);
        }
        header('Location: maquinas.php');
        exit();
    } catch (Throwable $exception) {
        $erro = api_user_message($exception);
        $dados = array_merge($dados, $payload);
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
    <a href="logout.php" class="logout-icon">
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
            <li><a href="maquinas.php" class="ativo"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <?php if ($tipo_usuario === 'ADM'): ?><li><a href="usuarios.php"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li><?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2><?= strtoupper($modo) ?> MÁQUINA</h2>
        </div>

        <form method="POST" class="formulario-dados">
            <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <label>Nome</label>
            <input type="text" name="nome" required value="<?= htmlspecialchars($dados['nome']) ?>">

            <label>Código</label>
            <input type="text" name="codigo" required value="<?= htmlspecialchars($dados['codigo']) ?>">

            <label>Modelo</label>
            <input type="text" name="modelo" required value="<?= htmlspecialchars($dados['modelo']) ?>">

            <label>Fabricante</label>
            <input type="text" name="fabricante" required value="<?= htmlspecialchars($dados['fabricante']) ?>">

            <label>Número de Série</label>
            <input type="text" name="numero_serie" required value="<?= htmlspecialchars($dados['numeroSerie'] ?? '') ?>">

            <label>Data de Aquisição</label>
            <input type="date" name="data_aquisicao" required value="<?= htmlspecialchars($dados['dataAquisicao']) ?>">

            <label>Localização</label>
            <input type="text" name="localizacao" required value="<?= htmlspecialchars($dados['localizacao'] ?? '') ?>">

            <label>Status da Máquina</label>
            <select name="status_maquina" required>
                <option value="">Selecione</option>
                <option value="ativa" <?= $dados['statusMaquina']=='ativa'?'selected':'' ?>>Ativa</option>
                <option value="inativa" <?= $dados['statusMaquina']=='inativa'?'selected':'' ?>>Inativa</option>
                <option value="em manutenção" <?= $dados['statusMaquina']=='em manutenção'?'selected':'' ?>>Em manutenção</option>
            </select>

            <label>Descrição</label>
            <textarea name="descricao" rows="4"><?= htmlspecialchars($dados['descricao']) ?></textarea>

            <button type="submit"><?= $modo === 'editar' ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR' ?></button>
        </form>
    </main>
</div>
</body>
</html>
