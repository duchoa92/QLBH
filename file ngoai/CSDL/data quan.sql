-- --------------------------------------------------------
-- Máy chủ:                      127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Phiên bản:           12.8.0.6908
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

-- Dumping data for table qlbh.bank_payment_intents: ~17 rows (approximately)
REPLACE INTO `bank_payment_intents` (`id`, `invoice_code`, `user_id`, `work_shift_id`, `bank_account`, `expected_amount`, `received_amount`, `checkout_payload`, `status`, `sale_id`, `sale_response`, `expires_at`, `created_at`, `updated_at`) VALUES
	(1, 'INV-20261020092018', 1, 1, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCVXUKK27PEX/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCVXUKK27PEX"}', 'cancelled', NULL, NULL, '2026-10-20 02:35:24', '2026-10-20 02:20:24', '2026-10-20 02:23:28'),
	(2, 'INV-20261020092335', 1, 1, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC192S28M9Z5/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC192S28M9Z5"}', 'cancelled', NULL, NULL, '2026-10-20 02:38:59', '2026-10-20 02:23:59', '2026-10-20 02:28:15'),
	(3, 'INV-20261020092817', 1, 1, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCN7WQ3QN3V2/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCN7WQ3QN3V2"}', 'paid', 3, '{"data": {"id": 3, "code": "INV-20261020092817", "paid": 2000, "items": [{"id": 3, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Khách lẻ"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 02:43:45', '2026-10-20 02:28:45', '2026-10-20 02:29:23'),
	(4, 'INV-20261020093008', 1, 1, '8807252653', 7000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": null, "warranty_days": 0, "warranty_unit": null, "discount_value": 0, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCUNK4LN9PVM/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCUNK4LN9PVM"}', 'cancelled', NULL, NULL, '2026-10-20 02:47:14', '2026-10-20 02:32:14', '2026-10-20 02:33:17'),
	(5, 'INV-20261020093906', 1, 1, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false}', 'expired', NULL, NULL, '2026-10-20 02:54:17', '2026-10-20 02:39:17', '2026-10-20 02:54:18'),
	(6, 'INV-20261020100803', 1, 1, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC3A34WRGXXP/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC3A34WRGXXP"}', 'paid', 4, '{"data": {"id": 4, "code": "INV-20261020100803", "paid": 2000, "items": [{"id": 4, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Đức Hòa"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 03:26:31', '2026-10-20 03:11:31', '2026-10-20 03:12:07'),
	(7, 'INV-20261020101408', 1, 1, '8807252653', 7000, 7000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": null, "warranty_days": 0, "warranty_unit": null, "discount_value": 0, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCDVYHZ1R31Y/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCDVYHZ1R31Y"}', 'paid', 5, '{"data": {"id": 5, "code": "INV-20261020101408", "paid": 7000, "items": [{"id": 5, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "7000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Khách lẻ"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 03:29:16', '2026-10-20 03:14:16', '2026-10-20 03:14:35'),
	(8, 'INV-20261020111839', 1, 1, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC4GNUJ9NXL4/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC4GNUJ9NXL4"}', 'paid', 6, '{"data": {"id": 6, "code": "INV-20261020111839", "paid": 2000, "items": [{"id": 6, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Khách lẻ"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 04:33:54', '2026-10-20 04:18:54', '2026-10-20 04:19:23'),
	(9, 'INV-20261020113321', 1, 1, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCUQHMDFUPR8/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCUQHMDFUPR8"}', 'paid', 7, '{"data": {"id": 7, "code": "INV-20261020113321", "paid": 2000, "items": [{"id": 7, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Đức Hòa"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 04:48:27', '2026-10-20 04:33:27', '2026-10-20 04:33:58'),
	(10, 'INV-20261020201453', 1, 1, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCZHFC0FWP96/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCZHFC0FWP96"}', 'paid', 8, '{"data": {"id": 8, "code": "INV-20261020201453", "paid": 2000, "items": [{"id": 8, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Đức Hòa"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-20 13:30:00', '2026-10-20 13:15:00', '2026-10-20 13:15:50'),
	(11, 'INV-20261020201401', 1, 1, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCTG3AU4EE0N/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCTG3AU4EE0N"}', 'waiting', NULL, NULL, '2026-10-20 13:30:56', '2026-10-20 13:15:56', '2026-10-20 13:15:58'),
	(12, 'INV-20261020201650', 1, 1, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": null, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC6X1N88F13V/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC6X1N88F13V"}', 'waiting', NULL, NULL, '2026-10-20 13:31:59', '2026-10-20 13:16:59', '2026-10-20 13:17:01'),
	(13, 'INV-20261021195034', 1, 2, '8807252653', 7000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": null, "warranty_days": 0, "warranty_unit": null, "discount_value": 0, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCDKN86EKGHV/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCDKN86EKGHV"}', 'waiting', NULL, NULL, '2026-10-21 13:05:44', '2026-10-21 12:50:44', '2026-10-21 12:50:52'),
	(14, 'INV-20261021200203', 1, 2, '8807252653', 2000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSCH770YETM9V/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSCH770YETM9V"}', 'expired', NULL, NULL, '2026-10-21 13:17:15', '2026-10-21 13:02:15', '2026-10-21 13:17:16'),
	(15, 'INV-20261021202615', 1, 2, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC0CZDVD1BZN/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC0CZDVD1BZN"}', 'paid', 9, '{"data": {"id": 9, "code": "INV-20261021202615", "paid": 2000, "items": [{"id": 9, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Đức Hòa"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-21 13:41:31', '2026-10-21 13:26:31', '2026-10-21 13:27:08'),
	(16, 'INV-20261021202922', 1, 2, '8807252653', 7000, 0, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": null, "warranty_days": 0, "warranty_unit": null, "discount_value": 0, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC8KH1Y358MK/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC8KH1Y358MK"}', 'waiting', NULL, NULL, '2026-10-21 13:44:27', '2026-10-21 13:29:27', '2026-10-21 13:29:29'),
	(17, 'INV-20261021203051', 1, 2, '8807252653', 2000, 2000, '{"items": [{"id": 9, "note": null, "gifts": [], "price": 7000, "imei_id": null, "unit_id": 2, "quantity": 1, "unit_name": "M", "variant_id": null, "discount_type": "amount", "warranty_days": 0, "warranty_unit": null, "discount_value": 5000, "warranty_value": null, "conversion_factor": 1}], "customer_id": 1, "pay_old_debt": false, "provider_qr_url": "https://app.apipay.vn/v1/payment-requests/APIPAYJSC6324PWXPEQ/qr-image", "provider_payment_url": "https://pay.apipay.vn/APIPAYJSC6324PWXPEQ"}', 'paid', 10, '{"data": {"id": 10, "code": "INV-20261021203051", "paid": 2000, "items": [{"id": 10, "imei": null, "product": {"name": "Dây mạng cường lực ngoài trời"}, "variant": null, "quantity": 1, "subtotal": "2000.00", "unit_name": "M", "unit_price": "7000.00", "warranty_days": 0, "warranty_unit": null, "warranty_value": null}], "change": 0, "customer": {"full_name": "Đức Hòa"}, "subtotal": 7000, "payment_method": "bank"}, "success": true}', '2026-10-21 13:45:57', '2026-10-21 13:30:57', '2026-10-21 13:31:19');

-- Dumping data for table qlbh.bank_payment_transactions: ~0 rows (approximately)
REPLACE INTO `bank_payment_transactions` (`id`, `transaction_id`, `bank_payment_intent_id`, `amount`, `description`, `created_at`, `updated_at`) VALUES
	(1, 'cmvg2ekiw03ch01p43c80fh8g', 3, 2000, 'INV20261020092817 af5a39b7-bdf9-4c19-b31c-fcff5dfb5f61', '2026-10-20 02:29:22', '2026-10-20 02:29:22'),
	(2, 'cmvg3xhlf009o01peb0naonc3', 6, 2000, 'INV20261020100803 6f71e9bf-5925-4876-8141-356060ebf1b6', '2026-10-20 03:12:07', '2026-10-20 03:12:07'),
	(3, 'cmvg40px903ub01p4gino6adh', 7, 7000, 'INV20261020101408 6275d104-f834-41df-bfee-5ed1c08c6617', '2026-10-20 03:14:35', '2026-10-20 03:14:35'),
	(4, 'cmvg6bxxr00j801pevexls0wz', 8, 2000, 'INV20261020111839 c9cfe4e2-0f72-49bb-aee9-454df9aa1ce0', '2026-10-20 04:19:23', '2026-10-20 04:19:23'),
	(5, 'cmvg6ut7k04do01rsq61b1588', 9, 2000, 'INV20261020113321 30628c09-44cb-474d-b8f2-9890b9b0cfa6', '2026-10-20 04:33:58', '2026-10-20 04:33:58'),
	(6, 'cmvgpha5f02b601per74pvmli', 10, 2000, 'INV20261020201453 cae8e48f-be86-496b-a0a4-d6f7ba7e4eaa', '2026-10-20 13:15:50', '2026-10-20 13:15:50'),
	(7, 'cmvi5c7k20ird01rsop5sexlb', 15, 2000, 'INV20261021202615 1c875ac2-c3b0-4288-b3f9-0177df51f3db', '2026-10-21 13:27:07', '2026-10-21 13:27:07'),
	(8, 'cmvi5hmqo0k5901p46zxg5b6f', 17, 2000, 'INV20261021203051 e25cfb4a-0c6b-4225-abed-a3c2f40337a1', '2026-10-21 13:31:19', '2026-10-21 13:31:19');

-- Dumping data for table qlbh.brands: ~8 rows (approximately)
REPLACE INTO `brands` (`id`, `name`, `search_text`, `slug`, `category_id`, `sort_order`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Samsung', 'samsung', 'samsung', 1, 0, 1, NULL, '2026-08-15 16:46:06', '2026-08-15 16:46:06'),
	(2, 'Apple', 'apple', 'apple', 1, 0, 1, NULL, '2026-08-15 16:46:20', '2026-08-15 16:46:20'),
	(3, 'Ezivi', 'ezivi', 'ezivi', 2, 0, 1, NULL, '2026-08-15 16:46:36', '2026-08-17 07:40:17'),
	(4, 'OEM', 'oem', 'oem', 3, 0, 1, NULL, '2026-08-17 07:40:29', '2026-08-17 07:40:29'),
	(5, 'OEM', 'oem', 'oem-4', 4, 0, 1, NULL, '2026-10-14 13:01:22', '2026-10-14 13:01:22'),
	(6, 'Việt Hàn', 'viet han', 'viet-han', 8, 0, 1, NULL, '2026-10-18 11:32:33', '2026-10-18 11:32:33'),
	(7, 'Dell', 'dell', 'dell', 9, 0, 1, NULL, '2026-10-19 11:28:03', '2026-10-19 11:28:03'),
	(8, 'Apple', 'apple', 'apple-4', 4, 0, 1, NULL, '2026-10-19 12:07:21', '2026-10-19 12:07:21');

-- Dumping data for table qlbh.cache: ~31 rows (approximately)
REPLACE INTO `cache` (`key`, `value`, `expiration`) VALUES
	('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab', 'i:1;', 1792327041),
	('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer', 'i:1792327041;', 1792327041),
	('laravel-cache-setting_allow_negative_stock', 's:1:"1";', 2107947120),
	('laravel-cache-setting_app_locale', 's:2:"vi";', 2107947119),
	('laravel-cache-setting_app_timezone', 's:16:"Asia/Ho_Chi_Minh";', 2107947119),
	('laravel-cache-setting_backup_enabled', 's:1:"1";', 2107685378),
	('laravel-cache-setting_backup_frequency', 's:5:"daily";', 2107685378),
	('laravel-cache-setting_backup_last_error', 'N;', 2107949962),
	('laravel-cache-setting_backup_last_run', 's:19:"2026-10-21 20:39:16";', 2107949962),
	('laravel-cache-setting_backup_monthday', 's:1:"1";', 2107685378),
	('laravel-cache-setting_backup_time', 's:5:"16:06";', 2107685378),
	('laravel-cache-setting_backup_weekday', 's:1:"1";', 2107685378),
	('laravel-cache-setting_bank_account', 's:10:"8807252653";', 2107947120),
	('laravel-cache-setting_bank_account_name', 's:10:"Do Duc Hoa";', 2107947120),
	('laravel-cache-setting_bank_accounts', 's:1699:"[{"id":"bank-1792328498457-joj9j","bank_bin":"970418","bank_name":"BIDV","bank_account":"8807252653","bank_account_name":"Do Duc Hoa","transfer_content_mode":"invoice","is_default":true,"payment_provider":"apipay","payment_provider_account_id":"cmvfel4m6007701rsf4v8mhlq","payment_api_key":"","payment_api_access_key":"eyJpdiI6InkrSk85V3RtSGNORE0xZ211a0hoMmc9PSIsInZhbHVlIjoicXZLejFKUWFXc0czcUtvNUtVSnJ2TVJValR6NTFpYjBGaFZoMXJaVlgraW9kbVRhc2UxRDV5N0RYZm9ndUZVMzhlL1B3UHdWMXcvNVY4Nmlsdk9pVWxHMTU2UFc2MExNcW5qTGxEZmNmUm89IiwibWFjIjoiMjk3MjczMDg4MWE4OTZiNGI0NWJjNjYwZDhmZjkyZTA0MWRiNmExMTlmMGRjMmU5N2M0ODNlY2NmNTBjMmQ5YiIsInRhZyI6IiJ9","payment_api_secret_key":"eyJpdiI6IjFtL09WMFhpZzVsb2FGSDEyMXRNS0E9PSIsInZhbHVlIjoiSDQ2U3huMkI4SjUrWnRPQ3V5YWhOd1VqOEl1NVdETHVRd2Z5KzJYMkpwbXBIZC9yQkI2cEZPWDYyOU5oOU0wOUNBd04rSElPMmtvRWtNaUl4T1cvZSsvOWdTeEo1NlNleGpRWWtHdmUydFYxYlpseXBOanF6SU1qQUtPK25USjAiLCJtYWMiOiJkZjRiZGM5NjU2OGRiNjdkZmU3MWMxNDE0NWE0N2RjOWY5OWFlYjdlMGIyYjg2Njg3YTcyZDI4OGMyZGEwZjk0IiwidGFnIjoiIn0=","payment_webhook_secret":"eyJpdiI6Im9OUVdwVHhjQ1p4dEQrSWlTUEp3UFE9PSIsInZhbHVlIjoidGJxOHNIeGlYVHpaeEZsR1BGSzRUMlFHVEJwcUlmaEVKU3ByVTlNMVkwR24xSFArNXpBQTNMNkc2ZnFkcUFPL3A5aURLbG1tYndRZ1R1UEY0SVRpS09WRDg4R3NaRjJadG9KU25ZUnl1VzA9IiwibWFjIjoiMWY4OGY3OWI1OWU0NDIzMGY1MjNlZGMzMjFmMzEzYmI2YTcwMjI2ZjYzMzJhZGFjOTEyNTcwMzBlMjhhYTdkZiIsInRhZyI6IiJ9"},{"id":"bank-1792328586340-gfwb7","bank_bin":"970436","bank_name":"Vietcombank","bank_account":"0211000535558","bank_account_name":"Do Duc Hoa","transfer_content_mode":"default","is_default":false,"payment_provider":"","payment_provider_account_id":"","payment_api_key":"","payment_api_access_key":"","payment_api_secret_key":"","payment_webhook_secret":""}]";', 2107947120),
	('laravel-cache-setting_bank_bin', 's:6:"970418";', 2107947120),
	('laravel-cache-setting_bank_qr_theme', 's:7:"classic";', 2107947120),
	('laravel-cache-setting_bank_transfer_content', 's:19:"Thanh toan don hang";', 2107947120),
	('laravel-cache-setting_currency_format', 's:5:"vi-VN";', 2107947119),
	('laravel-cache-setting_currency_symbol', 's:3:"₫";', 2107947119),
	('laravel-cache-setting_date_format', 's:5:"d/m/Y";', 2107947120),
	('laravel-cache-setting_inventory_alert_enabled', 's:1:"1";', 2107947120),
	('laravel-cache-setting_inventory_alert_threshold', 's:1:"5";', 2107947120),
	('laravel-cache-setting_payment_voice_enabled', 's:1:"1";', 2107947120),
	('laravel-cache-setting_payment_voice_language', 's:5:"vi-VN";', 2107947120),
	('laravel-cache-setting_print_orientation', 's:8:"portrait";', 2107947120),
	('laravel-cache-setting_print_paper_size', 's:2:"a4";', 2107947120),
	('laravel-cache-setting_shop_name', 's:11:"Đức hòa";', 2107947119),
	('laravel-cache-settings.vietqr_banks', 'a:76:{i:0;a:4:{s:3:"bin";s:6:"970415";s:4:"code";s:3:"ICB";s:9:"shortName";s:10:"VietinBank";s:4:"name";s:42:"Ngân hàng TMCP Công thương Việt Nam";}i:1;a:4:{s:3:"bin";s:6:"970436";s:4:"code";s:3:"VCB";s:9:"shortName";s:11:"Vietcombank";s:4:"name";s:46:"Ngân hàng TMCP Ngoại Thương Việt Nam";}i:2;a:4:{s:3:"bin";s:6:"970418";s:4:"code";s:4:"BIDV";s:9:"shortName";s:4:"BIDV";s:4:"name";s:56:"Ngân hàng TMCP Đầu tư và Phát triển Việt Nam";}i:3;a:4:{s:3:"bin";s:6:"970405";s:4:"code";s:3:"VBA";s:9:"shortName";s:8:"Agribank";s:4:"name";s:67:"Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam";}i:4;a:4:{s:3:"bin";s:6:"970448";s:4:"code";s:3:"OCB";s:9:"shortName";s:3:"OCB";s:4:"name";s:32:"Ngân hàng TMCP Phương Đông";}i:5;a:4:{s:3:"bin";s:6:"970422";s:4:"code";s:2:"MB";s:9:"shortName";s:6:"MBBank";s:4:"name";s:29:"Ngân hàng TMCP Quân đội";}i:6;a:4:{s:3:"bin";s:6:"970407";s:4:"code";s:3:"TCB";s:9:"shortName";s:11:"Techcombank";s:4:"name";s:41:"Ngân hàng TMCP Kỹ thương Việt Nam";}i:7;a:4:{s:3:"bin";s:6:"970416";s:4:"code";s:3:"ACB";s:9:"shortName";s:3:"ACB";s:4:"name";s:25:"Ngân hàng TMCP Á Châu";}i:8;a:4:{s:3:"bin";s:6:"970432";s:4:"code";s:3:"VPB";s:9:"shortName";s:6:"VPBank";s:4:"name";s:44:"Ngân hàng TMCP Việt Nam Thịnh Vượng";}i:9;a:4:{s:3:"bin";s:6:"970423";s:4:"code";s:3:"TPB";s:9:"shortName";s:6:"TPBank";s:4:"name";s:28:"Ngân hàng TMCP Tiên Phong";}i:10;a:4:{s:3:"bin";s:6:"970403";s:4:"code";s:3:"STB";s:9:"shortName";s:9:"Sacombank";s:4:"name";s:37:"Ngân hàng TMCP Sài Gòn Tài Lộc";}i:11;a:4:{s:3:"bin";s:6:"970437";s:4:"code";s:3:"HDB";s:9:"shortName";s:6:"HDBank";s:4:"name";s:58:"Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh";}i:12;a:4:{s:3:"bin";s:6:"970454";s:4:"code";s:4:"VCCB";s:9:"shortName";s:15:"VietCapitalBank";s:4:"name";s:31:"Ngân hàng TMCP Bản Việt";}i:13;a:4:{s:3:"bin";s:6:"970429";s:4:"code";s:3:"SCB";s:9:"shortName";s:3:"SCB";s:4:"name";s:26:"Ngân hàng TMCP Sài Gòn";}i:14;a:4:{s:3:"bin";s:6:"970441";s:4:"code";s:3:"VIB";s:9:"shortName";s:3:"VIB";s:4:"name";s:40:"Ngân hàng TMCP Quốc tế Việt Nam";}i:15;a:4:{s:3:"bin";s:6:"970443";s:4:"code";s:3:"SHB";s:9:"shortName";s:3:"SHB";s:4:"name";s:38:"Ngân hàng TMCP Sài Gòn - Hà Nội";}i:16;a:4:{s:3:"bin";s:6:"970431";s:4:"code";s:3:"EIB";s:9:"shortName";s:8:"Eximbank";s:4:"name";s:48:"Ngân hàng TMCP Xuất Nhập khẩu Việt Nam";}i:17;a:4:{s:3:"bin";s:6:"970426";s:4:"code";s:3:"MSB";s:9:"shortName";s:3:"MSB";s:4:"name";s:28:"Ngân hàng TMCP Hàng Hải";}i:18;a:4:{s:3:"bin";s:6:"546034";s:4:"code";s:4:"CAKE";s:9:"shortName";s:4:"CAKE";s:4:"name";s:66:"TMCP Việt Nam Thịnh Vượng - Ngân hàng số CAKE by VPBank";}i:19;a:4:{s:3:"bin";s:6:"546035";s:4:"code";s:5:"Ubank";s:9:"shortName";s:5:"Ubank";s:4:"name";s:67:"TMCP Việt Nam Thịnh Vượng - Ngân hàng số Ubank by VPBank";}i:20;a:4:{s:3:"bin";s:6:"963388";s:4:"code";s:4:"TIMO";s:9:"shortName";s:4:"Timo";s:4:"name";s:62:"Ngân hàng số Timo by Ban Viet Bank (Timo by Ban Viet Bank)";}i:21;a:4:{s:3:"bin";s:6:"971005";s:4:"code";s:8:"VTLMONEY";s:9:"shortName";s:12:"ViettelMoney";s:4:"name";s:108:"Tổng Công ty Dịch vụ số Viettel - Chi nhánh tập đoàn công nghiệp viễn thông Quân Đội";}i:22;a:4:{s:3:"bin";s:6:"971011";s:4:"code";s:9:"VNPTMONEY";s:9:"shortName";s:9:"VNPTMoney";s:4:"name";s:103:"Trung tâm dịch vụ tài chính số VNPT- Chi nhánh Tổng công ty truyền thông (VNPT Fintech)";}i:23;a:4:{s:3:"bin";s:6:"970400";s:4:"code";s:5:"SGICB";s:9:"shortName";s:10:"SaigonBank";s:4:"name";s:41:"Ngân hàng TMCP Sài Gòn Công Thương";}i:24;a:4:{s:3:"bin";s:6:"970409";s:4:"code";s:3:"BAB";s:9:"shortName";s:8:"BacABank";s:4:"name";s:25:"Ngân hàng TMCP Bắc Á";}i:25;a:4:{s:3:"bin";s:6:"971133";s:4:"code";s:4:"PVDB";s:9:"shortName";s:13:"PVcomBank Pay";s:4:"name";s:26:"Ngân hàng số PVcomBank";}i:26;a:4:{s:3:"bin";s:6:"971025";s:4:"code";s:4:"momo";s:9:"shortName";s:4:"MoMo";s:4:"name";s:42:"CTCP Dịch Vụ Di Động Trực Tuyến";}i:27;a:4:{s:3:"bin";s:6:"970412";s:4:"code";s:4:"PVCB";s:9:"shortName";s:9:"PVcomBank";s:4:"name";s:44:"Ngân hàng TMCP Đại Chúng Việt Nam";}i:28;a:4:{s:3:"bin";s:6:"970414";s:4:"code";s:3:"MBV";s:9:"shortName";s:3:"MBV";s:4:"name";s:45:"Ngân hàng TNHH MTV Việt Nam Hiện Đại";}i:29;a:4:{s:3:"bin";s:6:"970419";s:4:"code";s:3:"NCB";s:9:"shortName";s:3:"NCB";s:4:"name";s:28:"Ngân hàng TMCP Quốc Dân";}i:30;a:4:{s:3:"bin";s:6:"970424";s:4:"code";s:5:"SHBVN";s:9:"shortName";s:11:"ShinhanBank";s:4:"name";s:39:"Ngân hàng TNHH MTV Shinhan Việt Nam";}i:31;a:4:{s:3:"bin";s:6:"970425";s:4:"code";s:3:"ABB";s:9:"shortName";s:6:"ABBANK";s:4:"name";s:25:"Ngân hàng TMCP An Bình";}i:32;a:4:{s:3:"bin";s:6:"970427";s:4:"code";s:3:"VAB";s:9:"shortName";s:9:"VietABank";s:4:"name";s:26:"Ngân hàng TMCP Việt Á";}i:33;a:4:{s:3:"bin";s:6:"970428";s:4:"code";s:3:"NAB";s:9:"shortName";s:8:"NamABank";s:4:"name";s:23:"Ngân hàng TMCP Nam Á";}i:34;a:4:{s:3:"bin";s:6:"970430";s:4:"code";s:3:"PGB";s:9:"shortName";s:6:"PGBank";s:4:"name";s:51:"Ngân hàng TMCP Thịnh vượng và Phát triển";}i:35;a:4:{s:3:"bin";s:6:"970433";s:4:"code";s:8:"VIETBANK";s:9:"shortName";s:8:"VietBank";s:4:"name";s:41:"Ngân hàng TMCP Việt Nam Thương Tín";}i:36;a:4:{s:3:"bin";s:6:"970438";s:4:"code";s:3:"BVB";s:9:"shortName";s:11:"BaoVietBank";s:4:"name";s:29:"Ngân hàng TMCP Bảo Việt";}i:37;a:4:{s:3:"bin";s:6:"970440";s:4:"code";s:4:"SEAB";s:9:"shortName";s:7:"SeABank";s:4:"name";s:32:"Ngân hàng TMCP Đông Nam Á";}i:38;a:4:{s:3:"bin";s:6:"970446";s:4:"code";s:8:"COOPBANK";s:9:"shortName";s:8:"COOPBANK";s:4:"name";s:37:"Ngân hàng Hợp tác xã Việt Nam";}i:39;a:4:{s:3:"bin";s:6:"970449";s:4:"code";s:3:"LPB";s:9:"shortName";s:6:"LPBank";s:4:"name";s:39:"Ngân hàng TMCP Lộc Phát Việt Nam";}i:40;a:4:{s:3:"bin";s:6:"970452";s:4:"code";s:3:"KLB";s:9:"shortName";s:12:"KienLongBank";s:4:"name";s:27:"Ngân hàng TMCP Kiên Long";}i:41;a:4:{s:3:"bin";s:6:"668888";s:4:"code";s:5:"KBank";s:9:"shortName";s:5:"KBank";s:4:"name";s:43:"Ngân hàng Đại chúng TNHH Kasikornbank";}i:42;a:4:{s:3:"bin";s:6:"970466";s:4:"code";s:10:"KEBHANAHCM";s:9:"shortName";s:10:"KEBHanaHCM";s:4:"name";s:63:"Ngân hàng KEB Hana – Chi nhánh Thành phố Hồ Chí Minh";}i:43;a:4:{s:3:"bin";s:6:"970467";s:4:"code";s:9:"KEBHANAHN";s:9:"shortName";s:9:"KEBHANAHN";s:4:"name";s:45:"Ngân hàng KEB Hana – Chi nhánh Hà Nội";}i:44;a:4:{s:3:"bin";s:6:"977777";s:4:"code";s:4:"MAFC";s:9:"shortName";s:4:"MAFC";s:4:"name";s:55:"Công ty Tài chính TNHH MTV Mirae Asset (Việt Nam) ";}i:45;a:4:{s:3:"bin";s:6:"999888";s:4:"code";s:4:"VBSP";s:9:"shortName";s:4:"VBSP";s:4:"name";s:34:"Ngân hàng Chính sách Xã hội";}i:46;a:4:{s:3:"bin";s:6:"963668";s:4:"code";s:6:"BNP HN";s:9:"shortName";s:6:"BNP HN";s:4:"name";s:48:"Ngân hàng BNP Paribas – Chi nhánh Hà Nội";}i:47;a:4:{s:3:"bin";s:6:"555666";s:4:"code";s:4:"BIDC";s:9:"shortName";s:4:"BIDC";s:4:"name";s:73:"Ngân hàng Đầu Tư Và Phát Triển Campuchia - Chi nhánh Hà Nội";}i:48;a:4:{s:3:"bin";s:6:"963666";s:4:"code";s:7:"BNP HCM";s:9:"shortName";s:7:"BNP HCM";s:4:"name";s:66:"Ngân hàng BNP Paribas – Chi nhánh Thành phố Hồ Chí Minh";}i:49;a:4:{s:3:"bin";s:6:"971101";s:4:"code";s:7:"ZALOPAY";s:9:"shortName";s:7:"ZaloPay";s:4:"name";s:24:"Ví điện tử ZaloPay";}i:50;a:4:{s:3:"bin";s:6:"963688";s:4:"code";s:5:"BOCHK";s:9:"shortName";s:5:"BOCHK";s:4:"name";s:75:"Ngân hàng Bank of China (Hong Kong) Limited – Chi nhánh Hồ Chí Minh";}i:51;a:4:{s:3:"bin";s:6:"970465";s:4:"code";s:3:"BSP";s:9:"shortName";s:3:"BSP";s:4:"name";s:60:"Ngân hàng SinoPac - Chi nhánh Thành phố Hồ Chí Minh";}i:52;a:4:{s:3:"bin";s:6:"168999";s:4:"code";s:6:"CUBHCM";s:9:"shortName";s:6:"CUBHCM";s:4:"name";s:60:"Ngân hàng Cathay United Bank – Chi nhánh Hồ Chí Minh";}i:53;a:4:{s:3:"bin";s:6:"963686";s:4:"code";s:4:"ESUN";s:9:"shortName";s:4:"ESUN";s:4:"name";s:75:"Ngân hàng Thương mại TNHH E.SUN – Chi nhánh Đồng Nai (ESUNBANK)";}i:54;a:4:{s:3:"bin";s:6:"963669";s:4:"code";s:4:"ICBC";s:9:"shortName";s:4:"ICBC";s:4:"name";s:77:"Ngân hàng Công thương Trung Quốc – Chi nhánh thành phố Hà Nội";}i:55;a:4:{s:3:"bin";s:6:"971032";s:4:"code";s:4:"MVAS";s:9:"shortName";s:4:"MVAS";s:4:"name";s:90:"Trung tâm Dịch vụ số Mobifone – Chi nhánh Tổng Công ty viễn thông Mobifone";}i:56;a:4:{s:3:"bin";s:6:"963326";s:4:"code";s:4:"TNEX";s:9:"shortName";s:4:"TNEX";s:4:"name";s:11:"TNEX by MSB";}i:57;a:4:{s:3:"bin";s:6:"458761";s:4:"code";s:4:"HSBC";s:9:"shortName";s:4:"HSBC";s:4:"name";s:38:"Ngân hàng TNHH MTV HSBC (Việt Nam)";}i:58;a:4:{s:3:"bin";s:6:"970442";s:4:"code";s:5:"HLBVN";s:9:"shortName";s:9:"HongLeong";s:4:"name";s:42:"Ngân hàng TNHH MTV Hong Leong Việt Nam";}i:59;a:4:{s:3:"bin";s:6:"970455";s:4:"code";s:8:"IBK - HN";s:9:"shortName";s:5:"IBKHN";s:4:"name";s:68:"Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh Hà Nội";}i:60;a:4:{s:3:"bin";s:6:"970456";s:4:"code";s:9:"IBK - HCM";s:9:"shortName";s:6:"IBKHCM";s:4:"name";s:77:"Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh TP. Hồ Chí Minh";}i:61;a:4:{s:3:"bin";s:6:"970434";s:4:"code";s:3:"IVB";s:9:"shortName";s:12:"IndovinaBank";s:4:"name";s:25:"Ngân hàng TNHH Indovina";}i:62;a:4:{s:3:"bin";s:6:"970406";s:4:"code";s:5:"Vikki";s:9:"shortName";s:5:"Vikki";s:4:"name";s:46:"Ngân hàng TNHH Một thành viên số Vikki";}i:63;a:4:{s:3:"bin";s:6:"801011";s:4:"code";s:6:"NHB HN";s:9:"shortName";s:8:"Nonghyup";s:4:"name";s:43:"Ngân hàng Nonghyup - Chi nhánh Hà Nội";}i:64;a:4:{s:3:"bin";s:6:"796500";s:4:"code";s:3:"DBS";s:9:"shortName";s:7:"DBSBank";s:4:"name";s:53:"DBS Bank Ltd - Chi nhánh Thành phố Hồ Chí Minh";}i:65;a:4:{s:3:"bin";s:6:"970439";s:4:"code";s:4:"PBVN";s:9:"shortName";s:10:"PublicBank";s:4:"name";s:38:"Ngân hàng TNHH MTV Public Việt Nam";}i:66;a:4:{s:3:"bin";s:6:"970410";s:4:"code";s:4:"SCVN";s:9:"shortName";s:17:"StandardChartered";s:4:"name";s:55:"Ngân hàng TNHH MTV Standard Chartered Bank Việt Nam";}i:67;a:4:{s:3:"bin";s:6:"533948";s:4:"code";s:8:"CITIBANK";s:9:"shortName";s:8:"Citibank";s:4:"name";s:49:"Ngân hàng Citibank, N.A. - Chi nhánh Hà Nội";}i:68;a:4:{s:3:"bin";s:6:"422589";s:4:"code";s:4:"CIMB";s:9:"shortName";s:4:"CIMB";s:4:"name";s:36:"Ngân hàng TNHH MTV CIMB Việt Nam";}i:69;a:4:{s:3:"bin";s:6:"970458";s:4:"code";s:3:"UOB";s:9:"shortName";s:14:"UnitedOverseas";s:4:"name";s:59:"Ngân hàng United Overseas - Chi nhánh TP. Hồ Chí Minh";}i:70;a:4:{s:3:"bin";s:6:"970444";s:4:"code";s:3:"CBB";s:9:"shortName";s:6:"CBBank";s:4:"name";s:133:"Ngân hàng Thương mại trách nhiệm hữu hạn một thành viên Ngoại thương Công nghệ số (Vietcombank Neo Limited)";}i:71;a:4:{s:3:"bin";s:6:"970408";s:4:"code";s:3:"GPB";s:9:"shortName";s:6:"GPBank";s:4:"name";s:58:"Ngân hàng Thương mại TNHH MTV Dầu Khí Toàn Cầu";}i:72;a:4:{s:3:"bin";s:6:"970421";s:4:"code";s:3:"VRB";s:9:"shortName";s:3:"VRB";s:4:"name";s:36:"Ngân hàng Liên doanh Việt - Nga";}i:73;a:4:{s:3:"bin";s:6:"970457";s:4:"code";s:3:"WVN";s:9:"shortName";s:5:"Woori";s:4:"name";s:37:"Ngân hàng TNHH MTV Woori Việt Nam";}i:74;a:4:{s:3:"bin";s:6:"970462";s:4:"code";s:4:"KBHN";s:9:"shortName";s:9:"KookminHN";s:4:"name";s:42:"Ngân hàng Kookmin - Chi nhánh Hà Nội";}i:75;a:4:{s:3:"bin";s:6:"970463";s:4:"code";s:5:"KBHCM";s:9:"shortName";s:10:"KookminHCM";s:4:"name";s:60:"Ngân hàng Kookmin - Chi nhánh Thành phố Hồ Chí Minh";}}', 1792673463),
	('laravel-cache-settings.vietqr_banks.stale', 'a:76:{i:0;a:4:{s:3:"bin";s:6:"970415";s:4:"code";s:3:"ICB";s:9:"shortName";s:10:"VietinBank";s:4:"name";s:42:"Ngân hàng TMCP Công thương Việt Nam";}i:1;a:4:{s:3:"bin";s:6:"970436";s:4:"code";s:3:"VCB";s:9:"shortName";s:11:"Vietcombank";s:4:"name";s:46:"Ngân hàng TMCP Ngoại Thương Việt Nam";}i:2;a:4:{s:3:"bin";s:6:"970418";s:4:"code";s:4:"BIDV";s:9:"shortName";s:4:"BIDV";s:4:"name";s:56:"Ngân hàng TMCP Đầu tư và Phát triển Việt Nam";}i:3;a:4:{s:3:"bin";s:6:"970405";s:4:"code";s:3:"VBA";s:9:"shortName";s:8:"Agribank";s:4:"name";s:67:"Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam";}i:4;a:4:{s:3:"bin";s:6:"970448";s:4:"code";s:3:"OCB";s:9:"shortName";s:3:"OCB";s:4:"name";s:32:"Ngân hàng TMCP Phương Đông";}i:5;a:4:{s:3:"bin";s:6:"970422";s:4:"code";s:2:"MB";s:9:"shortName";s:6:"MBBank";s:4:"name";s:29:"Ngân hàng TMCP Quân đội";}i:6;a:4:{s:3:"bin";s:6:"970407";s:4:"code";s:3:"TCB";s:9:"shortName";s:11:"Techcombank";s:4:"name";s:41:"Ngân hàng TMCP Kỹ thương Việt Nam";}i:7;a:4:{s:3:"bin";s:6:"970416";s:4:"code";s:3:"ACB";s:9:"shortName";s:3:"ACB";s:4:"name";s:25:"Ngân hàng TMCP Á Châu";}i:8;a:4:{s:3:"bin";s:6:"970432";s:4:"code";s:3:"VPB";s:9:"shortName";s:6:"VPBank";s:4:"name";s:44:"Ngân hàng TMCP Việt Nam Thịnh Vượng";}i:9;a:4:{s:3:"bin";s:6:"970423";s:4:"code";s:3:"TPB";s:9:"shortName";s:6:"TPBank";s:4:"name";s:28:"Ngân hàng TMCP Tiên Phong";}i:10;a:4:{s:3:"bin";s:6:"970403";s:4:"code";s:3:"STB";s:9:"shortName";s:9:"Sacombank";s:4:"name";s:37:"Ngân hàng TMCP Sài Gòn Tài Lộc";}i:11;a:4:{s:3:"bin";s:6:"970437";s:4:"code";s:3:"HDB";s:9:"shortName";s:6:"HDBank";s:4:"name";s:58:"Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh";}i:12;a:4:{s:3:"bin";s:6:"970454";s:4:"code";s:4:"VCCB";s:9:"shortName";s:15:"VietCapitalBank";s:4:"name";s:31:"Ngân hàng TMCP Bản Việt";}i:13;a:4:{s:3:"bin";s:6:"970429";s:4:"code";s:3:"SCB";s:9:"shortName";s:3:"SCB";s:4:"name";s:26:"Ngân hàng TMCP Sài Gòn";}i:14;a:4:{s:3:"bin";s:6:"970441";s:4:"code";s:3:"VIB";s:9:"shortName";s:3:"VIB";s:4:"name";s:40:"Ngân hàng TMCP Quốc tế Việt Nam";}i:15;a:4:{s:3:"bin";s:6:"970443";s:4:"code";s:3:"SHB";s:9:"shortName";s:3:"SHB";s:4:"name";s:38:"Ngân hàng TMCP Sài Gòn - Hà Nội";}i:16;a:4:{s:3:"bin";s:6:"970431";s:4:"code";s:3:"EIB";s:9:"shortName";s:8:"Eximbank";s:4:"name";s:48:"Ngân hàng TMCP Xuất Nhập khẩu Việt Nam";}i:17;a:4:{s:3:"bin";s:6:"970426";s:4:"code";s:3:"MSB";s:9:"shortName";s:3:"MSB";s:4:"name";s:28:"Ngân hàng TMCP Hàng Hải";}i:18;a:4:{s:3:"bin";s:6:"546034";s:4:"code";s:4:"CAKE";s:9:"shortName";s:4:"CAKE";s:4:"name";s:66:"TMCP Việt Nam Thịnh Vượng - Ngân hàng số CAKE by VPBank";}i:19;a:4:{s:3:"bin";s:6:"546035";s:4:"code";s:5:"Ubank";s:9:"shortName";s:5:"Ubank";s:4:"name";s:67:"TMCP Việt Nam Thịnh Vượng - Ngân hàng số Ubank by VPBank";}i:20;a:4:{s:3:"bin";s:6:"963388";s:4:"code";s:4:"TIMO";s:9:"shortName";s:4:"Timo";s:4:"name";s:62:"Ngân hàng số Timo by Ban Viet Bank (Timo by Ban Viet Bank)";}i:21;a:4:{s:3:"bin";s:6:"971005";s:4:"code";s:8:"VTLMONEY";s:9:"shortName";s:12:"ViettelMoney";s:4:"name";s:108:"Tổng Công ty Dịch vụ số Viettel - Chi nhánh tập đoàn công nghiệp viễn thông Quân Đội";}i:22;a:4:{s:3:"bin";s:6:"971011";s:4:"code";s:9:"VNPTMONEY";s:9:"shortName";s:9:"VNPTMoney";s:4:"name";s:103:"Trung tâm dịch vụ tài chính số VNPT- Chi nhánh Tổng công ty truyền thông (VNPT Fintech)";}i:23;a:4:{s:3:"bin";s:6:"970400";s:4:"code";s:5:"SGICB";s:9:"shortName";s:10:"SaigonBank";s:4:"name";s:41:"Ngân hàng TMCP Sài Gòn Công Thương";}i:24;a:4:{s:3:"bin";s:6:"970409";s:4:"code";s:3:"BAB";s:9:"shortName";s:8:"BacABank";s:4:"name";s:25:"Ngân hàng TMCP Bắc Á";}i:25;a:4:{s:3:"bin";s:6:"971133";s:4:"code";s:4:"PVDB";s:9:"shortName";s:13:"PVcomBank Pay";s:4:"name";s:26:"Ngân hàng số PVcomBank";}i:26;a:4:{s:3:"bin";s:6:"971025";s:4:"code";s:4:"momo";s:9:"shortName";s:4:"MoMo";s:4:"name";s:42:"CTCP Dịch Vụ Di Động Trực Tuyến";}i:27;a:4:{s:3:"bin";s:6:"970412";s:4:"code";s:4:"PVCB";s:9:"shortName";s:9:"PVcomBank";s:4:"name";s:44:"Ngân hàng TMCP Đại Chúng Việt Nam";}i:28;a:4:{s:3:"bin";s:6:"970414";s:4:"code";s:3:"MBV";s:9:"shortName";s:3:"MBV";s:4:"name";s:45:"Ngân hàng TNHH MTV Việt Nam Hiện Đại";}i:29;a:4:{s:3:"bin";s:6:"970419";s:4:"code";s:3:"NCB";s:9:"shortName";s:3:"NCB";s:4:"name";s:28:"Ngân hàng TMCP Quốc Dân";}i:30;a:4:{s:3:"bin";s:6:"970424";s:4:"code";s:5:"SHBVN";s:9:"shortName";s:11:"ShinhanBank";s:4:"name";s:39:"Ngân hàng TNHH MTV Shinhan Việt Nam";}i:31;a:4:{s:3:"bin";s:6:"970425";s:4:"code";s:3:"ABB";s:9:"shortName";s:6:"ABBANK";s:4:"name";s:25:"Ngân hàng TMCP An Bình";}i:32;a:4:{s:3:"bin";s:6:"970427";s:4:"code";s:3:"VAB";s:9:"shortName";s:9:"VietABank";s:4:"name";s:26:"Ngân hàng TMCP Việt Á";}i:33;a:4:{s:3:"bin";s:6:"970428";s:4:"code";s:3:"NAB";s:9:"shortName";s:8:"NamABank";s:4:"name";s:23:"Ngân hàng TMCP Nam Á";}i:34;a:4:{s:3:"bin";s:6:"970430";s:4:"code";s:3:"PGB";s:9:"shortName";s:6:"PGBank";s:4:"name";s:51:"Ngân hàng TMCP Thịnh vượng và Phát triển";}i:35;a:4:{s:3:"bin";s:6:"970433";s:4:"code";s:8:"VIETBANK";s:9:"shortName";s:8:"VietBank";s:4:"name";s:41:"Ngân hàng TMCP Việt Nam Thương Tín";}i:36;a:4:{s:3:"bin";s:6:"970438";s:4:"code";s:3:"BVB";s:9:"shortName";s:11:"BaoVietBank";s:4:"name";s:29:"Ngân hàng TMCP Bảo Việt";}i:37;a:4:{s:3:"bin";s:6:"970440";s:4:"code";s:4:"SEAB";s:9:"shortName";s:7:"SeABank";s:4:"name";s:32:"Ngân hàng TMCP Đông Nam Á";}i:38;a:4:{s:3:"bin";s:6:"970446";s:4:"code";s:8:"COOPBANK";s:9:"shortName";s:8:"COOPBANK";s:4:"name";s:37:"Ngân hàng Hợp tác xã Việt Nam";}i:39;a:4:{s:3:"bin";s:6:"970449";s:4:"code";s:3:"LPB";s:9:"shortName";s:6:"LPBank";s:4:"name";s:39:"Ngân hàng TMCP Lộc Phát Việt Nam";}i:40;a:4:{s:3:"bin";s:6:"970452";s:4:"code";s:3:"KLB";s:9:"shortName";s:12:"KienLongBank";s:4:"name";s:27:"Ngân hàng TMCP Kiên Long";}i:41;a:4:{s:3:"bin";s:6:"668888";s:4:"code";s:5:"KBank";s:9:"shortName";s:5:"KBank";s:4:"name";s:43:"Ngân hàng Đại chúng TNHH Kasikornbank";}i:42;a:4:{s:3:"bin";s:6:"970466";s:4:"code";s:10:"KEBHANAHCM";s:9:"shortName";s:10:"KEBHanaHCM";s:4:"name";s:63:"Ngân hàng KEB Hana – Chi nhánh Thành phố Hồ Chí Minh";}i:43;a:4:{s:3:"bin";s:6:"970467";s:4:"code";s:9:"KEBHANAHN";s:9:"shortName";s:9:"KEBHANAHN";s:4:"name";s:45:"Ngân hàng KEB Hana – Chi nhánh Hà Nội";}i:44;a:4:{s:3:"bin";s:6:"977777";s:4:"code";s:4:"MAFC";s:9:"shortName";s:4:"MAFC";s:4:"name";s:55:"Công ty Tài chính TNHH MTV Mirae Asset (Việt Nam) ";}i:45;a:4:{s:3:"bin";s:6:"999888";s:4:"code";s:4:"VBSP";s:9:"shortName";s:4:"VBSP";s:4:"name";s:34:"Ngân hàng Chính sách Xã hội";}i:46;a:4:{s:3:"bin";s:6:"963668";s:4:"code";s:6:"BNP HN";s:9:"shortName";s:6:"BNP HN";s:4:"name";s:48:"Ngân hàng BNP Paribas – Chi nhánh Hà Nội";}i:47;a:4:{s:3:"bin";s:6:"555666";s:4:"code";s:4:"BIDC";s:9:"shortName";s:4:"BIDC";s:4:"name";s:73:"Ngân hàng Đầu Tư Và Phát Triển Campuchia - Chi nhánh Hà Nội";}i:48;a:4:{s:3:"bin";s:6:"963666";s:4:"code";s:7:"BNP HCM";s:9:"shortName";s:7:"BNP HCM";s:4:"name";s:66:"Ngân hàng BNP Paribas – Chi nhánh Thành phố Hồ Chí Minh";}i:49;a:4:{s:3:"bin";s:6:"971101";s:4:"code";s:7:"ZALOPAY";s:9:"shortName";s:7:"ZaloPay";s:4:"name";s:24:"Ví điện tử ZaloPay";}i:50;a:4:{s:3:"bin";s:6:"963688";s:4:"code";s:5:"BOCHK";s:9:"shortName";s:5:"BOCHK";s:4:"name";s:75:"Ngân hàng Bank of China (Hong Kong) Limited – Chi nhánh Hồ Chí Minh";}i:51;a:4:{s:3:"bin";s:6:"970465";s:4:"code";s:3:"BSP";s:9:"shortName";s:3:"BSP";s:4:"name";s:60:"Ngân hàng SinoPac - Chi nhánh Thành phố Hồ Chí Minh";}i:52;a:4:{s:3:"bin";s:6:"168999";s:4:"code";s:6:"CUBHCM";s:9:"shortName";s:6:"CUBHCM";s:4:"name";s:60:"Ngân hàng Cathay United Bank – Chi nhánh Hồ Chí Minh";}i:53;a:4:{s:3:"bin";s:6:"963686";s:4:"code";s:4:"ESUN";s:9:"shortName";s:4:"ESUN";s:4:"name";s:75:"Ngân hàng Thương mại TNHH E.SUN – Chi nhánh Đồng Nai (ESUNBANK)";}i:54;a:4:{s:3:"bin";s:6:"963669";s:4:"code";s:4:"ICBC";s:9:"shortName";s:4:"ICBC";s:4:"name";s:77:"Ngân hàng Công thương Trung Quốc – Chi nhánh thành phố Hà Nội";}i:55;a:4:{s:3:"bin";s:6:"971032";s:4:"code";s:4:"MVAS";s:9:"shortName";s:4:"MVAS";s:4:"name";s:90:"Trung tâm Dịch vụ số Mobifone – Chi nhánh Tổng Công ty viễn thông Mobifone";}i:56;a:4:{s:3:"bin";s:6:"963326";s:4:"code";s:4:"TNEX";s:9:"shortName";s:4:"TNEX";s:4:"name";s:11:"TNEX by MSB";}i:57;a:4:{s:3:"bin";s:6:"458761";s:4:"code";s:4:"HSBC";s:9:"shortName";s:4:"HSBC";s:4:"name";s:38:"Ngân hàng TNHH MTV HSBC (Việt Nam)";}i:58;a:4:{s:3:"bin";s:6:"970442";s:4:"code";s:5:"HLBVN";s:9:"shortName";s:9:"HongLeong";s:4:"name";s:42:"Ngân hàng TNHH MTV Hong Leong Việt Nam";}i:59;a:4:{s:3:"bin";s:6:"970455";s:4:"code";s:8:"IBK - HN";s:9:"shortName";s:5:"IBKHN";s:4:"name";s:68:"Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh Hà Nội";}i:60;a:4:{s:3:"bin";s:6:"970456";s:4:"code";s:9:"IBK - HCM";s:9:"shortName";s:6:"IBKHCM";s:4:"name";s:77:"Ngân hàng Công nghiệp Hàn Quốc - Chi nhánh TP. Hồ Chí Minh";}i:61;a:4:{s:3:"bin";s:6:"970434";s:4:"code";s:3:"IVB";s:9:"shortName";s:12:"IndovinaBank";s:4:"name";s:25:"Ngân hàng TNHH Indovina";}i:62;a:4:{s:3:"bin";s:6:"970406";s:4:"code";s:5:"Vikki";s:9:"shortName";s:5:"Vikki";s:4:"name";s:46:"Ngân hàng TNHH Một thành viên số Vikki";}i:63;a:4:{s:3:"bin";s:6:"801011";s:4:"code";s:6:"NHB HN";s:9:"shortName";s:8:"Nonghyup";s:4:"name";s:43:"Ngân hàng Nonghyup - Chi nhánh Hà Nội";}i:64;a:4:{s:3:"bin";s:6:"796500";s:4:"code";s:3:"DBS";s:9:"shortName";s:7:"DBSBank";s:4:"name";s:53:"DBS Bank Ltd - Chi nhánh Thành phố Hồ Chí Minh";}i:65;a:4:{s:3:"bin";s:6:"970439";s:4:"code";s:4:"PBVN";s:9:"shortName";s:10:"PublicBank";s:4:"name";s:38:"Ngân hàng TNHH MTV Public Việt Nam";}i:66;a:4:{s:3:"bin";s:6:"970410";s:4:"code";s:4:"SCVN";s:9:"shortName";s:17:"StandardChartered";s:4:"name";s:55:"Ngân hàng TNHH MTV Standard Chartered Bank Việt Nam";}i:67;a:4:{s:3:"bin";s:6:"533948";s:4:"code";s:8:"CITIBANK";s:9:"shortName";s:8:"Citibank";s:4:"name";s:49:"Ngân hàng Citibank, N.A. - Chi nhánh Hà Nội";}i:68;a:4:{s:3:"bin";s:6:"422589";s:4:"code";s:4:"CIMB";s:9:"shortName";s:4:"CIMB";s:4:"name";s:36:"Ngân hàng TNHH MTV CIMB Việt Nam";}i:69;a:4:{s:3:"bin";s:6:"970458";s:4:"code";s:3:"UOB";s:9:"shortName";s:14:"UnitedOverseas";s:4:"name";s:59:"Ngân hàng United Overseas - Chi nhánh TP. Hồ Chí Minh";}i:70;a:4:{s:3:"bin";s:6:"970444";s:4:"code";s:3:"CBB";s:9:"shortName";s:6:"CBBank";s:4:"name";s:133:"Ngân hàng Thương mại trách nhiệm hữu hạn một thành viên Ngoại thương Công nghệ số (Vietcombank Neo Limited)";}i:71;a:4:{s:3:"bin";s:6:"970408";s:4:"code";s:3:"GPB";s:9:"shortName";s:6:"GPBank";s:4:"name";s:58:"Ngân hàng Thương mại TNHH MTV Dầu Khí Toàn Cầu";}i:72;a:4:{s:3:"bin";s:6:"970421";s:4:"code";s:3:"VRB";s:9:"shortName";s:3:"VRB";s:4:"name";s:36:"Ngân hàng Liên doanh Việt - Nga";}i:73;a:4:{s:3:"bin";s:6:"970457";s:4:"code";s:3:"WVN";s:9:"shortName";s:5:"Woori";s:4:"name";s:37:"Ngân hàng TNHH MTV Woori Việt Nam";}i:74;a:4:{s:3:"bin";s:6:"970462";s:4:"code";s:4:"KBHN";s:9:"shortName";s:9:"KookminHN";s:4:"name";s:42:"Ngân hàng Kookmin - Chi nhánh Hà Nội";}i:75;a:4:{s:3:"bin";s:6:"970463";s:4:"code";s:5:"KBHCM";s:9:"shortName";s:10:"KookminHCM";s:4:"name";s:60:"Ngân hàng Kookmin - Chi nhánh Thành phố Hồ Chí Minh";}}', 1800363063),
	('laravel-cache-spatie.permission.cache', 'a:3:{s:5:"alias";a:4:{s:1:"a";s:2:"id";s:1:"b";s:4:"name";s:1:"c";s:10:"guard_name";s:1:"r";s:5:"roles";}s:11:"permissions";a:51:{i:0;a:4:{s:1:"a";i:1;s:1:"b";s:15:"categories.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:1;a:4:{s:1:"a";i:2;s:1:"b";s:17:"categories.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:2;a:4:{s:1:"a";i:3;s:1:"b";s:15:"categories.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:3;a:4:{s:1:"a";i:4;s:1:"b";s:17:"categories.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:4;a:4:{s:1:"a";i:5;s:1:"b";s:13:"products.view";s:1:"c";s:3:"web";s:1:"r";a:4:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;}}i:5;a:4:{s:1:"a";i:6;s:1:"b";s:15:"products.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:6;a:4:{s:1:"a";i:7;s:1:"b";s:13:"products.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:7;a:4:{s:1:"a";i:8;s:1:"b";s:15:"products.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:8;a:4:{s:1:"a";i:9;s:1:"b";s:11:"brands.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:9;a:4:{s:1:"a";i:10;s:1:"b";s:13:"brands.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:10;a:4:{s:1:"a";i:11;s:1:"b";s:11:"brands.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:11;a:4:{s:1:"a";i:12;s:1:"b";s:13:"brands.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:12;a:4:{s:1:"a";i:13;s:1:"b";s:10:"pos.access";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:13;a:4:{s:1:"a";i:14;s:1:"b";s:10:"users.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:14;a:4:{s:1:"a";i:15;s:1:"b";s:12:"users.create";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:15;a:4:{s:1:"a";i:16;s:1:"b";s:10:"users.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:16;a:4:{s:1:"a";i:17;s:1:"b";s:12:"users.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:17;a:4:{s:1:"a";i:19;s:1:"b";s:14:"dashboard.view";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:18;a:4:{s:1:"a";i:20;s:1:"b";s:10:"sales.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:19;a:4:{s:1:"a";i:21;s:1:"b";s:12:"sales.cancel";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:20;a:4:{s:1:"a";i:22;s:1:"b";s:12:"repairs.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:4;}}i:21;a:4:{s:1:"a";i:23;s:1:"b";s:14:"repairs.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:4;}}i:22;a:4:{s:1:"a";i:24;s:1:"b";s:12:"repairs.edit";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:4;}}i:23;a:4:{s:1:"a";i:25;s:1:"b";s:16:"repairs.complete";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:4;}}i:24;a:4:{s:1:"a";i:26;s:1:"b";s:14:"repairs.return";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:4;}}i:25;a:4:{s:1:"a";i:27;s:1:"b";s:18:"stock_imports.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:26;a:4:{s:1:"a";i:28;s:1:"b";s:20:"stock_imports.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:27;a:4:{s:1:"a";i:29;s:1:"b";s:15:"products.import";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:28;a:4:{s:1:"a";i:30;s:1:"b";s:15:"products.export";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:29;a:4:{s:1:"a";i:31;s:1:"b";s:14:"customers.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:30;a:4:{s:1:"a";i:32;s:1:"b";s:16:"customers.create";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:31;a:4:{s:1:"a";i:33;s:1:"b";s:14:"customers.edit";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:32;a:4:{s:1:"a";i:34;s:1:"b";s:16:"customers.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:33;a:4:{s:1:"a";i:35;s:1:"b";s:14:"suppliers.view";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:34;a:4:{s:1:"a";i:36;s:1:"b";s:16:"suppliers.create";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:35;a:4:{s:1:"a";i:37;s:1:"b";s:14:"suppliers.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:36;a:4:{s:1:"a";i:38;s:1:"b";s:16:"suppliers.delete";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:37;a:4:{s:1:"a";i:39;s:1:"b";s:19:"customer_debts.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:38;a:4:{s:1:"a";i:40;s:1:"b";s:18:"customer_debts.pay";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:39;a:4:{s:1:"a";i:41;s:1:"b";s:19:"supplier_debts.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:40;a:4:{s:1:"a";i:42;s:1:"b";s:18:"supplier_debts.pay";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:41;a:4:{s:1:"a";i:43;s:1:"b";s:12:"reports.view";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:42;a:4:{s:1:"a";i:44;s:1:"b";s:12:"roles.manage";s:1:"c";s:3:"web";s:1:"r";a:1:{i:0;i:1;}}i:43;a:4:{s:1:"a";i:45;s:1:"b";s:13:"settings.view";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:44;a:4:{s:1:"a";i:46;s:1:"b";s:13:"settings.edit";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:45;a:4:{s:1:"a";i:47;s:1:"b";s:14:"backups.manage";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:46;a:4:{s:1:"a";i:48;s:1:"b";s:12:"units.manage";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:47;a:4:{s:1:"a";i:49;s:1:"b";s:23:"payment_accounts.manage";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:48;a:4:{s:1:"a";i:50;s:1:"b";s:10:"sales.edit";s:1:"c";s:3:"web";s:1:"r";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:49;a:4:{s:1:"a";i:51;s:1:"b";s:13:"expenses.view";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}i:50;a:4:{s:1:"a";i:52;s:1:"b";s:15:"expenses.manage";s:1:"c";s:3:"web";s:1:"r";a:2:{i:0;i:1;i:1;i:2;}}}s:5:"roles";a:4:{i:0;a:3:{s:1:"a";i:1;s:1:"b";s:11:"Super Admin";s:1:"c";s:3:"web";}i:1;a:3:{s:1:"a";i:2;s:1:"b";s:5:"Admin";s:1:"c";s:3:"web";}i:2;a:3:{s:1:"a";i:3;s:1:"b";s:22:"Nhân viên bán hàng";s:1:"c";s:3:"web";}i:3;a:3:{s:1:"a";i:4;s:1:"b";s:18:"Kỹ thuật viên";s:1:"c";s:3:"web";}}}', 1792632449);

-- Dumping data for table qlbh.cache_locks: ~0 rows (approximately)

-- Dumping data for table qlbh.categories: ~6 rows (approximately)
REPLACE INTO `categories` (`id`, `parent_id`, `name`, `slug`, `attributes`, `sort_order`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, NULL, 'Điện Thoại', 'dien-thoai', NULL, 0, 1, '2026-08-15 16:43:02', '2026-08-15 16:43:02', NULL),
	(2, NULL, 'Camera', 'camera', NULL, 0, 1, '2026-08-15 16:44:39', '2026-08-15 16:44:39', NULL),
	(3, NULL, 'Phụ kiện', 'phu-kien', NULL, 0, 1, '2026-08-15 16:45:09', '2026-08-15 16:45:09', NULL),
	(4, NULL, 'Linh kiện', 'linh-kien', NULL, 0, 1, '2026-10-12 12:43:29', '2026-10-12 12:43:29', NULL),
	(5, NULL, 'Thiết bị mạng', 'thiet-bi-mang', NULL, 0, 1, '2026-10-18 11:30:35', '2026-10-18 11:30:35', NULL),
	(6, NULL, 'Vật tư', 'vat-tu', NULL, 0, 1, '2026-10-18 11:31:20', '2026-10-18 11:31:20', NULL),
	(8, NULL, 'Dây Mạng', 'day-mang', NULL, 0, 1, '2026-10-18 11:32:24', '2026-10-18 11:32:24', NULL),
	(9, NULL, 'Máy tính', 'may-tinh', NULL, 0, 1, '2026-10-19 11:27:49', '2026-10-19 11:27:49', NULL);

-- Dumping data for table qlbh.category_attributes: ~8 rows (approximately)
REPLACE INTO `category_attributes` (`id`, `category_id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Màu sắc', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(2, 1, 'Ram', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(3, 1, 'Bộ nhớ', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(4, 1, 'Phiên bản', '2026-08-15 16:43:02', '2026-08-15 16:43:02'),
	(5, 2, 'Độ phân giải', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(6, 2, 'Màu ban đêm', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(7, 2, 'Trong/Ngoài', '2026-08-15 16:44:39', '2026-08-15 16:44:39'),
	(8, 3, 'OEM', '2026-08-15 16:45:09', '2026-08-15 16:45:09'),
	(9, 4, 'Loại', '2026-10-12 12:45:33', '2026-10-12 12:45:33');

-- Dumping data for table qlbh.category_attribute_values: ~13 rows (approximately)
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
	(16, 3, '64Gb', '2026-08-17 07:39:25', '2026-08-17 07:39:25'),
	(17, 9, 'Zin', '2026-10-12 12:45:33', '2026-10-12 12:45:33'),
	(18, 9, 'Loại 1', '2026-10-12 12:45:33', '2026-10-12 12:45:33'),
	(19, 9, 'Bóc máy', '2026-10-14 13:51:40', '2026-10-14 13:51:40'),
	(20, 9, 'New', '2026-10-14 13:51:40', '2026-10-14 13:51:40'),
	(21, 2, '8G', '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(22, 1, 'Trắng', '2026-10-19 09:58:03', '2026-10-19 09:58:03');

-- Dumping data for table qlbh.customers: ~2 rows (approximately)
REPLACE INTO `customers` (`id`, `code`, `full_name`, `search_text`, `phone`, `email`, `birthday`, `gender`, `cccd`, `province`, `district`, `ward`, `address`, `note`, `point_balance`, `debt_balance`, `total_spent`, `total_orders`, `last_order_at`, `customer_type`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'KH000001', 'Đức Hòa', 'duc hoa', '0906064789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 480.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-09-12 17:18:11', '2026-10-19 02:44:51', NULL),
	(2, 'KH000002', 'Kim Ngân', 'kim ngan', '1234567', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, NULL, 'retail', 1, NULL, '2026-09-12 17:18:28', '2026-09-12 17:18:28', NULL);

-- Dumping data for table qlbh.customer_debts: ~0 rows (approximately)
REPLACE INTO `customer_debts` (`id`, `customer_id`, `type`, `amount`, `source_type`, `source_id`, `payment_method`, `note`, `created_at`, `updated_at`) VALUES
	(1, 1, 'increase', 360.00, 'App\\Models\\Sale', 1, NULL, 'Mua nợ - Tiền hàng còn thiếu của hóa đơn INV-20261009191729', '2026-10-09 12:17:29', '2026-10-09 12:17:29'),
	(2, 1, 'increase', 120.00, 'App\\Models\\Sale', 2, NULL, 'Mua nợ - Tiền hàng còn thiếu của hóa đơn INV-20261019094450', '2026-10-19 02:44:51', '2026-10-19 02:44:51');

-- Dumping data for table qlbh.customer_devices: ~0 rows (approximately)
REPLACE INTO `customer_devices` (`id`, `customer_id`, `brand`, `model`, `imei`, `serial`, `color`, `note`, `created_at`, `updated_at`) VALUES
	(1, 1, NULL, 'A03', '2222', '2222', NULL, NULL, '2026-10-09 10:19:33', '2026-10-09 10:19:33');

-- Dumping data for table qlbh.customer_images: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_logs: ~0 rows (approximately)

-- Dumping data for table qlbh.customer_points: ~0 rows (approximately)

-- Dumping data for table qlbh.expenses: ~0 rows (approximately)

-- Dumping data for table qlbh.export_histories: ~0 rows (approximately)

-- Dumping data for table qlbh.failed_jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.hold_sales: ~0 rows (approximately)

-- Dumping data for table qlbh.jobs: ~0 rows (approximately)

-- Dumping data for table qlbh.job_batches: ~0 rows (approximately)

-- Dumping data for table qlbh.migrations: ~53 rows (approximately)
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
	(32, '2026_09_13_000001_add_unit_conversion_to_stock_import_items_table', 1),
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
	(53, '2026_10_09_000005_allow_null_phone_for_users', 2),
	(54, '2026_09_15_000000_add_variant_id_to_sale_items_table', 3),
	(55, '2026_10_09_000006_expand_application_permissions', 3),
	(56, '2026_10_09_000007_add_payment_account_permission', 4),
	(57, '2026_10_09_000008_normalize_super_admin_and_admin_role', 5),
	(58, '2026_10_09_000006_add_avatar_to_users_table', 6),
	(59, '2026_10_12_100000_add_supplier_warranty_to_stock_import_items', 7),
	(60, '2026_10_13_000001_add_sales_edit_permission', 8),
	(61, '2026_10_13_000002_grant_sales_edit_to_sales_view_roles', 9),
	(62, '2026_10_13_000003_grant_sales_edit_to_direct_sales_view_users', 10),
	(63, '2026_10_18_000001_create_expenses_table', 11),
	(64, '2026_10_18_000002_add_expense_permissions', 11),
	(65, '2026_10_18_000003_create_recurring_expenses_tables', 11),
	(66, '2026_10_18_090000_add_warranty_fields_to_sale_items_table', 11),
	(67, '2026_10_18_100000_add_product_imei_to_sale_item_gifts_table', 11),
	(68, '2026_10_18_110000_create_work_shifts_table', 11),
	(69, '2026_10_18_120000_add_shift_customer_debt_amount', 11),
	(70, '2026_10_19_000001_add_bank_account_to_sales_table', 12),
	(71, '2026_10_19_000002_add_counterparty_to_stock_imports', 13),
	(72, '2026_10_19_000003_add_extra_info_to_stock_import_items', 13),
	(73, '2026_10_20_120000_create_bank_payment_intents_table', 14);

-- Dumping data for table qlbh.model_has_permissions: ~0 rows (approximately)

-- Dumping data for table qlbh.model_has_roles: ~2 rows (approximately)
REPLACE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
	(1, 'App\\Models\\User', 1),
	(4, 'App\\Models\\User', 2),
	(3, 'App\\Models\\User', 3);

-- Dumping data for table qlbh.notifications: ~22 rows (approximately)
REPLACE INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
	('01d794cd-b3e9-4bec-b3e0-6b3717f5a0b7', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "/records/sale/9", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021202615 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-21 13:27:15', '2026-10-21 13:27:15'),
	('10f77f2f-3cd3-4f0e-aca6-4b33e6375448', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "/records/sale/10", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021203051 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-21 13:31:20', '2026-10-21 13:31:20'),
	('250d33b1-e28a-4c85-af83-5fb452fff2bf', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020111839 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 04:19:23', '2026-10-20 04:19:23'),
	('253372bf-9e47-45e5-8502-d1625d2ce5a0', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "/records/sale/10", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021203051 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-21 13:31:19', '2026-10-21 13:31:19'),
	('26660058-a9a7-48f7-b0cc-e939d47ab297', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261009191729 đã được tạo cho Đức Hòa."}', NULL, '2026-10-09 12:17:29', '2026-10-09 12:17:29'),
	('34e57d9b-4504-42a6-ae5e-6cf1f07b8a78', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261009191729 đã được tạo cho Đức Hòa."}', '2026-10-11 03:15:31', '2026-10-09 12:17:29', '2026-10-11 03:15:31'),
	('38c354b0-358c-41c8-99aa-ba2e052e787f', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020201453 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 13:15:51', '2026-10-20 13:15:51'),
	('39bd90e6-a7e9-4e9b-99b1-d343cbc18ec2', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020113321 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', '2026-10-21 11:40:26', '2026-10-20 04:33:58', '2026-10-21 11:40:26'),
	('5225b5fe-1c2c-465f-9f2e-164c5cff75d3', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "/records/sale/9", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021202615 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-21 13:27:14', '2026-10-21 13:27:14'),
	('56db3065-8095-4a14-83af-f09d2d15f93b', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "/records/sale/10", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021203051 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', '2026-10-21 13:32:38', '2026-10-21 13:31:19', '2026-10-21 13:32:38'),
	('58f69c2b-dfd6-4a9d-87df-997914f30b27', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020111839 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 04:19:23', '2026-10-20 04:19:23'),
	('5c85cd9e-2401-420c-88f0-3762f0838a22', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020201453 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 13:15:51', '2026-10-20 13:15:51'),
	('608eaa9c-8b8c-4701-b759-dba4a8a20392', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://qlbh.test/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261019094450 đã được tạo cho Đức Hòa."}', NULL, '2026-10-19 02:44:54', '2026-10-19 02:44:54'),
	('6b6421f4-faa1-4fb1-97b7-1218f783c695', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020101408 đã nhận đủ 7.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 03:14:41', '2026-10-20 03:14:41'),
	('731faa7f-3d24-4d0e-b8da-ea0b409ab8b2', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://qlbh.test/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261019094450 đã được tạo cho Đức Hòa."}', '2026-10-20 03:08:23', '2026-10-19 02:44:54', '2026-10-20 03:08:23'),
	('7c544cde-a226-4405-8761-07c68880b1c7', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020201453 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', '2026-10-21 12:31:52', '2026-10-20 13:15:51', '2026-10-21 12:31:52'),
	('92154ded-1dbe-4023-a721-15df161cd8ff', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020111839 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', '2026-10-21 12:49:35', '2026-10-20 04:19:23', '2026-10-21 12:49:35'),
	('99fa7739-ea9f-49ab-9474-a543b08f3956', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020101408 đã nhận đủ 7.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 03:14:41', '2026-10-20 03:14:41'),
	('a824801d-4b6d-4148-8a48-1a06cf8ed8ec', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020101408 đã nhận đủ 7.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 03:14:40', '2026-10-20 03:14:40'),
	('b352ad23-df3a-4dbd-8628-7e0c5104bf4e', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "/records/sale/9", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261021202615 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', '2026-10-21 13:32:57', '2026-10-21 13:27:14', '2026-10-21 13:32:57'),
	('c3534258-5b38-4fd9-9268-f310d667580a', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://qlbh.test/repairs", "type": "warning", "title": "Cập nhật phiếu sửa SC-20261009171933", "message": "Phiếu SC-20261009171933 chuyển sang trạng thái đã hủy phiếu."}', NULL, '2026-10-15 13:11:06', '2026-10-15 13:11:06'),
	('d5bbb0b3-5961-4bc6-90b3-e5c4dcaf9ade', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://qlbh.test/repairs", "type": "warning", "title": "Cập nhật phiếu sửa SC-20261009171933", "message": "Phiếu SC-20261009171933 chuyển sang trạng thái đã hủy phiếu."}', '2026-10-21 11:40:59', '2026-10-15 13:11:06', '2026-10-21 11:40:59'),
	('d9638af0-0264-432d-bd6f-8941195ce69b', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020113321 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 04:33:58', '2026-10-20 04:33:58'),
	('dbf5001f-bfd8-46ef-a726-47284a7ddbd4', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://qlbh.test/sales", "type": "sale", "title": "Đơn hàng mới", "message": "Hóa đơn INV-20261019094450 đã được tạo cho Đức Hòa."}', NULL, '2026-10-19 02:44:54', '2026-10-19 02:44:54'),
	('e8d32384-11d8-49ba-9129-5891b8462acb', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://192.168.100.202/sales", "type": "sale", "title": "Đã nhận đủ tiền chuyển khoản", "message": "Hóa đơn INV-20261020113321 đã nhận đủ 2.000 ₫ và tự hoàn tất."}', NULL, '2026-10-20 04:33:58', '2026-10-20 04:33:58'),
	('f2749b3e-1239-4499-ad02-a3f1bc6c0b6b', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 3, '{"url": "http://qlbh.test/repairs", "type": "warning", "title": "Cập nhật phiếu sửa SC-20261009171933", "message": "Phiếu SC-20261009171933 chuyển sang trạng thái đã hủy phiếu."}', NULL, '2026-10-15 13:11:06', '2026-10-15 13:11:06'),
	('f8f2b6b4-39aa-4e2d-87e4-a5e6840ffbb2', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 2, '{"url": "http://qlbh.test/repairs", "type": "repair", "title": "Tiếp nhận máy sửa chữa mới", "message": "Đức Hòa vừa gửi tiếp nhận A03 (SC-20261009171933)."}', NULL, '2026-10-09 10:19:33', '2026-10-09 10:19:33'),
	('fd78a69f-5f21-4ad6-af67-08d717fa9597', 'App\\Notifications\\SystemNotification', 'App\\Models\\User', 1, '{"url": "http://qlbh.test/repairs", "type": "repair", "title": "Tiếp nhận máy sửa chữa mới", "message": "Đức Hòa vừa gửi tiếp nhận A03 (SC-20261009171933)."}', '2026-10-09 12:11:18', '2026-10-09 10:19:33', '2026-10-09 12:11:18');

-- Dumping data for table qlbh.password_reset_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.permissions: ~47 rows (approximately)
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
	(17, 'users.delete', 'web', '2026-08-15 16:40:32', '2026-08-15 16:40:32'),
	(19, 'dashboard.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(20, 'sales.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(21, 'sales.cancel', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(22, 'repairs.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(23, 'repairs.create', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(24, 'repairs.edit', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(25, 'repairs.complete', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(26, 'repairs.return', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(27, 'stock_imports.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(28, 'stock_imports.create', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(29, 'products.import', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(30, 'products.export', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(31, 'customers.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(32, 'customers.create', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(33, 'customers.edit', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(34, 'customers.delete', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(35, 'suppliers.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(36, 'suppliers.create', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(37, 'suppliers.edit', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(38, 'suppliers.delete', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(39, 'customer_debts.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(40, 'customer_debts.pay', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(41, 'supplier_debts.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(42, 'supplier_debts.pay', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(43, 'reports.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(44, 'roles.manage', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(45, 'settings.view', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(46, 'settings.edit', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(47, 'backups.manage', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(48, 'units.manage', 'web', '2026-10-09 09:56:29', '2026-10-09 09:56:29'),
	(49, 'payment_accounts.manage', 'web', '2026-10-09 09:58:59', '2026-10-09 09:58:59'),
	(50, 'sales.edit', 'web', '2026-10-13 12:52:03', '2026-10-13 12:52:03'),
	(51, 'expenses.view', 'web', '2026-10-19 01:26:16', '2026-10-19 01:26:16'),
	(52, 'expenses.manage', 'web', '2026-10-19 01:26:16', '2026-10-19 01:26:16');

-- Dumping data for table qlbh.personal_access_tokens: ~0 rows (approximately)

-- Dumping data for table qlbh.products: ~12 rows (approximately)
REPLACE INTO `products` (`id`, `category_id`, `brand_id`, `unit_id`, `has_unit_conversion`, `conversion_unit_id`, `conversion_factor`, `name`, `search_text`, `slug`, `sku`, `barcode`, `product_type`, `image`, `warranty_days`, `allow_negative_stock`, `cost_price`, `sell_price`, `tax_percent`, `stock`, `sold_count`, `alert_stock`, `manage_stock_by_serial`, `is_active`, `description`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, NULL, 0, NULL, 1, 'Samsung A01', 'samsung a01 dthsam Điện thoại samsung', 'samsung-a01', 'DTHSAM', NULL, 'imei', 'products/cPpJHqZGWAdX4K97JbI2vfz8mTZ4HFCJQxq2y1m0.jpg', 0, 0, 100.00, 120.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-15 16:48:02', '2026-10-15 13:08:14'),
	(2, 1, 2, NULL, 0, NULL, 1, 'Iphone 12 promax', 'iphone 12 promax dthapp Điện thoại apple', 'iphone-12-promax', 'DTHAPP', NULL, 'imei', 'products/fF3B8pKXVCEleDLxXJygQX8EGKz1ThxqCZHA69B4.jpg', 0, 0, 100.00, 120.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-15 16:49:37', '2026-08-15 16:49:37'),
	(3, 3, 4, NULL, 0, NULL, 1, 'Củ Sạc 20W', 'củ sạc 20w pkigen phụ kiện oem', 'cu-sac-20w', 'PKIGEN', NULL, 'normal', 'products/igncnAe4rPy71vsBMPwjcEDfnlQZWFYJG2OEB24X.webp', 0, 0, 100.00, 60000.00, 0.00, 26, 4, 0, 0, 1, NULL, NULL, '2026-08-15 16:50:31', '2026-10-19 12:19:13'),
	(6, 1, 1, NULL, 0, NULL, 1, 'A03', 'a03 dthsam-001 Điện thoại samsung', 'a03', 'DTHSAM-001', NULL, 'imei', 'products/Rq6YqWK27Pfol7u2FHZ4fzAvoafutEs7LLyWipu9.jpg', 0, 0, 100.00, 110.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-08-16 06:52:00', '2026-10-21 03:51:08'),
	(7, 4, NULL, 1, 0, NULL, 1, 'Màn hình', 'màn hình lkigen linh kiện ', 'man-hinh', 'LKIGEN', NULL, 'normal', 'products/zlZEZlOQoHFz0Xx9dqlpBxDPykCIb9arZhDL7i0e.jpg', 0, 0, 300000.00, 350000.00, 0.00, 0, 0, 0, 0, 1, NULL, NULL, '2026-10-12 12:45:33', '2026-10-12 12:45:33'),
	(8, 4, 5, 1, 0, NULL, 1, 'IC nguồn', 'ic nguồn lkioem linh kiện oem', 'ic-nguon', 'LKIOEM', NULL, 'normal', 'products/MnNOfc57oKSdoct3JD7ZoKyfTb9ycc2Xy6uVdFFO.webp', 0, 0, 20000.00, 50000.00, 0.00, 0, 0, 0, 0, 1, NULL, NULL, '2026-10-14 13:51:40', '2026-10-14 13:51:40'),
	(9, 8, 6, 2, 1, 3, 500, 'Dây mạng cường lực ngoài trời', 'dây mạng cường lực ngoài trời dmavha dây mạng việt hàn', 'day-mang-cuong-luc-ngoai-troi', 'DMAVHA', NULL, 'normal', 'products/FNHmw5LvSglO8nvI0hOP1H5jqYLYHfN9XZ6h7d5K.jpg', 0, 0, 3700.00, 7000.00, 0.00, 492, 8, 0, 0, 1, NULL, NULL, '2026-10-18 11:34:05', '2026-10-21 13:31:19'),
	(10, 1, 1, 4, 0, NULL, 1, 'Samsung A17 4G', 'samsung a17 4g dthsam-002 Điện thoại samsung', 'samsung-a17-4g', 'DTHSAM-002', NULL, 'imei', 'products/9b4iNggjXFw1NEU3BONg2066pOlgmSfgPjS95c39.jpg', 0, 0, 1000000.00, 2000000.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-10-19 09:43:40', '2026-10-19 09:43:40'),
	(11, 1, 1, 4, 0, NULL, 1, 'Samsung A17 5G', 'samsung a17 5g dthsam-003 Điện thoại samsung', 'samsung-a17-5g', 'DTHSAM-003', NULL, 'imei', 'products/GLNcjcP6lZPRg7rJVf9p4LquqIJcDeN6vsayMoXP.jpg', 0, 0, 3000000.00, 3500000.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-10-19 09:51:04', '2026-10-19 09:51:04'),
	(12, 1, 1, 4, 0, NULL, 1, 'Samsung A16 4G', 'samsung a16 4g dthsam-004 Điện thoại samsung', 'samsung-a16-4g', 'DTHSAM-004', NULL, 'imei', 'products/jF7boawzRnPWIVGavEzgG2qJ17ea9ss3Pqgr35PB.jpg', 0, 0, 2000000.00, 2500000.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(13, 1, 1, 4, 0, NULL, 1, 'Samsung A16 5G', 'samsung a16 5g dthsam-005 Điện thoại samsung', 'samsung-a16-5g', 'DTHSAM-005', NULL, 'imei', 'products/Zzgmhj2MUrl5IhmTp7PMogrA08tjAKDkJ35ccvJ1.jpg', 0, 0, 2100000.00, 2600000.00, 0.00, 0, 0, 0, 1, 1, NULL, NULL, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(14, 4, 8, 1, 0, NULL, 1, 'Dây sạc có imei', 'dây sạc có imei lkiapp linh kiện apple', 'day-sac-co-imei', 'LKIAPP', NULL, 'imei', 'products/PT6hmdFeBDVMWhLmVx2KcuvGHfAYzpmLxja5dMVj.jpg', 0, 0, 100000.00, 150000.00, 0.00, 2, 0, 0, 1, 1, NULL, NULL, '2026-10-19 12:07:26', '2026-10-19 12:10:19');

-- Dumping data for table qlbh.product_imeis: ~4 rows (approximately)
REPLACE INTO `product_imeis` (`id`, `product_id`, `variant_id`, `supplier_id`, `customer_id`, `sale_id`, `imei`, `serial`, `color`, `storage`, `extra_info`, `condition`, `battery_health`, `purchase_price`, `cost_price`, `sell_price`, `warranty_expired_at`, `customer_warranty_expires_at`, `customer_warranty_days`, `customer_warranty_voided_at`, `customer_warranty_void_reason`, `imported_at`, `sold_at`, `status`, `note`, `deleted_at`, `created_at`, `updated_at`, `customer_warranty_voided_by`) VALUES
	(1, 6, 129, NULL, NULL, NULL, '2222', NULL, NULL, NULL, '{"supplier_warranty_unit": "months"}', 'new', NULL, 0.00, 100.00, 110.00, NULL, NULL, NULL, NULL, NULL, '2026-10-09 10:17:43', NULL, 'in_stock', NULL, NULL, '2026-10-09 10:17:43', '2026-10-09 10:17:43', NULL),
	(2, 6, 131, NULL, NULL, NULL, '1234543221', NULL, NULL, NULL, '{"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}', 'new', NULL, 0.00, 2300000.00, 2500000.00, '2027-10-18 17:00:00', NULL, NULL, NULL, NULL, '2026-10-19 02:51:40', NULL, 'in_stock', NULL, NULL, '2026-10-19 02:51:40', '2026-10-19 02:51:40', NULL),
	(3, 14, NULL, NULL, NULL, NULL, '1233258', NULL, NULL, NULL, '{"supplier_warranty_unit": "months"}', 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-10-19 12:10:19', NULL, 'in_stock', NULL, NULL, '2026-10-19 12:10:19', '2026-10-19 12:10:19', NULL),
	(4, 14, NULL, NULL, NULL, NULL, '265885656', NULL, NULL, NULL, '{"supplier_warranty_unit": "months"}', 'new', NULL, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, '2026-10-19 12:10:19', NULL, 'in_stock', NULL, NULL, '2026-10-19 12:10:19', '2026-10-19 12:10:19', NULL);

-- Dumping data for table qlbh.product_imei_histories: ~4 rows (approximately)
REPLACE INTO `product_imei_histories` (`id`, `product_imei_id`, `imei`, `type`, `reference_type`, `reference_id`, `cost_price`, `sell_price`, `meta`, `note`, `happened_at`, `created_at`, `updated_at`) VALUES
	(1, 1, '2222', 'import', 'App\\Models\\StockImport', 1, 100.00, 110.00, '{"extra_info": {"supplier_warranty_unit": "months"}, "product_id": 6, "variant_id": 129, "import_code": "IMP-20261009171743", "supplier_id": "1", "warranty_expired_at": null}', NULL, '2026-10-08 17:00:00', '2026-10-09 10:17:43', '2026-10-09 10:17:43'),
	(2, 2, '1234543221', 'import', 'App\\Models\\StockImport', 5, 2300000.00, 2500000.00, '{"extra_info": {"supplier_warranty_unit": "months", "supplier_warranty_value": "12"}, "product_id": 6, "variant_id": 131, "import_code": "IMP-20261019095139", "supplier_id": "2", "warranty_expired_at": "2027-10-18T17:00:00.000000Z"}', NULL, '2026-10-18 17:00:00', '2026-10-19 02:51:40', '2026-10-19 02:51:40'),
	(3, 3, '1233258', 'import', 'App\\Models\\StockImport', 6, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months"}, "product_id": 14, "variant_id": null, "import_code": "IMP-20261019191018", "supplier_id": "5", "warranty_expired_at": null}', NULL, '2026-10-18 17:00:00', '2026-10-19 12:10:19', '2026-10-19 12:10:19'),
	(4, 4, '265885656', 'import', 'App\\Models\\StockImport', 6, 0.00, 0.00, '{"extra_info": {"supplier_warranty_unit": "months"}, "product_id": 14, "variant_id": null, "import_code": "IMP-20261019191018", "supplier_id": "5", "warranty_expired_at": null}', NULL, '2026-10-18 17:00:00', '2026-10-19 12:10:19', '2026-10-19 12:10:19');

-- Dumping data for table qlbh.product_variants: ~41 rows (approximately)
REPLACE INTO `product_variants` (`id`, `product_id`, `sku`, `barcode`, `attributes`, `cost_price`, `sell_price`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
	(76, 2, 'DTHAPP-DEN-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 120.00, 0, 1, '2026-08-17 07:40:51', '2026-08-17 07:40:51'),
	(81, 1, 'DTHSAM-DEN-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, 1, '2026-08-17 07:42:01', '2026-10-15 13:08:15'),
	(82, 1, 'DTHSAM-XANH-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, 1, '2026-08-17 07:42:01', '2026-10-15 13:08:15'),
	(83, 1, 'DTHSAM-VANG-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Vàng"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, 1, '2026-08-17 07:42:01', '2026-10-15 13:08:15'),
	(84, 1, 'DTHSAM-TIM-4G-32GB', NULL, '[{"name": "Màu sắc", "value": "Tím"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "32Gb"}]', 100.00, 120.00, 0, 1, '2026-08-17 07:42:01', '2026-10-15 13:08:15'),
	(129, 6, 'DTHSAM-XANH-4G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 1, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(130, 6, 'DTHSAM-XANH-4G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(131, 6, 'DTHSAM-XANH-6G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 1, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(132, 6, 'DTHSAM-XANH-6G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(133, 6, 'DTHSAM-DEN-4G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(134, 6, 'DTHSAM-DEN-4G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(135, 6, 'DTHSAM-DEN-6G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(136, 6, 'DTHSAM-DEN-6G-64GB-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 100.00, 110.00, 0, 1, '2026-08-17 07:45:15', '2026-10-21 03:51:08'),
	(137, 7, 'LKIGEN-ZIN', NULL, '[{"name": "Loại", "value": "Zin"}]', 0.00, 0.00, 0, 1, '2026-10-12 12:45:33', '2026-10-12 12:45:33'),
	(138, 7, 'LKIGEN-LOAI', NULL, '[{"name": "Loại", "value": "Loại 1"}]', 0.00, 0.00, 0, 1, '2026-10-12 12:45:33', '2026-10-12 12:45:33'),
	(139, 8, 'LKIOEM-BOC-', NULL, '[{"name": "Loại", "value": "Bóc máy"}]', 0.00, 60000.00, 10, 1, '2026-10-14 13:51:40', '2026-10-14 13:53:14'),
	(140, 8, 'LKIOEM-NEW', NULL, '[{"name": "Loại", "value": "New"}]', 0.00, 0.00, 1, 1, '2026-10-14 13:51:40', '2026-10-14 13:53:14'),
	(141, 10, 'DTHSAM-DEN-4G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:43:41', '2026-10-19 09:43:41'),
	(142, 11, 'DTHSAM-DEN-6G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:51:04', '2026-10-19 09:51:04'),
	(143, 12, 'DTHSAM-DEN-8G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(144, 12, 'DTHSAM-DEN-8G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(145, 12, 'DTHSAM-XANH-8G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(146, 12, 'DTHSAM-XANH-8G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:56:17', '2026-10-19 09:56:17'),
	(147, 13, 'DTHSAM-DEN-6G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(148, 13, 'DTHSAM-DEN-6G-128G-QUOC-002', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(149, 13, 'DTHSAM-DEN-8G-64G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(150, 13, 'DTHSAM-DEN-8G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(151, 13, 'DTHSAM-DEN-4G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(152, 13, 'DTHSAM-DEN-4G-128G-QUOC-002', NULL, '[{"name": "Màu sắc", "value": "Đen"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(153, 13, 'DTHSAM-XANH-6G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(154, 13, 'DTHSAM-XANH-6G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(155, 13, 'DTHSAM-XANH-8G-64G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(156, 13, 'DTHSAM-XANH-8G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(157, 13, 'DTHSAM-XANH-4G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(158, 13, 'DTHSAM-XANH-4G-128G-QUOC-001', NULL, '[{"name": "Màu sắc", "value": "Xanh"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(159, 13, 'DTHSAM-TRAN-6G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(160, 13, 'DTHSAM-TRAN-6G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "6G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(161, 13, 'DTHSAM-TRAN-8G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(162, 13, 'DTHSAM-TRAN-8G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "8G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(163, 13, 'DTHSAM-TRAN-4G-64G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "64G"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03'),
	(164, 13, 'DTHSAM-TRAN-4G-128G-QUOC', NULL, '[{"name": "Màu sắc", "value": "Trắng"}, {"name": "Ram", "value": "4G"}, {"name": "Bộ nhớ", "value": "128Gb"}, {"name": "Phiên bản", "value": "Quốc tế"}]', 0.00, 0.00, 0, 1, '2026-10-19 09:58:03', '2026-10-19 09:58:03');

-- Dumping data for table qlbh.recurring_expenses: ~1 rows (approximately)
REPLACE INTO `recurring_expenses` (`id`, `category`, `description`, `amount`, `due_day`, `remind_days`, `payment_method`, `recipient`, `note`, `is_active`, `user_id`, `created_at`, `updated_at`) VALUES
	(1, 'rent', 'Tiền thuê mặt bằng', 6000000.00, 25, 4, 'cash', 'Chủ nhà', NULL, 1, 1, '2026-10-21 04:15:19', '2026-10-21 04:17:02');

-- Dumping data for table qlbh.recurring_expense_payments: ~0 rows (approximately)

-- Dumping data for table qlbh.repairs: ~0 rows (approximately)
REPLACE INTO `repairs` (`id`, `code`, `customer_id`, `contact_phone`, `device_name`, `imei`, `serial`, `screen_password`, `screen_pattern`, `account_type`, `account_email`, `account_password`, `issue`, `repair_request`, `accessories`, `estimated_cost`, `parts_total`, `labor_cost`, `surcharge`, `final_cost`, `paid_amount`, `change_amount`, `payment_method`, `payment_note`, `status`, `intake_type`, `warranty_source_type`, `warranty_source_id`, `warranty_expires_at`, `warranty_status`, `warranty_declined_at`, `warranty_decline_reason`, `warranty_covered_amount`, `note`, `technician_id`, `received_at`, `completed_at`, `returned_at`, `repair_warranty_days`, `repair_warranty_started_at`, `repair_warranty_expires_at`, `repair_warranty_voided_at`, `repair_warranty_void_reason`, `created_at`, `updated_at`, `repair_warranty_voided_by`) VALUES
	(1, 'SC-20261009171933', 1, NULL, 'A03', '2222', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, NULL, NULL, 'cancelled', 'repair', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, '2026-10-09 10:19:33', NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-10-09 10:19:33', '2026-10-15 13:11:05', NULL);

-- Dumping data for table qlbh.repair_images: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_parts: ~0 rows (approximately)

-- Dumping data for table qlbh.repair_timelines: ~0 rows (approximately)
REPLACE INTO `repair_timelines` (`id`, `repair_id`, `user_id`, `status`, `title`, `description`, `issue`, `parts_needed`, `waiting_for_parts`, `waiting_mode`, `expected_days`, `created_at`, `updated_at`) VALUES
	(1, 1, 2, 'pending', 'Đã tiếp nhận máy', 'Tạo phiếu sửa chữa mới', NULL, NULL, 0, 'repair_now', NULL, '2026-10-09 10:19:33', '2026-10-09 10:19:33'),
	(2, 1, 1, 'cancelled', 'Đã hủy phiếu sửa', NULL, '[]', NULL, 0, 'repair_now', NULL, '2026-10-15 13:11:05', '2026-10-15 13:11:05');

-- Dumping data for table qlbh.roles: ~4 rows (approximately)
REPLACE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
	(1, 'Super Admin', 'web', '2026-08-15 16:40:31', '2026-08-15 16:40:31'),
	(2, 'Admin', 'web', '2026-08-15 16:40:32', '2026-10-09 10:20:25'),
	(3, 'Nhân viên bán hàng', 'web', '2026-10-09 09:19:43', '2026-10-09 13:14:24'),
	(4, 'Kỹ thuật viên', 'web', '2026-10-09 09:20:49', '2026-10-09 09:20:49');

-- Dumping data for table qlbh.role_has_permissions: ~120 rows (approximately)
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
	(46, 1),
	(47, 1),
	(48, 1),
	(49, 1),
	(50, 1),
	(51, 1),
	(52, 1),
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
	(45, 2),
	(46, 2),
	(47, 2),
	(48, 2),
	(49, 2),
	(50, 2),
	(51, 2),
	(52, 2),
	(1, 3),
	(2, 3),
	(5, 3),
	(6, 3),
	(9, 3),
	(10, 3),
	(13, 3),
	(14, 3),
	(20, 3),
	(21, 3),
	(27, 3),
	(28, 3),
	(31, 3),
	(32, 3),
	(33, 3),
	(39, 3),
	(40, 3),
	(41, 3),
	(45, 3),
	(50, 3),
	(5, 4),
	(22, 4),
	(23, 4),
	(24, 4),
	(25, 4),
	(26, 4);

-- Dumping data for table qlbh.sales: ~10 rows (approximately)
REPLACE INTO `sales` (`id`, `code`, `customer_id`, `user_id`, `work_shift_id`, `subtotal`, `discount`, `tax`, `grand_total`, `paid_amount`, `change_amount`, `payment_method`, `bank_account`, `status`, `cancel_reason`, `cancelled_at`, `note`, `created_at`, `updated_at`) VALUES
	(1, 'INV-20261009191729', 1, 1, NULL, 360.00, 0.00, 0.00, 360.00, 0.00, 0.00, 'cash', NULL, 'completed', NULL, NULL, NULL, '2026-10-09 12:17:29', '2026-10-09 12:17:29'),
	(2, 'INV-20261019094450', 1, 1, 1, 120.00, 0.00, 0.00, 120.00, 0.00, 0.00, 'cash', NULL, 'completed', NULL, NULL, NULL, '2026-10-19 02:44:50', '2026-10-19 02:44:51'),
	(3, 'INV-20261020092817', NULL, 1, 1, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 02:29:22', '2026-10-20 02:29:23'),
	(4, 'INV-20261020100803', 1, 1, 1, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 03:12:07', '2026-10-20 03:12:07'),
	(5, 'INV-20261020101408', NULL, 1, 1, 7000.00, 0.00, 0.00, 7000.00, 7000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 03:14:35', '2026-10-20 03:14:35'),
	(6, 'INV-20261020111839', NULL, 1, 1, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 04:19:23', '2026-10-20 04:19:23'),
	(7, 'INV-20261020113321', 1, 1, 1, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 04:33:58', '2026-10-20 04:33:58'),
	(8, 'INV-20261020201453', 1, 1, 1, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-20 13:15:50', '2026-10-20 13:15:50'),
	(9, 'INV-20261021202615', 1, 1, 2, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-21 13:27:08', '2026-10-21 13:27:08'),
	(10, 'INV-20261021203051', 1, 1, 2, 7000.00, 5000.00, 0.00, 2000.00, 2000.00, 0.00, 'bank', '8807252653', 'completed', NULL, NULL, NULL, '2026-10-21 13:31:19', '2026-10-21 13:31:19');

-- Dumping data for table qlbh.sale_items: ~1 rows (approximately)
REPLACE INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_imei_id`, `unit_id`, `unit_name`, `quantity`, `base_quantity`, `conversion_factor`, `unit_price`, `discount`, `tax`, `subtotal`, `note`, `discount_type`, `discount_value`, `warranty_days`, `warranty_value`, `warranty_unit`, `created_at`, `updated_at`) VALUES
	(1, 1, 3, NULL, NULL, NULL, NULL, 3, 3, 1, 120.00, 0.00, 0.00, 360.00, NULL, NULL, 0.00, 0, NULL, NULL, '2026-10-09 12:17:29', '2026-10-09 12:17:29'),
	(2, 2, 3, NULL, NULL, NULL, NULL, 1, 1, 1, 120.00, 0.00, 0.00, 120.00, NULL, NULL, 0.00, 0, NULL, NULL, '2026-10-19 02:44:50', '2026-10-19 02:44:50'),
	(3, 3, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-20 02:29:23', '2026-10-20 02:29:23'),
	(4, 4, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-20 03:12:07', '2026-10-20 03:12:07'),
	(5, 5, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 7000.00, NULL, NULL, 0.00, 0, NULL, NULL, '2026-10-20 03:14:35', '2026-10-20 03:14:35'),
	(6, 6, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-20 04:19:23', '2026-10-20 04:19:23'),
	(7, 7, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-20 04:33:58', '2026-10-20 04:33:58'),
	(8, 8, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-20 13:15:50', '2026-10-20 13:15:50'),
	(9, 9, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-21 13:27:08', '2026-10-21 13:27:08'),
	(10, 10, 9, NULL, NULL, 2, 'M', 1, 1, 1, 7000.00, 0.00, 0.00, 2000.00, NULL, 'amount', 5000.00, 0, NULL, NULL, '2026-10-21 13:31:19', '2026-10-21 13:31:19');

-- Dumping data for table qlbh.sale_item_gifts: ~0 rows (approximately)

-- Dumping data for table qlbh.sessions: ~12 rows (approximately)
REPLACE INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
	('0syQp4tszugyaNyH8Dp6QZDMMm5ki7FsAFbWd3nK', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieFJ2YTFlVXJkM25qcElrUXkyNlRkRjlYS1c0WEhkQndQQUcyWU1xMSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792462889),
	('912zSFCHtzOp4psUcQW7rFpP5vIVQPQW20jnvxE8', 1, '192.168.100.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiM1VEbDJISGZjbkNNU2VXYVI1R0d3VWt1MkI2QkJXWkd0b1d4dDZQWCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiMmQ2NTYyNmIxNjczYjM5YTEwZDJiNTMwZGZhYTIzNzMxMmYxOGE2YWUzOWNhNGFkOGI0Nzg5MzgyNThlMzRlYiI7fQ==', 1792502169),
	('A7zChYZE7rBDaNwJQhpwgdqdGvvo4MAHyS3lZcDR', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMWhST2N1eEtvSFZuUTRFUTJPZFVUUWw5WTJ0Z2JYc3lLNnBXSGNZYSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792469966),
	('agzQEd8DVbM1OtiH99sfUvXnq0yPC3Sk6pW99jdd', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidTBuaWppa0xXZVFEWlg3ZUdEaEZBalhLdER1SXFxeDBIeE5MTGRyYSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792554681),
	('AnowEeFpu7o1YGGmGrcV3OqLmU1rRK7nBfxetFqP', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicnhXWEdTZkhnZU9FUjYyYWxIVWJaaWt2ZkZZNUFub1dRaGdjdDFhbCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792501591),
	('BX3BYr1IY4AF2M9pvLmpZZBlCOXrkL7eL2YC5TJF', 1, '192.168.100.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiOHY4M2E3VElyRmZYZUt3bjdHSjRlUTAzMzJCTWhGUHJZMGRxWVg3USI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiMmQ2NTYyNmIxNjczYjM5YTEwZDJiNTMwZGZhYTIzNzMxMmYxOGE2YWUzOWNhNGFkOGI0Nzg5MzgyNThlMzRlYiI7fQ==', 1792501753),
	('gbHqVzZlhRWIsxjVqp6IpxIhcZsyFBB5JzXahMsT', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibjVlRm9xcnRSMFRNZFdBVVVydEtkaDVPZ0pKbDRYVFBMZDJCYTZGdSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792589473),
	('GXEzQpfec3ywO911kv4FRokj8xKApYQTWZqJbYDC', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiS0dLOU1VSEN0OG5SNXk1b3ozdUlTOTEzbkFYUVVJb1BmSFp1VnlvRyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792458410),
	('iYsPu6u3q9W2HQA95V9Y64TgDgUe2cCs53xYBaA2', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiR2RzSWtjWG9QTEw5YmVlb2l0RGZTYm53S0RpSDlSWVo3blJFZW9mNyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792463099),
	('jqrkaBuN3Sqm1JN0ddathnXV3pxA6aP0n2aBCjXv', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ003THdQVExzbzFaekFkcGxMSUYzUzNIakxOYTdOT1BJWXVoYnpPbSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792466075),
	('lHJJk6XNIHCuEWtje5ZaC9ooRvJq8SRYxn1tlBl6', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUllFUUJHOTRaclhWVm9PckZIVnl1TGhXVHBYVVNESkY5cUloajNjQyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792462939),
	('RtJtfiCJVMFAcCgNVEj8sTMfxwhhCBddcZt48Q4E', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVDdpV2xrZkRjWkQ5cFhENWZKMFg2STFWWHNVMW1ab0M3UDRPQXpOWCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792463042),
	('SLtaoJCxdYDajU9TU89J6SRx1RKPOPGQhgFrkcZZ', 1, '192.168.100.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibVhUa2tOcnVZUmFObXM4MW9IZExrU21PRTN5UUtydVBPcmMyUmhwTSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792502303),
	('UbCnEdrE6w8XPfBaZn36elNRrg1LDnES7lhCCQUv', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiemgxSzEwTWRMSVhEUWFIZVB2OWpucmI3YkE0UW9lSnVDc3p2T25ZUCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792501474),
	('VapXMqSXCG22JosZx0jpLxAsWmWN0exQqNelBr8V', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ0VSVHRSdVR0WjJNSmo5WWpMc2RDYnlYMlZIZmNRNzhWc1BCd1ZqciI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792458156),
	('ZLWd2Aa3JwhbaQ71uYwNiYxpSUn6DkLrujR2mUY0', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoibURwbkRIblU5RGpDOUpMTjRGVVNNSkxySGtJU1pHcTNBdVdSd0JHQyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1792462881);

-- Dumping data for table qlbh.settings: ~28 rows (approximately)
REPLACE INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
	(1, 'shop_name', 'Đức hòa', '2026-10-09 01:03:33', '2026-10-09 01:03:33'),
	(2, 'currency_symbol', '₫', '2026-10-09 01:03:33', '2026-10-09 01:03:33'),
	(3, 'currency_format', 'vi-VN', '2026-10-09 01:03:33', '2026-10-09 01:03:33'),
	(4, 'app_locale', 'vi', '2026-10-09 01:03:33', '2026-10-09 08:21:12'),
	(5, 'app_timezone', 'Asia/Ho_Chi_Minh', '2026-10-09 01:03:33', '2026-10-09 01:03:33'),
	(6, 'date_format', 'd/m/Y', '2026-10-09 01:03:33', '2026-10-09 01:03:33'),
	(7, 'allow_negative_stock', '1', '2026-10-09 01:03:33', '2026-10-21 12:51:58'),
	(8, 'bank_bin', '970418', '2026-10-09 01:03:33', '2026-10-20 13:13:48'),
	(9, 'bank_account', '8807252653', '2026-10-09 01:03:33', '2026-10-20 13:13:48'),
	(10, 'bank_account_name', 'Do Duc Hoa', '2026-10-09 01:03:33', '2026-10-18 13:02:02'),
	(11, 'bank_transfer_content', 'Thanh toan don hang', '2026-10-09 01:03:33', '2026-10-18 13:47:44'),
	(12, 'print_paper_size', 'a4', '2026-10-09 08:20:36', '2026-10-09 08:20:36'),
	(13, 'print_orientation', 'portrait', '2026-10-09 08:20:36', '2026-10-09 08:20:36'),
	(14, 'backup_enabled', '1', '2026-10-09 08:25:35', '2026-10-10 07:09:11'),
	(15, 'backup_frequency', 'daily', '2026-10-09 08:25:35', '2026-10-09 08:25:35'),
	(16, 'backup_time', '16:06', '2026-10-09 08:25:35', '2026-10-09 09:05:38'),
	(17, 'backup_weekday', '1', '2026-10-09 08:25:35', '2026-10-09 08:25:35'),
	(18, 'backup_monthday', '1', '2026-10-09 08:25:35', '2026-10-09 08:25:35'),
	(19, 'backup_timezone', 'Asia/Ho_Chi_Minh', '2026-10-09 08:25:35', '2026-10-09 08:25:35'),
	(20, 'backup_last_run', '2026-10-21 20:39:16', '2026-10-09 08:58:04', '2026-10-21 13:39:16'),
	(21, 'backup_last_error', NULL, '2026-10-09 08:58:04', '2026-10-09 08:58:04'),
	(22, 'backup_last_slot', 'daily:2026-10-09', '2026-10-09 09:02:05', '2026-10-09 09:02:05'),
	(23, 'bank_accounts', '[{"id":"bank-1792328498457-joj9j","bank_bin":"970418","bank_name":"BIDV","bank_account":"8807252653","bank_account_name":"Do Duc Hoa","transfer_content_mode":"invoice","is_default":true,"payment_provider":"apipay","payment_provider_account_id":"cmvfel4m6007701rsf4v8mhlq","payment_api_key":"","payment_api_access_key":"eyJpdiI6InkrSk85V3RtSGNORE0xZ211a0hoMmc9PSIsInZhbHVlIjoicXZLejFKUWFXc0czcUtvNUtVSnJ2TVJValR6NTFpYjBGaFZoMXJaVlgraW9kbVRhc2UxRDV5N0RYZm9ndUZVMzhlL1B3UHdWMXcvNVY4Nmlsdk9pVWxHMTU2UFc2MExNcW5qTGxEZmNmUm89IiwibWFjIjoiMjk3MjczMDg4MWE4OTZiNGI0NWJjNjYwZDhmZjkyZTA0MWRiNmExMTlmMGRjMmU5N2M0ODNlY2NmNTBjMmQ5YiIsInRhZyI6IiJ9","payment_api_secret_key":"eyJpdiI6IjFtL09WMFhpZzVsb2FGSDEyMXRNS0E9PSIsInZhbHVlIjoiSDQ2U3huMkI4SjUrWnRPQ3V5YWhOd1VqOEl1NVdETHVRd2Z5KzJYMkpwbXBIZC9yQkI2cEZPWDYyOU5oOU0wOUNBd04rSElPMmtvRWtNaUl4T1cvZSsvOWdTeEo1NlNleGpRWWtHdmUydFYxYlpseXBOanF6SU1qQUtPK25USjAiLCJtYWMiOiJkZjRiZGM5NjU2OGRiNjdkZmU3MWMxNDE0NWE0N2RjOWY5OWFlYjdlMGIyYjg2Njg3YTcyZDI4OGMyZGEwZjk0IiwidGFnIjoiIn0=","payment_webhook_secret":"eyJpdiI6Im9OUVdwVHhjQ1p4dEQrSWlTUEp3UFE9PSIsInZhbHVlIjoidGJxOHNIeGlYVHpaeEZsR1BGSzRUMlFHVEJwcUlmaEVKU3ByVTlNMVkwR24xSFArNXpBQTNMNkc2ZnFkcUFPL3A5aURLbG1tYndRZ1R1UEY0SVRpS09WRDg4R3NaRjJadG9KU25ZUnl1VzA9IiwibWFjIjoiMWY4OGY3OWI1OWU0NDIzMGY1MjNlZGMzMjFmMzEzYmI2YTcwMjI2ZjYzMzJhZGFjOTEyNTcwMzBlMjhhYTdkZiIsInRhZyI6IiJ9"},{"id":"bank-1792328586340-gfwb7","bank_bin":"970436","bank_name":"Vietcombank","bank_account":"0211000535558","bank_account_name":"Do Duc Hoa","transfer_content_mode":"default","is_default":false,"payment_provider":"","payment_provider_account_id":"","payment_api_key":"","payment_api_access_key":"","payment_api_secret_key":"","payment_webhook_secret":""}]', '2026-10-18 13:02:02', '2026-10-20 13:13:48'),
	(24, 'bank_qr_theme', 'classic', '2026-10-18 13:47:44', '2026-10-19 12:54:31'),
	(25, 'payment_voice_language', 'vi-VN', '2026-10-20 04:18:24', '2026-10-20 04:18:24'),
	(26, 'payment_voice_enabled', '1', '2026-10-20 13:02:07', '2026-10-21 12:51:58'),
	(27, 'inventory_alert_enabled', '1', '2026-10-21 12:51:58', '2026-10-21 12:51:58'),
	(28, 'inventory_alert_threshold', '5', '2026-10-21 12:51:58', '2026-10-21 12:51:58');

-- Dumping data for table qlbh.stock_imports: ~4 rows (approximately)
REPLACE INTO `stock_imports` (`id`, `code`, `supplier_id`, `party_type`, `customer_id`, `party_name`, `party_phone`, `party_cccd`, `party_address`, `user_id`, `import_date`, `discount`, `extra_fee`, `total_amount`, `grand_total`, `paid_amount`, `note`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'IMP-20261009171743', 1, 'supplier', NULL, NULL, NULL, NULL, NULL, 1, '2026-10-09 00:00:00', 0.00, 0.00, 100.00, 100.00, 0.00, NULL, 'completed', '2026-10-09 10:17:43', '2026-10-09 10:17:43'),
	(3, 'IMP-20261014205314', 1, 'supplier', NULL, NULL, NULL, NULL, NULL, 1, '2026-10-14 00:00:00', 0.00, 0.00, 300000.00, 300000.00, 0.00, NULL, 'completed', '2026-10-14 13:53:14', '2026-10-14 13:53:14'),
	(4, 'IMP-20261018190605', 4, 'supplier', NULL, NULL, NULL, NULL, NULL, 1, '2026-10-18 00:00:00', 0.00, 0.00, 1850000.00, 1850000.00, 0.00, NULL, 'completed', '2026-10-18 12:06:05', '2026-10-18 12:06:05'),
	(5, 'IMP-20261019095139', 2, 'supplier', NULL, NULL, NULL, NULL, NULL, 1, '2026-10-19 00:00:00', 0.00, 0.00, 2800000.00, 2800000.00, 0.00, NULL, 'completed', '2026-10-19 02:51:39', '2026-10-19 02:51:39'),
	(6, 'IMP-20261019191018', 5, 'supplier', NULL, 'Duy Crown', NULL, NULL, NULL, 1, '2026-10-19 00:00:00', 0.00, 0.00, 190000.00, 190000.00, 0.00, NULL, 'completed', '2026-10-19 12:10:18', '2026-10-19 12:10:18'),
	(7, 'IMP-20261019191913', 5, 'supplier', NULL, 'Duy Crown', NULL, NULL, NULL, 1, '2026-10-19 00:00:00', 0.00, 0.00, 250000.00, 250000.00, 0.00, NULL, 'completed', '2026-10-19 12:19:13', '2026-10-19 12:19:13');

-- Dumping data for table qlbh.stock_import_items: ~8 rows (approximately)
REPLACE INTO `stock_import_items` (`id`, `stock_import_id`, `product_id`, `variant_id`, `unit_id`, `unit_name`, `quantity`, `base_quantity`, `conversion_factor`, `cost_price`, `supplier_warranty_value`, `supplier_warranty_unit`, `extra_info`, `created_at`, `updated_at`) VALUES
	(1, 1, 6, 129, NULL, NULL, 1, 1, 1, 100.00, NULL, NULL, NULL, '2026-10-09 10:17:43', '2026-10-09 10:17:43'),
	(2, 3, 8, 139, 1, 'Cái', 10, 10, 1, 30000.00, 1, 'months', NULL, '2026-10-14 13:53:14', '2026-10-14 13:53:14'),
	(3, 3, 8, 140, 1, 'Cái', 1, 1, 1, 0.00, 1, 'months', NULL, '2026-10-14 13:53:14', '2026-10-14 13:53:14'),
	(4, 4, 9, NULL, 3, 'Cuộn', 1, 500, 500, 1850000.00, NULL, NULL, NULL, '2026-10-18 12:06:05', '2026-10-18 12:06:05'),
	(5, 5, 3, NULL, NULL, NULL, 20, 20, 1, 25000.00, NULL, NULL, NULL, '2026-10-19 02:51:40', '2026-10-19 02:51:40'),
	(6, 5, 6, 131, NULL, NULL, 1, 1, 1, 2300000.00, NULL, NULL, NULL, '2026-10-19 02:51:40', '2026-10-19 02:51:40'),
	(7, 6, 14, NULL, 1, 'Cái', 1, 1, 1, 100000.00, NULL, NULL, NULL, '2026-10-19 12:10:19', '2026-10-19 12:10:19'),
	(8, 6, 14, NULL, 1, 'Cái', 1, 1, 1, 90000.00, NULL, NULL, NULL, '2026-10-19 12:10:19', '2026-10-19 12:10:19'),
	(9, 7, 3, NULL, NULL, NULL, 10, 10, 1, 25000.00, NULL, NULL, NULL, '2026-10-19 12:19:13', '2026-10-19 12:19:13');

-- Dumping data for table qlbh.suppliers: ~5 rows (approximately)
REPLACE INTO `suppliers` (`id`, `code`, `name`, `search_text`, `contact_person`, `phone`, `email`, `tax_code`, `province`, `district`, `ward`, `address`, `debt_balance`, `total_purchase`, `total_orders`, `note`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'NCC00001', 'Shoppe', 'shoppe', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 300100.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:18', '2026-10-14 13:53:14', NULL),
	(2, 'NCC00002', 'Thắng Hải', 'thang hai', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 2800000.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:32', '2026-10-19 02:51:40', NULL),
	(3, 'NCC00003', 'Quân', 'quan', NULL, '12345678', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0, NULL, 1, '2026-09-12 17:17:38', '2026-09-12 17:17:38', NULL),
	(4, 'NCC00004', 'Hải Đường', 'hai duong', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1850000.00, 0.00, 0, NULL, 1, '2026-10-18 12:05:17', '2026-10-18 12:06:05', NULL),
	(5, 'NCC00005', 'Duy Crown', 'duy crown', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 440000.00, 0.00, 0, NULL, 1, '2026-10-19 12:08:23', '2026-10-19 12:19:13', NULL);

-- Dumping data for table qlbh.supplier_debts: ~0 rows (approximately)
REPLACE INTO `supplier_debts` (`id`, `supplier_id`, `type`, `amount`, `source_type`, `source_id`, `payment_method`, `note`, `user_id`, `created_at`, `updated_at`) VALUES
	(1, 1, 'increase', 100.00, 'App\\Models\\StockImport', 1, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261009171743', 1, '2026-10-09 10:17:43', '2026-10-09 10:17:43'),
	(3, 1, 'increase', 300000.00, 'App\\Models\\StockImport', 3, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261014205314', 1, '2026-10-14 13:53:14', '2026-10-14 13:53:14'),
	(4, 4, 'increase', 1850000.00, 'App\\Models\\StockImport', 4, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261018190605', 1, '2026-10-18 12:06:05', '2026-10-18 12:06:05'),
	(5, 2, 'increase', 2800000.00, 'App\\Models\\StockImport', 5, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261019095139', 1, '2026-10-19 02:51:40', '2026-10-19 02:51:40'),
	(6, 5, 'increase', 190000.00, 'App\\Models\\StockImport', 6, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261019191018', 1, '2026-10-19 12:10:19', '2026-10-19 12:10:19'),
	(7, 5, 'increase', 250000.00, 'App\\Models\\StockImport', 7, NULL, 'Công nợ phát sinh từ phiếu nhập IMP-20261019191913', 1, '2026-10-19 12:19:13', '2026-10-19 12:19:13');

-- Dumping data for table qlbh.units: ~3 rows (approximately)
REPLACE INTO `units` (`id`, `name`, `short_name`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
	(1, 'Cái', 'Cái', 1, NULL, '2026-10-12 12:44:30', '2026-10-12 12:44:30'),
	(2, 'Mét', 'M', 1, NULL, '2026-10-18 11:32:50', '2026-10-18 11:32:50'),
	(3, 'Cuộn', 'Cuộn', 1, NULL, '2026-10-18 11:33:41', '2026-10-18 11:33:41'),
	(4, 'Chiếc', 'Chiếc', 1, NULL, '2026-10-19 09:41:19', '2026-10-19 09:41:19');

-- Dumping data for table qlbh.users: ~3 rows (approximately)
REPLACE INTO `users` (`id`, `name`, `username`, `phone`, `email`, `avatar`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrator', 'admin', '0906064789', 'admin@gmail.com', 'avatars/j8RDQRBgEeA5f2lYTUIU1yLPb2hmIYfoYSAhcMky.jpg', NULL, '$2y$12$WUtVW1Ln7UXJI.iKXYlgAu724YU4eTf3di4wanGwr0OumLnL8u4w2', 'lOihNiYeuRmHYwjE2ejkJc1jdQ6ZrFUVmuw5suHIPKMNjyiQRc2MeoTmzBY0', '2026-08-15 16:40:32', '2026-10-10 06:54:57'),
	(2, 'Hòa', 'hoa', NULL, NULL, NULL, NULL, '$2y$12$T4kvDo.4lafJk/0WRajAFufFUoaUWlZpxwrFiAMYJ65Y9xCOtpc7m', 'XUOpbx4qAeoxcFOFIm4RVS6HbJa4W0aOi5WcV8GaS5mNCrZqbCJY7hQBFFPN', '2026-10-09 09:44:53', '2026-10-09 09:45:08'),
	(3, 'Hiền', 'hien', '0789201197', 'duchoa.92@gmail.com', NULL, NULL, '$2y$12$qeIp9sQLqzns.kHg9E7PhOfgirovk4TrRUUHgJf.i/wR0SptslgZW', NULL, '2026-10-09 13:04:47', '2026-10-09 13:29:02');

-- Dumping data for table qlbh.work_shifts: ~0 rows (approximately)
REPLACE INTO `work_shifts` (`id`, `user_id`, `opening_amount`, `opening_denominations`, `opening_bank_balances`, `cash_collected`, `customer_debt_amount`, `expected_cash`, `closing_amount`, `closing_denominations`, `closing_bank_balances`, `opened_at`, `closed_at`, `created_at`, `updated_at`) VALUES
	(1, 1, 1632000.00, '{"1000": 0, "2000": 1, "5000": 4, "10000": 5, "20000": 3, "50000": 6, "100000": 2, "200000": 5, "500000": 0}', '{"bank-1792328498457-joj9j": 1000000, "bank-1792328586340-gfwb7": 0}', 0.00, 120.00, 1632000.00, 1632000.00, '{"1000": 0, "2000": 0, "5000": 0, "10000": 0, "20000": 0, "50000": 0, "100000": 0, "200000": 0, "500000": 0}', '{"bank-1792328498457-joj9j": 1017000, "bank-1792328586340-gfwb7": 0}', '2026-10-19 01:49:11', '2026-10-20 13:20:29', '2026-10-19 01:49:11', '2026-10-20 13:20:29'),
	(2, 1, 5000000.00, '{"1000": 0, "2000": 0, "5000": 0, "10000": 0, "20000": 0, "50000": 0, "100000": 0, "200000": 0, "500000": 0}', '{"bank-1792328498457-joj9j": 0, "bank-1792328586340-gfwb7": 0}', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-21 01:03:20', NULL, '2026-10-21 01:03:20', '2026-10-21 01:03:20');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
