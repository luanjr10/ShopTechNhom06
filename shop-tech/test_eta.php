<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $svc = app(\App\Services\ShippingService::class);
    $quote = $svc->quoteCart(
        [['product_id' => 34, 'quantity' => 1]],
        1442,
        '21012'
    );
    echo json_encode($quote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
}
