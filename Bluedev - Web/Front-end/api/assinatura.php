<?php
require_once __DIR__ . '/bootstrap.php';

function api_subscription_config()
{
    $path = dirname(__DIR__) . '/config.local.php';
    if (!is_file($path)) {
        api_respond(
            ['erro' => 'Mercado Pago não configurado. Copie config.example.php para config.local.php e preencha as credenciais de teste.'],
            503
        );
    }

    $config = require $path;
    if (!is_array($config) ||
        !isset($config['mercadopago_access_token']) ||
        !is_string($config['mercadopago_access_token']) ||
        trim($config['mercadopago_access_token']) === '' ||
        !isset($config['mercadopago_webhook_secret']) ||
        !is_string($config['mercadopago_webhook_secret']) ||
        trim($config['mercadopago_webhook_secret']) === '' ||
        !isset($config['public_base_url']) ||
        !is_string($config['public_base_url']) ||
        !filter_var($config['public_base_url'], FILTER_VALIDATE_URL) ||
        !isset($config['plan_name']) ||
        !is_string($config['plan_name']) ||
        trim($config['plan_name']) === '' ||
        !isset($config['amount_brl']) ||
        !is_numeric($config['amount_brl']) ||
        (float) $config['amount_brl'] <= 0) {
        api_respond(['erro' => 'A configuração local do Mercado Pago está incompleta ou inválida.'], 503);
    }

    $url = parse_url($config['public_base_url']);
    if (!is_array($url) || !isset($url['scheme'], $url['host']) ||
        !in_array($url['scheme'], ['http', 'https'], true)) {
        api_respond(['erro' => 'A URL pública do servidor não é válida.'], 503);
    }
    return $config;
}

function api_subscription_mp_request($method, $path, $token, $body = null)
{
    $curl = curl_init('https://api.mercadopago.com' . $path);
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false) {
        api_respond(['erro' => 'Não foi possível conectar ao Mercado Pago. Verifique a rede do servidor.'], 502);
    }
    $data = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        api_respond(
            ['erro' => 'O Mercado Pago não concluiu a solicitação. Verifique as credenciais e tente novamente.'],
            502
        );
    }
    if (!is_array($data)) {
        api_respond(['erro' => 'O Mercado Pago retornou uma resposta inválida.'], 502);
    }
    return $data;
}

function api_subscription_status($providerStatus)
{
    $statuses = [
        'pending' => 'pending',
        'authorized' => 'approved',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'cancelled' => 'canceled',
        'canceled' => 'canceled',
        'paused' => 'paused',
        'in_process' => 'pending'
    ];
    return isset($statuses[$providerStatus]) ? $statuses[$providerStatus] : null;
}

function api_subscription_validate_webhook($secret, $dataId)
{
    $signature = isset($_SERVER['HTTP_X_SIGNATURE'])
        ? $_SERVER['HTTP_X_SIGNATURE'] : '';
    $requestId = isset($_SERVER['HTTP_X_REQUEST_ID'])
        ? $_SERVER['HTTP_X_REQUEST_ID'] : '';
    if (!is_string($signature) || !is_string($requestId) ||
        $signature === '' || $requestId === '' || $dataId === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signature) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    if (!isset($parts['ts'], $parts['v1']) ||
        !preg_match('/^\d+$/', $parts['ts']) ||
        !preg_match('/^[a-f0-9]{64}$/i', $parts['v1'])) {
        return false;
    }

    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $requestId
        . ';ts:' . $parts['ts'] . ';';
    $expected = hash_hmac('sha256', $manifest, $secret);
    return hash_equals($expected, strtolower($parts['v1']));
}

function api_subscription_update_status($pdo, $preapprovalId, $status)
{
    $stmt = $pdo->prepare(
        'UPDATE assinaturas SET status = :status '
        . 'WHERE mp_preapproval_id = :mp_preapproval_id'
    );
    $stmt->execute([
        ':status' => $status,
        ':mp_preapproval_id' => $preapprovalId
    ]);
}

