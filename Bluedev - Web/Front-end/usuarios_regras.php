<?php
/**
 * Gerenciamento de usuários (tela web) — conversa com o user-service pelo gateway.
 * Exige auth.php já carregado. As regras de negócio principais (nome único, não
 * ficar sem administrador ativo, permissões válidas) ficam no user-service; aqui
 * ficam a validação do formulário e as proteções da sessão (não mexer na própria conta).
 */

// Valida e normaliza os dados do formulário. $id = null ao criar. Retorna [erro|null, dados].
function usuarios_validar($pdo, array $in, $id = null)
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

    $ativo = filter_var($in['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
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
                return $erro('Permissão desconhecida.');
            }
        }
        $permissoes = array_values(array_unique($enviadas));
    }

    // Vínculo opcional com um funcionário cadastrado (tabela funcionarios do web).
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

// Monta o corpo JSON esperado pelo user-service.
function usuarios_payload(array $d)
{
    $p = [
        'usuario' => $d['usuario'],
        'tipoUsuario' => auth_perfil_para_tipo($d['tipo']),
        'nome' => $d['nome'],
        'ativo' => (bool) $d['ativo'],
        'funcionarioId' => $d['funcionario_id']
    ];
    if ($d['permissoes'] !== null) {
        $p['permissoes'] = $d['permissoes'];
    }
    if ($d['senha'] !== '') {
        $p['senha'] = $d['senha'];
    }
    return $p;
}

// Falha de comunicação com o serviço: mostra a página 503.
function usuarios_checar_disponibilidade(array $r)
{
    if ($r['status'] === 0 || $r['status'] >= 500) {
        auth_negar('Não foi possível falar com o serviço de usuários. Tente novamente em instantes.', 503);
    }
}

// Representação usada pelas telas.
function usuarios_publico(array $u)
{
    $tipo = auth_tipo_para_perfil($u['tipoUsuario'] ?? '');
    return [
        'id' => (int) $u['id'],
        'usuario' => $u['usuario'],
        'nome' => $u['nome'] ?? null,
        'tipo' => $tipo,
        'ativo' => !empty($u['ativo']),
        'permissoes' => $tipo === 'ADM'
            ? array_keys(auth_permissoes_disponiveis())
            : array_values(array_intersect(array_keys(auth_permissoes_disponiveis()), (array) ($u['permissoes'] ?? []))),
        'funcionario_id' => isset($u['funcionarioId']) ? (int) $u['funcionarioId'] : null,
        'funcionario_nome' => null
    ];
}

// Lista com filtros: q (usuário/nome), tipo (ADM|FUNC), ativo ('1'|'0').
function usuarios_listar($pdo, $q, $tipo, $ativo)
{
    $query = [];
    if ($q !== '') {
        $query['q'] = $q;
    }
    if ($tipo === 'ADM' || $tipo === 'FUNC') {
        $query['tipo'] = auth_perfil_para_tipo($tipo);
    }
    if ($ativo === '1' || $ativo === '0') {
        $query['ativo'] = $ativo === '1' ? 'true' : 'false';
    }

    $r = gateway_request('GET', '/api/usuarios', null, $query);
    usuarios_checar_disponibilidade($r);
    if ($r['status'] !== 200 || !is_array($r['body'])) {
        auth_negar('Não foi possível listar os usuários: ' . ($r['erro'] ?? 'erro desconhecido'), 502);
    }

    $lista = array_map('usuarios_publico', $r['body']);

    // Nome do funcionário vinculado (tabela funcionarios do web).
    $ids = array_values(array_unique(array_filter(array_column($lista, 'funcionario_id'))));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE id IN ($ph)");
        $st->execute($ids);
        $nomes = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($lista as &$u) {
            $u['funcionario_nome'] = $nomes[$u['funcionario_id']] ?? null;
        }
        unset($u);
    }

    return $lista;
}

// Um usuário (ou null se não existir).
function usuarios_buscar($pdo, $id)
{
    $r = gateway_request('GET', '/api/usuarios/' . (int) $id);
    usuarios_checar_disponibilidade($r);
    if ($r['status'] === 404) {
        return null;
    }
    if ($r['status'] !== 200 || !is_array($r['body'])) {
        auth_negar('Não foi possível carregar o usuário: ' . ($r['erro'] ?? 'erro desconhecido'), 502);
    }
    return usuarios_publico($r['body']);
}

// Proteção da sessão: o administrador logado não pode rebaixar nem desativar a si mesmo.
function usuarios_verificar_protecao($pdo, array $logado, $id, array $novo)
{
    if ($id === $logado['id'] && ($novo['tipo'] !== 'ADM' || !$novo['ativo'])) {
        return 'Você não pode rebaixar nem desativar a sua própria conta.';
    }
    return null;
}

// Cria um usuário. Retorna [erro|null, id|null].
function usuarios_criar($pdo, array $d)
{
    $r = gateway_request('POST', '/api/usuarios', usuarios_payload($d));
    usuarios_checar_disponibilidade($r);
    if ($r['status'] !== 201 || !is_array($r['body'])) {
        return [$r['erro'] ?? 'Não foi possível criar o usuário.', null];
    }
    return [null, (int) $r['body']['id']];
}

// Atualiza um usuário. Retorna erro|null.
function usuarios_atualizar($pdo, $id, array $d)
{
    $r = gateway_request('PUT', '/api/usuarios/' . (int) $id, usuarios_payload($d));
    usuarios_checar_disponibilidade($r);
    return $r['status'] === 200 ? null : ($r['erro'] ?? 'Não foi possível salvar o usuário.');
}

// Mensagem de bloqueio se o usuário não puder ser excluído (ou null se puder).
function usuarios_motivo_bloqueio_exclusao($pdo, array $logado, $id)
{
    if ($id === $logado['id']) {
        return 'Você não pode excluir a sua própria conta.';
    }
    $u = usuarios_buscar($pdo, $id);
    if ($u === null) {
        return 'Usuário não encontrado.';
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

// Exclui um usuário. Retorna erro|null.
function usuarios_excluir($pdo, $id)
{
    $r = gateway_request('DELETE', '/api/usuarios/' . (int) $id);
    usuarios_checar_disponibilidade($r);
    return in_array($r['status'], [200, 204], true) ? null : ($r['erro'] ?? 'Não foi possível excluir o usuário.');
}
