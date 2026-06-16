ALTER TABLE sale_items
ADD COLUMN gift_name VARCHAR(255) NULL AFTER note;

ALTER TABLE sale_items
ADD COLUMN discount_type ENUM('amount','percent') NULL AFTER gift_name;

ALTER TABLE sale_items
ADD COLUMN discount_value DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER discount_type;



-- 1. Thêm cột (nếu cột chưa tồn tại)
ALTER TABLE sale_items
ADD COLUMN gift_product_id BIGINT UNSIGNED NULL;

-- 2. Tạo khóa ngoại với ràng buộc NULL on delete
ALTER TABLE sale_items
ADD CONSTRAINT fk_gift_product_id
FOREIGN KEY (gift_product_id) REFERENCES products (id)
ON DELETE SET NULL;