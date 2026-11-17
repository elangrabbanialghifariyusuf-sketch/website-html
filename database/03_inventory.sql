USE hayatisayang;

ALTER TABLE products
    ADD COLUMN stock_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER price;

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
