<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationService;

/**
 * Địa chỉ hành chính Việt Nam theo hệ CŨ của GHN (tỉnh -> quận/huyện -> phường/xã)
 * — bắt buộc dùng hệ này vì API Tính phí GHN chỉ nhận district_id/ward_code kiểu
 * cũ. Public — không cần đăng nhập, chỉ là dữ liệu tham chiếu để chọn địa chỉ.
 */
class LocationController extends Controller
{
    public function __construct(private LocationService $locationService) {}

    // [GET] /api/locations/provinces
    public function provinces()
    {
        return response()->json([
            'success' => true,
            'data' => $this->locationService->provinces(),
        ], 200);
    }

    // [GET] /api/locations/provinces/{id}/districts
    public function districts(int $id)
    {
        if (! $this->locationService->findProvince($id)) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tỉnh/thành phố'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->locationService->districtsOfProvince($id),
        ], 200);
    }

    // [GET] /api/locations/districts/{id}/wards
    public function wards(int $id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->locationService->wardsOfDistrict($id),
        ], 200);
    }
}
