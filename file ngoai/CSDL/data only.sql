-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping data for table qlbh.backup_cloud_connections: ~0 rows (approximately)

-- Dumping data for table qlbh.bank_payment_intents: ~0 rows (approximately)

-- Dumping data for table qlbh.bank_payment_transactions: ~0 rows (approximately)

-- Dumping data for table qlbh.brands: ~4 rows (approximately)
REPLACE INTO `brands` (`id`, `name`, `search_text`, `slug`, `category_id`, `sort_order`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Samsung', 'samsung', 'samsung', 1, 0, 1, NULL, '2026-10-20 15:53:20', '2026-10-20 15:53:20'),
	(2, 'Việt Hàn', 'viet han', 'viet-han', 4, 0, 1, NULL, '2026-10-20 16:07:49', '2026-10-20 16:07:49'),
	(3, 'Samsung', 'samsung', 'samsung-2', 2, 0, 1, NULL, '2026-10-20 23:47:41', '2026-10-20 23:47:41'),
	(4, 'OEM', 'oem', 'oem', 2, 0, 1, NULL, '2026-10-20 23:59:10', '2026-10-20 23:59:10');

-- Dumping data for table qlbh.cache: ~0 rows (approximately)

-- Dumping data for table qlbh.cache_locks: ~0 rows (approximately)

-- Dumping data for table qlbh.categories: ~4 rows (approximately)
REPLACE INTO `categories` (`id`, `parent_id`, `name`, `slug`, `attributes`, `sort_order`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, NULL, 'Điện Thoại', 'dien-thoai', NULL, 0, 1, '2026-10-20 15:48:44', '2026-10-20 15:48:44', NULL),
	(2, NULL, 'Phụ kiện', 'phu-kien', NULL, 0, 1, '2026-10-20 15:50:37', '2026-10-20 15:50:37', NULL),
	(3, NULL, 'Linh kiện', 'linh-kien', NULL, 0, 1, '2026-10-20 15:50:48', '2026-10-20 15:50:48', NULL),
	(4, NULL, 'Vật tư', 'vat-tu', NULL, 0, 1, '2026-10-20 16:07:39', '2026-10-20 16:07:39', NULL);

-- Dumping data for table qlbh.category_attributes: ~8 rows (approximately)
REPLACE INTO `category_attributes` (`id`, `category_id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Màu', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(2, 1, 'Ram', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(3, 1, 'Rom', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(4, 1, 'Băng tần mạng', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(5, 1, 'Loại sim', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(6, 1, 'Mã nước', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(7, 1, 'Khóa mạng', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(8, 1, 'NFC', '2026-10-20 15:48:44', '2026-10-20 15:48:44'),
	(9, 2, 'Chuẩn cắm', '2026-10-21 00:00:20', '2026-10-21 00:00:20');

-- Dumping data for table qlbh.category_attribute_values: ~12 rows (approximately)
REPLACE INTO `category_attribute_values` (`id`, `attribute_id`, `value`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Đen Tuyền', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(2, 1, 'Xanh Băng', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(3, 1, 'Xanh Ngọc', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(4, 2, '4G', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(5, 2, '6G', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(6, 3, '64G', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(7, 3, '128G', '2026-10-20 16:01:00', '2026-10-20 16:01:00'),
	(8, 1, 'Đen ánh kim', '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(9, 1, 'Xám ánh kim', '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(10, 1, 'Xanh ánh kim', '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(11, 2, '8G', '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(12, 3, '256G', '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(13, 9, 'US', '2026-10-21 00:00:20', '2026-10-21 00:00:20'),
	(14, 9, 'UK', '2026-10-21 00:00:20', '2026-10-21 00:00:20');

-- Dumping data for table qlbh.customers: ~1 rows (approximately)
REPLACE INTO `customers` (`id`, `code`, `full_name`, `search_text`, `phone`, `email`, `birthday`, `gender`, `cccd`, `province`, `district`, `ward`, `address`, `note`, `point_balance`, `debt_balance`, `total_spent`, `total_orders`, `last_order_at`, `customer_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'KH20261020RDN9', 'Hòa', 'hoa', '0906064789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-10-20 16:17:54', '2026-10-20 16:20:32', NULL);

-- Dumping data for table qlbh.customer_debts: ~0 rows (approximately)
REPLACE INTO `customer_debts` (`id`, `customer_id`, `type`, `amount`, `source_type`, `source_id`, `payment_method`, `note`, `created_at`, `updated_at`) VALUES
	(1, 1, 'increase', 500000.00, 'App\\Models\\Sale', 1, NULL, 'Mua nợ - Tiền hàng còn thiếu của hóa đơn INV-20261020231812', '2026-10-20 16:19:46', '2026-10-20 16:19:46'),
	(2, 1, 'decrease', 500000.00, 'App\\Models\\Sale', 1, NULL, 'Hoàn tác công nợ do hủy hóa đơn INV-20261020231812', '2026-10-20 16:20:32', '2026-10-20 16:20:32');

-- Dumping data for table qlbh.customer_devices: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_images: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_logs: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_points: ~0 rows (approximately)

-- Dumping data for table qlbh.expenses: ~0 rows (approximately)

-- Dumping data for table qlbh.export_histories: ~0 rows (approximately)

-- Dumping data for table qlbh.failed_jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.hold_sales: ~0 rows (approximately)

-- Dumping data for table qlbh.jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.job_batches: ~0 rows (approximately)

-- Dumping data for table qlbh.migrations: ~35 rows (approximately)
REPLACE INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2026_05_07_140452_create_permission_tables', 1),
	(5, '2026_05_22_082551_create_customers_table', 1),
	(6, '2026_05_22_082552_create_customer_devices_table', 1),
	(7, '2026_05_22_082553_create_customer_debts_table', 1),
	(8, '2026_05_22_082553_create_customer_points_table', 1),
	(9, '2026_05_22_082557_create_customer_logs_table', 1),
	(10, '2026_05_22_091156_create_customer_images_table', 1),
	(11, '2026_05_23_145920_create_suppliers_table', 1),
	(12, '2026_05_23_145930_create_categories_table', 1),
	(13, '2026_05_23_145940_create_brands_table', 1),
	(14, '2026_05_23_145950_create_units_table', 1),
	(15, '2026_05_23_145960_create_products_table', 1),
	(16, '2026_05_23_145980_create_sales_table', 1),
	(17, '2026_05_23_145983_create_product_variants_table', 1),
	(18, '2026_05_23_145985_create_product_imeis_table', 1),
	(19, '2026_05_23_145990_create_sale_items_table', 1),
	(20, '2026_05_23_145995_create_sale_item_gifts_table', 1),
	(21, '2026_05_23_146100_create_repairs_table', 1),
	(22, '2026_05_23_146110_create_repair_images_table', 1),
	(23, '2026_05_23_146120_create_repair_timelines_table', 1),
	(24, '2026_05_25_123656_create_hold_sales_table', 1),
	(25, '2026_05_25_151943_create_personal_access_tokens_table', 1),
	(26, '2026_07_22_235536_create_export_histories_table', 1),
	(27, '2026_08_01_233827_create_settings_table', 1),
	(28, '2026_08_06_143848_create_category_attributes_table', 1),
	(29, '2026_08_06_143940_create_category_attribute_values_table', 1),
	(30, '2026_08_17_232234_create_stock_imports_table', 1),
	(31, '2026_08_17_232315_create_stock_import_items_table', 1),
	(32, '2026_09_15_000000_add_variant_id_to_sale_items_table', 1),
	(33, '2026_09_15_000001_add_note_to_customers_table', 1),
	(34, '2026_09_16_000007_add_is_active_to_product_variants_table', 1),
	(35, '2026_09_16_000008_remove_unit_conversion_columns', 1),
	(36, '2026_09_20_000212_add_cancel_fields_to_sales_table', 1),
	(37, '2026_09_20_000320_add_extra_info_to_product_imeis_table', 1),
	(38, '2026_09_20_000330_create_product_imei_histories_table', 1),
	(39, '2026_09_26_000100_add_product_unit_conversions', 1),
	(40, '2026_09_26_000101_backfill_unit_conversion_quantities', 1),
	(41, '2026_09_26_000102_align_product_conversion_with_import_units', 1),
	(42, '2026_10_02_000000_create_notifications_table', 1),
	(43, '2026_10_02_000001_add_customer_id_to_repairs_table', 1),
	(44, '2026_10_02_000002_add_repair_progress_details', 1),
	(45, '2026_10_02_000003_add_repair_completion_and_payment', 1),
	(46, '2026_10_03_090000_add_customer_warranty_tracking_to_repairs_and_imeis', 1),
	(47, '2026_10_05_090000_add_customer_warranty_days_to_product_imeis', 1),
	(48, '2026_10_07_090000_add_waiting_mode_to_repair_timelines', 1),
	(49, '2026_10_09_000001_create_supplier_debts_table', 1),
	(50, '2026_10_09_000002_add_payment_method_to_customer_debts_table', 1),
	(51, '2026_10_09_000003_create_backup_cloud_connections_table', 1),
	(52, '2026_10_09_000004_allow_negative_product_stock', 1),
	(53, '2026_10_09_000005_allow_null_phone_for_users', 1),
	(54, '2026_10_09_000006_add_avatar_to_users_table', 1),
	(55, '2026_10_09_000006_expand_application_permissions', 1),
	(56, '2026_10_09_000007_add_payment_account_permission', 1),
	(57, '2026_10_09_000008_normalize_super_admin_and_admin_role', 1),
	(58, '2026_10_12_100000_add_supplier_warranty_to_stock_import_items', 1),
	(59, '2026_10_13_000001_add_sales_edit_permission', 1),
	(60, '2026_10_13_000002_grant_sales_edit_to_sales_view_roles', 1),
	(61, '2026_10_13_000003_grant_sales_edit_to_direct_sales_view_users', 1),
	(62, '2026_10_18_000001_create_expenses_table', 1),
	(63, '2026_10_18_000002_add_expense_permissions', 1),
	(64, '2026_10_18_000003_create_recurring_expenses_tables', 1),
	(65, '2026_10_18_090000_add_warranty_fields_to_sale_items_table', 1),
	(66, '2026_10_18_100000_add_product_imei_to_sale_item_gifts_table', 1),
	(67, '2026_10_18_110000_create_work_shifts_table', 1),
	(68, '2026_10_18_120000_add_shift_customer_debt_amount', 1),
	(69, '2026_10_19_000001_add_bank_account_to_sales_table', 1),
	(70, '2026_10_19_000002_add_counterparty_to_stock_imports', 1),
	(71, '2026_10_19_000003_add_extra_info_to_stock_import_items', 1),
	(72, '2026_10_20_120000_create_bank_payment_intents_table', 1);

-- Dumping data for table qlbh.model_has_permissions: ~0 rows (approximately)

-- Dumping data for table qlbh.model_has_roles: ~0 rows (approximately)
REPLACE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
	(2, 'App\\Models\\User', 1);

-- Dumping data for table qlbh.notifications: ~0 rows (approximately)
REPLACE INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
	('6ddfbafa-615d-4954-ab16-537791d62152', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://qlbh.test/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261020231812 đã được tạo cho Hòa."}', NULL, '2026-10-20 16:19:47', '2026-10-20 16:19:47');

-- Dumping data for table qlbh.password_reset_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.permissions: ~51 rows (approximately)
REPLACE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'dashboard.view', 'web', '2026-10-20 15:44:25', '2026-10-20 15:44:25'),
	(2, 'pos.access', 'web', '2026-10-20 15:44:25', '2026-10-20 15:44:25'),
	(3, 'sales.view', 'web', '2026-10-20 15:44:25', '2026-10-20 15:44:25'),
	(4, 'sales.edit', 'web', '2026-10-20 15:44:25', '2026-10-20 15:44:25'),
	(5, 'sales.cancel', 'web', '2026-10-20 15:44:25', '2026-10-20 15:44:25'),
	(6, 'repairs.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(7, 'repairs.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(8, 'repairs.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(9, 'repairs.complete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(10, 'repairs.return', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(11, 'stock_imports.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(12, 'stock_imports.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(13, 'products.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(14, 'products.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(15, 'products.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(16, 'products.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(17, 'products.import', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(18, 'products.export', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(19, 'categories.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(20, 'categories.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(21, 'categories.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(22, 'categories.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(23, 'brands.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(24, 'brands.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(25, 'brands.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(26, 'brands.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(27, 'customers.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(28, 'customers.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(29, 'customers.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(30, 'customers.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(31, 'suppliers.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(32, 'suppliers.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(33, 'suppliers.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(34, 'suppliers.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(35, 'customer_debts.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(36, 'customer_debts.pay', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(37, 'supplier_debts.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(38, 'supplier_debts.pay', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(39, 'expenses.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(40, 'expenses.manage', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(41, 'reports.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(42, 'users.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(43, 'users.create', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(44, 'users.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(45, 'users.delete', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(46, 'roles.manage', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(47, 'settings.view', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(48, 'settings.edit', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(49, 'payment_accounts.manage', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(50, 'backups.manage', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(51, 'units.manage', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26');

-- Dumping data for table qlbh.personal_access_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.products: ~5 rows (approximately)
REPLACE INTO `products` (`id`, `category_id`, `brand_id`, `unit_id`, `has_unit_conversion`, `conversion_unit_id`, `conversion_factor`, `name`, `search_text`, `slug`, `sku`, `barcode`, `product_type`, `image`, `warranty_days`, `allow_negative_stock`, `cost_price`, `sell_price`, `tax_percent`, `stock`, `sold_count`, `alert_stock`, `manage_stock_by_serial`, `is_active`, `description`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, 1, 0, NULL, 1, 'Điện thoại Samsung A06 4G', 'Điện thoại samsung a06 4g dthsam Điện thoại samsung', 'dien-thoai-samsung-a06-4g', 'DTHSAM', NULL, 'imei', 'products/rrOBYyE2ma9d65uTOsYdSOF9cZvYYaaIZMU7H3Mq.jpg', 360, 0, 2500000.00, 3000000.00, 0.00, 2, 0, 0, 1, 1, NULL, NULL, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(2, 1, 1, 1, 0, NULL, 1, 'Điên thoại Samsung A17 5G', 'Điên thoại samsung a17 5g dthsam-001 Điện thoại samsung', 'dien-thoai-samsung-a17-5g', 'DTHSAM-001', NULL, 'imei', 'products/TqBFB7rf10G7tsqR9iGYqRohMPmX4X5g9oBnvLXp.jpg', 360, 0, 4500000.00, 5000000.00, 0.00, 2, 0, 0, 1, 1, NULL, NULL, '2026-10-20 16:06:08', '2026-10-20 16:20:32'),
	(3, 4, 2, 2, 1, 3, 500, 'Dây mạng ngoài trời kèm dây cường lực', 'dây mạng ngoài trời kèm dây cường lực vtuvha vật tư việt hàn', 'day-mang-ngoai-troi-kem-day-cuong-luc', 'VTUVHA', NULL, 'normal', 'products/Ov2bz6WMvHAXApqFGwIIz8YicEYu4nhUKYrqOH85.jpg', 0, 0, 3600.00, 7000.00, 0.00, 500, 0, 0, 0, 1, NULL, NULL, '2026-10-20 16:10:30', '2026-10-20 16:11:45'),
	(4, 2, 3, 1, 0, NULL, 1, 'Tai nghe Samsung AKG', 'tai nghe samsung akg pkisam phụ kiện samsung', 'tai-nghe-samsung-akg', 'PKISAM', NULL, 'imei', 'products/QqTW5S7UsewmvOvKPNXuaxVzAXCykf5hvFSSchqt.jpg', 180, 0, 100000.00, 150000.00, 0.00, 2, 0, 0, 1, 1, NULL, NULL, '2026-10-20 23:48:23', '2026-10-20 23:57:42'),
	(5, 2, 4, NULL, 0, NULL, 1, 'Củ sạc nhanh 20w', 'củ sạc nhanh 20w pkioem phụ kiện oem', 'cu-sac-nhanh-20w', 'PKIOEM', NULL, 'normal', 'products/BZxJLwsllCs3UETWfy9scBM5VTcDpnTh6paTRyMP.jpg', 90, 0, 30000.00, 60000.00, 0.00, 0, 0, 0, 0, 1, NULL, NULL, '2026-10-21 00:00:20', '2026-10-21 00:00:20');

-- Dumping data for table qlbh.product_imeis: ~6 rows (approximately)
REPLACE INTO `product_imeis` (`id`, `product_id`, `variant_id`, `supplier_id`, `customer_id`, `sale_id`, `imei`, `serial`, `color`, `storage`, `extra_info`, `condition`, `battery_health`, `purchase_price`, `cost_price`, `sell_price`, `warranty_expired_at`, `customer_warranty_expires_at`, `customer_warranty_days`, `customer_warranty_voided_at`, `customer_warranty_void_reason`, `imported_at`, `sold_at`, `status`, `note`, `deleted_at`, `created_at`, `updated_at`, `customer_warranty_voided_by`) VALUES
	(1, 2, 13, NULL, NULL, NULL, '111111', NULL, NULL, NULL, '{"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}', 'new', NULL, 0.00, 0.00, 0.00, '2027-10-19 17:00:00', '2027-10-15 16:19:45', 360, NULL, NULL, '2026-10-20 16:15:13', NULL, 'in_stock', NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:20:32', NULL),
	(2, 2, 15, NULL, NULL, NULL, '111112', NULL, NULL, NULL, '{"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}', 'new', NULL, 0.00, 0.00, 0.00, '2027-10-19 17:00:00', NULL, NULL, NULL, NULL, '2026-10-20 16:15:13', NULL, 'in_stock', NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13', NULL),
	(3, 1, 5, NULL, NULL, NULL, '222222', NULL, NULL, NULL, '{"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}', 'new', NULL, 0.00, 0.00, 0.00, '2027-10-19 17:00:00', NULL, NULL, NULL, NULL, '2026-10-20 16:15:13', NULL, 'in_stock', NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13', NULL),
	(4, 1, 10, NULL, NULL, NULL, '222221', NULL, NULL, NULL, '{"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}', 'new', NULL, 0.00, 0.00, 0.00, '2027-10-19 17:00:00', NULL, NULL, NULL, NULL, '2026-10-20 16:15:13', NULL, 'in_stock', NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13', NULL),
	(5, 4, NULL, NULL, NULL, NULL, '123321', NULL, NULL, NULL, '{"supplier_warranty_unit": "months"}', 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-10-20 23:57:42', NULL, 'in_stock', NULL, NULL, '2026-10-20 23:57:42', '2026-10-20 23:57:42', NULL),
	(6, 4, NULL, NULL, NULL, NULL, '6556776', NULL, NULL, NULL, '{"supplier_warranty_unit": "months"}', 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-10-20 23:57:42', NULL, 'in_stock', NULL, NULL, '2026-10-20 23:57:42', '2026-10-20 23:57:42', NULL);

-- Dumping data for table qlbh.product_imei_histories: ~6 rows (approximately)
REPLACE INTO `product_imei_histories` (`id`, `product_imei_id`, `imei`, `type`, `reference_type`, `reference_id`, `cost_price`, `sell_price`, `meta`, `note`, `happened_at`, `created_at`, `updated_at`) VALUES
	(1, 1, '111111', 'import', 'App\\Models\\StockImport', 2, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}, "product_id": 2, "variant_id": 13, "import_code": "IMP-20261020231513", "supplier_id": "2", "warranty_expired_at": "2027-10-19T17:00:00.000000Z"}', NULL, '2026-10-19 17:00:00', '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(2, 2, '111112', 'import', 'App\\Models\\StockImport', 2, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}, "product_id": 2, "variant_id": 15, "import_code": "IMP-20261020231513", "supplier_id": "2", "warranty_expired_at": "2027-10-19T17:00:00.000000Z"}', NULL, '2026-10-19 17:00:00', '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(3, 3, '222222', 'import', 'App\\Models\\StockImport', 2, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}, "product_id": 1, "variant_id": 5, "import_code": "IMP-20261020231513", "supplier_id": "2", "warranty_expired_at": "2027-10-19T17:00:00.000000Z"}', NULL, '2026-10-19 17:00:00', '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(4, 4, '222221', 'import', 'App\\Models\\StockImport', 2, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}, "product_id": 1, "variant_id": 10, "import_code": "IMP-20261020231513", "supplier_id": "2", "warranty_expired_at": "2027-10-19T17:00:00.000000Z"}', NULL, '2026-10-19 17:00:00', '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(5, 5, '123321', 'import', 'App\\Models\\StockImport', 3, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months"}, "product_id": 4, "variant_id": null, "import_code": "IMP-20261021065742", "supplier_id": "3", "warranty_expired_at": null}', NULL, '2026-10-19 17:00:00', '2026-10-20 23:57:42', '2026-10-20 23:57:42'),
	(6, 6, '6556776', 'import', 'App\\Models\\StockImport', 3, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months"}, "product_id": 4, "variant_id": null, "import_code": "IMP-20261021065742", "supplier_id": "3", "warranty_expired_at": null}', NULL, '2026-10-19 17:00:00', '2026-10-20 23:57:42', '2026-10-20 23:57:42');

-- Dumping data for table qlbh.product_variants: ~32 rows (approximately)
REPLACE INTO `product_variants` (`id`, `product_id`, `sku`, `barcode`, `attributes`, `cost_price`, `sell_price`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 1, 'DTHSAM-DEN--4G-64G', NULL, '[{"name": "Màu", "value": "Đen Tuyền"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(2, 1, 'DTHSAM-DEN--4G-128G', NULL, '[{"name": "Màu", "value": "Đen Tuyền"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(3, 1, 'DTHSAM-DEN--6G-64G', NULL, '[{"name": "Màu", "value": "Đen Tuyền"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(4, 1, 'DTHSAM-DEN--6G-128G', NULL, '[{"name": "Màu", "value": "Đen Tuyền"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(5, 1, 'DTHSAM-XANH-4G-64G', NULL, '[{"name": "Màu", "value": "Xanh Băng"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 1, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(6, 1, 'DTHSAM-XANH-4G-128G', NULL, '[{"name": "Màu", "value": "Xanh Băng"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(7, 1, 'DTHSAM-XANH-6G-64G', NULL, '[{"name": "Màu", "value": "Xanh Băng"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(8, 1, 'DTHSAM-XANH-6G-128G', NULL, '[{"name": "Màu", "value": "Xanh Băng"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(9, 1, 'DTHSAM-XANH-4G-64G-001', NULL, '[{"name": "Màu", "value": "Xanh Ngọc"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(10, 1, 'DTHSAM-XANH-4G-128G-001', NULL, '[{"name": "Màu", "value": "Xanh Ngọc"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 1, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(11, 1, 'DTHSAM-XANH-6G-64G-001', NULL, '[{"name": "Màu", "value": "Xanh Ngọc"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "64G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:00', '2026-10-21 00:05:12'),
	(12, 1, 'DTHSAM-XANH-6G-128G-001', NULL, '[{"name": "Màu", "value": "Xanh Ngọc"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:01:01', '2026-10-21 00:05:12'),
	(13, 2, 'DTHSAM-DEN--4G-128G-001', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 1, 1, '2026-10-20 16:06:08', '2026-10-20 16:20:32'),
	(14, 2, 'DTHSAM-DEN--4G-256G', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(15, 2, 'DTHSAM-DEN--6G-128G-001', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 1, 1, '2026-10-20 16:06:08', '2026-10-20 16:15:13'),
	(16, 2, 'DTHSAM-DEN--6G-256G', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(17, 2, 'DTHSAM-DEN--8G-128G', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(18, 2, 'DTHSAM-DEN--8G-256G', NULL, '[{"name": "Màu", "value": "Đen ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(19, 2, 'DTHSAM-XAM--4G-128G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(20, 2, 'DTHSAM-XAM--4G-256G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(21, 2, 'DTHSAM-XAM--6G-128G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(22, 2, 'DTHSAM-XAM--6G-256G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(23, 2, 'DTHSAM-XAM--8G-128G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(24, 2, 'DTHSAM-XAM--8G-256G', NULL, '[{"name": "Màu", "value": "Xám ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(25, 2, 'DTHSAM-XANH-4G-128G-002', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(26, 2, 'DTHSAM-XANH-4G-256G', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "4G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(27, 2, 'DTHSAM-XANH-6G-128G-002', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(28, 2, 'DTHSAM-XANH-6G-256G', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "6G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(29, 2, 'DTHSAM-XANH-8G-128G', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "128G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(30, 2, 'DTHSAM-XANH-8G-256G', NULL, '[{"name": "Màu", "value": "Xanh ánh kim"}, {"name": "Ram", "value": "8G"}, {"name": "Rom", "value": "256G"}]', 0.00, 0.00, 0, 1, '2026-10-20 16:06:08', '2026-10-20 16:06:08'),
	(31, 5, 'PKIOEM-US', NULL, '[{"name": "Chuẩn cắm", "value": "US"}]', 0.00, 70000.00, 10, 1, '2026-10-21 00:00:20', '2026-10-21 00:02:15'),
	(32, 5, 'PKIOEM-UK', NULL, '[{"name": "Chuẩn cắm", "value": "UK"}]', 0.00, 60000.00, 10, 1, '2026-10-21 00:00:20', '2026-10-21 00:02:15');

-- Dumping data for table qlbh.recurring_expenses: ~0 rows (approximately)

-- Dumping data for table qlbh.recurring_expense_payments: ~0 rows (approximately)

-- Dumping data for table qlbh.repairs: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_images: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_parts: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_timelines: ~0 rows (approximately)

-- Dumping data for table qlbh.roles: ~2 rows (approximately)
REPLACE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26'),
	(2, 'Super Admin', 'web', '2026-10-20 15:44:26', '2026-10-20 15:44:26');

-- Dumping data for table qlbh.role_has_permissions: ~101 rows (approximately)
REPLACE INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
	(1, 1),
	(2, 1),
	(3, 1),
	(4, 1),
	(5, 1),
	(6, 1),
	(7, 1),
	(8, 1),
	(9, 1),
	(10, 1),
	(11, 1),
	(12, 1),
	(13, 1),
	(14, 1),
	(15, 1),
	(16, 1),
	(17, 1),
	(18, 1),
	(19, 1),
	(20, 1),
	(21, 1),
	(22, 1),
	(23, 1),
	(24, 1),
	(25, 1),
	(26, 1),
	(27, 1),
	(28, 1),
	(29, 1),
	(30, 1),
	(31, 1),
	(32, 1),
	(33, 1),
	(34, 1),
	(35, 1),
	(36, 1),
	(37, 1),
	(38, 1),
	(39, 1),
	(40, 1),
	(41, 1),
	(42, 1),
	(43, 1),
	(44, 1),
	(45, 1),
	(47, 1),
	(48, 1),
	(49, 1),
	(50, 1),
	(51, 1),
	(1, 2),
	(2, 2),
	(3, 2),
	(4, 2),
	(5, 2),
	(6, 2),
	(7, 2),
	(8, 2),
	(9, 2),
	(10, 2),
	(11, 2),
	(12, 2),
	(13, 2),
	(14, 2),
	(15, 2),
	(16, 2),
	(17, 2),
	(18, 2),
	(19, 2),
	(20, 2),
	(21, 2),
	(22, 2),
	(23, 2),
	(24, 2),
	(25, 2),
	(26, 2),
	(27, 2),
	(28, 2),
	(29, 2),
	(30, 2),
	(31, 2),
	(32, 2),
	(33, 2),
	(34, 2),
	(35, 2),
	(36, 2),
	(37, 2),
	(38, 2),
	(39, 2),
	(40, 2),
	(41, 2),
	(42, 2),
	(43, 2),
	(44, 2),
	(45, 2),
	(46, 2),
	(47, 2),
	(48, 2),
	(49, 2),
	(50, 2),
	(51, 2);

-- Dumping data for table qlbh.sales: ~1 rows (approximately)
REPLACE INTO `sales` (`id`, `code`, `customer_id`, `user_id`, `work_shift_id`, `subtotal`, `discount`, `tax`, `grand_total`, `paid_amount`, `change_amount`, `payment_method`, `bank_account`, `status`, `cancel_reason`, `cancelled_at`, `note`, `created_at`, `updated_at`) VALUES
	(1, 'INV-20261020231812', 1, 1, 1, 5000000.00, 0.00, 0.00, 5000000.00, 4500000.00, 0.00, 'cash', NULL, 'cancelled', 'Khách đổi ý không mua nữa', '2026-10-20 16:20:32', NULL, '2026-10-20 16:19:45', '2026-10-20 16:20:32');

-- Dumping data for table qlbh.sale_items: ~0 rows (approximately)
REPLACE INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_imei_id`, `unit_id`, `unit_name`, `quantity`, `base_quantity`, `conversion_factor`, `unit_price`, `discount`, `tax`, `subtotal`, `note`, `discount_type`, `discount_value`, `warranty_days`, `warranty_value`, `warranty_unit`, `created_at`, `updated_at`) VALUES
	(1, 1, 2, 13, 1, 1, 'Chiếc', 1, 1, 1, 5000000.00, 0.00, 0.00, 5000000.00, NULL, NULL, 0.00, 360, NULL, NULL, '2026-10-20 16:19:45', '2026-10-20 16:19:45');

-- Dumping data for table qlbh.sale_item_gifts: ~0 rows (approximately)

-- Dumping data for table qlbh.sessions: ~0 rows (approximately)

-- Dumping data for table qlbh.settings: ~21 rows (approximately)
REPLACE INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
	(1, 'shop_name', 'Đức Hòa', '2026-10-20 23:35:57', '2026-10-20 23:35:57'),
	(2, 'currency_symbol', '₫', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(3, 'currency_format', 'vi-VN', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(4, 'app_locale', 'vi', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(5, 'app_timezone', 'Asia/Ho_Chi_Minh', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(6, 'date_format', 'd/m/Y', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(7, 'allow_negative_stock', '1', '2026-10-20 23:35:58', '2026-10-21 00:20:32'),
	(8, 'inventory_alert_enabled', '1', '2026-10-20 23:35:58', '2026-10-21 00:20:32'),
	(9, 'inventory_alert_threshold', '2', '2026-10-20 23:35:58', '2026-10-21 00:20:33'),
	(10, 'print_paper_size', '80mm', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(11, 'print_orientation', 'portrait', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(12, 'bank_bin', '970422', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(13, 'bank_account', '0906064789', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(14, 'bank_account_name', 'Do Duc Hoa', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(15, 'bank_transfer_content', 'Thanh toan don hang', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(16, 'bank_qr_theme', 'indigo', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(17, 'payment_voice_language', 'vi-VN', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(18, 'payment_voice_enabled', '1', '2026-10-20 23:35:58', '2026-10-21 00:20:33'),
	(19, 'bank_accounts', '[{"id":"legacy-default","bank_bin":"970422","bank_name":"MBBank","bank_account":"0906064789","bank_account_name":"Do Duc Hoa","transfer_content_mode":"default","is_default":true,"payment_provider":"","payment_provider_account_id":"","payment_api_key":"","payment_api_access_key":"","payment_api_secret_key":"","payment_webhook_secret":""},{"id":"bank-1792333601769-wchnt","bank_bin":"970436","bank_name":"Vietcombank","bank_account":"0211000535558","bank_account_name":"Do Duc Hoa","transfer_content_mode":"default","is_default":false,"payment_provider":"","payment_provider_account_id":"","payment_api_key":"","payment_api_access_key":"","payment_api_secret_key":"","payment_webhook_secret":""}]', '2026-10-20 23:35:58', '2026-10-20 23:35:58'),
	(20, 'backup_last_run', '2026-10-21 07:24:00', '2026-10-21 00:24:00', '2026-10-21 00:24:00'),
	(21, 'backup_last_error', NULL, '2026-10-21 00:24:00', '2026-10-21 00:24:00');

-- Dumping data for table qlbh.stock_imports: ~0 rows (approximately)
REPLACE INTO `stock_imports` (`id`, `code`, `supplier_id`, `party_type`, `customer_id`, `party_name`, `party_phone`, `party_cccd`, `party_address`, `user_id`, `import_date`, `discount`, `extra_fee`, `total_amount`, `grand_total`, `paid_amount`, `note`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'IMP-20261020231145', 1, 'supplier', NULL, 'Hải Đường', '123321456', NULL, NULL, 1, '2026-10-20 00:00:00', 0.00, 0.00, 1800000.00, 1800000.00, 0.00, NULL, 'completed', '2026-10-20 16:11:45', '2026-10-20 16:11:45'),
	(2, 'IMP-20261020231513', 2, 'supplier', NULL, 'Shoppe', '000000', NULL, NULL, 1, '2026-10-20 00:00:00', 0.00, 0.00, 14600000.00, 14600000.00, 0.00, NULL, 'completed', '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(3, 'IMP-20261021065742', 3, 'supplier', NULL, 'Duy Crown', '12234431', NULL, NULL, 1, '2026-10-20 00:00:00', 0.00, 0.00, 210000.00, 210000.00, 0.00, NULL, 'completed', '2026-10-20 23:57:42', '2026-10-20 23:57:42'),
	(4, 'IMP-20261021070215', 3, 'supplier', NULL, 'Duy Crown', '12234431', NULL, NULL, 1, '2026-10-21 00:00:00', 0.00, 0.00, 690000.00, 690000.00, 0.00, NULL, 'completed', '2026-10-21 00:02:15', '2026-10-21 00:02:15');

-- Dumping data for table qlbh.stock_import_items: ~9 rows (approximately)
REPLACE INTO `stock_import_items` (`id`, `stock_import_id`, `product_id`, `variant_id`, `unit_id`, `unit_name`, `quantity`, `base_quantity`, `conversion_factor`, `cost_price`, `supplier_warranty_value`, `supplier_warranty_unit`, `extra_info`, `created_at`, `updated_at`) VALUES
	(1, 1, 3, NULL, 3, 'cuộn', 1, 500, 500, 1800000.00, NULL, NULL, NULL, '2026-10-20 16:11:45', '2026-10-20 16:11:45'),
	(2, 2, 2, 13, 1, 'Chiếc', 1, 1, 1, 4600000.00, NULL, NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(3, 2, 2, 15, 1, 'Chiếc', 1, 1, 1, 4600000.00, NULL, NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(4, 2, 1, 5, 1, 'Chiếc', 1, 1, 1, 2600000.00, NULL, NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(5, 2, 1, 10, 1, 'Chiếc', 1, 1, 1, 2800000.00, NULL, NULL, NULL, '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(6, 3, 4, NULL, 1, 'Chiếc', 1, 1, 1, 100000.00, NULL, NULL, NULL, '2026-10-20 23:57:42', '2026-10-20 23:57:42'),
	(7, 3, 4, NULL, 1, 'Chiếc', 1, 1, 1, 110000.00, NULL, NULL, NULL, '2026-10-20 23:57:42', '2026-10-20 23:57:42'),
	(8, 4, 5, 31, NULL, NULL, 10, 10, 1, 35000.00, NULL, NULL, NULL, '2026-10-21 00:02:15', '2026-10-21 00:02:15'),
	(9, 4, 5, 32, NULL, NULL, 10, 10, 1, 34000.00, NULL, NULL, NULL, '2026-10-21 00:02:15', '2026-10-21 00:02:15');

-- Dumping data for table qlbh.suppliers: ~3 rows (approximately)
REPLACE INTO `suppliers` (`id`, `code`, `name`, `search_text`, `contact_person`, `phone`, `email`, `tax_code`, `province`, `district`, `ward`, `address`, `debt_balance`, `total_purchase`, `total_orders`, `note`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'NCC00001', 'Hải Đường', 'hai duong', NULL, '123321456', NULL, NULL, NULL, NULL, NULL, NULL, 1800000.00, 0.00, 0, NULL, 1, '2026-10-20 16:11:26', '2026-10-20 16:11:45', NULL),
	(2, 'NCC00002', 'Shoppe', 'shoppe', NULL, '000000', NULL, NULL, NULL, NULL, NULL, NULL, 14600000.00, 0.00, 0, NULL, 1, '2026-10-20 16:12:11', '2026-10-20 16:15:13', NULL),
	(3, 'NCC00003', 'Duy Crown', 'duy crown', NULL, '12234431', NULL, NULL, NULL, NULL, NULL, NULL, 900000.00, 0.00, 0, NULL, 1, '2026-10-20 23:56:22', '2026-10-21 00:02:15', NULL);

-- Dumping data for table qlbh.supplier_debts: ~0 rows (approximately)
REPLACE INTO `supplier_debts` (`id`, `supplier_id`, `type`, `amount`, `source_type`, `source_id`, `payment_method`, `note`, `user_id`, `created_at`, `updated_at`) VALUES
	(1, 1, 'increase', 1800000.00, 'App\\Models\\StockImport', 1, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261020231145', 1, '2026-10-20 16:11:45', '2026-10-20 16:11:45'),
	(2, 2, 'increase', 14600000.00, 'App\\Models\\StockImport', 2, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261020231513', 1, '2026-10-20 16:15:13', '2026-10-20 16:15:13'),
	(3, 3, 'increase', 210000.00, 'App\\Models\\StockImport', 3, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261021065742', 1, '2026-10-20 23:57:42', '2026-10-20 23:57:42'),
	(4, 3, 'increase', 690000.00, 'App\\Models\\StockImport', 4, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261021070215', 1, '2026-10-21 00:02:15', '2026-10-21 00:02:15');

-- Dumping data for table qlbh.units: ~2 rows (approximately)
REPLACE INTO `units` (`id`, `name`, `short_name`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Chiếc', 'Chiếc', 1, NULL, '2026-10-20 15:53:41', '2026-10-20 15:53:41'),
	(2, 'Mét', 'm', 1, NULL, '2026-10-20 16:08:12', '2026-10-20 16:08:12'),
	(3, 'Cuộn', 'cuộn', 1, NULL, '2026-10-20 16:09:00', '2026-10-20 16:09:00');

-- Dumping data for table qlbh.users: ~1 rows (approximately)
REPLACE INTO `users` (`id`, `name`, `username`, `phone`, `email`, `avatar`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin', '0906064789', 'admin@gmail.com', NULL, NULL, '$2y$12$DucrkL1IM1N0AA0pKolCPeXDm3s6kffWDKm89pzThDkt2r//ZZU9y', 'L1XITW5okJrZza5s9Csw330NZviWwrDq1CSsB0oEV4Py0XJSNir9sUl5cOge', '2026-10-20 15:44:30', '2026-10-20 15:44:30');

-- Dumping data for table qlbh.work_shifts: ~0 rows (approximately)
REPLACE INTO `work_shifts` (`id`, `user_id`, `opening_amount`, `opening_denominations`, `opening_bank_balances`, `cash_collected`, `customer_debt_amount`, `expected_cash`, `closing_amount`, `closing_denominations`, `closing_bank_balances`, `opened_at`, `closed_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 0.00, '{"1000": 0, "2000": 0, "5000": 0, "10000": 0, "20000": 0, "50000": 0, "100000": 0, "200000": 0, "500000": 0}', '{"legacy-default": 0, "bank-1792333601769-wchnt": 0}', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-20 16:19:09', NULL, '2026-10-20 16:19:09', '2026-10-20 16:19:09');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
