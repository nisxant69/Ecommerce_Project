-- Create brands table
CREATE TABLE IF NOT EXISTS brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(255),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add brand_id to products table
ALTER TABLE products
ADD COLUMN brand_id INT,
ADD COLUMN total_sales INT DEFAULT 0,
ADD FOREIGN KEY (brand_id) REFERENCES brands(id);

-- Insert sample brands
INSERT INTO brands (name, description, website) VALUES
('Apple', 'Technology company that designs and develops consumer electronics', 'https://www.apple.com'),
('Samsung', 'Global leader in technology and electronics manufacturing', 'https://www.samsung.com'),
('Nike', 'Leading manufacturer of athletic shoes and sports equipment', 'https://www.nike.com'),
('Adidas', 'Sports apparel and equipment manufacturer', 'https://www.adidas.com'),
('Sony', 'Multinational technology and entertainment company', 'https://www.sony.com'),
('Dell', 'Computer technology company', 'https://www.dell.com'),
('HP', 'Information technology company', 'https://www.hp.com'),
('LG', 'Electronics, chemicals, and telecommunications products', 'https://www.lg.com'),
('Lenovo', 'Technology company specializing in PCs and smart devices', 'https://www.lenovo.com'),
('Asus', 'Computer hardware and consumer electronics company', 'https://www.asus.com');
