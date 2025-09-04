-- Fishing Gear Store Database Schema
-- Compatible with phpMyAdmin

CREATE DATABASE IF NOT EXISTS fishing_gear_store;
USE fishing_gear_store;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Brands table
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    category_id INT,
    brand_id INT,
    image VARCHAR(255),
    images TEXT, -- Comma-separated image URLs
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    weight DECIMAL(8,2),
    dimensions VARCHAR(100),
    material VARCHAR(100),
    color VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
);

-- Cart table (for logged-in users)
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    shipping_name VARCHAR(100) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(50) NOT NULL,
    shipping_state VARCHAR(50) NOT NULL,
    shipping_zip VARCHAR(10) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) DEFAULT 10.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product reviews table
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    review TEXT,
    images TEXT, -- Comma-separated image URLs
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_review (user_id, product_id)
);

-- Promotions table
CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('percentage', 'fixed', 'free_shipping') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0.00,
    max_discount_amount DECIMAL(10,2),
    code VARCHAR(50) UNIQUE,
    category_id INT,
    product_id INT,
    usage_limit INT,
    used_count INT DEFAULT 0,
    start_date DATETIME,
    end_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Contact messages table
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Wishlist table
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_wishlist (user_id, product_id)
);

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_brand ON products(brand_id);
CREATE INDEX idx_products_price ON products(price);
CREATE INDEX idx_products_stock ON products(stock);
CREATE INDEX idx_products_rating ON products(rating);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
CREATE INDEX idx_reviews_product ON product_reviews(product_id);
CREATE INDEX idx_reviews_user ON product_reviews(user_id);
CREATE INDEX idx_reviews_rating ON product_reviews(rating);
CREATE INDEX idx_promotions_code ON promotions(code);
CREATE INDEX idx_promotions_active ON promotions(is_active);
CREATE INDEX idx_contact_status ON contact_messages(status);

-- Insert initial data

-- Insert categories
INSERT INTO categories (name, description, image) VALUES
('Fishing Rods', 'High-quality fishing rods for all types of fishing', 'assets/images/categories/rods.jpg'),
('Fishing Reels', 'Professional fishing reels and spinners', 'assets/images/categories/reels.jpg'),
('Baits & Lures', 'Artificial baits, lures, and fishing accessories', 'assets/images/categories/baits.jpg'),
('Fishing Accessories', 'Tackle boxes, lines, hooks, and other fishing gear', 'assets/images/categories/accessories.jpg');

-- Insert brands
INSERT INTO brands (name, description, logo) VALUES
('Shimano', 'Premium fishing equipment manufacturer', 'assets/images/brands/shimano.png'),
('Daiwa', 'Innovative fishing gear and tackle', 'assets/images/brands/daiwa.png'),
('Abu Garcia', 'Professional fishing reels and rods', 'assets/images/brands/abu-garcia.png'),
('Penn', 'Saltwater fishing specialists', 'assets/images/brands/penn.png'),
('Okuma', 'Quality fishing equipment for all anglers', 'assets/images/brands/okuma.png'),
('Rapala', 'World leader in fishing lures', 'assets/images/brands/rapala.png'),
('Berkley', 'Innovative fishing lines and baits', 'assets/images/brands/berkley.png'),
('St. Croix', 'Premium fishing rods made in USA', 'assets/images/brands/st-croix.png');

-- Insert products (10 per category)

