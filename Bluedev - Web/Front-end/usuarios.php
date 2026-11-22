<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios_regras.php';
$auth = auth_require_admin();

$busca = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$tipoFiltro = isset($_GET['tipo']) && is_string($_GET['tipo']) ? $_GET['tipo'] : '';
$ativoFiltro = isset($_GET['ativo']) && is_string($_GET['ativo']) ? $_GET['ativo'] : '';

$usuarios = usuarios_listar($pdo, $busca, $tipoFiltro, $ativoFiltro);

$rotulosPermissoes = auth_permissoes_disponiveis();
$mensagem = $_SESSION['flash_usuarios'] ?? null;
unset($_SESSION['flash_usuarios']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Usuários</title>
<link rel="stylesheet" href="style/style_base.css">
<link rel="stylesheet" href="style/admin.css">
</head>
<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="logout.php" class="logout-icon">
        <img src="../Imagens/icone_sair.png" alt="Sair">
    </a>
</div>

<div class="container">
<?php $menuAtivo = 'usuarios'; include 'menu.php'; ?>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>USUÁRIOS</h2>
            <div class="botoes">
                <form method="GET" class="busca-form">
                    <input type="search" name="q" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar">
                    <select name="tipo" aria-label="Perfil">
                        <option value="">Todos os perfis</option>
                        <option value="ADM" <?= $tipoFiltro === 'ADM' ? 'selected' : '' ?>>Administrador</option>
                        <option value="FUNC" <?= $tipoFiltro === 'FUNC' ? 'selected' : '' ?>>Funcionário</option>
                    </select>
                    <select name="ativo" aria-label="Situação">
                        <option value="">Todas</option>
                        <option value="1" <?= $ativoFiltro === '1' ? 'selected' : '' ?>>Ativos</option>
                        <option value="0" <?= $ativoFiltro === '0' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                    <button type="submit">BUSCAR</button>
                </form>
                <button onclick="window.location.href='adicionar_usuario.php'" type="button">ADICIONAR</button>
                <button id="btnRemover" type="button" class="btn-remover">REMOVER</button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <p class="<?= $mensagem['tipo'] === 'ok' ? 'ok-form' : 'erro-form' ?>"><?= htmlspecialchars($mensagem['texto']) ?></p>
        <?php endif; ?>

        <form id="formRemover" method="POST" action="remover_usuario.php">
            <?= auth_csrf_field() ?>
            <div class="lista-funcionarios">
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <div class="funcionario-card">
                            <?php if ($u['id'] !== $auth['id']): ?>
                                <input type="checkbox" name="usuarios[]" value="<?= $u['id'] ?>" class="checkbox-funcionario">
                            <?php endif; ?>
                            <div class="info">
                                <h3><?= htmlspecialchars($u['usuario']) ?>
                                    <span class="selo <?= $u['tipo'] === 'ADM' ? '' : 'func' ?>"><?= $u['tipo'] === 'ADM' ? 'Administrador' : 'Funcionário' ?></span>
                                    <?php if (!$u['ativo']): ?><span class="selo inativo">Inativo</span><?php endif; ?>
                                </h3>
                                <p><strong>Nome:</strong> <?= htmlspecialchars($u['nome'] ?? '—') ?></p>
                                <p><strong>Funcionário vinculado:</strong> <?= htmlspecialchars($u['funcionario_nome'] ?? '—') ?></p>
                                <p><strong>Permissões:</strong>
                                    <?php
                                    if ($u['tipo'] === 'ADM') {
                                        echo 'Acesso total';
                                    } elseif (!$u['permissoes']) {
                                        echo 'Nenhuma';
                                    } else {
                                        echo htmlspecialchars(implode(', ', array_map(fn($p) => $rotulosPermissoes[$p], $u['permissoes'])));
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="acoes">
                                <a href="adicionar_usuario.php?id=<?= $u['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sem-funcionarios">
                        <p>Nenhum usuário encontrado.</p>
                        <img src="../Imagens/nada_encontrado3.png" alt="Sem usuários">
                    </div>
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
    const container = document.querySelector('.lista-funcionarios');

    if (!modoRemover) {
        modoRemover = true;
        container.classList.add('modo-remover');
        btnRemover.textContent = 'CONFIRMAR REMOÇÃO';
        return;
    }

    const selecionados = Array.from(container.querySelectorAll('.checkbox-funcionario')).filter(c => c.checked);
    if (selecionados.length === 0) {
        alert('Selecione pelo menos um usuário para remover.');
        return;
    }
    if (confirm('Tem certeza que deseja excluir os usuários selecionados?')) {
        formRemover.submit();
    }
});
</script>
</body>
</html>
