# Déploiement — Gestion Crédits

Guide pas à pas pour mettre l'application en ligne sur un VPS Ubuntu avec
**Docker Compose** (PHP-FPM + Caddy pour le HTTPS automatique).

> Résultat : `https://votre-domaine.com` avec certificat Let's Encrypt renouvelé
> automatiquement, l'API sur `/api/v1`, les sauvegardes quotidiennes et la PWA
> installable sur téléphone.

---

## 1. Prérequis

| Élément | Minimum |
|---|---|
| VPS | Ubuntu 22.04 / 24.04 — 2 vCPU, 2 Go RAM, 20 Go SSD |
| Domaine | un enregistrement DNS **A** → IP du VPS (ex. `credits.mondomaine.com`) |
| Ports | 22 (SSH), 80 et 443 ouverts |

Vérifier que le domaine pointe bien vers le serveur :

```bash
dig +short credits.mondomaine.com
```

---

## 2. Installer Docker

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
newgrp docker

docker --version
docker compose version
```

---

## 3. Copier le projet sur le serveur

Depuis votre PC (PowerShell) :

```powershell
# Remplacez user et l'IP
scp -r "D:\gestion-credits" user@IP_DU_VPS:/home/user/gestion-credits
```

Ou avec Git si le projet est dans un dépôt :

```bash
git clone <url-du-depot> ~/gestion-credits
```

> `node_modules/`, `vendor/`, `dist/` ne sont pas nécessaires sur le serveur :
> ils sont reconstruits dans les images Docker.

---

## 4. Configurer

```bash
cd ~/gestion-credits

# 1) Domaine + email Let's Encrypt (utilisés par Docker Compose)
cp .env.example .env
nano .env

# 2) Configuration Laravel de production
cp backend/.env.production.example backend/.env
nano backend/.env
```

Dans `backend/.env`, renseigner :

```ini
APP_KEY=base64:...        # voir ci-dessous
APP_URL=https://credits.mondomaine.com
```

Générer la clé :

```bash
openssl rand -base64 32
```

Copier le résultat en préfixant par `base64:` :

```ini
APP_KEY=base64:LE_RESULTAT_DE_OPENSSL
```

> Le fichier `backend/.env` reste **sur le serveur**, il n'est jamais inclus
> dans l'image Docker.

---

## 5. Lancer l'application

```bash
docker compose up -d --build
```

Au premier démarrage :

1. les images PHP et Caddy sont construites (2–4 min) ;
2. les migrations sont exécutées automatiquement ;
3. si la base est neuve, le compte **admin / admin123** est créé ;
4. Caddy obtient le certificat HTTPS (30–60 s).

Vérifier :

```bash
docker compose ps
docker compose logs -f web
docker compose logs -f app
```

Ouvrir ensuite `https://credits.mondomaine.com`.

> 💾 **Conserver vos données actuelles** : la base SQLite est montée depuis
> `backend/database/database.sqlite`. Si vous copiez tout le dossier du projet
> sur le serveur, vos clients, ventes et crédits existants sont conservés et
> aucun ré-initialisation n'a lieu (le seed ne s'exécute que si la base est
> absente). Pour repartir de zéro, supprimez ce fichier avant le premier
> démarrage.

> ⚠️ **Première connexion** : allez immédiatement dans
> **Paramètres → Mon compte** pour changer le mot de passe `admin123`.

---

## 6. Données et sauvegardes

| Quoi | Où (sur le serveur) |
|---|---|
| Base SQLite | `backend/database/database.sqlite` |
| Sauvegardes créées par l'app | `backend/storage/app/backups/` |
| Logs Laravel | `backend/storage/logs/` |

- Un service `scheduler` exécute `php artisan schedule:run` chaque minute.
- **Chaque nuit à 02:00**, une sauvegarde est créée automatiquement
  (les **14** dernières sont conservées).
- Depuis **Paramètres → Sauvegarde de la base** : créer, télécharger ou
  supprimer une sauvegarde à la main.

### Copier les sauvegardes hors du serveur (recommandé)

```bash
# Exemple : rapatrier les sauvegardes vers votre PC
scp -r user@IP_DU_VPS:~/gestion-credits/backend/storage/app/backups ./sauvegardes
```

### Restaurer une sauvegarde

```bash
cd ~/gestion-credits
docker compose stop app scheduler

cp backend/storage/app/backups/backup-AAAA-MM-JJ_HHMMSS.sqlite \
   backend/database/database.sqlite

docker compose start app scheduler
```

---

## 7. Mise à jour de l'application

```bash
cd ~/gestion-credits
git pull                     # ou rsync depuis votre PC
docker compose up -d --build
```

- Les migrations sont rejouées automatiquement au redémarrage.
- Si vous modifiez `backend/.env`, redémarrez pour reconstruire le cache :

```bash
docker compose restart app scheduler
```

---

## 8. Sécurité du serveur

```bash
# Pare-feu
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Connexion SSH par clé uniquement (recommandé)
nano /etc/ssh/sshd_config   # PasswordAuthentication no
sudo systemctl restart ssh
```

- Changer `admin123` dès la première connexion.
- Créer un compte par employé (rôles : gestionnaire, caissier, comptable).
- Télécharger régulièrement les sauvegardes hors du VPS.
- Ne jamais mettre `APP_DEBUG=true` en production.

---

## 9. PWA (application sur téléphone)

L'application est installable (manifest + service worker + HTTPS) :

- **Android / Chrome** : menu ⋮ → « Installer l'application ».
- **iPhone / Safari** : Partager → « Sur l'écran d'accueil ».

Elle s'ouvre alors en plein écran. Les données restent synchronisées avec le
serveur (l'API n'est jamais mise en cache, seuls les fichiers de l'interface
le sont pour un démarrage rapide).

---

## 10. Dépannage

| Symptôme | Piste |
|---|---|
| Page 502 / 503 | `docker compose ps` puis `docker compose logs app` |
| Pas de certificat HTTPS | DNS pas encore propagé, ports 80/443 fermés, `docker compose logs web` |
| Erreur 500 | `backend/storage/logs/laravel.log` ; vérifier `APP_KEY` et les droits |
| « No application encryption key » | `APP_KEY` vide → voir étape 4 |
| Mot de passe oublié | `docker compose exec app php artisan tinker` puis réinitialiser via l'admin, ou restaurer une sauvegarde |
| Modifs `.env` ignorées | `docker compose restart app scheduler` (le cache de config est reconstruit au démarrage) |

---

## 11. Alternative sans Docker (Nginx + PHP-FPM + Certbot)

Pour un serveur plus classique :

```bash
sudo apt install nginx php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-zip php8.3-bcmath
cd /var/www/gestion-credits/backend
composer install --no-dev --optimize-autoloader
cp .env.production.example .env && nano .env   # APP_KEY, APP_URL, DB_DATABASE absolu
php artisan migrate --force && php artisan db:seed --force   # uniquement si base neuve
php artisan config:cache && php artisan route:cache

cd ../frontend
npm ci && npm run build
sudo cp -r dist/* /var/www/gestion-credits/public/
```

Nginx (extrait) :

```nginx
server {
    listen 80;
    server_name credits.mondomaine.com;
    root /var/www/gestion-credits/public;
    index index.html;

    location /api/ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/gestion-credits/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    location / {
        try_files $uri /index.html;
    }
}
```

Puis :

```bash
sudo certbot --nginx -d credits.mondomaine.com
```

Et ajouter au cron :

```cron
* * * * * cd /var/www/gestion-credits/backend && php artisan schedule:run >> /dev/null 2>&1
```
