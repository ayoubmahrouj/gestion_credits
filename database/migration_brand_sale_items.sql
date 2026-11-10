-- Migration : marque sur les lignes de vente
-- Base : gestion_credits_php
--
-- 1. Ajoute la colonne `brand` dans `sale_items`.
-- 2. Recopie la marque du produit pour les lignes existantes.
-- 3. Retire l'ancien préfixe « MARQUE — » de `product_name`.

ALTER TABLE `sale_items`
  ADD COLUMN `brand` VARCHAR(120) NOT NULL DEFAULT '' AFTER `product_id`;

UPDATE `sale_items` si
JOIN `products` p ON p.id = si.`product_id`
SET si.`brand` = COALESCE(p.`brand`, ''),
    si.`product_name` = CASE
        WHEN p.`brand` <> '' AND si.`product_name` LIKE CONCAT(p.`brand`, ' — %')
            THEN SUBSTRING(si.`product_name`, CHAR_LENGTH(p.`brand`) + 4)
        ELSE si.`product_name`
    END
WHERE si.`brand` = '';
