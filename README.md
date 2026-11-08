# Gestion Crédits — PHP natif

Application de gestion commerciale (ventes, crédits, versements, rapports) écrite en **PHP 8 natif**,
sans framework, sans `composer`, sans `npm`, sans étape de build.

Un seul dossier à copier : la base de données, le schéma et le compte administrateur sont créés
automatiquement au premier lancement.

---

## Sommaire

1. [Présentation](#1-présentation)
2. [Prérequis](#2-prérequis)
3. [Installation](#3-installation)
4. [Première connexion et sécurisation](#4-première-connexion-et-sécurisation)
5. [Fonctionnalités](#5-fonctionnalités)
6. [Architecture](#6-architecture)
7. [Configuration](#7-configuration)
8. [Base de données](#8-base-de-données)
9. [Pages et routes](#9-pages-et-routes)
10. [Sécurité](#10-sécurité)
11. [Français / Arabe (RTL)](#11-français--arabe-rtl)
12. [Impression et tickets 80 mm](#12-impression-et-tickets-80-mm)
13. [Export Excel](#13-export-excel)
14. [Rôles et permissions](#14-rôles-et-permissions)
15. [Ajouts rapides (marque, produit, client)](#15-ajouts-rapides-marque-produit-client)
16. [Maintenance et dépannage](#16-maintenance-et-dépannage)
17. [Arborescence complète](#17-arborescence-complète)
18. [Mise à jour / Git](#18-mise-à-jour--git)
19. [Journal des versions](#19-journal-des-versions)

---

## 1. Présentation

| Élément | Valeur |
|---|---|
| Nom | Gestion Crédits |
| Version | 1.1 |
| Langage | PHP 8.1+ (testé PHP 8.3.28) |
| Base de données | SQLite 3 (fichier unique, via PDO) |
| Serveur testé | WAMP 64 bits (Apache 2.4 + PHP 8.3) |
| Dépendances | **aucune** (pas de Composer, pas de npm) |
| Langues | Français, Arabe (RTL) |
| Devise | DH (dirham) — configurable |
| Fuseau horaire | Africa/Casablanca |

L'application est mono-dossier et portable : elle fonctionne à la racine d'un site
(`http://localhost/`) comme dans un sous-dossier (`http://localhost/gestion-credits-php/`).

---

## 2. Prérequis

- PHP **8.1 ou supérieur**
- Extensions PHP activées :
  - `pdo_sqlite` (base de données)
  - `sqlite3` (sauvegardes / outils)
  - `zip` (export Excel `.xlsx`)
  - `mbstring` (accents, arabe)
  - `session`, `json` (toujours présents)
- Apache avec `mod_rewrite` **facultatif** (les URLs fonctionnent sans : `index.php?p=...`)
- Dossier `data/` **inscriptible** par le serveur web

Sous WAMP, ces extensions sont actives par défaut. Vérification rapide :
`http://localhost/?phpinfo=1` → chercher `pdo_sqlite`, `zip`, `mbstring`.

---

## 3. Installation

### 3.1 Sous WAMP (local)

1. Copier le dossier `gestion-credits-php` dans `C:\wamp64\www\`.
2. Démarrer WAMP (icône verte).
3. Ouvrir **http://localhost/gestion-credits-php/**
4. La base `data/gestion.sqlite` est créée automatiquement, avec :
   - l'utilisateur `admin` (mot de passe `admin123`),
   - les paramètres par défaut du commerce.
5. Se connecter, puis changer immédiatement le mot de passe.

### 3.2 Sur un hébergement mutualisé

1. Envoyer tout le dossier par FTP (ou via le gestionnaire de fichiers).
2. Mettre `data/` en chmod **775** (ou 777 si l'hébergeur l'exige).
3. Ouvrir l'URL du dossier dans le navigateur.
4. Vérifier que `.htaccess` est bien pris en compte (Apache). Sur Nginx, voir
   [§16.6](#166-nginx-sans-htaccess).

### 3.3 Réinitialisation complète

Supprimer les fichiers de base puis recharger la page :

```text
data/gestion.sqlite
data/gestion.sqlite-wal
data/gestion.sqlite-shm
```

Le schéma et le compte `admin` / `admin123` sont recréés. **Toutes les données sont perdues** :
faire une sauvegarde avant.

---

## 4. Première connexion et sécurisation

| Champ | Valeur par défaut |
|---|---|
| Utilisateur | `admin` |
| Mot de passe | `admin123` |

À faire dès la première connexion :

1. **Paramètres → Mon compte** : changer le mot de passe.
2. **Paramètres → Informations du commerce** : nom, téléphone, adresse, pied de ticket,
   délai de crédit par défaut (jours).
3. **Utilisateurs** : créer les comptes caissier / gestionnaire, désactiver ceux qui ne servent pas.
4. Dans `inc/config.php`, passer `DEBUG` à `false` en production.

---

## 5. Fonctionnalités

### Clients
- Fiche complète : nom, téléphone, adresse, plafond de crédit.
- Solde dû calculé en temps réel (somme des crédits ouverts).
- Historique : ventes, crédits, versements.
- Filtres : recherche (nom / téléphone), statut, « solde dû > 0 ».
- Désactivation (jamais de suppression définitive).

### Produits
- **Marque + Désignation** : la combinaison est **unique** (insensible à la casse et aux espaces).
- Si un produit identique existe déjà → **création refusée** avec message explicite.
- Si le produit identique existe mais est désactivé → il est **réactivé et mis à jour**.
- Bouton **+** à côté de « Marque » : ajout rapide d'une nouvelle marque (sans quitter le formulaire).
- Prix de vente, description, activation/désactivation.
- Filtres : recherche (marque / désignation / description), statut.

### Ventes
- Panier multi-lignes : produit, quantité, prix unitaire (pré-rempli, modifiable), total ligne.
- Remise globale, montant payé immédiatement, mode de paiement (espèces, carte, virement).
- Statut automatique : `payé`, `partiel`, `crédit`.
- Création automatique du crédit pour le reste à payer, avec échéance = date de vente + délai configuré.
- Ticket 80 mm imprimé automatiquement après validation.
- Filtres : recherche client/téléphone, période, client, statut, mode de paiement.
- Export Excel.

### Crédits
- Une ligne par vente à crédit : montant, payé, reste, échéance, statut.
- Alerte « en retard » quand l'échéance est dépassée.
- Filtres : client, statut, période de création, **retard uniquement**.
- Totaux : encours, retard, historique soldé.

### Versements
- Saisie d'un versement pour un client (montant, date, mode, remarque).
- **Imputation automatique FIFO** sur les crédits ouverts, du plus ancien au plus récent.
- Reçu 80 mm.
- Refus si le montant dépasse le solde dû.
- Filtres : recherche client, période, client, mode de paiement.
- Export Excel.

### Rapports
- Critères : période, client, statut de vente, mode de paiement.
- Indicateurs : chiffre d'affaires, encaissé sur ventes, vendu à crédit, versements reçus,
  panier moyen, encours en retard.
- Meilleurs produits, meilleurs clients, ancienneté des crédits (0-30 / 31-60 / 61-90 / 90+).
- Détail des ventes et des versements de la période.
- **Impression** et **export Excel** du rapport complet.

### Paramètres
- Informations du commerce (affichées sur les tickets).
- Délai de crédit par défaut.
- Changement de mot de passe.
- Sauvegardes : création, téléchargement, suppression.

### Utilisateurs (admin uniquement)
- Création, modification, réinitialisation du mot de passe.
- Rôles : `admin`, `manager`, `cashier`, `accountant`.
- Désactivation (impossible sur son propre compte).

### Divers
- Interface **FR / AR** avec bascule RTL immédiate.
- Thème sombre / clair.
- Toutes les listes et tous les rapports imprimables.
- Export Excel natif (aucune bibliothèque).
- Aucune suppression définitive : `is_active` partout.

---

## 6. Architecture

### 6.1 Principe général

Architecture **front controller** ultra simple :

```text
Navigateur
   │
   ▼
index.php  ──►  inc/config.php   (constantes, session, fuseau, dossiers)
   │             inc/helpers.php  (fonctions utilitaires, auth, CSRF, i18n)
   │             inc/db.php       (PDO SQLite, schéma, migrations, helpers SQL)
   │
   ├─ page publique : pages/login.php
   │
   └─ page protégée : require_login() puis pages/<p>.php
                        │
                        └── layout_start() ... layout_end()  (inc/layout.php)
                              │
                              └── assets/app.css + assets/app.js
```

- **Aucun autoloader** : `index.php` inclut explicitement les 4 fichiers du noyau.
- **Aucune classe métier** : la logique est dans les pages, les fonctions partagées dans `inc/helpers.php`.
- **Aucun moteur de template** : PHP brut avec `<?= e(...) ?>` pour l'échappement.
- **Une page = un fichier** dans `pages/`, sélectionné par `?p=<nom>` (liste blanche implicite :
  le fichier doit exister, sinon 404 → tableau de bord).

### 6.2 Rôle des fichiers

| Fichier | Rôle |
|---|---|
| `index.php` | Routeur : locale, déconnexion, contrôle d'accès, inclusion de la page |
| `inc/config.php` | Constantes (chemins, devise, version), fuseau, session, `BASE`, `DEBUG` |
| `inc/db.php` | Connexion PDO, création du schéma, migrations, jeu de données initial, helpers `q/one/all/scalar/setting` |
| `inc/helpers.php` | Échappement, URLs, redirections, POST/GET, flash, CSRF, auth, rôles, i18n, dates, calculs de crédit, imputation des paiements |
| `inc/lang.php` | Dictionnaires `fr` et `ar` (tableau associatif) |
| `inc/layout.php` | Gabarit HTML (sidebar, topbar, flash), en-tête et bouton d'impression, gabarit de connexion |
| `inc/xlsx.php` | Classe `XlsxWriter` : génération `.xlsx` avec `ZipArchive` |
| `pages/*.php` | Une page = un écran (liste, formulaire, détail, traitement POST) |
| `assets/app.css` | Thème sombre/clair, composants, ticket, RTL, impression |
| `assets/app.js` | Thème, menu mobile, modales, panier de vente, ajouts rapides, recherche locale |
| `data/` | Base SQLite, sauvegardes, exports temporaires (protégé par `.htaccess`) |

### 6.3 Cycle d'une requête (exemple : validation d'une vente)

1. `index.php` → `inc/config.php` (session démarrée, `BASE` calculé).
2. Routage : `?p=sale_new` → fichier `pages/sale_new.php` existant.
3. `require_login()` : redirige vers `login` si la session n'a pas d'utilisateur.
4. La page traite le `POST` :
   - `verify_csrf()` compare `$_POST['_token']` au jeton de session,
   - validation des données,
   - `db()->beginTransaction()`,
   - insertion dans `sales`, `sale_items`, puis `credits` si reste à payer,
   - `commit()`,
   - `flash()` + `redirect('ticket', ...)`.
5. Le ticket s'affiche (document autonome 80 mm) et lance `window.print()`.

### 6.4 Conventions de code

- `declare(strict_types=1)` en tête de chaque fichier PHP.
- Fonctions en `snake_case`, variables en `camelCase` pour les données de page.
- Toute sortie HTML passe par `e()` (`htmlspecialchars`).
- Toute URL passe par `url()` (respecte `BASE`).
- Toute redirection passe par `redirect()` (qui termine le script).
- Toute requête SQL passe par `q()`, `one()`, `all()` ou `scalar()` (requêtes préparées).
- Les montants sont des `REAL` ; arrondis à 2 décimales via `round()`.

---

## 7. Configuration

### 7.1 `inc/config.php` — constantes

| Constante | Valeur par défaut | Description |
|---|---|---|
| `APP_ROOT` | dossier racine | Chemin absolu de l'application |
| `DATA_DIR` | `APP_ROOT/data` | Données |
| `BACKUP_DIR` | `DATA_DIR/backups` | Sauvegardes `.sqlite` |
| `EXPORT_DIR` | `DATA_DIR/exports` | Fichiers Excel temporaires |
| `DB_FILE` | `DATA_DIR/gestion.sqlite` | Fichier de base SQLite |
| `APP_NAME` | `Gestion Crédits` | Nom affiché (remplacé en arabe par `t('app_name')`) |
| `CURRENCY` | `DH` | Devise affichée |
| `VERSION` | `1.1` | Version affichée en bas de la sidebar |
| `BASE` | calculée | URL de base (`''` à la racine, `/gestion-credits-php` en sous-dossier) |
| `DEBUG` | `true` | `false` en production : masque les erreurs PHP |

Le fuseau est fixé par `date_default_timezone_set('Africa/Casablanca')`.
La session utilise `path = BASE . '/'`, `httponly = true`, `samesite = Lax`.

**Passer en production** :

```php
define('DEBUG', false);
```

### 7.2 Paramètres stockés en base (table `settings`)

Modifiables depuis **Paramètres**, sans toucher au code.

| Clé | Défaut | Utilisation |
|---|---|---|
| `shop_name` | `Mon commerce` | En-tête du ticket et des impressions |
| `shop_phone` | *(vide)* | Ticket |
| `shop_address` | *(vide)* | Ticket |
| `shop_footer` | `Merci de votre visite` | Bas du ticket |
| `credit_alert_days` | `30` | Échéance par défaut d'un nouveau crédit (jours) |

Accès en lecture : `setting('shop_name', APP_NAME)` (mise en cache par requête).

### 7.3 Extensions et `php.ini`

Recommandé :

```ini
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=mbstring
date.timezone=Africa/Casablanca
upload_max_filesize=8M
```

L'application n'utilise aucun upload de fichier (les imports n'existent pas).

### 7.4 `.htaccess`

- Racine : `DirectoryIndex index.php`, `Options -Indexes`, UTF-8, réécriture optionnelle
  `/<page>` → `index.php?p=<page>`.
- `data/`, `inc/`, `pages/` : `Require all denied` — ces dossiers ne sont jamais servis
  directement (les pages sont incluses par PHP, pas appelées par URL).

---

## 8. Base de données

### 8.1 Généralités

- Moteur : **SQLite 3**, fichier `data/gestion.sqlite`.
- Accès : **PDO** (`PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION`, `FETCH_ASSOC`).
- Mode journal : **WAL** (`gestion.sqlite-wal`, `gestion.sqlite-shm` à côté du fichier).
- Clés étrangères : activées (`PRAGMA foreign_keys = ON`).
- Création automatique : si le fichier n'existe pas, `install_schema()` puis `seed_data()`.
- Migrations : `migrate()` s'exécute à chaque connexion (idempotent).
- Dates : `TEXT` au format `AAAA-MM-JJ` (dates) ou `AAAA-MM-JJ HH:MM:SS` (`created_at`).

> Le mode WAL crée des fichiers temporaires : lors d'une copie manuelle de la base,
> arrêter Apache (ou utiliser la sauvegarde intégrée) pour éviter une copie partielle.

### 8.2 Schéma relationnel

```text
users ──┐
        │ user_id
clients ─┼──< sales ──< sale_items >── products
        │      │
        │      └──< credits ──< payment_allocations >── payments
        │                                   │
        └───────────────────────────────────┘
                  (client_id sur credits/payments)

brands  : liste des marques (indépendante, alimentée par les produits)
settings: couples clé/valeur
```

### 8.3 Dictionnaire des tables

#### `users` — utilisateurs

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK AUTOINCREMENT | Identifiant |
| `name` | TEXT | NOT NULL | Nom affiché |
| `username` | TEXT | NOT NULL UNIQUE | Identifiant de connexion |
| `password` | TEXT | NOT NULL | Hash `password_hash()` (bcrypt) |
| `role` | TEXT | NOT NULL, défaut `admin` | `admin`, `manager`, `cashier`, `accountant` |
| `is_active` | INTEGER | NOT NULL, défaut 1 | 1 = actif, 0 = désactivé |
| `created_at` | TEXT | défaut `datetime('now')` | Création |

#### `clients` — clients

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `name` | TEXT | NOT NULL | Nom |
| `phone` | TEXT | | Téléphone |
| `address` | TEXT | | Adresse |
| `credit_limit` | REAL | NOT NULL, défaut 0 | Plafond de crédit (informatif) |
| `is_active` | INTEGER | NOT NULL, défaut 1 | Désactivation douce |
| `created_at` | TEXT | défaut `datetime('now')` | Création |

#### `brands` — marques

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `name` | TEXT | NOT NULL, unique insensible à la casse | Nom de la marque |
| `is_active` | INTEGER | NOT NULL, défaut 1 | Réservé |
| `created_at` | TEXT | défaut `datetime('now')` | Création |

Alimentée automatiquement par les marques déjà saisies sur les produits.

#### `products` — produits

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `brand` | TEXT | NOT NULL, défaut `''` | Marque (texte, cohérent avec `brands.name`) |
| `name` | TEXT | NOT NULL | Désignation |
| `description` | TEXT | | Description libre |
| `price` | REAL | NOT NULL, défaut 0 | Prix de vente |
| `is_active` | INTEGER | NOT NULL, défaut 1 | Désactivation douce |
| `created_at` | TEXT | défaut `datetime('now')` | Création |

**Contrainte d'unicité** :

```sql
CREATE UNIQUE INDEX idx_products_unique
    ON products (brand COLLATE NOCASE, name COLLATE NOCASE);
```

Deux produits ne peuvent donc pas partager la même marque **et** la même désignation.
L'application vérifie aussi en amont (pour renvoyer un message clair et réactiver un produit désactivé).

> Anciennes bases : la colonne `stock` peut encore exister ; elle n'est plus utilisée ni affichée.

#### `sales` — ventes

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Numéro de vente |
| `client_id` | INTEGER | NOT NULL → `clients.id` | Client |
| `user_id` | INTEGER | → `users.id` | Caissier |
| `date` | TEXT | NOT NULL | Date de la vente |
| `subtotal` | REAL | NOT NULL | Somme des lignes |
| `discount` | REAL | NOT NULL | Remise |
| `total` | REAL | NOT NULL | `subtotal - discount` |
| `paid` | REAL | NOT NULL | Montant payé immédiatement |
| `payment_method` | TEXT | NOT NULL | `cash`, `card`, `transfer`, `credit` |
| `status` | TEXT | NOT NULL | `paid`, `partial`, `credit` |
| `note` | TEXT | | Remarque |
| `created_at` | TEXT | défaut `datetime('now')` | Horodatage |

#### `sale_items` — lignes de vente

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `sale_id` | INTEGER | NOT NULL → `sales.id` ON DELETE CASCADE | Vente |
| `product_id` | INTEGER | → `products.id` | Produit (peut être nul si supprimé) |
| `product_name` | TEXT | NOT NULL | Copie du libellé « MARQUE — Désignation » |
| `qty` | REAL | NOT NULL, défaut 1 | Quantité |
| `unit_price` | REAL | NOT NULL | Prix unitaire appliqué |
| `total` | REAL | NOT NULL | `qty × unit_price` |

#### `credits` — crédits (reste à payer d'une vente)

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `sale_id` | INTEGER | NOT NULL → `sales.id` | Vente concernée |
| `client_id` | INTEGER | NOT NULL → `clients.id` | Client |
| `amount` | REAL | NOT NULL | Montant initial du crédit |
| `paid` | REAL | NOT NULL, défaut 0 | Cumul des versements imputés |
| `remaining` | REAL | NOT NULL | Reste dû (`amount - paid`) |
| `due_date` | TEXT | | Échéance |
| `status` | TEXT | NOT NULL, défaut `open` | `open`, `paid` |
| `created_at` | TEXT | défaut `datetime('now')` | Création |

#### `payments` — versements

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `client_id` | INTEGER | NOT NULL → `clients.id` | Client |
| `user_id` | INTEGER | → `users.id` | Utilisateur |
| `date` | TEXT | NOT NULL | Date du versement |
| `amount` | REAL | NOT NULL | Montant reçu |
| `method` | TEXT | NOT NULL | `cash`, `card`, `transfer` |
| `note` | TEXT | | Remarque |
| `created_at` | TEXT | défaut `datetime('now')` | Horodatage |

#### `payment_allocations` — imputation des versements

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | INTEGER | PK | Identifiant |
| `payment_id` | INTEGER | NOT NULL → `payments.id` ON DELETE CASCADE | Versement |
| `credit_id` | INTEGER | NOT NULL → `credits.id` | Crédit soldé |
| `amount` | REAL | NOT NULL | Part imputée |

#### `settings` — paramètres

| Colonne | Type | Contraintes |
|---|---|---|
| `key` | TEXT | PK |
| `value` | TEXT | |

### 8.4 Index

| Index | Table | Colonnes |
|---|---|---|
| `idx_products_unique` | `products` | `brand` (NOCASE), `name` (NOCASE) — UNIQUE |
| `idx_brands_unique` | `brands` | `name` (NOCASE) — UNIQUE |
| `idx_sales_date` | `sales` | `date` |
| `idx_sales_client` | `sales` | `client_id` |
| `idx_credits_client` | `credits` | `client_id` |
| `idx_credits_status` | `credits` | `status` |
| `idx_payments_date` | `payments` | `date` |
| `idx_payments_client` | `payments` | `client_id` |

### 8.5 Statuts et conventions

| Table | Champ | Valeurs | Signification |
|---|---|---|---|
| `sales` | `status` | `paid` | Totalement payée |
| | | `partial` | Payée en partie → crédit créé |
| | | `credit` | Aucun paiement → crédit total |
| `credits` | `status` | `open` | Reste dû > 0 |
| | | `paid` | Soldé |
| `users`, `clients`, `products` | `is_active` | `1` / `0` | Actif / désactivé (jamais supprimé) |

### 8.6 Règles métier

**Calcul d'une vente**

```text
subtotal = Σ (qty × unit_price)
total    = max(0, subtotal − discount)
paid     = min(paid_saisi, total)
reste    = total − paid
```

**Statut automatique**

```text
reste ≤ 0,009        → paid
0 < paid < total     → partial  (mode = mode choisi)
paid = 0             → credit   (mode = 'credit')
```

**Création du crédit**

Si `reste > 0` : insertion dans `credits` avec
`amount = reste`, `paid = 0`, `remaining = reste`, `status = 'open'`,
`due_date = date_vente + credit_alert_days`.

**Imputation d'un versement (FIFO)**

`allocate_payment()` parcourt les crédits `open` du client, du plus ancien au plus récent
(`ORDER BY created_at, id`) et impute le montant disponible :

```text
pour chaque crédit :
    part = min(reste_versement, crédit.remaining)
    crédit.paid      += part
    crédit.remaining -= part
    si remaining ≤ 0,009 → status = 'paid'
    enregistrer payment_allocations(payment_id, credit_id, part)
    reste_versement -= part
    si reste_versement = 0 → stop
```

**Solde d'un client**

```sql
SELECT COALESCE(SUM(remaining), 0)
FROM credits
WHERE client_id = ? AND status = 'open';
```

### 8.7 Migrations

`migrate()` est exécutée à **chaque** connexion et ne fait que des opérations idempotentes :

- `CREATE TABLE IF NOT EXISTS brands (...)`
- `CREATE UNIQUE INDEX IF NOT EXISTS idx_brands_unique ...`
- `INSERT OR IGNORE INTO brands (name) SELECT DISTINCT TRIM(brand) FROM products ...`

Pour ajouter une évolution : compléter `migrate()` avec des instructions `IF NOT EXISTS`
ou des tests sur `PRAGMA table_info(...)`. Ne jamais modifier `install_schema()` sans migration
correspondante (les bases existantes ne seraient pas mises à jour).

### 8.8 Sauvegarde, restauration, réinitialisation

**Depuis l'application** : *Paramètres → Sauvegarde de la base → Créer une sauvegarde*.
La copie est faite avec `VACUUM INTO` (copie cohérente même en WAL), stockée dans `data/backups/`,
téléchargeable et supprimable.

**Restauration** :

1. Arrêter Apache (WAMP → *Stop All Services*).
2. Remplacer `data/gestion.sqlite` par le fichier sauvegardé (renommé `gestion.sqlite`).
3. Supprimer `gestion.sqlite-wal` et `gestion.sqlite-shm` s'ils existent.
4. Redémarrer Apache.

**Réinitialisation** : supprimer `gestion.sqlite*` puis recharger l'application.

### 8.9 Requêtes SQL utiles

```sql
-- Solde dû par client
SELECT c.id, c.name, COALESCE(SUM(cr.remaining), 0) AS solde
FROM clients c
LEFT JOIN credits cr ON cr.client_id = c.id AND cr.status = 'open'
GROUP BY c.id ORDER BY solde DESC;

-- Crédits en retard
SELECT cr.*, c.name
FROM credits cr JOIN clients c ON c.id = cr.client_id
WHERE cr.status = 'open' AND cr.due_date < date('now')
ORDER BY cr.due_date;

-- Chiffre d'affaires du mois
SELECT COALESCE(SUM(total), 0) FROM sales
WHERE date BETWEEN date('now', 'start of month') AND date('now');

-- Doublons de produits (ne doit rien retourner grâce à l'index unique)
SELECT brand, name, COUNT(*) FROM products
GROUP BY lower(brand), lower(name) HAVING COUNT(*) > 1;
```

---

## 9. Pages et routes

Toutes les URLs passent par `index.php` :

```text
http://localhost/gestion-credits-php/index.php?p=<page>&<paramètres>
```

| `p=` | Fichier | Accès | Rôle |
|---|---|---|---|
| `login` | `login.php` | public | Connexion |
| `logout` | *(routeur)* | connecté | Déconnexion |
| `dashboard` | `dashboard.php` | connecté | Tableau de bord |
| `clients` | `clients.php` | connecté | Liste / création / édition (`&edit=ID`, `&new=1`) |
| `client` | `client.php` | connecté | Fiche client (`&id=ID`) |
| `products` | `products.php` | connecté | Liste / création / édition (`&edit=ID`) |
| `sales` | `sales.php` | connecté | Liste filtrable |
| `sale_new` | `sale_new.php` | connecté | Nouvelle vente (`&client_id=`) |
| `sale` | `sale.php` | connecté | Détail (`&id=`) |
| `credits` | `credits.php` | connecté | Crédits filtrables |
| `payments` | `payments.php` | connecté | Versements (`&new=1` → redirige) |
| `payment_new` | `payment_new.php` | connecté | Nouveau versement (`&client_id=`) |
| `reports` | `reports.php` | connecté | Rapports |
| `settings` | `settings.php` | connecté | Paramètres / compte / sauvegardes |
| `users` | `users.php` | **admin** | Utilisateurs |
| `ticket` | `ticket.php` | connecté | Ticket 80 mm (`&type=sale\|payment&id=&autoprint=1`) |
| `export` | `export.php` | connecté | Export `.xlsx` (`&type=sales\|credits\|payments\|clients\|products\|report`) |
| `product_quick` | `product_quick.php` | connecté | JSON : ajout rapide produit |
| `client_quick` | `client_quick.php` | connecté | JSON : ajout rapide client |
| `brand_quick` | `brand_quick.php` | connecté | JSON : ajout rapide marque |

Paramètres de filtrage usuels : `q`, `from`, `to`, `status`, `method`, `client_id`, `overdue`.

---

## 10. Sécurité

| Mesure | Détail |
|---|---|
| Mots de passe | `password_hash()` (bcrypt) + `password_verify()` |
| Sessions | PHP natives, cookie `HttpOnly`, `SameSite=Lax`, chemin limité à l'application |
| CSRF | Jeton unique par session (`csrf()`), vérifié par `verify_csrf()` sur **tous** les POST |
| Injections SQL | 100 % requêtes préparées (PDO) via `q()`, `one()`, `all()`, `scalar()` |
| XSS | Toute sortie passe par `e()` (`htmlspecialchars`, UTF-8) |
| Accès aux fichiers sensibles | `.htaccess` : `Require all denied` sur `data/`, `inc/`, `pages/` |
| Suppressions | Aucune suppression définitive de données métier (désactivation seulement) |
| Sauvegardes | Nom de fichier validé par expression régulière (pas de traversée de chemin) |
| Erreurs | `DEBUG = false` en production |

---

## 11. Français / Arabe (RTL)

- Dictionnaires dans `inc/lang.php` : tableaux `fr` et `ar` (mêmes clés).
- `t('clé')` retourne le texte traduit ; si la clé manque, elle est affichée telle quelle.
- La langue est stockée en session (`?lang=ar` / `?lang=fr`).
- `is_rtl()` renvoie `true` en arabe → `<html dir="rtl">` et styles RTL dans `app.css`.
- Les montants et téléphones sont forcés en lecture gauche → droite (`unicode-bidi: isolate`).
- Pour ajouter une langue : dupliquer le tableau dans `inc/lang.php`, ajouter le code dans
  `locale()` (liste blanche) et le bouton de bascule dans `inc/layout.php`.

---

## 12. Impression et tickets 80 mm

**Tickets** (`pages/ticket.php`) : document HTML autonome, largeur ticket, police monospace.

Après validation d'une vente ou d'un versement, la page s'ouvre avec `autoprint=1` :
`window.print()` est déclenché au chargement.

Réglages navigateur recommandés :

| Option | Valeur |
|---|---|
| Destination | imprimante thermique 80 mm |
| Format papier | 80 mm (ou « Reçu ») |
| Marges | Aucune |
| Échelle | 100 % |
| En-têtes / pieds de page | décochés |

**Listes et rapports** : chaque page possède un bouton **Imprimer** (`print_button()`) et un
en-tête d'impression (`print_header()`) qui ajoute nom du commerce, titre et date.
Les règles `@media print` de `app.css` masquent la navigation, les boutons et les filtres, et
élargissent les tableaux.

---

## 13. Export Excel

Généré par `inc/xlsx.php` (`XlsxWriter`, basé sur `ZipArchive`) — aucune bibliothèque externe.
Fichier créé dans `data/exports/`, envoyé au navigateur puis supprimé.

| Type (`?type=`) | Feuilles | Colonnes principales |
|---|---|---|
| `sales` | Ventes, Articles | N°, date, client, total, payé, reste, mode, statut / lignes |
| `credits` | Crédits | N°, date vente, client, montant, payé, reste, échéance, statut |
| `payments` | Versements | N°, date, client, montant, mode, remarque |
| `clients` | Clients | N°, nom, téléphone, adresse, plafond, solde dû, statut |
| `products` | Produits | N°, marque, désignation, description, prix, statut |
| `report` | Synthèse, Ventes, Articles, Crédits, Versements, Clients, Produits | Rapport complet filtrable |

Les colonnes monétaires utilisent le format Excel `#,##0.00`.

---

## 14. Rôles et permissions

| Rôle | Menu | Droits |
|---|---|---|
| `admin` | Tout + Utilisateurs | Administration complète |
| `manager` | Tout sauf Utilisateurs | Gestion commerciale et paramètres |
| `cashier` | Ventes, clients, crédits, versements | Encaissement |
| `accountant` | Consultation, rapports, exports | Lecture |

> Le contrôle d'accès est appliqué à l'entrée (`require_login()`, `require_admin()`).
> Les rôles sont enregistrés dans `users.role` ; les libellés sont traduits (`role_admin`, etc.).

---

## 15. Ajouts rapides (marque, produit, client)

Trois endpoints JSON, appelés en `fetch` par `assets/app.js` :

| Formulaire | Endpoint | Réponse |
|---|---|---|
| `data-quick-form="brand_quick"` | `?p=brand_quick` | `{id, name}` |
| `data-quick-form="product_quick"` | `?p=product_quick` | `{id, name, price}` |
| `data-quick-form="client_quick"` | `?p=client_quick` | `{id, name}` |

Attributs du formulaire :

- `data-target="<id-du-select>"` : sélecteur à compléter après création ;
- `data-value="name"` : utilise `name` comme valeur d'option (cas des marques, où la valeur
  est le nom et non l'identifiant) ;
- `__line-product__` : cible la dernière ligne du panier de vente.

Codes HTTP : `422` champ manquant, `403` jeton CSRF invalide, `409` doublon (produit),
`405` méthode non autorisée.

---

## 16. Maintenance et dépannage

### 16.1 « La base ne s'écrit pas »
Vérifier les droits sur `data/` (et `data/backups/`, `data/exports/`).
Sous Windows/WAMP, le dossier doit être accessible au service Apache.

### 16.2 « database is locked »
Un autre processus tient la base (deux onglets en écriture simultanée, antivirus, sauvegarde).
Fermer les onglets, réessayer. En dernier recours, arrêter Apache et supprimer les fichiers `-wal`/`-shm`
après sauvegarde.

### 16.3 Page blanche
Mettre temporairement `DEBUG` à `true` dans `inc/config.php`, recharger, lire le message.
Vérifier les logs Apache (`C:\wamp64\logs\apache_error.log`).

### 16.4 Erreur d'export Excel
L'extension PHP `zip` doit être activée (`php.ini`), puis redémarrer Apache.

### 16.5 Changer de dossier / d'URL
Rien à faire : `BASE` est calculée à partir de l'URL. Déplacer le dossier et recharger.

### 16.6 Nginx (sans `.htaccess`)
```nginx
location ~ ^/(data|inc|pages)/ { deny all; }
location / { index index.php; }
```

### 16.7 Réinitialiser un mot de passe oublié (admin)
```sql
UPDATE users SET password = '<hash>' WHERE username = 'admin';
```
Générer le hash :
```bash
php -r "echo password_hash('nouveau', PASSWORD_DEFAULT);"
```

---

## 17. Arborescence complète

```text
gestion-credits-php/
├── index.php                  # Routeur (point d'entrée unique)
├── .htaccess                  # URLs propres + charset + options
├── README.md                  # Cette documentation
├── .gitignore
├── assets/
│   ├── app.css                # Thème, composants, ticket, RTL, impression
│   └── app.js                 # Thème, menu, modales, panier, ajouts rapides
├── inc/
│   ├── config.php             # Constantes, session, BASE, DEBUG
│   ├── db.php                 # PDO, schéma, migrations, helpers SQL
│   ├── helpers.php            # Sécurité, auth, i18n, calculs crédits
│   ├── lang.php               # Traductions FR / AR
│   ├── layout.php             # Gabarit HTML + impression
│   ├── xlsx.php               # Générateur Excel natif
│   └── .htaccess              # Accès interdit
├── pages/
│   ├── login.php              # Connexion
│   ├── dashboard.php          # Tableau de bord
│   ├── clients.php            # Liste + formulaire client
│   ├── client.php             # Fiche client
│   ├── products.php           # Liste + formulaire produit (marque unique)
│   ├── sales.php              # Liste des ventes
│   ├── sale_new.php           # Nouvelle vente
│   ├── sale.php               # Détail d'une vente
│   ├── credits.php            # Crédits
│   ├── payments.php           # Versements
│   ├── payment_new.php        # Nouveau versement
│   ├── reports.php            # Rapports
│   ├── settings.php           # Paramètres + sauvegardes
│   ├── users.php              # Utilisateurs (admin)
│   ├── ticket.php             # Ticket / reçu 80 mm
│   ├── export.php             # Export Excel
│   ├── product_quick.php      # JSON produit
│   ├── client_quick.php       # JSON client
│   ├── brand_quick.php        # JSON marque
│   └── .htaccess              # Accès interdit
└── data/
    ├── gestion.sqlite         # Base SQLite (auto-créée)
    ├── gestion.sqlite-wal     # Journal WAL (auto)
    ├── gestion.sqlite-shm     # Index partagé (auto)
    ├── backups/               # Sauvegardes .sqlite
    ├── exports/               # Excel temporaires
    └── .htaccess              # Accès interdit
```

---

## 18. Mise à jour / Git

Le dossier est versionné sur GitHub :

```text
https://github.com/ayoubmahrouj/gestion_credits
```

- Branche `main` : version **PHP natif** (celle-ci).
- Branche `laravel-vue` : ancienne version Laravel + Vue (archive).

Mettre à jour une installation :

1. Sauvegarder `data/gestion.sqlite` depuis **Paramètres**.
2. Remplacer les fichiers de code (`index.php`, `inc/`, `pages/`, `assets/`) — **ne pas**
   supprimer `data/gestion.sqlite`.
3. Recharger : les migrations (`migrate()`) s'appliquent automatiquement.

---

## 19. Journal des versions

### 1.1
- Produits : bouton **+** pour créer une marque directement depuis le formulaire produit.
- Nouvelle table `brands` (liste des marques, unique insensible à la casse).
- Suppression de la gestion de **stock** (champ, colonne, filtres, export, décrément à la vente).
- Tableaux plus lisibles (largeur minimale, colonnes téléphone/montants non coupées, RTL).
- Documentation complète (architecture, configuration, base de données).

### 1.0
- Première version PHP natif : clients, produits, ventes, crédits, versements, rapports,
  tickets 80 mm, export Excel, utilisateurs, sauvegardes, FR/AR.
