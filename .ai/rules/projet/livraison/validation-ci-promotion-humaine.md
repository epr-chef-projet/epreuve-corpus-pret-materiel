# LIV-CI — Validation CI et promotion humaine

**Version 1.0 — contenu validé.** [Index des compléments](../index.md).

**Lire pour :** concevoir, exécuter, auditer ou corriger un circuit qui retient **validation locale préalable → vérification/construction en intégration continue (CI) → promotion humaine habilitée vers la production**. Retrouver ce choix dans les sources du projet, par son profil ; ce complément ne l'adopte pas pour un autre projet et n'impose aucun fournisseur, format de livrable ou mode de déploiement.

**Procédure :** sources → validation du contenu soumis → vérification de la révision intégrée → construction/preuves du livrable → sélection et autorisation → recontrôle → transition et constat R21.

Les prescriptions `LIV-CI-001` à `LIV-CI-012` sont obligatoires dans ce champ, selon [R01](../../socle/01-noyau-agent.md). Les références de conception possèdent versions, commandes, profils, critères, habilitations et paramètres. Leur présence, une CI verte ou cette rédaction ne qualifie aucun circuit installé.

**Frontières :** [R18](../../socle/18-tests-preuves-acceptation.md) possède les preuves et profils ; [R20](../../socle/20-environnement-dependances-qualite.md) les runtimes, dépendances et commandes ; [R08](../../socle/08-securite-identites-isolation.md) les accès ; [R09](../../socle/09-confidentialite-cycle-donnees.md) les données des diagnostics ; [R19](../../socle/19-performance-observabilite.md) les mesures ; [R21](../../socle/21-livraison-exploitation-reprise.md) la préparation de cible, la concurrence, la transition, les processus et la reprise. Consulter les propriétaires affectés selon leur statut, sans charger récursivement tout le corpus.

## 1. Retrouver les sources et prérequis

**LIV-CI-001 — Identifier le circuit applicable.** Retrouver dans les sources versionnées ses phases, déclencheurs, profils de validation, commandes communes, lieu de construction, format/stockage du livrable, recette de cible et habilitations. Distinguer décisions, mécanismes installés et garanties éprouvées. Une suite locale, un fichier de workflow ou un bouton de livraison isolé ne démontre pas leur raccordement ; clarifier auprès du responsable habilité toute exigence nécessaire introuvable selon R01, sans choisir à sa place un fournisseur ou une procédure.

**LIV-CI-002 — Raccorder les phases avant leur exécution.** Associer dans la recette chaque phase à ses entrées identifiées, préconditions, commandes/profil, résultats requis et conduite en cas d'échec. Vérifier accès et moyens qualifiés R20 avant la phase dépendante ; expliciter les différences légitimes entre poste, CI et cible. Prévoir comment révision vérifiée, preuves, livrable et autorisation seront rapprochés jusqu'au constat de livraison R21. Suspendre une phase dont un prérequis essentiel manque ; poursuivre les travaux indépendants autorisés.

## 2. Valider localement le contenu soumis

**LIV-CI-003 — Vérifier ce qui sera soumis.** Avant soumission à la CI de validation, exécuter sur le poste les tests et contrôles requis R18/R20 depuis le runtime déclaré, avec génération/concordance nécessaires. Examiner fichiers suivis, indexés et non suivis pour identifier le contenu effectivement soumis : une sortie locale absente du commit ne prouve pas le contenu du dépôt. Un échec bloque cette soumission ; après modification, génération ou résolution de conflit, reprendre les preuves invalidées selon R18. Relier le résultat à la révision soumise, pas seulement au nom de branche.

**LIV-CI-004 — Distinguer contribution et résultat intégré.** Identifier la révision exacte que la CI doit vérifier : contribution, résultat de fusion ou version destinée à la livraison selon la phase. Faire contrôler le résultat réellement intégré avant d'admettre son livrable à la promotion ; les verts des contributions séparées ne prouvent pas leur assemblage. Construction et contrôles suivent l'ordre déclaré, certains contrôles portant sur le build obtenu. Une fusion sans conflit Git reste à vérifier. Une fusion exclusivement produite sur le serveur n'est pas réputée testée sur le poste ; cela n'exige pas de la rapatrier systématiquement pour répéter toute la suite locale.

## 3. Vérifier et construire en CI

**LIV-CI-005 — Reprendre indépendamment les garanties.** Faire exécuter à la CI les commandes communes depuis une révision explicitement résolue et l'environnement qualifié R20, avec dépendances verrouillées et ressources d'essai maîtrisées. Déclarer les différences de profils R18 ; ne pas dépendre d'un état caché du poste. Propager jusqu'à la décision les échecs, interruptions et délais des étapes requises. Un contrôle manquant, annulé, ignoré ou dont l'échec est neutralisé ne constitue pas une preuve réussie ; une condition de workflow qui saute un contrôle ne vaut pas justification de sa non-applicabilité. Vérifier la correspondance entre résultats requis et statut agrégé, pas seulement un indicateur vert.

