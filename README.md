# Redirects Checker

> **EN** -- Detect redirect chains, loops, self-redirects and HTTP errors (404, 5xx) in a bulk list of 301 redirections, with automatic chain flattening and CSV export.

---

## Description

**Redirects Checker** est un outil d'audit SEO qui analyse une liste de redirections 301 pour detecter automatiquement les problemes courants : chaines de redirections (A -> B -> C), boucles infinies (A -> B -> A), auto-redirections (A -> A) et erreurs HTTP (404, 5xx, timeouts). L'outil propose des corrections automatiques en aplatissant les chaines pour pointer directement vers la destination finale.

L'utilisateur soumet ses redirections via un textarea (copier-coller) ou un fichier CSV/TSV. L'outil effectue d'abord une analyse statique du graphe de redirections (detection des chaines, boucles et auto-redirections), puis lance optionnellement un worker en arriere-plan qui crawle les URLs sources en HTTP HEAD concurrent pour verifier les codes de reponse reels. La progression du crawl est affichee en temps reel via un systeme de polling AJAX avec logs en streaming.

Les resultats sont presentes dans une interface a onglets avec KPI synthetiques, tableau des problemes filtrable/triable, liste complete des redirections et export CSV. L'outil supporte l'internationalisation (francais/anglais) et s'integre a la plateforme SEO en mode embedded.

---

## Fonctionnalites

