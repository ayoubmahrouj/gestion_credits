# Gestion Crédits — Suivi des crédits clients

Application de gestion des **crédits clients** pour un commerce de pièces auto / pneus / batteries.
Architecture **MVC + API REST**, interface **FR / AR (RTL)**, design premium sombre/clair.

---

## 1. Stack

| Couche | Technologie |
|---|---|
| Backend | Laravel 13 · PHP 8.4 · API REST `/api/v1` |
| Auth | Laravel Sanctum (tokens) |
| Frontend | Vue 3 + TypeScript + Vite |
| Style | TailwindCSS 4 · design system maison (noir encre / or) |
| i18n | vue-i18n (français + arabe, RTL automatique) |
| Base | SQLite (dev) · MySQL 8 / MariaDB (production) |

---

## 2. Démarrage

### Option A — script (Windows)
Double-cliquer sur **`demarrer.bat`** : il lance l'API + le frontend et ouvre le navigateur.

### Option B — manuel
```powershell
# Terminal 1 — API
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — Interface
cd frontend
npm run dev
```

- Interface : **http://localhost:5173** (ou http://127.0.0.1:5173)
- API : **http://localhost:8000/api/v1**

### Option C — WAMP (aucune fenêtre à laisser ouverte, recommandé au quotidien)

L'application est installée dans WAMP et servie par Apache :

