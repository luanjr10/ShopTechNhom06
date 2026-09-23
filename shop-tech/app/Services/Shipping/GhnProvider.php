<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

/**
 * Tích hợp GHN (Giao Hàng Nhanh) thật — endpoint/field lấy đúng từ tài liệu
 * chính thức developer.ghn.vn (Tính phí: /v2/shipping-order/fee, Tạo đơn:
 * /v2/shipping-order/create), KHÔNG bịa field.
 */
class GhnProvider implements ShippingProviderInterface
{
    public function __construct(private GhnClient $client) {}

    /**
     * @param  array{from_district_id:int,from_ward_code:string,to_district_id:int,to_ward_code:string,weight:int,length:int,width:int,height:int,service_type_id:int}  $params
     * @return array{fee:int, expected_delivery_time: ?string, raw:array<string,mixed>}
     */
    public function calculateFee(array $params): array
    {
        // Phí (GHN /shipping-order/fee) + ETA (GHN /shipping-order/leadtime)
        // là 2 endpoint RIÊNG — GHN docs chính thức. ETA fallback null nếu
        // leadtime lỗi (sandbox GHN đôi khi 429) để caller vẫn show phí.
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
            // GHN trả về `data.leadtime` (unix timestamp), KHÔNG có field
            // `expected_delivery_time` như tài liệu gợi ý — đã verify thực tế
            // qua sandbox (data.leadtime_order.to_estimate_date khớp cùng mốc).
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

    /**
     * @param  array<string, mixed>  $params
     * @return array{tracking_number:string,fee:int,expected_delivery_time:?string,raw:array<string,mixed>}
     */
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
