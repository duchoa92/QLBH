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
	(1, 'Samsung', 'samsung', 0, 1, NULL, '2026-06-03 08:32:08', '2026-06-03 08:32:08'),
	(2, 'Apple', 'apple', 0, 1, NULL, '2026-06-03 08:32:15', '2026-06-03 08:32:15'),
	(3, 'OEM', 'oem', 0, 1, NULL, '2026-06-03 08:32:25', '2026-06-03 08:32:25');

-- Dumping data for table qlbh.cache: ~0 rows (approximately)

-- Dumping data for table qlbh.cache_locks: ~0 rows (approximately)

-- Dumping data for table qlbh.categories: ~2 rows (approximately)
REPLACE INTO `categories` (`id`, `parent_id`, `name`, `slug`, `sort_order`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, NULL, 'Điện thoại', 'dien-thoai', 0, 1, '2026-06-03 08:32:51', '2026-06-03 08:32:51', NULL),
	(2, NULL, 'Phụ kiện', 'phu-kien', 0, 1, '2026-06-03 08:32:58', '2026-06-03 08:32:58', NULL);

-- Dumping data for table qlbh.customers: ~3 rows (approximately)
REPLACE INTO `customers` (`id`, `code`, `full_name`, `phone`, `email`, `birthday`, `gender`, `cccd`, `province`, `district`, `ward`, `address`, `point_balance`, `debt_balance`, `total_spent`, `total_orders`, `last_order_at`, `customer_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'KH000001', 'Đức Hòa', '0906064789', NULL, '1992-10-06', 'male', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-03 08:33:31', '2026-06-03 08:33:31', NULL),
	(2, 'KH000002', 'Kim Ngân', '123321', NULL, NULL, 'female', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-03 08:33:50', '2026-06-03 08:33:50', NULL),
	(3, 'KH000003', 'Kim Oanh', '789987', NULL, NULL, 'female', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-06-03 08:34:04', '2026-06-03 08:34:04', NULL);

-- Dumping data for table qlbh.customer_debts: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_devices: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_images: ~2 rows (approximately)
REPLACE INTO `customer_images` (`id`, `customer_id`, `type`, `path`, `mime_type`, `size`, `is_primary`, `uploaded_by`, `created_at`, `updated_at`) VALUES
	(1, 1, 'portrait', 'customers/1/EezJ4aWuy6LKUpp6CSJRQZJIOG1xO4AyKGasVXPh.jpg', 'image/jpeg', 27218, 1, NULL, '2026-06-03 08:33:31', '2026-06-03 08:33:31'),
	(2, 2, 'portrait', 'customers/2/ZOjFxAlIH1BEMO9ZlpihwhRwaQm8g0An61MiEIM1.jpg', 'image/jpeg', 45859, 1, NULL, '2026-06-03 08:33:50', '2026-06-03 08:33:50');

-- Dumping data for table qlbh.customer_logs: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_points: ~0 rows (approximately)

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
	(1, 'categories.view', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(2, 'categories.create', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(3, 'categories.edit', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(4, 'categories.delete', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(5, 'products.view', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(6, 'products.create', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(7, 'products.edit', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(8, 'products.delete', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(9, 'brands.view', 'web', '2026-06-03 08:31:15', '2026-06-03 08:31:15'),
	(10, 'brands.create', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(11, 'brands.edit', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(12, 'brands.delete', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(13, 'pos.access', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(14, 'users.view', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(15, 'users.create', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(16, 'users.edit', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(17, 'users.delete', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16');

-- Dumping data for table qlbh.personal_access_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.products: ~4 rows (approximately)
REPLACE INTO `products` (`id`, `category_id`, `brand_id`, `unit_id`, `name`, `slug`, `sku`, `barcode`, `product_type`, `image`, `warranty_days`, `allow_negative_stock`, `cost_price`, `sell_price`, `tax_percent`, `stock`, `sold_count`, `alert_stock`, `manage_stock_by_serial`, `is_active`, `description`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 2, NULL, 'Iphone 10', 'iphone-10', '123321', NULL, 'normal', 'products/winD0s93IMazKelTXp2yJ7wmSKF8KAKpaor80AZ2.jpg', 0, 0, 1000000.00, 1200000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-03 08:35:14', '2026-06-03 08:35:14'),
	(2, 1, 2, NULL, 'Iphone 11', 'iphone-11', '456654', NULL, 'normal', 'products/4BO6Hlc96xLuqkO5ClEIiirZti8BLzyCWy20CgR1.jpg', 0, 0, 2000000.00, 2200000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-03 08:35:53', '2026-06-03 08:35:53'),
	(3, 1, 1, NULL, 'Samsung A16', 'samsung-a16', '258852', NULL, 'normal', 'products/4SsruRhBL8XwL6LJLIB7zWmXfHuozoXA4RR5UrNS.jpg', 0, 0, 1500000.00, 200000.00, 0.00, 2, 0, 0, 0, 1, NULL, NULL, '2026-06-03 08:36:41', '2026-06-03 08:36:41'),
	(4, 2, 3, NULL, 'Dây sạc', 'day-sac', '963369', NULL, 'normal', 'products/z12eqrZMSuoz9mLBiwAUbI0AWdP3iFOzi0PinUzq.jpg', 0, 0, 20000.00, 50000.00, 0.00, 10, 0, 0, 0, 1, NULL, NULL, '2026-06-03 08:37:17', '2026-06-03 08:37:17');

-- Dumping data for table qlbh.product_imeis: ~6 rows (approximately)
REPLACE INTO `product_imeis` (`id`, `product_id`, `supplier_id`, `customer_id`, `sale_id`, `imei`, `serial`, `color`, `storage`, `condition`, `battery_health`, `purchase_price`, `cost_price`, `sell_price`, `warranty_expired_at`, `imported_at`, `sold_at`, `status`, `note`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, NULL, NULL, NULL, '0000', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:35:14', '2026-06-03 08:35:14'),
	(2, 1, NULL, NULL, NULL, '0011', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:35:14', '2026-06-03 08:35:14'),
	(3, 2, NULL, NULL, NULL, '2222', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:35:53', '2026-06-03 08:35:53'),
	(4, 2, NULL, NULL, NULL, '3333', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:35:53', '2026-06-03 08:35:53'),
	(5, 3, NULL, NULL, NULL, '4444', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:36:41', '2026-06-03 08:36:41'),
	(6, 3, NULL, NULL, NULL, '5555', NULL, NULL, NULL, 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-03 08:36:41', '2026-06-03 08:36:41');

-- Dumping data for table qlbh.repairs: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_images: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_timelines: ~0 rows (approximately)

-- Dumping data for table qlbh.roles: ~2 rows (approximately)
REPLACE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Super Admin', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16'),
	(2, 'admin', 'web', '2026-06-03 08:31:16', '2026-06-03 08:31:16');

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

-- Dumping data for table qlbh.suppliers: ~0 rows (approximately)

-- Dumping data for table qlbh.units: ~0 rows (approximately)

-- Dumping data for table qlbh.users: ~1 rows (approximately)
REPLACE INTO `users` (`id`, `name`, `username`, `phone`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin', '0906064789', 'admin@gmail.com', NULL, '$2y$12$ewmvC.vN2kLZ2FWM3BvIwOSsAl3j1HtVIEnDkf9E46SqyBWsXo24y', NULL, '2026-06-03 08:31:16', '2026-06-03 08:31:16');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
