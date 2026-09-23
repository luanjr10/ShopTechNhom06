<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Liên kết một sản phẩm (MySQL) với nhiều Quick Link (UseCase).
 * Theo đúng mẫu ProductImage/ProductSpecification: mỗi sản phẩm 1 document,
 * `useCaseIds` là mảng _id (string) của các UseCase.
 */
class ProductUseCase extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_use_cases';

    protected $fillable = [
        'productId',
        'useCaseIds',
    ];

    protected $casts = [
        'productId' => 'integer',
    ];

    // Cố ý KHÔNG cast `useCaseIds` là 'array': Eloquent's 'array' cast luôn
    // json_encode() giá trị thành 1 chuỗi trước khi lưu (hành vi dành cho cột
    // text ở DB quan hệ), khiến Mongo lưu useCaseIds thành 1 STRING duy nhất
    // thay vì mảng BSON thật — filter theo Quick Link (`where('useCaseIds', $id)`
    // ở ProductController::productIdsForUseCase) sẽ luôn trả rỗng vì Mongo
    // không so khớp string với phần tử mảng. Không cast, gán thẳng mảng PHP,
    // giống `ProductImage.images` (không cast) đã hoạt động đúng từ trước.
}
