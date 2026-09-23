<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Dữ liệu bổ trợ của một sản phẩm (MySQL) lưu trên MongoDB:
 * - `specifications`: thông số kỹ thuật chung (RAM, màn hình, CPU...).
 * - `variants`: các phiên bản người dùng có thể chọn (màu/dung lượng/RAM/CPU),
 *   mỗi phiên bản có SKU, giá và tồn kho riêng.
 *
 * Giữ nguyên collection hiện tại (`product_specifications`), chỉ bổ sung `variants`.
 */
class ProductSpecification extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_specifications';

    protected $fillable = [
        'productId',
        'specifications',
        'variants',
    ];

    protected $casts = [
        'productId' => 'integer',
        'specifications' => 'array',
        'variants' => 'array',
    ];
}
