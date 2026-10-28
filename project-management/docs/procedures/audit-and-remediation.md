# Auditer un périmètre et traiter les constats

**Version 1.2 - contenu validé ; parcours opérationnel à exercer.**

Cette procédure organise un audit d'un projet entier, d'une fonctionnalité ou d'un changement, puis le traitement de ses constats. Elle complète la revue courante de chaque petit lot prévue par R01-014. Elle n'impose pas un dossier d'audit pour chaque modification.

Retrouver les sources actuelles depuis les instructions de la racine, l'index des règles et le profil du projet : CDC, architecture, contrats, documentation interne et procédures. Respecter leurs statuts et leur applicabilité. R01 possède accords, arbitrages et clôture ; R18 possède les exigences de preuve ; les règles spécialisées gardent leurs garanties. La présente procédure ne recopie pas ces références et n'autorise pas leur assouplissement.

<a id="scope"></a>

## 1. Définir le périmètre et conserver le suivi

Identifier le résultat attendu, la racine du projet, la révision examinée, la branche et les modifications locales pertinentes, y compris les nouveaux fichiers. Un identifiant de commit ne décrit pas à lui seul un travail non committé. Décrire cet état de manière suffisante pour retrouver le contenu examiné, sans imposer de nouveau clone ni déplacer le travail existant.

Déterminer si la demande porte sur un changement ou sur l'existant complet. Identifier les domaines à examiner selon le périmètre : comportement, architecture, accès, intégrité des données, concurrence, reprises, coût des traitements et requêtes, interface, tests, configuration et livraison. Une qualité mesurable demande les conditions et preuves correspondantes ; la lecture du code ne garantit pas une tenue en charge.

Conserver les rapports dans `project-management/local/audits/<YYYY-MM-DD_short-description>/`, avec une description en anglais et un nom non ambigu en cas de plusieurs audits le même jour. Vérifier l'exclusion effective de `project-management/local/` de Git, des images et des archives partagées avant d'y écrire. Si elle manque, faire approuver sa préparation avant la création des rapports ; la reconnaissance en lecture seule peut continuer.

```text
project-management/local/audits/2026-10-26_full-application-review/
  mapping.md
  parts/
    01-access-control.md
    02-data-integrity.md
  report.md
  progress.md
```

Les noms de parties sont choisis selon le périmètre réel ; cet exemple ne fixe pas les domaines obligatoires. Tous les noms de fichiers et de sous-dossiers d'audit sont en anglais. Créer les fichiers au moment où ils deviennent utiles. `mapping.md` conserve le cadre, la cartographie approuvée et sa couverture. Les fichiers de `parts/` portent les constats détaillés. `report.md` les synthétise et y renvoie. `progress.md` conserve l'avancement et la reprise de l'audit puis des corrections, sans recopier les constats.

Les documents de préparation, de compte rendu et de suivi conservés dans le dossier d'audit sont exclusivement en Markdown (`.md`) : `mapping.md`, les parties dans `parts/`, `report.md` et `progress.md`. Inscrire directement l'inventaire des fichiers, les exclusions et la couverture dans `mapping.md`, sous forme de listes ou de tableaux Markdown. Ne produire ni inventaire ni état de suivi parallèle en JSON, TSV ou CSV, ni export, script ou fichier annexe non demandé. Tout fichier supplémentaire dans le dossier d'audit nécessite un besoin expliqué et un accord explicite avant sa création.

Les résultats natifs des outils et de la CI restent consultables dans leur format et leur emplacement prévus ; les référencer depuis les documents Markdown sans les copier ou les exporter automatiquement dans le dossier d'audit.

Cette limitation concerne les documents d'audit ; les fichiers de correction et les sorties normales des essais restent couverts par leur plan approuvé.

Ne pas enregistrer secrets, données privées inutiles, corps de requêtes ou journaux bruts non autorisés. La procédure commune et les critères utiles au projet sont partageables ; les rapports d'exécution et les échanges d'approbation restent privés.

<a id="mapping"></a>

## 2. Soumettre la cartographie à approbation

Inventorier les fichiers du périmètre, puis proposer un découpage par comportements, responsabilités et frontières. Pour un audit complet, inclure le code maintenu, les tests, les configurations, les workflows et les références documentaires pertinents. Justifier les exclusions, notamment dépendances tierces, sorties générées ou archives. Une dépendance utilisée peut nécessiter un examen de son raccordement sans lecture de tout son code.

Pour chaque partie, préciser :

- Les comportements, responsabilités et consommateurs examinés, y compris ceux situés hors des fichiers modifiés.
- Les fichiers concernés, inventoriés directement dans `mapping.md` ; un simple motif de chemin ne vaut pas preuve de lecture de tous les fichiers correspondants.
- Les exigences et règles applicables, leurs identifiants lorsqu'ils existent, leurs sources et les points à confronter au travail réel.
- Les preuves prévues et les limites connues : analyse du code, résultats antérieurs à recevoir ou essais à exécuter.
- Les commandes et effets des essais envisagés, leurs ressources autorisées, leur isolation et les limites utiles de durée, de volume ou de coût.

