<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios_regras.php';
$auth = auth_require_login();
$tipo_usuario = $auth['tipo'];
$usuario_id   = $auth['id'];
$podeRegistrar = auth_can('registrar_ocorrencias', $auth);
$podeAtualizarProprias = auth_can('gerenciar_ocorrencias', $auth);

$statusFiltro = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
$tipoFiltro   = isset($_GET['tipo']) && is_string($_GET['tipo']) ? trim($_GET['tipo']) : '';
$maquinaFiltro = isset($_GET['maquina_id']) && $_GET['maquina_id'] !== '' ? (int) $_GET['maquina_id'] : 0;
$usuarioFiltro = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int) $_GET['usuario_id'] : 0;
$busca = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

try {
    $where = [];
    $params = [];

    // Administrador vê todas as ocorrências; funcionário, apenas as próprias.
    if ($tipo_usuario !== 'ADM') {
        $where[] = "o.usuario_id = :usuario_id";
        $params[':usuario_id'] = $usuario_id;
    } elseif ($usuarioFiltro > 0) {
        $where[] = "o.usuario_id = :filtro_usuario";
        $params[':filtro_usuario'] = $usuarioFiltro;
    }
    if ($statusFiltro !== '') {
        if ($statusFiltro === 'Concluído' || $statusFiltro === 'Concluída') {
            $where[] = "o.status IN ('Concluída', 'Concluído')";
        } else {
            $where[] = "o.status = :status";
            $params[':status'] = $statusFiltro;
        }
    }
    if ($tipoFiltro !== '') {
        $where[] = "o.tipo = :tipo";
        $params[':tipo'] = $tipoFiltro;
    }
    if ($maquinaFiltro > 0) {
        $where[] = "o.maquina_id = :maquina";
        $params[':maquina'] = $maquinaFiltro;
    }
    if ($busca !== '') {
        $where[] = "(o.titulo LIKE :q1 OR o.descricao LIKE :q2 OR m.nome LIKE :q3)";
        foreach (['q1', 'q2', 'q3'] as $k) {
            $params[':' . $k] = auth_like($busca);
        }
    }

    $sql = "SELECT o.*, m.nome AS nome_maquina
            FROM ocorrencias o
            LEFT JOIN maquinas m ON o.maquina_id = m.id"
        . ($where ? " WHERE " . implode(" AND ", $where) : "")
        . " ORDER BY o.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ocorrencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Opções dos filtros do administrador.
    $listaMaquinas = $pdo->query("SELECT id, nome FROM maquinas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    // Os usuários ficam no user-service; só o administrador precisa da lista (filtro e "Registrada por").
    $listaUsuarios = [];
    $nomesUsuarios = [];
    if ($tipo_usuario === 'ADM') {
        foreach (usuarios_listar($pdo, '', '', '') as $us) {
            $listaUsuarios[] = ['id' => $us['id'], 'usuario' => $us['usuario']];
            $nomesUsuarios[$us['id']] = $us['usuario'];
        }
        usort($listaUsuarios, fn($a, $b) => strcasecmp($a['usuario'], $b['usuario']));
    }
} catch (PDOException $e) {
    die("Erro ao buscar ocorrências: " . $e->getMessage());
}

// Pode editar: administrador (todas) ou o dono, se tiver permissão de atualizar.
function pode_editar_ocorrencia($oc, $tipo_usuario, $usuario_id, $podeAtualizarProprias)
{
    return $tipo_usuario === 'ADM'
        || ($podeAtualizarProprias && (int) $oc['usuario_id'] === (int) $usuario_id);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Ocorrências</title>
<link rel="stylesheet" href="style/style_base.css">
<link rel="stylesheet" href="style/admin.css">
</head>
<body>

<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" class="logo-cliente">
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png"></a>
</div>

<div class="container">
    <?php $menuAtivo = 'ocorrencias'; include 'menu.php'; ?>

    <main class="main-content">

        <div class="titulo-pagina">
            <h2>OCORRÊNCIAS</h2>

            <div class="botoes">

                <form method="GET" class="busca-form">
                    <input type="search" name="q" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFiltro) ?>">
                    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipoFiltro) ?>">
                    <input type="hidden" name="maquina_id" value="<?= $maquinaFiltro ?: '' ?>">
                    <input type="hidden" name="usuario_id" value="<?= $usuarioFiltro ?: '' ?>">
                    <button type="submit">BUSCAR</button>
                </form>

                <div class="filtro-container">
                    <img src="../Imagens/filtro.png" class="icone-filtro" id="abrirFiltro">
                    <div class="menu-filtro menu-filtro-largo" id="menuFiltro">
                        <form method="GET">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($busca) ?>">
                            <label>Status</label>
                            <select name="status">
                                <option value="">Todos</option>
                                <option value="Pendente" <?= ($statusFiltro === 'Pendente' ? 'selected' : '') ?>>Pendente</option>
                                <option value="Em andamento" <?= ($statusFiltro === 'Em andamento' ? 'selected' : '') ?>>Em andamento</option>
                                <option value="Concluído" <?= (in_array($statusFiltro, ['Concluído', 'Concluída'], true) ? 'selected' : '') ?>>Concluído</option>
                            </select>
                            <label>Tipo</label>
                            <select name="tipo">
                                <option value="">Todos</option>
                                <option value="Preventiva" <?= ($tipoFiltro === 'Preventiva' ? 'selected' : '') ?>>Preventiva</option>
                                <option value="Corretiva" <?= ($tipoFiltro === 'Corretiva' ? 'selected' : '') ?>>Corretiva</option>
                            </select>
                            <label>Máquina</label>
                            <select name="maquina_id">
                                <option value="">Todas</option>
                                <?php foreach ($listaMaquinas as $mq): ?>
                                    <option value="<?= $mq['id'] ?>" <?= $maquinaFiltro === (int) $mq['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mq['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($tipo_usuario === 'ADM'): ?>
                                <label>Registrada por</label>
                                <select name="usuario_id">
                                    <option value="">Todos</option>
                                    <?php foreach ($listaUsuarios as $us): ?>
                                        <option value="<?= $us['id'] ?>" <?= $usuarioFiltro === (int) $us['id'] ? 'selected' : '' ?>><?= htmlspecialchars($us['usuario']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <div class="menu-filtro-acoes">
                                <button type="submit">Filtrar</button>
                                <a href="ocorrencias.php">Limpar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($podeRegistrar): ?>
                    <button onclick="window.location.href='adicionar_ocorrencia.php'" type="button">ADICIONAR</button>
                <?php endif; ?>

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
                                <?php if ($tipo_usuario === 'ADM'): ?>
                                    <p><strong>Registrada por:</strong> <?= htmlspecialchars($nomesUsuarios[$oc['usuario_id']] ?? '—') ?></p>
                                <?php endif; ?>

                                <?php if (!empty($oc['descricao'])): ?>
                                    <div class="descricao">
                                        <strong>Descrição:</strong>
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($oc['descricao'], 0, 60, '...'))) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="acoes">
                                <?php $editavel = pode_editar_ocorrencia($oc, $tipo_usuario, $usuario_id, $podeAtualizarProprias); ?>
                                <?php if ($editavel): ?>
                                <a href="adicionar_ocorrencia.php?id=<?= $oc['id'] ?>">
                                    <img src="../Imagens/editar.png">
                                </a>
                                <?php endif; ?>

                                <a href="#" class="ver-mais"
                                   data-id="<?= $oc['id'] ?>"
                                   data-editavel="<?= $editavel ? '1' : '0' ?>"
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
    const desc = document.getElementById('modalDescricao');
    desc.textContent = '';
    if (oc.descricao) {
        const rotulo = document.createElement('strong');
        rotulo.textContent = 'Descrição:';
        desc.append(rotulo, document.createElement('br'), document.createTextNode(oc.descricao));
    }

    const img = document.getElementById('modalImagem');
    if (oc.imagem) {
        img.src = oc.imagem;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }

    btnEditar.style.display = oc.editavel ? '' : 'none';
    btnEditar.onclick = () => window.location.href = `adicionar_ocorrencia.php?id=${oc.id}`;
    modal.style.display = 'flex';
}

document.querySelectorAll('.ver-mais').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        abrirModal({
            id: btn.dataset.id,
            editavel: btn.dataset.editavel === '1',
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
