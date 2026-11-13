<?php

namespace App\Jobs;

use App\Exports\ProductsDataExport;
use App\Models\ExportHistory;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;
use ZipArchive;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** Tên thư mục chứa ảnh trong file zip. */
    public const IMAGE_FOLDER = 'images';

    public function __construct(public $exportId, public bool $withImages = false) {}

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
        $baseName = 'exports/products_' . now()->format('Ymd_His') . '_' . $export->id;
        $excelFile = $baseName . '.xlsx';

        // Ghi rõ disk "local" để khớp với nơi controller đọc file tải về.
        Excel::store(new ProductsDataExport($export), $excelFile, 'local');

        $finalFile = $excelFile;

        if ($this->withImages) {
            $export->update(['progress' => 90]);

            $finalFile = $this->zipWithImages($excelFile, $baseName . '.zip');
        }

        $export->update([
            'progress' => 100,
            'status' => 'done',
            'file' => $finalFile,
        ]);
    }

    /**
     * Gói file Excel và toàn bộ ảnh sản phẩm vào một file zip:
     *   products_xxx.xlsx
     *   images/<tên ảnh>   (đúng tên ghi ở cột "Ảnh" trong Excel)
     * Ảnh không còn trên máy chủ thì bỏ qua. Mỗi ảnh chỉ thêm một lần dù nhiều sản phẩm dùng chung.
     * File zip này chọn thẳng ở ô "ảnh" khi nhập lại: hệ thống tự lấy các ảnh trong thư mục images.
     */
    private function zipWithImages(string $excelFile, string $zipFile): string
    {
        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $zipPath = $local->path($zipFile);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file zip khi xuất sản phẩm.');
        }

        $zip->addFile($local->path($excelFile), basename($excelFile));

        $added = [];

        // Cùng điều kiện với file Excel (không lấy sản phẩm trong thùng rác).
        Product::query()
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->pluck('image')
            ->each(function (string $path) use ($zip, $public, &$added): void {
                $name = basename($path);

                if (isset($added[$name]) || ! $public->exists($path)) {
                    return;
                }

                $entry = self::IMAGE_FOLDER . '/' . $name;

                if ($zip->addFile($public->path($path), $entry)) {
                    // Ảnh đã nén sẵn: lưu nguyên để nhanh và nhẹ CPU.
                    $zip->setCompressionName($entry, ZipArchive::CM_STORE);
                    $added[$name] = true;
                }
            });

        if (! $zip->close()) {
            $local->delete($zipFile);

            throw new RuntimeException('Không ghi được file zip khi xuất sản phẩm.');
        }

        // Excel đã nằm trong zip: xóa bản lẻ để không tốn dung lượng.
        $local->delete($excelFile);

        return $zipFile;
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
