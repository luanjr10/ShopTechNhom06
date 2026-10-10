<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Store;
use App\Services\ChatService;
use Illuminate\Http\Request;
use RuntimeException;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $conversations = ChatConversation::with('store:id,name,slug,logo')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (ChatConversation $c) => [
                'id' => $c->id,
                'store' => $c->store ?? ChatService::supportStore(),
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at,
                'unread' => $c->customer_unread,
            ]);

        return response()->json(['success' => true, 'data' => $conversations], 200);
    }

    public function start(Request $request)
    {
        $validated = $request->validate(['store_id' => 'required|integer|exists:stores,id']);

        try {
            $conversation = $this->chat->startConversation(
                $request->user(),
                Store::with('sellerProfile')->findOrFail($validated['store_id']),
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $conversation->load('store:id,name,slug,logo'),
        ], 200);
    }

    public function startSupport(Request $request)
    {
        $conversation = $this->chat->startSupportConversation($request->user());

        return response()->json([
            'success' => true,
            'data' => ['id' => $conversation->id, 'store' => ChatService::supportStore()],
        ], 200);
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $this->authorizeCustomer($request, $conversation);

        $messages = $this->chat->messages($conversation, (int) $request->input('after_id', 0));
        $this->chat->markRead($conversation, ChatService::SIDE_CUSTOMER);

        return response()->json([
            'success' => true,
            'data' => [
                'store' => $conversation->store()->select('id', 'name', 'slug', 'logo')->first() ?? ChatService::supportStore(),
                'messages' => $this->chat->present($messages),
            ],
        ], 200);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $this->authorizeCustomer($request, $conversation);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000|required_without:attachments',
            'attachments' => 'nullable|array|max:'.ChatService::MAX_ATTACHMENTS,
            'attachments.*' => 'file|mimes:'.ChatService::ATTACHMENT_MIMES.'|max:'.ChatService::MAX_ATTACHMENT_KB,
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        try {
            $message = $this->chat->send(
                $conversation,
                ChatService::SIDE_CUSTOMER,
                $request->user(),
                $validated['body'] ?? null,
                $validated['product_id'] ?? null,
                $request->hasFile('attachments') ? $this->chat->uploadAttachments($request->file('attachments')) : [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $this->chat->present([$message])[0]], 201);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['count' => $this->chat->unreadForCustomer($request->user())],
        ], 200);
    }

    private function authorizeCustomer(Request $request, ChatConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404, 'Không tìm thấy cuộc trò chuyện');
    }
}
