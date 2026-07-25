<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\ExportHistory;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsDataExport;
use App\Models\Product;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public $exportId) {}


    public function handle()
    {
        $export = ExportHistory::find($this->exportId);

        $export->update([
            'status' => 'processing',
            'progress' => 0
        ]);

        $fileName = 'exports/products_' . time() . '.xlsx';

        Excel::store(
            new ProductsDataExport($export), // 👈 truyền vào
            $fileName
        );

        $export->update([
            'progress' => 100,
            'status' => 'done',
            'file' => $fileName
        ]);
    }
}