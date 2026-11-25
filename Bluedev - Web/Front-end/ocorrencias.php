<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$tipo_usuario = current_user_type();
$statusFiltro = trim($_GET['status'] ?? '');
$ocorrencias = [];
$nomesMaquinas = [];
$erro = null;

try {
    $maquinasResumo = api_request('GET', '/api/maquinas');
    foreach (is_array($maquinasResumo) ? $maquinasResumo : [] as $maquina) {
        if (!empty($maquina['id'])) $nomesMaquinas[(int) $maquina['id']] = $maquina['nome'] ?? '—';
    }

    $resposta = api_request('GET', '/api/ocorrencias');
    foreach (is_array($resposta) ? $resposta : [] as $ocorrencia) {
        if ($tipo_usuario !== 'ADM' && (int) ($ocorrencia['usuarioId'] ?? 0) !== current_user_id()) continue;
        if ($statusFiltro !== '' && !in_array($ocorrencia['status'] ?? '', [$statusFiltro, $statusFiltro === 'Concluído' ? 'Concluída' : $statusFiltro], true)) continue;
        $ocorrencia['nome_maquina'] = $nomesMaquinas[(int) ($ocorrencia['maquinaId'] ?? 0)] ?? '—';
        $ocorrencias[] = $ocorrencia;
    }
    usort($ocorrencias, static function ($a, $b) {
        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });
} catch (Throwable $exception) {
    $erro = api_user_message($exception);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Ocorrências</title>
<link rel="stylesheet" href="style/style_base.css">
</head>
<body>

<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" class="logo-cliente">
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png"></a>
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
        <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($mensagem = api_take_flash_error()): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

        <div class="titulo-pagina">
            <h2>OCORRÊNCIAS</h2>

            <div class="botoes">

                <div class="filtro-container">
                    <img src="../Imagens/filtro.png" class="icone-filtro" id="abrirFiltro">
                    <div class="menu-filtro" id="menuFiltro">
                        <form method="GET">
                            <select name="status" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="Pendente" <?= ($statusFiltro === 'Pendente' ? 'selected' : '') ?>>Pendente</option>
                                <option value="Em andamento" <?= ($statusFiltro === 'Em andamento' ? 'selected' : '') ?>>Em andamento</option>
                                <option value="Concluído" <?= ($statusFiltro === 'Concluído' ? 'selected' : '') ?>>Concluído</option>
                            </select>
                        </form>
                    </div>
                </div>

                <button onclick="window.location.href='adicionar_ocorrencia.php'">ADICIONAR</button>

                <?php if ($tipo_usuario === 'ADM'): ?>
                    <button id="btnRemover" class="btn-remover" type="button">REMOVER</button>
                <?php endif; ?>

            </div>
        </div>

        <form id="formRemover" method="POST" action="remover_ocorrencia.php">
            <div class="lista-ocorrencias">

                <?php if (!empty($ocorrencias)): ?>
                    <?php foreach ($ocorrencias as $oc): ?>

                        <?php
                            $imagemCaminho = '';
                            if (!empty($oc['imagem'])) {
                                $imagemCaminho = str_starts_with($oc['imagem'], 'uploads/')
                                    ? $oc['imagem']
                                    : 'uploads/' . ltrim($oc['imagem'], '/');
                            }
                        ?>

                        <div class="ocorrencia-card">

                            <?php if ($tipo_usuario === 'ADM'): ?>
                                <input type="checkbox" class="checkbox-ocorrencia" name="ocorrencias[]" value="<?= $oc['id'] ?>">
                            <?php endif; ?>

                            <div class="info">
                                <h3><?= htmlspecialchars($oc['titulo']) ?></h3>
                                <p><strong>Tipo:</strong> <?= htmlspecialchars($oc['tipo']) ?></p>
                                <p><strong>Status:</strong> <?= htmlspecialchars($oc['status']) ?></p>
                                <p><strong>Máquina:</strong> <?= htmlspecialchars($oc['nome_maquina'] ?? '—') ?></p>

                                <?php if (!empty($oc['descricao'])): ?>
                                    <div class="descricao">
                                        <strong>Descrição:</strong>
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($oc['descricao'], 0, 60, '...'))) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="acoes">
                                <a href="adicionar_ocorrencia.php?id=<?= $oc['id'] ?>">
                                    <img src="../Imagens/editar.png">
                                </a>

                                <a href="#" class="ver-mais"
                                   data-id="<?= $oc['id'] ?>"
                                   data-titulo="<?= htmlspecialchars($oc['titulo'], ENT_QUOTES) ?>"
                                   data-tipo="<?= htmlspecialchars($oc['tipo'], ENT_QUOTES) ?>"
                                   data-status="<?= htmlspecialchars($oc['status'], ENT_QUOTES) ?>"
                                   data-descricao="<?= htmlspecialchars($oc['descricao'] ?? '', ENT_QUOTES) ?>"
                                   data-imagem="<?= htmlspecialchars($imagemCaminho, ENT_QUOTES) ?>">
                                   <img src="../Imagens/visualizar.png">
                                </a>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="sem-ocorrencias">
                        <p>Nenhum resultado disponível no momento.</p>
                        <img src="../Imagens/nada_encontrado.png">
                    </div>
                <?php endif; ?>

            </div>
        </form>

    </main>
