# Fiche bien unifiée — recommandations et maquettes

> Document de cadrage UX. Il ne constitue pas une implémentation fonctionnelle.

## 1. Décision produit

La galerie devient un point d’entrée vers une **fiche de bien unique**, ouverte dans un nouvel onglet. Cette fiche rassemble cinq espaces de premier niveau : **Annonce**, **Prix**, **Patrimoine**, **Locatif** et **Marchand de biens** (`MDB` sous 640 px). Prix présente les mutations DVF comparables et une synthèse de négociation indépendante des analyses enregistrées.

Le dernier onglet consulté est mémorisé globalement pour l’utilisateur. À défaut de préférence enregistrée, la fiche s’ouvre sur **Annonce**. Le retour à la galerie restaure les filtres, le tri, le mode d’affichage et la position de défilement.

## 2. Principes UX

1. **Un bien, une URL, quatre lectures.** Le bien reste le contexte principal ; les analyses sont des vues indépendantes.
2. **Divulgation progressive.** L’utilisateur ne renseigne que les données indispensables manquantes au moment de lancer une analyse.
3. **État toujours explicite.** Chaque onglet d’analyse indique s’il est non calculé, à compléter, en cours, disponible ou en échec.
4. **Actions destructives secondaires.** La suppression d’une annonce ou d’une analyse est isolée dans un menu et confirmée.
5. **Aucun bruit analytique dans la galerie.** Aucun score, badge de disponibilité ou bouton de calcul n’est affiché sur les vignettes.
6. **Responsive sans changer le modèle mental.** Les quatre onglets restent au même niveau sur toutes les tailles d’écran.

## 3. Galerie simplifiée

### Interaction principale

- Toute la vignette, hors actions, est un lien vers la fiche et s’ouvre dans un nouvel onglet.
- Le titre du lien accessible annonce cette conséquence : « Ouvrir la fiche dans un nouvel onglet ».
- Les actions visibles sont **Favori**, **Écarter** et **Carte**.
- **Supprimer** est placé dans le menu `•••`, puis protégé par une confirmation.
- Les boutons « Calculer », « Afficher la fiche », les scores et les badges d’analyse disparaissent.

### Hiérarchie recommandée

1. photo au ratio stable ;
2. prix, typologie et surface ;
3. commune et code postal ;
4. source et fraîcheur de l’annonce ;
5. barre d’actions compacte.

## 4. Structure de la fiche

### En-tête persistant

- lien **Retour à la galerie** ;
- identité courte du bien : type, surface, commune et prix ;
- actions secondaires : Favori, Écarter, Carte et menu `•••` ;
- navigation primaire : Annonce, Patrimoine, Locatif, Marchand de biens.

L’en-tête devient compact après défilement afin de conserver les onglets sans masquer le contenu. Sur mobile, les onglets sont horizontalement défilables et présentent un indicateur de débordement.

### Onglet Annonce

- galerie photo dominante ;
- résumé décisionnel immédiatement lisible : prix, surface, pièces, terrain et localisation ;
- adresse exacte éditable, préalimentée par l’annonce, marquée **facultative pour les analyses** ;
- description et caractéristiques ;
- informations de source et bouton « Voir l’annonce d’origine ».

L’adresse exacte appartient au bien, pas à une analyse : sa saisie est donc placée dans l’onglet Annonce et réutilisée dans les trois formulaires.

### Onglets d’analyse

Une fois disponible, chaque fiche existante est rendue dans son onglet, sous un bandeau commun contenant la date, le statut et le menu d’actions. Les sous-onglets internes actuels (résumé, axes, finance, risques, sources…) restent au second niveau et ne doivent pas concurrencer les quatre onglets principaux.

## 5. Cycle d’une analyse indépendante

| État | Message et contenu | Action principale | Actions secondaires |
| --- | --- | --- | --- |
| Non calculée | Valeur attendue de l’analyse et données nécessaires | Lancer l’analyse | Aucune |
| À compléter | Formulaire limité aux champs indispensables manquants ; adresse exacte affichée séparément comme facultative | Enregistrer et lancer | Annuler |
| En cours | Étapes compréhensibles, heure de lancement et possibilité de quitter la page | Aucune | Actualiser l’état |
| Disponible | Fiche existante et date du dernier calcul | Consulter la fiche | Recalculer, supprimer l’analyse |
| Échec | Cause exploitable si disponible, données conservées | Réessayer | Modifier les données, supprimer l’analyse |

