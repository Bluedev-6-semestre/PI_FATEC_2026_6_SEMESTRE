<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-29 days'));
$start = isset($_GET['inicio']) ? $_GET['inicio'] : $defaultStart;
$end = isset($_GET['fim']) ? $_GET['fim'] : $today;
$searchValue = isset($_GET['busca']) ? $_GET['busca'] : '';

if (!is_string($start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
    !checkdate((int) substr($start, 5, 2), (int) substr($start, 8, 2), (int) substr($start, 0, 4))) {
    api_respond(['erro' => 'Data inicial inválida.'], 422);
}
if (!is_string($end) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ||
    !checkdate((int) substr($end, 5, 2), (int) substr($end, 8, 2), (int) substr($end, 0, 4))) {
    api_respond(['erro' => 'Data final inválida.'], 422);
}
if ($end < $start) {
    api_respond(['erro' => 'A data final deve ser igual ou posterior à inicial.'], 422);
}
if (!is_string($searchValue)) {
    api_respond(['erro' => 'O filtro de busca deve ser um texto.'], 422);
}
$search = trim($searchValue);

$conditions = ['o.data_criacao >= :inicio', 'o.data_criacao <= :fim'];
$params = [
    ':inicio' => $start . ' 00:00:00',
    ':fim' => $end . ' 23:59:59'
];
if ($search !== '') {
    $conditions[] = '(o.titulo LIKE :busca_titulo OR o.tipo LIKE :busca_tipo '
        . 'OR o.status LIKE :busca_status)';
    $pattern = '%' . $search . '%';
    $params[':busca_titulo'] = $pattern;
    $params[':busca_tipo'] = $pattern;
    $params[':busca_status'] = $pattern;
}
if ($usuario['tipo'] !== 'ADM') {
    $conditions[] = 'o.usuario_id = :usuario_id';
    $params[':usuario_id'] = $usuario['id'];
}
$where = ' WHERE ' . implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    'SELECT o.id, o.titulo, o.tipo, o.status, o.descricao, o.data_criacao '
    . 'FROM ocorrencias o' . $where . ' ORDER BY o.data_criacao DESC'
);
$stmt->execute($params);
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtType = $pdo->prepare(
    'SELECT o.tipo AS categoria, COUNT(*) AS total FROM ocorrencias o'
    . $where . ' GROUP BY o.tipo ORDER BY total DESC, o.tipo ASC LIMIT 5'
);
$stmtType->execute($params);
$byType = $stmtType->fetchAll(PDO::FETCH_ASSOC);

$stmtStatus = $pdo->prepare(
    'SELECT o.status AS categoria, COUNT(*) AS total FROM ocorrencias o'
    . $where . ' GROUP BY o.status ORDER BY total DESC, o.status ASC LIMIT 5'
);
$stmtStatus->execute($params);
$byStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

$stmtMonth = $pdo->prepare(
    'SELECT mes, total FROM ('
    . 'SELECT DATE_FORMAT(o.data_criacao, \'%Y-%m\') AS mes, COUNT(*) AS total '
    . 'FROM ocorrencias o' . $where
    . ' GROUP BY mes ORDER BY mes DESC LIMIT 5'
    . ') AS ultimos_meses ORDER BY mes ASC'
);
$stmtMonth->execute($params);
$byMonth = $stmtMonth->fetchAll(PDO::FETCH_ASSOC);

$normalizeCounts = function ($rows) {
    return array_map(function ($row) {
        return [
            'categoria' => $row['categoria'],
            'total' => (int) $row['total']
        ];
    }, $rows);
};
$byMonth = array_map(function ($row) {
    return ['mes' => $row['mes'], 'total' => (int) $row['total']];
}, $byMonth);
$occurrences = array_map(function ($row) {
    $row['id'] = (int) $row['id'];
    return $row;
}, $occurrences);

api_respond([
    'inicio' => $start,
    'fim' => $end,
    'ocorrencias' => $occurrences,
    'por_tipo' => $normalizeCounts($byType),
    'por_status' => $normalizeCounts($byStatus),
    'por_mes' => $byMonth
]);