**LIV-CI-006 — Produire un livrable vérifiable.** Construire et vérifier en CI le livrable final R21 depuis cette révision ; relier identité vérifiable du contenu, entrées de génération, locks, runtime/profil et résultats requis à l'exécution qui les a produits. Contrôler générés, build et fichiers réellement distribués ; protéger leur stockage et vérifier l'identité aux transferts. Un commit affiché ou un tag mutable ne prouve pas le contenu obtenu. Toute reconstruction reçoit une identité d'exécution et de contenu, sans présumer son équivalence ; un contenu différent, notamment un build par destination, reprend les preuves affectées et l'autorisation applicable. Un contenu identique ne dispense pas du recontrôle de ses conditions R21.

## 4. Protéger le circuit et conserver son efficacité

**LIV-CI-007 — Séparer les capacités dans les systèmes effectifs.** Donner à chaque phase les droits nécessaires pour vérifier, publier ou agir sur la cible selon R08 ; contrôler les accès au dépôt, workflows, sorties, secrets, CI et mécanismes de déploiement. Traiter code et entrées non fiables avant exécution privilégiée ; ne pas interpoler une donnée non fiable dans une commande. La séparation en jobs ne prouve pas l'isolation d'une machine persistante, d'un espace de travail, cache ou socket Docker partagé. Protéger modifications du circuit et accès permettant de contourner ses gardes R21 ; une Policy Laravel ne protège pas un bouton Forge ou un secret CI. Qualifier aussi actions/scripts tiers selon R20.

**LIV-CI-008 — Optimiser sans changer le sens des contrôles.** Mesurer le chemin critique et les ressources selon R19 ; paralléliser seulement des étapes indépendantes, avec isolation des données et agrégation bloquante des résultats requis. Pour chaque cache, identifier entrées déterminant sa validité, portée, droits d'écriture et restauration ; refuser qu'une origine non fiable alimente une étape privilégiée. Un cache absent ou invalide entraîne le calcul nécessaire, pas l'omission du contrôle. Conserver les critères R18 pour ciblage/réutilisation des preuves : ne pas remplacer une preuve requise par l'existence d'un cache ni imposer une répétition complète lorsque seules certaines garanties sont invalidées.

## 5. Autoriser puis recontrôler la promotion

**LIV-CI-009 — Faire sélectionner le livrable et la cible.** Une personne habilitée autorise explicitement le livrable vérifié, sa cible et le moment de livraison selon la recette. Enregistrer cette association et faire exécuter la procédure commune par l'automatisation ; push, intégration ou CI verte n'autorise pas à lui seul la production. Garder la même identité jusqu'au contenu réellement installé : ni « dernier build », ni branche devenue courante, ni reconstruction différente ne peut se substituer au livrable autorisé. Une interface qui affiche un commit sans imposer ce contenu au déploiement ne satisfait pas cette sélection.

**LIV-CI-010 — Maintenir les conditions jusqu'à l'action.** Après attente ou changement pertinent, rapprocher autorisation, identité/intégrité, preuves, cible, configuration, état et compatibilité de reprise selon R21. Déterminer ce qui reste valide et refaire la préparation/qualification nécessaire avant l'opération dépendante ; ne pas inventer de durée fixe de validité. Appliquer ces gardes à toutes les voies ordinaires, notamment boutons, hooks et scripts. Raccorder la livraison à la sérialisation et à la transition R21 ; préparer une version ne permet pas d'agir sur l'état partagé de production avant l'autorisation correspondante.

## 6. Qualifier et entretenir le parcours

**LIV-CI-011 — Éprouver succès et refus.** Qualifier dans un périmètre sûr le parcours complet et ses refus représentatifs R18/R21 : fusion incompatible, contrôle échoué/annulé/manquant, livrable altéré, accord absent ou invalidé, déclencheur alternatif et opérations concurrentes. Vérifier le refus dans le mécanisme réel, pas seulement dans l'interface. Après annulation ou perte de connexion du déclencheur, constater l'état de l'opération distante : son arrêt n'est pas acquis. Ne pas relancer une opération incompatible ni libérer son verrou tant qu'une intervention active n'est pas exclue selon R21.

**LIV-CI-012 — Transmettre une recette et des preuves exploitables.** Versionner commandes/configurations et points d'arrêt dans leurs supports propriétaires ; rendre retrouvables révision soumise/intégrée, exécution CI, profils/résultats, identité du livrable, cible, autorisation et constat R21. Distinguer validation locale, CI, qualification représentative et résultat réel ; garder les limites des preuves. Conserver diagnostics utiles et accessibles selon R09/R19, sans divulguer secrets ou données d'essai inutiles. Après changement de runtime, outil, circuit ou habilitation, réexaminer les garanties affectées et mettre à jour recette et preuves selon R18/R20/R21.

