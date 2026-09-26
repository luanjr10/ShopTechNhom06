<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GhnClient
{
    private string $baseUrl;

    private string $token;

    private ?string $shopId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ghn.api_url'), '/');
        $this->token = (string) config('services.ghn.token');
        $this->shopId = config('services.ghn.shop_id') ? (string) config('services.ghn.shop_id') : null;

        $isProductionHost = str_contains($this->baseUrl, 'online-gateway.ghn.vn')
            && ! str_contains($this->baseUrl, 'dev-online-gateway.ghn.vn');
        $allowProduction = filter_var(config('services.ghn.allow_production'), FILTER_VALIDATE_BOOLEAN);

        if ($isProductionHost && ! app()->environment('production') && ! $allowProduction) {
            throw new RuntimeException(
                'GHN_API_URL đang trỏ tới Production nhưng app không chạy ở môi trường production — '
                .'chặn lại để tránh tạo vận đơn/phí thật ngoài ý muốn. Dùng dev-online-gateway.ghn.vn khi dev/test, '
                .'hoặc set GHN_ALLOW_PRODUCTION=true nếu chắc chắn muốn gọi production.'
            );
        }
    }

    public function get(string $path, array $query = [], bool $requiresShopId = true): array
    {
        return $this->send('GET', $path, $query, $requiresShopId);
    }

    public function post(string $path, array $body = [], bool $requiresShopId = true): array
    {
        return $this->send('POST', $path, $body, $requiresShopId);
    }

    private function send(string $method, string $path, array $params, bool $requiresShopId): array
    {
        if (! $this->token) {
            throw new RuntimeException('Chưa cấu hình GHN_TOKEN — không thể gọi API Giao Hàng Nhanh.');
        }
        if ($requiresShopId && ! $this->shopId) {
            throw new RuntimeException('Chưa cấu hình GHN_SHOP_ID — không thể gọi API Giao Hàng Nhanh.');
        }

        $headers = ['Token' => $this->token];
        if ($this->shopId) {
            $headers['ShopId'] = $this->shopId;
        }

        $request = Http::withHeaders($headers)->timeout(15)->acceptJson();

        $response = $method === 'GET'
            ? $request->get($this->baseUrl.$path, $params)
            : $request->asJson()->post($this->baseUrl.$path, $params);

        $result = $response->json();

        if (! $response->successful() || (int) ($result['code'] ?? 0) !== 200) {
            Log::error('GHN API lỗi', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $result,
            ]);

            throw new RuntimeException(
                'GHN: '.($result['message'] ?? 'Không gọi được API Giao Hàng Nhanh, vui lòng thử lại.')
            );
        }

        return is_array($result) ? $result : [];
    }
}