-- Fishing Rods
INSERT INTO products (name, description, price, stock, category_id, brand_id, image, images, weight, dimensions, material, color) VALUES
('Shimano Stradic FL Spinning Reel', 'High-performance spinning reel with smooth drag system and corrosion-resistant construction.', 199.99, 15, 1, 1, 'assets/images/products/shimano-stradic-fl.jpg', 'assets/images/products/shimano-stradic-fl.jpg,assets/images/products/shimano-stradic-fl-2.jpg', 0.35, '6.5" x 2.5"', 'Aluminum', 'Silver'),
('Daiwa BG Spinning Reel', 'Heavy-duty spinning reel perfect for saltwater fishing with sealed drag system.', 149.99, 12, 1, 2, 'assets/images/products/daiwa-bg.jpg', 'assets/images/products/daiwa-bg.jpg,assets/images/products/daiwa-bg-2.jpg', 0.42, '7" x 2.8"', 'Aluminum', 'Black'),
('Abu Garcia Revo SX Spinning Reel', 'Professional spinning reel with carbon matrix drag and instant anti-reverse.', 179.99, 8, 1, 3, 'assets/images/products/abu-garcia-revo-sx.jpg', 'assets/images/products/abu-garcia-revo-sx.jpg', 0.38, '6.8" x 2.6"', 'Aluminum', 'Blue'),
('Penn Battle III Spinning Reel', 'Saltwater-ready spinning reel with full metal body and HT-100 drag system.', 129.99, 20, 1, 4, 'assets/images/products/penn-battle-iii.jpg', 'assets/images/products/penn-battle-iii.jpg', 0.45, '7.2" x 2.9"', 'Aluminum', 'Silver'),
('Okuma Ceymar C-30 Spinning Reel', 'Lightweight spinning reel with precision machined aluminum spool.', 79.99, 25, 1, 5, 'assets/images/products/okuma-ceymar-c30.jpg', 'assets/images/products/okuma-ceymar-c30.jpg', 0.28, '6" x 2.2"', 'Aluminum', 'Black'),
('Shimano Teramar TMS-X80M Spinning Rod', 'Medium-heavy spinning rod perfect for inshore fishing with fast action.', 159.99, 10, 1, 1, 'assets/images/products/shimano-teramar.jpg', 'assets/images/products/shimano-teramar.jpg', 0.15, '8 feet', 'Graphite', 'Black'),
('Daiwa Procyon Spinning Rod', 'High-modulus graphite spinning rod with titanium guides and comfortable grip.', 139.99, 14, 1, 2, 'assets/images/products/daiwa-procyon.jpg', 'assets/images/products/daiwa-procyon.jpg', 0.18, '7 feet', 'Graphite', 'Black'),
('Abu Garcia Vendetta Spinning Rod', 'Sensitive spinning rod with carbon fiber construction and ergonomic handle.', 119.99, 18, 1, 3, 'assets/images/products/abu-garcia-vendetta.jpg', 'assets/images/products/abu-garcia-vendetta.jpg', 0.16, '7.5 feet', 'Carbon Fiber', 'Black'),
('Penn Battalion II Spinning Rod', 'Heavy-duty spinning rod designed for saltwater fishing with stainless steel guides.', 99.99, 22, 1, 4, 'assets/images/products/penn-battalion-ii.jpg', 'assets/images/products/penn-battalion-ii.jpg', 0.22, '8 feet', 'Graphite', 'Black'),
('Okuma Tundra Spinning Rod', 'Versatile spinning rod with comfortable cork handle and aluminum oxide guides.', 89.99, 16, 1, 5, 'assets/images/products/okuma-tundra.jpg', 'assets/images/products/okuma-tundra.jpg', 0.19, '7 feet', 'Graphite', 'Black');

