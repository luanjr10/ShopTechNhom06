<?php

namespace App\Services;

use App\Services\Shipping\GhnClient;
use Illuminate\Support\Facades\Cache;

class LocationService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(private GhnClient $ghn) {}

    public function provinces(): array
    {
        return Cache::remember('locations:ghn:provinces', self::CACHE_TTL_SECONDS, function () {
            return $this->ghn->get('/shiip/public-api/master-data/province')['data'] ?? [];
        });
    }

    public function districtsOfProvince(int $provinceId): array
    {
        return Cache::remember("locations:ghn:districts:{$provinceId}", self::CACHE_TTL_SECONDS, function () use ($provinceId) {
            return $this->ghn->get('/shiip/public-api/master-data/district', ['province_id' => $provinceId])['data'] ?? [];
        });
    }

    public function wardsOfDistrict(int $districtId): array
    {
        return Cache::remember("locations:ghn:wards:{$districtId}", self::CACHE_TTL_SECONDS, function () use ($districtId) {
            return $this->ghn->get('/shiip/public-api/master-data/ward', ['district_id' => $districtId])['data'] ?? [];
        });
    }

    public function findProvince(int $provinceId): ?array
    {
        foreach ($this->provinces() as $province) {
            if ((int) $province['ProvinceID'] === $provinceId) {
                return $province;
            }
        }

        return null;
    }

    public function findDistrict(int $provinceId, int $districtId): ?array
    {
        foreach ($this->districtsOfProvince($provinceId) as $district) {
            if ((int) $district['DistrictID'] === $districtId) {
                return $district;
            }
        }

        return null;
    }

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
