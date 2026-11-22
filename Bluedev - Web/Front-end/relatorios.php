<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$tipo_usuario = current_user_type();
$busca = trim($_GET['busca'] ?? '');
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-d');
$ocorrencias = [];
$dadosTipo = [];
$dadosStatus = [];
$dadosMes = [];
$erro = null;

try {
    $resposta = api_request('GET', '/api/ocorrencias');
    $contagemTipo = [];
    $contagemStatus = [];
    $contagemMes = [];
    foreach (is_array($resposta) ? $resposta : [] as $ocorrencia) {
        if ($tipo_usuario !== 'ADM' && (int) ($ocorrencia['usuarioId'] ?? 0) !== current_user_id()) continue;
        $data = substr((string) ($ocorrencia['dataCriacao'] ?? ''), 0, 10);
        if ($data === '' || $data < $data_inicio || $data > $data_fim) continue;
        if ($busca !== '') {
            $texto = ($ocorrencia['titulo'] ?? '') . ' ' . ($ocorrencia['tipo'] ?? '') . ' ' . ($ocorrencia['status'] ?? '');
            if (stripos($texto, $busca) === false) continue;
        }
        $ocorrencias[] = $ocorrencia;
        $tipo = (string) ($ocorrencia['tipo'] ?? 'Não informado');
        $status = (string) ($ocorrencia['status'] ?? 'Não informado');
        $mes = substr($data, 0, 7);
        $contagemTipo[$tipo] = ($contagemTipo[$tipo] ?? 0) + 1;
        $contagemStatus[$status] = ($contagemStatus[$status] ?? 0) + 1;
        $contagemMes[$mes] = ($contagemMes[$mes] ?? 0) + 1;
    }
    usort($ocorrencias, static fn($a, $b) => strcmp((string) ($b['dataCriacao'] ?? ''), (string) ($a['dataCriacao'] ?? '')));
    foreach ($contagemTipo as $tipo => $total) $dadosTipo[] = ['tipo' => $tipo, 'total' => $total];
    foreach ($contagemStatus as $status => $total) $dadosStatus[] = ['status' => $status, 'total' => $total];
    ksort($contagemMes);
    foreach ($contagemMes as $mes => $total) $dadosMes[] = ['mes' => $mes, 'total' => $total];
} catch (Throwable $exception) {
    $erro = api_user_message($exception);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatórios</title>
<link rel="stylesheet" href="style/style_base.css">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawCharts);

function drawCharts() {
    drawTipo();
    drawStatus();
    drawMes();
}

function drawTipo() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Tipo');
    data.addColumn('number', 'Total');
    data.addRows(<?= json_encode(array_map(static fn($d) => [$d['tipo'], (int) $d['total']], $dadosTipo), JSON_UNESCAPED_UNICODE) ?>);
    var chart = new google.visualization.PieChart(document.getElementById('chart_tipo'));
    chart.draw(data, {title: 'Ocorrências por Tipo', height: 300, backgroundColor: 'transparent', chartArea:{width:'85%',height:'75%'}});
}

function drawStatus() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Status');
    data.addColumn('number', 'Total');
    data.addRows(<?= json_encode(array_map(static fn($d) => [$d['status'], (int) $d['total']], $dadosStatus), JSON_UNESCAPED_UNICODE) ?>);
    var chart = new google.visualization.ColumnChart(document.getElementById('chart_status'));
    chart.draw(data, {
        title: 'Ocorrências por Status',
        height: 300,
        legend: {position: 'none'},
        backgroundColor: 'transparent',
        chartArea:{width:'85%',height:'75%'},
        colors: ['#2f5597']
    });
}

function drawMes() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Mês');
    data.addColumn('number', 'Total');
    data.addRows(<?= json_encode(array_map(static fn($d) => [$d['mes'], (int) $d['total']], $dadosMes), JSON_UNESCAPED_UNICODE) ?>);
    var chart = new google.visualization.LineChart(document.getElementById('chart_mes'));
    chart.draw(data, {
        title: 'Ocorrências por Mês',
        curveType: 'function',
        height: 300,
        backgroundColor: 'transparent',
        chartArea:{width:'85%',height:'75%'},
        colors: ['#007acc']
    });
}
window.addEventListener('resize', drawCharts);
</script>

<style>
/* ===== Melhorias visuais para a página de relatórios ===== */

.titulo-pagina {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

/* Barra de filtro aprimorada */
.filtro {
    display: flex;
    align-items: center;
    gap: 10px;
    background-color: #e6e6e6;
    padding: 10px 15px;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

.filtro input[type="text"] {
    flex: 1;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #bbb;
    outline: none;
}

.filtro label {
    font-size: 14px;
    font-weight: 500;
}

.filtro input[type="date"] {
    padding: 6px 8px;
    border-radius: 8px;
    border: 1px solid #bbb;
}

.filtro button {
    background-color: #2f5597;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 15px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}

.filtro button:hover {
    background-color: #20457a;
    box-shadow: 0 0 6px rgba(32,69,122,0.5);
}

/* Gráficos */
.graficos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* Agora com fundo branco e sombra suave */
.grafico {
    background-color: #fff;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

/* Cards de ocorrências */
.item-card {
    background-color: #f5f5f5;
    border-radius: 15px;
    padding: 15px 20px;
    margin-bottom: 15px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

.item-card h3 {
    color: #2f5597;
}

.sem-itens img {
    width: 120px;
    opacity: 0.8;
}
</style>
</head>

<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="logout.php" class="logout-icon"><img src="../Imagens/icone_sair.png" alt="Sair"></a>
</div>

<div class="container">
    <aside class="sidebar">
        <ul>
            <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
            <?php if ($tipo_usuario === 'ADM'): ?>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
                <li><a href="usuarios.php"><img src="../Imagens/perfil.png" alt="Usuários"> Usuários</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php" class="ativo"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <?php if ($erro): ?><p class="mensagem-erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <div class="titulo-pagina">
            <h2>RELATÓRIOS</h2>
            <form method="GET" class="filtro">
                <input type="text" name="busca" placeholder="Buscar por título, tipo ou status..." value="<?= htmlspecialchars($busca) ?>">
                <label>De: <input type="date" name="inicio" value="<?= htmlspecialchars($data_inicio) ?>"></label>
                <label>Até: <input type="date" name="fim" value="<?= htmlspecialchars($data_fim) ?>"></label>
                <button type="submit">FILTRAR</button>
            </form>
        </div>

        <div class="graficos">
            <div id="chart_tipo" class="grafico"></div>
            <div id="chart_status" class="grafico"></div>
            <div id="chart_mes" class="grafico"></div>
        </div>

        <div class="lista-itens">
            <?php if (!empty($ocorrencias)): ?>
                <?php foreach ($ocorrencias as $o): ?>
                    <div class="item-card">
                        <div class="info">
                            <h3><?= htmlspecialchars($o['titulo']) ?></h3>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars($o['tipo']) ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars($o['status']) ?></p>
                            <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($o['dataCriacao'])) ?></p>
                            <p><strong>Descrição:</strong> <?= htmlspecialchars($o['descricao']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sem-itens">
                    <p>Nenhuma ocorrência encontrada no período selecionado.</p>
                    <img src="../Imagens/nada_encontrado2.png" alt="Sem dados">
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
