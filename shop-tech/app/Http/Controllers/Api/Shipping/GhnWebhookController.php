<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GhnWebhookController extends Controller
{
    private const TERMINAL_FAILURE_STATUSES = ['returned', 'cancel', 'exception', 'lost', 'damage', 'scrap'];

    public function __construct(private SellerOrderService $sellerOrderService) {}

    public function handle(Request $request)
    {
        $headerName = (string) config('services.ghn.webhook_header_name');
        $expected = config('services.ghn.webhook_secret');
        $received = $request->header($headerName);

        if (! $expected || ! $received || ! hash_equals((string) $expected, (string) $received)) {
            Log::warning('GHN webhook auth không hợp lệ', ['header' => $headerName]);

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $orderCode = $payload['OrderCode'] ?? null;

        if (! $orderCode) {
            return response()->json(['success' => true], 200);
        }

        $shipment = Shipment::where('tracking_number', $orderCode)->first();
        if (! $shipment) {
            Log::warning('GHN webhook không khớp shipment nào', ['order_code' => $orderCode]);

            return response()->json(['success' => true], 200);
        }

        if (($payload['Type'] ?? null) === 'switch_status' && ($status = $payload['Status'] ?? null)) {
            $shipment->update([
                'status' => $status,
                'delivered_at' => $status === 'delivered' ? ($shipment->delivered_at ?? now()) : $shipment->delivered_at,
            ]);

            if ($status === 'delivered' && $shipment->sellerOrder->status === 'shipping') {
                try {
                    $this->sellerOrderService->markDelivered($shipment->sellerOrder);
                } catch (RuntimeException $e) {
                    Log::warning('GHN webhook markDelivered lỗi: '.$e->getMessage(), ['order_code' => $orderCode]);
                }
            }

            if (in_array($status, self::TERMINAL_FAILURE_STATUSES, true) && $shipment->sellerOrder->status === 'shipping') {
                try {
                    $this->sellerOrderService->markDeliveryFailed($shipment->sellerOrder, $status);
                } catch (RuntimeException $e) {
                    Log::warning('GHN webhook markDeliveryFailed lỗi: '.$e->getMessage(), ['order_code' => $orderCode]);
                }
            }
        }

        if (($payload['Type'] ?? null) === 'cod' && ! $shipment->cod_transferred_at) {
            $shipment->update(['cod_transferred_at' => now()]);
        }

        return response()->json(['success' => true], 200);
    }
}
