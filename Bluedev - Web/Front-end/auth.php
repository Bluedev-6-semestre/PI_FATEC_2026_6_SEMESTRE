<?php
/**
 * Autenticação, perfis e permissões (compartilhado pelas páginas PHP e pelas APIs).
 *
 * - Perfil (nível de acesso): 'ADM' (administrador) ou 'FUNC' (funcionário).
 *   No banco, a coluna usuarios.tipo_usuario guarda ADMIN / FUNCIONARIO
 *   (mesmo padrão do user-service).
 * - Permissões: o ADM tem todas. O FUNC recebe somente as marcadas no cadastro
 *   do usuário (usuarios.permissoes, lista JSON).
 * - A cada requisição o usuário é relido do banco: desativar um usuário ou
 *   mudar suas permissões vale imediatamente, sem esperar o logout.
 */

require_once __DIR__ . '/bd.php';

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

// Permissões de um funcionário recém-criado (ou sem permissões definidas).
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

function auth_decodificar_permissoes($json)
{
    if ($json === null || $json === '') {
        return auth_permissoes_padrao();
    }
    $lista = json_decode($json, true);
    if (!is_array($lista)) {
        return auth_permissoes_padrao();
    }
    return array_values(array_intersect(array_keys(auth_permissoes_disponiveis()), $lista));
}

/**
 * Usuário logado (lido do banco) ou null se não houver sessão válida.
 * Retorna: id, usuario, nome, tipo ('ADM'|'FUNC'), permissoes (lista).
 */
function auth_user()
{
    global $pdo;
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    $id = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
    if ($id < 1) {
        return $cache = null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, usuario, nome, tipo_usuario, ativo, permissoes FROM usuarios WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int) $row['ativo'] !== 1) {
        $_SESSION = [];
        return $cache = null;
    }

    $tipo = auth_tipo_para_perfil($row['tipo_usuario']);
    // Mantém a sessão coerente com o banco (outras partes do sistema leem estas chaves).
    $_SESSION['usuario'] = $row['usuario'];
    $_SESSION['tipo_usuario'] = $tipo;

    return $cache = [
        'id'         => (int) $row['id'],
        'usuario'    => $row['usuario'],
        'nome'       => $row['nome'] ?: $row['usuario'],
        'tipo'       => $tipo,
        'permissoes' => $tipo === 'ADM'
            ? array_keys(auth_permissoes_disponiveis())
            : auth_decodificar_permissoes($row['permissoes'])
    ];
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

// ---------------------------------------------------------------------------
// Páginas web
// ---------------------------------------------------------------------------

function auth_negar($mensagem, $status = 403)
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Acesso negado</title>'
        . '<link rel="stylesheet" href="style/login.css"></head><body style="display:flex;align-items:center;'
        . 'justify-content:center;height:100vh;font-family:Arial,sans-serif"><div style="text-align:center">'
        . '<h2>Acesso negado</h2><p>' . htmlspecialchars($mensagem) . '</p>'
        . '<p><a href="javascript:history.back()">Voltar</a></p></div></body></html>';
    exit();
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
// CSRF (formulários de usuários e ações administrativas sensíveis)
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
// Login (usado por login.php e api/login.php)
// ---------------------------------------------------------------------------

/**
 * Valida usuário e senha no banco. Retorna o registro do usuário ou null.
 * Contas desativadas não entram. A mensagem de erro é sempre a mesma
 * (não revela se o usuário existe).
 */
function auth_autenticar($usuario, $senha)
{
    global $pdo;
    $usuario = trim((string) $usuario);
    if ($usuario === '' || $senha === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, usuario, nome, senha, tipo_usuario, ativo FROM usuarios WHERE usuario = :usuario'
    );
    $stmt->execute([':usuario' => $usuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Faz o hash mesmo se o usuário não existir, para não vazar tempo de resposta.
    $hash = $row ? $row['senha'] : '$2y$10$qD0etc8hD0H/jKh8XfyIUORsHukpFXbHEfDfcL1lE5rllXkVnILuG';
    $ok = password_verify($senha, $hash);

    if (!$row || !$ok || (int) $row['ativo'] !== 1) {
        return null;
    }

    if (password_needs_rehash($row['senha'], PASSWORD_DEFAULT)) {
        $up = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
        $up->execute([':senha' => password_hash($senha, PASSWORD_DEFAULT), ':id' => $row['id']]);
    }

    return $row;
}

function auth_iniciar_sessao($row)
{
    session_regenerate_id(true);
    $tipo = auth_tipo_para_perfil($row['tipo_usuario']);
    $_SESSION['loggedin'] = true;
    $_SESSION['usuario'] = $row['usuario'];
    $_SESSION['usuario_id'] = (int) $row['id'];
    $_SESSION['tipo_usuario'] = $tipo;
    return $tipo;
}

// Escapa curingas do LIKE para buscas por texto.
function auth_like($texto)
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto) . '%';
}
