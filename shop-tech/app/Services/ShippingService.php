<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\Shipping\ShippingProviderInterface;
use RuntimeException;

/**
 * Orchestrator tính phí/tạo vận đơn — KHÔNG tự tính thay nhà vận chuyển, chỉ
 * chuẩn bị dữ liệu (weight/dimension từ Product, địa chỉ từ Store/Order) rồi
 * gọi ShippingProviderInterface (hiện là GhnProvider — xem app/Services/Shipping).
 *
 * Phí ship tính RIÊNG theo từng Store trong giỏ hàng (multi-seller: mỗi store
 * có địa chỉ kho khác nhau, GHN trả giá khác nhau theo tuyến) — KHÔNG có global
 * free-shipping threshold nào nữa, luôn gọi GHN thật.
 */
class ShippingService
{
    /** service_type_id GHN: 2 = tổng khối lượng dưới 20kg (mặc định cho ShopTech). */
    private const SERVICE_TYPE_ID = 2;

    public function __construct(private ShippingProviderInterface $provider) {}

    /**
     * Gom items giỏ hàng theo store_id + validate MỖI sản phẩm đã khai báo đủ
     * weight/length/width/height — thiếu thì báo lỗi rõ ràng theo tên sản phẩm,
     * KHÔNG tự gán giá trị mặc định.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     * @return array<int, array{store: Store, lines: array<int, array{product: Product, quantity: int}>}>
     */
    public function resolveItemsByStore(array $items): array
    {
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $byStore = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw new RuntimeException("Sản phẩm #{$item['product_id']} không tồn tại.");
            }
            if ($product->weight === null || $product->length === null || $product->width === null || $product->height === null) {
                throw new RuntimeException("Sản phẩm \"{$product->name}\" chưa khai báo đầy đủ khối lượng/kích thước — không thể tính phí vận chuyển.");
            }
            if (! $product->store_id) {
                throw new RuntimeException("Sản phẩm \"{$product->name}\" chưa gắn với gian hàng nào.");
            }

