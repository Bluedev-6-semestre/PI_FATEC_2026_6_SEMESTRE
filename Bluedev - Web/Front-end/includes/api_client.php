<?php
/** Cliente HTTP centralizado para o API Gateway do BlueDev. */

class ApiException extends RuntimeException
{
    private int $httpStatus;
    private $responseBody;

    public function __construct(string $message, int $httpStatus = 0, $responseBody = null)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }
}

function api_base_url(): string
{
    $baseUrl = getenv('API_BASE_URL');
    if (!$baseUrl) {
        $baseUrl = 'http://localhost:8080';
    }
    return rtrim(trim($baseUrl), '/');
}

function api_request(string $method, string $path, ?array $payload = null)
{
    $url = api_base_url() . '/' . ltrim($path, '/');
    $method = strtoupper($method);
    $json = null;
    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new ApiException('Não foi possível preparar os dados enviados à API.');
        }
    }

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($json !== null) {
            $options[CURLOPT_POSTFIELDS] = $json;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $options);
        $rawResponse = curl_exec($curl);
        $transportError = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    } else {
        // Alternativa nativa para instalações PHP sem a extensão cURL.
        $headers = "Accept: application/json\r\n";
        if ($json !== null) $headers .= "Content-Type: application/json\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $json ?? '',
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $rawResponse = @file_get_contents($url, false, $context);
        $transportError = $rawResponse === false ? 'Falha na requisição HTTP via stream.' : '';
        $status = 0;
        foreach ($http_response_header ?? [] as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
                $status = (int) $matches[1];
            }
        }
    }

    if ($rawResponse === false) {
        error_log('[BlueDev API] Falha de comunicação: ' . $transportError . ' | URL: ' . $url);
        throw new ApiException('Não foi possível comunicar com o servidor. Verifique se o API Gateway está ativo.');
    }

    if ($rawResponse === '' && $status >= 200 && $status < 300) {
        return null;
    }

    $decoded = json_decode($rawResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[BlueDev API] Resposta JSON inválida. HTTP ' . $status . ' | URL: ' . $url . ' | Resposta: ' . substr($rawResponse, 0, 1000));
        if ($status >= 200 && $status < 300) {
            throw new ApiException('O servidor retornou uma resposta inválida.', $status);
        }
        $decoded = null;
    }

    if ($status < 200 || $status >= 300) {
        $technicalMessage = is_array($decoded)
            ? ($decoded['detail'] ?? $decoded['message'] ?? $decoded['error'] ?? $decoded['title'] ?? '')
            : '';
        error_log('[BlueDev API] HTTP ' . $status . ' | ' . $method . ' ' . $url . ' | ' . (is_string($technicalMessage) ? $technicalMessage : $rawResponse));
        throw new ApiException(is_string($technicalMessage) ? $technicalMessage : 'Erro retornado pela API.', $status, $decoded);
    }

    return $decoded;
}

function api_user_message(Throwable $exception): string
{
    if (!$exception instanceof ApiException) {
        error_log('[BlueDev PHP] ' . $exception->getMessage());
        return 'Não foi possível concluir a operação. Tente novamente.';
    }

    switch ($exception->getHttpStatus()) {
        case 400:
        case 422:
            return 'Os dados informados são inválidos. Verifique os campos e tente novamente.';
        case 401:
            return 'Usuário ou senha inválidos.';
        case 403:
            return 'Você não tem permissão para realizar esta operação.';
        case 404:
            return 'O registro solicitado não foi encontrado.';
        case 409:
            return 'Já existe um registro com essas informações.';
        case 0:
            return $exception->getMessage();
        default:
            return $exception->getHttpStatus() >= 500
                ? 'O servidor está temporariamente indisponível. Tente novamente mais tarde.'
                : 'Não foi possível concluir a operação. Tente novamente.';
    }
}

function api_flash_error(string $message): void
{
    $_SESSION['flash_error'] = $message;
}

function api_take_flash_error(): ?string
{
    $message = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
    return is_string($message) ? $message : null;
}

