<?php

namespace App\Services;

use App\Http\Controllers\Concerns\NormalizesProductCatalog;
use App\Models\Brands;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chatbot AI tư vấn sản phẩm + giải đáp thắc mắc cho khách trên storefront.
 *
 * Dùng Groq (Chat Completions API tương thích OpenAI, miễn phí, không cần thẻ)
 * với function calling: model tự quyết định khi nào cần gọi `search_products`
 * để tra cứu catalog THẬT (MySQL) thay vì bịa tên/giá sản phẩm. Kết quả tool
 * cuối cùng được trả kèm để FE render product card.
 */
class AiChatService
{
    use NormalizesProductCatalog;

    private const MAX_HISTORY = 12;

    private const MAX_TOOL_ROUNDS = 3;

    public function respond(array $history): array
    {
        $apiKey = config('services.groq.api_key');

        if (! $apiKey) {
            return [
                'reply' => 'Trợ lý AI hiện chưa được cấu hình (thiếu GROQ_API_KEY). Vui lòng liên hệ shop qua hotline để được hỗ trợ.',
                'products' => [],
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...array_slice($history, -self::MAX_HISTORY),
        ];

        $lastProducts = [];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(25)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => config('services.groq.model', 'openai/gpt-oss-120b'),
                        'messages' => $messages,
                        'tools' => $this->toolDefinitions(),
                        'temperature' => 0.4,
                    ]);
            } catch (Throwable $e) {
                Log::error('AI chat: lỗi gọi Groq', ['message' => $e->getMessage()]);

                return [
                    'reply' => 'Xin lỗi, trợ lý AI đang gặp sự cố kết nối. Bạn thử lại sau ít phút nhé.',
                    'products' => [],
                ];
            }

            if ($response->failed()) {
                Log::error('AI chat: Groq trả lỗi', ['status' => $response->status(), 'body' => $response->body()]);

                return [
                    'reply' => 'Xin lỗi, trợ lý AI đang gặp sự cố. Bạn thử lại sau ít phút nhé.',
                    'products' => [],
                ];
            }

            $message = $response->json('choices.0.message');
            $toolCalls = $message['tool_calls'] ?? null;

            if (! $toolCalls) {
                return [
                    'reply' => trim((string) ($message['content'] ?? 'Xin lỗi, tôi chưa có câu trả lời phù hợp.')),
                    'products' => $lastProducts,
                ];
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $args = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                $results = $this->searchProducts($args);
                $lastProducts = $results ?: $lastProducts;

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode([
                        'count' => count($results),
                        'products' => array_map(fn ($p) => [
                            'name' => $p['name'],
                            'price' => $p['final_price'],
                            'category' => $p['category'],
                            'brand' => $p['brand'],
                            'stock' => $p['stock'],
                        ], $results),
                    ], JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return [
            'reply' => 'Mình cần thêm thông tin để tư vấn chính xác hơn — bạn mô tả rõ hơn nhu cầu/ngân sách giúp mình nhé!',
            'products' => $lastProducts,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là trợ lý AI tư vấn bán hàng của ShopTech — sàn thương mại điện tử chuyên đồ công nghệ
(điện thoại, laptop, đồng hồ thông minh, phụ kiện...) với nhiều gian hàng (store) khác nhau.

Nhiệm vụ:
- Tư vấn sản phẩm phù hợp nhu cầu/ngân sách khách hàng.
- Giải đáp thắc mắc chung về mua sắm, vận chuyển (giao hàng COD, ước tính phí/ngày nhận),
  thanh toán (MoMo, VNPay, OnePay, SePay, COD), đổi trả, mã giảm giá, chính sách gian hàng.
- Khi khách hỏi về sản phẩm cụ thể (tên, giá, có hàng không, gợi ý sản phẩm...), LUÔN gọi
  tool `search_products` để lấy dữ liệu thật — TUYỆT ĐỐI KHÔNG tự bịa tên sản phẩm, giá, hay
  tình trạng kho.
- Nếu tool không trả về kết quả phù hợp, thành thật nói chưa tìm thấy và gợi ý khách thử
  từ khoá khác, không bịa sản phẩm.
- Trả lời ngắn gọn, thân thiện, đúng trọng tâm, bằng tiếng Việt. Có thể dùng danh sách gạch
  đầu dòng khi liệt kê nhiều lựa chọn.
- Không tư vấn ngoài phạm vi mua sắm/công nghệ (không đưa lời khuyên y tế, pháp lý, tài chính...).
PROMPT;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Tìm sản phẩm thật trong catalog ShopTech theo từ khoá, danh mục, thương hiệu, khoảng giá.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Từ khoá tìm theo tên sản phẩm, VD "iphone 15", "laptop gaming".',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'Tên danh mục, VD "Điện thoại", "Laptop", "Đồng hồ".',
                            ],
                            'brand' => [
                                'type' => 'string',
                                'description' => 'Tên thương hiệu, VD "Apple", "Samsung".',
                            ],
                            'min_price' => ['type' => 'number', 'description' => 'Giá tối thiểu (VNĐ).'],
                            'max_price' => ['type' => 'number', 'description' => 'Giá tối đa (VNĐ).'],
                            'sort' => [
                                'type' => 'string',
                                'enum' => ['price_asc', 'price_desc', 'newest'],
                                'description' => 'Cách sắp xếp kết quả.',
                            ],
                            'limit' => ['type' => 'integer', 'description' => 'Số lượng kết quả tối đa (mặc định 5, tối đa 8).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchProducts(array $args): array
    {
        $search = trim((string) ($args['query'] ?? ''));
        $categoryName = trim((string) ($args['category'] ?? ''));
        $brandName = trim((string) ($args['brand'] ?? ''));

        $products = $this->buildProductQuery($args, $search, $categoryName, $brandName)->get();

        // AI có thể gán category/brand theo tên "đời thường" (VD "Apple") không
        // khớp đúng tên lưu trong DB shop (VD "Iphone") hoặc category bị lệch dữ
        // liệu — nếu có từ khoá tên sản phẩm rõ ràng mà lọc cứng làm mất hết kết
        // quả, thử lại chỉ với từ khoá để tránh báo "không có hàng" sai sự thật.
        if ($products->isEmpty() && $search !== '' && ($categoryName !== '' || $brandName !== '')) {
            $products = $this->buildProductQuery($args, $search, '', '')->get();
        }

        if ($products->isEmpty()) {
            return [];
        }

        $categoryNames = Category::whereIn('id', $products->pluck('category_id')->unique()->filter())
            ->pluck('name', 'id');

        $imagesByProductId = ProductImage::whereIn('productId', $products->pluck('id'))
            ->get()
            ->keyBy('productId');

        return $products->map(function (Product $product) use ($categoryNames, $imagesByProductId) {
            $images = $imagesByProductId->get($product->id)?->images ?? [];

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'discount_percent' => (float) $product->discount_percent,
                'final_price' => $this->calculateFinalPrice($product->price, $product->discount_percent),
                'thumbnail' => $images[0] ?? null,
                'stock' => (int) $product->stock,
                'category' => $categoryNames->get($product->category_id),
                'brand' => $product->brand?->name,
                'store' => $product->store?->name,
            ];
        })->values()->all();
    }

    private function buildProductQuery(array $args, string $search, string $categoryName, string $brandName): Builder
    {
        $query = Product::query()->with(['store:id,name,slug,logo', 'brand:id,name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($categoryName !== '') {
            $categoryIds = Category::where('name', 'like', "%{$categoryName}%")
                ->orWhere('slug', 'like', "%{$categoryName}%")
                ->pluck('id');
            $query->whereIn('category_id', $categoryIds);
        }

        if ($brandName !== '') {
            $brandIds = Brands::where('name', 'like', "%{$brandName}%")
                ->orWhere('slug', 'like', "%{$brandName}%")
                ->pluck('id');
            $query->whereIn('brand_id', $brandIds);
        }

        if (isset($args['min_price']) && is_numeric($args['min_price'])) {
            $query->where('price', '>=', (float) $args['min_price']);
        }

        if (isset($args['max_price']) && is_numeric($args['max_price'])) {
            $query->where('price', '<=', (float) $args['max_price']);
        }

        match ($args['sort'] ?? null) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default => $query->orderByDesc('created_at'),
        };

        $limit = min((int) ($args['limit'] ?? 5) ?: 5, 8);

        return $query->limit($limit);
    }
}
