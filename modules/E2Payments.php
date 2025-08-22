<?php
namespace Modules;

class E2Payments {
    private \Modules\Logger $logger;
    private ?string $cachedToken = null;
    private int $tokenExpiry = 0;

    public function __construct() {
        $this->logger = new Logger();
    }

    public function createPayment(array $paymentData): ?array {
        $token = $this->getToken();
        if (!$token) {
            $this->logger->error('Falha ao obter token e2Payments');
            return null;
        }

        $url = E2PAYMENTS_API_URL . 'c2b/mpesa-payment/' . E2PAYMENTS_WALLET_ID;
        $payload = [
            'client_id' => E2PAYMENTS_CLIENT_ID,
            'amount' => $paymentData['amount'],
            'phone' => $paymentData['phone'],
            'reference' => $paymentData['reference']
        ];

        $response = $this->makeRequest($url, 'POST', $payload, [
            'Authorization: ' . $token,
            'Accept: application/json',
            'Content-Type: application/json'
        ]);

        if ($response) {
            $this->logger->info("Pagamento criado: {$paymentData['reference']}");
            return $response;
        }

        $this->logger->error("Erro ao criar pagamento", $paymentData);
        return null;
    }

    private function getToken(): ?string {
        // Verificar cache
        if ($this->cachedToken && time() < $this->tokenExpiry) {
            return $this->cachedToken;
        }

        $tokenData = [
            'grant_type' => 'client_credentials',
            'client_id' => E2PAYMENTS_CLIENT_ID,
            'client_secret' => E2PAYMENTS_CLIENT_SECRET
        ];

        $response = $this->makeRequest(E2PAYMENTS_AUTH_URL . 'oauth/token', 'POST', $tokenData, [
            'Content-Type: application/json'
        ]);

        if ($response && isset($response['access_token'])) {
            $this->cachedToken = $response['token_type'] . ' ' . $response['access_token'];
            $this->tokenExpiry = time() + ($response['expires_in'] ?? 3600) - 300; // 5 minutos de margem
            return $this->cachedToken;
        }

        return null;
    }

    private function makeRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): ?array {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            }
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $this->logger->error('CURL Error e2Payments: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        $this->logger->error("e2Payments API Error: HTTP $httpCode");
        return null;
    }
}