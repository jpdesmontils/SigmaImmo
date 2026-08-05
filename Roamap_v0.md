# Roadmap v0 — SigmaImmo

## Objectif

Transformer le MVP actuel en une v0 SaaS freemium simple, robuste et exploitable en production, tout en restant compatible PHP 7.0.33.

## Contraintes validées

- Runtime serveur : PHP 7.0.33 pour le moment.
- Stockage v0 : SQLite.
- Modèle produit : SaaS freemium.
- Compte gratuit : données et analyses partagées, limitées à 10 favoris.
- Compte payant : données privées, sans limite fonctionnelle de favoris.
- SEO : publication de cas d'étude anonymisés issus de biens analysés.
- Architecture cible : monolithe PHP modulaire, rendu serveur pour le SEO, API JSON pour l'app et l'extension Chrome.

## Principes d'architecture

1. Garder une architecture simple : pas de headless pur en v0.
2. Utiliser SQLite comme stockage principal applicatif.
3. Garder les fichiers pour les exports, caches, prompts et gros artefacts générés.
4. Rendre les pages publiques en HTML serveur pour maximiser le SEO.
5. Exposer une API JSON versionnée pour l'extension Chrome et les interactions applicatives.
6. Séparer progressivement contrôleurs, services, repositories, templates et jobs.
7. Éviter toute réécriture massive : migrer par étapes depuis l'existant.

## Structure cible proposée

```txt
/public
  index.php
  assets/

/app
  Controllers/
  Middleware/
  Services/
  Repositories/
  Jobs/
  Llm/
  Seo/
  Views/

/storage
  app.sqlite
  users/
    {user_id}/
      exports/
      cache/
      uploads/
  logs/

/templates
  layouts/
  pages/
  app/
  blog/
  seo/

/migrations
/tests
```

## Étape 1 — Socle SQLite

### Objectif

Remplacer progressivement le stockage JSON principal par SQLite sans casser le MVP.

### Actions

1. Créer `storage/app.sqlite`.
2. Créer un mini système de migrations compatible PHP 7.0.
3. Créer les tables initiales :
   - `users`
   - `sessions`
   - `api_tokens`
   - `properties`
   - `property_tags`
   - `analyses`
   - `analysis_jobs`
   - `llm_generations`
   - `seo_case_studies`
   - `audit_logs`
4. Ajouter un wrapper PDO SQLite unique.
5. Ajouter un script de migration des favoris JSON existants vers SQLite.
6. Garder les anciens fichiers JSON en sauvegarde temporaire jusqu'à validation.

### Critères d'acceptation

- L'application démarre avec une base SQLite vide.
- Les favoris existants peuvent être importés.
- Les lectures principales utilisent SQLite.
- Les écritures critiques utilisent SQLite.
- Les exports JSON restent possibles.

## Étape 2 — Utilisateurs, sessions et plans

### Objectif

Activer le multi-utilisateur avec séparation claire entre gratuit et payant.

### Actions

1. Ajouter inscription, connexion, déconnexion.
2. Stocker les mots de passe avec `password_hash` et vérifier avec `password_verify`.
3. Ajouter des sessions serveur.
4. Ajouter les plans :
   - `free`
   - `paid`
   - `admin`
5. Appliquer les règles produit :
   - gratuit : maximum 10 favoris ; données et analyses marquées comme partageables ;
   - payant : favoris illimités ; données privées par défaut.
6. Ajouter un middleware d'utilisateur courant.
7. Ajouter un middleware de contrôle de plan.
8. Ajouter des logs d'audit pour connexion, création, suppression et analyse.

### Critères d'acceptation

- Un utilisateur peut créer un compte et se connecter.
- Chaque favori est rattaché à un `user_id`.
- Un compte gratuit ne peut pas dépasser 10 favoris.
- Un compte payant peut créer plus de 10 favoris.
- Un utilisateur ne peut pas accéder aux données privées d'un autre utilisateur.

## Étape 3 — API JSON v1

### Objectif

Stabiliser les échanges avec l'application web et l'extension Chrome.

### Actions

1. Créer un préfixe `/api/v1`.
2. Versionner les endpoints principaux :
   - `POST /api/v1/sync`
   - `GET /api/v1/properties`
   - `GET /api/v1/properties/{id}`
   - `POST /api/v1/properties/{id}/tags`
   - `POST /api/v1/properties/{id}/analyses`
   - `GET /api/v1/analyses/{id}`
