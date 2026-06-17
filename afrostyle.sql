-- AfroStyle Database Schema
CREATE DATABASE IF NOT EXISTS afrostyle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE afrostyle;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    promo_price DECIMAL(10,2) DEFAULT NULL,
    image VARCHAR(255),
    images JSON,
    available_sizes JSON COMMENT 'Array of available sizes e.g. ["S","M","L"]',
    allow_custom_measure TINYINT(1) DEFAULT 0,
    stock INT DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Sénégal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_method ENUM('domicile','point_retrait') DEFAULT 'domicile',
    delivery_address TEXT,
    delivery_city VARCHAR(100),
    delivery_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','confirmed','in_production','shipped','delivered','cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'cash',
    payment_status ENUM('unpaid','paid') DEFAULT 'unpaid',
    notes TEXT,
    wave_session_id VARCHAR(255) DEFAULT NULL,
    paydunya_token VARCHAR(255) DEFAULT NULL,
    confirm_token VARCHAR(64) DEFAULT NULL,
    sender_phone VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    size VARCHAR(20),
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    is_custom_measure TINYINT(1) DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE measurements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    tour_poitrine DECIMAL(5,1),
    tour_taille DECIMAL(5,1),
    tour_hanches DECIMAL(5,1),
    longueur_epaule DECIMAL(5,1),
    longueur_totale DECIMAL(5,1),
    longueur_manche DECIMAL(5,1),
    tour_cou DECIMAL(5,1),
    tour_bras DECIMAL(5,1),
    notes TEXT,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

CREATE TABLE delivery_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(100) NOT NULL,
    note TEXT,
    location VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: admin / afrostyle2024
INSERT INTO admins (username, email, password) VALUES
('admin', 'admin@afrostyle.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample categories
INSERT INTO categories (name, slug, description) VALUES
('Robes', 'robes', 'Robes traditionnelles et modernes africaines'),
('Ensemble Homme', 'ensemble-homme', 'Tenues complètes pour hommes'),
('Ensemble Femme', 'ensemble-femme', 'Tenues complètes pour femmes'),
('Accessoires', 'accessoires', 'Sacs, bijoux et accessoires africains'),
('Bazin', 'bazin', 'Collections Bazin riche et brodé');

-- Sample products
INSERT INTO products (category_id, name, slug, description, price, available_sizes, allow_custom_measure, stock, featured, image) VALUES
(1, 'Robe Kente Royale', 'robe-kente-royale', 'Magnifique robe en tissu Kente authentique, broderies dorées à la main. Un mélange parfait entre tradition et modernité.', 45000, '["XS","S","M","L","XL","XXL"]', 1, 15, 1, NULL),
(1, 'Robe Wax Élégance', 'robe-wax-elegance', 'Robe longue en wax premium, imprimés exclusifs. Coupe moderne et raffinée pour toutes les occasions.', 35000, '["XS","S","M","L","XL"]', 1, 20, 1, NULL),
(2, 'Boubou Grand Bazin', 'boubou-grand-bazin', 'Grand boubou en bazin riche blanc brodé main. L''élégance africaine dans toute sa splendeur.', 65000, '["S","M","L","XL","XXL"]', 1, 10, 1, NULL),
(2, 'Costume Africain Moderne', 'costume-africain-moderne', 'Costume 3 pièces inspiré des tenues traditionnelles, coupe contemporaine. Parfait pour les grandes occasions.', 75000, '["S","M","L","XL","XXL"]', 1, 8, 0, NULL),
(3, 'Ensemble Pagne Royal', 'ensemble-pagne-royal', 'Ensemble 2 pièces en pagne wax premium. Haut et jupe assortis avec broderies exclusives.', 42000, '["XS","S","M","L","XL","XXL"]', 1, 18, 1, NULL),
(5, 'Bazin Brodé Premium', 'bazin-brode-premium', 'Tissu bazin riche confectionné sur mesure avec broderies traditionnelles. Disponible en plusieurs coloris.', 55000, '["S","M","L","XL"]', 1, 12, 1, NULL);