Rattacher chaque fichier au moins à une partie ou à une exclusion justifiée. Prévoir les raccordements transverses : une correspondance entre un fichier et une règle ne couvre pas une chaîne répartie entre plusieurs composants.

La première remise comporte la cartographie complète dans `mapping.md`, puis attend son approbation avant l'examen détaillé. Un inventaire seul ne remplace pas le découpage et les correspondances demandés. La reconnaissance nécessaire à sa préparation peut rester en lecture seule ; les seuls écrits autorisés à ce stade sont `mapping.md` et, si nécessaire, `progress.md`. Réutiliser le périmètre déjà approuvé sans demander une nouvelle autorisation par fichier ou commande.

Conserver la référence de la cartographie approuvée dans `progress.md`. Une nouvelle dépendance peut compléter la cartographie ; une extension importante de périmètre, de risque ou d'effets attend un complément approuvé.

<a id="review"></a>

## 3. Examiner et suivre la couverture

Traiter chaque partie en confrontant le travail réel aux exigences sélectionnées. Examiner les chemins nominaux, refus, erreurs, transitions et combinaisons de conditions pertinentes. Rechercher les contournements et consommateurs oubliés ; ne pas se limiter aux fichiers que les tests actuels exécutent.

Pour chaque entrée de la cartographie, distinguer : `planned`, `in_progress`, `reviewed`, `not_applicable` ou `not_reviewed`, avec justification pour les deux derniers. `reviewed` signifie que l'examen prévu est effectué, pas qu'aucun défaut n'existe. Conserver les références utiles aux fichiers et exigences réellement examinés, sans journal obligatoire par ligne de code.

L'audit ne modifie pas les fichiers de l'application, les règles, les configurations ou les données actives pour corriger un défaut. Les essais approuvés utilisent les ressources d'essai prévues et leur nettoyage ; ne pas reconstruire une base, lancer une migration ou changer des services actifs au seul motif de vérifier une hypothèse. Un essai nécessaire non autorisé est proposé ou laissé comme limite explicite. Une CI réussie renseigne sur les contrôles exécutés, sans remplacer l'examen des exigences.

Séparer systématiquement l'analyse du code, la consultation d'une preuve existante et un essai personnellement exécuté. Relier chaque résultat à son état de code et à ses conditions utiles. Un résultat ancien ne reste recevable que si les changements intervenus ne l'invalident pas.

Si le projet évolue pendant l'examen, relever la nouvelle révision et réexaminer les conclusions et preuves affectées. Ne pas présenter comme une revue d'une seule version des résultats provenant d'états incompatibles.

