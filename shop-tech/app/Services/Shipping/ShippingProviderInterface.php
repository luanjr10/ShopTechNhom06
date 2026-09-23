<?php

namespace App\Services\Shipping;

/**
 * Abstraction cho 1 nhà vận chuyển thật (GHN hiện tại — sau này có thể thêm
 * GHTK/Viettel Post... cùng implement interface này, ShippingService không đổi).
 */
interface ShippingProviderInterface
{
    /**
     * Tính phí vận chuyển thật từ nhà vận chuyển — KHÔNG được tự tính thay,
     * KHÔNG fallback 0 khi lỗi (ném RuntimeException để caller xử lý).
     *
     * `expected_delivery_time` chỉ là ước tính (nhà vận chuyển trả qua API
     * leadtime riêng) — provider ĐƯỢC trả null nếu không lấy được, không ném
     * lỗi — vì caller (ShippingService) vẫn muốn show phí dù ETA không rõ.
     *
     * @param  array{from_district_id:int,from_ward_code:string,to_district_id:int,to_ward_code:string,weight:int,length:int,width:int,height:int,service_type_id:int}  $params
     * @return array{fee:int, expected_delivery_time: ?string, raw:array<string,mixed>}
     */
    public function calculateFee(array $params): array;

    /**
     * Tạo vận đơn thật, trả về mã vận đơn (tracking number) + toàn bộ response.
     *
     * @param  array<string, mixed>  $params
     * @return array{tracking_number:string,fee:int,expected_delivery_time:?string,raw:array<string,mixed>}
     */
    public function createShipment(array $params): array;
}
