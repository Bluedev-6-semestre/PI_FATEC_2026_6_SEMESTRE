<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? ''; 
$usuario_id   = $_SESSION['usuario_id'] ?? 0;

try {
    if ($tipo_usuario === 'ADM') {
        $stmt = $pdo->prepare("SELECT * FROM fornecedores ORDER BY id DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE usuario_id = :usuario_id ORDER BY id DESC");
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    $stmt->execute();
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
</head>
<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="index.php" class="logout-icon">
        <img src="../Imagens/icone_sair.png" alt="Sair">
    </a>
</div>

<div class="container">
    <aside class="sidebar">
        <ul>
            <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
            <?php if ($tipo_usuario === 'ADM'): ?>
                <li><a href="fornecedores.php" class="ativo"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
                <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>FORNECEDORES</h2>
            <div class="botoes">
                <button onclick="window.location.href='adicionar_fornecedores.php'">ADICIONAR</button>
                <?php if ($tipo_usuario === 'ADM'): ?>
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
                                <a href="adicionar_fornecedores.php?id=<?= $f['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
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
                        <p>Nenhum fornecedor cadastrado no momento.</p>
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
            <button id="btnEditar" class="btn-editar">Editar</button>
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
    btnEditar.onclick = () => window.location.href = `adicionar_fornecedor.php?id=${f.id}`;
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
