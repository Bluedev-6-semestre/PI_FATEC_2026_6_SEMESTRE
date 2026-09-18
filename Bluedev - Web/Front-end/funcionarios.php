<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
$usuario_id   = $_SESSION['usuario_id'] ?? 0;

try {
    if ($tipo_usuario === 'ADM') {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios ORDER BY id DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE usuario_id = :usuario_id ORDER BY id DESC");
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    $stmt->execute();
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
                <li><a href="fornecedores.php"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
                <li><a href="funcionarios.php" class="ativo"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>FUNCIONÁRIOS</h2>
            <div class="botoes">
                <button onclick="window.location.href='adicionar_funcionario.php'">ADICIONAR</button>
                <?php if ($tipo_usuario === 'ADM'): ?>
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
                                <a href="adicionar_funcionario.php?id=<?= $f['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
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
                        <p>Nenhum funcionário cadastrado no momento.</p>
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
            <button id="btnEditar" class="btn-editar">Editar</button>
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
    btnEditar.onclick = () => window.location.href = `adicionar_funcionario.php?id=${f.id}`;
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
