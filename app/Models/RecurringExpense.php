<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    protected $fillable = ['category', 'description', 'amount', 'due_day', 'interval_months', 'start_month', 'remind_days', 'payment_method', 'recipient', 'note', 'is_active', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** Tháng làm mốc của chu kỳ (Y-m): tháng bắt đầu đã chọn, không có thì tháng tạo lịch. */
    public function anchorPeriod(): string
    {
        return $this->start_month ?: ($this->created_at?->format('Y-m') ?? now()->format('Y-m'));
    }

    /** Lịch này có đến hạn trong kỳ Y-m không (hàng tháng: luôn có; hàng quý: cách đúng 3 tháng kể từ mốc...). */
    public function isDueInPeriod(string $period): bool
    {
        $interval = max(1, (int) $this->interval_months);
        if ($interval === 1) {
            return true;
        }

        [$anchorYear, $anchorMonth] = array_map('intval', explode('-', $this->anchorPeriod()));
        [$year, $month] = array_map('intval', explode('-', $period));
        $diff = ($year - $anchorYear) * 12 + ($month - $anchorMonth);

        return $diff >= 0 && $diff % $interval === 0;
    }

    /** Các tháng trong năm có đến hạn, ví dụ quý bắt đầu tháng 1: [1, 4, 7, 10]. */
    public function dueMonths(): array
    {
        $interval = max(1, (int) $this->interval_months);
        $anchorMonth = (int) explode('-', $this->anchorPeriod())[1];

        return collect(range(0, intdiv(12, $interval) - 1))
            ->map(fn (int $step) => (($anchorMonth - 1 + $step * $interval) % 12) + 1)
            ->sort()->values()->all();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RecurringExpensePayment::class);
    }
}
