<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComment;
use Illuminate\Http\Request;

/**
 * "Hỏi & đáp" của sản phẩm — ai đăng nhập cũng bình luận/trả lời được (không
 * chỉ seller), public xem không cần đăng nhập.
 */
class ProductCommentController extends Controller
{
    // [GET] /api/products/{product}/comments
    public function index(Product $product)
    {
        $comments = ProductComment::where('product_id', $product->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,username,avatar,google_avatar,role',
                'user.sellerProfile:id,user_id',
                'user.sellerProfile.stores:id,seller_profile_id',
                'replies.user:id,name,username,avatar,google_avatar,role',
                'replies.user.sellerProfile:id,user_id',
                'replies.user.sellerProfile.stores:id,seller_profile_id',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $this->presentThread($comments, $product)], 200);
    }

    // [POST] /api/products/{product}/comments
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:product_comments,id',
        ]);

        $parentId = null;

        if (! empty($validated['parent_id'])) {
            $parent = ProductComment::where('product_id', $product->id)->findOrFail($validated['parent_id']);
            // Luôn "làm phẳng" về tối đa 2 cấp: trả lời 1 reply thì gắn vào
            // đúng comment GỐC của reply đó, không tạo chuỗi lồng sâu thêm.
            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = ProductComment::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        $comment->load('user:id,name,username,avatar,google_avatar,role');

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bình luận',
            'data' => $this->presentComment($comment, $product, []),
        ], 201);
    }

    private function presentThread($comments, Product $product): array
    {
        return $comments->map(fn (ProductComment $c) => [
            ...$this->presentComment($c, $product, []),
            'replies' => $c->replies->map(fn (ProductComment $r) => $this->presentComment($r, $product, []))->values(),
        ])->values()->all();
    }

    private function presentComment(ProductComment $comment, Product $product, array $extra): array
    {
        $user = $comment->user;

        return [
            'id' => $comment->id,
            'product_id' => $comment->product_id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'created_at' => $comment->created_at,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ] : null,
            'is_admin' => $user?->role === 'admin',
            'is_seller_of_store' => $user?->role === 'seller' && $user->sellerProfile?->stores?->contains('id', $product->store_id),
            ...$extra,
        ];
    }
}
