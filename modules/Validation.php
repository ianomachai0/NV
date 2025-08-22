<?php
namespace Modules;

class Validation {
    private static array $rateLimit = [];

    public static function checkRateLimit(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();

        if (!isset(self::$rateLimit[$ip])) {
            self::$rateLimit[$ip] = [
                'count' => 1,
                'time' => $now
            ];
            return true;
        }

        // Reset após 60 segundos
        if ($now - self::$rateLimit[$ip]['time'] > 60) {
            self::$rateLimit[$ip] = [
                'count' => 1,
                'time' => $now
            ];
            return true;
        }

        // Limite de 10 requisições por minuto
        if (self::$rateLimit[$ip]['count'] >= 10) {
            return false;
        }

        self::$rateLimit[$ip]['count']++;
        return true;
    }

    public static function validateJsonPayload(string $json): array {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON inválido', 400);
        }

        if (empty($data)) {
            throw new \Exception('Payload vazio', 400);
        }

        return $data;
    }

    public static function validateOrderData(array $data): void {
        $required = ['product_id', 'name', 'email', 'phone', 'payment_method'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \Exception("Campo obrigatório: $field", 400);
            }
        }

        self::validateEmail($data['email']);
        self::validatePhone($data['phone']);
        self::validateProductId($data['product_id']);
    }

    public static function validateEmail(string $email): string {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Email inválido', 400);
        }
        return $email;
    }

    public static function validatePhone(string $phone): string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validar formato M-Pesa (Moçambique: 84, 85, 86, 87 + 7 dígitos)
        if (!preg_match('/^8[4-7][0-9]{7}$/', $phone)) {
            throw new \Exception('Número de telefone inválido', 400);
        }
        
        return $phone;
    }

    public static function validateProductId($id): int {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            throw new \Exception('ID do produto inválido', 400);
        }
        return $productId;
    }

    public static function validatePrice($price): float {
        $price = filter_var($price, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price <= 0) {
            throw new \Exception('Preço inválido', 400);
        }
        return $price;
    }

    public static function validateQuantity($quantity): int {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity <= 0 || $quantity > 100) {
            throw new \Exception('Quantidade inválida', 400);
        }
        return $quantity;
    }

    public static function sanitizeName(string $name): string {
        return htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    }
}