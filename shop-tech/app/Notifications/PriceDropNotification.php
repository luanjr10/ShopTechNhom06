<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceDropNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public float $oldPrice,
        public float $newPrice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $format = fn (float $v) => number_format($v, 0, ',', '.').'đ';
        $url = rtrim((string) config('app.frontend_url'), '/').'/san-pham/'.($this->product->slug ?: $this->product->id);

        return (new MailMessage)
            ->subject("Giá giảm: {$this->product->name} — ShopTech")
            ->greeting('Xin chào '.($notifiable->name ?: '').'!')
            ->line("Sản phẩm bạn đang theo dõi \"{$this->product->name}\" vừa giảm giá.")
            ->line('Giá cũ: '.$format($this->oldPrice).' → Giá mới: '.$format($this->newPrice))
            ->action('Xem sản phẩm', $url);
    }
}
