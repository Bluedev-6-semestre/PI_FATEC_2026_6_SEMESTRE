<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    api_respond(['erro' => 'Método não permitido.'], 405);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

function api_cnpj_digits_valid($cnpj)
{
    if (!is_string($cnpj) || !preg_match('/^\d{14}$/', $cnpj) ||
        preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $cnpj[$i] * $weights[$i];
    }
    $remainder = $sum % 11;
    $firstDigit = $remainder < 2 ? 0 : 11 - $remainder;
    if ((int) $cnpj[12] !== $firstDigit) {
        return false;
    }

    $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum += (int) $cnpj[$i] * $weights[$i];
    }
    $remainder = $sum % 11;
    $secondDigit = $remainder < 2 ? 0 : 11 - $remainder;
    return (int) $cnpj[13] === $secondDigit;
}

function api_format_cnpj($cnpj)
{
    return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.'
        . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-'
        . substr($cnpj, 12, 2);
}

if ($method === 'GET') {
    if (isset($_GET['consultar_cnpj'])) {
        $cnpj = preg_replace('/\D+/', '', (string) $_GET['consultar_cnpj']);
        if (!api_cnpj_digits_valid($cnpj)) {
            api_respond(['erro' => 'Digite um CNPJ válido com 14 números.'], 422);
        }

        $curl = curl_init('https://brasilapi.com.br/api/cnpj/v1/' . $cnpj);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        $response = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            api_respond(
                ['erro' => 'Não foi possível consultar a BrasilAPI. Verifique a conexão e tente novamente.'],
                502
            );
        }
        if ($httpStatus === 404 || $httpStatus === 400) {
            api_respond(['erro' => 'CNPJ não encontrado na BrasilAPI.'], 404);
        }
        if ($httpStatus < 200 || $httpStatus >= 300) {
            api_respond(
                ['erro' => 'A consulta do CNPJ está indisponível. Tente novamente mais tarde.'],
                502
            );
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            api_respond(['erro' => 'A BrasilAPI retornou uma resposta inválida.'], 502);
        }
        $logradouro = isset($data['logradouro']) && is_string($data['logradouro'])
            ? $data['logradouro'] : '';
        $numero = isset($data['numero']) && is_string($data['numero'])
            ? $data['numero'] : '';
        $bairro = isset($data['bairro']) && is_string($data['bairro'])
            ? $data['bairro'] : '';
        $municipio = isset($data['municipio']) && is_string($data['municipio'])
            ? $data['municipio'] : '';
        $uf = isset($data['uf']) && is_string($data['uf']) ? $data['uf'] : '';
        api_respond([
            'nome' => isset($data['razao_social']) && is_string($data['razao_social'])
                ? $data['razao_social'] : '',
            'telefone' => isset($data['ddd_telefone_1']) &&
                is_string($data['ddd_telefone_1']) ? $data['ddd_telefone_1'] : '',
            'endereco' => $logradouro . ', ' . $numero . ' - ' . $bairro
                . ', ' . $municipio . ' - ' . $uf
        ]);
    }

    if ($usuario['tipo'] === 'ADM') {
        $stmt = $pdo->query(
            'SELECT id, nome, cnpj, categoria, telefone, endereco, usuario_id '
            . 'FROM fornecedores ORDER BY id DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, nome, cnpj, categoria, telefone, endereco, usuario_id '
            . 'FROM fornecedores WHERE usuario_id = :usuario_id ORDER BY id DESC'
        );
        $stmt->execute([':usuario_id' => $usuario['id']]);
    }
    api_respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($method === 'POST' || $method === 'PUT') {
    $body = api_read_json();
    $nameValue = isset($body['nome']) ? $body['nome'] : '';
    $cnpjValue = isset($body['cnpj']) ? $body['cnpj'] : '';
    $categoryValue = isset($body['categoria']) ? $body['categoria'] : '';
    $phoneValue = isset($body['telefone']) ? $body['telefone'] : '';
    $addressValue = isset($body['endereco']) ? $body['endereco'] : '';
    $nome = is_string($nameValue) ? trim($nameValue) : '';
    $cnpjInput = is_string($cnpjValue) ? trim($cnpjValue) : '';
    $categoria = is_string($categoryValue) ? trim($categoryValue) : '';
    $telefone = is_string($phoneValue) ? trim($phoneValue) : '';
    $endereco = is_string($addressValue) ? trim($addressValue) : '';
    $cnpj = preg_replace('/\D+/', '', $cnpjInput);

    if (!preg_match('/^.{1,100}$/us', $nome)) {
        api_respond(['erro' => 'Informe o nome da empresa (até 100 caracteres).'], 422);
    }
    if (!api_cnpj_digits_valid($cnpj)) {
        api_respond(['erro' => 'Digite um CNPJ válido com 14 números.'], 422);
    }
    if (!preg_match('/^.{1,50}$/us', $categoria)) {
        api_respond(['erro' => 'Informe a categoria (até 50 caracteres).'], 422);
    }
    if (!preg_match('/^.{1,20}$/us', $telefone)) {
        api_respond(['erro' => 'Informe um telefone com até 20 caracteres.'], 422);
    }
    if (!preg_match('/^.{1,255}$/us', $endereco)) {
        api_respond(['erro' => 'Informe o endereço com até 255 caracteres.'], 422);
    }
    $cnpj = api_format_cnpj($cnpj);

    $values = [
        ':nome' => $nome,
        ':cnpj' => $cnpj,
        ':categoria' => $categoria,
        ':telefone' => $telefone,
        ':endereco' => $endereco
    ];

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO fornecedores (usuario_id, nome, cnpj, categoria, telefone, endereco) '
            . 'VALUES (:usuario_id, :nome, :cnpj, :categoria, :telefone, :endereco)'
        );
        $stmt->execute([':usuario_id' => $usuario['id']] + $values);
        api_respond(['id' => (int) $pdo->lastInsertId()], 201);
    }

    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de fornecedor inválido.'], 400);
    }
    $check = $pdo->prepare(
        'SELECT id FROM fornecedores WHERE id = :id AND usuario_id = :usuario_id'
    );
    $check->execute([':id' => $id, ':usuario_id' => $usuario['id']]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        api_respond(['erro' => 'Fornecedor não encontrado ou sem permissão de acesso.'], 404);
    }
    $values[':id'] = $id;
    $values[':usuario_id'] = $usuario['id'];
    $stmt = $pdo->prepare(
        'UPDATE fornecedores SET nome = :nome, cnpj = :cnpj, categoria = :categoria, '
        . 'telefone = :telefone, endereco = :endereco '
        . 'WHERE id = :id AND usuario_id = :usuario_id'
    );
    $stmt->execute($values);
    api_respond(['mensagem' => 'Fornecedor atualizado.']);
}

if ($method === 'DELETE') {
    if ($usuario['tipo'] !== 'ADM') {
        api_respond(['erro' => 'Somente administradores podem excluir fornecedores.'], 403);
    }
    $id = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        api_respond(['erro' => 'Identificador de fornecedor inválido.'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM fornecedores WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        api_respond(['erro' => 'Fornecedor não encontrado.'], 404);
    }
    api_respond(['mensagem' => 'Fornecedor removido.']);
}

api_respond(['erro' => 'Método não permitido.'], 405);
