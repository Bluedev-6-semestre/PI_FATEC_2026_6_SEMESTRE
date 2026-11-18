<?php
require_once __DIR__ . '/auth.php';
auth_require_admin();
auth_require_post();

if (empty($_POST['maquinas']) || !is_array($_POST['maquinas'])) {
    die("Nenhuma máquina selecionada para remoção.");
}

$ids = array_values(array_filter(array_map('intval', $_POST['maquinas']), fn($v) => $v > 0));
if (!$ids) {
    die("Nenhuma máquina selecionada para remoção.");
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Mesma regra da API: máquina vinculada a ocorrências não pode ser removida.
    $check = $pdo->prepare("SELECT COUNT(*) FROM ocorrencias WHERE maquina_id IN ($placeholders)");
    $check->execute($ids);
    if ((int) $check->fetchColumn() > 0) {
        die("Não é possível remover máquinas vinculadas a ocorrências. <a href=\"maquinas.php\">Voltar</a>");
    }

    $stmt = $pdo->prepare("DELETE FROM maquinas WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    header("Location: maquinas.php");
    exit();

} catch (PDOException $e) {
    die("Erro ao remover máquinas: " . $e->getMessage());
}
