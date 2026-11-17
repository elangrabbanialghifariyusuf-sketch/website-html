USE hayatisayang;

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

ALTER TABLE orders
    ADD COLUMN cashier_shift_id BIGINT UNSIGNED NULL AFTER table_id,
    ADD KEY idx_orders_cashier_shift (cashier_shift_id),
    ADD CONSTRAINT fk_orders_shift FOREIGN KEY (cashier_shift_id) REFERENCES cashier_shifts(shift_id) ON DELETE SET NULL;
