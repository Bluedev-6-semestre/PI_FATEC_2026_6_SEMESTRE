<?php
require_once __DIR__ . '/auth.php';
$auth = auth_require_permission('ver_cadastros');
$tipo_usuario = $auth['tipo'];
$busca = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

try {
    $sql = "SELECT * FROM fornecedores";
    $params = [];
    if ($busca !== '') {
        $sql .= " WHERE nome LIKE :q1 OR cnpj LIKE :q2 OR categoria LIKE :q3 OR telefone LIKE :q4";
        foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
            $params[':' . $k] = auth_like($busca);
        }
    }
    $stmt = $pdo->prepare($sql . " ORDER BY id DESC");
    $stmt->execute($params);
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar fornecedores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Fornecedores</title>
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
    <?php $menuAtivo = 'fornecedores'; include 'menu.php'; ?>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>FORNECEDORES</h2>
            <div class="botoes">
                <form method="GET" class="busca-form">
                    <input type="search" name="q" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar">
                    <button type="submit">BUSCAR</button>
                </form>
                <?php if ($tipo_usuario === 'ADM'): ?>
                    <button onclick="window.location.href='adicionar_fornecedores.php'" type="button">ADICIONAR</button>
                    <button id="btnRemover" type="button" class="btn-remover">REMOVER</button>
                <?php endif; ?>
            </div>
        </div>

        <form id="formRemover" method="POST" action="remover_fornecedor.php">
            <div class="lista-fornecedores">
                <?php if (!empty($fornecedores)): ?>
                    <?php foreach ($fornecedores as $f): ?>
                        <div class="fornecedor-card">
                            <?php if ($tipo_usuario === 'ADM'): ?>
                                <input type="checkbox" name="fornecedores[]" value="<?= $f['id'] ?>" class="checkbox-fornecedor">
                            <?php endif; ?>
                            <div class="info">
                                <h3><?= htmlspecialchars($f['nome']) ?></h3>
                                <p><strong>CNPJ:</strong> <?= htmlspecialchars($f['cnpj']) ?></p>
                                <p><strong>Categoria:</strong> <?= htmlspecialchars($f['categoria']) ?></p>
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($f['telefone']) ?></p>
                                <p><strong>Endereço (CEP):</strong> <?= htmlspecialchars($f['endereco']) ?></p>
                            </div>
                            <div class="acoes">
                                <?php if ($tipo_usuario === 'ADM'): ?>
                                <a href="adicionar_fornecedores.php?id=<?= $f['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
                                <?php endif; ?>
                                <a href="#"
                                   class="ver-mais"
                                   data-id="<?= $f['id'] ?>"
                                   data-nome="<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>"
                                   data-cnpj="<?= htmlspecialchars($f['cnpj'], ENT_QUOTES) ?>"
                                   data-categoria="<?= htmlspecialchars($f['categoria'], ENT_QUOTES) ?>"
                                   data-telefone="<?= htmlspecialchars($f['telefone'], ENT_QUOTES) ?>"
                                   data-endereco="<?= htmlspecialchars($f['endereco'], ENT_QUOTES) ?>">
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sem-fornecedores">
                        <p><?= $busca !== '' ? 'Nenhum resultado para a busca.' : 'Nenhum fornecedor cadastrado no momento.' ?></p>
                        <img src="../Imagens/nada_encontrado2.png" alt="Sem fornecedores">
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>

<div id="modalFornecedor" class="modal">
    <div class="modal-content">
        <span class="fechar">&times;</span>
        <h2 id="modalNome"></h2>
        <p><strong>CNPJ:</strong> <span id="modalCnpj"></span></p>
        <p><strong>Categoria:</strong> <span id="modalCategoria"></span></p>
        <p><strong>Telefone:</strong> <span id="modalTelefone"></span></p>
        <p><strong>Endereço (CEP):</strong> <span id="modalEndereco"></span></p>
        <div class="botoes-modal">
            <?php if ($tipo_usuario === 'ADM'): ?><button id="btnEditar" class="btn-editar">Editar</button><?php endif; ?>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('modalFornecedor');
const fechar = document.querySelector('.fechar');
const btnEditar = document.getElementById('btnEditar');

function abrirModal(f) {
    document.getElementById('modalNome').textContent = f.nome;
    document.getElementById('modalCnpj').textContent = f.cnpj;
    document.getElementById('modalCategoria').textContent = f.categoria;
    document.getElementById('modalTelefone').textContent = f.telefone;
    document.getElementById('modalEndereco').textContent = f.endereco;
    if (btnEditar) btnEditar.onclick = () => window.location.href = `adicionar_fornecedores.php?id=${f.id}`;
    modal.style.display = 'flex';
}

function fecharModal() {
    modal.style.display = 'none';
}

fechar.onclick = fecharModal;
window.onclick = e => { if (e.target === modal) fecharModal(); };

document.querySelectorAll('.acoes a.ver-mais').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const fornecedor = {
            id: btn.dataset.id,
            nome: btn.dataset.nome,
            cnpj: btn.dataset.cnpj,
            categoria: btn.dataset.categoria,
            telefone: btn.dataset.telefone,
            endereco: btn.dataset.endereco
        };
        abrirModal(fornecedor);
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
        alert('Selecione pelo menos um fornecedor para remover.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir os fornecedores selecionados?')) {
        formRemover.submit();
    }
});
</script>
<?php endif; ?>
</body>
</html>
