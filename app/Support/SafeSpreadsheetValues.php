<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Dùng cho các export có `extends DefaultValueBinder implements WithCustomValueBinder`.
 *
 * - Chuỗi bắt đầu bằng "=" được ghi là văn bản, không bị Excel hiểu thành công thức.
 * - Cột SKU/Barcode (C, D) của sheet sản phẩm luôn ghi dạng văn bản để không mất số 0
 *   đầu và không bị hiển thị dạng 8.93E+12.
 */
trait SafeSpreadsheetValues
{
    /** Tiêu đề sheet cần ép cột văn bản. */
    protected function textColumnsSheetTitle(): string
    {
        return 'DS Sản phẩm';
    }

    /** @return array<int, string> */
    protected function textColumns(): array
    {
        return ['C', 'D'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && $value[0] === '=') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        if (
            (is_string($value) || is_int($value) || is_float($value))
            && (string) $value !== ''
            && $cell->getRow() > 1
            && in_array($cell->getColumn(), $this->textColumns(), true)
            && $cell->getWorksheet()->getTitle() === $this->textColumnsSheetTitle()
        ) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
