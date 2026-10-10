<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductCompareService;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function __construct(private ProductCompareService $compareService) {}

    public function show(Request $request)
    {
        $ids = $this->ids($request);

        if (count($ids) < ProductCompareService::MIN_PRODUCTS) {
            return response()->json(['success' => false, 'message' => 'Chọn ít nhất 2 sản phẩm để so sánh.'], 422);
        }

        return response()->json(['success' => true, 'data' => $this->compareService->compare($ids)], 200);
    }

    public function verdict(Request $request)
    {
        $ids = $this->ids($request);

        if (count($ids) < ProductCompareService::MIN_PRODUCTS) {
            return response()->json(['success' => false, 'message' => 'Chọn ít nhất 2 sản phẩm để so sánh.'], 422);
        }

        return response()->json(['success' => true, 'data' => $this->compareService->verdict($ids)], 200);
    }

    /**
     * @return list<int>
     */
    private function ids(Request $request): array
    {
        $raw = $request->query('ids', '');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return $this->compareService->normalizeIds($list);
    }
}
