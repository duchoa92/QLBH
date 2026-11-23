<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * "Số phiên bản dữ liệu" theo từng nhóm (kênh) để các trang tự làm mới khi dữ liệu đổi.
 *
 *  - Mọi câu lệnh INSERT / UPDATE / DELETE / TRUNCATE (kể cả DB::table, job nền, webhook ngân hàng...)
 *    được bắt qua DB::listen, đối chiếu tên bảng với TABLE_CHANNELS.
 *  - Các kênh bị chạm tới được gom lại và tăng số phiên bản sau khi giao dịch DB commit.
 *  - Trình duyệt chỉ hỏi /api/data-versions (rất nhẹ); kênh nào đổi số thì trang liên quan tải lại ngầm.
 *
 * Thêm bảng mới: khai báo vào TABLE_CHANNELS. Lỗi ở đây KHÔNG BAO GIỜ được làm hỏng thao tác chính.
 */
class DataVersion
{
    public const TABLE = 'data_versions';

    /** @var array<string, string[]> bảng => các kênh bị ảnh hưởng */
    public const TABLE_CHANNELS = [
        'products' => ['products'],
        'product_variants' => ['products'],
        'product_imeis' => ['products'],
        'product_imei_histories' => ['products'],
        'product_stock_batches' => ['products'],
        'units' => ['units', 'products'],

        'categories' => ['categories', 'products'],
        'category_attributes' => ['categories', 'products'],
        'category_attribute_values' => ['categories', 'products'],
        'brands' => ['brands', 'products'],
        'brand_category' => ['brands', 'categories'],

        'customers' => ['customers'],
        'customer_devices' => ['customers'],
        'customer_images' => ['customers'],
        'customer_logs' => ['customers'],
        'customer_points' => ['customers'],
        'customer_face_profiles' => ['customers'],
        'customer_face_samples' => ['customers'],
        'customer_visits' => ['customer_visits'],
        'customer_debts' => ['debts', 'customers'],

        'sales' => ['sales', 'customers', 'products'],
        'sale_items' => ['sales', 'products'],
        'sale_item_gifts' => ['sales'],
        'sale_item_stock_batches' => ['sales', 'products'],
        'order_item_cancellations' => ['sales', 'stock_imports'],

        'repairs' => ['repairs', 'customers'],
        'repair_images' => ['repairs'],
        'repair_parts' => ['repairs', 'products'],
        'repair_timelines' => ['repairs'],

        'stock_imports' => ['stock_imports', 'products', 'suppliers'],
        'stock_import_items' => ['stock_imports', 'products'],
        'suppliers' => ['suppliers'],
        'supplier_debts' => ['debts', 'suppliers'],

        'expenses' => ['expenses'],
        'recurring_expenses' => ['expenses'],
        'recurring_expense_payments' => ['expenses'],
        'work_shifts' => ['work_shifts'],

        'users' => ['users'],
        'roles' => ['users'],
        'permissions' => ['users'],
        'model_has_roles' => ['users'],
        'model_has_permissions' => ['users'],
        'role_has_permissions' => ['users'],
        'settings' => ['settings'],

        'bank_payment_intents' => ['payments', 'sales'],
        'bank_payment_transactions' => ['payments', 'sales'],
        'notifications' => ['notifications'],
        'export_histories' => ['exports'],
        'backup_cloud_connections' => ['backups'],
    ];

    /** @var array<string, true> kênh đang chờ tăng phiên bản */
    private static array $pending = [];

    private static bool $flushing = false;

    private static bool $listening = false;

    /** Đăng ký bộ lắng nghe câu lệnh ghi. Gọi một lần ở AppServiceProvider::boot(). */
    public static function listen(): void
    {
        if (self::$listening) {
            return;
        }
        self::$listening = true;

        DB::listen(function (QueryExecuted $query): void {
            try {
                self::capture($query);
            } catch (\Throwable) {
                // Không để lỗi theo dõi thay đổi ảnh hưởng thao tác của người dùng.
            }
        });
    }

    private static function capture(QueryExecuted $query): void
    {
        if (self::$flushing) {
            return;
        }

        $sql = ltrim($query->sql);
        $first = strtolower($sql[0] ?? '');
        if (! in_array($first, ['i', 'u', 'd', 't', 'r'], true)) {
            return;
        }

        $table = self::tableFromSql($sql);
        if ($table === null || ! isset(self::TABLE_CHANNELS[$table])) {
            return;
        }

        foreach (self::TABLE_CHANNELS[$table] as $channel) {
            self::$pending[$channel] = true;
        }

        // Đợi giao dịch commit rồi mới tăng phiên bản; ngoài giao dịch thì chạy ngay.
        // Nếu giao dịch rollback, các kênh còn chờ sẽ được tăng ở lần ghi kế tiếp (chỉ dư một lần làm mới).
        try {
            $query->connection->afterCommit(static fn () => self::flush());
        } catch (\Throwable) {
            self::flush();
        }
    }

    private static function tableFromSql(string $sql): ?string
    {
        if (preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update(?:\s+ignore)?|delete\s+from|truncate(?:\s+table)?)\s+[`"\[]?([A-Za-z0-9_]+)/i', $sql, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /** Tăng phiên bản cho các kênh đã gom. */
    public static function flush(): void
    {
        $channels = array_keys(self::$pending);
        self::$pending = [];

        if ($channels !== []) {
            self::bump($channels);
        }
    }

    /** Kích hoạt thủ công: DataVersion::touch('sales'). @param string|string[] $channels */
    public static function touch(string|array $channels): void
    {
        self::bump((array) $channels);
    }

    /** @param string[] $channels */
    private static function bump(array $channels): void
    {
        self::$flushing = true;

        try {
            $now = now();
            DB::table(self::TABLE)->insertOrIgnore(array_map(
                static fn (string $channel) => ['channel' => $channel, 'version' => 0, 'updated_at' => $now],
                $channels,
            ));
            DB::table(self::TABLE)->whereIn('channel', $channels)->update([
                'version' => DB::raw('version + 1'),
                'updated_at' => $now,
            ]);
        } catch (\Throwable) {
            // Bảng chưa migrate hoặc DB tạm lỗi: bỏ qua.
        } finally {
            self::$flushing = false;
        }
    }

    /** @return array<string, int> */
    public static function all(): array
    {
        try {
            return DB::table(self::TABLE)->pluck('version', 'channel')
                ->map(static fn ($version) => (int) $version)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