            $byStore[$product->store_id]['lines'][] = ['product' => $product, 'quantity' => (int) $item['quantity']];
        }

        foreach ($byStore as $storeId => $group) {
            $store = Store::find($storeId);
            if (! $store) {
                throw new RuntimeException("Gian hàng #{$storeId} không tồn tại.");
            }
            $byStore[$storeId]['store'] = $store;
        }

        return $byStore;
    }

    /**
     * Tính tổng khối lượng (gram) + kích thước kiện (cm) cho 1 nhóm sản phẩm
     * của 1 store. GIẢ ĐỊNH đóng gói: chiều dài/rộng = kích thước lớn nhất trong
     * số các sản phẩm (đặt cạnh nhau vừa hộp lớn nhất), chiều cao = tổng chiều
     * cao × số lượng (xếp chồng) — weight là field BẮT BUỘC của GHN, còn
     * length/width/height chỉ là "Tùy chọn" nên giả định này ảnh hưởng rất nhỏ
     * tới kết quả (chủ yếu dùng cho phụ phí vùng xa/thể tích nếu có).
     *
     * @param  array<int, array{product: Product, quantity: int}>  $lines
     * @return array{weight:int, length:int, width:int, height:int}
     */
    public function packageFor(array $lines): array
    {
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;

        foreach ($lines as $line) {
            $product = $line['product'];
            $qty = $line['quantity'];
            $weight += (int) $product->weight * $qty;
            $length = max($length, (int) $product->length);
            $width = max($width, (int) $product->width);
            $height += (int) $product->height * $qty;
        }

        return ['weight' => max(1, $weight), 'length' => max(1, $length), 'width' => max(1, $width), 'height' => max(1, $height)];
    }

    /** Nội thành (cùng tỉnh với kho gian hàng): miễn phí ship, giao trong khung giờ này. */
    private const SAME_PROVINCE_EXPRESS_HOURS = 2;

    /**
     * Tính phí GHN thật cho 1 store gửi tới 1 địa chỉ — ném RuntimeException nếu
     * store chưa có địa chỉ lấy hàng hoặc GHN lỗi (KHÔNG fallback 0).
     *
     * Ngoại lệ: nếu `$toProvinceId` trùng tỉnh với kho của store (nội thành),
     * bỏ qua GHN — miễn phí ship + giao trong SAME_PROVINCE_EXPRESS_HOURS giờ.
     * Đây là chính sách nội bộ của sàn (không phải giá GHN thật) cho đơn cùng
     * tỉnh với gian hàng, tách biệt hẳn tuyến liên tỉnh GHN tính bên dưới.
     *
     * @param  array<int, array{product: Product, quantity: int}>  $lines
     * @return array{fee:int, weight:int, length:int, width:int, height:int, expected_delivery_time: ?string, same_province_express: bool, raw:array<string,mixed>}
     */
    public function feeForStore(Store $store, int $toDistrictId, string $toWardCode, array $lines, ?int $toProvinceId = null): array
    {
        if (! $store->hasPickupAddress()) {
            throw new RuntimeException("Gian hàng \"{$store->name}\" chưa cấu hình địa chỉ lấy hàng — không thể tính phí vận chuyển.");
        }

        $package = $this->packageFor($lines);

        if ($toProvinceId !== null && (int) $store->province_id === $toProvinceId) {
            return array_merge($package, [
                'fee' => 0,
                'expected_delivery_time' => now()->addHours(self::SAME_PROVINCE_EXPRESS_HOURS)->format('Y-m-d H:i:s'),
                'same_province_express' => true,
                'raw' => [],
            ]);
        }

        $result = $this->provider->calculateFee([
            'from_district_id' => (int) $store->district_id,
            'from_ward_code' => (string) $store->ward_code,
            'to_district_id' => $toDistrictId,
            'to_ward_code' => $toWardCode,
            'weight' => $package['weight'],
            'length' => $package['length'],
            'width' => $package['width'],
            'height' => $package['height'],
            'service_type_id' => self::SERVICE_TYPE_ID,
        ]);

        return array_merge($package, [
            'fee' => $result['fee'],
            'expected_delivery_time' => $result['expected_delivery_time'] ?? null,
            'same_province_express' => false,
            'raw' => $result['raw'],
        ]);
    }

    /**
     * Tính phí cho toàn bộ giỏ hàng (nhiều store) tới 1 địa chỉ — dùng cho cả
     * preview ở Checkout lẫn tính lại (không tin FE) lúc OrderService::place().
     * ETA lấy MAX(trễ nhất) trong các store để hiển thị 1 dòng "Dự kiến nhận".
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     * @return array{total_fee:int, expected_delivery_time: ?string, by_store: array<int, array{store_id:int, store_name:string, fee:int, weight:int, length:int, width:int, height:int, expected_delivery_time: ?string, same_province_express: bool}>}
     */
    public function quoteCart(array $items, int $toDistrictId, string $toWardCode, ?int $toProvinceId = null): array
    {
        $byStore = $this->resolveItemsByStore($items);

        $totalFee = 0;
        $breakdown = [];
        $latestEta = null;
        foreach ($byStore as $storeId => $group) {
            $quote = $this->feeForStore($group['store'], $toDistrictId, $toWardCode, $group['lines'], $toProvinceId);
            $totalFee += $quote['fee'];
            $breakdown[] = [
                'store_id' => $storeId,
                'store_name' => $group['store']->name,
                'fee' => $quote['fee'],
                'weight' => $quote['weight'],
                'length' => $quote['length'],
                'width' => $quote['width'],
                'height' => $quote['height'],
                'expected_delivery_time' => $quote['expected_delivery_time'] ?? null,
                'same_province_express' => $quote['same_province_express'] ?? false,
            ];
            // Lấy ETA muộn nhất (max) — an toàn cho multi-seller (vì kiện về
            // cùng đích nhưng đến riêng từng kho, kiện nào đến sau là giới hạn).
            $storeEta = $quote['expected_delivery_time'] ?? null;
            if ($storeEta !== null && ($latestEta === null || $storeEta > $latestEta)) {
                $latestEta = $storeEta;
            }
        }

        return ['total_fee' => $totalFee, 'expected_delivery_time' => $latestEta, 'by_store' => $breakdown];
    }

    public function defaultServiceTypeId(): int
    {
        return self::SERVICE_TYPE_ID;
    }

    /**
     * Tạo vận đơn GHN THẬT cho 1 SellerOrder — from = địa chỉ Store, to =
     * snapshot địa chỉ GHN đã lưu trên Order lúc đặt hàng. Trả về mảng dữ liệu
     * để caller lưu vào bảng `shipments`.
     *
     * HIỆN KHÔNG CÓ CALLER (2026-09-19): SellerOrderService::handover() không
     * gọi GHN thật nữa (seller tự giao, xem doc-block SellerOrderService) —
     * giữ lại method này cho lần tích hợp lại vận chuyển thật sau này thay vì
     * xoá, vì logic tính COD/kiện hàng vẫn đúng, không phải sửa gì thêm.
     *
     * @return array{tracking_number:string, fee:int, expected_delivery_time:?string, weight:int, length:int, width:int, height:int, service_type_id:int, client_order_code:string, raw:array<string,mixed>}
     */
    public function createShipmentForSellerOrder(SellerOrder $sellerOrder): array
    {
        $store = $sellerOrder->store;
        $order = $sellerOrder->order;

        if (! $store->hasPickupAddress()) {
            throw new RuntimeException("Gian hàng \"{$store->name}\" chưa cấu hình địa chỉ lấy hàng — không thể tạo vận đơn.");
        }

        $productIds = $sellerOrder->items->pluck('product_id')->unique()->all();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];
        $orderItems = [];
        foreach ($sellerOrder->items as $item) {
            $product = $products->get($item->product_id);
            if (! $product || $product->weight === null || $product->length === null || $product->width === null || $product->height === null) {
                throw new RuntimeException("Sản phẩm \"{$item->product_name}\" chưa khai báo đầy đủ khối lượng/kích thước — không thể tạo vận đơn.");
            }

            $lines[] = ['product' => $product, 'quantity' => (int) $item->quantity];
            $orderItems[] = [
                'name' => $item->product_name,
                'code' => $item->sku,
                'quantity' => (int) $item->quantity,
                'price' => (int) round((float) $item->unit_price),
                'weight' => (int) $product->weight,
            ];
        }

        $package = $this->packageFor($lines);
        // Idempotent: gọi lại cùng client_order_code (nếu request trước bị timeout
        // nhưng GHN đã nhận) sẽ trả về đúng order_code cũ thay vì tạo đơn trùng.
        $clientOrderCode = 'SHOPTECH-SO'.$sellerOrder->id;

        // Thu hộ (COD): CHỈ áp dụng khi payment_method='cod' — các phương thức
        // thanh toán online (SePay/MoMo/VNPay/OnePay) khách đã trả trước, GHN
        // không thu gì thêm. Đơn multi-seller tách nhiều vận đơn riêng, mỗi vận
        // đơn GHN chỉ thu đúng phần khách nợ CHO KIỆN ĐÓ (subtotal + shipping_fee
        // của seller_order này), trừ đi phần giảm giá phân bổ theo tỷ lệ subtotal
        // (voucher do platform chịu, tính trên toàn đơn) để tổng COD thu đúng
        // bằng order.total_amount khách phải trả.
        $codAmount = 0;
        if ($order->payment_method === 'cod') {
            $orderSubtotalTotal = (float) $order->total_amount - (float) $order->shipping_fee + (float) $order->discount_amount;
            $discountShare = $orderSubtotalTotal > 0
                ? round((float) $order->discount_amount * ((float) $sellerOrder->subtotal / $orderSubtotalTotal), 2)
                : 0.0;
            $codAmount = max(0, (int) round((float) $sellerOrder->subtotal + (float) $sellerOrder->shipping_fee - $discountShare));
        }

        $result = $this->provider->createShipment([
            'to_name' => $order->receiver_name,
            'to_phone' => $order->receiver_phone,
            'to_address' => $order->shipping_address,
            // GHN với is_new_to_address=false bắt buộc cần ID/code để lookup
            // ward/district (đã lưu snapshot lúc checkout). Name chỉ để hiển thị.
            'to_ward_code' => $order->ghn_ward_code,
            'to_district_id' => (int) $order->ghn_district_id,
            'to_ward_name' => $order->ghn_ward_name,
            'to_district_name' => $order->ghn_district_name,
            'to_province_name' => $order->ghn_province_name,
            'is_new_to_address' => false,
            'from_name' => $store->pickup_contact_name,
            'from_phone' => $store->pickup_phone,
            'from_address' => $store->address_line,
            'from_ward_code' => (string) $store->ward_code,
            'from_district_id' => (int) $store->district_id,
            'from_ward_name' => $store->ward_name,
            'from_district_name' => $store->district_name,
            'from_province_name' => $store->province_name,
            'is_new_from_address' => false,
            'client_order_code' => $clientOrderCode,
            'weight' => $package['weight'],
            'length' => $package['length'],
            'width' => $package['width'],
            'height' => $package['height'],
            'service_type_id' => self::SERVICE_TYPE_ID,
            'payment_type_id' => 1, // ShopTech (platform) trả phí GHN — khách đã trả qua checkout.
            // Đồ công nghệ: cho khách kiểm tra ngoại quan trước khi nhận, không cho tháo/thử.
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'cod_amount' => $codAmount,
            'items' => $orderItems,
        ]);

        if (! $result['tracking_number']) {
            throw new RuntimeException('GHN không trả về mã vận đơn — vui lòng thử lại.');
        }

        return array_merge($package, [
            'tracking_number' => $result['tracking_number'],
            'fee' => $result['fee'],
            'cod_amount' => $codAmount,
            'expected_delivery_time' => $result['expected_delivery_time'],
            'service_type_id' => self::SERVICE_TYPE_ID,
            'client_order_code' => $clientOrderCode,
            'raw' => $result['raw'],
        ]);
    }
}
