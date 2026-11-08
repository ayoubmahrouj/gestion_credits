# Gestion Crédits — PHP natif

Application de gestion commerciale (ventes, crédits, versements, rapports) en **PHP natif** :
aucun framework, aucun `composer`, aucun `npm`. Elle tourne directement sous WAMP / XAMPP / Laragon
ou n'importe quel hébergement PHP 8.1+ avec SQLite.

> Cette version remplace l'ancienne version Laravel + Vue. Elle est 100 % autonome : un seul dossier à copier.

---

## 1. Fonctionnalités

- **Tableau de bord** : ventes du jour / du mois, crédits en cours, encaissements, principaux débiteurs.
- **Clients** : fiche complète, plafond de crédit, solde dû, historique ventes / crédits / versements.
- **Produits** : marque + désignation, **unicité marque + désignation** (pas de doublon), prix, stock, activation/désactivation.
- **Ventes** : panier multi-lignes, remise, paiement partiel ou total, création automatique du crédit, ticket 80 mm.
- **Crédits** : suivi des restes à payer, échéances, retards, filtres et totaux.
- **Versements** : imputation automatique sur les crédits ouverts (du plus ancien au plus récent), reçu 80 mm.
- **Rapports** : période, client, statut, mode de paiement ; chiffre d'affaires, encaissé, crédit, versements,
  meilleurs produits, meilleurs clients, ancienneté des crédits — **tout est imprimable**.
- **Export Excel (.xlsx)** : ventes, articles, crédits, versements, clients, produits, rapport complet.
- **Utilisateurs** : rôles (admin, gestionnaire, caissier, comptable), activation/désactivation, mots de passe.
- **Paramètres** : infos du commerce (ticket), mot de passe, sauvegardes de la base.
- **FR / AR** avec bascule RTL instantanée.
- **Aucune suppression définitive** : clients, produits et utilisateurs sont seulement désactivés.

---

## 2. Installation sous WAMP

1. Copier le dossier `gestion-credits-php` dans `C:\wamp64\www\`.
2. Démarrer WAMP (Apache + PHP 8.3 ou supérieur, extensions `pdo_sqlite`, `sqlite3`, `zip`, `mbstring`).
3. Ouvrir : **http://localhost/gestion-credits-php/**
4. Se connecter :
   - utilisateur : `admin`
   - mot de passe : `admin123`
5. **Changer immédiatement le mot de passe** dans *Paramètres → Mon compte*.
6. Renseigner le nom du commerce dans *Paramètres → Informations du commerce* (il apparaît sur les tickets).

La base de données est créée automatiquement au premier lancement dans `data/gestion.sqlite`.

### Installation sur un autre serveur

- Uploader tout le dossier (FTP ou panneau d'hébergement).
- S'assurer que le dossier `data/` est **inscriptible** par PHP (chmod 775 ou 777 selon l'hébergeur).
- Ouvrir l'URL du dossier dans le navigateur. C'est tout.

### Changer le dossier / l'URL

L'application détecte automatiquement son URL de base. Elle fonctionne aussi bien à la racine
(`http://localhost/`) que dans un sous-dossier (`http://localhost/gestion-credits-php/`).

---

## 3. Connexion par défaut

| Champ | Valeur |
|---|---|
| Utilisateur | `admin` |
| Mot de passe | `admin123` |

> À modifier dès la première connexion.

---

## 4. Arborescence

