<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Hỏi & đáp" theo danh mục — parent_id luôn trỏ về comment GỐC (BE làm phẳng
 * tối đa 2 cấp lúc tạo, xem CategoryCommentController::store), cùng khuôn mẫu
 * với ProductComment.
 */
class CategoryComment extends Model
{
    protected $fillable = ['category_id', 'user_id', 'parent_id', 'body'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }
}