</div>

<div id="modalOcorrencia" class="modal">
    <div class="modal-content">
        <span class="fechar">&times;</span>
        <h2 id="modalTitulo"></h2>
        <p><strong>Tipo:</strong> <span id="modalTipo"></span></p>
        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        <div id="modalDescricao"></div>

        <div class="modal-imagem-wrapper">
            <img id="modalImagem" src="">
        </div>

        <div class="botoes-modal">
            <button id="btnEditar" class="btn-editar">Editar</button>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
const filtroIcone = document.getElementById('abrirFiltro');
const menuFiltro = document.getElementById('menuFiltro');

filtroIcone.addEventListener('click', () => {
    menuFiltro.classList.toggle('ativo');
});

window.addEventListener('click', e => {
    if (!menuFiltro.contains(e.target) && e.target !== filtroIcone) {
        menuFiltro.classList.remove('ativo');
    }
});
</script>

<script>
const modal = document.getElementById('modalOcorrencia');
const fechar = document.querySelector('.fechar');
const btnEditar = document.getElementById('btnEditar');

function abrirModal(oc) {
    document.getElementById('modalTitulo').textContent = oc.titulo;
    document.getElementById('modalTipo').textContent = oc.tipo;
    document.getElementById('modalStatus').textContent = oc.status;
    document.getElementById('modalDescricao').innerHTML = oc.descricao ? `<strong>Descrição:</strong><br>${oc.descricao}` : '';

    const img = document.getElementById('modalImagem');
    if (oc.imagem) {
        img.src = oc.imagem;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }

    btnEditar.onclick = () => window.location.href = `adicionar_ocorrencia.php?id=${oc.id}`;
    modal.style.display = 'flex';
}

document.querySelectorAll('.ver-mais').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        abrirModal({
            id: btn.dataset.id,
            titulo: btn.dataset.titulo,
            tipo: btn.dataset.tipo,
            status: btn.dataset.status,
            descricao: btn.dataset.descricao,
            imagem: btn.dataset.imagem
        });
    });
});

function fecharModal() {
    modal.style.display = 'none';
}

fechar.onclick = fecharModal;

window.onclick = e => {
    if (e.target === modal) fecharModal();
};
</script>

<script>
const btnRemover = document.getElementById('btnRemover');
const formRemover = document.getElementById('formRemover');
const container = document.querySelector('.lista-ocorrencias');
let modoRemover = false;

if (btnRemover) {
    btnRemover.addEventListener('click', () => {
        const checkboxes = document.querySelectorAll('.checkbox-ocorrencia');

        if (!modoRemover) {
            modoRemover = true;
            btnRemover.textContent = 'CONFIRMAR REMOÇÃO';
            container.classList.add('remocao-ativa');
            return;
        }

        const selecionados = Array.from(checkboxes).filter(c => c.checked);

        if (selecionados.length === 0) {
            alert('Selecione pelo menos uma ocorrência para remover.');
            return;
        }

        if (confirm('Tem certeza que deseja excluir as ocorrências selecionadas?')) {
            formRemover.submit();
        }
    });
}
</script>

</body>
</html>
