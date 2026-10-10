<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ChatService
{
    public const MAX_ATTACHMENTS = 5;

    public const MAX_ATTACHMENT_KB = 10240;

    public const ATTACHMENT_MIMES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip';

    public function __construct(private CloudinaryService $cloudinary) {}

    public const SIDE_CUSTOMER = 'customer';

    public const SIDE_STORE = 'store';

    public function startConversation(User $customer, Store $store): ChatConversation
    {
        if ($store->status !== 'active') {
            throw new RuntimeException('Gian hàng này hiện không hoạt động.');
        }

        if ($store->sellerProfile?->user_id === $customer->id) {
            throw new RuntimeException('Bạn không thể nhắn tin cho gian hàng của chính mình.');
        }

        return ChatConversation::firstOrCreate(['user_id' => $customer->id, 'store_id' => $store->id]);
    }

    /**
     * Hội thoại hỗ trợ: khách chat với đội ngũ ShopTech (store_id rỗng), admin/nhân viên trả lời.
     */
    public function startSupportConversation(User $customer): ChatConversation
    {
        return ChatConversation::firstOrCreate(['user_id' => $customer->id, 'store_id' => null]);
    }

    public function unreadForStaff(): int
    {
        return (int) ChatConversation::whereNull('store_id')->sum('store_unread');
    }

    public static function supportStore(): array
    {
        return ['id' => 0, 'name' => 'Hỗ trợ ShopTech', 'slug' => '', 'logo' => null, 'is_support' => true];
    }

    /**
     * @param  list<array{type: string, url: string, name: string, size: int}>  $attachments
     */
    public function send(ChatConversation $conversation, string $side, User $sender, ?string $body, ?int $productId = null, array $attachments = []): ChatMessage
    {
        $body = trim((string) $body);

        if ($body === '' && empty($attachments)) {
            throw new RuntimeException('Tin nhắn không được để trống.');
        }

        return DB::transaction(function () use ($conversation, $side, $sender, $body, $productId, $attachments) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $side,
                'sender_id' => $sender->id,
                'body' => $body,
                'product_id' => $productId,
                'attachments' => $attachments ?: null,
            ]);

            $unreadColumn = $side === self::SIDE_CUSTOMER ? 'store_unread' : 'customer_unread';

            $conversation->update([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body !== '' ? $body : $this->attachmentPreview($attachments), 80),
                $unreadColumn => $conversation->{$unreadColumn} + 1,
            ]);

            return $message;
        });
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function messages(ChatConversation $conversation, int $afterId = 0, int $limit = 100): Collection
    {
        return ChatMessage::where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<array{type: string, url: string, name: string, size: int}>
     */
    public function uploadAttachments(array $files): array
    {
        return array_map(function ($file) {
            $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

            return [
                'type' => $isImage ? 'image' : 'file',
                'url' => $isImage ? $this->cloudinary->uploadImage($file, 'chat-shoptech') : $this->cloudinary->uploadFile($file),
                'name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
            ];
        }, array_slice($files, 0, self::MAX_ATTACHMENTS));
    }

    /**
     * @param  list<array{type: string}>  $attachments
     */
    private function attachmentPreview(array $attachments): string
    {
        $allImages = collect($attachments)->every(fn ($a) => $a['type'] === 'image');

        return $allImages ? '[Đã gửi '.count($attachments).' ảnh]' : '[Đã gửi tệp đính kèm]';
    }

    /**
     * @param  iterable<int, ChatMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public function present(iterable $messages): array
    {
        $messages = collect($messages);
        $productIds = $messages->pluck('product_id')->filter()->unique()->values()->all();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $images = $productIds ? ProductImage::whereIn('productId', $productIds)->get()->keyBy('productId') : collect();

        return $messages->map(function (ChatMessage $m) use ($products, $images) {
            $product = $m->product_id ? $products->get($m->product_id) : null;

            return [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'body' => $m->body,
                'attachments' => $m->attachments ?? [],
                'created_at' => $m->created_at,
                'product' => $product ? [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'final_price' => PriceAlertService::effectivePrice((float) $product->price, (float) ($product->discount_percent ?: 0)),
                    'thumbnail' => $images->get($product->id)?->images[0] ?? null,
                ] : null,
            ];
        })->values()->all();
    }

    public function markRead(ChatConversation $conversation, string $side): void
    {
        $column = $side === self::SIDE_CUSTOMER ? 'customer_unread' : 'store_unread';

        if ($conversation->{$column} > 0) {
            $conversation->update([$column => 0]);
        }
    }

    public function unreadForCustomer(User $customer): int
    {
        return (int) ChatConversation::where('user_id', $customer->id)->sum('customer_unread');
    }

    public function unreadForStore(Store $store): int
    {
        return (int) ChatConversation::where('store_id', $store->id)->sum('store_unread');
    }
}
