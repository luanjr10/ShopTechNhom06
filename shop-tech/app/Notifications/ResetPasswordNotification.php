<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail đặt lại mật khẩu — gửi mã OTP 6 số (không dùng link).
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ttl = (int) config('auth.reset_code.ttl', 300);
        $duration = $ttl >= 60 ? intdiv($ttl, 60).' phút' : $ttl.' giây';

        return (new MailMessage)
            ->subject('Mã đặt lại mật khẩu — ShopTech')
            ->greeting('Xin chào '.($notifiable->name ?: '').'!')
            ->line('Mã xác minh đặt lại mật khẩu của bạn là:')
            ->line('**'.$this->code.'**')
            ->line("Mã có hiệu lực trong {$duration}.")
            ->line('Nếu không phải bạn yêu cầu, hãy bỏ qua email này.');
    }
}
