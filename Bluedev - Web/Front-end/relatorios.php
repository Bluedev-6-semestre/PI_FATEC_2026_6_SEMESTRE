<?php
session_start();
require 'bd.php';

$tipo_usuario = $_SESSION['tipo_usuario'] ?? ''; 
$usuario_id   = $_SESSION['usuario_id'] ?? 0;

// Filtros
$busca = $_GET['busca'] ?? '';
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-d');

// Monta SQL base
$where = "WHERE o.data_criacao BETWEEN :inicio AND :fim";
$params = [
    ':inicio' => $data_inicio . ' 00:00:00',
    ':fim' => $data_fim . ' 23:59:59'
];

if ($busca !== '') {
    $where .= " AND (o.titulo LIKE :busca OR o.tipo LIKE :busca OR o.status LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

if ($tipo_usuario !== 'ADM') {
    $where .= " AND o.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}

// Busca ocorrências
try {
    $stmt = $pdo->prepare("SELECT * FROM ocorrencias o $where ORDER BY o.data_criacao DESC");
    $stmt->execute($params);
    $ocorrencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar ocorrências: " . $e->getMessage());
}

// Dados para gráficos
$stmtTipo = $pdo->prepare("SELECT o.tipo, COUNT(*) as total FROM ocorrencias o $where GROUP BY o.tipo");
$stmtTipo->execute($params);
$dadosTipo = $stmtTipo->fetchAll(PDO::FETCH_ASSOC);

$stmtStatus = $pdo->prepare("SELECT o.status, COUNT(*) as total FROM ocorrencias o $where GROUP BY o.status");
$stmtStatus->execute($params);
$dadosStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

$stmtMes = $pdo->prepare("SELECT DATE_FORMAT(o.data_criacao, '%Y-%m') as mes, COUNT(*) as total FROM ocorrencias o $where GROUP BY mes ORDER BY mes");
$stmtMes->execute($params);
$dadosMes = $stmtMes->fetchAll(PDO::FETCH_ASSOC);
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
    data.addRows([
        <?php foreach ($dadosTipo as $d): ?>
        ['<?= addslashes($d['tipo']) ?>', <?= (int)$d['total'] ?>],
        <?php endforeach; ?>
    ]);
    var chart = new google.visualization.PieChart(document.getElementById('chart_tipo'));
    chart.draw(data, {title: 'Ocorrências por Tipo', height: 300, backgroundColor: 'transparent', chartArea:{width:'85%',height:'75%'}});
}

function drawStatus() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', 'Status');
    data.addColumn('number', 'Total');
    data.addRows([
        <?php foreach ($dadosStatus as $d): ?>
        ['<?= addslashes($d['status']) ?>', <?= (int)$d['total'] ?>],
        <?php endforeach; ?>
    ]);
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
    data.addRows([
        <?php foreach ($dadosMes as $d): ?>
        ['<?= $d['mes'] ?>', <?= (int)$d['total'] ?>],
        <?php endforeach; ?>
    ]);
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
.fornecedor-card {
    background-color: #f5f5f5;
    border-radius: 15px;
    padding: 15px 20px;
    margin-bottom: 15px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

.fornecedor-card h3 {
    color: #2f5597;
}

.sem-fornecedores img {
    width: 120px;
    opacity: 0.8;
}
</style>
</head>

<body>
<div class="navbar">
    <img src="../Imagens/logo_cliente.jpeg" alt="Logo Cliente" class="logo-cliente">
    <a href="index.php" class="logout-icon"><img src="../Imagens/icone_sair.png" alt="Sair"></a>
</div>

<div class="container">
    <aside class="sidebar">
        <ul>
            <li><a href="ocorrencias.php"><img src="../Imagens/ocorrencia_icone.png" alt="Ocorrências"> Ocorrências</a></li>
            <?php if ($tipo_usuario === 'ADM'): ?>
                <li><a href="fornecedores.php"><img src="../Imagens/fornecedor_icone.png" alt="Fornecedores"> Fornecedores</a></li>
                <li><a href="funcionarios.php"><img src="../Imagens/func_icone.png" alt="Funcionários"> Funcionários</a></li>
                <li><a href="maquinas.php"><img src="../Imagens/maquina_icone.png" alt="Máquinas"> Máquinas</a></li>
            <?php endif; ?>
            <li><a href="relatorios.php" class="ativo"><img src="../Imagens/relatorio_icone.png" alt="Relatórios"> Relatórios</a></li>
        </ul>
    </aside>

    <main class="main-content">
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

        <div class="lista-fornecedores">
            <?php if (!empty($ocorrencias)): ?>
                <?php foreach ($ocorrencias as $o): ?>
                    <div class="fornecedor-card">
                        <div class="info">
                            <h3><?= htmlspecialchars($o['titulo']) ?></h3>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars($o['tipo']) ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars($o['status']) ?></p>
                            <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($o['data_criacao'])) ?></p>
                            <p><strong>Descrição:</strong> <?= htmlspecialchars($o['descricao']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sem-fornecedores">
                    <p>Nenhuma ocorrência encontrada no período selecionado.</p>
                    <img src="../Imagens/nada_encontrado2.png" alt="Sem dados">
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
