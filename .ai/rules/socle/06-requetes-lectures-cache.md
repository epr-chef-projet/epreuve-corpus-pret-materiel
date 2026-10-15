# R06 — Requêtes, lectures et cache

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r06).

**Lire pour :** créer, auditer ou modifier une liste, recherche, relation chargée, compteur, bilan, export, lecture SQL/Eloquent ou cache. **Procédure :** besoin du consommateur → organisation des accès → projection bornée → coût vérifié → cache éventuel → preuves.

Les prescriptions `R06-001` à `R06-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Elles s'appliquent aussi aux corrections. Les [références de conception](../index.md#references-conception) fixent les données attendues, limites de parcours et exigences de fraîcheur ; cette règle explique comment les servir.

**Frontières :** [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md) possèdent organisation et métier, [R04](04-contrats-dto-validation.md) les contrats, [R05](05-schema-migrations-historique.md) le schéma/historique, [R07](07-transactions-concurrence-etats.md) la concurrence, [R08](08-securite-identites-isolation.md) les accès/RLS et [R09](09-confidentialite-cycle-donnees.md) les politiques de données. Consulter [R10](10-jobs-evenements-fiabilite.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md) selon publication, tests et mesure, en respectant leur statut ; un renvoi futur ne prouve aucun contrôle installé.

## 1. Définir le contrat de lecture et son propriétaire

**R06-001 — Décrire le résultat avant la requête.** Identifier consommateur, périmètre autorisé, champs nécessaires, filtres, ordre, navigation, volumes et cohérence attendue. Distinguer données actuelles, faits historiques et calculs dérivés selon R05 ; ne pas reconstituer un fait historique depuis un état courant mutable. Retrouver limites et fraîcheur dans la conception ; faire clarifier une exigence absente selon R01. Porter ce contrat dans les types, tests et documentation de l'usage, sans créer une fiche obligatoire par requête.

**R06-002 — Placer les responsabilités.** L'action orchestre le contexte et les accès nécessaires ; les repositories exécutent les lectures ; les services prennent les décisions ; les DTO transportent une projection sans SQL. Ranger par module/sous-domaine/usage selon R02. Rétablir le contexte autorisé R08 pour HTTP, outil IA, console et job : changer de point d'entrée ne dispense pas du périmètre. Un accesseur, service ou constructeur de DTO ne doit pas déclencher discrètement une requête.

**R06-003 — Mutualiser un besoin commun, pas un résultat trop large.** Concevoir la lecture pour son usage. Partager une requête entière seulement si sens, périmètre, champs, fraîcheur et évolution sont compatibles ; sinon séparer les projections. Une restriction stable peut rester partagée sans imposer le même chargement. Éviter repository universel, requête géante puis tri des données en PHP, mais aussi un appel HTTP/SQL par petit composant lorsque les données forment un même besoin collectif.

## 2. Concevoir le parcours collectif

**R06-004 — Compter le travail du parcours, pas seulement celui d'une requête.** Tracer les accès de l'entrée jusqu'à la réponse, y compris méthodes appelées dans les boucles, relations, accesseurs, conversions et sérialisation. Pour chaque collection et rapprochement, déterminer comment évoluent requêtes, lignes, mémoire et volume transmis avec ses dimensions. Une méthode rapide appelée pour chacun des `N × M` couples reste un coût multiplicatif ; son placement dans un repository ne résout pas ce problème.

**R06-005 — Regrouper avant d'itérer.** Pour un besoin collectif, préparer la lecture des éléments pertinents et de leurs relations, une projection/agrégation SQL ou des lots bornés ; traiter ensuite l'état obtenu sans requête par élément/couple. Ne charger ni ensemble non borné, ni candidats inutiles, ni produit cartésien inutile pour éviter plusieurs requêtes. Respecter les surfaces publiques intermodules de R02 : demander un accès par lot au propriétaire plutôt que joindre ses tables par commodité. **Variante :** des lectures individuelles restent recevables pour un traitement réellement indépendant et borné, après examen documenté du coût ; aucune obligation de « tout en une requête ».

**R06-006 — Examiner aussi le travail en mémoire.** Éviter de reparcourir une collection complète pour chaque élément quand une correspondance construite une fois par identifiant suffit. Borner sa taille et tenir compte du coût des objets hydratés, copies et DTO. Une seule requête qui charge tout, ou des boucles sans SQL qui recréent inutilement `N × M` comparaisons, ne constitue pas une optimisation. Si le résultat attendu comporte lui-même beaucoup d'associations, borner leur restitution avec le contrat ; ne pas les supprimer silencieusement.

## 3. Construire des requêtes et projections explicites

**R06-007 — Choisir le mécanisme utile.** Utiliser Eloquent pour modèles/relations, le Query Builder pour projections/agrégats adaptés, et du SQL spécialisé si le besoin le justifie. Garder ces accès dans les repositories, sur la connexion attendue. Query Builder et SQL brut n'appliquent pas automatiquement scopes, casts ou conventions Eloquent : rétablir restrictions pertinentes, qualification des colonnes et conversions R04. RLS renforce l'isolation ; elle ne remplace pas tous les filtres et droits applicatifs.

**R06-008 — Sélectionner explicitement.** Charger les colonnes du contrat et celles nécessaires à identité, relations, ordre et contrôles. Avec une sélection de relations Eloquent, conserver clés primaires et étrangères utiles au raccordement. Renvoyer une projection autorisée, pas le modèle sérialisé en bloc ni un DTO surchargé ensuite amputé. Vérifier les types des agrégats et valeurs SQL à la frontière R04 ; ne pas supposer que toutes les expressions reçoivent les casts du modèle.

**R06-009 — Rendre les relations visibles et bornées.** Charger explicitement les relations nécessaires (`with`, chargement groupé approprié), avec colonnes et contraintes pertinentes. Examiner aussi `$with`, accesseurs et retours enfant → parent : un eager loading initial ne garantit pas l'absence de nouvelles lectures. Paginer les parents ne borne pas leurs enfants ; choisir compteur, sous-ensemble défini ou pagination dédiée lorsque la relation peut grandir. En développement/test, activer les gardes Eloquent adaptées, notamment `preventLazyLoading` et `preventAccessingMissingAttributes`, et vérifier leur portée dans la version installée. Elles complètent les assertions d'absence de SQL, sans les remplacer.

**R06-010 — Calculer au bon endroit et au bon niveau.** Pour savoir si une ligne existe, compter ou agréger, employer `exists`, `count`, `sum`, `withCount`, `withExists` ou une projection adaptée plutôt que charger les modèles uniquement pour calculer. Vérifier le niveau d'agrégation : joindre deux relations multiples peut multiplier les lignes et fausser un total. Préagréger, utiliser une sous-requête ou séparer les lectures selon le plan observé ; `distinct` ne corrige pas universellement le problème. Le service conserve la décision métier fondée sur le résultat.

**R06-011 — Construire filtres et tris sûrs.** Lier les valeurs ; choisir colonnes, opérateurs et directions dynamiques dans une liste autorisée, sans concaténer des entrées en SQL. Regrouper les alternatives `OR` sous le périmètre commun pour ne pas élargir accidentellement la lecture. Valider taille des recherches, listes d'identifiants et taille de page selon le contrat. Examiner sélectivité, fonctions appliquées aux colonnes et recherches avec jokers : un `LIMIT` borne le résultat, pas nécessairement le travail préalable de filtrage/tri.

**Illustration A — Liste avec relation et compteur.** Hypothèse pédagogique : afficher les identifiants de commandes d'une période, la date, le libellé de table du repas associé et le **nombre de lignes**. Aucun détail d'article n'est demandé. Le schéma et ces champs illustrent la procédure, sans définir le produit.

```text
app/Modules/Dining/Orders/
├── Models/Order.php
└── UseCases/ListOrders/
    ├── ListOrdersAction.php
    ├── ListOrdersInput.php
    ├── ListOrdersRepository.php
    └── OrderListItemData.php
