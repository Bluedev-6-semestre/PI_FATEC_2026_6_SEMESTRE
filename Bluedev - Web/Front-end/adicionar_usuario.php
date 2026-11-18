<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$modo = $id ? 'editar' : 'adicionar';
$dados = ['usuario' => '', 'tipoUsuario' => 'FUNCIONARIO'];
$erro = null;
if ($id) {
    try {
        $dados = api_request('GET', '/api/usuarios/' . $id);
    } catch (Throwable $exception) {
        http_response_code($exception instanceof ApiException && $exception->getHttpStatus() === 404 ? 404 : 502);
        exit(htmlspecialchars(api_user_message($exception), ENT_QUOTES, 'UTF-8'));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'usuario' => trim($_POST['usuario'] ?? ''),
        'senha' => (string) ($_POST['senha'] ?? ''),
        'tipoUsuario' => $_POST['tipo_usuario'] ?? 'FUNCIONARIO',
    ];
    if ($id && $payload['senha'] === '') $payload['senha'] = null;
    try {
        if ($id) api_request('PUT', '/api/usuarios/' . $id, $payload);
        else api_request('POST', '/api/usuarios', $payload);
        header('Location: usuarios.php');
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
<title><?= ucfirst($modo) ?> Usuário</title>
<link rel="stylesheet" href="style/style_base.css">
</head>
<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png" alt="Sair"></a>
    <a href="usuarios.php" class="voltar-icon"><img src="../Imagens/voltar.png" alt="Voltar"></a>
</div>
<div class="container">
    <aside class="sidebar"><ul>
        <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
        <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
        <li><a href="usuarios.php" class="ativo"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li>
        <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
    </ul></aside>
    <main class="main-content">
        <div class="titulo-pagina"><h2><?= strtoupper($modo) ?> USUÁRIO</h2></div>
        <form method="POST" class="formulario-dados">
            <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <label for="usuario">Nome de usuário</label>
            <input type="text" id="usuario" name="usuario" maxlength="50" required value="<?= htmlspecialchars($dados['usuario'] ?? '') ?>">
            <label for="senha">Senha <?= $id ? '(deixe em branco para manter a atual)' : '' ?></label>
            <input type="password" id="senha" name="senha" <?= $id ? '' : 'required' ?> autocomplete="new-password">
            <label for="tipo_usuario">Tipo de usuário</label>
            <select id="tipo_usuario" name="tipo_usuario" required>
                <option value="ADMIN" <?= ($dados['tipoUsuario'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
                <option value="FUNCIONARIO" <?= ($dados['tipoUsuario'] ?? '') === 'FUNCIONARIO' ? 'selected' : '' ?>>Funcionário</option>
            </select>
            <button type="submit"><?= $id ? 'SALVAR ALTERAÇÕES' : 'CADASTRAR' ?></button>
        </form>
    </main>
</div>
</body>
</html>
