<?php
namespace Modules;

class WooCommerce {
    private \Modules\Logger $logger;

    public function __construct() {
        $this->logger = new Logger();
    }

    public function getProduct(int $productId): ?array {
        $url = WOOCOMMERCE_API_URL . 'products/' . $productId;
        
        $response = $this->makeRequest($url, 'GET', [], [
            'Authorization: Basic ' . base64_encode(WOOCOMMERCE_CONSUMER_KEY . ':' . WOOCOMMERCE_CONSUMER_SECRET)
        ]);

        if (!$response || !isset($response['id'])) {
            $this->logger->error("Produto não encontrado: $productId");
            return null;
        }

        return [
            'id' => $response['id'],
            'name' => $response['name'],
            'price' => $response['price'],
            'image' => !empty($response['images']) ? $response['images'][0]['src'] : '',
            'description' => strip_tags($response['description']),
            'regular_price' => $response['regular_price'] ?? $response['price']
        ];
    }

    public function createOrder(array $orderData): ?array {
        $url = WOOCOMMERCE_API_URL . 'orders';
        $headers = [
            'Authorization: Basic ' . base64_encode(WOOCOMMERCE_CONSUMER_KEY . ':' . WOOCOMMERCE_CONSUMER_SECRET),
            'Content-Type: application/json'
        ];

        $response = $this->makeRequest($url, 'POST', $orderData, $headers);
        
        if ($response && isset($response['id'])) {
            $this->logger->info("Pedido criado: {$response['id']}");
            return $response;
        }

        $this->logger->error("Erro ao criar pedido", $orderData);
        return null;
    }

    public function updateOrderStatus(int $orderId, string $status, ?array $webhookData = null): bool {
        $url = WOOCOMMERCE_API_URL . 'orders/' . $orderId;
        $headers = [
            'Authorization: Basic ' . base64_encode(WOOCOMMERCE_CONSUMER_KEY . ':' . WOOCOMMERCE_CONSUMER_SECRET),
            'Content-Type: application/json'
        ];

        $data = ['status' => $status];
        $response = $this->makeRequest($url, 'PUT', $data, $headers);

        if ($response && isset($response['id'])) {
            $this->logger->info("Status atualizado: $orderId -> $status");
            return true;
        }

        $this->logger->error("Erro ao atualizar status: $orderId");
        return false;
    }

    public function updateOrderMeta(int $orderId, array $metaData): bool {
        $url = WOOCOMMERCE_API_URL . 'orders/' . $orderId;
        $headers = [
            'Authorization: Basic ' . base64_encode(WOOCOMMERCE_CONSUMER_KEY . ':' . WOOCOMMERCE_CONSUMER_SECRET),
            'Content-Type: application/json'
        ];

        $data = ['meta_data' => []];
        foreach ($metaData as $key => $value) {
            $data['meta_data'][] = [
                'key' => $key,
                'value' => $value
            ];
        }

        $response = $this->makeRequest($url, 'PUT', $data, $headers);
        return $response && isset($response['id']);
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
            $this->logger->error('CURL Error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        $this->logger->error("API Error: HTTP $httpCode - $response");
        return null;
    }
}