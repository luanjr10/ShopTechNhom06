<?php

namespace App\Console\Commands;

use App\Models\InstallmentPayment;
use App\Notifications\InstallmentReminderNotification;
use Illuminate\Console\Command;
use Throwable;

class SendInstallmentReminders extends Command
{
    protected $signature = 'installments:remind {--days=3 : Nhắc trước hạn bao nhiêu ngày}';

    protected $description = 'Gửi email nhắc kỳ trả góp sắp đến hạn (một lần) và kỳ đã quá hạn (mỗi ngày một lần)';

    public function handle(): int
    {
        $today = now('Asia/Ho_Chi_Minh')->toDateString();
        $soon = now('Asia/Ho_Chi_Minh')->addDays((int) $this->option('days'))->toDateString();
        $sent = 0;

        InstallmentPayment::with('plan.user')
            ->where('status', 'pending')
            ->where('due_date', '<=', $soon)
            ->whereHas('plan', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->each(function (InstallmentPayment $payment) use ($today, &$sent) {
                $overdue = $payment->due_date->toDateString() < $today;
                $alreadyReminded = $payment->reminded_at
                    && ($overdue ? $payment->reminded_at->toDateString() === $today : true);

                if ($alreadyReminded || $payment->number === 1) {
                    return;
                }

                try {
                    $payment->plan->user->notify(new InstallmentReminderNotification($payment, $overdue));
                } catch (Throwable $e) {
                    $this->warn("Không gửi được mail kỳ #{$payment->id}: {$e->getMessage()}");

                    return;
                }

                $payment->update(['reminded_at' => now()]);
                $sent++;
            });

        $this->info("Đã gửi {$sent} email nhắc trả góp.");

        return self::SUCCESS;
    }
}