Le CTA ne doit jamais promettre un résultat instantané. Après validation du formulaire, l’utilisateur arrive immédiatement sur l’état « Analyse en cours ».

## 6. Formulaire de lancement

- Afficher uniquement les champs indispensables absents, regroupés par thème.
- Préremplir toutes les valeurs connues et expliquer leur provenance.
- Afficher l’adresse exacte dans une zone distincte, préremplie, éditable et explicitement facultative.
- Ne jamais bloquer un calcul sur l’adresse exacte vide.
- Conserver les saisies en cas d’erreur technique.
- Placer les erreurs au niveau du champ et un résumé d’erreurs en tête du formulaire.

Microcopie recommandée pour l’adresse : **« Adresse exacte (facultatif) — améliore la précision géographique, mais n’est pas nécessaire pour lancer l’analyse. »**

## 7. Recalcul et suppression

- **Recalculer** ouvre le même parcours de vérification des données que le premier calcul, avec les valeurs existantes préremplies.
- La fiche disponible reste visible tant que le nouveau calcul n’est pas terminé ; un bandeau signale le recalcul en cours.
- Une modification du bien ne déclenche aucune action ni alerte automatique.
- **Supprimer l’analyse** ne supprime ni l’annonce ni les autres analyses et requiert une confirmation nommant explicitement le type d’analyse.
- La suppression de l’annonce reste une action différente et irréversible depuis le menu du bien.

## 8. Responsive

### Desktop — ≥ 1024 px

- conteneur de contenu limité à environ 1 280 px ;
- grille Annonce en deux colonnes, média majoritaire ;
- panneaux d’analyse conservant leur densité actuelle.

### Tablette — 640 à 1023 px

- galerie photo pleine largeur puis synthèse en deux colonnes ;
- actions regroupées pour éviter une barre surchargée ;
- tableaux complexes défilables horizontalement avec première colonne persistante si nécessaire.

### Mobile — < 640 px

- libellé `MDB` ;
- onglets horizontalement défilables, cible tactile minimale de 44 px ;
- actions Favori, Écarter et Carte sous forme d’icônes libellées ou accessibles ;
- contenu en une colonne et CTA principal pleine largeur ;
- feuilles modales plein écran pour formulaires et confirmations.

## 9. Accessibilité et mesure

- navigation d’onglets conforme au motif ARIA `tablist` / `tab` / `tabpanel` ;
- focus visible et restauré après fermeture d’une modale ;
- annonces de changement de statut via une région `aria-live` ;
- couleur jamais utilisée seule pour communiquer un état ;
- ouverture dans un nouvel onglet annoncée dans le nom accessible ;
- suivi recommandé : ouverture d’une fiche, changement d’onglet, abandon du formulaire, lancement, succès, échec, recalcul et suppression.

## 10. Dette repérée pendant l’audit

Ces éléments devront être traités lors de l’implémentation, sans dupliquer un nouveau parcours :

- le choix d’une fiche est aujourd’hui répété entre les cartes, la liste et la visionneuse ;
- les états d’analyse sont construits séparément dans plusieurs rendus de `assets/js/app.js` ;
- les trois templates d’analyse répètent leur propre en-tête de bien et leur galerie ;
- le bouton flottant de recalcul permet actuellement de choisir n’importe quel type depuis une fiche donnée, ce qui contredit le futur modèle « un onglet = une analyse » ;
- la visionneuse actuelle et les en-têtes des fiches présentent deux descriptions concurrentes du même bien.

## 11. Maquette haute fidélité

Le fichier [`fiche-bien-unifiee.html`](./fiche-bien-unifiee.html) est un prototype statique autonome. Sa barre de démonstration permet de visualiser :

- la galerie simplifiée ;
- l’onglet Annonce ;
- les cinq états de l’onglet Locatif ;
- les largeurs desktop, tablette et mobile.

Les interactions sont volontairement limitées à la présentation et ne déclenchent aucun appel API.