tests/Integration/Modules/Dining/Orders/ListOrders/…
```

Entrée validée → autorisation R08 → action/contexte SQL → repository → projection `OrderListItemData` → réponse R04. L'entrée contient période UTC valide, taille de page plafonnée et curseur validé ; `$access` provient du serveur. Extrait du repository exécuté dans ce contexte :

```php
return Order::query()
    ->where('restaurant_id', $access->restaurantId)
    ->where('created_at', '>=', $input->from)
    ->where('created_at', '<', $input->until)
    ->select(['id', 'restaurant_id', 'meal_id', 'created_at'])
    ->with('meal:id,restaurant_id,table_label')
    ->withCount('items')
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->cursorPaginate($input->pageSize, ['*'], 'cursor', $input->cursor ?? '');
```

Le curseur interne est `?string` ; `null` signifie première page. Ici, `?? ''` évite que Laravel résolve à nouveau le curseur depuis la requête HTTP ambiante ; toute chaîne non vide invalide est refusée à l'entrée. Ce [comportement natif](https://github.com/laravel/framework/blob/v13.27.0/src/Illuminate/Database/Concerns/BuildsQueries.php) est vérifié sur Laravel 13.27.0, à qualifier sur la version utilisée.

Hypothèses : `id` est unique, `created_at` non nul ; les relations `meal`/`items` appliquent le périmètre requis. Avant de remplacer les modèles par des DTO, extraire et encoder `nextCursor()`/`previousCursor()` : le [paginator lit les colonnes d'ordre sur ses éléments](https://github.com/laravel/framework/blob/v13.27.0/src/Illuminate/Pagination/AbstractCursorPaginator.php), qui pourraient ensuite être renommées. Construire l'enveloppe avec `items`, `nextCursor` et `previousCursor`, selon R04. La projection utilise `id`, `created_at`, le libellé de `meal` chargé et `items_count`, avec le traitement prévu pour un repas absent. La terminer avant de sortir du contexte, sans SQL. Le compteur n'hydrate pas les articles ; mesurer néanmoins son coût. Aucun total de toutes les commandes n'est demandé.

Pour un besoin d'associations entre deux ensembles, appliquer la même préparation collective :

| Construction | Conséquence et correction |
|---|---|
| Pour chaque élément, parcourir tous les candidats et appeler `exists` pour chaque couple | Jusqu'à `N × M` accès, même avec un bon index. Sélectionner d'abord les éléments de la page et demander leurs seules associations pertinentes en groupe. |
| Charger toutes les associations, puis les refiltrer entièrement pour chaque élément | SQL moins fréquent, mais mémoire et comparaisons restent excessives. Borner à la sélection et construire une correspondance par identifiant, parcourue une fois. |

**Preuve :** plusieurs tailles et densités de relations donnent les mêmes résultats attendus, sans requête par élément/couple ni SQL pendant projection/sérialisation. Vérifier champs, absence de données d'un autre périmètre et volume des associations, pas seulement le compteur de requêtes.

## 4. Borner listes et traitements volumineux

**R06-012 — Choisir la pagination selon le contrat.** `paginate` fournit un total avec un comptage dont il faut mesurer le coût ; `simplePaginate` évite ce total mais reste à décalage ; `cursorPaginate` évite le coût des grands décalages avec les contraintes d'ordre du mécanisme. Prévoir un départage unique et des colonnes compatibles, notamment non nulles pour la pagination native par curseur. Garder filtres/tri/périmètre cohérents entre pages ; recommencer la navigation s'ils changent. Un curseur ne constitue ni une autorisation ni une preuve d'ensemble figé.

**R06-013 — Distinguer ordre et cohérence.** Un ordre stable rend le départage reproductible ; il n'empêche pas insertions, suppressions ou modifications entre pages/requêtes. Identifier ce que le consommateur exige : état observé à chaque lecture ou ensemble cohérent à un instant donné. Choisir alors lecture unique, instantané maîtrisé ou préparation d'un export selon R07. Une transaction PostgreSQL en `READ COMMITTED`, même nécessaire à RLS, ne fige pas toutes ses lectures successives. Ne pas conserver une transaction longue par défaut pour parcourir tout un historique.

**R06-014 — Parcourir par lots sans accumulation complète.** Pour un grand traitement, sélectionner seulement les champs utiles et consommer chaque lot avant le suivant. Choisir `chunkById`, `lazyById` ou une progression explicite compatible avec les données ; préciser clé/ordre, borne, mutations possibles et condition de fin. Une progression par identifiant n'est pas un instantané. `cursor()` n'autorise pas l'eager loading et le pilote peut tamponner des résultats : ne pas en déduire une mémoire constante sans mesure. Si le parcours reprend après interruption, coordonner progression et sortie avec le propriétaire R10/R21 ; le dernier identifiant seul ne garantit pas une sortie sans doublon ni omission.

**R06-015 — Maintenir le contexte jusqu'au SQL réel.** Les générateurs, collections paresseuses, relations différées, props différées et reconstructions de cache peuvent lancer leurs requêtes après leur création. Les consommer sous le contexte autorisé de la connexion qui exécute ces requêtes, ou matérialiser une projection bornée avant d'en sortir. Retourner `lazy()` depuis une transaction terminée ne transporte pas son contexte RLS. Pour chaque lot/job, rétablir les accès selon R08 et vérifier aussi les lectures implicites de restauration de modèles ; ne pas sérialiser une connexion ou un contexte ambiant comme preuve d'autorisation.

**Illustration B — Export par lots.** Hypothèse : le jeu synthétique exporté est immuable pendant l'opération ; `id` est unique et l'ordre par `id` convient. `ExportOrdersAction` orchestre, `ExportOrdersRepository::readBatchAfter` lit ; ils sont rangés dans `Orders/UseCases/ExportOrders/` selon R02.

```text
1. Action : initialiser afterId, filtres et taille de lot validés.
2. Ouvrir l'unité SQL courte ; installer le contexte RLS autorisé.
3. Repository : périmètre + filtres + id > afterId si présent,
   orderBy('id'), limit(batchSize), get(colonnes nécessaires).
