<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Product;
use App\Models\ProductAffiliate;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\AffiliateService;
use App\Services\MomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Chương trình affiliate của gian hàng: bật/tắt, chọn sản phẩm và hoa hồng từng sản phẩm, chi trả cho người giới thiệu.
 */
class AffiliateController extends Controller
{
    public function __construct(private AffiliateService $affiliateService, private MomoService $momoService) {}

    public function settings(Store $store)
    {
        return response()->json(['success' => true, 'data' => $this->affiliateService->settingsFor($store)], 200);
    }

    public function updateSettings(Request $request, Store $store)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'rate' => 'required|numeric|min:0|max:50',
            'hold_days' => 'required|integer|min:0|max:90',
            'min_withdrawal' => 'required|integer|min:0',
        ]);

        $this->affiliateService->updateSettings($store, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật chương trình affiliate của gian hàng',
            'data' => $this->affiliateService->settingsFor($store),
        ], 200);
    }

    public function products(Request $request, Store $store)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Product::where('store_id', $store->id)->orderByDesc('id');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $paginator = $query->paginate(min((int) $request->input('per_page', 15), 100) ?: 15);
        $ids = collect($paginator->items())->pluck('id')->all();
        $affiliates = ProductAffiliate::whereIn('product_id', $ids)->get()->keyBy('product_id');
        $images = ProductImage::whereIn('productId', $ids)->get()->keyBy('productId');

        $paginator->through(function (Product $product) use ($affiliates, $images) {
            $affiliate = $affiliates->get($product->id);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'discount_percent' => (float) $product->discount_percent,
                'thumbnail' => ($images->get($product->id)?->images ?? [])[0] ?? null,
                'affiliate_enabled' => (bool) $affiliate?->enabled,
                'affiliate_rate' => $affiliate ? (float) $affiliate->rate : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    public function updateProducts(Request $request, Store $store)
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:200',
            'product_ids.*' => 'integer',
            'enabled' => 'required|boolean',
            'rate' => 'nullable|numeric|min:0.1|max:50|required_if:enabled,true',
        ]);

        $count = $this->affiliateService->setProductsAffiliate(
            $store,
            $validated['product_ids'],
            (bool) $validated['enabled'],
            isset($validated['rate']) ? (float) $validated['rate'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => $validated['enabled']
                ? "Đã bật affiliate cho {$count} sản phẩm"
                : "Đã tắt affiliate cho {$count} sản phẩm",
        ], 200);
    }

    public function pay(Request $request, Store $store, AffiliateWithdrawal $withdrawal)
    {
        abort_unless($withdrawal->store_id === $store->id, 404, 'Không tìm thấy yêu cầu rút tiền');

        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Yêu cầu này đã được xử lý rồi'], 422);
        }

        try {
            $payUrl = $this->momoService->createAffiliatePayoutUrl($withdrawal);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }

        $withdrawal->update(['reviewed_by' => $request->user()->id]);

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    public function momoReturn(Request $request)
    {
        $data = $request->query();
        $withdrawal = AffiliateWithdrawal::where('payout_reference', $data['orderId'] ?? null)->first();

        if ($withdrawal && $this->momoService->verifySignature($data) && (int) ($data['resultCode'] ?? 1) === 0) {
            $this->affiliateService->finalizePayout($withdrawal);

            return $this->redirectToAdmin('success');
        }

        Log::warning('MoMo affiliate-return không hợp lệ hoặc thất bại', $data);

        return $this->redirectToAdmin('failed');
    }

    private function redirectToAdmin(string $status)
    {
        return redirect()->away(rtrim((string) config('app.admin_url'), '/').'/seller/affiliate?payout='.$status);
    }

    public function commissions(Request $request, Store $store)
    {
        return response()->json([
            'success' => true,
            'data' => AffiliateCommission::where('store_id', $store->id)
                ->with(['referrer:id,name,email', 'referredUser:id,name'])
                ->latest()
                ->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function withdrawals(Request $request, Store $store)
    {
        $query = AffiliateWithdrawal::where('store_id', $store->id)->with('user:id,name,email')->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function review(Request $request, Store $store, AffiliateWithdrawal $withdrawal)
    {
        abort_unless($withdrawal->store_id === $store->id, 404, 'Không tìm thấy yêu cầu rút tiền');

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $this->affiliateService->reviewWithdrawal(
                $withdrawal, $validated['status'], $request->user()->id, $validated['note'] ?? null
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'approved' ? 'Đã ghi nhận chi trả' : 'Đã từ chối yêu cầu rút tiền',
            'data' => $withdrawal->load('user:id,name,email'),
        ], 200);
    }
}
