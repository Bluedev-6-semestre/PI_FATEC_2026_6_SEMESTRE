<?php
/**
 * Regras de negócio do gerenciamento de usuários (usadas pela tela web e pela API).
 * Exige auth.php já carregado ($pdo disponível).
 */

// Valida e normaliza os dados de um usuário.
// $id = null ao criar. Retorna [erro|null, dados].
function usuarios_validar(PDO $pdo, array $in, $id = null)
{
    $erro = function ($msg) { return [$msg, null]; };

    $usuario = isset($in['usuario']) && is_string($in['usuario']) ? trim($in['usuario']) : '';
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)) {
        return $erro('Usuário deve ter de 3 a 50 caracteres (letras, números, ponto, hífen ou sublinhado).');
    }

    $nome = isset($in['nome']) && is_string($in['nome']) ? trim($in['nome']) : '';
    if (mb_strlen($nome) > 100) {
        return $erro('O nome deve ter até 100 caracteres.');
    }

    $tipo = isset($in['tipo']) && is_string($in['tipo']) ? strtoupper(trim($in['tipo'])) : '';
    if (!in_array($tipo, ['ADM', 'FUNC'], true)) {
        return $erro('Perfil inválido. Use ADM ou FUNC.');
    }

    $ativoIn = $in['ativo'] ?? true;
    $ativo = filter_var($ativoIn, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($ativo === null) {
        return $erro('Situação inválida.');
    }

    $senha = isset($in['senha']) && is_string($in['senha']) ? $in['senha'] : '';
    if ($id === null && $senha === '') {
        return $erro('Informe uma senha.');
    }
    if ($senha !== '' && (strlen($senha) < 6 || strlen($senha) > 72)) {
        return $erro('A senha deve ter de 6 a 72 caracteres.');
    }

    // Permissões: só fazem sentido para funcionários.
    $permissoes = null;
    if ($tipo === 'FUNC') {
        $enviadas = $in['permissoes'] ?? [];
        if (!is_array($enviadas)) {
            return $erro('Permissões inválidas.');
        }
        $validas = array_keys(auth_permissoes_disponiveis());
        foreach ($enviadas as $p) {
            if (!is_string($p) || !in_array($p, $validas, true)) {
                return $erro('Permissão desconhecida: ' . (is_string($p) ? $p : '?'));
            }
        }
        $permissoes = array_values(array_unique($enviadas));
    }

    // Vínculo opcional com um funcionário cadastrado.
    $funcionarioId = null;
    if (isset($in['funcionario_id']) && $in['funcionario_id'] !== '' && $in['funcionario_id'] !== null) {
        $funcionarioId = filter_var($in['funcionario_id'], FILTER_VALIDATE_INT);
        if ($funcionarioId === false || $funcionarioId < 1) {
            return $erro('Funcionário inválido.');
        }
        $st = $pdo->prepare('SELECT id FROM funcionarios WHERE id = :id');
        $st->execute([':id' => $funcionarioId]);
        if (!$st->fetch()) {
            return $erro('Funcionário não encontrado.');
        }
        $st = $pdo->prepare('SELECT usuario FROM usuarios WHERE funcionario_id = :f AND id <> :id');
        $st->execute([':f' => $funcionarioId, ':id' => $id ?? 0]);
        if ($outro = $st->fetchColumn()) {
            return $erro('Este funcionário já está vinculado ao usuário "' . $outro . '".');
        }
    }

    // Nome de usuário único.
    $st = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :u AND id <> :id');
    $st->execute([':u' => $usuario, ':id' => $id ?? 0]);
    if ($st->fetch()) {
        return $erro('Já existe um usuário com este nome.');
    }

    return [null, [
        'usuario' => $usuario,
        'nome' => $nome !== '' ? $nome : null,
        'tipo' => $tipo,
        'ativo' => $ativo ? 1 : 0,
        'senha' => $senha,
        'permissoes' => $permissoes,
        'funcionario_id' => $funcionarioId
    ]];
}

function usuarios_total_admins_ativos(PDO $pdo, $excetoId = 0)
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'ADMIN' AND ativo = 1 AND id <> :id"
    );
    $st->execute([':id' => $excetoId]);
    return (int) $st->fetchColumn();
}

// Regras que protegem o próprio administrador logado e o último administrador ativo.
function usuarios_verificar_protecao(PDO $pdo, array $logado, $id, array $novo)
{
    $st = $pdo->prepare('SELECT tipo_usuario, ativo FROM usuarios WHERE id = :id');
    $st->execute([':id' => $id]);
    $atual = $st->fetch(PDO::FETCH_ASSOC);
    if (!$atual) {
        return 'Usuário não encontrado.';
    }
    if ($id === $logado['id'] && ($novo['tipo'] !== 'ADM' || !$novo['ativo'])) {
        return 'Você não pode rebaixar nem desativar a sua própria conta.';
    }
    $eraAdminAtivo = $atual['tipo_usuario'] === 'ADMIN' && (int) $atual['ativo'] === 1;
    $seraAdminAtivo = $novo['tipo'] === 'ADM' && $novo['ativo'];
    if ($eraAdminAtivo && !$seraAdminAtivo && usuarios_total_admins_ativos($pdo, $id) === 0) {
        return 'É necessário manter pelo menos um administrador ativo.';
    }
    return null;
}