function api_subscription_webhook($config)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_respond(['erro' => 'Método não permitido.'], 405);
    }

    $rawBody = file_get_contents('php://input');
    $notification = json_decode($rawBody, true);
    if (!is_array($notification)) {
        api_respond(['erro' => 'Corpo JSON inválido.'], 400);
    }
    $dataId = isset($_GET['data_id']) ? $_GET['data_id'] : (
        isset($_GET['id']) ? $_GET['id'] : (
            isset($notification['data']['id']) ? $notification['data']['id'] : ''
        )
    );
    if (!is_string($dataId) && !is_int($dataId)) {
        api_respond(['erro' => 'Identificador da notificação inválido.'], 400);
    }
    $dataId = trim((string) $dataId);
    if (!api_subscription_validate_webhook(
        $config['mercadopago_webhook_secret'],
        $dataId
    )) {
        api_respond(['erro' => 'Assinatura da notificação inválida.'], 401);
    }

    $topic = isset($_GET['type']) && is_string($_GET['type'])
        ? $_GET['type']
        : (isset($notification['type']) && is_string($notification['type'])
            ? $notification['type'] : '');
    if ($topic === 'subscription_preapproval') {
        $resource = api_subscription_mp_request(
            'GET',
            '/preapproval/' . rawurlencode($dataId),
            $config['mercadopago_access_token']
        );
        if (isset($resource['status']) && is_string($resource['status'])) {
            $status = api_subscription_status($resource['status']);
            if ($status === null) {
                api_respond(['erro' => 'Status de assinatura desconhecido no Mercado Pago.'], 502);
            }
            require_once dirname(__DIR__) . '/bd.php';
            api_subscription_update_status($pdo, $dataId, $status);
        }
    } elseif ($topic === 'payment' || $topic === 'subscription_authorized_payment') {
        $resourcePath = $topic === 'payment'
            ? '/v1/payments/' . rawurlencode($dataId)
            : '/authorized_payments/' . rawurlencode($dataId);
        $resource = api_subscription_mp_request(
            'GET',
            $resourcePath,
            $config['mercadopago_access_token']
        );
        $preapprovalId = isset($resource['preapproval_id'])
            ? (string) $resource['preapproval_id'] : '';
        if ($preapprovalId !== '' && isset($resource['status']) &&
            is_string($resource['status'])) {
            $status = api_subscription_status($resource['status']);
            if ($status === null) {
                api_respond(['erro' => 'Status de pagamento desconhecido no Mercado Pago.'], 502);
            }
            require_once dirname(__DIR__) . '/bd.php';
            api_subscription_update_status($pdo, $preapprovalId, $status);
        }
    }

    api_respond(['recebido' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    api_respond(['erro' => 'Método não permitido.'], 405);
}
$config = api_subscription_config();

if (isset($_GET['webhook']) && $_GET['webhook'] === '1') {
    require_once dirname(__DIR__) . '/bd.php';
    api_subscription_webhook($config);
}

$usuario = api_require_login();
require_once dirname(__DIR__) . '/bd.php';

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT id, mp_preapproval_id, plano, valor, status, init_point, updated_at '
        . 'FROM assinaturas WHERE usuario_id = :usuario_id ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuario['id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) {
        api_respond([
            'status' => 'none',
            'plano' => $config['plan_name'],
            'valor' => (float) $config['amount_brl'],
            'moeda' => 'BRL'
        ]);
    }

    $status = $subscription['status'];
    if (!empty($subscription['mp_preapproval_id'])) {
        $remote = api_subscription_mp_request(
            'GET',
            '/preapproval/' . rawurlencode($subscription['mp_preapproval_id']),
            $config['mercadopago_access_token']
        );
        if (!isset($remote['status']) || !is_string($remote['status'])) {
            api_respond(['erro' => 'O Mercado Pago não retornou o status da assinatura.'], 502);
        }
        $remoteStatus = api_subscription_status($remote['status']);
        if ($remoteStatus === null) {
            api_respond(['erro' => 'Status de assinatura desconhecido no Mercado Pago.'], 502);
        }
        if ($remoteStatus === 'pending' && $status === 'rejected') {
            $status = 'rejected';
        } else {
            $status = $remoteStatus;
        }
        api_subscription_update_status(
            $pdo,
            $subscription['mp_preapproval_id'],
            $status
        );
    }

    api_respond([
        'status' => $status,
        'plano' => $subscription['plano'],
        'valor' => (float) $subscription['valor'],
        'moeda' => 'BRL',
        'checkout_url' => $status === 'pending' ? $subscription['init_point'] : null,
        'atualizado_em' => $subscription['updated_at']
    ]);
}

