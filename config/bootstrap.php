<?php
declare(strict_types=1);

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Configurações
define('WOOCOMMERCE_API_URL', getenv('WOOCOMMERCE_API_URL'));
define('WOOCOMMERCE_CONSUMER_KEY', getenv('WOOCOMMERCE_CONSUMER_KEY'));
define('WOOCOMMERCE_CONSUMER_SECRET', getenv('WOOCOMMERCE_CONSUMER_SECRET'));

define('E2PAYMENTS_API_URL', getenv('E2PAYMENTS_API_URL'));
define('E2PAYMENTS_AUTH_URL', getenv('E2PAYMENTS_AUTH_URL'));
define('E2PAYMENTS_CLIENT_ID', getenv('E2PAYMENTS_CLIENT_ID'));
define('E2PAYMENTS_CLIENT_SECRET', getenv('E2PAYMENTS_CLIENT_SECRET'));
define('E2PAYMENTS_WALLET_ID', getenv('E2PAYMENTS_WALLET_ID'));
define('E2PAYMENTS_WEBHOOK_SECRET', getenv('E2PAYMENTS_WEBHOOK_SECRET'));

define('SITE_URL', getenv('SITE_URL'));
define('DEBUG', getenv('DEBUG') === 'true');

// Configuração de erro
if (!DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Timezone
date_default_timezone_set('Africa/Maputo');