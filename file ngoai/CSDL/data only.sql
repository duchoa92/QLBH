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

-- Dumping data for table qlbh.brands: ~0 rows (approximately)
REPLACE INTO `brands` (`id`, `name`, `search_text`, `slug`, `category_id`, `sort_order`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Samsung', 'samsung', 'samsung', 1, 0, 1, NULL, '2026-08-15 16:46:06', '2026-08-15 16:46:06'),
	(2, 'Apple', 'apple', 'apple', 1, 0, 1, NULL, '2026-08-15 16:46:20', '2026-08-15 16:46:20'),
	(3, 'Ezivi', 'ezivi', 'ezivi', 2, 0, 1, NULL, '2026-08-15 16:46:36', '2026-08-17 07:40:17'),
	(4, 'OEM', 'oem', 'oem', 3, 0, 1, NULL, '2026-08-17 07:40:29', '2026-08-17 07:40:29');

-- Dumping data for table qlbh.cache: ~0 rows (approximately)

-- Dumping data for table qlbh.cache_locks: ~0 rows (approximately)

-- Dumping data for table qlbh.categories: ~0 rows (approximately)
REPLACE INTO `categories` (`id`, `parent_id`, `name`, `slug`, `attributes`, `sort_order`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, NULL, 'Điện Thoại', 'dien-thoai', NULL, 0, 1, '2026-08-15 16:43:02', '2026-08-15 16:43:02', NULL),
	(2, NULL, 'Camera', 'camera', NULL, 0, 1, '2026-08-15 16:44:39', '2026-08-15 16:44:39', NULL),
	(3, NULL, 'Phụ kiện', 'phu-kien', NULL, 0, 1, '2026-08-15 16:45:09', '2026-08-15 16:45:09', NULL);

-- Dumping data for table qlbh.category_attributes: ~0 rows (approximately)
REPLACE INTO `category_attributes` (`id`, `category_id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Màu sắc', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(2, 1, 'Ram', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(3, 1, 'Bộ nhớ', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(4, 1, 'Phiên bản', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(5, 2, 'Độ phân giải', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(6, 2, 'Màu ban đêm', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(7, 2, 'Trong/Ngoài', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(8, 3, 'OEM', '2026-08-15 16:45:09', '2026-08-15 16:45:09');

-- Dumping data for table qlbh.category_attribute_values: ~0 rows (approximately)
REPLACE INTO `category_attribute_values` (`id`, `attribute_id`, `value`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Đen', '2026-08-15 16:48:02', '2026-08-15 16:48:02'),
	(2, 2, '3G', '2026-08-15 16:48:02', '2026-08-15 16:48:02'),
	(3, 3, '32Gb', '2026-08-15 16:48:02', '2026-08-15 16:48:02'),
	(4, 4, 'Quốc tế', '2026-08-15 16:48:02', '2026-08-15 16:48:02'),
	(5, 1, 'Xanh', '2026-08-15 16:49:37', '2026-08-15 16:49:37'),
	(6, 1, 'Vàng', '2026-08-15 16:49:37', '2026-08-15 16:49:37'),
	(7, 3, '128Gb', '2026-08-15 16:49:37', '2026-08-15 16:49:37'),
	(10, 1, 'Tím', '2026-08-16 06:52:00', '2026-08-16 06:52:00'),
	(11, 2, '4G', '2026-08-16 06:52:00', '2026-08-16 06:52:00'),
	(12, 3, '64G', '2026-08-16 06:52:00', '2026-08-16 06:52:00'),
	(13, 2, '6G', '2026-08-16 06:53:02', '2026-08-16 06:53:02'),
	(14, 3, '128G', '2026-08-16 06:53:02', '2026-08-16 06:53:02'),
	(16, 3, '64Gb', '2026-08-17 07:39:25', '2026-08-17 07:39:25');

-- Dumping data for table qlbh.customers: ~0 rows (approximately)
REPLACE INTO `customers` (`id`, `code`, `full_name`, `search_text`, `phone`, `email`, `birthday`, `gender`, `cccd`, `province`, `district`, `ward`, `address`, `point_balance`, `debt_balance`, `total_spent`, `total_orders`, `last_order_at`, `customer_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'KH000001', 'Đức Hòa', 'duc hoa', '0906064789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-09-12 17:18:11', '2026-09-12 17:18:11', NULL),
	(2, 'KH000002', 'Kim Ngân', 'kim ngan', '1234567', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-09-12 17:18:28', '2026-09-12 17:18:28', NULL);

-- Dumping data for table qlbh.customer_debts: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_devices: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_images: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_logs: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_points: ~0 rows (approximately)

-- Dumping data for table qlbh.export_histories: ~0 rows (approximately)

-- Dumping data for table qlbh.failed_jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.hold_sales: ~0 rows (approximately)

-- Dumping data for table qlbh.jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.job_batches: ~0 rows (approximately)

-- Dumping data for table qlbh.migrations: ~0 rows (approximately)
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
	(30, '2026_08_17_232234_create_stock_imports_table', 2),
	(31, '2026_08_17_232315_create_stock_import_items_table', 2),
	(32, '2026_09_13_000001_add_unit_conversion_to_stock_import_items_table', 1);

-- Dumping data for table qlbh.model_has_permissions: ~0 rows (approximately)

-- Dumping data for table qlbh.model_has_roles: ~1 rows (approximately)
REPLACE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
	(1, 'App\\Models\\User', 1);

-- Dumping data for table qlbh.password_reset_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.permissions: ~17 rows (approximately)
REPLACE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'categories.view', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(2, 'categories.create', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(3, 'categories.edit', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(4, 'categories.delete', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(5, 'products.view', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(6, 'products.create', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(7, 'products.edit', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(8, 'products.delete', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(9, 'brands.view', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(10, 'brands.create', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(11, 'brands.edit', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(12, 'brands.delete', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(13, 'pos.access', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(14, 'users.view', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32'),
	(15, 'users.create', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32'),
	(16, 'users.edit', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32'),
	(17, 'users.delete', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32');

-- Dumping data for table qlbh.personal_access_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.products: ~0 rows (approximately)
REPLACE INTO `products` (`id`, `category_id`, `brand_id`, `unit_id`, `name`, `search_text`, `slug`, `sku`, `barcode`, `product_type`, `image`, `warranty_days`, `allow_negative_stock`, `cost_price`, `sell_price`, `tax_percent`, `stock`, `sold_count`, `alert_stock`, `manage_stock_by_serial`, `is_active`, `description`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, NULL, 'Samsung A01', 'samsung a01 dthsam Điện thoại samsung', 'samsung-a01', 'DTHSAM', NULL, 'imei', 'products/KMSQXS12HHbGSgrurtiV7mo0KtLYaGKuK6ed0WzG.jpg', 0, 0, 100.00, 120.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-15 16:48:02', '2026-08-15 16:48:02'),
	(2, 1, 2, NULL, 'Iphone 12 promax', 'iphone 12 promax dthapp Điện thoại apple', 'iphone-12-promax', 'DTHAPP', NULL, 'imei', 'products/fF3B8pKXVCEleDLxXJygQX8EGKz1ThxqCZHA69B4.jpg', 0, 0, 100.00, 120.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-15 16:49:37', '2026-08-15 16:49:37'),
	(3, 3, 4, NULL, 'Củ Sạc 20W', 'củ sạc 20w pkigen phụ kiện oem', 'cu-sac-20w', 'PKIGEN', NULL, 'normal', 'products/bpM8fcLnfozCr072WV6B4DsrseaYibtIeYEH7VPs.jpg', 0, 0, 100.00, 120.00, 0.00, 0, 0, 0, 0, 1, NULL, NULL, '2026-08-15 16:50:31', '2026-08-17 07:40:46'),
	(6, 1, 1, NULL, 'A03', 'a03 dthsam-001 Điện thoại samsung', 'a03', 'DTHSAM-001', NULL, 'imei', 'products/WqwrsfjrXyRwFoVwh5RTOAGC6l0d9wqo7DJbw41s.jpg', 0, 0, 100.00, 110.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-16 06:52:00', '2026-08-17 07:43:27');

-- Dumping data for table qlbh.product_imeis: ~0 rows (approximately)

-- Dumping data for table qlbh.product_variants: ~0 rows (approximately)
REPLACE INTO `product_variants` (`id`, `product_id`, `sku`, `barcode`, `attributes`, `cost_price`, `sell_price`, `stock`, `created_at`, `updated_at`) VALUES
	(76, 2, 'DTHAPP-DEN-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 120.00, 0, '2026-08-17 07:40:51', '2026-08-17 07:40:51'),
	(81, 1, 'DTHSAM-DEN-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, '2026-08-17 07:42:01', '2026-08-17 07:42:01'),
	(82, 1, 'DTHSAM-XANH-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, '2026-08-17 07:42:01', '2026-08-17 07:42:01'),
	(83, 1, 'DTHSAM-VANG-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Vàng"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, '2026-08-17 07:42:01', '2026-08-17 07:42:01'),
	(84, 1, 'DTHSAM-TIM-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Tím"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, '2026-08-17 07:42:01', '2026-08-17 07:42:01'),
	(129, 6, 'DTHSAM-XANH-4G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(130, 6, 'DTHSAM-XANH-4G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(131, 6, 'DTHSAM-XANH-6G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(132, 6, 'DTHSAM-XANH-6G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(133, 6, 'DTHSAM-DEN-4G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(134, 6, 'DTHSAM-DEN-4G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(135, 6, 'DTHSAM-DEN-6G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15'),
	(136, 6, 'DTHSAM-DEN-6G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, '2026-08-17 07:45:15', '2026-08-17 07:45:15');

-- Dumping data for table qlbh.repairs: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_images: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_timelines: ~0 rows (approximately)

-- Dumping data for table qlbh.roles: ~2 rows (approximately)
REPLACE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Super Admin', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(2, 'admin', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32');

-- Dumping data for table qlbh.role_has_permissions: ~30 rows (approximately)
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
	(17, 2);

-- Dumping data for table qlbh.sales: ~0 rows (approximately)

-- Dumping data for table qlbh.sale_items: ~0 rows (approximately)

-- Dumping data for table qlbh.sale_item_gifts: ~0 rows (approximately)

-- Dumping data for table qlbh.sessions: ~0 rows (approximately)

-- Dumping data for table qlbh.settings: ~0 rows (approximately)

-- Dumping data for table qlbh.stock_imports: ~0 rows (approximately)

-- Dumping data for table qlbh.stock_import_items: ~0 rows (approximately)

-- Dumping data for table qlbh.suppliers: ~0 rows (approximately)
REPLACE INTO `suppliers` (`id`, `code`, `name`, `search_text`, `contact_person`, `phone`, `email`, `tax_code`, `province`, `district`, `ward`, `address`, `debt_balance`, `total_purchase`, `total_orders`, `note`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'NCC00001', 'Shoppe', 'shoppe', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:18', '2026-09-12 17:17:18', NULL),
	(2, 'NCC00002', 'Thắng Hải', 'thang hai', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:32', '2026-09-12 17:17:32', NULL),
	(3, 'NCC00003', 'Quân', 'quan', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:38', '2026-09-12 17:17:38', NULL);

-- Dumping data for table qlbh.units: ~0 rows (approximately)

-- Dumping data for table qlbh.users: ~1 rows (approximately)
REPLACE INTO `users` (`id`, `name`, `username`, `phone`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin', '0906064789', 'admin@gmail.com', NULL, '$2y$12$WUtVW1Ln7UXJI.iKXYlgAu724YU4eTf3di4wanGwr0OumLnL8u4w2', 'lOihNiYeuRmHYwjE2ejkJc1jdQ6ZrFUVmuw5suHIPKMNjyiQRc2MeoTmzBY0', '2026-08-15 16:40:32', '2026-08-15 16:40:32');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
