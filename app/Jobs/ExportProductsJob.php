<?php

namespace App\Jobs;

use App\Exports\ProductsDataExport;
use App\Models\ExportHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public $exportId) {}

    public function handle(): void
    {
        $export = ExportHistory::find($this->exportId);

        if (! $export) {
            return;
        }

        $export->update([
            'status' => 'processing',
            'progress' => 0,
        ]);

        // Tên file có id lần xuất nên hai lần xuất cùng giây không ghi đè nhau.
        $fileName = 'exports/products_' . now()->format('Ymd_His') . '_' . $export->id . '.xlsx';

        // Ghi rõ disk "local" để khớp với nơi controller đọc file tải về.
        Excel::store(new ProductsDataExport($export), $fileName, 'local');

        $export->update([
            'progress' => 100,
            'status' => 'done',
            'file' => $fileName,
        ]);
    }

    /** Job lỗi/quá hạn: đánh dấu thất bại để giao diện thôi chờ. */
    public function failed(?Throwable $exception = null): void
    {
        ExportHistory::query()->whereKey($this->exportId)->update(['status' => 'failed']);

        if ($exception) {
            report($exception);
        }
    }
}
