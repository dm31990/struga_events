-- ============================================================
-- Struga Events — Rental Management System
-- Database schema (aligned with SEEU Software Engineering document)
-- Student: Drilon Merko (131990) · Mentor: Mentor Hamiti · May 2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS struga_events_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE struga_events_rental;

-- users — all roles (Customer, Employee, Admin via role field)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role ENUM('customer','employee','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- rental_items — catalog with stock, price, deposit, photo
CREATE TABLE rental_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    quantity_total INT NOT NULL DEFAULT 1,
    quantity_available INT NOT NULL DEFAULT 1,
    daily_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_path VARCHAR(255),
    status ENUM('available','unavailable') DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- reservations — pending → approved → active → completed | cancelled
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    delivery_address VARCHAR(255),
    event_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes TEXT,
    total_price DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','approved','active','completed','cancelled') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE reservation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    rental_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_item_id) REFERENCES rental_items(id) ON DELETE RESTRICT
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL UNIQUE,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 18.00,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_with_tax DECIMAL(10,2) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE,
    payment_status ENUM('unpaid','partially_paid','paid') DEFAULT 'unpaid',
    pdf_file_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
);

CREATE TABLE invoice_line_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

CREATE TABLE event_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    location VARCHAR(255),
    color VARCHAR(20) DEFAULT '#2e6b8a',
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- SAMPLE DATA (demo password: password)
-- ============================================================

INSERT INTO users (first_name, last_name, email, password_hash, phone, role) VALUES
('Admin', 'Struga', 'admin@strugaevents.mk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+389 46 000 000', 'admin'),
('Drilon', 'Merko', 'dm31990@seeu.edu.mk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+389 70 000 001', 'employee'),
('Ana', 'Petrovska', 'ana.customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+389 70 111 222', 'customer');

INSERT INTO categories (name, description) VALUES
('Tents', 'Wedding tents and marquees'),
('Tables', 'Round and buffet tables'),
('Chairs', 'Chiavari and standard seating'),
('Lighting', 'LED and event lighting'),
('Decoration', 'Arches and decorative elements');

INSERT INTO rental_items (name, category_id, description, daily_price, deposit_amount, quantity_total, quantity_available, status) VALUES
('Wedding Tent 10x15m', 1, 'Premium lakeside wedding tent for up to 150 guests.', 180.00, 200.00, 4, 4, 'available'),
('Marquee Tent 6x12m', 1, 'Medium marquee for garden and corporate events.', 95.00, 100.00, 6, 6, 'available'),
('Round Table (10 seats)', 2, 'Stable round tables with linen-ready surface.', 8.00, 0, 80, 80, 'available'),
('Chiavari Gold Chair', 3, 'Elegant gold chiavari chairs.', 3.50, 0, 200, 200, 'available'),
('Floral Wedding Arch', 5, 'Decorative arch for ceremonies.', 45.00, 50.00, 10, 10, 'available'),
('LED String Lighting Kit', 4, 'Warm white outdoor LED kit (50m).', 35.00, 25.00, 15, 15, 'available');
