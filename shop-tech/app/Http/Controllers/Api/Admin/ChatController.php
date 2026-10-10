<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Hộp thư hỗ trợ khách hàng của sàn: các hội thoại không thuộc gian hàng nào.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index()
    {
        $conversations = ChatConversation::with('customer:id,name,username,avatar,google_avatar')
            ->whereNull('store_id')
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (ChatConversation $c) => [
                'id' => $c->id,
                'customer' => $c->customer,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at,
                'unread' => $c->store_unread,
            ]);

        return response()->json([
            'success' => true,
            'data' => ['conversations' => $conversations, 'unread_total' => $this->chat->unreadForStaff()],
        ], 200);
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $this->assertSupport($conversation);

        $messages = $this->chat->messages($conversation, (int) $request->input('after_id', 0));
        $this->chat->markRead($conversation, ChatService::SIDE_STORE);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $conversation->customer()->select('id', 'name', 'username', 'avatar', 'google_avatar')->first(),
                'messages' => $this->chat->present($messages),
            ],
        ], 200);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $this->assertSupport($conversation);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000|required_without:attachments',
            'attachments' => 'nullable|array|max:'.ChatService::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:'.ChatService::ATTACHMENT_MIMES.'|max:'.ChatService::MAX_ATTACHMENT_KB,
        ]);

        try {
            $message = $this->chat->send(
                $conversation,
                ChatService::SIDE_STORE,
                $request->user(),
                $validated['body'] ?? null,
                null,
                $request->hasFile('attachments') ? $this->chat->uploadAttachments($request->file('attachments')) : [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $this->chat->present([$message])[0]], 201);
    }

    private function assertSupport(ChatConversation $conversation): void
    {
        abort_unless($conversation->isSupport(), 404, 'Không tìm thấy cuộc trò chuyện');
    }
}
