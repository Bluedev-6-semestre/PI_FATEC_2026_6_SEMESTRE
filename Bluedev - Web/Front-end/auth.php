<?php
/**
 * Autenticação, perfis e permissões do Backoffice web.
 *
 * Os usuários ficam no user-service (acessado pelo gateway). Aqui o web:
 *  - faz o login chamando POST /api/usuarios/login;
 *  - guarda só o id na sessão;
 *  - a cada requisição relê o usuário no user-service (GET /api/usuarios/{id}),
 *    então desativar uma conta ou mudar permissões vale imediatamente.
 *
 * Perfil: 'ADM' (administrador) ou 'FUNC' (funcionário). No serviço o campo
 * tipoUsuario é ADMIN / FUNCIONARIO. O ADM tem todas as permissões.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/gateway.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Permissões que podem ser concedidas a um funcionário.
function auth_permissoes_disponiveis()
{
    return [
        'ver_cadastros'         => 'Visualizar funcionários, máquinas e fornecedores',
        'registrar_ocorrencias' => 'Registrar ocorrências',
        'gerenciar_ocorrencias' => 'Acompanhar e atualizar as próprias ocorrências',
        'ver_relatorios'        => 'Consultar relatórios'
    ];
}

function auth_permissoes_padrao()
{
    return array_keys(auth_permissoes_disponiveis());
}

function auth_tipo_para_perfil($tipoUsuario)
{
    return strtoupper((string) $tipoUsuario) === 'ADMIN' ? 'ADM' : 'FUNC';
}

function auth_perfil_para_tipo($perfil)
{
    return $perfil === 'ADM' ? 'ADMIN' : 'FUNCIONARIO';
}

function auth_negar($mensagem, $status = 403)
{
    http_response_code($status);
    $titulo = $status === 503 ? 'Serviço indisponível' : 'Acesso negado';
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>' . $titulo . '</title>'
        . '<link rel="stylesheet" href="style/login.css"></head><body style="display:flex;align-items:center;'
        . 'justify-content:center;height:100vh;font-family:Arial,sans-serif"><div style="text-align:center">'
        . '<h2>' . $titulo . '</h2><p>' . htmlspecialchars($mensagem) . '</p>'
        . '<p><a href="javascript:history.back()">Voltar</a></p></div></body></html>';
    exit();
}

// Converte a resposta do user-service no formato usado pelo web.
function auth_montar_usuario(array $u)
{
    $tipo = auth_tipo_para_perfil($u['tipoUsuario'] ?? '');
    $todas = auth_permissoes_padrao();
    return [
        'id'         => (int) $u['id'],
        'usuario'    => $u['usuario'],
        'nome'       => !empty($u['nome']) ? $u['nome'] : $u['usuario'],
        'tipo'       => $tipo,
        'permissoes' => $tipo === 'ADM'
            ? $todas
            : array_values(array_intersect($todas, (array) ($u['permissoes'] ?? [])))
    ];
}

/**
 * Usuário logado (lido do user-service) ou null se não houver sessão válida.
 * Retorna: id, usuario, nome, tipo ('ADM'|'FUNC'), permissoes (lista).
 */
function auth_user()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    $id = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
    if ($id < 1) {
        return $cache = null;
    }

    $r = gateway_request('GET', '/api/usuarios/' . $id);
    if ($r['status'] === 0 || $r['status'] >= 500) {
        auth_negar('Não foi possível falar com o serviço de usuários. Tente novamente em instantes.', 503);
    }
    if ($r['status'] !== 200 || !is_array($r['body']) || empty($r['body']['ativo'])) {
        $_SESSION = [];
        return $cache = null;
    }

    $user = auth_montar_usuario($r['body']);
    $_SESSION['usuario'] = $user['usuario'];
    $_SESSION['tipo_usuario'] = $user['tipo'];
    return $cache = $user;
}

function auth_is_admin($user = null)
{
    $user = $user ?: auth_user();
    return $user !== null && $user['tipo'] === 'ADM';
}

function auth_can($permissao, $user = null)
{
    $user = $user ?: auth_user();
    if ($user === null) {
        return false;
    }
    return $user['tipo'] === 'ADM' || in_array($permissao, $user['permissoes'], true);
}

// Exige login. Devolve o usuário.
function auth_require_login()
{
    $user = auth_user();
    if ($user === null) {
        header('Location: index.php');
        exit();
    }
    return $user;
}

// Exige perfil ADM.
function auth_require_admin()
{
    $user = auth_require_login();
    if ($user['tipo'] !== 'ADM') {
        auth_negar('Esta área é exclusiva para administradores.');
    }
    return $user;
}

// Exige uma permissão específica (ADM sempre passa).
function auth_require_permission($permissao)
{
    $user = auth_require_login();
    if (!auth_can($permissao, $user)) {
        auth_negar('Você não tem permissão para acessar este recurso.');
    }
    return $user;
}

// Para ações (POST) que alteram dados: só aceita POST.
function auth_require_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Método não permitido.');
    }
}

// ---------------------------------------------------------------------------
// CSRF (formulários de usuários)
// ---------------------------------------------------------------------------

function auth_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(auth_csrf_token()) . '">';
}

function auth_csrf_check()
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        auth_negar('Sessão expirada ou requisição inválida. Recarregue a página e tente novamente.');
    }
}

// ---------------------------------------------------------------------------
// Login (usado por login.php)
// ---------------------------------------------------------------------------

/**
 * Valida usuário e senha no user-service. Retorna os dados do usuário ou null
 * (usuário/senha inválidos ou conta inativa; a mensagem é sempre a mesma).
 */
function auth_autenticar($usuario, $senha)
{
    $usuario = trim((string) $usuario);
    if ($usuario === '' || $senha === '') {
        return null;
    }

    $r = gateway_request('POST', '/api/usuarios/login', ['usuario' => $usuario, 'senha' => $senha]);
    if ($r['status'] === 0 || $r['status'] >= 500) {
        auth_negar('Não foi possível falar com o serviço de usuários. Tente novamente em instantes.', 503);
    }
    if ($r['status'] !== 200 || !is_array($r['body'])) {
        return null;
    }
    return $r['body'];
}

function auth_iniciar_sessao(array $u)
{
    session_regenerate_id(true);
    $tipo = auth_tipo_para_perfil($u['tipoUsuario'] ?? '');
    $_SESSION['loggedin'] = true;
    $_SESSION['usuario'] = $u['usuario'];
    $_SESSION['usuario_id'] = (int) $u['id'];
    $_SESSION['tipo_usuario'] = $tipo;
    return $tipo;
}

// Escapa curingas do LIKE para buscas por texto.
function auth_like($texto)
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';
}
