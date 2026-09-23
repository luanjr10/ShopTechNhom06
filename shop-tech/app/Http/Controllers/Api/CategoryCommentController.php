<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryComment;
use Illuminate\Http\Request;

/**
 * "Hỏi & đáp" theo danh mục — ai đăng nhập cũng bình luận/trả lời được, public
 * xem không cần đăng nhập. Cùng khuôn mẫu với ProductCommentController.
 */
class CategoryCommentController extends Controller
{
    // [GET] /api/categories/{category}/comments
    public function index(Category $category)
    {
        $comments = CategoryComment::where('category_id', $category->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,username,avatar,google_avatar,role',
                'replies.user:id,name,username,avatar,google_avatar,role',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $this->presentThread($comments)], 200);
    }

    // [POST] /api/categories/{category}/comments
    public function store(Request $request, Category $category)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:category_comments,id',
        ]);

        $parentId = null;

        if (! empty($validated['parent_id'])) {
            $parent = CategoryComment::where('category_id', $category->id)->findOrFail($validated['parent_id']);
            // Luôn "làm phẳng" về tối đa 2 cấp: trả lời 1 reply thì gắn vào
            // đúng comment GỐC của reply đó, không tạo chuỗi lồng sâu thêm.
            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = CategoryComment::create([
            'category_id' => $category->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        $comment->load('user:id,name,username,avatar,google_avatar,role');

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi câu hỏi',
            'data' => $this->presentComment($comment, []),
        ], 201);
    }

    private function presentThread($comments): array
    {
        return $comments->map(fn (CategoryComment $c) => [
            ...$this->presentComment($c, []),
            'replies' => $c->replies->map(fn (CategoryComment $r) => $this->presentComment($r, []))->values(),
        ])->values()->all();
    }

    private function presentComment(CategoryComment $comment, array $extra): array
    {
        $user = $comment->user;

        return [
            'id' => $comment->id,
            'category_id' => $comment->category_id,
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
            ...$extra,
        ];
    }
}
