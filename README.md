# SigmaImmo — v0 SQLite

SigmaImmo est un MVP PHP monolithique qui agrège des favoris immobiliers, affiche les annonces côté serveur/statique et expose des APIs JSON pour l'application et l'extension Chrome.

Cette version introduit un socle SQLite progressif : les lectures et écritures critiques des favoris passent par SQLite, tandis que les fichiers JSON existants restent conservés comme export/backup temporaire pendant la migration.

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

## Lancement local

Pour un lancement local simple avec le serveur PHP intégré :

```bash
php -S 127.0.0.1:8000
```

Puis ouvrez :

```txt
http://127.0.0.1:8000/app.html
```

Les endpoints API restent disponibles sous `/api`, par exemple :

```txt
http://127.0.0.1:8000/api/listings.php
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
- `data/analyses/` : résultats d'analyses et jobs encore stockés sous forme de fichiers pendant cette étape.
- `storage/logs/` et `log/` : logs runtime selon les scripts existants.

## Vérification rapide

```bash
php scripts/migrate.php
php scripts/import_properties_json.php /chemin/vers/export.json
php -l app/Database/Connection.php app/Database/Migrator.php app/Database/bootstrap.php app/Repositories/PropertyRepository.php scripts/migrate.php scripts/import_properties_json.php
```

## Notes de production v0

- Donnez au user système du serveur web les droits de lecture/écriture sur `storage/` et `data/`.
- Utilisez un chemin absolu pour `SIGMAIMMO_SQLITE_PATH` en production.
- Sauvegardez régulièrement `storage/app.sqlite` et les artefacts utiles dans `data/`.
- Ne commitez jamais `api/.env`, `storage/app.sqlite` ni les fichiers SQLite WAL/journal.
