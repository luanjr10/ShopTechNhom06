<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Wishlist;
use App\Services\PriceAlertService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::with('product:id,name,slug,price,discount_percent,stock,status')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->filter(fn (Wishlist $w) => $w->product !== null);

        $images = ProductImage::whereIn('productId', $items->pluck('product_id')->all())->get()->keyBy('productId');

        $data = $items->map(function (Wishlist $w) use ($images) {
            $product = $w->product;
            $current = PriceAlertService::effectivePrice((float) $product->price, (float) ($product->discount_percent ?: 0));
            $image = $images->get($product->id);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $image?->images[0] ?? null,
                'price' => (float) $product->price,
                'discount_percent' => (float) ($product->discount_percent ?: 0),
                'current_price' => $current,
                'in_stock' => (int) $product->stock > 0 && (int) $product->status === 1,
                'target_price' => $w->target_price !== null ? (float) $w->target_price : null,
                'target_reached' => $w->target_price !== null && $current <= (float) $w->target_price,
                'added_at' => $w->created_at,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    public function ids(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => Wishlist::where('user_id', $request->user()->id)->pluck('product_id'),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'target_price' => 'nullable|numeric|min:0',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $item = Wishlist::firstOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id],
            ['target_price' => $validated['target_price'] ?? null],
        );

        if (! $item->wasRecentlyCreated && array_key_exists('target_price', $validated)) {
            $item->update(['target_price' => $validated['target_price'], 'last_notified_price' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm vào danh sách yêu thích',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, int $productId)
    {
        $validated = $request->validate(['target_price' => 'nullable|numeric|min:0']);

        $item = Wishlist::where('user_id', $request->user()->id)->where('product_id', $productId)->firstOrFail();
        $item->update(['target_price' => $validated['target_price'] ?? null, 'last_notified_price' => null]);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật mức giá mong muốn', 'data' => $item], 200);
    }

    public function destroy(Request $request, int $productId)
    {
        Wishlist::where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return response()->json(['success' => true, 'message' => 'Đã bỏ khỏi danh sách yêu thích'], 200);
    }
}
