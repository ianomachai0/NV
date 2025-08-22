<?php
// api.php - Backend refatorado para GJ Checkout com e2Payments
declare(strict_types=1);

header('Content-Type: application/json');

// Configuração de ambiente
require_once __DIR__ . '/config/bootstrap.php';

// Módulos
require_once __DIR__ . '/modules/Logger.php';
require_once __DIR__ . '/modules/Validation.php';
require_once __DIR__ . '/modules/WooCommerce.php';
require_once __DIR__ . '/modules/E2Payments.php';
require_once __DIR__ . '/modules/Webhook.php';

use Modules\Logger;
use Modules\Validation;
use Modules\WooCommerce;
use Modules\E2Payments;
use Modules\Webhook;

// Inicializar logger
$logger = new Logger();

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido', 405);
    }

    // Verificar ação
    if (!isset($_GET['action'])) {
        throw new Exception('Ação não especificada', 400);
    }

    $action = $_GET['action'];
    $logger->info("Ação solicitada: $action");

    // Rate limiting
    if (!Validation::checkRateLimit()) {
        throw new Exception('Muitas requisições. Tente novamente mais tarde.', 429);
    }

    // Roteamento de ações
    switch ($action) {
        case 'getProduct':
            handleGetProduct();
            break;
        case 'createOrder':
            handleCreateOrder();
            break;
        case 'webhook':
            handleWebhook();
            break;
        default:
            throw new Exception('Ação não reconhecida', 400);
    }

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    $logger->error("Erro: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $code
    ]);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $code
    ]);
    exit;
}

/**
 * Manipula a requisição para obter dados do produto
 */
function handleGetProduct(): void {
    if (!isset($_GET['id'])) {
        throw new Exception('ID do produto não especificado', 400);
    }

    $productId = Validation::validateProductId($_GET['id']);
    $product = WooCommerce::getProduct($productId);

    if ($product) {
        echo json_encode([
            'status' => 'success',
            'data' => $product
        ]);
    } else {
        throw new Exception('Produto não encontrado', 404);
    }
}

/**
 * Manipula a criação de pedidos
 */
function handleCreateOrder(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido', 405);
    }

    $json = file_get_contents('php://input');
    $data = Validation::validateJsonPayload($json);
    Validation::validateOrderData($data);

    $result = createOrder($data);
    
    echo json_encode($result);
}

/**
 * Cria pedido completo
 */
function createOrder(array $data): array {
    $woocommerce = new WooCommerce();
    $e2payments = new E2Payments();

    // Obter produto
    $product = $woocommerce->getProduct($data['product_id']);
    if (!$product) {
        throw new Exception('Produto não encontrado', 404);
    }

    // Validar preço e quantidade
    Validation::validatePrice($product['price']);
    Validation::validateQuantity($data['quantity'] ?? 1);

    // Criar pedido no WooCommerce
    $orderData = [
        'payment_method' => $data['payment_method'],
        'payment_method_title' => ucfirst($data['payment_method']),
        'set_paid' => false,
        'status' => 'pending',
        'customer_id' => 0,
        'billing' => [
            'first_name' => Validation::sanitizeName($data['name']),
            'email' => Validation::validateEmail($data['email']),
            'phone' => Validation::validatePhone($data['phone'])
        ],
        'line_items' => [
            [
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
                'price' => $product['price']
            ]
        ]
    ];

    $orderResponse = $woocommerce->createOrder($orderData);
    if (!$orderResponse) {
        throw new Exception('Erro ao criar pedido no WooCommerce', 500);
    }

    $orderId = $orderResponse['id'];
    $orderKey = $orderResponse['order_key'];

    // Criar pagamento na e2Payments
    $paymentData = [
        'amount' => (float) $product['price'] * ($data['quantity'] ?? 1),
        'phone' => $data['phone'],
        'reference' => 'ORDER_' . $orderId
    ];

    $paymentResponse = $e2payments->createPayment($paymentData);
    if (!$paymentResponse) {
        $woocommerce->updateOrderStatus($orderId, 'failed');
        throw new Exception('Erro ao criar pagamento na e2Payments', 500);
    }

    // Atualizar metadados do pedido
    $woocommerce->updateOrderMeta($orderId, [
        '_e2payments_reference' => $paymentResponse['reference'] ?? 'ORDER_' . $orderId,
        '_e2payments_status' => 'pending',
        '_e2payments_created_at' => date('Y-m-d H:i:s')
    ]);

    return [
        'status' => 'success',
        'message' => 'Pagamento iniciado. Aguarde confirmação via SMS.',
        'data' => [
            'order_id' => $orderId,
            'order_key' => $orderKey
        ]
    ];
}

/**
 * Manipula webhooks da e2Payments
 */
function handleWebhook(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido', 405);
    }

    $payload = file_get_contents('php://input');
    $webhook = new Webhook();
    
    if (!$webhook->validateSignature($payload)) {
        throw new Exception('Assinatura inválida', 401);
    }

    $data = Validation::validateJsonPayload($payload);
    $result = $webhook->processPayload($data);
    
    echo json_encode($result);
}