| Élément | Emplacement |
|---|---|
| Interface (build) | `C:\wamp64\www\gestion-credits` |
| API (point d'entrée) | `C:\wamp64\www\gestion-credits-api` (pointe vers `backend/`) |
| URL | **http://localhost/gestion-credits/** |
| Raccourci | icône **« Gestion Credits »** sur le Bureau |

- WAMP démarre avec Windows (services `wampapache64` / `wampmariadb64`) : rien à lancer à la main.
- Après une modification du frontend : double-cliquer sur **`maj-wamp.bat`** (rebuild + copie vers WAMP).
- L'API utilise le PHP de WAMP (8.3) : `composer.json` déclare `config.platform.php = 8.3.28` pour rester compatible.

### ⚠️ « localhost refused to connect »
Cela signifie simplement que **les deux serveurs ne tournent pas**.
Relancer avec `demarrer.bat` (ou les deux commandes ci-dessus), ou utiliser WAMP (option C)
qui démarre tout seul avec Windows.

### Connexion
```
Identifiant : admin
Password    : admin123
```
> ⚠️ Changer le mot de passe avant toute mise en ligne.

---

## 3. Base de données

La base est **vide** : aucun client, produit, vente ou crédit de démonstration.
Seul le compte administrateur est créé.

Commandes utiles (dans `backend/`) :

```powershell
php artisan migrate:fresh --seed       # remet la base à zéro (garde l'admin)
php artisan db:seed --class=DemoSeeder # (optionnel) recharge des données d'exemple
php artisan credits:refresh            # recalcule les retards + statuts
php artisan migrate                    # applique les nouvelles migrations
```

### Ordre de saisie conseillé
0. **Paramètres** → créer vos **marques** (Bosch, Michelin…) et **catégories** (Pneus, Batteries…),
   et renseigner vos **informations boutique** (nom, téléphone, ICE) pour les tickets
1. **Produits** (référence, prix d'achat/vente, stock)
2. **Clients** (limite de crédit, téléphone/WhatsApp, ICE)
3. **Ventes** → type « Crédit » génère automatiquement le crédit + l'échéancier
4. **Encaissements** → affectation automatique **FIFO** (plus ancienne échéance d'abord)

---

## 4. Structure du projet

```
gestion-credits/
├─ demarrer.bat            # lancement rapide Windows
├─ docker-compose.yml      # production : app + scheduler + web (Caddy)
├─ DEPLOIEMENT.md          # guide VPS pas à pas
├─ backend/                # Laravel (API)
│  ├─ app/
│  │  ├─ Http/Controllers/Api/V1/   # Auth, Clients, Products, Sales, Credits, Payments, Dashboard, Reports, Exports, Settings, Reminders, Meta
│  │  ├─ Http/Requests/             # validation
│  │  ├─ Http/Resources/            # formatage JSON
│  │  ├─ Models/                    # Client, Product, Sale, Credit, Installment, Payment…
│  │  ├─ Support/XlsxWriter.php     # générateur Excel (sans dépendance)
│  │  └─ Services/                  # logique métier (le « cerveau »)
│  │     ├─ SaleService.php         # vente + stock + crédit
│  │     ├─ CreditService.php       # limite, échéancier, statuts, retards
│  │     ├─ PaymentService.php      # versements + affectation FIFO
│  │     ├─ CreditScoreService.php  # score de paiement 0–100
│  │     ├─ ReportService.php       # statistiques, séries, filtres
│  │     ├─ ExportService.php       # classeur Excel multi-feuilles
│  │     └─ DashboardService.php    # KPI, aging, DSO, top débiteurs
│  ├─ database/migrations/          # 15 tables
│  ├─ Dockerfile                    # PHP 8.4-FPM + extensions
│  ├─ docker/                       # entrypoint.sh + php.ini
│  └─ routes/api.php
└─ frontend/               # Vue 3 + TS
   ├─ Dockerfile                    # build Vite + Caddy
   ├─ Caddyfile                     # SPA + /api en FastCGI
   ├─ public/                       # manifest.webmanifest, sw.js, icônes PWA
   └─ src/
      ├─ views/            # Dashboard, Reports, Clients, ClientDetail, Credits, Payments, Sales, Products, Settings, Login, Ticket (80 mm)
      ├─ layouts/          # Sidebar + Topbar
      ├─ components/       # ui/ (card, button, modal, badge, stat) + charts/
      ├─ stores/           # auth, ui (thème, langue, toast)
      └─ i18n/             # fr.json / ar.json
```

---

## 5. API REST

Toutes les routes (sauf login) exigent `Authorization: Bearer <token>`.

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/v1/auth/login` | Connexion → token |
| GET | `/api/v1/auth/me` | Utilisateur courant |
| POST | `/api/v1/auth/logout` | Déconnexion |
| PATCH | `/api/v1/auth/password` | Changer son propre mot de passe |
| GET/POST/PUT/DELETE | `/api/v1/users` | Utilisateurs (admin uniquement) — DELETE = désactivation |
| PATCH | `/api/v1/users/{id}/password` | Réinitialiser un mot de passe (admin) |
| PATCH | `/api/v1/users/{id}/restore` | Réactiver un utilisateur |
| GET/POST | `/api/v1/backups` | Sauvegardes de la base (admin) |
| GET/DELETE | `/api/v1/backups/{file}` | Télécharger / supprimer une sauvegarde |
| GET | `/api/v1/dashboard` | KPI + aging + top débiteurs + série mensuelle |
| GET | `/api/v1/reports/aging` | Balance âgée seule |
| GET | `/api/v1/reports/summary` | Statistiques complètes (filtres période/client/marque/catégorie/type/mode/statut) |
| GET | `/api/v1/exports/excel` | Classeur Excel (8 feuilles) — mêmes filtres + `scope` |
| GET/PUT | `/api/v1/settings` | Informations boutique (ticket 80 mm) |
| GET | `/api/v1/meta` | Marques, catégories, méthodes de paiement |
| GET/POST/PUT/DELETE | `/api/v1/clients` | Clients (DELETE = désactivation) |
| GET | `/api/v1/clients/{id}/statement` | Relevé de compte (période) |
| PATCH | `/api/v1/clients/{id}/restore` | Réactiver un client désactivé |
| GET/POST/PUT/DELETE | `/api/v1/products` | Produits (DELETE = désactivation) |
| PATCH | `/api/v1/products/{id}/restore` | Réactiver un produit |
| GET/POST/PUT/DELETE | `/api/v1/brands` | Marques (DELETE = désactivation) |
| PATCH | `/api/v1/brands/{id}/restore` | Réactiver une marque |
| GET/POST/PUT/DELETE | `/api/v1/categories` | Catégories (DELETE = désactivation) |
| PATCH | `/api/v1/categories/{id}/restore` | Réactiver une catégorie |
| GET/POST | `/api/v1/sales` | Ventes (comptant / crédit) |
| GET | `/api/v1/credits` | Crédits (`?overdue=1` pour les retards) |
| GET | `/api/v1/credits/{id}` | Crédit + échéances + paiements |
| GET/POST | `/api/v1/payments` | Encaissements (en-tête `Idempotency-Key`) |
| GET | `/api/v1/payments/{id}` | Reçu de paiement (ticket 80 mm) |
| GET/POST | `/api/v1/reminders` | Journal des relances |

### Exemple — vente à crédit en 3 échéances
```json
POST /api/v1/sales
{
  "client_id": 1,
  "type": "credit",
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 3, "quantity": 1 }
  ],
  "installments": { "count": 3, "first_due_date": "2026-12-01", "interval_days": 30 }
}
```

---

## 6. Règles métier

### Créer / Lire / Modifier / Désactiver (pas de suppression)
- Chaque fiche (client, produit, marque, catégorie) est **modifiable** (bouton crayon) :
  les données historiques restent liées (ventes, crédits, paiements).
- Le bouton « désactiver » (icône interdite) **ne supprime rien** : il passe
  `is_active = false`. L'élément disparaît des listes, reste en base, et peut être
  **réactivé** à tout moment (icône flèche, case « Afficher les désactivés »).
- Un client avec un **solde impayé** ne peut pas être désactivé (message explicite).
- Un produit désactivé **ne peut plus être vendu** ; un client désactivé ne peut plus
  prendre de crédit.
- Les documents comptables (ventes, crédits, paiements) ne sont jamais supprimés.

### Création rapide « + » dans les formulaires
- À côté de chaque liste déroulante (marque, catégorie, client, produit) un bouton
  **+** permet de créer l'élément sans quitter le formulaire ; il est ensuite
  automatiquement sélectionné.
- Exemple : dans *Nouveau produit*, le **+** à côté de « Marque » ouvre une petite
  fenêtre « Nouvelle marque » ; dans *Nouvelle vente*, le **+** à côté du produit
  ouvre le formulaire produit complet, puis le produit créé est ajouté à la ligne
  avec son prix de vente.

### Modification directe dans le tableau (inline)
- Dans **Produits** et **Clients**, un clic sur une cellule l'ouvre en édition :
  *Produits* → désignation FR, désignation AR, marque, stock, prix de vente ;
  *Clients* → nom, téléphone, limite de crédit.
- `Entrée` enregistre, `Échap` annule, quitter la cellule enregistre aussi.
  Le bouton crayon reste disponible pour le formulaire complet.

### Formulaire produit simplifié
- À la création : **Marque**, **Désignation FR/AR** (zones de texte) et
  **Description (optionnel)**.
- La référence (SKU) est générée automatiquement (`PRD-00001`, `PRD-00002`…).
- Les champs **prix, stock, catégorie** sont repliés dans « Prix & stock (optionnel) ».
- Un produit sans prix s'ajoute quand même à une vente : le prix se saisit sur la
  ligne de vente. Un produit avec un stock à 0 n'est pas bloqué à la vente
  (stock non suivi) ; le stock n'est décrémenté que s'il est géré.

- **Limite de crédit** : bloquée si `encours + montant > limite` ou client `blocked`.
- **Échéancier** : répartition égale, dernier échéance absorbe les arrondis (calcul en centimes).
- **Paiement partiel** : autorisé ; affectation FIFO sur les échéances les plus anciennes.
- **Trop-perçu** : devient une avance (solde client négatif).
- **Score 0–100** : +25 max paiements à temps, −8 par échéance en retard, −0,5/jour de retard,
  −10 si dépassement de limite, +5 si client > 6 mois.
- **Aging** : À échoir / 1–30 / 31–60 / 61–90 / +90 jours.
- **DSO** : `encours ÷ ventes 90 j × 90`.
- **Sans riba** : aucun intérêt ni pénalité d'intérêt → suspension de crédit / frais fixes.

Tâche planifiée (`routes/console.php`) : chaque nuit à 00:05, marque les échéances dépassées.
En production, ajouter le cron :
```
* * * * * cd /chemin/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Utilisateurs & sauvegardes

### Comptes et rôles
- Rôles : `admin`, `manager`, `cashier`, `accountant` (colonne `users.role`).
- Seul un **administrateur** voit la page **Utilisateurs** (`/users`) et peut créer,
  modifier, désactiver ou réinitialiser un mot de passe.
- Sécurité : impossible de désactiver son propre compte ou de retirer le dernier
  administrateur actif ; la désactivation révoque immédiatement les tokens de l'utilisateur.
- Chacun peut changer son mot de passe dans **Paramètres → Mon compte**
  (`PATCH /api/v1/auth/password`) : vérification de l'ancien mot de passe et
  révocation des autres sessions.

### Sauvegarde de la base
- **Paramètres → Sauvegarde de la base** : création à la demande, téléchargement, suppression.
- Copie cohérente via `VACUUM INTO` (SQLite), stockée dans `storage/app/backups`.
- Tâche planifiée quotidienne à **02:00** (`db:backup`), les **14** dernières sont conservées.
- En production : ajouter `* * * * * php artisan schedule:run` au cron.

---

## 8. Ticket 80 mm, Export Excel & Rapports

### Ticket de caisse 80 mm
- Chaque vente et chaque encaissement a un bouton **imprimante** dans la liste.
- Après création d'une vente / d'un paiement, une fenêtre propose directement
  **« Imprimer »**.
- Le ticket s'ouvre dans un onglet dédié (`/print/sale/{id}` ou `/print/payment/{id}`),
  au format **80 mm** (imprimante thermique de caisse) : en-tête boutique, articles,
  totaux, reste à payer, échéances du crédit, pied de ticket.
- Le reçu de paiement affiche le montant reçu, le mode, l'ancien et le nouveau solde.
- L'impression est réglée via `@page { size: 80mm auto }` ; choisir **80 mm** (ou
  « Reçu ») dans la boîte de dialogue, marges « aucune / minimum », et désactiver
  « en-têtes et pieds de page » pour un rendu parfait.

