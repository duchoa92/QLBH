<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DataVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class DataVersionController extends Controller
{
    /** Số phiên bản của từng nhóm dữ liệu; trình duyệt hỏi định kỳ và chỉ tải lại phần thật sự đổi. */
    public function index(): JsonResponse
    {
        $versions = DataVersion::all();

        // ready=false: bảng data_versions chưa được migrate. Trình duyệt sẽ chuyển sang làm mới định kỳ thay thế.
        $ready = $versions !== [] || Schema::hasTable(DataVersion::TABLE);

        return response()
            ->json(['versions' => (object) $versions, 'ready' => $ready])
            ->header('Cache-Control', 'no-store, private');
    }
}
