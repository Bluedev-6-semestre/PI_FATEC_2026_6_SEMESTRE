<?php
require_once __DIR__ . '/auth.php';
$auth = auth_require_permission('ver_cadastros');
$tipo_usuario = $auth['tipo'];
$busca = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

try {
    $sql = "SELECT * FROM funcionarios";
    $params = [];
    if ($busca !== '') {
        $sql .= " WHERE nome LIKE :q1 OR funcao LIKE :q2";
        $params[':q1'] = auth_like($busca);
        $params[':q2'] = auth_like($busca);
    }
    $stmt = $pdo->prepare($sql . " ORDER BY id DESC");
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar funcionários: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Página Funcionários</title>
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
    <?php $menuAtivo = 'funcionarios'; include 'menu.php'; ?>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>FUNCIONÁRIOS</h2>
            <div class="botoes">
                <form method="GET" class="busca-form">
                    <input type="search" name="q" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar">
                    <button type="submit">BUSCAR</button>
                </form>
                <?php if ($tipo_usuario === 'ADM'): ?>
                    <button onclick="window.location.href='adicionar_funcionario.php'" type="button">ADICIONAR</button>
                    <button id="btnRemover" type="button" class="btn-remover">REMOVER</button>
                <?php endif; ?>
            </div>
        </div>

        <form id="formRemover" method="POST" action="remover_funcionario.php">
            <div class="lista-funcionarios">
                <?php if (!empty($funcionarios)): ?>
                    <?php foreach ($funcionarios as $f): ?>
                        <div class="funcionario-card">
                            <?php if ($tipo_usuario === 'ADM'): ?>
                                <input type="checkbox" name="funcionarios[]" value="<?= $f['id'] ?>" class="checkbox-funcionario">
                            <?php endif; ?>
                            <div class="info">
                                <h3><?= htmlspecialchars($f['nome']) ?></h3>
                                <p><strong>Idade:</strong> <?= htmlspecialchars($f['idade']) ?></p>
                                <p><strong>Função:</strong> <?= htmlspecialchars($f['funcao']) ?></p>
                            </div>
                            <div class="acoes">
                                <?php if ($tipo_usuario === 'ADM'): ?>
                                <a href="adicionar_funcionario.php?id=<?= $f['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
                                <?php endif; ?>
                                <a href="#"
                                   class="ver-mais"
                                   data-id="<?= $f['id'] ?>"
                                   data-nome="<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>"
                                   data-idade="<?= htmlspecialchars($f['idade'], ENT_QUOTES) ?>"
                                   data-funcao="<?= htmlspecialchars($f['funcao'], ENT_QUOTES) ?>">
                                   <img src="../Imagens/visualizar.png" alt="Ver Mais">
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sem-funcionarios">
                        <p><?= $busca !== '' ? 'Nenhum resultado para a busca.' : 'Nenhum funcionário cadastrado no momento.' ?></p>
                        <img src="../Imagens/nada_encontrado3.png" alt="Sem funcionários">
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>

<div id="modalFuncionario" class="modal">
    <div class="modal-content">
        <span class="fechar">&times;</span>
        <h2 id="modalNome"></h2>
        <p><strong>Idade:</strong> <span id="modalIdade"></span></p>
        <p><strong>Função:</strong> <span id="modalFuncao"></span></p>
        <div class="botoes-modal">
            <?php if ($tipo_usuario === 'ADM'): ?><button id="btnEditar" class="btn-editar">Editar</button><?php endif; ?>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('modalFuncionario');
const fechar = document.querySelector('.fechar');
const btnEditar = document.getElementById('btnEditar');

function abrirModal(f) {
    document.getElementById('modalNome').textContent = f.nome;
    document.getElementById('modalIdade').textContent = f.idade;
    document.getElementById('modalFuncao').textContent = f.funcao;
    if (btnEditar) btnEditar.onclick = () => window.location.href = `adicionar_funcionario.php?id=${f.id}`;
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
        const funcionario = {
            id: btn.dataset.id,
            nome: btn.dataset.nome,
            idade: btn.dataset.idade,
            funcao: btn.dataset.funcao
        };
        abrirModal(funcionario);
    });
});

<?php if ($tipo_usuario === 'ADM'): ?>
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

    const checkboxes = container.querySelectorAll('.checkbox-funcionario');
    const selecionados = Array.from(checkboxes).filter(c => c.checked);

    if (selecionados.length === 0) {
        alert('Selecione pelo menos um funcionário para remover.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir os funcionários selecionados?')) {
        formRemover.submit();
    }
});
<?php endif; ?>
</script>
</body>
</html>
