<?php

namespace App\Console\Commands;

use App\Models\ExportHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportFiles extends Command
{
    protected $signature = 'exports:prune {--days=7 : Giữ file xuất trong bao nhiêu ngày}';

    protected $description = 'Xóa file xuất (Excel/zip) và lịch sử xuất cũ hơn số ngày quy định (mặc định 7 ngày)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $disk = Storage::disk('local');

        // 1. File trong thư mục exports quá hạn (kể cả file mồ côi không còn bản ghi lịch sử).
        $deletedFiles = 0;

        foreach ($disk->files('exports') as $path) {
            if ($disk->lastModified($path) < $cutoff->getTimestamp() && $disk->delete($path)) {
                $deletedFiles++;
            }
        }

        // 2. Lịch sử xuất quá hạn (file của chúng đã bị xóa ở bước trên hoặc không còn).
        $deletedRecords = ExportHistory::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Đã xóa {$deletedFiles} file xuất và {$deletedRecords} bản ghi lịch sử cũ hơn {$days} ngày.");

        return self::SUCCESS;
    }
}