-- Fishing Reels
INSERT INTO products (name, description, price, stock, category_id, brand_id, image, images, weight, dimensions, material, color) VALUES
('Shimano Curado K Baitcasting Reel', 'High-performance baitcasting reel with SVS Infinity braking system.', 199.99, 12, 2, 1, 'assets/images/products/shimano-curado-k.jpg', 'assets/images/products/shimano-curado-k.jpg', 0.32, '4.2" x 2.8"', 'Aluminum', 'Black'),
('Daiwa Tatula SV TW Baitcasting Reel', 'Advanced baitcasting reel with T-Wing system and Magforce Z braking.', 249.99, 8, 2, 2, 'assets/images/products/daiwa-tatula-sv.jpg', 'assets/images/products/daiwa-tatula-sv.jpg', 0.28, '4" x 2.6"', 'Aluminum', 'Black'),
('Abu Garcia Revo SX Baitcasting Reel', 'Professional baitcasting reel with carbon matrix drag and instant anti-reverse.', 179.99, 15, 2, 3, 'assets/images/products/abu-garcia-revo-sx-bc.jpg', 'assets/images/products/abu-garcia-revo-sx-bc.jpg', 0.35, '4.3" x 2.9"', 'Aluminum', 'Blue'),
('Penn Fathom II Lever Drag Reel', 'Heavy-duty lever drag reel perfect for big game fishing.', 299.99, 6, 2, 4, 'assets/images/products/penn-fathom-ii.jpg', 'assets/images/products/penn-fathom-ii.jpg', 1.2, '5.5" x 3.2"', 'Aluminum', 'Silver'),
('Okuma Komodo SS Baitcasting Reel', 'Saltwater baitcasting reel with corrosion-resistant construction.', 159.99, 10, 2, 5, 'assets/images/products/okuma-komodo-ss.jpg', 'assets/images/products/okuma-komodo-ss.jpg', 0.38, '4.1" x 2.7"', 'Aluminum', 'Black'),
('Shimano Stella SW Spinning Reel', 'Premium saltwater spinning reel with X-Protect and CI4+ construction.', 699.99, 4, 2, 1, 'assets/images/products/shimano-stella-sw.jpg', 'assets/images/products/shimano-stella-sw.jpg', 0.42, '6.8" x 2.9"', 'Aluminum', 'Silver'),
('Daiwa Saltist MQ Spinning Reel', 'High-performance saltwater spinning reel with Magsealed technology.', 399.99, 7, 2, 2, 'assets/images/products/daiwa-saltist-mq.jpg', 'assets/images/products/daiwa-saltist-mq.jpg', 0.45, '7" x 3"', 'Aluminum', 'Black'),
('Abu Garcia Ambassadeur C3 Baitcasting Reel', 'Classic baitcasting reel with level wind and star drag system.', 129.99, 18, 2, 3, 'assets/images/products/abu-garcia-ambassadeur-c3.jpg', 'assets/images/products/abu-garcia-ambassadeur-c3.jpg', 0.48, '4.5" x 3.1"', 'Aluminum', 'Silver'),
('Penn Slammer IV Spinning Reel', 'Heavy-duty spinning reel with full metal body and sealed drag system.', 199.99, 14, 2, 4, 'assets/images/products/penn-slammer-iv.jpg', 'assets/images/products/penn-slammer-iv.jpg', 0.52, '7.2" x 3.1"', 'Aluminum', 'Silver'),
('Okuma Helios SX Spinning Reel', 'Lightweight spinning reel with precision machined aluminum spool.', 119.99, 20, 2, 5, 'assets/images/products/okuma-helios-sx.jpg', 'assets/images/products/okuma-helios-sx.jpg', 0.31, '6.5" x 2.5"', 'Aluminum', 'Black');