## Illustrations — Trois parcours

Hypothèses fictives : ce circuit est retenu et ses commandes, profils, livrable et habilitations sont qualifiés. Aucun exemple ne constitue un pipeline installé ni une sélection de fournisseur.

<a id="parcours-livraison"></a>
### A — Contribution A, fusion A′ et livrable L

Une correction de contrat affecte PHP et Vue. Les preuves et gardes du parcours transféré de R21 restent nécessaires ; les étapes d'exploitation restent possédées par R21.

| Phase | Résultat ou refus observable |
| --- | --- |
| Local | Sur le contenu soumis A, génération puis tests/suite requise R20 réussissent ; un échec arrête la soumission à la CI de validation. |
| Intégration/CI | La fusion produit A′ : la CI contrôle A′, construit le livrable L et vérifie son contenu. Le vert obtenu sur A ne vaut pas pour A′. |
| Préparation | L et ses preuves sont identifiés ; configuration de cible et recette préproduction applicables vérifiées. Une reconstruction différente de L revient aux preuves requises. |
| Promotion | La personne habilitée choisit L, la cible et le moment ; tous les déclencheurs appliquent les gardes. Preuves invalidées, accord absent ou CI en échec bloquent aussi un bouton Forge/hook alternatif. |
| Activation/suivi | Transition et renouvellement qualifiés R21, puis contrôles de version, SQL, effet différé et vue synchronisée lorsqu'ils sont concernés. Un échec conduit au confinement/repli prévu, pas à annoncer livraison réussie. |

Une fusion sans conflit peut garder une page Vue dépendant de l'ancien champ : le contrôle d'A′ échoue et bloque L malgré les verts séparés. L'adaptateur doit installer le contenu sélectionné ; la réserve sur Forge est dans [R21, illustration A](../../socle/21-livraison-exploitation-reprise.md#a--identifier-le-contenu-à-livrer).

### B — Attendre, changer une valeur, reconstruire

| Situation | Procédure et limite |
| --- | --- |
| L attend la personne habilitée | Recontrôler conditions et preuves au moment d'agir ; l'attente seule n'impose ni expiration arbitraire ni répétition de toute la suite. |
| Une configuration de cible a changé | Examiner les garanties affectées et la reprise R21. Les tests du code peuvent rester valides ; la qualification du raccordement modifié doit être reprise. |
| Une valeur publique est compilée par Vite pour une autre destination | Modifier le `.env` serveur ne réécrit pas le build. La nouvelle construction obtient son identité, les preuves appropriées et une autorisation pour ce livrable/cette cible ; consulter R21-007. |
| Un nouveau L₂ est disponible après sélection de L | Garder L si ses conditions tiennent, ou faire autoriser L₂ après ses vérifications. « Dernier build » ne remplace pas la sélection enregistrée. |

### C — Refuser sans supposer l'opération arrêtée

| Épreuve | Résultat à constater |
| --- | --- |
| Contrôle requis annulé ou échec neutralisé | Pas de livrable admis à la promotion sur cette preuve ; le statut agrégé ne masque pas le manque. |
| Livrable modifié après vérification | Identité/intégrité non concordante : refus ; retrouver le contenu et les preuves nécessaires avant une nouvelle sélection. |
| Accord absent ou ancien accord portant sur un autre livrable/cible | Refus de production, même si la CI est verte. |
| Bouton ou hook tentant de contourner une condition requise | Les mêmes gardes refusent l'opération ; une permission dans Laravel seule ne démontre pas ce refus. |
| Deux opérations incompatibles visent le même état | La sérialisation R21 empêche leur chevauchement pendant la transition entière. |
| Déclencheur annulé après début d'intervention distante | Vérifier si l'intervention continue. L'annulation ne permet ni de libérer automatiquement son verrou ni de lancer une seconde livraison incompatible. |

**Repères vérifiés le 6 octobre 2026 :** [sécurité des workflows GitHub Actions](https://docs.github.com/en/actions/reference/security/secure-use), [artefacts et vérification de leur digest](https://docs.github.com/en/actions/tutorials/store-and-share-data), [dépendances/conditions des jobs](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax#jobsjob_idneeds), [déploiements Forge](https://laravel.com/forge/docs/sites/deployments). Ces références éclairent les mécanismes lorsqu'ils sont concernés ; elles n'adoptent aucun fournisseur. Vérifier les versions et capacités réellement utilisées. Aucune exécution CI, compilation, livraison ou mesure applicative réalisée par cette rédaction.
