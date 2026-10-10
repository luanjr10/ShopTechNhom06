<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\TradeInModel;
use App\Models\TradeInRequest;
use App\Services\TradeInService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Thu cũ đổi mới do gian hàng quản lý: bảng giá thu riêng và duyệt yêu cầu của khách.
 */
class TradeInController extends Controller
{
    public function __construct(private TradeInService $tradeIn) {}

    public function models(Store $store)
    {
        return response()->json([
            'success' => true,
            'data' => TradeInModel::where('store_id', $store->id)->orderBy('category')->orderBy('brand')->orderBy('name')->get(),
            'categories' => TradeInModel::CATEGORIES,
        ], 200);
    }

    public function storeModel(Request $request, Store $store)
    {
        $model = TradeInModel::create($this->validatedModel($request) + ['store_id' => $store->id]);

        return response()->json(['success' => true, 'message' => 'Đã thêm dòng máy', 'data' => $model], 201);
    }

    public function updateModel(Request $request, Store $store, TradeInModel $model)
    {
        $this->assertModelBelongsToStore($model, $store);

        $model->update($this->validatedModel($request));

        return response()->json(['success' => true, 'message' => 'Đã cập nhật dòng máy', 'data' => $model], 200);
    }

    public function destroyModel(Store $store, TradeInModel $model)
    {
        $this->assertModelBelongsToStore($model, $store);

        $model->delete();

        return response()->json(['success' => true, 'message' => 'Đã xoá dòng máy'], 200);
    }

    public function requests(Request $request, Store $store)
    {
        $query = TradeInRequest::with('user:id,name,email,phone')->where('store_id', $store->id)->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function review(Request $request, Store $store, TradeInRequest $tradeInRequest)
    {
        abort_unless($tradeInRequest->store_id === $store->id, 404, 'Không tìm thấy yêu cầu');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'final_price' => 'nullable|numeric|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $result = $validated['status'] === 'approved'
                ? $this->tradeIn->approve($tradeInRequest, $request->user()->id, isset($validated['final_price']) ? (float) $validated['final_price'] : null, $validated['note'] ?? null)
                : $this->tradeIn->reject($tradeInRequest, $request->user()->id, $validated['note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'approved'
                ? 'Đã duyệt và gửi voucher cho khách (khoản trừ do gian hàng chịu)'
                : 'Đã từ chối yêu cầu',
            'data' => $result->load('user:id,name,email,phone'),
        ], 200);
    }

    private function assertModelBelongsToStore(TradeInModel $model, Store $store): void
    {
        abort_unless($model->store_id === $store->id, 404, 'Không tìm thấy dòng máy');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedModel(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(TradeInModel::CATEGORIES))],
            'brand' => 'required|string|max:60',
            'name' => 'required|string|max:150',
            'base_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }
}
