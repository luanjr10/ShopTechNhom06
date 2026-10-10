<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\TradeInModel;
use App\Models\TradeInRequest;
use App\Services\CloudinaryService;
use App\Services\TradeInService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class TradeInController extends Controller
{
    public function __construct(
        private TradeInService $tradeIn,
        private CloudinaryService $cloudinary,
    ) {}

    public function catalog()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => TradeInModel::CATEGORIES,
                'conditions' => collect(TradeInService::CONDITIONS)->map(fn ($c, $key) => [
                    'key' => $key,
                    'label' => $c['label'],
                    'hint' => $c['hint'],
                ])->values(),
                'stores' => Store::where('status', 'active')
                    ->whereHas('tradeInModels', fn ($q) => $q->where('is_active', true))
                    ->get(['id', 'name', 'slug', 'logo']),
                'models' => TradeInModel::where('is_active', true)
                    ->whereHas('store', fn ($q) => $q->where('status', 'active'))
                    ->orderBy('category')->orderBy('brand')->orderByDesc('base_price')
                    ->get(['id', 'store_id', 'category', 'brand', 'name']),
                'credit_valid_days' => TradeInService::CREDIT_VALID_DAYS,
            ],
        ], 200);
    }

    public function estimate(Request $request)
    {
        $validated = $request->validate([
            'trade_in_model_id' => 'required|integer',
            'condition' => ['required', Rule::in(array_keys(TradeInService::CONDITIONS))],
            'has_box' => 'nullable|boolean',
            'has_charger' => 'nullable|boolean',
        ]);

        $model = TradeInModel::where('is_active', true)->whereHas('store', fn ($q) => $q->where('status', 'active'))->find($validated['trade_in_model_id']);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Dòng máy này hiện chưa được thu mua.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->tradeIn->estimate(
                $model,
                $validated['condition'],
                (bool) ($validated['has_box'] ?? false),
                (bool) ($validated['has_charger'] ?? false),
            ),
        ], 200);
    }

    public function mine(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => TradeInRequest::with('store:id,name,slug')->where('user_id', $request->user()->id)->latest()->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'trade_in_model_id' => 'required|integer|exists:trade_in_models,id',
            'condition' => ['required', Rule::in(array_keys(TradeInService::CONDITIONS))],
            'has_box' => 'nullable|boolean',
            'has_charger' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
            'images' => 'required|array|min:1|max:4',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'images.required' => 'Vui lòng tải lên ít nhất 1 ảnh thực tế của máy.',
        ]);

        try {
            $imageUrls = $this->cloudinary->uploadMultipleImages($request->file('images'), 'tradein-shoptech');
            $tradeInRequest = $this->tradeIn->submit($request->user(), $validated, $imageUrls);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu thu cũ. Gian hàng sẽ xem ảnh, duyệt và gửi voucher cho bạn.',
            'data' => $tradeInRequest,
        ], 201);
    }
}
