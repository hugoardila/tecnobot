<?php
declare(strict_types=1);

final class PayPalConfig
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $live = strtolower(trim((string)getenv('PAYPAL_MODE'))) === 'live';
        $prefix = $live ? 'PAYPAL_LIVE_' : 'PAYPAL_SANDBOX_';

        $this->baseUrl = $live ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $this->clientId = $this->requiredEnv($prefix . 'CLIENT_ID');
        $this->clientSecret = $this->requiredEnv($prefix . 'CLIENT_SECRET');
    }

    public function createOrder(float $amount, string $currency, string $description, string $invoiceId): array
    {
        $appBaseUrl = rtrim($this->requiredEnv('APP_BASE_URL'), '/');
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'invoice_id' => $invoiceId,
                'description' => $description,
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $appBaseUrl . '/return_paypal.php?success=true',
                'cancel_url' => $appBaseUrl . '/return_paypal.php?cancelled=true',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->request('POST', '/v2/checkout/orders', $payload);
        $approveUrl = '';
        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = (string)($link['href'] ?? '');
                break;
            }
        }

        if (($response['id'] ?? '') === '' || $approveUrl === '') {
            throw new RuntimeException('PayPal no devolvio una orden valida.');
        }

        return ['order_id' => $response['id'], 'approve_url' => $approveUrl];
    }

    public function captureOrder(string $orderId): array
    {
        if (!preg_match('/^[A-Z0-9]{8,40}$/i', $orderId)) {
            throw new InvalidArgumentException('Identificador de orden PayPal no valido.');
        }

        $response = $this->request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', []);
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? [];
        $payer = $response['payer'] ?? [];
        $name = $payer['name'] ?? [];

        return [
            'status' => (string)($response['status'] ?? ''),
            'transaction_id' => (string)($capture['id'] ?? ''),
            'amount' => (float)($capture['amount']['value'] ?? 0),
            'payer_email' => (string)($payer['email_address'] ?? ''),
            'payer_name' => trim((string)($name['given_name'] ?? '') . ' ' . (string)($name['surname'] ?? '')),
        ];
    }

    private function request(string $method, string $path, array $payload): array
    {
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->accessToken()];
        $curl = curl_init($this->baseUrl . $path);
        if ($curl === false) {
            throw new RuntimeException('No fue posible iniciar la conexion con PayPal.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload !== [] ? $payload : (object)[], JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'No fue posible conectar con PayPal.');
        }

        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('PayPal rechazo la solicitud.');
        }

        return $decoded;
    }

    private function accessToken(): string
    {
        $curl = curl_init($this->baseUrl . '/v1/oauth2/token');
        if ($curl === false) {
            throw new RuntimeException('No fue posible iniciar la autenticacion con PayPal.');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: es_CO'],
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'No fue posible autenticar PayPal.');
        }
        $decoded = json_decode($body, true);
        $token = is_array($decoded) ? trim((string)($decoded['access_token'] ?? '')) : '';
        if ($status !== 200 || $token === '') {
            throw new RuntimeException('PayPal no acepto las credenciales configuradas.');
        }

        return $token;
    }

    private function requiredEnv(string $name): string
    {
        $value = trim((string)getenv($name));
        if ($value === '') {
            throw new RuntimeException($name . ' no esta configurada.');
        }

        return $value;
    }
}
