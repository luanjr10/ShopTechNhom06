<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationService;

class LocationController extends Controller
{
    public function __construct(private LocationService $locationService) {}

    public function provinces()
    {
        return response()->json([
            'success' => true,
            'data' => $this->locationService->provinces(),
        ], 200);
    }

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

    public function wards(int $id)
    {
        return response()->json([
            'success' => true,
            'data' => $this->locationService->wardsOfDistrict($id),
        ], 200);
    }
}
