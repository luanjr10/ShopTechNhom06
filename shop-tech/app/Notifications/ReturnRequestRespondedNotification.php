<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Báo cho khách khi seller đã duyệt/từ chối yêu cầu hoàn trả/bảo hành —
 * "gửi tin nhắn của seller về cho người dùng" (xem Seller\ReturnController::respond).
 */
class ReturnRequestRespondedNotification extends Notification
{
    use Queueable;

    public function __construct(public ReturnRequest $returnRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->returnRequest->status === 'approved';
        $typeLabel = $this->returnRequest->type === 'warranty' ? 'bảo hành' : 'hoàn trả';
        $productName = $this->returnRequest->orderItem?->product_name ?? 'sản phẩm';

        $mail = (new MailMessage)
            ->subject(($approved ? 'Đã duyệt' : 'Đã từ chối')." yêu cầu {$typeLabel} — ShopTech")
            ->greeting('Xin chào '.($notifiable->name ?: '').'!')
            ->line("Yêu cầu {$typeLabel} cho sản phẩm \"{$productName}\" của bạn đã được ".($approved ? 'DUYỆT.' : 'TỪ CHỐI.'));

        if ($this->returnRequest->seller_response) {
            $mail->line('Phản hồi từ người bán:')
                ->line($this->returnRequest->seller_response);
        }

        return $mail;
    }
}