```text
gestion-credits-php/
├── index.php                  # Point d'entrée unique (routeur)
├── .htaccess                  # URLs propres + protection
├── README.md
├── assets/
│   ├── app.css                # Thème sombre/clair, impression, RTL
│   └── app.js                 # Thème, modales, panier de vente, ajout rapide
├── inc/
│   ├── config.php             # Constantes, session, URL de base, fuseau horaire
│   ├── db.php                 # PDO SQLite, schéma, données initiales, requêtes
│   ├── helpers.php            # Échappement, URLs, CSRF, auth, i18n, crédits
│   ├── lang.php               # Traductions FR / AR
│   ├── layout.php             # Gabarit HTML (sidebar, topbar, impression)
│   └── xlsx.php               # Générateur XLSX sans dépendance (ZipArchive)
├── pages/
│   ├── login.php              # Connexion
│   ├── dashboard.php          # Tableau de bord
│   ├── clients.php            # Liste + création/édition + filtres
│   ├── client.php             # Fiche client (ventes, crédits, versements)
│   ├── products.php           # Produits (marque + désignation uniques)
│   ├── sales.php              # Liste des ventes + filtres + export
│   ├── sale_new.php           # Nouvelle vente (panier)
│   ├── sale.php               # Détail d'une vente
│   ├── credits.php            # Crédits + filtres (statut, retard, période)
│   ├── payments.php           # Versements + filtres + export
│   ├── payment_new.php        # Nouveau versement
│   ├── reports.php            # Rapports (critères + impression)
│   ├── settings.php           # Paramètres, compte, sauvegardes
│   ├── users.php              # Utilisateurs (admin)
│   ├── ticket.php             # Ticket / reçu 80 mm
│   ├── export.php             # Export Excel (.xlsx)
│   ├── product_quick.php      # Ajout rapide produit (JSON)
│   └── client_quick.php       # Ajout rapide client (JSON)
└── data/
    ├── gestion.sqlite         # Base de données (créée automatiquement)
    ├── backups/               # Sauvegardes .sqlite
    ├── exports/               # Fichiers Excel temporaires
    └── .htaccess              # Interdit l'accès web à la base
```

---

## 5. Impression du ticket 80 mm

1. Ouvrir une vente puis **Ticket**, ou valider une vente (le ticket s'ouvre et s'imprime automatiquement).
2. Dans la fenêtre d'impression du navigateur :
   - Format papier : **80 mm** (ou « Reçu » / « Rouleau »),
   - Marges : **aucune**,
   - Échelle : **100 %**,
   - Décocher « en-têtes et pieds de page ».
3. Le bouton **Imprimer** relance l'impression sans réimprimer l'interface.

Toutes les listes et tous les rapports disposent d'un bouton **Imprimer** : l'en-tête du commerce
et la date sont ajoutés automatiquement, la navigation est masquée.

---

## 6. Export Excel

Chaque liste (ventes, crédits, versements, clients, produits) et le rapport complet disposent
d'un bouton **Exporter Excel**. Le fichier `.xlsx` est généré à la volée (aucune bibliothèque requise)
et s'ouvre dans Excel, LibreOffice ou Google Sheets.

---

## 7. Sauvegardes

- *Paramètres → Sauvegarde de la base* → **Créer une sauvegarde**.
- Les fichiers sont stockés dans `data/backups/` et téléchargeables depuis la même page.
- Pour restaurer : arrêter Apache, remplacer `data/gestion.sqlite` par la sauvegarde, redémarrer.

Sauvegardez régulièrement, surtout avant une mise à jour.

---

## 8. Sécurité

- Mots de passe hachés (`password_hash` / `bcrypt`).
- Sessions PHP + jeton CSRF sur tous les formulaires.
- Accès à `data/`, `inc/` et `pages/` interdit par `.htaccess`.
- Toutes les requêtes SQL sont préparées (PDO).
- Toutes les sorties HTML sont échappées.

En production : désactiver l'affichage des erreurs en mettant `DEBUG` à `false` dans `inc/config.php`.

---

## 9. Rôles

| Rôle | Accès |
|---|---|
| `admin` | Tout, y compris la gestion des utilisateurs |
| `manager` | Ventes, clients, produits, crédits, versements, rapports, paramètres |
| `cashier` | Ventes, clients, crédits, versements |
| `accountant` | Consultation, rapports, exports |

Seul l'administrateur voit le menu **Utilisateurs**.

---

## 10. Notes techniques

- PHP ≥ 8.1 (testé sous PHP 8.3.28 / Apache 2.4, WAMP 64 bits).
- SQLite 3 (fichier unique, aucun serveur à installer).
- Aucune dépendance externe, aucun build : modifier un fichier = recharger la page.
- Les dates sont stockées au format `AAAA-MM-JJ`, affichées au format `JJ/MM/AAAA`.
- Montants en dirhams (`DH`), modifiables dans `inc/config.php` (`CURRENCY`).
