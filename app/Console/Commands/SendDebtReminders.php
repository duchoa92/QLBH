<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Supplier;
use App\Notifications\SystemNotification;
use App\Support\NotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendDebtReminders extends Command
{
    protected $signature = 'system:send-debt-reminders';

    protected $description = 'Send weekly system notifications for outstanding customer and supplier debts';

    public function handle(): int
    {
        $this->sendFor(Customer::query()->where('debt_balance', '>', 0)->get(), 'customer', 'customer_debts.view');
        $this->sendFor(Supplier::query()->where('debt_balance', '>', 0)->get(), 'supplier', 'supplier_debts.view');

        return self::SUCCESS;
    }

    private function sendFor($parties, string $type, string $permission): void
    {
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $users = NotificationRecipients::withPermission($permission);
        if ($users->isEmpty()) return;

        foreach ($parties as $party) {
            $key = "debt:{$type}:{$party->id}:{$week}";
            if (! Cache::add('system_reminder:' . $key, true, now()->addYear())) continue;

            $name = $type === 'customer' ? $party->full_name : $party->name;
            $balance = number_format((float) $party->debt_balance, 0, ',', '.');
            $title = $type === 'customer' ? 'Nhắc thu công nợ khách hàng' : 'Nhắc thanh toán công nợ nhà cung cấp';
            $path = $type === 'customer' ? 'debts.customers' : 'debts.suppliers';
            $message = ($name ?: 'Đối tác') . " còn công nợ {$balance} đ. Vui lòng kiểm tra và đối soát.";

            Notification::send($users, new SystemNotification(
                $title,
                $message,
                'warning',
                route($path, ['open_reminder' => $party->id], false),
                ['reminder_key' => $key, 'reminder_kind' => 'debt', 'party_type' => $type, 'party_id' => $party->id, 'required_permission' => $permission]
            ));
        }
    }
}
