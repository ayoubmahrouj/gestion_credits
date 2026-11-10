# Déploiement — Gestion Crédits (PHP natif + MySQL)

## Accès

- Local : http://localhost/gestion-credits/
- Réseau : http://192.168.2.16/gestion-credits/
- Identifiant : `admin` / `admin123` (à changer immédiatement dans **Paramètres → Mon compte**)

## Base de données

- Moteur : MySQL / MariaDB (XAMPP), port 3306
- Base : **`gestion_credits_php`** (base dédiée à cette application PHP)
- Utilisateur : `root`, mot de passe vide (configuration XAMPP par défaut)
- La base `gestion_credits` (schéma Laravel) n'est **pas** utilisée par cette application et n'a pas été modifiée.

Configuration : `inc/config.php`

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gestion_credits_php');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DEBUG', false);
```

## Scripts SQL

- `database/schema_mysql.sql` : crée la base et les 10 tables (schéma natif).
- `database/copie_depuis_gestion_credits.sql` : recopie les données depuis la base `gestion_credits` (lecture seule).
- `database/migration_brand_sale_items.sql` : ajoute la marque (`sale_items.brand`) sur une base déjà installée.

Import :

```powershell
Get-Content database\schema_mysql.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root
Get-Content database\copie_depuis_gestion_credits.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root
```

Les fichiers `.sql` et le dossier `database/` sont bloqués par `.htaccess` (HTTP 403).

## Sauvegarde

- Depuis l'application : **Paramètres → Sauvegarde → Créer** (fichier `.sql` dans `data/backups/`).
- Restauration manuelle : `mysql -u root gestion_credits_php < fichier.sql`.

## Sécurité

- `DEBUG = false` (aucune erreur PHP affichée).
- Dossiers `inc/`, `pages/`, `data/`, `database/` interdits en HTTP.
- Fichiers cachés, `.sql`, `.log`, `.md`, `.json`, etc. interdits en HTTP.
- Accès limité au réseau local : ne pas exposer Apache sur Internet.
- Recommandé : changer `admin123` et définir un mot de passe pour MySQL `root`.

## Remarques

- L'ancien fichier `data/gestion.sqlite` (version SQLite) n'est plus utilisé ; il peut être conservé comme archive.
- L'ancien dossier `C:\xampp\htdocs\gestion_credits` n'a pas été modifié.
