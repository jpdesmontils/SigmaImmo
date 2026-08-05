# Description de l'application SigmaImmo / ImmoAgg

## Résumé de la finalité

SigmaImmo, affichée dans l'interface sous les noms ImmoAgg ou ImmoAggregator, est une application personnelle de veille, de centralisation et d'aide à la décision pour des annonces immobilières. Elle permet de capturer des annonces via une extension Chrome, de les regrouper dans une galerie unique, de les filtrer, de les classer, de consulter leurs détails, puis de lancer ou consulter des analyses chiffrées adaptées à plusieurs stratégies d'investissement : patrimonial, locatif et marchand de biens. L'application s'appuie notamment sur des comparables de marché issus des mutations DVF et sur des analyses générées à partir de prompts dédiés pour transformer une annonce en décision argumentée : score, rendement, cash-flow, marge, risques, conditions et sources.

## Écrans de l'application web

### Page d'accueil / landing page (`index.html`)

- Présentation du positionnement de l'outil : centraliser les annonces et fournir un score pour décider.
- Mise en avant du parcours principal : capturer, centraliser, décider.
- Présentation des bénéfices : capture en un clic, galerie filtrable, trois types d'analyses, comparables notariés.
- Illustrations par captures d'écran de la galerie et d'une analyse locative.
- Boutons d'accès vers la galerie applicative.

### Galerie des annonces (`app.html`)

- Écran principal de consultation des annonces sauvegardées.
- Header avec logo, compteur de favoris et sélecteur de vue.
- Sidebar de navigation et de filtres.
- Navigation vers les guides opérationnels intégrés.
- Filtres par ville, prix minimum/maximum et surface minimum/maximum.
- Tri par date, prix, surface, note, rendement, revenu brut ou cash-flow, en ordre croissant ou décroissant.
- Filtrage par classement utilisateur : tous, sans tag, ShortList, à visiter, visité, écartés.
- Filtrage par types d'analyse disponibles : patrimonial, locatif, marchand de biens.
- Réinitialisation des filtres.
- Sauvegarde locale de l'état de galerie : filtres, vue active, ordre et liste filtrée.
- Suppression d'une annonce avec modale de confirmation.

### Vue galerie des annonces

- Affichage des annonces sous forme de cartes.
- Photo principale ou placeholder si aucune image n'est disponible.
- Affichage des badges de classement utilisateur et des types d'analyses disponibles.
- Affichage du score de la dernière analyse disponible.
- Affichage de synthèses locatives lorsqu'elles existent : revenu brut annuel, rendement net, cash-flow.
- Affichage du prix, du prix au mètre carré, de la surface, de la localisation et des métadonnées principales.
- Actions rapides sur les cartes : classer, ouvrir les options, supprimer.
- Ouverture de la visionneuse détaillée d'annonce.

### Vue liste des annonces

- Affichage tabulaire des annonces.
- Colonnes principales : miniature, titre, prix, surface, ville, source et actions.
- Consultation rapide des mêmes données synthétiques que la galerie.
- Accès aux fiches ou à l'annonce selon les actions proposées.

### Vue carte des annonces

- Affichage des annonces géolocalisées sur une carte Leaflet.
- Regroupement des marqueurs via clustering.
- Marqueurs colorés selon le prix relatif.
- Popups reprenant les informations principales du bien.
- Actions depuis les popups : classement, ouverture, suppression.
- Possibilité de recentrer la carte sur une annonce depuis la visionneuse.

### Visionneuse d'annonce dans la galerie

- Consultation détaillée d'une annonce sans quitter `app.html`.
- Navigation entre les annonces filtrées : précédente / suivante.
- Carrousel photo avec boutons précédent / suivant, compteur et miniatures.
- Affichage complet du titre, prix, localisation, description, caractéristiques et informations techniques.
- Affichage des tags utilisateur, des analyses disponibles et du score.
- Actions : classer l'annonce, voir sur la carte, supprimer, ouvrir la fiche in-app ou ouvrir l'annonce source.

### Contenu intégré dans l'application (`view-in-app`)

- Chargement des guides opérationnels et des fiches dans le cadre applicatif sans sortir de la galerie.
- Injection d'un bloc « Source annonce » lorsqu'un guide ou une fiche est consulté depuis une annonce.
- Réexécution des scripts nécessaires après chargement dynamique pour conserver les onglets et simulateurs interactifs.

### Fiche du bien (`fiche-bien.html`)

- Page dédiée à une annonce sauvegardée.
- Navigation de retour vers la galerie.
- Navigation entre les annonces issues de la liste filtrée.
- Onglets de fiche : Annonce, Prix, Patrimoine, Locatif, Marchand de biens, Notes.
- Persistance du dernier onglet consulté.