-- Baits & Lures
INSERT INTO products (name, description, price, stock, category_id, brand_id, image, images, weight, dimensions, material, color) VALUES
('Rapala Original Floating Minnow', 'Classic wooden minnow lure with realistic swimming action.', 12.99, 50, 3, 6, 'assets/images/products/rapala-original.jpg', 'assets/images/products/rapala-original.jpg', 0.05, '3.5 inches', 'Wood', 'Silver'),
('Berkley PowerBait Trout Worm', 'Scented soft plastic worm that attracts trout with natural scent.', 8.99, 100, 3, 7, 'assets/images/products/berkley-powerbait-worm.jpg', 'assets/images/products/berkley-powerbait-worm.jpg', 0.02, '4 inches', 'Plastic', 'Natural'),
('Rapala Shad Rap', 'Diving crankbait with realistic shad profile and rattling sound.', 15.99, 30, 3, 6, 'assets/images/products/rapala-shad-rap.jpg', 'assets/images/products/rapala-shad-rap.jpg', 0.08, '2.5 inches', 'Plastic', 'Chartreuse'),
('Berkley Gulp! Alive Minnow', 'Scented soft plastic minnow with natural fish attractant.', 9.99, 75, 3, 7, 'assets/images/products/berkley-gulp-minnow.jpg', 'assets/images/products/berkley-gulp-minnow.jpg', 0.03, '3 inches', 'Plastic', 'White'),
('Rapala X-Rap', 'Slashbait with aggressive action and sharp treble hooks.', 18.99, 25, 3, 6, 'assets/images/products/rapala-x-rap.jpg', 'assets/images/products/rapala-x-rap.jpg', 0.12, '4 inches', 'Plastic', 'Fire Tiger'),
('Berkley PowerBait Power Eggs', 'Scented bait eggs perfect for trout and panfish.', 6.99, 200, 3, 7, 'assets/images/products/berkley-power-eggs.jpg', 'assets/images/products/berkley-power-eggs.jpg', 0.01, '0.5 inches', 'Plastic', 'Orange'),
('Rapala CountDown', 'Sinking minnow lure with precise depth control.', 14.99, 40, 3, 6, 'assets/images/products/rapala-countdown.jpg', 'assets/images/products/rapala-countdown.jpg', 0.15, '3 inches', 'Wood', 'Gold'),
('Berkley Gulp! Alive Shrimp', 'Scented soft plastic shrimp with natural saltwater scent.', 11.99, 60, 3, 7, 'assets/images/products/berkley-gulp-shrimp.jpg', 'assets/images/products/berkley-gulp-shrimp.jpg', 0.04, '2.5 inches', 'Plastic', 'Natural'),
('Rapala Husky Jerk', 'Suspending jerkbait with realistic minnow profile.', 16.99, 35, 3, 6, 'assets/images/products/rapala-husky-jerk.jpg', 'assets/images/products/rapala-husky-jerk.jpg', 0.18, '4.5 inches', 'Wood', 'Perch'),
('Berkley PowerBait Power Worms', 'Scented soft plastic worms in various colors and sizes.', 7.99, 150, 3, 7, 'assets/images/products/berkley-power-worms.jpg', 'assets/images/products/berkley-power-worms.jpg', 0.03, '6 inches', 'Plastic', 'Red');

-- Fishing Accessories
INSERT INTO products (name, description, price, stock, category_id, brand_id, image, images, weight, dimensions, material, color) VALUES
('Shimano Tackle Box', 'Waterproof tackle box with multiple compartments and removable trays.', 49.99, 20, 4, 1, 'assets/images/products/shimano-tackle-box.jpg', 'assets/images/products/shimano-tackle-box.jpg', 2.5, '12" x 8" x 6"', 'Plastic', 'Blue'),
('Berkley Trilene XL Monofilament Line', 'High-quality monofilament fishing line with excellent knot strength.', 12.99, 100, 4, 7, 'assets/images/products/berkley-trilene-xl.jpg', 'assets/images/products/berkley-trilene-xl.jpg', 0.1, '300 yards', 'Monofilament', 'Clear'),
('Mustad UltraPoint Hooks', 'Sharp, corrosion-resistant fishing hooks in various sizes.', 8.99, 200, 4, 8, 'assets/images/products/mustad-ultrapoint-hooks.jpg', 'assets/images/products/mustad-ultrapoint-hooks.jpg', 0.02, 'Size 1/0', 'Steel', 'Silver'),
('Rapala Digital Scale', 'Accurate digital fishing scale with backlit display.', 29.99, 15, 4, 6, 'assets/images/products/rapala-digital-scale.jpg', 'assets/images/products/rapala-digital-scale.jpg', 0.3, '6" x 2" x 1"', 'Plastic', 'Black'),
('Berkley PowerBait Attractant', 'Liquid fish attractant to enhance bait effectiveness.', 9.99, 50, 4, 7, 'assets/images/products/berkley-attractant.jpg', 'assets/images/products/berkley-attractant.jpg', 0.2, '4 oz', 'Liquid', 'Clear'),
('Shimano Fishing Pliers', 'Stainless steel fishing pliers with line cutter and split ring tool.', 24.99, 30, 4, 1, 'assets/images/products/shimano-pliers.jpg', 'assets/images/products/shimano-pliers.jpg', 0.4, '7 inches', 'Stainless Steel', 'Silver'),
('Berkley Trilene Braid Line', 'High-strength braided fishing line with superior sensitivity.', 18.99, 80, 4, 7, 'assets/images/products/berkley-trilene-braid.jpg', 'assets/images/products/berkley-trilene-braid.jpg', 0.08, '150 yards', 'Braided', 'Green'),
('Rapala Fish Gripper', 'Safe fish handling tool with built-in scale and measuring tape.', 19.99, 25, 4, 6, 'assets/images/products/rapala-fish-gripper.jpg', 'assets/images/products/rapala-fish-gripper.jpg', 0.3, '12 inches', 'Aluminum', 'Black'),
('Shimano Fishing Net', 'Lightweight landing net with rubber mesh to protect fish.', 34.99, 18, 4, 1, 'assets/images/products/shimano-fishing-net.jpg', 'assets/images/products/shimano-fishing-net.jpg', 0.8, '18" x 15"', 'Aluminum/Rubber', 'Black'),
('Berkley PowerBait Gloves', 'Grip-enhancing fishing gloves with touchscreen compatibility.', 14.99, 40, 4, 7, 'assets/images/products/berkley-fishing-gloves.jpg', 'assets/images/products/berkley-fishing-gloves.jpg', 0.1, 'One Size', 'Synthetic', 'Black');

