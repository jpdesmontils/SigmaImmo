# SigmaImmo — v0 SQLite

SigmaImmo est un MVP PHP monolithique qui agrège des favoris immobiliers, affiche les annonces côté serveur/statique et expose des APIs JSON pour l'application et l'extension Chrome.

Cette version expose exclusivement l'API JSON v1 sur SQLite. Les routes sont versionnées, authentifiées par token utilisateur et limitées par une liste blanche.

## Prérequis

- PHP 7.0.33 compatible CLI et serveur web.
- Extension PHP PDO SQLite activée (`pdo_sqlite`).
- Un serveur HTTP capable de servir le dossier du projet ou le routeur PHP intégré pour un lancement local.
- Une clé OpenAI uniquement si vous lancez les analyses IA.

## Installation

Depuis la racine du projet :

```bash
cd /chemin/vers/SigmaImmo
cp api/.env.example api/.env
```

Éditez ensuite `api/.env` :

```bash
export IMMO_API_KEY='change-me'
export OPENAI_API_KEY='sk-...'
export OPENAI_MODEL='gpt-5.3-chat-latest'
export PERPLEXITY_API_KEY=''
export SIGMAIMMO_SQLITE_PATH=''
```

> SQLite n'utilise pas de login/mot de passe : l'accès dépend du chemin du fichier et des permissions système.

## Configuration SQLite

Par défaut, la base applicative est créée dans :

```txt
storage/app.sqlite
```

Le chemin peut être remplacé avec la variable d'environnement `SIGMAIMMO_SQLITE_PATH`. Laissez cette variable vide pour utiliser le chemin par défaut du code.

Exemples :

```bash
# Valeur vide : utilise storage/app.sqlite à la racine du projet.
export SIGMAIMMO_SQLITE_PATH=''

# Chemin absolu recommandé en production.
export SIGMAIMMO_SQLITE_PATH='/var/www/sigmaimmo/storage/app.sqlite'
```

Le fichier SQLite runtime n'est pas versionné par Git. Le schéma est versionné dans `migrations/`.

## Initialiser la base

Lancez les migrations :

```bash
php scripts/migrate.php
```

Ce script crée le fichier SQLite si nécessaire et applique les migrations non encore exécutées.

## Importer un export JSON existant

Si un export JSON historique existe, importez-le dans SQLite en indiquant explicitement son chemin :

```bash
php scripts/import_properties_json.php /chemin/vers/export.json
```

Le script crée une sauvegarde du fichier JSON d'origine avec un suffixe `.bak-YYYYMMDDHHMMSS`.
L'ancien script `scripts/import_favorites_json.php` reste disponible comme alias de migration, mais il exige également un chemin explicite et ne pointe plus par défaut vers `data/favorites.json`.

## Lancement local

Pour un lancement local simple avec le serveur PHP intégré :

```bash
php -S 127.0.0.1:8000 router.php
```

Puis ouvrez la landing page publique :

```txt
http://127.0.0.1:8000/
```

L'API est disponible sous `/api/v1`, par exemple :

```txt
http://127.0.0.1:8000/api/v1/properties
```

## Lancement avec variables d'environnement

Pour les scripts CLI et workers, chargez le fichier d'environnement avant d'exécuter les commandes :

```bash
set -a
. api/.env
set +a
php scripts/migrate.php
```

En production, préférez définir les variables dans la configuration du service PHP/FPM, du virtual host ou du process manager, plutôt que de dépendre d'un shell interactif.

## Données et fichiers générés

- `storage/app.sqlite` : base SQLite applicative runtime.
- `storage/*.sqlite-wal`, `storage/*.sqlite-shm`, `storage/*.sqlite-journal` : fichiers internes SQLite ignorés par Git.
- `data/analyses/` : sources historiques à importer avant activation de l'API v1 ; elles ne sont jamais lues par les nouveaux endpoints ou workers.
- `storage/logs/` et `log/` : logs runtime selon les scripts existants.

## Vérification rapide

```bash
php scripts/migrate.php
php scripts/import_properties_json.php /chemin/vers/export.json
php scripts/import_analyses_sqlite.php /chemin/vers/data/analyses
php -l app/Database/Connection.php app/Database/Migrator.php app/Database/bootstrap.php app/Repositories/PropertyRepository.php scripts/migrate.php scripts/import_properties_json.php
```

## Notes de production v0

- Donnez au user système du serveur web les droits de lecture/écriture sur `storage/` et `data/`.
- Utilisez un chemin absolu pour `SIGMAIMMO_SQLITE_PATH` en production.
- Sauvegardez régulièrement `storage/app.sqlite` et les artefacts utiles dans `data/`.
- Ne commitez jamais `api/.env`, `storage/app.sqlite` ni les fichiers SQLite WAL/journal.

## API JSON v1

`api/v1/routes_wl.json` est l'unique liste blanche des routes. Une table absente de cette configuration n'est pas exposée. Le processeur générique `cAPI_Processor` fournit `GET`, `POST`, `PUT`, `PATCH` et `DELETE`; les synchronisations, tags, analyses et quotas utilisent des sous-classes spécialisées.

Toutes les réponses utilisent les clés `data`, `error` et `meta`. Les biens `shared` sont lisibles par tous les utilisateurs authentifiés, mais seul leur propriétaire peut les modifier. Les anciens scripts `/api/*.php` répondent désormais avec HTTP 410 via Apache ou `router.php`.

Avant le déploiement, importez obligatoirement les analyses historiques :

```bash
php scripts/import_analyses_sqlite.php data/analyses
```

Une ligne déjà présente dans SQLite reste prioritaire. Après contrôle du résultat, archivez les fichiers sources hors du répertoire applicatif.

Créez un token pour un utilisateur existant depuis la page `/auth/account.php`, depuis `POST /api/v1/tokens` avec une session PHP valide, ou en CLI :

```bash
php scripts/create_api_token.php utilisateur@example.test "Extension Chrome"
```

Le secret n'est affiché qu'une fois et seul son SHA-256 est stocké. Utilisez-le ainsi :

```bash
curl -H 'Authorization: Bearer VOTRE_TOKEN' http://127.0.0.1:8000/api/v1/properties
```

Les origines autorisées sont déclarées dans `api/v1/CORS.json`. Remplacez impérativement `chrome-extension://REMPLACER_PAR_ID_EXTENSION` par l'identifiant publié de l'extension avant le déploiement.
