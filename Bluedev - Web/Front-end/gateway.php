<?php
/**
 * Cliente HTTP do API Gateway (microserviços).
 * Endereço: variável de ambiente GATEWAY_URL (padrão http://localhost:8080).
 */

function gateway_url()
{
    return rtrim(getenv('GATEWAY_URL') ?: 'http://localhost:8080', '/');
}

/**
 * Faz uma chamada ao gateway.
 * Retorna ['status' => int, 'body' => array|null, 'erro' => string|null].
 * status 0 = não foi possível conectar.
 */
function gateway_request($metodo, $caminho, $dados = null, $query = [])
{
    $url = gateway_url() . $caminho;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    if ($dados !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $resposta = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $falha = $resposta === false ? curl_error($ch) : null;
    curl_close($ch);

    if ($falha !== null) {
        error_log('Gateway indisponível: ' . $falha);
        return ['status' => 0, 'body' => null, 'erro' => 'Serviço de usuários indisponível.'];
    }

    $body = json_decode($resposta, true);
    $erro = null;
    if ($status >= 400) {
        $erro = is_array($body) && !empty($body['message'])
            ? (string) $body['message']
            : 'Erro ' . $status . ' ao falar com o serviço de usuários.';
    }

    return ['status' => $status, 'body' => is_array($body) ? $body : null, 'erro' => $erro];
}
