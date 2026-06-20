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

-- Dumping data for table qlbh.brands: ~3 rows (approximately)
REPLACE INTO `brands` (`id`, `name`, `slug`, `sort_order`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Samsung', 'samsung', 0, 1, NULL, '2026-06-19 09:03:23', '2026-06-19 09:03:23'),
	(2, 'Apple', 'apple', 0, 1, NULL, '2026-06-19 09:03:30', '2026-06-19 09:03:30'),
	(3, 'OEM', 'oem', 0, 1, NULL, '2026-06-19 09:03:38', '2026-06-19 09:03:38');

-- Dumping data for table qlbh.cache: ~0 rows (approximately)

-- Dumping data for table qlbh.cache_locks: ~0 rows (approximately)

-- Dumping data for table qlbh.categories: ~2 rows (approximately)
REPLACE INTO `categories` (`id`, `parent_id`, `name`, `slug`, `sort_order`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, NULL, 'Điện Thoại', 'dien-thoai', 0, 1, '2026-06-19 09:02:48', '2026-06-19 09:02:48', NULL),
	(2, NULL, 'Phụ kiện', 'phu-kien', 0, 1, '2026-06-19 09:03:02', '2026-06-19 09:03:02', NULL);

-- Dumping data for table qlbh.customers: ~4 rows (approximately)
REPLACE INTO `customers` (`id`, `code`, `full_name`, `search_text`, `phone`, `email`, `birthday`, `gender`, `cccd`, `province`, `district`, `ward`, `address`, `point_balance`, `debt_balance`, `total_spent`, `total_orders`, `last_order_at`, `customer_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'KH000001', 'Đức Hòa', 'duc hoa', '0906064789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-19 09:10:43', '2026-06-19 09:10:43', NULL),
	(2, 'KH000002', 'Kim Ngân', 'kim ngan', '123456789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-19 09:11:07', '2026-06-19 09:11:07', NULL),
	(3, 'KH000003', 'Kim Oanh', 'kim oanh', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-19 09:11:22', '2026-06-19 09:11:22', NULL),
	(4, 'KH000004', 'Lê Hiền', 'le hien', '789456123', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-19 09:11:41', '2026-06-19 09:11:41', NULL);

-- Dumping data for table qlbh.customer_debts: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_devices: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_images: ~1 rows (approximately)
REPLACE INTO `customer_images` (`id`, `customer_id`, `type`, `path`, `mime_type`, `size`, `is_primary`, `uploaded_by`, `created_at`, `updated_at`) VALUES
	(1, 1, 'portrait', 'customers/1/SrwZ0VGRc7mkOQbmScK0A1JsoC0ADgjUar8Qae3m.jpg', 'image/jpeg', 11243, 1, NULL, '2026-06-19 09:10:43', '2026-06-19 09:10:43');

-- Dumping data for table qlbh.customer_logs: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_points: ~0 rows (approximately)

-- Dumping data for table qlbh.failed_jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.hold_sales: ~0 rows (approximately)

-- Dumping data for table qlbh.jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.job_batches: ~0 rows (approximately)

-- Dumping data for table qlbh.migrations: ~24 rows (approximately)
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
	(12, '2026_05_23_145930_create_brands_table', 1),
	(13, '2026_05_23_145940_create_categories_table', 1),
	(14, '2026_05_23_145950_create_units_table', 1),
	(15, '2026_05_23_145960_create_products_table', 1),
	(16, '2026_05_23_145980_create_sales_table', 1),
	(17, '2026_05_23_145981_create_product_imeis_table', 1),
	(18, '2026_05_23_145990_create_sale_items_table', 1),
	(19, '2026_05_23_146100_create_repairs_table', 1),
	(20, '2026_05_23_146110_create_repair_images_table', 1),
	(21, '2026_05_23_146120_create_repair_timelines_table', 1),
	(22, '2026_05_25_123656_create_hold_sales_table', 1),
	(23, '2026_05_25_151943_create_personal_access_tokens_table', 1),
	(24, '2026_05_26_230107_add_sold_count_to_products_table', 1);

-- Dumping data for table qlbh.model_has_permissions: ~0 rows (approximately)

-- Dumping data for table qlbh.model_has_roles: ~1 rows (approximately)
REPLACE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
	(1, 'App\\Models\\User', 1);

-- Dumping data for table qlbh.password_reset_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.permissions: ~17 rows (approximately)
REPLACE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'categories.view', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(2, 'categories.create', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(3, 'categories.edit', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(4, 'categories.delete', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(5, 'products.view', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(6, 'products.create', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(7, 'products.edit', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(8, 'products.delete', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(9, 'brands.view', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(10, 'brands.create', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(11, 'brands.edit', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(12, 'brands.delete', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(13, 'pos.access', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(14, 'users.view', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(15, 'users.create', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(16, 'users.edit', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(17, 'users.delete', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05');

-- Dumping data for table qlbh.personal_access_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.products: ~7 rows (approximately)
REPLACE INTO `products` (`id`, `category_id`, `brand_id`, `unit_id`, `name`, `search_text`, `slug`, `sku`, `barcode`, `product_type`, `image`, `warranty_days`, `allow_negative_stock`, `cost_price`, `sell_price`, `tax_percent`, `stock`, `sold_count`, `alert_stock`, `manage_stock_by_serial`, `is_active`, `description`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, NULL, 'Samsung A07', 'samsung a07', 'samsung-a07', 'DTSSA07', NULL, 'normal', 'products/cY6bplbSH1k8nJeBG5yzxfsFbUnC9qGgAEAeAnpH.jpg', 0, 0, 2500000.00, 2700000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:04:55', '2026-06-19 09:04:55'),
	(2, 1, 1, NULL, 'Samsung A17', 'samsung a17', 'samsung-a17', 'DTSSA17', NULL, 'normal', 'products/4f3DOMkABUEJ1YwJPb4NmpG4vdzHbC3muYteVZBa.jpg', 0, 0, 3000000.00, 3500000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:05:44', '2026-06-19 09:05:44'),
	(3, 1, 1, NULL, 'Điện Thoại Samsung A17 5G', 'dien thoai samsung a17 5g', 'dien-thoai-samsung-a17-5g', 'DTSSA175G', NULL, 'normal', 'products/W7YauvpjAx1zTO2aqMRXAEsmCpTLnOi0fyAzQIdy.jpg', 0, 0, 3000000.00, 3500000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:07:13', '2026-06-19 09:07:13'),
	(4, 1, 2, NULL, 'Iphone 11', 'iphone 11', 'iphone-11', 'DTIP11', NULL, 'normal', 'products/SnTSwqmK4bwfdfUmBumWe5SjGRuHOnOTzCpXxKNE.jpg', 0, 0, 5000000.00, 5500000.00, 0.00, 3, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:08:10', '2026-06-19 09:08:10'),
	(5, 1, 2, NULL, 'Điện Thoại IP 12', 'dien thoai ip 12', 'dien-thoai-ip-12', 'DTIP12', NULL, 'normal', 'products/5lAy8J8uJ63KfaXN8MYtIUWjoMKaCka4uDMMytHO.jpg', 0, 0, 6000000.00, 6500000.00, 0.00, 1, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:08:58', '2026-06-19 09:08:58'),
	(6, 2, 3, NULL, 'Dây sạc rẻ', 'day sac re', 'day-sac-re', 'DSRE', NULL, 'normal', 'products/REPcUXh3qJ3YHnoMWIaqyOb7xnXgnjBetmcqqRPb.jpg', 0, 0, 10000.00, 20000.00, 0.00, 100, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:09:34', '2026-06-19 09:09:34'),
	(7, 2, 3, NULL, 'Củ sạc 20w', 'cu sac 20w', 'cu-sac-20w', 'CS20W', NULL, 'normal', 'products/6fqRfYcyfjk4aXUGxNuR4YFWFZoFnes0llTyS7CL.jpg', 0, 0, 30000.00, 50000.00, 0.00, 100, 0, 0, 0, 1, NULL, NULL, '2026-06-19 09:10:10', '2026-06-19 09:10:10');

-- Dumping data for table qlbh.product_imeis: ~10 rows (approximately)
REPLACE INTO `product_imeis` (`id`, `product_id`, `supplier_id`, `customer_id`, `sale_id`, `imei`, `serial`, `color`, `storage`, `condition`, `battery_health`, `purchase_price`, `cost_price`, `sell_price`, `warranty_expired_at`, `imported_at`, `sold_at`, `status`, `note`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, NULL, NULL, NULL, '0000', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:04:55', '2026-06-19 09:04:55'),
	(2, 1, NULL, NULL, NULL, '1111', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:04:55', '2026-06-19 09:04:55'),
	(3, 2, NULL, NULL, NULL, '2222', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:05:44', '2026-06-19 09:05:44'),
	(4, 2, NULL, NULL, NULL, '3333', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:05:44', '2026-06-19 09:05:44'),
	(5, 3, NULL, NULL, NULL, '4444', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:07:13', '2026-06-19 09:07:13'),
	(6, 3, NULL, NULL, NULL, '5555', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:07:13', '2026-06-19 09:07:13'),
	(7, 4, NULL, NULL, NULL, '6666', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:08:10', '2026-06-19 09:08:10'),
	(8, 4, NULL, NULL, NULL, '7777', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:08:10', '2026-06-19 09:08:10'),
	(9, 4, NULL, NULL, NULL, '8888', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:08:10', '2026-06-19 09:08:10'),
	(10, 5, NULL, NULL, NULL, '9999', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-19 09:08:58', '2026-06-19 09:08:58');

-- Dumping data for table qlbh.repairs: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_images: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_timelines: ~0 rows (approximately)

-- Dumping data for table qlbh.roles: ~2 rows (approximately)
REPLACE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Super Admin', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05'),
	(2, 'admin', 'web', '2026-06-19 08:44:05', '2026-06-19 08:44:05');

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

-- Dumping data for table qlbh.sessions: ~0 rows (approximately)

-- Dumping data for table qlbh.units: ~0 rows (approximately)

-- Dumping data for table qlbh.users: ~1 rows (approximately)
REPLACE INTO `users` (`id`, `name`, `username`, `phone`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin', '0906064789', 'admin@gmail.com', NULL, '$2y$12$bF6brt2gvYO4emoh6FLds.kj4pW5o.2EikqqvOmoOI/XUaGGrMsjG', NULL, '2026-06-19 08:44:05', '2026-06-19 08:44:05');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