Avant de clore chaque partie, effectuer le [contrôle de clôture](#closure). La couverture des fichiers et celle des obligations restent distinctes.

<a id="findings"></a>

## 4. Décrire des constats vérifiables

Donner à chaque constat un identifiant stable dans le dossier, par exemple `AUD-001`. Un même défaut partagé entre plusieurs parties conserve un seul identifiant et une seule description propriétaire.

Chaque constat contient au minimum :

- Sa catégorie : `confirmed` pour un défaut établi, `suspected` pour une hypothèse restant à vérifier, `improvement` pour une amélioration sans non-conformité démontrée.
- Le scénario déclencheur, le comportement observé ou déduit et l'impact concret.
- L'emplacement et la révision concernés, avec les références de fichiers et lignes utiles.
- L'exigence applicable lorsqu'il s'agit d'une non-conformité, ou la justification technique d'un défaut non explicitement décrit dans les références.
- La preuve disponible, ses limites et le moyen de reproduction ou de vérification lorsqu'il est connu.
- La sévérité et sa justification selon l'échelle retenue dans le projet ou approuvée avec la cartographie.

Fonder la sévérité sur l'impact et les conditions réelles, indépendamment du degré de certitude. Une suspicion à impact potentiellement élevé reste une suspicion ; une préférence de style ne devient pas un défaut critique. Ne pas imposer un nombre minimal de constats.

Pour chaque écart, distinguer autant que les preuves le permettent : implémentation contraire à une référence claire, exigence manquante ou contradictoire, environnement, preuve insuffisante ou amélioration facultative. Ne pas attribuer une intention ou une cause personnelle sans preuve.

Signaler immédiatement un risque critique confirmé, sans attendre le rapport final et sans lancer de correction spontanée. Indiquer le périmètre affecté et l'arbitrage nécessaire. Poursuivre seulement les travaux qui restent indépendants et autorisés.

<a id="report"></a>

## 5. Remettre le rapport

<a id="closure"></a>

### Contrôle de clôture

Avant de clore chaque partie puis l'audit, reprendre les exigences et prescriptions applicables des références sélectionnées et rechercher ce qui établit leur respect dans le travail réel. Ne pas limiter cette passe aux fichiers cochés ou aux constats déjà trouvés. Une référence déclarée consultée ne prouve pas que ses obligations ont été vérifiées.

Dans le rapport de partie existant, relier les obligations examinées à une conformité étayée, un constat d'écart, une vérification limitée ou manquante, ou une non-applicabilité justifiée. Préciser l'emplacement pertinent dans le code ou la documentation et la preuve disponible, selon la garantie concernée ; conserver les identifiants lorsqu'ils existent. Regrouper les obligations couvertes par une même vérification si leurs identifiants, leur portée et leur preuve restent repérables. Aucun nouveau fichier, formulaire ou tableau universel n'est imposé.

Revoir la qualification des conclusions et des points écartés. La réussite d'un contrôle ou le respect d'une garantie voisine ne démontre pas toutes les exigences concernées. Une obligation dont la satisfaction reste indéterminée conserve une limite ou une question ouverte ; elle ne devient pas une simple amélioration facultative sans justification. Avant la synthèse finale, vérifier la cohérence entre obligations examinées, constats, limites et verdicts annoncés. Reprendre les vérifications affectées lorsque nécessaire, dans la portée autorisée.

### Synthèse et remise

Produire `report.md` avec la révision et le périmètre reçus, la couverture, les exclusions, les vérifications réalisées ou seulement consultées, puis une synthèse des constats par sévérité avec leurs identifiants et liens vers les parties. Séparer défauts établis, suspicions, améliorations et qualifications manquantes. Préserver les constats détaillés dans leurs fichiers propriétaires.

Expliquer ce qui peut être considéré comme reçu et ce qui reste à corriger, préciser ou qualifier. Une absence de constat n'établit pas l'absence de tout défaut. Un audit complet décrit la couverture de son périmètre approuvé ; les parties non examinées restent visibles, sans être déclarées conformes.

Présenter les priorités et regroupements de correction possibles sans les exécuter. L'approbation de l'audit ne vaut ni autorisation générale de correction ni décision de fusion, de publication ou de déploiement.

<a id="remediation"></a>

## 6. Préparer et réaliser les corrections

Retrouver le dossier d'audit demandé, sa cartographie, son rapport et son avancement. Un commit désigne une révision ; il ne remplace pas le dossier ni les identifiants des constats à traiter. Si la cible est ambiguë, la faire préciser avant les modifications dépendantes.

Comparer la révision actuelle à celle de l'audit et reconfirmer les constats concernés. Un point déjà corrigé est reçu sur preuve ; un point devenu sans objet ou contesté est expliqué. Une suspicion appelle d'abord sa vérification. Ne pas modifier les règles, affaiblir un test ou réécrire l'exigence pour faire disparaître artificiellement un écart.

Proposer de petits lots cohérents selon les responsabilités, dépendances et risques. Chaque plan de lot identifie les constats concernés, le résultat attendu, les fichiers et consommateurs affectés, les vérifications et les décisions nécessaires. Réutiliser le dossier existant sans imposer un plan séparé pour chaque remarque.

Conserver les plans de correction et leurs accords dans `progress.md`, sans créer de fichiers de plan ou d'état supplémentaires non demandés.

Attendre l'approbation de chaque plan de lot, puis réaliser sa portée complète, y compris les corrections et vérifications nécessaires déjà couvertes par l'accord. Avant le suivant, effectuer la revue de conformité de R01-014. Une extension importante ou un changement d'exigence attend son arbitrage ; les parties indépendantes déjà autorisées peuvent avancer.

<a id="verification"></a>

## 7. Vérifier la résolution et conclure

Suivre les constats dans `progress.md` avec les états `open`, `in_progress`, `fixed_pending_verification`, `verified`, `deferred`, `not_reproduced` ou `dismissed`, et les justifications nécessaires. `not_reproduced` indique une limite de reproduction, pas une preuve d'absence. Un point reporté conserve la décision, la limite acceptée et sa condition de reprise ; un point écarté conserve la preuve et la justification.

Pour une correction, ajouter ou adapter les régressions utiles et vérifier leur capacité à détecter l'ancien défaut lorsque cela est réalisable. Reprendre les contrôles dont la preuve est invalidée, puis examiner le changement réel et ses raccordements. Une modification déclarée faite reste `fixed_pending_verification` tant que la preuve attendue manque. La CI ne remplace pas cette revue.

Avant de déclarer un lot terminé ou un constat vérifié, appliquer le [contrôle de clôture](#closure) aux obligations affectées par les corrections, sans étendre tacitement l'audit aux parties indépendantes.

Identifier pour chaque résolution la révision ou l'état du travail, les vérifications et leurs résultats. Préserver le constat initial ; le suivi décrit sa résolution sans effacer ce que l'audit avait établi. Les critères, contrats ou recettes devenus nécessaires au projet rejoignent leurs références partagées, sans les récits de l'enquête.

Remettre une synthèse finale des points vérifiés, ouverts, reportés ou écartés, avec les réserves restantes. Respecter le circuit de contribution et les décisions de fusion, de publication et de déploiement du projet ; la correction d'un rapport ne les déclenche pas implicitement.

<a id="resumption"></a>

## 8. Reprendre ou transmettre

Maintenir dans `progress.md` l'objectif, les références et accords actuels, la couverture terminée, le lot en cours, les décisions attendues, les limites et la prochaine action autorisée. À la reprise, confronter ces informations à la révision réelle et aux sources actuelles avant de poursuivre. Lire seulement les parties nécessaires à l'action, sans recharger systématiquement tous les rapports et toutes les règles.