### Informations boutique
**Paramètres → Informations boutique** : nom, slogan, téléphone, adresse, ICE,
pied de ticket. Ces informations apparaissent en haut du ticket (la devise `DH`
sert de référence).

### Export Excel
- Bouton **« Exporter Excel »** sur *Clients*, *Produits*, *Ventes*, *Encaissements*
  et *Rapports*.
- Le classeur contient : **Résumé** (période, CA, encaissements, créances, marge,
  stock…), **Clients**, **Produits**, **Ventes**, **Lignes de vente**,
  **Encaissements**, **Crédits**, **Échéances**.
- Depuis *Rapports*, l'export reprend **les filtres actifs** (période, client,
  marque, catégorie, type, mode de paiement).
- Format `.xlsx` réel (aucune dépendance externe), montants au format `#,##0.00`,
  en-têtes gelés et largeurs automatiques.

### Rapports & statistiques
Page **Rapports** (menu latéral) — analyse avec tous les critères :
- **Période** : aujourd'hui, hier, 7 j, 30 j, ce mois, mois dernier, trimestre,
  année, ou dates personnalisées.
- **Filtres** : client, marque, catégorie, type de vente (comptant/crédit),
  mode de paiement, statut.
- **Indicateurs** : chiffre d'affaires, nombre de ventes, panier moyen, encaissements,
  créances, retards, taux de recouvrement, marge estimée (prix d'achat actuel),
  remises, valeur du stock.
- **Graphiques** : CA et encaissements par jour (ou par mois si la période dépasse
  62 jours), répartition comptant/crédit, modes de paiement.
- **Classements** : meilleurs produits (quantité, CA, marge), meilleurs clients
  (CA, solde), par marque, par catégorie, par vendeur.
- **Stock bas** et **balance âgée**.

---

## 9. Thème / personnalisation

- Couleurs : `frontend/src/style.css` (`--gold`, `--bg`, `--surface`, `--ink`…)
- Nom du commerce : **Paramètres → Informations boutique** (tickets) ; libellé de
  l'application : `frontend/src/i18n/fr.json` + `ar.json` → `app.name`, `app.tagline`
- Titre onglet : `frontend/index.html`
- Thème sombre / clair : bouton lune/soleil en haut à droite (mémorisé)
- Langue FR / AR : bouton globe (RTL automatique)

---

## 10. Déploiement & PWA

- **Guide complet** : [`DEPLOIEMENT.md`](./DEPLOIEMENT.md) (VPS Ubuntu + Docker).
- `docker-compose.yml` : `app` (PHP 8.4-FPM), `scheduler` (tâches planifiées),
  `web` (Caddy : SPA + HTTPS Let's Encrypt automatique).
- Configuration : `backend/.env.production.example` + `.env.example` (domaine).
- Données persistantes montées depuis `backend/database` et `backend/storage`.
- **PWA** : `manifest.webmanifest` + `sw.js` + icônes — application installable
  sur Android/iPhone (HTTPS requis), coquille mise en cache, API jamais cachée.

```bash
cp .env.example .env && cp backend/.env.production.example backend/.env
# renseigner DOMAIN, ACME_EMAIL, APP_KEY, APP_URL
docker compose up -d --build
```

---

## 11. Prochaines étapes (roadmap)

- [x] Gestion des utilisateurs + changement de mot de passe (Paramètres)
- [ ] Facture PDF FR/AR (A4)
- [ ] Relevé de compte PDF téléchargeable
- [ ] Relances WhatsApp Cloud API / SMS (jobs planifiés)
- [ ] Journal d'audit (qui a modifié quoi)
- [ ] Sessions de caisse + dépenses
- [x] Ticket thermique 80 mm (vente + reçu de paiement)
- [x] Export Excel complet
- [x] Rapports & statistiques multi-critères
- [x] Sauvegarde automatique de la base
- [x] Déploiement VPS (Docker + Caddy / HTTPS automatique) + PWA
