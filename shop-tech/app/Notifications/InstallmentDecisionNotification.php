<?php

namespace App\Notifications;

use App\Models\InstallmentPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstallmentDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(public InstallmentPlan $plan, public bool $approved) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $storeName = $this->plan->store?->name ?? 'Gian hàng';
        $url = rtrim((string) config('app.frontend_url'), '/').'/tai-khoan/tra-gop';
        $amount = number_format((float) $this->plan->principal, 0, ',', '.').'đ';

        $mail = (new MailMessage)
            ->subject(($this->approved ? 'Yêu cầu trả góp được chấp nhận' : 'Yêu cầu trả góp bị từ chối').' — ShopTech')
            ->greeting('Xin chào '.($notifiable->name ?: '').'!');

        if ($this->approved) {
            $mail->line("{$storeName} đã chấp nhận cho bạn trả góp {$this->plan->months} tháng cho đơn #{$this->plan->order_id} ({$amount}).")
                ->line('Hãy thanh toán kỳ đầu tiên qua MoMo để gian hàng bắt đầu chuẩn bị hàng.')
                ->action('Thanh toán kỳ 1', $url);
        } else {
            $mail->line("{$storeName} không chấp nhận yêu cầu trả góp cho đơn #{$this->plan->order_id}. Đơn hàng đã được hủy.")
                ->line('Bạn có thể đặt lại với hình thức thanh toán khác.');
        }

        if ($this->plan->decision_note) {
            $mail->line('Lời nhắn từ gian hàng: '.$this->plan->decision_note);
        }

        return $mail;
    }
}
