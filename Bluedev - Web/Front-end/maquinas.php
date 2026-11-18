<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$tipo_usuario = current_user_type();
$maquinas = [];
$erro = null;

try {
    // O endpoint de listagem retorna apenas id e nome; os detalhes são
    // consultados para preencher os cards e aplicar o filtro por proprietário.
    $resumo = api_request('GET', '/api/maquinas');
    foreach (is_array($resumo) ? $resumo : [] as $item) {
        if (empty($item['id'])) continue;
        $detalhe = api_request('GET', '/api/maquinas/' . (int) $item['id']);
        if (!is_array($detalhe)) continue;
        if ($tipo_usuario !== 'ADM' && (int) ($detalhe['usuarioId'] ?? 0) !== current_user_id()) continue;
        $maquinas[] = $detalhe;
    }
    usort($maquinas, static fn($a, $b) => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
} catch (Throwable $exception) {
    $erro = api_user_message($exception);
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
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png" alt="Sair"></a>
</div>
<div class="container">
    <aside class="sidebar"><ul>
        <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
        <?php if ($tipo_usuario === 'ADM'): ?><li><a href="maquinas.php" class="ativo"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li><?php endif; ?>
        <?php if ($tipo_usuario === 'ADM'): ?><li><a href="usuarios.php"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li><?php endif; ?>
        <li><a href="relatorios.php"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
    </ul></aside>
    <main class="main-content">
        <div class="titulo-pagina"><h2>MÁQUINAS</h2><div class="botoes">
            <button onclick="window.location.href='adicionar_maquinas.php'">ADICIONAR</button>
            <?php if ($tipo_usuario === 'ADM'): ?><button id="btnRemover" type="button" class="btn-remover">REMOVER</button><?php endif; ?>
        </div></div>
        <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <?php if ($mensagem = api_take_flash_error()): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
        <form id="formRemover" method="POST" action="remover_maquina.php">
            <div class="lista-itens">
                <?php if ($maquinas): foreach ($maquinas as $m): ?>
                    <div class="item-card">
                        <?php if ($tipo_usuario === 'ADM'): ?><input type="checkbox" name="maquinas[]" value="<?= (int) $m['id'] ?>" class="checkbox-item"><?php endif; ?>
                        <div class="info">
                            <h3><?= htmlspecialchars($m['nome'] ?? '') ?></h3>
                            <p><strong>Código:</strong> <?= htmlspecialchars($m['codigo'] ?? '') ?></p>
                            <p><strong>Modelo:</strong> <?= htmlspecialchars($m['modelo'] ?? '') ?></p>
                            <p><strong>Fabricante:</strong> <?= htmlspecialchars($m['fabricante'] ?? '') ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars($m['statusMaquina'] ?? '') ?></p>
                        </div>
                        <div class="acoes">
                            <a href="adicionar_maquinas.php?id=<?= (int) $m['id'] ?>"><img src="../Imagens/editar.png" alt="Editar"></a>
                            <a href="#" class="ver-mais"
                               data-id="<?= (int) $m['id'] ?>"
                               data-nome="<?= htmlspecialchars($m['nome'] ?? '', ENT_QUOTES) ?>"
                               data-codigo="<?= htmlspecialchars($m['codigo'] ?? '', ENT_QUOTES) ?>"
                               data-modelo="<?= htmlspecialchars($m['modelo'] ?? '', ENT_QUOTES) ?>"
                               data-fabricante="<?= htmlspecialchars($m['fabricante'] ?? '', ENT_QUOTES) ?>"
                               data-numero_serie="<?= htmlspecialchars($m['numeroSerie'] ?? '', ENT_QUOTES) ?>"
                               data-data_aquisicao="<?= htmlspecialchars($m['dataAquisicao'] ?? '', ENT_QUOTES) ?>"
                               data-localizacao="<?= htmlspecialchars($m['localizacao'] ?? '', ENT_QUOTES) ?>"
                               data-status_maquina="<?= htmlspecialchars($m['statusMaquina'] ?? '', ENT_QUOTES) ?>"
                               data-descricao="<?= htmlspecialchars($m['descricao'] ?? '', ENT_QUOTES) ?>"></a>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="sem-itens"><p>Nenhuma máquina cadastrada no momento.</p><img src="../Imagens/nada_encontrado4.png" alt="Sem máquinas"></div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>
<div id="modalMaquina" class="modal"><div class="modal-content">
    <span class="fechar">&times;</span><h2 id="modalNome"></h2>
    <p><strong>Código:</strong> <span id="modalCodigo"></span></p><p><strong>Modelo:</strong> <span id="modalModelo"></span></p>
    <p><strong>Fabricante:</strong> <span id="modalFabricante"></span></p><p><strong>Nº de Série:</strong> <span id="modalNumeroSerie"></span></p>
    <p><strong>Data de Aquisição:</strong> <span id="modalDataAquisicao"></span></p><p><strong>Localização:</strong> <span id="modalLocalizacao"></span></p>
    <p><strong>Status:</strong> <span id="modalStatus"></span></p><p><strong>Descrição:</strong> <span id="modalDescricao"></span></p>
    <div class="botoes-modal"><button id="btnEditar" class="btn-editar">Editar</button><button class="btn-fechar" onclick="fecharModal()">Fechar</button></div>
</div></div>
<script>
const modal = document.getElementById('modalMaquina');
const fechar = document.querySelector('.fechar');
const btnEditar = document.getElementById('btnEditar');
function abrirModal(m) {
    const campos = {Nome:'nome', Codigo:'codigo', Modelo:'modelo', Fabricante:'fabricante', NumeroSerie:'numero_serie', DataAquisicao:'data_aquisicao', Localizacao:'localizacao', Status:'status_maquina', Descricao:'descricao'};
    Object.entries(campos).forEach(([label, key]) => { document.getElementById('modal'+label).textContent = m[key] || ''; });
    btnEditar.onclick = () => window.location.href = `adicionar_maquinas.php?id=${m.id}`;
    modal.style.display = 'flex';
}
function fecharModal(){ modal.style.display='none'; }
fechar.onclick=fecharModal; window.onclick=e=>{if(e.target===modal)fecharModal();};
document.querySelectorAll('.acoes a.ver-mais').forEach(btn=>btn.addEventListener('click',e=>{e.preventDefault();abrirModal(btn.dataset);}));
<?php if ($tipo_usuario === 'ADM'): ?>
const btnRemover=document.getElementById('btnRemover'); const formRemover=document.getElementById('formRemover'); let modoRemover=false;
btnRemover.addEventListener('click',()=>{const container=document.querySelector('.lista-itens');if(!modoRemover){modoRemover=true;container.classList.add('remocao-ativa');btnRemover.textContent='CONFIRMAR REMOÇÃO';return;}const selecionados=[...container.querySelectorAll('.checkbox-item')].filter(c=>c.checked);if(!selecionados.length){alert('Selecione pelo menos uma máquina para remover.');return;}if(confirm('Tem certeza que deseja excluir as máquinas selecionadas?'))formRemover.submit();});
<?php endif; ?>
</script>
</body></html>
