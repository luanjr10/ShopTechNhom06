<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Services\ProductPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->cartOf($request);

        return response()->json([
            'success' => true,
            'data' => $this->formatCart($cart),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'sku' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::find($validated['product_id']);
        if (! $product || (int) $product->status !== 1) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $sku = $validated['sku'] ?? '';
        $spec = ProductSpecification::where('productId', $product->id)->first();
        $availableStock = ProductPricingService::stock($product, $spec, $sku !== '' ? $sku : null);

        $cart = $this->cartOf($request);
        $item = $cart->items()->where('product_id', $product->id)->where('sku', $sku)->first();
        $newQuantity = ($item?->quantity ?? 0) + $validated['quantity'];

        if ($newQuantity > $availableStock) {
            return response()->json([
                'success' => false,
                'message' => 'Số lượng sản phẩm trong giỏ vượt quá tồn kho hiện tại.',
            ], 422);
        }

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create(['product_id' => $product->id, 'sku' => $sku, 'quantity' => $newQuantity]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm sản phẩm vào giỏ hàng.',
            'data' => $this->formatCart($cart->refresh()),
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $owned = $this->ownedItem($request, $cartItem);
        if ($owned instanceof JsonResponse) {
            return $owned;
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::find($cartItem->product_id);
        if (! $product || (int) $product->status !== 1) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $spec = ProductSpecification::where('productId', $product->id)->first();
        $sku = $cartItem->sku !== '' ? $cartItem->sku : null;
        $availableStock = ProductPricingService::stock($product, $spec, $sku);

        if ($validated['quantity'] > $availableStock) {
            return response()->json([
                'success' => false,
                'message' => 'Số lượng sản phẩm trong giỏ vượt quá tồn kho hiện tại.',
            ], 422);
        }

        $cartItem->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật số lượng.',
            'data' => $this->formatCart($cartItem->cart->refresh()),
        ], 200);
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $owned = $this->ownedItem($request, $cartItem);
        if ($owned instanceof JsonResponse) {
            return $owned;
        }

        $cart = $cartItem->cart;
        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.',
            'data' => $this->formatCart($cart->refresh()),
        ], 200);
    }

    public function clear(Request $request)
    {
        $cart = $this->cartOf($request);
        $cart->items()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa toàn bộ giỏ hàng.',
            'data' => $this->formatCart($cart->refresh()),
        ], 200);
    }

    private function cartOf(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function ownedItem(Request $request, CartItem $cartItem)
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'], 404);
        }

        return null;
    }

    private function formatCart(Cart $cart): array
    {
        $items = $cart->items()->get();

        if ($items->isEmpty()) {
            return ['items' => [], 'total_item' => 0, 'total_quantity' => 0, 'subtotal' => 0];
        }

        $productIds = $items->pluck('product_id')->unique()->all();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $specs = ProductSpecification::whereIn('productId', $productIds)->get()->keyBy('productId');
        $images = ProductImage::whereIn('productId', $productIds)->get()->keyBy('productId');

        $totalQuantity = 0;
        $subtotal = 0.0;

        $formatted = $items->map(function (CartItem $item) use ($products, $specs, $images, &$totalQuantity, &$subtotal) {
            $product = $products->get($item->product_id);

            if (! $product || (int) $product->status !== 1) {
                return [
                    'id' => $item->id,
                    'product' => $product ? ['id' => $product->id, 'name' => $product->name] : null,
                    'variant' => $item->sku !== '' ? ['sku' => $item->sku] : null,
                    'quantity' => $item->quantity,
                    'unit_price' => 0,
                    'subtotal' => 0,
                    'available_stock' => 0,
                    'unavailable' => true,
                    'stock_insufficient' => false,
                ];
            }

            $spec = $specs->get($product->id);
            $sku = $item->sku !== '' ? $item->sku : null;
            $variant = $sku ? $this->findVariant($spec, $sku) : null;

            $unitPrice = ProductPricingService::unitPrice($product, $spec, $sku);
            $availableStock = ProductPricingService::stock($product, $spec, $sku);
            $lineSubtotal = round($unitPrice * $item->quantity, 2);
            $stockInsufficient = $item->quantity > $availableStock;

            if (! $stockInsufficient) {
                $totalQuantity += $item->quantity;
                $subtotal += $lineSubtotal;
            }

            return [
                'id' => $item->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail' => $images->get($product->id)?->images[0] ?? null,
                ],
                'variant' => $sku ? ['sku' => $sku, 'attributes' => $variant['attributes'] ?? []] : null,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'available_stock' => $availableStock,
                'unavailable' => false,
                'stock_insufficient' => $stockInsufficient,
            ];
        });

        return [
            'items' => $formatted->values(),
            'total_item' => $formatted->count(),
            'total_quantity' => $totalQuantity,
            'subtotal' => round($subtotal, 2),
        ];
    }

    private function findVariant(?ProductSpecification $spec, string $sku): ?array
    {
        foreach ($spec?->variants ?? [] as $variant) {
            if (($variant['sku'] ?? null) === $sku) {
                return $variant;
            }
        }

        return null;
    }
}
