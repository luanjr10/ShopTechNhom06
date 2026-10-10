<?php

namespace App\Notifications;

use App\Models\InstallmentPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstallmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public InstallmentPayment $payment, public bool $overdue) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->payment->plan;
        $amount = number_format((float) $this->payment->amount, 0, ',', '.').'đ';
        $due = $this->payment->due_date->format('d/m/Y');
        $url = rtrim((string) config('app.frontend_url'), '/').'/tai-khoan/tra-gop';

        return (new MailMessage)
            ->subject(($this->overdue ? 'Kỳ trả góp đã quá hạn' : 'Sắp đến hạn trả góp').' — ShopTech')
            ->greeting('Xin chào '.($notifiable->name ?: '').'!')
            ->line("Kỳ {$this->payment->number}/{$plan->months} của khoản trả góp đơn #{$plan->order_id} ".($this->overdue ? "đã quá hạn từ {$due}." : "đến hạn vào {$due}."))
            ->line("Số tiền cần thanh toán: {$amount}")
            ->action('Thanh toán ngay', $url);
    }
}