### Onglet Annonce de la fiche du bien

- Affichage du titre, de la localisation et des photos de l'annonce.
- Galerie photo avec miniature, photo principale et navigation.
- Affichage des KPI modifiables : surface, terrain, DPE, GES.
- Affichage des pièces, du prix et du lien vers l'annonce d'origine.
- Édition de l'adresse exacte pour améliorer la précision géographique.
- Classement du bien : ShortList, à visiter, visité, écarté.
- Actions de lancement ou de consultation des analyses disponibles.

### Onglet Prix de la fiche du bien

- Analyse du positionnement du prix via des ventes comparables DVF.
- Affichage du prix demandé et du prix demandé au mètre carré.
- Calcul de la médiane des comparables et de l'écart du bien à cette médiane.
- Estimation d'une valeur prudente et d'une fourchette de contre-offre recommandée.
- Argumentaire de négociation selon l'écart au marché.
- Liste des ventes comparables retenues : date, adresse, type, surface, terrain, prix, prix/m², distance.
- Carte des ventes comparables et du bien analysé.
- Recalcul manuel des comparables.
- Lien vers la source publique des données.

### Onglet Patrimoine de la fiche du bien

- Lancement ou consultation d'une analyse patrimoniale.
- Affichage d'une fiche d'opportunité patrimoniale lorsque l'analyse existe.
- Score global et verdict de décision.
- Synthèse, conditions, points forts et points de vigilance.
- Analyse d'accessibilité depuis Paris.
- Analyse de qualité patrimoniale.
- Leviers d'optimisation.
- Scénarios financiers.
- Risques, actions avant offre et avant compromis.
- Sources et avertissements.

### Onglet Locatif de la fiche du bien

- Lancement ou consultation d'une analyse d'investissement locatif.
- Score global et verdict.
- Rendement brut/net, revenus, cash-flow et coût total projet.
- Synthèse exécutive et conditions de décision.
- Scores par axes et détail des KPI.
- Décomposition des revenus par lot.
- Scénarios financiers.
- Risques, upsides et sources.

### Onglet Marchand de biens de la fiche du bien

- Lancement ou consultation d'une analyse marchand de biens.
- Qualification de l'opération, faisabilité et complexité.
- Marge nette de base, prix demandé, prix maximum d'acquisition et écart demandé / PMA.
- Consultation d'une fiche d'opportunité marchand de biens construite depuis le résultat d'analyse.

### Onglet Notes de la fiche du bien

- Saisie et sauvegarde de notes personnelles sur l'annonce.
- Champs de suivi de visite et de contact agent lorsque disponibles dans l'API.
- Persistance des informations dans les données de favoris.

### Fiche d'investissement patrimonial (`templates/fiche-investissement-patrimonial.html`)

- Modèle de restitution d'analyse patrimoniale.
- Onglets : Synthèse, Paris → destination, Patrimoine, Optimisations, Financement, Risques, Sources.
- Échelle de valeur, position du prix demandé et fourchette d'offre recommandée.
- Cohérence du prix par rapport au marché communal ou de quartier.
- Détail du trajet ferroviaire et du dernier kilomètre.
- Grille de notation pondérée.
- Leviers d'optimisation et scénarios financiers.
- Sources générales, sources d'accessibilité et avertissements.

### Fiche d'investissement locatif (`templates/fiche-investissement-locatif.html`)

- Modèle de restitution d'analyse locative.
- Onglets : Exec summary, Synthèse axes, Détail KPI, Revenus & lots, Scénarios financiers, Risques & upsides, Sources.
- Score, verdict, rendement net, cash-flow, revenus et coût total projet.
- Analyse détaillée par axe pondéré.
- Tableau des lots et des loyers.
- Scénarios de financement.
- Risques classés par criticité et opportunités d'amélioration.

### Fiche d'opportunité marchand de biens (`templates/fiche-investissement-mdb.html`)

- Modèle de restitution d'analyse MDB.
- Affichage de la qualification, de la faisabilité et de la complexité.
- Mise en avant de la marge nette, du prix demandé, du PMA et de l'écart à négocier.
- Support de décision pour une opération de marchand de biens.

### Guide opérationnel — Marchand de biens / division parcellaire (`guide-mdb-division-parcellaire.html`)

