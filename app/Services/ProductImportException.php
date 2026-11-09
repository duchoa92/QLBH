<?php

namespace App\Services;

use RuntimeException;

/** Lỗi ở mức cả file (thiếu cột, file hỏng, phiên ảnh hết hạn...) — hiển thị thẳng cho người dùng. */
class ProductImportException extends RuntimeException
{
}