-- Insert admin user
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@fishinggear.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample promotions
INSERT INTO promotions (name, description, type, value, min_order_amount, code, start_date, end_date, is_active) VALUES
('Welcome Discount', '10% off your first order', 'percentage', 10.00, 50.00, 'WELCOME10', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), TRUE),
('Free Shipping', 'Free shipping on orders over $100', 'free_shipping', 0.00, 100.00, 'FREESHIP', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), TRUE),
('Holiday Sale', '$20 off orders over $150', 'fixed', 20.00, 150.00, 'HOLIDAY20', NOW(), DATE_ADD(NOW(), INTERVAL 3 MONTH), TRUE);

-- Update product ratings and review counts (simulate some reviews)
UPDATE products SET rating = 4.5, review_count = 12 WHERE id = 1;
UPDATE products SET rating = 4.2, review_count = 8 WHERE id = 2;
UPDATE products SET rating = 4.7, review_count = 15 WHERE id = 3;
UPDATE products SET rating = 4.0, review_count = 6 WHERE id = 4;
UPDATE products SET rating = 4.3, review_count = 10 WHERE id = 5;
UPDATE products SET rating = 4.6, review_count = 18 WHERE id = 6;
UPDATE products SET rating = 4.1, review_count = 7 WHERE id = 7;
UPDATE products SET rating = 4.4, review_count = 11 WHERE id = 8;
UPDATE products SET rating = 3.9, review_count = 5 WHERE id = 9;
UPDATE products SET rating = 4.2, review_count = 9 WHERE id = 10;

-- Insert some sample reviews
INSERT INTO product_reviews (product_id, user_id, rating, title, review) VALUES
(1, 1, 5, 'Excellent reel!', 'This Shimano Stradic FL is amazing. Smooth drag and very durable. Highly recommend!'),
(2, 1, 4, 'Great value', 'The Daiwa BG is a solid reel for the price. Good for saltwater fishing.'),
(3, 1, 5, 'Perfect for bass fishing', 'Love this Abu Garcia reel. The drag system is smooth and the reel is very reliable.');

-- Create a view for product details with category and brand names
CREATE VIEW product_details AS
SELECT 
    p.*,
    c.name as category_name,
    b.name as brand_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id;

-- Create a view for order details with user information
CREATE VIEW order_details AS
SELECT 
    o.*,
    u.name as user_name,
    u.email as user_email
FROM orders o
LEFT JOIN users u ON o.user_id = u.id;