- **Detection des chaines de redirections** : identifie les chaines de 2+ sauts et propose l'aplatissement automatique vers la destination finale
- **Detection des boucles** : algorithme de coloration du graphe (blanc/gris/noir) pour reperer les cycles
- **Detection des auto-redirections** : signale les URLs qui redirigent vers elles-memes
- **Verification HTTP en arriere-plan** : crawl concurrent des URLs sources via Guzzle Pool (HEAD avec fallback GET pour les 403/405)
- **Protection SSRF** : validation des URLs avec blocage des IP privees et des ports non standards
- **Progression en temps reel** : polling AJAX avec logs JSON Lines (JSONL) affiches dans un terminal de streaming
- **Options avancees du crawler** : concurrence, delai entre requetes, timeout, max redirections suivies, User-Agent configurable (Googlebot, Bingbot, Chrome, custom), header HTTP custom
- **Sauvegarde des configurations** : persistance des presets de crawler (jusqu'a 50 par utilisateur), avec chargement et suppression
- **Historique des analyses** : conservation des jobs pendant 72h avec pagination, nettoyage automatique
- **Edition inline des corrections** : clic sur la destination corrigee pour ajuster manuellement
- **Application en masse** : bouton "Appliquer toutes les corrections" pour accepter les aplatissements proposes
- **Export CSV** : deux modes d'export (corrections avec destinations aplaties, ou liste des problemes)
- **Export JSON** : telechargement complet des donnees brutes via `download.php`
- **Filtres et recherche** : filtrage par type de probleme (chaines, boucles, 404, erreurs HTTP, auto-redirections, redirections inattendues) et recherche textuelle
- **Tri des colonnes** : tri ascendant/descendant sur les colonnes source
- **Pagination cote client** : pagination des tableaux pour les listes volumineuses
- **Internationalisation** : interface disponible en francais et anglais (systeme `data-i18n` + `translations.js`)
- **Copie en un clic** : clic sur une URL pour la copier dans le presse-papiers avec toast de confirmation
- **Detection des redirections inattendues** : signale quand la destination reelle ne correspond pas a la destination declaree

---

## Prerequis

- **PHP >= 8.3** (typed class constants, `str_starts_with`, `str_contains`)
- **Composer** pour l'installation des dependances
- Extension PHP `curl` (requise par Guzzle)
- Permissions d'ecriture sur le repertoire `data/` (creation automatique)

---

## Installation

```bash
cd /home/aymeric/projects/redirects-checker/
composer install
```

Aucune variable d'environnement requise (`env_keys` est vide dans `module.json`).

---

## Utilisation

### Developpement local (standalone)

```bash
cd /home/aymeric/projects/redirects-checker/
php -S localhost:8080
```

Ouvrir `http://localhost:8080` dans le navigateur.

### Etape 1 — Importer les redirections

Deux modes d'acquisition sont disponibles :

- **Onglet "Coller le texte"** — Coller directement la liste des redirections dans le textarea. Une redirection par ligne, avec source et destination separees par une tabulation, un point-virgule ou une virgule. Le separateur est auto-detecte.
- **Onglet "Importer un fichier"** — Importer un fichier CSV, TSV ou TXT avec 2 colonnes (source et destination). **Taille maximale : 5 Mo** (~100 000 redirections). Formats acceptes : `.csv`, `.tsv`, `.txt`.

Exemples de formats valides :

```
/ancienne-page	/nouvelle-page
/ancien-produit;/nouveau-produit
https://example.com/old,https://example.com/new
```

### Etape 2 — Configurer les options (optionnel)

- **Separateur** — Par defaut en auto-detection. Forcer manuellement si necessaire (tabulation, point-virgule, virgule).
- **Verifier les 404 (HTTP)** — Cocher cette option pour lancer un crawl HTTP des URLs sources. Le worker verifie les codes de reponse reels (301, 404, 500, etc.) et compare la destination reelle avec la destination declaree.

#### Options avancees du crawler

Accessibles en cliquant sur "Options avancees du crawler" :

| Option | Defaut | Description |
|--------|--------|-------------|
| Requetes concurrentes | 2 | Nombre de requetes HTTP simultanees (1 a 10) |
| Delai entre requetes | 100 ms | Pause entre chaque requete (0 a 2 s) |
| Timeout par requete | 5 s | Delai maximal d'attente (3 a 30 s) |
| Max redirections suivies | 3 | Nombre de sauts suivis (0 a 5) |
| User-Agent | Personnalise | Googlebot, Googlebot Mobile, Bingbot, Chrome Desktop, ou custom |
| Header HTTP custom | — | Header supplementaire (ex: `X-Custom-Header: valeur`) |

#### Sauvegarder une configuration

Les presets du crawler sont persistants. Saisir un nom dans le champ "Nom de la config" et cliquer sur **Sauvegarder**. Jusqu'a 50 configurations par utilisateur. Les charger ou les supprimer via le menu deroulant "Configs sauvegardees".

### Etape 3 — Lancer l'analyse

Cliquer sur **Analyser les redirections**. Si des URLs relatives sont detectees avec la verification HTTP activee, une modale demande le domaine a prefixer (ex: `https://www.example.com`).

L'analyse se deroule en deux phases :

1. **Analyse statique du graphe** — Instantanee. Detection des chaines, boucles et auto-redirections par analyse du graphe de redirections.
2. **Verification HTTP** (si activee) — Le worker crawle les URLs sources en arriere-plan. La progression s'affiche en temps reel avec un terminal de logs et une barre de progression.

### Etape 4 — Lire les resultats

#### KPI synthetiques

Quatre indicateurs en haut de page :

- **Redirections 3xx** — Nombre de sources qui retournent effectivement un code 3xx
- **Chaines** — Nombre de chaines detectees (2+ sauts)
- **Erreurs HTTP** — Nombre de sources en erreur (404, 5xx, timeout)
- **Corrections** — Nombre de destinations corrigees manuellement

#### Onglet "Problemes"

Tableau filtrable par type de probleme :

| Filtre | Description |
|--------|-------------|
| Chaines | Redirections avec 2+ sauts (A → B → C) |
| Boucles | Cycles dans le graphe (A → B → A) |
| Auto-redir | URL qui redirige vers elle-meme |
| 404 | Source qui retourne un 404 |
| Erreur HTTP | Source en erreur (5xx, timeout, connexion impossible) |
| Redir. inattendue | La destination reelle ne correspond pas a la destination declaree |
| Pas de redir. | La source repond 200 sans rediriger |

Chaque probleme affiche la source, la destination actuelle, le detail du probleme et une correction proposee (editable en cliquant dessus).

#### Onglet "Liste complete"

Tableau de toutes les redirections avec les colonnes :

| Colonne | Description |
|---------|-------------|
| Source | URL source de la redirection |
| Destination | URL de destination declaree (+ URL finale reelle si differente) |
| Statut | OK, Chaine, Boucle, Auto, 404, Redirect inattendue, Pas de redir. |
| HTTP Src | Code HTTP retourne par la source (301, 302, 200, 404, etc.) |
| HTTP Dest | Code HTTP retourne par la destination finale (200, 404, 500, etc.) |

Triable par colonne source, avec recherche textuelle et pagination.

### Etape 5 — Corriger et exporter

- **Edition inline** — Cliquer sur une destination corrigee pour la modifier manuellement.
- **Appliquer toutes les corrections** — Bouton pour accepter toutes les corrections automatiques (aplatissement des chaines).
- **Export CSV corrections** — Telecharge un CSV avec les sources, destinations originales, destinations corrigees et raisons.
- **Export CSV problemes** — Telecharge un CSV avec les types de problemes, sources, destinations et details.
- **Export JSON** — Telecharge les donnees brutes completes au format JSON.

### Historique

L'onglet **Historique** liste les analyses precedentes (conservees 72h). Chaque entree affiche la date, le domaine, le nombre d'URLs, le statut du crawl et un lien pour revoir les resultats.

### Limites

| Limite | Valeur |
|--------|--------|
| Taille max fichier | 5 Mo |
| Memoire process.php | 256 Mo |
| Memoire worker.php | 256 Mo |
| Retention historique | 72 heures |
| Configs sauvegardees | 50 par utilisateur |

### Lancer les tests

```bash
composer test
# ou directement :
vendor/bin/pest
```

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.3 |
| Client HTTP | Guzzle 7 (Pool concurrent, HEAD + fallback GET) |
| Frontend | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Poppins |
| JavaScript | Vanilla JS (IIFE), aucun framework |
| Tests | Pest 3 |
| Stockage | Fichiers JSON sur disque (`data/jobs/`) |
| i18n | `translations.js` avec systeme `data-i18n` |

---

## Structure du projet

```
redirects-checker/
|-- module.json              # Manifeste du plugin (slug, routes, quota, etc.)
|-- boot.php                 # Autoloader Composer + autoload PSR-0 pour src/
|-- index.php                # Page d'accueil (formulaire + historique)
|-- process.php              # Endpoint POST : parsing, analyse du graphe, lancement du worker
|-- worker.php               # Worker CLI en arriere-plan : crawl HTTP concurrent
|-- progress.php             # Endpoint GET : progression du worker + logs JSONL
|-- results.php              # Page de resultats (KPI, tableaux, exports)
|-- download.php             # Export CSV/JSON des resultats
|-- configs.php              # CRUD des configurations de crawler sauvegardees
|-- historique.php           # Endpoint GET : liste des jobs existants
|-- app.js                   # Logique JS complete (formulaire, polling, tableaux, export, i18n)
|-- translations.js          # Traductions FR/EN
|-- styles.css               # Charte graphique (variables CSS, composants)
|-- composer.json            # Dependances PHP (Guzzle, Pest)
|-- phpunit.xml              # Configuration PHPUnit/Pest
|-- .gitignore               # Exclusions (vendor, data, .env, logs)
|-- src/
|   |-- AnalyseurRedirections.php  # Parsing du texte/CSV, detection du separateur, prefixage domaine
|   |-- GrapheRedirections.php     # Analyse du graphe : chaines, boucles, auto-redirections, aplatissement
|   |-- GestionnaireJobs.php       # Gestion des jobs sur disque (CRUD, progression, nettoyage 72h)
|   |-- VerificateurHttp.php       # Crawl HTTP concurrent via Guzzle Pool (HEAD/GET, SSRF protection)
|-- tests/
|   |-- Pest.php                   # Bootstrap Pest
|   |-- Unit/
|   |   |-- AnalyseurRedirectionsTest.php
|   |   |-- GrapheRedirectionsTest.php
|   |   |-- GestionnaireJobsTest.php
|   |-- Feature/                   # (repertoire present, tests a venir)
|-- data/
|   |-- jobs/                      # Stockage des jobs (analyse.json, config.json, progress.json, resultats.json, logs.jsonl)
|   |-- configs/                   # Configurations de crawler sauvegardees par utilisateur
```

---

## Routes (module.json)

| Methode | Chemin | Type | Description |
|---------|--------|------|-------------|
| `POST` | `process.php` | `ajax` | Soumission du formulaire : parsing, analyse du graphe, creation du job, lancement du worker |
| `GET` | `results.php` | `page` | Page de resultats (rendue dans le layout plateforme) |
| `GET` | `progress.php` | `ajax` | Progression du worker + logs temps reel (polling) |
| `GET` | `download.php` | `ajax` | Export CSV ou JSON des resultats (`?type=corrige\|problemes&format=csv\|json`) |
| `GET` | `configs.php` | `ajax` | Lister les configurations sauvegardees |
| `POST` | `configs.php` | `ajax` | Sauvegarder une configuration de crawler |
| `DELETE` | `configs.php` | `ajax` | Supprimer une configuration sauvegardee |
| `GET` | `historique.php` | `ajax` | Lister tous les jobs existants |

---

## Integration plateforme

### Mode d'affichage

Le plugin fonctionne en mode **embedded** (`display_mode: "embedded"`). La plateforme extrait le contenu du `<body>`, recrit les chemins CSS/JS, supprime la navbar et injecte le CSRF automatiquement. Le plugin reste fonctionnel en standalone sans modification.

### Quota

- **Mode** : `form_submit` (incremente sur POST du formulaire)
- **Quota par defaut** : 100 analyses/mois
- Le worker verifie egalement le quota via `Platform\Module\Quota::trackerSiDisponible()` dans `process.php`
- Gestion du code 429 cote client avec message d'erreur

### Internationalisation

- Langues supportees : `fr`, `en`
- La langue est determinee par `window.PLATFORM_LANG` (plateforme) ou par le parametre `?lg=` / `localStorage` (standalone)
- Selecteur de langue affiche uniquement en mode standalone (masque quand `PLATFORM_EMBEDDED` est defini)

### Routage AJAX

Tous les appels `fetch()` utilisent `window.MODULE_BASE_URL || '.'` comme prefixe pour garantir la resolution correcte des URLs en mode embedded.

### Constantes plateforme utilisees

- `PLATFORM_EMBEDDED` : ajuste les redirections PHP (erreur inline vs `header('Location')`) et masque le selecteur de langue
- `$_SESSION['user_id']` : isole les configurations sauvegardees par utilisateur en mode plateforme

### Variables d'environnement

Aucune (`env_keys: []`). Le plugin ne necessite aucune cle API externe.
