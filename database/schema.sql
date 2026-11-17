CREATE DATABASE IF NOT EXISTS hayatisayang CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hayatisayang;

CREATE TABLE IF NOT EXISTS login (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    nomor_hp VARCHAR(30) NOT NULL DEFAULT '',
    role ENUM('customer', 'kasir', 'admin') NOT NULL DEFAULT 'customer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    product_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    price INT UNSIGNED NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    photo VARCHAR(255) NOT NULL DEFAULT '',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS option_groups (
    group_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_option_groups_category FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS option_values (
    value_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(80) NOT NULL,
    extra_price INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_option_values_group FOREIGN KEY (group_id) REFERENCES option_groups(group_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS floors (
    floor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tables_cafe (
    table_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    floor_id BIGINT UNSIGNED NOT NULL,
    table_number VARCHAR(20) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_floor_table (floor_id, table_number),
    CONSTRAINT fk_tables_floor FOREIGN KEY (floor_id) REFERENCES floors(floor_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cashier_shifts (
    shift_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cashier_id BIGINT UNSIGNED NOT NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opening_cash INT UNSIGNED NOT NULL DEFAULT 0,
    closed_at DATETIME NULL,
    closing_cash INT UNSIGNED NULL,
    notes VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_cashier_shift_cashier_open (cashier_id, closed_at),
    KEY idx_cashier_shift_opened (opened_at),
    CONSTRAINT fk_cashier_shift_user FOREIGN KEY (cashier_id) REFERENCES login(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(24) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    order_type ENUM('dine_in', 'takeaway') NOT NULL,
    status ENUM('menunggu_bayar', 'diterima', 'diproses', 'disajikan', 'siap', 'selesai', 'batal', 'kedaluwarsa') NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    payment_method VARCHAR(40) NOT NULL DEFAULT 'kasir',
    paid_at DATETIME NULL,
    refunded_at DATETIME NULL,
    subtotal INT UNSIGNED NOT NULL,
    packaging_fee INT UNSIGNED NOT NULL DEFAULT 0,
    total INT UNSIGNED NOT NULL,
    floor_id BIGINT UNSIGNED NULL,
    table_id BIGINT UNSIGNED NULL,
    cashier_shift_id BIGINT UNSIGNED NULL,
    buyer_name VARCHAR(100) NULL,
    buyer_phone VARCHAR(30) NULL,
    pickup_mode ENUM('asap', 'scheduled') NULL,
    pickup_at DATETIME NULL,
    ready_at DATETIME NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    payment_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_expiry (status, payment_expires_at),
    KEY idx_orders_ready (order_type, status, ready_at),
    KEY idx_orders_paid_at (paid_at),
    KEY idx_orders_refunded_at (refunded_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES login(user_id),
    CONSTRAINT fk_orders_floor FOREIGN KEY (floor_id) REFERENCES floors(floor_id),
    CONSTRAINT fk_orders_table FOREIGN KEY (table_id) REFERENCES tables_cafe(table_id),
    CONSTRAINT fk_orders_shift FOREIGN KEY (cashier_shift_id) REFERENCES cashier_shifts(shift_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(120) NOT NULL,
    price INT UNSIGNED NOT NULL,
    qty TINYINT UNSIGNED NOT NULL,
    note VARCHAR(150) NOT NULL DEFAULT '',
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_item_options (
    order_item_option_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT UNSIGNED NOT NULL,
    group_name VARCHAR(80) NOT NULL,
    value_name VARCHAR(80) NOT NULL,
    extra_price INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_item_options_item FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_log (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    changed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_status_log_order (order_id, created_at),
    CONSTRAINT fk_order_status_log_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_status_log_user FOREIGN KEY (changed_by) REFERENCES login(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
    movement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    changed_by BIGINT UNSIGNED NULL,
    movement_type ENUM('sale', 'return', 'adjustment') NOT NULL,
    quantity_delta INT NOT NULL,
    note VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_product_date (product_id, created_at),
    KEY idx_inventory_order (order_id),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(product_id),
    CONSTRAINT fk_inventory_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_user FOREIGN KEY (changed_by) REFERENCES login(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts (username, ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO categories (name, description, sort_order) SELECT 'Kopi', 'Kopi pilihan racikan barista.', 1 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Kopi');
INSERT INTO categories (name, description, sort_order) SELECT 'Non-kopi', 'Minuman segar tanpa kopi.', 2 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Non-kopi');
INSERT INTO products (category_id, name, description, price, stock_quantity) SELECT category_id, 'Kopi Susu Gula Aren', 'Espresso, susu, dan gula aren.', 24000, 20 FROM categories WHERE name = 'Kopi' AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Kopi Susu Gula Aren');
INSERT INTO products (category_id, name, description, price, stock_quantity) SELECT category_id, 'Cokelat', 'Cokelat lembut disajikan dingin atau hangat.', 22000, 20 FROM categories WHERE name = 'Non-kopi' AND NOT EXISTS (SELECT 1 FROM products WHERE name = 'Cokelat');
INSERT INTO floors (name) SELECT 'Lantai 1' WHERE NOT EXISTS (SELECT 1 FROM floors WHERE name = 'Lantai 1');
INSERT INTO floors (name) SELECT 'Lantai 2' WHERE NOT EXISTS (SELECT 1 FROM floors WHERE name = 'Lantai 2');
INSERT INTO tables_cafe (floor_id, table_number) SELECT f.floor_id, CONCAT('M', LPAD(numbers.n, 2, '0')) FROM floors f CROSS JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) numbers WHERE f.name IN ('Lantai 1', 'Lantai 2') AND NOT EXISTS (SELECT 1 FROM tables_cafe t WHERE t.floor_id = f.floor_id AND t.table_number = CONCAT('M', LPAD(numbers.n, 2, '0')));
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('nama_kafe', 'Allana Coffee'), ('alamat', ''), ('jam_buka', '08:00'), ('jam_tutup', '22:00'), ('biaya_kemasan', '2000');
