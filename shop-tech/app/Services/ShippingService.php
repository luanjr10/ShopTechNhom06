<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\Shipping\ShippingProviderInterface;
use RuntimeException;

class ShippingService
{
    private const SERVICE_TYPE_ID = 2;

    public function __construct(private ShippingProviderInterface $provider) {}

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

    private const SAME_PROVINCE_EXPRESS_HOURS = 2;

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
        $clientOrderCode = 'SHOPTECH-SO'.$sellerOrder->id;

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
            'payment_type_id' => 1,
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
