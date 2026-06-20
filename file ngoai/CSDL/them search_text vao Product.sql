-- Thêm cột search_text
ALTER TABLE products 
ADD COLUMN search_text VARCHAR(255) NULL AFTER name;

-- Tạo index cho cột search_text
CREATE INDEX products_search_text_index ON products (search_text);

-- Tạo index cho cột sku (nếu cột này đã tồn tại)
CREATE INDEX products_sku_index ON products (sku);