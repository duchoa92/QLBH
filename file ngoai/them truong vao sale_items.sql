ALTER TABLE sale_items
ADD COLUMN gift_name VARCHAR(255) NULL AFTER note;

ALTER TABLE sale_items
ADD COLUMN discount_type ENUM('amount','percent') NULL AFTER gift_name;

ALTER TABLE sale_items
ADD COLUMN discount_value DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER discount_type;