function usuarios_criar(PDO $pdo, array $d)
{
    $st = $pdo->prepare(
        'INSERT INTO usuarios (usuario, nome, senha, tipo_usuario, ativo, permissoes, funcionario_id) '
        . 'VALUES (:usuario, :nome, :senha, :tipo, :ativo, :permissoes, :funcionario_id)'
    );
    $st->execute([
        ':usuario' => $d['usuario'],
        ':nome' => $d['nome'],
        ':senha' => password_hash($d['senha'], PASSWORD_DEFAULT),
        ':tipo' => auth_perfil_para_tipo($d['tipo']),
        ':ativo' => $d['ativo'],
        ':permissoes' => $d['permissoes'] === null ? null : json_encode($d['permissoes']),
        ':funcionario_id' => $d['funcionario_id']
    ]);
    return (int) $pdo->lastInsertId();
}

function usuarios_atualizar(PDO $pdo, $id, array $d)
{
    $sql = 'UPDATE usuarios SET usuario = :usuario, nome = :nome, tipo_usuario = :tipo, '
        . 'ativo = :ativo, permissoes = :permissoes, funcionario_id = :funcionario_id';
    $params = [
        ':usuario' => $d['usuario'],
        ':nome' => $d['nome'],
        ':tipo' => auth_perfil_para_tipo($d['tipo']),
        ':ativo' => $d['ativo'],
        ':permissoes' => $d['permissoes'] === null ? null : json_encode($d['permissoes']),
        ':funcionario_id' => $d['funcionario_id'],
        ':id' => $id
    ];
    if ($d['senha'] !== '') {
        $sql .= ', senha = :senha';
        $params[':senha'] = password_hash($d['senha'], PASSWORD_DEFAULT);
    }
    $st = $pdo->prepare($sql . ' WHERE id = :id');
    $st->execute($params);
}

// Mensagem de bloqueio se o usuário não puder ser excluído (ou null se puder).
function usuarios_motivo_bloqueio_exclusao(PDO $pdo, array $logado, $id)
{
    if ($id === $logado['id']) {
        return 'Você não pode excluir a sua própria conta.';
    }
    $st = $pdo->prepare('SELECT tipo_usuario, ativo, usuario FROM usuarios WHERE id = :id');
    $st->execute([':id' => $id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return 'Usuário não encontrado.';
    }
    if ($u['tipo_usuario'] === 'ADMIN' && (int) $u['ativo'] === 1
        && usuarios_total_admins_ativos($pdo, $id) === 0) {
        return 'É necessário manter pelo menos um administrador ativo.';
    }
    foreach (['ocorrencias' => 'ocorrências', 'maquinas' => 'máquinas', 'fornecedores' => 'fornecedores'] as $tabela => $rotulo) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM $tabela WHERE usuario_id = :id");
        $c->execute([':id' => $id]);
        if ((int) $c->fetchColumn() > 0) {
            return 'O usuário "' . $u['usuario'] . '" possui ' . $rotulo
                . ' registradas e não pode ser excluído. Desative a conta em vez de excluir.';
        }
    }
    return null;
}

function usuarios_excluir(PDO $pdo, $id)
{
    $pdo->prepare('DELETE FROM assinaturas WHERE usuario_id = :id')->execute([':id' => $id]);
    $pdo->prepare('UPDATE funcionarios SET usuario_id = NULL WHERE usuario_id = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $id]);
}

// Representação pública (nunca inclui a senha).
function usuarios_publico(array $row)
{
    $tipo = auth_tipo_para_perfil($row['tipo_usuario']);
    return [
        'id' => (int) $row['id'],
        'usuario' => $row['usuario'],
        'nome' => $row['nome'],
        'tipo' => $tipo,
        'ativo' => (int) $row['ativo'] === 1,
        'permissoes' => $tipo === 'ADM'
            ? array_keys(auth_permissoes_disponiveis())
            : auth_decodificar_permissoes($row['permissoes']),
        'funcionario_id' => $row['funcionario_id'] !== null ? (int) $row['funcionario_id'] : null,
        'funcionario_nome' => $row['funcionario_nome'] ?? null,
        'created_at' => $row['created_at'] ?? null
    ];
}

// Lista com filtros: q (usuário/nome), tipo (ADM|FUNC), ativo (1|0).
function usuarios_listar(PDO $pdo, $q, $tipo, $ativo)
{
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(u.usuario LIKE :q1 OR u.nome LIKE :q2)';
        $params[':q1'] = auth_like($q);
        $params[':q2'] = auth_like($q);
    }
    if ($tipo === 'ADM' || $tipo === 'FUNC') {
        $where[] = 'u.tipo_usuario = :tipo';
        $params[':tipo'] = auth_perfil_para_tipo($tipo);
    }
    if ($ativo === '1' || $ativo === '0') {
        $where[] = 'u.ativo = :ativo';
        $params[':ativo'] = (int) $ativo;
    }
    $sql = 'SELECT u.id, u.usuario, u.nome, u.tipo_usuario, u.ativo, u.permissoes, u.funcionario_id, '
        . 'u.created_at, f.nome AS funcionario_nome FROM usuarios u '
        . 'LEFT JOIN funcionarios f ON f.id = u.funcionario_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return array_map('usuarios_publico', $st->fetchAll(PDO::FETCH_ASSOC));
}
