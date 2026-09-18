<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? ''; 
$usuario_id   = $_SESSION['usuario_id'] ?? 0;

try {
    if ($tipo_usuario === 'ADM') {
        $stmt = $pdo->prepare("SELECT * FROM maquinas ORDER BY id DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM maquinas WHERE usuario_id = :usuario_id ORDER BY id DESC");
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    $stmt->execute();
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
                <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php" class="ativo"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="titulo-pagina">
            <h2>MÁQUINAS</h2>
            <div class="botoes">
                <button onclick="window.location.href='adicionar_maquinas.php'">ADICIONAR</button>
                <?php if ($tipo_usuario === 'ADM'): ?>
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
                                <a href="adicionar_maquinas.php?id=<?= $m['id'] ?>">
                                    <img src="../Imagens/editar.png" alt="Editar">
                                </a>
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
                        <p>Nenhuma máquina cadastrada no momento.</p>
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
            <button id="btnEditar" class="btn-editar">Editar</button>
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
    btnEditar.onclick = () => window.location.href = `adicionar_maquinas.php?id=${m.id}`;
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
