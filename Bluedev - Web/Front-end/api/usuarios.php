<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/usuarios_regras.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

// Gerenciamento de usuários e permissões: exclusivo de administradores.
$logado = api_require_admin();

function api_usuario_id()
{
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de usuário inválido.'], 400);
    }
    return $id;
}

if ($method === 'GET') {
    $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
    $tipo = isset($_GET['tipo']) && is_string($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
    $ativo = isset($_GET['ativo']) && is_string($_GET['ativo']) ? $_GET['ativo'] : '';
    $lista = usuarios_listar($pdo, $q, $tipo, $ativo);
    if (isset($_GET['id'])) {
        $id = api_usuario_id();
        foreach ($lista as $u) {
            if ($u['id'] === $id) {
                api_respond($u);
            }
        }
        api_respond(['erro' => 'Usuário não encontrado.'], 404);
    }
    api_respond($lista);
}

if ($method === 'POST') {
    [$erro, $dados] = usuarios_validar($pdo, api_read_json());
    if ($erro) {
        api_respond(['erro' => $erro], 422);
    }
    api_respond(['id' => usuarios_criar($pdo, $dados)], 201);
}

if ($method === 'PUT') {
    $id = api_usuario_id();
    [$erro, $dados] = usuarios_validar($pdo, api_read_json(), $id);
    if ($erro) {
        api_respond(['erro' => $erro], 422);
    }
    if ($bloqueio = usuarios_verificar_protecao($pdo, $logado, $id, $dados)) {
        api_respond(['erro' => $bloqueio], 409);
    }
    usuarios_atualizar($pdo, $id, $dados);
    api_respond(['mensagem' => 'Usuário atualizado.']);
}

// DELETE
$id = api_usuario_id();
if ($bloqueio = usuarios_motivo_bloqueio_exclusao($pdo, $logado, $id)) {
    api_respond(['erro' => $bloqueio], 409);
}
usuarios_excluir($pdo, $id);
api_respond(['mensagem' => 'Usuário removido.']);