- Guide stratégique pour une opération de division parcellaire avec 100 k€ d'apport.
- Onglets : Cadrage financier, Bilan type, Départements, Villes cibles, Process opérationnel, Typologies MDB, Fiscalité & TVA, Simulateur.
- Comparaison de LTV et enveloppe financière.
- Liste de banques et courtiers à contacter.
- Bilan type d'opération de division parcellaire.
- Sensibilité de marge selon le prix de revente du terrain.
- Simulateur interactif : acquisition, frais, financement, travaux, lots, prix de vente, TVA, objectif de marge.
- Calculs de marge brute, marge nette, rendement sur fonds propres, point mort, PMA et alertes.

### Guide opérationnel — Investissement locatif (`guide-investissement-locatif.html`)

- Guide stratégique pour un investissement locatif avec 125 k€ d'apport.
- Onglets : Cadrage financier, Bilan type, Départements, Villes cibles, Process opérationnel, Typologies, Fiscalité, Simulateur.
- Comparaison d'apport et d'effet de levier sur un bien type.
- Sélection de départements et villes cibles selon rendement, liquidité et demande locative.
- Process de sélection, acquisition et mise en location.
- Typologies d'actifs locatifs.
- Sensibilité du cash-flow selon loyer et apport.
- Simulateur interactif de rendement net, cash-flow, coût total et financement.

## Écrans de l'extension Chrome

### Popup de l'extension (`chrome-plugin/popup.html`)

- Affichage de l'identité ImmoAggregator.
- Statistiques locales : nombre de favoris et date de dernière synchronisation.
- Bouton de synchronisation manuelle.
- Bouton d'ouverture de la galerie web.
- Action de capture de l'annonce courante.
- Accès à la configuration.
- Effacement des données locales.
- Barre de statut de l'extension.

### Configuration de l'extension

- Saisie de l'URL serveur de synchronisation.
- Saisie de la clé API.
- Saisie de l'URL de galerie.
- Activation/désactivation de la synchronisation automatique.
- Enregistrement local de la configuration.

### Capture de contenu par l'extension

- Extraction de données depuis les pages d'annonces immobilières.
- Capture du titre, du prix, de la surface, des photos, de la description et de la source lorsque ces données sont détectables.
- Scripts spécifiques pour des pages Green-Acres, dont favoris et pages d'annonces.
- Utilitaires d'extraction testés pour parser notamment les surfaces.

## Fonctionnalités backend et données

### Synchronisation et stockage des annonces

- API de synchronisation des favoris capturés par l'extension.
- API de liste des annonces, avec enrichissement par les synthèses d'analyses disponibles.
- Stockage JSON des favoris et des analyses.
- Nettoyage de doublons.
- Correction utilitaire de prix pour certaines annonces SeLoger.

### Classement et suppression

- API de tag pour modifier le classement d'une annonce.
- Classements supportés côté interface : ShortList, à visiter, visité, écarté, sans tag.
- API de suppression d'annonce.
- Suppression associée des fichiers d'analyses liés à une annonce supprimée.

### Édition des données de fiche

- API de fiche permettant de mettre à jour des champs : adresse, localisation, prix, surface, pièces, terrain, DPE, GES, notes, date de visite, nom/téléphone/email agent.
- Validation des valeurs énergétiques DPE/GES de A à G.
- Invalidation de l'analyse prix lorsque des champs structurants changent.

### Analyses immobilières

- Types d'analyse supportés : patrimonial, locatif et marchand de biens.
- Lancement d'analyse via API et worker asynchrone.
- Suivi de statut de job d'analyse.
- Prompts dédiés par type d'analyse.
- Pour l'analyse patrimoniale : étape de données prix DVF, étape trajet Paris, puis analyse patrimoniale consolidée.
- Calcul et normalisation de synthèses d'analyse : score, date, revenu brut annuel, rendement net, cash-flow.
- Notifications globales pour signaler la progression ou la disponibilité des analyses.

### Analyse de prix / DVF

- Service DVF pour rechercher les mutations comparables autour d'un bien.
- Géocodage ou utilisation de coordonnées lorsqu'elles existent.
- Calcul de médiane, quartiles, valeur prudente et fourchette de contre-offre.
- Déduplication des ventes par lots comparables.
- Restitution en liste et en carte.

### Journalisation et maintenance

- Journalisation des étapes d'analyse et des erreurs.
- Nettoyage des jobs expirés.
- Endpoints utilitaires pour maintenance ou correction des données.

## Fonctionnalités transverses

- Interface responsive avec sidebar mobile.
- Design system partagé entre landing page, galerie, fiches et guides.
- Chargement de contenus in-app pour éviter les ruptures de navigation.
- Gestion de cache par versions de scripts CSS/JS dans les URLs.
- Sécurité minimale côté API par clé et validation/sanitation de champs utilisateur.
- Tests JavaScript et PHP couvrant les filtres, les notes, les onglets, les options de galerie, les analyses, les prix, DVF et l'extension.
