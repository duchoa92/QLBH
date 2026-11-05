<?php

namespace App\Models;

use App\Support\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $fillable = [

        'code',
        'name',
        'search_text',
        'phone',
        'email',
        'bank_name',
        'bank_bin',
        'bank_account',
        'bank_account_name',
        'bank_payment_url',
        'bank_accounts',
        'tax_code',
        'contact_person',
        'province',
        'district',
        'ward',
        'address',
        'debt_balance',
        'is_active',
        'note',
    ];

    protected $casts = [

        'bank_accounts' => 'array',

        'is_active' => 'boolean',

        'debt_balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(
            function (
                Supplier $supplier
            ): void {

                $supplier->search_text =
                    self::normalizeSearch(
                        $supplier->name ?? ''
                    );
            }
        );
    }

    /**
     * Các phát sinh phải được xử lý trước khi xóa nhà cung cấp.
     * Không dùng cascade để tránh mất lịch sử nhập hàng/công nợ.
     */
    public function usageSummary(): array
    {
        $imports = \DB::table('stock_imports')->where('supplier_id', $this->id)->count();
        $imeis = \DB::table('product_imeis')->where('supplier_id', $this->id)->count();
        $debts = \DB::table('supplier_debts')->where('supplier_id', $this->id)->count();
        $hasBalance = abs((float) $this->debt_balance) > 0.0001;

        return compact('imports', 'imeis', 'debts', 'hasBalance');
    }

    public function hasUsage(): bool
    {
        return collect($this->usageSummary())->filter()->isNotEmpty();
    }
}
