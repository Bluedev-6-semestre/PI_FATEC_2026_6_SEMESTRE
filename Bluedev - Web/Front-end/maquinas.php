<?php
require_once __DIR__ . '/auth.php';
$auth = auth_require_permission('ver_cadastros');
$tipo_usuario = $auth['tipo'];
$busca = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$statusFiltro = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';

try {
    $where = [];
    $params = [];
    if ($busca !== '') {
        $where[] = "(nome LIKE :q1 OR codigo LIKE :q2 OR modelo LIKE :q3 OR fabricante LIKE :q4)";
        foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
            $params[':' . $k] = auth_like($busca);
        }
    }
    if ($statusFiltro !== '') {
        $where[] = "status_maquina = :status";
        $params[':status'] = $statusFiltro;
    }
    $sql = "SELECT * FROM maquinas" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $maquinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar máquinas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Máquinas</title>
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
    <?php $menuAtivo = 'maquinas'; include 'menu.php'; ?>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>MÁQUINAS</h2>
            <div class="botoes">
                <form method="GET" class="busca-form">
                    <input type="search" name="q" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar">
                    <select name="status" aria-label="Status">
                        <option value="">Todos os status</option>
                        <?php foreach (['ativa', 'inativa', 'em manutenção'] as $st): ?>
                            <option value="<?= $st ?>" <?= $statusFiltro === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">BUSCAR</button>
                </form>
                <?php if ($tipo_usuario === 'ADM'): ?>
                    <button onclick="window.location.href='adicionar_maquinas.php'" type="button">ADICIONAR</button>
                    <button id="btnRemover" type="button" class="btn-remover">REMOVER</button>
                <?php endif; ?>
            </div>
        </div>

        <form id="formRemover" method="POST" action="remover_maquina.php">
            <div class="lista-fornecedores">
                <?php if (!empty($maquinas)): ?>
                    <?php foreach ($maquinas as $m): ?>
                        <div class="fornecedor-card">
                            <?php if ($tipo_usuario === 'ADM'): ?>
                                <input type="checkbox" name="maquinas[]" value="<?= $m['id'] ?>" class="checkbox-fornecedor">
                            <?php endif; ?>
                            <div class="info">
                                <h3><?= htmlspecialchars($m['nome']) ?></h3>
                                <p><strong>Código:</strong> <?= htmlspecialchars($m['codigo']) ?></p>
                                <p><strong>Modelo:</strong> <?= htmlspecialchars($m['modelo']) ?></p>
                                <p><strong>Fabricante:</strong> <?= htmlspecialchars($m['fabricante']) ?></p>
                                <p><strong>Status:</strong> <?= htmlspecialchars($m['status_maquina']) ?></p>
                            </div>
                            <div class="acoes">
                                <?php if ($tipo_usuario === 'ADM'): ?>
                                <a href="adicionar_maquinas.php?id=<?= $m['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
                                <?php endif; ?>
                                <a href="#"
                                   class="ver-mais"
                                   data-id="<?= $m['id'] ?>"
                                   data-nome="<?= htmlspecialchars($m['nome'], ENT_QUOTES) ?>"
                                   data-codigo="<?= htmlspecialchars($m['codigo'], ENT_QUOTES) ?>"
                                   data-modelo="<?= htmlspecialchars($m['modelo'], ENT_QUOTES) ?>"
                                   data-fabricante="<?= htmlspecialchars($m['fabricante'], ENT_QUOTES) ?>"
                                   data-numero_serie="<?= htmlspecialchars($m['numero_serie'], ENT_QUOTES) ?>"
                                   data-data_aquisicao="<?= htmlspecialchars($m['data_aquisicao'], ENT_QUOTES) ?>"
                                   data-status_maquina="<?= htmlspecialchars($m['status_maquina'], ENT_QUOTES) ?>"
                                   data-descricao="<?= htmlspecialchars($m['descricao'], ENT_QUOTES) ?>">
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sem-fornecedores">
                        <p><?= ($busca !== '' || $statusFiltro !== '') ? 'Nenhum resultado para a busca.' : 'Nenhuma máquina cadastrada no momento.' ?></p>
                        <img src="../Imagens/nada_encontrado4.png" alt="Sem máquinas">
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>

<div id="modalMaquina" class="modal">
    <div class="modal-content">
        <span class="fechar">&times;</span>
        <h2 id="modalNome"></h2>
        <p><strong>Código:</strong> <span id="modalCodigo"></span></p>
        <p><strong>Modelo:</strong> <span id="modalModelo"></span></p>
        <p><strong>Fabricante:</strong> <span id="modalFabricante"></span></p>
        <p><strong>Nº de Série:</strong> <span id="modalNumeroSerie"></span></p>
        <p><strong>Data de Aquisição:</strong> <span id="modalDataAquisicao"></span></p>
        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        <p><strong>Descrição:</strong> <span id="modalDescricao"></span></p>
        <div class="botoes-modal">
            <?php if ($tipo_usuario === 'ADM'): ?><button id="btnEditar" class="btn-editar">Editar</button><?php endif; ?>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('modalMaquina');
const fechar = document.querySelector('.fechar');
const btnEditar = document.getElementById('btnEditar');

function abrirModal(m) {
    document.getElementById('modalNome').textContent = m.nome;
    document.getElementById('modalCodigo').textContent = m.codigo;
    document.getElementById('modalModelo').textContent = m.modelo;
    document.getElementById('modalFabricante').textContent = m.fabricante;
    document.getElementById('modalNumeroSerie').textContent = m.numero_serie;
    document.getElementById('modalDataAquisicao').textContent = m.data_aquisicao;
    document.getElementById('modalStatus').textContent = m.status_maquina;
    document.getElementById('modalDescricao').textContent = m.descricao;
    if (btnEditar) btnEditar.onclick = () => window.location.href = `adicionar_maquinas.php?id=${m.id}`;
    modal.style.display = 'flex';
}

function fecharModal() { modal.style.display = 'none'; }
fechar.onclick = fecharModal;
window.onclick = e => { if (e.target === modal) fecharModal(); };

document.querySelectorAll('.acoes a.ver-mais').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const m = {
            id: btn.dataset.id,
            nome: btn.dataset.nome,
            codigo: btn.dataset.codigo,
            modelo: btn.dataset.modelo,
            fabricante: btn.dataset.fabricante,
            numero_serie: btn.dataset.numero_serie,
            data_aquisicao: btn.dataset.data_aquisicao,
            status_maquina: btn.dataset.status_maquina,
            descricao: btn.dataset.descricao
        };
        abrirModal(m);
    });
});
</script>

<?php if ($tipo_usuario === 'ADM'): ?>
<script>
const btnRemover = document.getElementById('btnRemover');
const formRemover = document.getElementById('formRemover');
const container = document.querySelector('.lista-fornecedores');
let modoRemover = false;

btnRemover.addEventListener('click', () => {
    const checkboxes = document.querySelectorAll('.checkbox-fornecedor');

    if (!modoRemover) {
        modoRemover = true;
        btnRemover.textContent = 'CONFIRMAR REMOÇÃO';
        container.classList.add('remocao-ativa');
        return;
    }

    const selecionados = Array.from(checkboxes).filter(c => c.checked);
    if (selecionados.length === 0) {
        alert('Selecione pelo menos uma máquina para remover.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir as máquinas selecionadas?')) {
        formRemover.submit();
    }
});
</script>
<?php endif; ?>
</body>
</html>