4. Construire les lignes de sortie sans SQL ; terminer l'unité SQL.
5. Si le lot est vide, passer à 6. Sinon le consommer sans conserver
   les précédents, puis avancer afterId après réussite. Revenir à 2.
6. Finaliser la sortie selon son contrat.
```

L'écriture du fichier s'effectue hors de l'unité SQL ; aucun générateur de requête n'en sort. L'action conserve seulement le lot courant et la progression. Tester un volume dépassant plusieurs lots, des clés non contiguës et un périmètre adverse. Si la source devient mutable, l'hypothèse ne tient plus : décider selon R06-013 du traitement des changements. Un identifiant maximal, notamment UUID, ne fige pas à lui seul l'ensemble. La reprise durable et la publication du fichier relèvent de leurs propriétaires, pas de ce seul parcours.

## 5. Examiner le coût et justifier les index

**R06-016 — Examiner chaque lecture créée ou modifiée.** Vérifier nombre d'accès, lignes parcourues/retournées, largeur, objets hydratés, mémoire et réponse transmise sur le parcours réel. Identifier ce qui croît avec page, relations, filtres, historique et concurrence. Une lecture simple par clé peut être justifiée par sa structure et ses contrôles ciblés ; historique croissant, recherche, agrégats, jointures complexes, grand traitement ou régression exigent un plan SQL représentatif. Fixer les critères chiffrés avec la conception/R19, sans inventer un seuil universel ni compter sur un petit jeu local.

**R06-017 — Observer le plan effectif.** Utiliser `EXPLAIN`, puis lorsque nécessaire `EXPLAIN (ANALYZE, BUFFERS)` dans un environnement maîtrisé : cette seconde forme exécute réellement la requête. Examiner estimations et lignes réelles, répétitions des sous-plans, filtres, tris, lectures de blocs et débordements sur disque. Employer rôle SQL, contexte RLS, paramètres et distributions représentatifs, y compris cas dense/peu sélectif ; distinguer cache chaud et froid. Un parcours séquentiel peut être pertinent ; une utilisation d'index ne suffit pas à prouver l'efficacité. Ne pas produire de traces avec valeurs sensibles hors du cadre R09.

**R06-018 — Relier l'index à l'accès mesuré.** Justifier colonnes et ordre par filtres, jointures, tri et pagination réels ; examiner index existants, sélectivité et surcoût des écritures/stockage. Après modification, comparer plan et mesure du parcours, pas seulement une durée isolée. R05 possède migration, index physique et compatibilité de déploiement. Ne pas ajouter systématiquement un index par champ filtrable ni utiliser le cache pour masquer une requête mal construite.

## 6. Introduire et entretenir un cache justifié

**R06-019 — Démontrer le bénéfice attendu.** Introduire un cache à partir d'une mesure du parcours ou d'un coût et d'une fréquence identifiés, puis vérifier le gain. Distinguer fréquence des lectures, des modifications et fraîcheur requise. Comptabiliser accès au cache, invalidations, reconstructions, contention et workers ; des données souvent lues mais constamment modifiées peuvent rendre le cache peu utile. Évaluer d'abord la lecture directe optimisée. Le cache reste un choix ciblé, sans interdiction générale pour les données fréquemment mises à jour.

**R06-020 — Définir le contrat de cache.** Préciser source de vérité, projection, propriétaire, clé et déterminants du résultat, validité, déclencheurs d'invalidation/reconstruction, délai admissible et retrait des copies. Inclure périmètre, filtres, ordre/page, langue, destinataire et version de format **lorsqu'ils influencent le contenu**. Partager seulement entre lecteurs autorisés à la même projection ; une clé par utilisateur n'est pas obligatoire par défaut. TTL d'expiration, fraîcheur admise et conservation R09 sont des exigences différentes. Conserver ce contrat près de l'usage ; extraire un support commun uniquement dans les conditions R02.

**R06-021 — Préserver les garanties de la source.** Contrôler les accès actuels indépendamment du succès du cache. Ne pas utiliser une ancienne projection comme preuve suffisante d'autorisation ou de validité d'une mutation ; relire/protéger la décision selon R07/R08. Publier seulement des données acquises : une reconstruction depuis les écritures non commitées d'une transaction métier peut laisser une valeur fausse après rollback. Faire partir les effets après le commit extérieur selon R07 ; si leur livraison doit être garantie, appliquer le protocole R10. `afterCommit` seul ne comble pas une panne après commit et avant publication.

**R06-022 — Traiter les reconstructions concurrentes.** Choisir selon le contrat invalidation, révision de source, renouvellement coordonné ou absence de cache. Examiner au minimum remplissages simultanés et ancienne reconstruction terminant après une modification. `Cache::remember` fournit lecture/remplissage, pas toute cette cohérence ; un simple `forget` peut être suivi du rétablissement d'une ancienne valeur. `Cache::lock` peut limiter le travail concurrent avec délai et durée maîtrisés, mais ne prouve ni fraîcheur ni atomicité avec PostgreSQL. Tout versionnement doit couvrir tous les producteurs et dépendances de la projection ; tester le point où une génération devient lisible.

**R06-023 — Définir absence, attente et panne.** Sur absence/expiration, préciser qui reconstruit et ce que reçoit le lecteur. Si une valeur ancienne reste admissible, définir jusqu'à quand ; `Cache::flexible` ou reconstruction en job ne dispense pas de cette limite ni du rétablissement du contexte R08. Une mise en file n'est pas une reconstruction terminée. Borner concurrence, reprises et demandes de reconstruction ; éviter un job redondant par lecture. Prévoir la panne du cache : lecture directe seulement si son coût et ses garanties sont acceptables, sinon échec traité selon R22. Ne jamais transformer une indisponibilité en liste vide ou en valeur périmée silencieusement.

**R06-024 — Entretenir et retirer les copies.** Inclure anciennes clés/versions, invalidations manquées, changement de format et producteurs encore actifs dans les procédures R09/R21. Une génération devenue inaccessible reste une copie stockée ; expiration ou changement de clé ne prouve pas sa suppression. Une purge doit aussi empêcher une reconstruction encore en cours de réintroduire des données dont le retrait est exigé. Faire évoluer code de lecture, code de reconstruction et contrat ensemble, y compris pendant leur coexistence au déploiement. Après restauration de la source, invalider ou séparer les générations avant remise en service selon R21 : la base restaurée ne doit pas réassocier une ancienne clé conservée à un contenu différent.

**Illustration C — Reconstruction ancienne terminant trop tard.** Hypothèse : une projection autorisée de catalogue est assez coûteuse pour justifier le cache ; chaque lecteur peut accepter la version observée au début de sa lecture. Le protocole suivant illustre un **choix possible**, pas un mécanisme imposé partout.

1. Chaque mutation pertinente modifie données et révision de source dans la **même transaction PostgreSQL**. La révision ne se réutilise pas et couvre toutes les dépendances/producteurs de cette projection.
2. Le lecteur autorisé lit la révision sur le primaire sous le contexte SQL R08, puis consulte une clé contenant périmètre, variantes, format et révision. Il peut utiliser la projection trouvée ; le contexte et le coût de cette petite lecture SQL existent même sur un cache présent.
3. Sur absence, reconstruire dans une unité de lecture **sans écritures métier en attente**, sur ce même primaire, sous contexte RLS. En `READ COMMITTED`, lire révision `r1`, projection, puis révision `r2` avec les instantanés renouvelés des instructions. Si elles diffèrent, abandonner ce résultat et reprendre dans une limite définie ; après épuisement, appliquer l'issue prévue. Ne pas comparer deux lectures d'une révision figée en prétendant détecter les modifications concurrentes.
4. Si `r1 = r2`, terminer l'unité SQL, puis publier sous la clé de **cette révision**, sans maintenir la transaction pendant l'appel Redis. Ne jamais placer cette ancienne projection sous une révision plus récente. Ne pas mélanger révision primaire et données d'une réplique en retard.
5. Les lecteurs suivants consultent la révision courante sur le primaire ; le retrait physique des anciennes générations suit séparément R09.

| Entrelacement | Résultat |
|---|---|
| A reconstruit une projection en révision 7 ; B commit données + révision 8 entre ses deux contrôles | A détecte le changement et abandonne le résultat. |
| B commit après le second contrôle de A, avant sa publication Redis | A peut publier la clé 7 ; un lecteur observant 8 cherche la clé 8. La reconstruction ancienne ne redevient pas la génération courante. |
| B rollback | La révision et les données précédentes restent acquises ; aucun cache issu de ses écritures abandonnées n'a été publié. |
| B commit puis le processus tombe avant nettoyage Redis | Le primaire expose déjà 8 ; les nouvelles lectures ne dépendent pas d'un pointeur Redis resté à 7. Les anciennes copies restent à retirer selon leur contrat. |

**Limite explicite :** une lecture qui chevauche la mutation peut finir avec la version qu'elle a observée. Ce protocole ne promet pas une actualisation instantanée de tout écran ; une exigence plus forte demande une autre coordination. Réévaluer le bénéfice avec vérification de révision, reconstructions abandonnées et contention ; ne pas appliquer ce protocole à toute petite lecture.

## 7. Vérifier le parcours et transmettre les garanties

**R06-025 — Tester l'usage jusqu'à sa sortie.** Vérifier exactitude des champs, relations et agrégats, périmètre adverse, cas vide et données denses. Compter les accès sur le parcours complet, transformations incluses, et affirmer l'absence de SQL là où il est interdit. Utiliser aussi collections d'un et de plusieurs éléments : un garde du framework n'a pas nécessairement la même portée dans tous les chemins d'hydratation. Distinguer requêtes fonctionnelles et coût fixe du contexte/autorisation ; ne pas figer un compteur magique sans expliquer ce qu'il protège.

**R06-026 — Vérifier l'évolution des coûts.** Faire varier nombre d'éléments, densité des relations, historique hors sélection et nombre de lots, avec volumes représentatifs du risque. Contrôler accès, lignes, mémoire et taille de réponse, ainsi que plans requis par R06-016. Tester pagination/progression sur clés égales départagées, limites, changements de filtre et mutations permises par le contrat. Pour la pagination par curseur, vérifier première page explicite malgré un curseur HTTP présent et navigation aller/retour après projection en DTO. Pour une lecture différée, provoquer son exécution réelle et prouver qu'elle dispose encore du bon contexte ; sa seule création ne constitue pas un test.

**R06-027 — Éprouver le contrat de cache retenu.** Couvrir présent, absent, expiré et indisponible, isolation des clés/destinataires, droits devenus différents et versions de format. Pour le mécanisme choisi, provoquer reconstructions simultanées, ancienne publication après mutation, rollback et panne après commit. Vérifier fraîcheur effective, reprises bornées, charge sur la source et retrait des copies/absence de recréation exigés par R09. Un test de clé attendue ou de succès de mise en file ne suffit pas.

**R06-028 — Livrer une justification reproductible.** Conserver près de l'usage contrat utile, hypothèses de volume/cohérence, variante éventuelle, données et commandes de vérification, résultat et limites. Réutiliser tests/types et dossier de revue plutôt que multiplier les documents. Signaler les mesures non exécutées et qualifications restantes ; une explication plausible ne prouve pas une performance. Relire impacts sur consommateurs et producteurs, sans modifier un comportement produit au nom d'une optimisation.

**Références natives :** [relations et agrégats Eloquent](https://laravel.com/framework/docs/13.x/eloquent-relationships), [pagination](https://laravel.com/framework/docs/13.x/pagination), [lots et curseurs Eloquent](https://laravel.com/framework/docs/13.x/eloquent#chunking-results), [cache Laravel](https://laravel.com/framework/docs/13.x/cache), [plans PostgreSQL](https://www.postgresql.org/docs/current/using-explain.html), [isolation PostgreSQL](https://www.postgresql.org/docs/current/transaction-iso.html). Vérifier la documentation de la version réellement installée avant implémentation.
