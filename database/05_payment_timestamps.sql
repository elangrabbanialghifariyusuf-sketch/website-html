USE hayatisayang;

ALTER TABLE orders
    ADD COLUMN paid_at DATETIME NULL AFTER payment_method,
    ADD COLUMN refunded_at DATETIME NULL AFTER paid_at,
    ADD KEY idx_orders_paid_at (paid_at),
    ADD KEY idx_orders_refunded_at (refunded_at);

UPDATE orders
SET paid_at = updated_at
WHERE payment_status IN ('paid', 'refunded') AND paid_at IS NULL;

UPDATE orders
SET refunded_at = updated_at
WHERE payment_status = 'refunded' AND refunded_at IS NULL;
