<?php

namespace App\Support;

/**
 * Lựa chọn của người dùng ở bước kiểm tra file nhập sản phẩm.
 * Mọi giá trị đều được đối chiếu với danh sách cho phép; giá trị lạ rơi về mặc định.
 */
final class ProductImportOptions
{
    /** Dòng lỗi: dừng cả file (mặc định, an toàn: không nhập gì cho đến khi người dùng chọn cách xử lý) hay bỏ qua dòng lỗi. */
    public const ON_ERROR = ['skip_row', 'abort'];

    /** Giá trị sai ở cột không bắt buộc: bỏ qua giá trị (dùng mặc định) hay coi cả dòng là lỗi. */
    public const OPTIONAL_FIELD = ['ignore', 'error'];

    /** SKU đã có trong hệ thống. */
    public const DUPLICATE_SKU = ['error', 'skip', 'update', 'new_sku'];

    /** SKU lặp lại ngay trong file (các dòng sau dòng đầu). */
    public const DUPLICATE_IN_FILE = ['error', 'skip', 'new_sku'];

    /** Danh mục/thương hiệu/đơn vị chưa có: tự tạo mới hay không chấp nhận. */
    public const MISSING_REFERENCE = ['create', 'reject'];

    public function __construct(
        public readonly string $onError = 'abort',
        public readonly string $optionalField = 'error',
        public readonly string $duplicateSku = 'error',
        public readonly string $duplicateInFile = 'error',
        public readonly string $missingReference = 'create',
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $pick = fn (string $key, array $allowed, string $default): string => in_array($input[$key] ?? null, $allowed, true)
            ? (string) $input[$key]
            : $default;

        return new self(
            $pick('on_error', self::ON_ERROR, 'abort'),
            $pick('optional_field', self::OPTIONAL_FIELD, 'error'),
            $pick('duplicate_sku', self::DUPLICATE_SKU, 'error'),
            $pick('duplicate_in_file', self::DUPLICATE_IN_FILE, 'error'),
            $pick('missing_reference', self::MISSING_REFERENCE, 'create'),
        );
    }

    /** Đọc từ chuỗi JSON (gửi qua FormData) hoặc mảng. */
    public static function fromRequestValue(mixed $value): self
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return self::fromArray(is_array($value) ? $value : []);
    }
}
