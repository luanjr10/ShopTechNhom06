<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

class GhnProvider implements ShippingProviderInterface
{
    public function __construct(private GhnClient $client) {}

    public function calculateFee(array $params): array
    {
        $feeResult = $this->client->post('/shiip/public-api/v2/shipping-order/fee', [
            'from_district_id' => $params['from_district_id'],
            'from_ward_code' => $params['from_ward_code'],
            'to_district_id' => $params['to_district_id'],
            'to_ward_code' => $params['to_ward_code'],
            'service_type_id' => $params['service_type_id'],
            'weight' => $params['weight'],
            'length' => $params['length'],
            'width' => $params['width'],
            'height' => $params['height'],
        ]);

        $expectedAt = null;
        try {
            $leadtime = $this->client->post('/shiip/public-api/v2/shipping-order/leadtime', [
                'from_district_id' => $params['from_district_id'],
                'from_ward_code' => $params['from_ward_code'],
                'to_district_id' => $params['to_district_id'],
                'to_ward_code' => $params['to_ward_code'],
                'service_type_id' => $params['service_type_id'],
            ]);
            $leadtimeTimestamp = $leadtime['data']['leadtime'] ?? null;
            $expectedAt = $leadtimeTimestamp ? date('Y-m-d H:i:s', (int) $leadtimeTimestamp) : null;
        } catch (\Throwable $e) {
            Log::warning('GHN leadtime không lấy được — trả null ETA', [
                'error' => $e->getMessage(),
                'from' => $params['from_district_id'],
                'to' => $params['to_district_id'],
            ]);
        }

        return [
            'fee' => (int) ($feeResult['data']['total'] ?? 0),
            'expected_delivery_time' => $expectedAt,
            'raw' => $feeResult['data'] ?? [],
        ];
    }

    public function createShipment(array $params): array
    {
        $result = $this->client->post('/shiip/public-api/v2/shipping-order/create', $params);
        $data = $result['data'] ?? [];

        return [
            'tracking_number' => (string) ($data['order_code'] ?? ''),
            'fee' => (int) ($data['total_fee'] ?? 0),
            'expected_delivery_time' => $data['expected_delivery_time'] ?? null,
            'raw' => $data,
        ];
    }
}
