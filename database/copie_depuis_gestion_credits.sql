-- Copie des données depuis gestion_credits (schéma Laravel) vers gestion_credits_php (schéma natif PHP)
-- Lecture seule sur gestion_credits.

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `gestion_credits_php`.`users` (id, name, username, password, role, is_active, created_at)
SELECT id, name, username, password, role, is_active, COALESCE(created_at, NOW())
FROM `gestion_credits`.`users`;

INSERT INTO `gestion_credits_php`.`clients` (id, name, phone, address, credit_limit, is_active, is_passenger, created_at)
SELECT id, name, phone, address, credit_limit, is_active, is_passenger, COALESCE(created_at, NOW())
FROM `gestion_credits`.`clients`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`brands` (id, name, is_active, created_at)
SELECT id, name, is_active, COALESCE(created_at, NOW())
FROM `gestion_credits`.`brands`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`products` (id, brand, name, description, price, is_active, created_at)
SELECT id, COALESCE(brand, ''), name_fr, description, sale_price, is_active, COALESCE(created_at, NOW())
FROM `gestion_credits`.`products`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`sales` (id, client_id, user_id, date, subtotal, discount, total, paid, payment_method, status, note, created_at)
SELECT id, client_id, user_id, date, subtotal, discount, total, paid_amount,
       CASE WHEN type = 'credit' THEN 'credit' ELSE COALESCE(type, 'cash') END,
       CASE WHEN status = 'unpaid' THEN 'credit' ELSE status END,
       notes, COALESCE(created_at, NOW())
FROM `gestion_credits`.`sales`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`sale_items` (id, sale_id, product_id, brand, product_name, qty, unit_price, total)
SELECT si.id, si.sale_id, si.product_id,
       COALESCE((SELECT p.brand FROM `gestion_credits`.`products` p WHERE p.id = si.product_id), ''),
       COALESCE((SELECT p.name_fr FROM `gestion_credits`.`products` p WHERE p.id = si.product_id), 'Article'),
       si.quantity, si.unit_price, si.total
FROM `gestion_credits`.`sale_items` si
ORDER BY si.id;

INSERT INTO `gestion_credits_php`.`credits` (id, sale_id, client_id, amount, paid, remaining, due_date, status, created_at)
SELECT id, sale_id, client_id, principal, amount_paid, balance, due_date,
       CASE WHEN status = 'active' THEN 'open' ELSE status END,
       COALESCE(created_at, NOW())
FROM `gestion_credits`.`credits`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`payments` (id, client_id, user_id, date, amount, method, note, created_at)
SELECT id, client_id, received_by, DATE(paid_at), amount, method, notes, COALESCE(created_at, NOW())
FROM `gestion_credits`.`payments`
ORDER BY id;

INSERT INTO `gestion_credits_php`.`payment_allocations` (id, payment_id, credit_id, amount)
SELECT pa.id, pa.payment_id, i.credit_id, pa.amount
FROM `gestion_credits`.`payment_allocations` pa
JOIN `gestion_credits`.`installments` i ON i.id = pa.installment_id
ORDER BY pa.id;

INSERT INTO `gestion_credits_php`.`settings` (`key`, `value`)
SELECT `key`, `value` FROM `gestion_credits`.`settings`;

SET FOREIGN_KEY_CHECKS = 1;
