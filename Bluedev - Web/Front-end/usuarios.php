<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$usuarios = [];
$erro = null;
try {
    $resposta = api_request('GET', '/api/usuarios');
    $usuarios = is_array($resposta) ? $resposta : [];
} catch (Throwable $exception) {
    $erro = api_user_message($exception);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários</title>
<link rel="stylesheet" href="style/style_base.css">
</head>
<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png" alt="Sair"></a>
</div>
<div class="container">
    <aside class="sidebar"><ul>
        <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
        <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
        <li><a href="usuarios.php" class="ativo"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li>
        <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
    </ul></aside>
    <main class="main-content">
        <div class="titulo-pagina"><h2>USUÁRIOS</h2><div class="botoes">
            <button onclick="window.location.href='adicionar_usuario.php'">ADICIONAR</button>
            <button id="btnRemover" type="button" class="btn-remover">REMOVER</button>
        </div></div>
        <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($mensagem = api_take_flash_error()): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
        <form id="formRemover" method="POST" action="remover_usuario.php">
            <div class="lista-itens">
                <?php if ($usuarios): foreach ($usuarios as $usuario): ?>
                    <div class="item-card">
                        <input type="checkbox" name="usuarios[]" value="<?= (int) ($usuario['id'] ?? 0) ?>" class="checkbox-item" <?= (int) ($usuario['id'] ?? 0) === current_user_id() ? 'disabled title="Não é possível excluir a própria conta por esta tela."' : '' ?>>
                        <div class="info">
                            <h3><?= htmlspecialchars($usuario['usuario'] ?? '') ?></h3>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars(($usuario['tipoUsuario'] ?? '') === 'ADMIN' ? 'Administrador' : 'Funcionário') ?></p>
                        </div>
                        <div class="acoes"><a href="adicionar_usuario.php?id=<?= (int) ($usuario['id'] ?? 0) ?>"><img src="../Imagens/editar.png" alt="Editar"></a></div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="sem-itens"><p>Nenhum usuário cadastrado.</p><img src="../Imagens/Nada_encontrado.png" alt="Nenhum usuário"></div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>
<script>
const btnRemover = document.getElementById('btnRemover');
const formRemover = document.getElementById('formRemover');
let modoRemover = false;
btnRemover.addEventListener('click', () => {
    const container = document.querySelector('.lista-itens');
    if (!modoRemover) { modoRemover = true; container.classList.add('remocao-ativa'); btnRemover.textContent = 'CONFIRMAR REMOÇÃO'; return; }
    const selecionados = [...container.querySelectorAll('.checkbox-item')].filter(item => item.checked);
    if (!selecionados.length) { alert('Selecione pelo menos um usuário para remover.'); return; }
    if (confirm('Tem certeza que deseja excluir os usuários selecionados?')) formRemover.submit();
});
</script>
</body>
</html>
