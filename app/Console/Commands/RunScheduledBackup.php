<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\BackupCloudConnection;
use App\Services\CloudBackupStorage;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class RunScheduledBackup extends Command
{
    protected $signature = 'backup:scheduled';
    protected $description = 'Run a database and uploaded-files backup when its configured schedule is due';

    public function handle(BackupService $service, CloudBackupStorage $cloudStorage): int
    {
        if (! filter_var(setting('backup_enabled', false), FILTER_VALIDATE_BOOLEAN)) return self::SUCCESS;

        $timezone = (string) setting('backup_timezone', 'Asia/Ho_Chi_Minh');
        $now = now($timezone);
        $time = (string) setting('backup_time', '02:00');
        if ($now->format('H:i') < $time) return self::SUCCESS;

        $frequency = setting('backup_frequency', 'daily');
        $slot = match ($frequency) {
            'weekly' => $now->isDayOfWeek((int) setting('backup_weekday', 1)) ? $now->format('o-\WW') : null,
            'monthly' => $now->day === min((int) setting('backup_monthday', 1), $now->daysInMonth) ? $now->format('Y-m') : null,
            default => $now->format('Y-m-d'),
        };
        if ($slot === null || setting('backup_last_slot') === $frequency.':'.$slot) return self::SUCCESS;

        try {
            $path = $service->create();
            foreach (BackupCloudConnection::where('enabled', true)->get() as $connection) {
                $cloudStorage->upload($connection, $path, basename($path));
            }
            Setting::set('backup_last_slot', $frequency.':'.$slot);
            Setting::set('backup_last_run', Carbon::now($timezone)->toDateTimeString().' '.$timezone);
            Setting::set('backup_last_error', null);
            $this->info('Scheduled backup created.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            Setting::set('backup_last_error', $exception->getMessage());
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