if ($usuario['tipo'] !== 'ADM') {
    api_respond(['erro' => 'Somente administradores podem assinar o plano.'], 403);
}

$stmt = $pdo->prepare(
    'SELECT mp_preapproval_id, plano, valor, status, init_point '
    . 'FROM assinaturas WHERE usuario_id = :usuario_id ORDER BY id DESC LIMIT 1'
);
$stmt->execute([':usuario_id' => $usuario['id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);
if ($subscription && $subscription['status'] === 'approved') {
    api_respond(['erro' => 'A assinatura já está aprovada.'], 409);
}
if ($subscription && $subscription['status'] === 'pending' &&
    !empty($subscription['init_point'])) {
    api_respond([
        'status' => 'pending',
        'checkout_url' => $subscription['init_point']
    ]);
}

$baseUrl = rtrim($config['public_base_url'], '/');
$externalReference = 'bluedev-' . $usuario['id'] . '-' . bin2hex(random_bytes(8));
$notificationUrl = $baseUrl . '/api/assinatura.php?webhook=1';
$payload = [
    'reason' => $config['plan_name'],
    'external_reference' => $externalReference,
    'auto_recurring' => [
        'frequency' => 1,
        'frequency_type' => 'months',
        'transaction_amount' => (float) $config['amount_brl'],
        'currency_id' => 'BRL'
    ],
    'back_url' => $baseUrl,
    'notification_url' => $notificationUrl,
    'status' => 'pending'
];
$created = api_subscription_mp_request(
    'POST',
    '/preapproval',
    $config['mercadopago_access_token'],
    $payload
);
$preapprovalId = isset($created['id']) && is_string($created['id'])
    ? $created['id'] : '';
$checkoutUrl = isset($created['init_point']) && is_string($created['init_point'])
    ? $created['init_point'] : '';
$checkout = parse_url($checkoutUrl);
if ($preapprovalId === '' || !is_array($checkout) ||
    !isset($checkout['scheme'], $checkout['host']) ||
    $checkout['scheme'] !== 'https' ||
    !preg_match('/(^|\.)mercadopago\.com(\.br)?$/i', $checkout['host'])) {
    api_respond(['erro' => 'O Mercado Pago não retornou uma URL de checkout válida.'], 502);
}
$providerStatus = isset($created['status']) && is_string($created['status'])
    ? api_subscription_status($created['status']) : 'pending';
if ($providerStatus === null) {
    api_respond(['erro' => 'Status de assinatura desconhecido no Mercado Pago.'], 502);
}

$stmt = $pdo->prepare(
    'INSERT INTO assinaturas '
    . '(usuario_id, mp_preapproval_id, external_reference, plano, valor, status, init_point) '
    . 'VALUES (:usuario_id, :mp_preapproval_id, :external_reference, :plano, :valor, :status, :init_point)'
);
$stmt->execute([
    ':usuario_id' => $usuario['id'],
    ':mp_preapproval_id' => $preapprovalId,
    ':external_reference' => $externalReference,
    ':plano' => $config['plan_name'],
    ':valor' => $config['amount_brl'],
    ':status' => $providerStatus,
    ':init_point' => $checkoutUrl
]);
api_respond([
    'status' => $providerStatus,
    'checkout_url' => $checkoutUrl
], 201);
