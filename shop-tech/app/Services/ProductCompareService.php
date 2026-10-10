<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductCompareService
{
    public const MIN_PRODUCTS = 2;

    public const MAX_PRODUCTS = 3;

    public function __construct(private RatingService $ratingService) {}

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    public function normalizeIds(array $ids): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, self::MAX_PRODUCTS);
    }

    /**
     * @param  list<int>  $ids
     * @return array{products: list<array<string, mixed>>, rows: list<array{name: string, values: list<string|null>}>, highlights: array<string, int|null>}
     */
    public function compare(array $ids): array
    {
        $products = Product::with(['brand:id,name', 'store:id,name,slug'])
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->values();

        $images = ProductImage::whereIn('productId', $products->pluck('id')->all())->get()->keyBy('productId');
        $specs = ProductSpecification::whereIn('productId', $products->pluck('id')->all())->get()->keyBy('productId');

        $items = $products->map(function (Product $product) use ($images) {
            $stats = $this->ratingService->productStats($product->id);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'thumbnail' => $images->get($product->id)?->images[0] ?? null,
                'price' => (float) $product->price,
                'discount_percent' => (float) ($product->discount_percent ?: 0),
                'final_price' => PriceAlertService::effectivePrice((float) $product->price, (float) ($product->discount_percent ?: 0)),
                'brand' => $product->brand?->name,
                'store' => $product->store?->name,
                'in_stock' => (int) $product->stock > 0,
                'rating' => $stats['average'],
                'reviews_count' => $stats['count'],
            ];
        })->values()->all();

        $table = $products->mapWithKeys(function (Product $product) use ($specs) {
            $map = [];
            foreach ($specs->get($product->id)?->specifications ?? [] as $spec) {
                $name = trim((string) ($spec['name'] ?? ''));
                if ($name !== '') {
                    $map[$name] = trim((string) ($spec['value'] ?? ''));
                }
            }

            return [$product->id => $map];
        });

        $names = $table->flatMap(fn (array $map) => array_keys($map))->unique()->values();

        $rows = $names->map(fn (string $name) => [
            'name' => $name,
            'values' => $products->map(fn (Product $p) => $table[$p->id][$name] ?? null)->values()->all(),
        ])->values()->all();

        return ['products' => $items, 'rows' => $rows, 'highlights' => $this->highlights($items)];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{cheapest: int|null, best_rated: int|null}
     */
    private function highlights(array $items): array
    {
        $cheapest = collect($items)->sortBy('final_price')->first();
        $rated = collect($items)->where('reviews_count', '>', 0)->sortByDesc('rating')->first();

        return ['cheapest' => $cheapest['id'] ?? null, 'best_rated' => $rated['id'] ?? null];
    }

    /**
     * @param  list<int>  $ids
     * @return array{text: string, source: string}
     */
    public function verdict(array $ids): array
    {
        $comparison = $this->compare($ids);

        if (count($comparison['products']) < self::MIN_PRODUCTS) {
            return ['text' => 'Cần ít nhất 2 sản phẩm để so sánh.', 'source' => 'basic'];
        }

        $key = 'compare-verdict:'.md5(json_encode([$comparison['products'], $comparison['rows']]));

        return Cache::remember($key, now()->addHour(), function () use ($comparison) {
            $ai = $this->askAi($comparison);

            return $ai !== null
                ? ['text' => $ai, 'source' => 'ai']
                : ['text' => $this->basicVerdict($comparison), 'source' => 'basic'];
        });
    }

    /**
     * @param  array{products: list<array<string, mixed>>, rows: list<array{name: string, values: list<string|null>}>, highlights: array<string, int|null>}  $comparison
     */
    public function basicVerdict(array $comparison): string
    {
        $products = collect($comparison['products'])->keyBy('id');
        $lines = [];

        $cheapest = $products->get($comparison['highlights']['cheapest']);
        if ($cheapest) {
            $lines[] = '- Giá tốt nhất: '.$cheapest['name'].' ('.number_format($cheapest['final_price'], 0, ',', '.').'đ).';
        }

        $best = $products->get($comparison['highlights']['best_rated']);
        if ($best) {
            $lines[] = '- Được đánh giá cao nhất: '.$best['name'].' ('.$best['rating'].'/5 từ '.$best['reviews_count'].' đánh giá).';
        }

        $outOfStock = $products->where('in_stock', false)->pluck('name');
        if ($outOfStock->isNotEmpty()) {
            $lines[] = '- Tạm hết hàng: '.$outOfStock->implode(', ').'.';
        }

        $lines[] = '- Hãy đối chiếu bảng thông số bên trên để chọn sản phẩm khớp nhu cầu của bạn.';

        return implode("\n", $lines);
    }

    /**
     * @param  array{products: list<array<string, mixed>>, rows: list<array{name: string, values: list<string|null>}>, highlights: array<string, int|null>}  $comparison
     */
    private function askAi(array $comparison): ?string
    {
        $apiKey = config('services.groq.api_key');

        if (! $apiKey) {
            return null;
        }

        $payload = [
            'san_pham' => array_map(fn ($p) => [
                'ten' => $p['name'],
                'gia' => $p['final_price'],
                'thuong_hieu' => $p['brand'],
                'danh_gia' => $p['reviews_count'] > 0 ? $p['rating'] : null,
                'con_hang' => $p['in_stock'],
            ], $comparison['products']),
            'thong_so' => array_map(fn ($row) => ['muc' => $row['name'], 'gia_tri' => $row['values']], array_slice($comparison['rows'], 0, 25)),
        ];

        try {
            $response = Http::withToken($apiKey)->timeout(25)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model', 'openai/gpt-oss-120b'),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => <<<'PROMPT'
Bạn là chuyên gia tư vấn công nghệ của ShopTech. Dựa DUY NHẤT vào dữ liệu JSON được cung cấp (không bịa thêm thông số),
hãy so sánh các sản phẩm bằng tiếng Việt, ngắn gọn (tối đa 120 từ):
1. Một câu tổng quan về điểm khác biệt chính.
2. Mỗi sản phẩm một dòng bắt đầu bằng "Chọn <tên rút gọn> nếu ..." nêu nhu cầu phù hợp.
3. Nếu thông số thiếu để kết luận, nói rõ là chưa đủ dữ liệu. Không dùng markdown tiêu đề, chỉ dùng gạch đầu dòng "-".
PROMPT],
                    ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                ],
            ]);

            if ($response->failed()) {
                Log::warning('So sánh AI: Groq trả lỗi', ['status' => $response->status()]);

                return null;
            }

            $text = trim((string) $response->json('choices.0.message.content'));

            return $text !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('So sánh AI: lỗi gọi Groq', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
