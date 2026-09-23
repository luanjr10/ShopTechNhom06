<?php

namespace App\Services;

use App\Services\Shipping\GhnClient;
use Illuminate\Support\Facades\Cache;

/**
 * Địa chỉ hành chính Việt Nam — nguồn: master-data CŨ của GHN (tỉnh/quận-huyện/
 * phường-xã, 3 cấp). Đổi từ provinces.open-api.vn (2 cấp, không quận/huyện)
 * sang đây vì API Tính phí GHN (/v2/shipping-order/fee) CHỈ nhận district_id +
 * ward_code theo hệ cũ — không có field tương đương cho hệ 2 cấp mới. Dùng
 * chung 1 nguồn duy nhất để mã tỉnh/quận/phường khách chọn khi checkout khớp
 * chính xác 100% với mã GHN cần, không phải đoán/map giữa 2 hệ mã khác nhau.
 *
 * Cache 24h (dữ liệu hành chính gần như không đổi) để không phụ thuộc uptime GHN
 * ở mọi request, giống cách làm cũ.
 */
class LocationService
{
    private const CACHE_TTL_SECONDS = 86400; // 1 ngày

    public function __construct(private GhnClient $ghn) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function provinces(): array
    {
        return Cache::remember('locations:ghn:provinces', self::CACHE_TTL_SECONDS, function () {
            return $this->ghn->get('/shiip/public-api/master-data/province')['data'] ?? [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function districtsOfProvince(int $provinceId): array
    {
        return Cache::remember("locations:ghn:districts:{$provinceId}", self::CACHE_TTL_SECONDS, function () use ($provinceId) {
            return $this->ghn->get('/shiip/public-api/master-data/district', ['province_id' => $provinceId])['data'] ?? [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wardsOfDistrict(int $districtId): array
    {
        return Cache::remember("locations:ghn:wards:{$districtId}", self::CACHE_TTL_SECONDS, function () use ($districtId) {
            return $this->ghn->get('/shiip/public-api/master-data/ward', ['district_id' => $districtId])['data'] ?? [];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProvince(int $provinceId): ?array
    {
        foreach ($this->provinces() as $province) {
            if ((int) $province['ProvinceID'] === $provinceId) {
                return $province;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDistrict(int $provinceId, int $districtId): ?array
    {
        foreach ($this->districtsOfProvince($provinceId) as $district) {
            if ((int) $district['DistrictID'] === $districtId) {
                return $district;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWard(int $districtId, string $wardCode): ?array
    {
        foreach ($this->wardsOfDistrict($districtId) as $ward) {
            if ((string) $ward['WardCode'] === $wardCode) {
                return $ward;
            }
        }

        return null;
    }
}