3. Remplacer la clé API globale par des tokens par utilisateur.
4. Hasher les tokens en base.
5. Limiter CORS aux origines autorisées.
6. Uniformiser les réponses JSON : `data`, `error`, `meta`.
7. Ajouter des erreurs métier explicites : quota atteint, non autorisé, analyse déjà en cours.

### Critères d'acceptation

- L'extension peut synchroniser avec un token utilisateur.
- Les réponses API ont un format stable.
- Le quota gratuit est appliqué côté API.
- Les endpoints existants restent utilisables ou redirigés pendant la migration.

## Étape 4 — Refactor monolithe modulaire

### Objectif

Réduire la dette sans réécrire toute l'application.

### Actions

1. Créer un front controller `public/index.php`.
2. Ajouter un router minimal compatible PHP 7.0.
3. Extraire la logique métier dans des services :
   - `UserService`
   - `PropertyService`
   - `AnalysisService`
   - `PlanService`
   - `LlmService`
   - `SeoCaseStudyService`
4. Extraire l'accès aux données dans des repositories :
   - `UserRepository`
   - `PropertyRepository`
   - `AnalysisRepository`
   - `SeoRepository`
5. Garder les pages existantes fonctionnelles pendant la transition.
6. Supprimer progressivement les accès directs aux fichiers JSON pour les données applicatives.

### Critères d'acceptation

- Les nouveaux développements passent par services et repositories.
- Les scripts existants ne dupliquent plus la logique métier principale.
- Les tests existants restent verts.

## Étape 5 — Pipeline LLM robuste

### Objectif

Fiabiliser les analyses et préparer la génération de contenus SEO.

### Actions

1. Centraliser les appels LLM dans `LlmService`.
2. Versionner les prompts.
3. Stocker chaque génération dans `llm_generations` :
   - prompt utilisé,
   - modèle,
   - entrée,
   - sortie,
   - statut,
   - erreurs,
   - coût estimé si disponible.
4. Utiliser des sorties JSON structurées avec validation serveur.
5. Mettre les analyses en file d'attente dans `analysis_jobs`.
6. Ajouter reprise sur erreur et statut clair : `queued`, `running`, `completed`, `failed`.
7. Séparer analyse privée complète et version publiable anonymisée.

### Critères d'acceptation

- Une analyse peut être relancée proprement.
- Une erreur LLM ne casse pas l'application.
- Les sorties invalides sont refusées et loguées.
- Les analyses sont historisées.

## Étape 6 — Site public et blog SEO

### Objectif

Créer une base d'acquisition organique sans complexité front-end.

### Actions

1. Ajouter un layout public commun.
2. Créer les pages publiques principales :
   - accueil,
   - investissement locatif,
   - marchand de biens,
   - analyse de rentabilité,
   - guides.
3. Ajouter un blog rendu serveur.
4. Ajouter les champs SEO :
   - title,
   - meta description,
   - canonical,
   - slug,
   - statut de publication.
5. Générer `sitemap.xml`.
6. Ajouter `robots.txt`.
7. Ajouter Open Graph et données structurées simples.
8. Relier les guides existants au nouveau layout.

### Critères d'acceptation

- Les pages publiques sont accessibles sans JavaScript obligatoire.
- Chaque page publiée a un title, une description et une URL canonique.
- Le sitemap liste les pages publiées.
- Le blog permet de publier, modifier et dépublier un article.

## Étape 7 — Cas d'étude anonymisés SEO

### Objectif

Publier du contenu différenciant sans exposer de biens ou données sensibles.

### Actions

1. Créer le statut de publication : `private`, `candidate`, `draft`, `published`, `rejected`.
2. Pour les comptes gratuits, marquer les analyses comme partageables par défaut selon les CGU.
3. Pour les comptes payants, garder les analyses privées par défaut.
4. Créer un générateur de cas d'étude anonymisé.
5. Supprimer systématiquement :
   - adresse,
   - lien d'annonce,
   - photos originales,
   - texte source copié,
   - coordonnées GPS,
   - dates trop précises,
   - identifiants externes.
6. Arrondir ou généraliser :
   - prix,
   - surface,
   - loyers,
   - rendement,
   - localisation.
