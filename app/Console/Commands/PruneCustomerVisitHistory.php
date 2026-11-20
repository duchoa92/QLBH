<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CustomerVisitRetention;
use Illuminate\Console\Command;

class PruneCustomerVisitHistory extends Command
{
    protected $signature = 'customer-visits:prune';

    protected $description = 'Xóa lịch sử khách đến và ảnh camera đã quá thời hạn lưu trong Cài đặt';

    public function handle(CustomerVisitRetention $retention): int
    {
        $deleted = $retention->prune();
        $this->info("Đã xóa {$deleted} lượt khách quá {$retention->days()} ngày, bao gồm ảnh camera riêng tư.");

        return self::SUCCESS;
    }
}
