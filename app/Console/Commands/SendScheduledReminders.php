<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Notifications\SystemNotification;
use App\Support\NotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendScheduledReminders extends Command
{
    protected $signature = 'system:send-reminders';

    protected $description = 'Send system notifications for scheduled recurring expenses';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $users = NotificationRecipients::withPermission('expenses.view');
        if ($users->isEmpty()) return self::SUCCESS;

        foreach (RecurringExpense::query()->where('is_active', true)->get() as $schedule) {
            foreach ([$today->copy(), $today->copy()->addMonthNoOverflow()->startOfMonth()] as $monthStart) {
                $period = $monthStart->format('Y-m');
                if ($schedule->payments()->where('period', $period)->exists()) continue;

                $dueDate = $monthStart->copy()->day(min((int) $schedule->due_day, $monthStart->daysInMonth));
                $daysUntil = (int) $today->diffInDays($dueDate, false);
                $inReminderWindow = $daysUntil <= (int) $schedule->remind_days
                    && ($daysUntil >= 0 || $period === $today->format('Y-m'));
                if (! $inReminderWindow) continue;

                $key = "expense:{$schedule->id}:{$period}";
                if (! Cache::add('system_reminder:' . $key, true, now()->addYear())) continue;

                $timing = $daysUntil > 0
                    ? "còn {$daysUntil} ngày đến hạn"
                    : ($daysUntil === 0 ? 'đến hạn hôm nay' : 'đã quá hạn ' . abs($daysUntil) . ' ngày');
                $message = "{$schedule->description} · " . number_format((float) $schedule->amount, 0, ',', '.')
                    . ' đ · ' . $timing . ' (' . $dueDate->format('d/m/Y') . ').';

                Notification::send($users, new SystemNotification(
                    'Nhắc lịch thanh toán chi phí',
                    $message,
                    $daysUntil < 0 ? 'warning' : 'info',
                    route('expenses.index', ['month' => $period, 'open_schedule' => $schedule->id], false),
                    ['reminder_key' => $key, 'reminder_kind' => 'recurring_expense', 'period' => $period, 'required_permission' => 'expenses.view']
                ));
            }
        }

        return self::SUCCESS;
    }
}