7. Ajouter une validation humaine obligatoire avant publication.
8. Ajouter un score de risque de ré-identification.
9. Publier seulement les cas utiles pédagogiquement.

### Critères d'acceptation

- Aucun cas d'étude n'est publié automatiquement.
- Un cas publié ne permet pas de retrouver directement l'annonce source.
- Les fiches payantes ne sont jamais candidates SEO sans accord explicite.
- Les contenus publiés sont éditorialisés et non dupliqués.

## Étape 8 — Freemium et conversion

### Objectif

Rendre la différence gratuit/payant claire et actionnable.

### Actions

1. Afficher le quota gratuit : `x / 10 favoris`.
2. Bloquer l'ajout du 11e favori avec un message clair.
3. Ajouter une page de comparaison des plans.
4. Prévoir l'état `paid` même si le paiement réel est manuel au début.
5. Ajouter des appels à conversion :
   - favoris illimités,
   - données privées,
   - analyses privées,
   - exports avancés.
6. Ajouter un panneau admin simple pour passer un utilisateur en payant.

### Critères d'acceptation

- Le quota gratuit est compréhensible.
- Le blocage du quota n'est pas destructif.
- Le passage manuel en payant fonctionne.
- Les données payantes sont privées par défaut.

## Étape 9 — Sécurité et conformité minimale

### Objectif

Rendre la v0 exploitable sans risque évident.

### Actions

1. Ajouter CSRF sur les formulaires.
2. Ajouter cookies `HttpOnly`, `Secure` en production et `SameSite`.
3. Limiter CORS.
4. Valider tous les inputs serveur.
5. Ajouter rate limiting simple sur login et API.
6. Hasher les tokens API.
7. Ajouter une page de suppression de compte.
8. Ajouter export des données utilisateur.
9. Rédiger CGU, confidentialité et règles de partage des comptes gratuits.
10. Sauvegarder SQLite régulièrement.

### Critères d'acceptation

- Les endpoints privés nécessitent une session ou un token valide.
- Les tokens ne sont pas stockés en clair.
- Les utilisateurs gratuits sont informés du partage de leurs analyses.
- Une sauvegarde restaurable existe.

## Étape 10 — Tests et observabilité

### Objectif

Sécuriser les évolutions v0.

### Actions

1. Ajouter tests unitaires sur services critiques.
2. Ajouter tests API sur sync, quota, auth et analyses.
3. Ajouter tests de migration JSON vers SQLite.
4. Ajouter tests d'anonymisation.
5. Ajouter logs applicatifs structurés.
6. Ajouter une page admin de suivi des jobs.
7. Ajouter scripts de backup et restore SQLite.

### Critères d'acceptation

- Les tests existants restent verts.
- Les quotas sont couverts par tests.
- Les règles d'anonymisation sont testées.
- Une analyse échouée est visible et compréhensible.

## Ordre d'exécution recommandé

1. SQLite et migrations.
2. Utilisateurs, sessions, plans.
3. API v1 et tokens utilisateur.
4. Migration favoris vers SQLite.
5. Quota gratuit et confidentialité payante.
6. Refactor services/repositories.
7. Pipeline LLM robuste.
8. Site public SEO.
9. Blog.
10. Cas d'étude anonymisés.
11. Freemium et conversion.
12. Sécurité, conformité et backups.
13. Tests et observabilité.

## Décisions à ne pas prendre en v0

- Ne pas construire de microservices.
- Ne pas faire de SPA publique.
- Ne pas publier automatiquement des fiches anonymisées.
- Ne pas gérer des organisations ou équipes complexes.
- Ne pas ajouter une facturation complète tant que le modèle payant peut être validé manuellement.
- Ne pas remplacer tout l'existant d'un coup.

## Définition de terminé pour la v0

La v0 est prête quand :

- un utilisateur peut créer un compte ;
- un utilisateur gratuit peut gérer jusqu'à 10 favoris ;
- un utilisateur payant peut gérer des favoris privés sans limite ;
- l'extension synchronise avec un token utilisateur ;
- les annonces et analyses sont stockées dans SQLite ;
- les analyses LLM sont historisées et relançables ;
- le site public, le blog et le sitemap sont disponibles ;
- des cas d'étude anonymisés peuvent être préparés puis publiés après validation humaine ;
- les principales règles de sécurité sont en place ;
- une sauvegarde SQLite restaurable existe.
