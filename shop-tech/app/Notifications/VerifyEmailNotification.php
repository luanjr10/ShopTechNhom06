<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Mail xác thực email — link signed trỏ về API. Sau khi bấm, backend đánh dấu
 * verified rồi redirect về client React.
 */
class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Xác thực địa chỉ email — ShopTech')
            ->greeting('Xin chào '.($notifiable->name ?: '').'!')
            ->line('Vui lòng bấm nút bên dưới để xác thực địa chỉ email của bạn.')
            ->action('Xác thực email', $url)
            ->line('Nếu bạn không tạo tài khoản, hãy bỏ qua email này.');
    }

    /**
     * Tạo signed URL tới route 'verification.verify'.
     */
    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
