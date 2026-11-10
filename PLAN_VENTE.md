# Plan — Vente (`sales`) et ses lignes (`sale_items` / VenteItem)

## 1. Objectif

Une **vente** représente une opération de caisse ou de crédit. Elle contient
**une ou plusieurs lignes** (`VenteItem`) qui décrivent les produits vendus.

```
Vente (1) ──────── (N) VenteItem
   │
   └── (0..1) Crédit
```

---

## 2. Modèle de données

### 2.1 Vente — table `sales`

| Champ | Type | Rôle |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK | Identifiant de la vente |
| `client_id` | BIGINT, FK → `clients.id` | Client (ou client « Passager ») |
| `user_id` | BIGINT, FK → `users.id` | Utilisateur qui a encaissé |
| `date` | DATE | Date de la vente |
| `subtotal` | DECIMAL(14,2) | Somme des lignes avant remise |
| `discount` | DECIMAL(14,2) | Remise sur la vente |
| `total` | DECIMAL(14,2) | Total à payer |
| `paid` | DECIMAL(14,2) | Montant payé immédiatement |
| `payment_method` | VARCHAR(20) | `cash`, `card`, `transfer`, `credit` |
| `status` | VARCHAR(20) | `paid`, `partial`, `credit` |
| `note` | TEXT NULL | Remarque |
| `created_at` | TIMESTAMP | Date de création |

### 2.2 VenteItem — table `sale_items`

| Champ | Type | Rôle |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK | Identifiant de la ligne |
| `sale_id` | BIGINT, FK → `sales.id` | Vente parente (ON DELETE CASCADE) |
| `product_id` | BIGINT NULL, FK → `products.id` | Produit (ON DELETE SET NULL) |
| `brand` | VARCHAR(120) | Marque au moment de la vente |
| `product_name` | VARCHAR(200) | Désignation au moment de la vente |
| `qty` | DECIMAL(12,2) | Quantité |
| `unit_price` | DECIMAL(14,2) | Prix unitaire |
| `total` | DECIMAL(14,2) | `qty × unit_price` |

> `brand` et `product_name` sont **copiés** dans la ligne : si le produit est
> modifié ou supprimé plus tard, l'historique de la vente reste correct.

### 2.3 Tables liées

- `credits` : 0 ou 1 crédit par vente (`sale_id` unique), avec
  `amount`, `paid`, `remaining`, `due_date`, `status`.
- `payment_allocations` : répartition des versements sur les crédits.
- `clients`, `users`, `products` : référentiels.

---

## 3. Règles de gestion

1. Une vente doit avoir **au moins une ligne** avec `qty > 0`.
2. Pour chaque ligne :
   - `total = round(qty × unit_price, 2)`
3. Pour la vente :
   - `subtotal = somme des totals des lignes`
   - `total = max(0, subtotal − discount)`
   - `remaining = total − paid`
4. Statut :
   - `paid` si `remaining ≤ 0,009`
   - `partial` si `paid > 0` et `remaining > 0`
   - `credit` si `paid = 0` et `remaining > 0`
5. Mode de paiement :
   - `credit` automatiquement si la vente est entièrement à crédit.
6. Si `remaining > 0` :
   - création d'un enregistrement dans `credits`
   - `due_date = date de vente + délai de crédit` (paramètre du commerce).
7. Le montant payé ne peut pas dépasser le total.
8. Une vente avec des versements déjà imputés est **verrouillée** pour
   l'édition des lignes et du client.
9. Suppression d'une vente :
   - suppression des lignes (`sale_items`, cascade),
   - suppression du crédit lié (cascade),
   - les versements déjà reçus sont libérés.

---

## 4. Écrans / parcours

### 4.1 Point de vente — `pos.php`
- Client « Passager » par défaut.
- Recherche/ajout rapide de produits.
- Panier (lignes), quantité, prix, total.
- Montant payé, mode de paiement.
- Validation → ticket.

### 4.2 Nouvelle vente — `sale_new.php`
- Choix du client (recherche dans la liste).
- Date, mode de paiement, remarque.
- **Panier** : sélection d'un produit, quantité et prix, puis
  **« Ajouter au panier »** ; la ligne est ajoutée au tableau.
- Lignes : `Produit (Désignation — Marque)`, `Qté`, `Prix unitaire`, `Total ligne`.
- Chaque ligne peut encore être modifiée ou supprimée.
- Remise, total, montant payé, reste.
- Validation transactionnelle : vente + lignes + crédit éventuel.

### 4.3 Liste des ventes — `sales.php`
- Filtres : recherche, période, client, statut, mode.
- Totaux : ventes, encaissé, vendu à crédit.
- Actions : détail, ticket, modification, suppression.

### 4.4 Détail — `sale.php`
- Informations client / date / mode / statut.
- Tableau des lignes (produit, marque, qté, prix, total).
- Totaux et crédit lié.
- Actions : modifier, imprimer, PDF, ticket.

### 4.5 Modification — `sale_edit.php`
- Éditable si aucun versement n'est imputé.
- Recalcul complet des totaux et du crédit.

### 4.6 Gestion des articles — `sale_items.php`
CRUD complet des `VenteItem` d'une vente :

| Action | Route |
|---|---|
| Liste + formulaire d'ajout | `?p=sale_items&sale_id=1` |
| Modification | `?p=sale_items&sale_id=1&edit=ID` |
| Création (POST) | `action=create` |
| Modification (POST) | `action=update` |
| Suppression (POST) | `action=delete` |

Règles :
- accès administrateur ;
- ajout/modification/suppression interdits si un versement est déjà imputé ;
- une vente doit garder au moins un article ;
- après chaque opération, `recalculate_sale()` met à jour :
  - `subtotal`, `total`, `paid`, `payment_method`, `status` ;
  - le crédit lié (création, mise à jour ou suppression) ;
- pour un client « Passager », `paid = total` automatiquement ;
- si le nouveau total devient inférieur au montant déjà payé, l'opération est refusée.

### 4.7 Ticket / PDF
- `ticket.php` : ticket 80 mm.
- `pdf.php` : document A4 (vente, client, listes).

---

## 5. Structure SQL

```sql
CREATE TABLE `sales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `date` DATE NOT NULL,
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `paid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_date_index` (`date`),
  KEY `sales_client_index` (`client_id`),
  CONSTRAINT `sales_client_fk` FOREIGN KEY (`client_id`)
    REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_user_fk` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `brand` VARCHAR(120) NOT NULL DEFAULT '',
  `product_name` VARCHAR(200) NOT NULL,
  `qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_index` (`sale_id`),
  CONSTRAINT `sale_items_sale_fk` FOREIGN KEY (`sale_id`)
    REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_product_fk` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Pseudo-code (création)

```
BEGIN TRANSACTION
  INSERT INTO sales (client_id, user_id, date, subtotal, discount,
                     total, paid, payment_method, status, note)
  sale_id = lastInsertId()

  POUR CHAQUE ligne:
      INSERT INTO sale_items (sale_id, product_id, brand,
                              product_name, qty, unit_price, total)

  SI remaining > 0:
      INSERT INTO credits (sale_id, client_id, amount, paid,
                           remaining, due_date, status)
COMMIT
```

---

## 7. Évolutions recommandées

- Numéro de facture unique (`invoice_no`).
- Remise ou TVA par ligne dans `sale_items`.
- Prix d'achat / marge (`cost_price`).
- Mouvements de stock (`stock_movements`) et décrémentation automatique.
- Verrouillage anti-double-clic (idempotence).
- Journal d'audit (qui a créé/modifié/supprimé).
