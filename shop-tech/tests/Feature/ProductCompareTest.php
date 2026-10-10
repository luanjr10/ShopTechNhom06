<?php

use App\Services\ProductCompareService;

function sampleComparison(): array
{
    return [
        'products' => [
            ['id' => 1, 'name' => 'Phone A', 'final_price' => 20000000.0, 'rating' => 4.5, 'reviews_count' => 10, 'in_stock' => true],
            ['id' => 2, 'name' => 'Phone B', 'final_price' => 15000000.0, 'rating' => 0.0, 'reviews_count' => 0, 'in_stock' => false],
        ],
        'rows' => [],
        'highlights' => ['cheapest' => 2, 'best_rated' => 1],
    ];
}

test('ids are de-duplicated, cleaned and capped at three', function () {
    $service = app(ProductCompareService::class);

    expect($service->normalizeIds(['3', '3', 'abc', '0', 5, 7, 9, 11]))->toBe([3, 5, 7]);
    expect($service->normalizeIds([]))->toBe([]);
});

test('the basic verdict names the cheapest, the best rated and out-of-stock products', function () {
    $text = app(ProductCompareService::class)->basicVerdict(sampleComparison());

    expect($text)->toContain('Giá tốt nhất: Phone B (15.000.000đ)')
        ->and($text)->toContain('Phone A (4.5/5 từ 10 đánh giá)')
        ->and($text)->toContain('Tạm hết hàng: Phone B');
});
