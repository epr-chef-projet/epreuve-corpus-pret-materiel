# R05 — Schéma, migrations et historique

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r05).

**Lire pour :** créer, auditer ou modifier table, colonne, relation, contrainte, index, cast de persistance, donnée historique ou migration de données. **Repères :** migrations/factories/seeders dans le module propriétaire ; modèles dans le sous-domaine ; accès métier dans les repositories, selon [R02](02-architecture-modules.md).

Les prescriptions `R05-001` à `R05-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). **Procédure :** exigence → représentation SQL → garanties → migration du schéma et des données → preuves. Appliquer le même parcours à une création et à la correction de l'existant.

Les [références de conception](../index.md#references-conception) définissent les données, états, relations et conditions historiques attendus. R05 prescrit leur implémentation. [R03](03-php-laravel-metier.md) possède les invariants métier, [R04](04-contrats-dto-validation.md) les contrats, [R06](06-requetes-lectures-cache.md) les requêtes, [R07](07-transactions-concurrence-etats.md) la concurrence, [R08](08-securite-identites-isolation.md) les accès/RLS, [R09](09-confidentialite-cycle-donnees.md) l'application des politiques de données et [R21](21-livraison-exploitation-reprise.md) la livraison. Consulter selon l'impact et le statut ; un renvoi futur ne prouve aucun mécanisme installé.

## 1. Retrouver l'exigence et le propriétaire

**R05-001 — Définir ce que le stockage représente.** Retrouver sens, propriétaire, portée, cardinalités, valeurs possibles et caractère courant/historique/reconstruisible. Déduire nullabilité, défauts, unicité et suppression de ces exigences, sans les inventer pour simplifier la migration. En cas de manque, appliquer R01/R09 avant la partie dépendante. Réutiliser la documentation propriétaire ; ne pas créer une fiche mécanique par colonne.

**R05-002 — Placer et raccorder le schéma.** Ranger les migrations métier dans `app/Modules/<Module>/Database/Migrations/`, les factories/seeders dans leurs dossiers voisins ; réserver `database/` aux responsabilités techniques transversales. Générer avec `make:migration --path=...` via le lanceur du projet ; charger les migrations dans le provider déclaré selon R02. Raccorder explicitement factories et seeders hors conventions, puis vérifier découverte et ordre. Déclarer les dépendances entre modules : une clé étrangère n'autorise pas leurs repositories à contourner `PublicApi`. Le SQL de migration appartient à cette responsabilité technique ; le SQL métier courant reste dans les repositories.

**R05-003 — Nommer sans ambiguïté.** Utiliser l'anglais et `snake_case` pour SQL ; tables métier `<module>_<ressource_au_pluriel>`, qualifiées par sous-domaine en cas d'homonymie. Déclarer la table du modèle avec `$table` et les références dont le nom sort des conventions Eloquent. Nommer contraintes/index de manière explicite, stable, unique dans leur portée et compatible avec les limites du moteur ; abréger de façon lisible si nécessaire. Respecter les noms imposés par les packages. Ne pas renommer silencieusement un objet existant : tout renommage suit la procédure de migration.

## 2. Choisir les types et préserver le sens

**R05-004 — Unifier les identifiants métier.** Employer un UUIDv7 comme clé primaire, stocké en `uuid` PostgreSQL et généré côté application. Avec Eloquent, utiliser `HasUuids` après vérification du comportement de la version ; pour insertions directes/imports/factories, vérifier le même format et l'absence de génération concurrente d'une seconde identité. Garder les références dans le même type et le transport opaque exact R04. Ne pas ajouter systématiquement un identifiant public parallèle ; UUID ne vaut ni autorisation, ni secret, ni ordre métier. **Variante :** conventions des tables techniques, clé composée d'une liaison ou identité externe justifiée, sans imposer un UUID supplémentaire à chaque ligne.

**R05-005 — Stocker les montants exacts.** Utiliser un `bigint` signé en unités mineures pour les montants finalisés ; rendre devise et échelle identifiables dans le contrat et sa source persistante, sans supposer deux décimales pour toute devise. Définir bornes métier et bornes représentables avant conversion. Garder taux/intermédiaires en décimales exactes à précision/échelle déclarées ; appliquer l'arrondi R03 avant finalisation. Vérifier casts et transport R04 sans détour par un float ou un nombre JS imprécis. Aucune positivité universelle : elle vient du sens du montant. **Contrôle :** zéro, signes admis, limites et aller-retour exacts ; aucun défaut monétaire ne masque une donnée inconnue.

**R05-006 — Distinguer les données temporelles.** Stocker les instants avec `timestamp with time zone` (`timestamptz`), en convention UTC, et une précision raccordée à R04 ; utiliser les méthodes Laravel correspondantes, dont `timestampTz`. Conserver les dates civiles en `date`. Un horaire local garde le fuseau IANA et le contexte nécessaires ; `timestamptz` ne conserve pas le fuseau d'origine. Une durée porte son unité. Examiner casts, défauts SQL et configuration des connexions ; tester aller-retour et changements d'heure sans dépendre du fuseau implicite du serveur.

**R05-007 — Préserver absence et valeurs.** Définir pour chaque colonne utile le sens de `NULL`, zéro, chaîne vide et défaut. Employer `NOT NULL` lorsque l'exigence impose une valeur ; ne pas remplir une ancienne ligne par une valeur arbitraire pour réussir ce contrôle. Aligner conversions, tailles et bornes SQL sur les contrats, sans faire du cast Eloquent une validation. Pour une unicité incluant des valeurs nulles, choisir et tester explicitement la sémantique attendue.

**R05-008 — Contrôler les ensembles fermés.** Pour un ensemble défini par le code, utiliser un texte et un `CHECK` nommé sur les valeurs admises, avec enum PHP lorsque pertinent. Tester la concordance du schéma courant et du code, ainsi que le rejet d'une valeur inconnue. Les migrations historiques gardent leurs valeurs explicites, sans appeler l'enum actuel. **Variante :** enum PostgreSQL natif si besoin précis et évolution maîtrisée ; documenter ajout, renommage et retrait. Une liste administrable selon le produit n'est pas automatiquement un enum fermé : concevoir son stockage relationnel.

**R05-009 — Justifier le JSON.** Choisir colonnes/relations pour les données qui nécessitent une identité, des liens ou des garanties propres. Un document variable cohérent peut justifier `jsonb` : définir propriétaire, forme, taille, évolution et projections utilisées. Ne pas enfouir des références structurantes dans du JSON pour éviter des contraintes. Si comparaison, indexation ou représentation exacte du document impose une autre solution, la motiver. R04 valide les contrats ; les garanties essentielles de stockage restent à exercer, y compris pour un champ JSON devenu structurant.

## 3. Poser les garanties structurelles

**R05-010 — Traduire les invariants adaptés.** Pour chaque exigence de persistance, choisir clé, unicité, `NOT NULL`, `CHECK`, référence ou contrainte spécialisée adaptée. Définir portée et moment de vérification. Une relation Eloquent ou une vérification `exists`/`unique` ne remplace pas ces garanties. Un `CHECK` pouvant accepter `NULL` ne suffit pas à rendre un champ obligatoire ; ne pas y dissimuler une lecture mutable d'autres lignes. Les décisions multi-opérations gardent leur coordination R07. **Contrôle :** écriture incohérente réellement refusée en base, sans validation applicative préalable qui masquerait l'absence de contrainte.

**R05-011 — Définir le cycle des relations.** Choisir les actions de suppression/mise à jour selon l'exigence : restriction, cascade ou lien nullable justifiés, jamais une cascade universelle. Vérifier types, clé cible, nullabilité et comportement des objets partagés/historiques. Une suppression logique Eloquent ne déclenche pas une suppression SQL du parent ; elle ne prouve ni cohérence métier du lien ni effacement R09. Une relation polymorphe ou une référence sans FK exige une garantie alternative identifiée et testée, pas une absence implicite d'intégrité.

**R05-012 — Garantir la cohérence des périmètres.** Pour un lien devant rester dans le même périmètre d'isolation, protéger le couple périmètre/identifiant, par exemple avec FK composée et unicité cible correspondante. Deux références simples valides ne prouvent pas leur appartenance commune. Appliquer les politiques et droits R08 aux nouvelles tables, avec identité de migration distincte du rôle applicatif ; la cohérence des liens complète RLS sans remplacer l'autorisation. Une référence globale ou publique suit sa portée propre, sans clé de périmètre ajoutée mécaniquement.

**Illustration A — Lier une commande au bon repas.** Hypothèse : commande et repas appartiennent au même restaurant ; supprimer un repas encore référencé est refusé. Extraits des `up()` de deux migrations ordonnées du module `Dining`, avec `Schema` et `Blueprint` importés. Ils montrent cette garantie seulement : tables d'établissement, autres liens, RLS, droits et colonnes métier restent à raccorder selon leurs exigences. Les UUID sont fournis à l'insertion ; la colonne ne les génère pas seule.

```text
app/Modules/Dining/
├── Providers/DiningServiceProvider.php
├── Database/Migrations/
│   ├── …_create_dining_meals_table.php
│   ├── …_create_dining_orders_table.php
│   ├── …_create_dining_order_items_table.php  # B, puis variante C
│   └── …_add_unit_price_minor_to_dining_order_items.php  # C, base préservée
└── Orders/Models/…
tests/Integration/Modules/Dining/Database/…
```

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// …_create_dining_meals_table.php
Schema::create('dining_meals', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('restaurant_id');
    $table->unique(['restaurant_id', 'id'], 'dining_meals_restaurant_id_unique');
});

// …_create_dining_orders_table.php
Schema::create('dining_orders', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('restaurant_id');
    $table->uuid('meal_id');
    $table->foreign(['restaurant_id', 'meal_id'], 'dining_orders_meal_fk')
        ->references(['restaurant_id', 'id'])->on('dining_meals')
        ->restrictOnDelete();
    $table->index(['restaurant_id', 'meal_id'], 'dining_orders_meal_index');
});
```

L'unicité composée du parent permet la FK exacte malgré la clé primaire `id` ; l'index enfant illustré sert aux recherches de dépendants, il n'est pas créé automatiquement par la FK. Sa pertinence doit être confirmée avec R06 sur les accès réels.

**Preuve :** créer un repas A et un repas B. Sous rôle applicatif et contexte RLS A valides, une commande A → repas A réussit ; une commande portant restaurant A et l'identifiant du repas B échoue sur `dining_orders_meal_fk` avec SQLSTATE `23503`. Écrire directement pour exercer la base. Insérer une commande B sous contexte A pourrait seulement prouver un refus RLS, pas la FK. Tester aussi la suppression restrictive ; isoler les essais ou rétablir la transaction après chaque erreur PostgreSQL. Restitution de l'erreur chez R22.

## 4. Représenter l'historique et les données dérivées

**R05-013 — Séparer fait historique et valeur courante.** Lorsque l'exigence impose de conserver les conditions d'une opération, enregistrer les valeurs nécessaires depuis sa source autorisée ou référencer une version stable. Identifier le moment où elles deviennent acquises, avec R03/R07. Une lecture historique ne doit pas les recalculer implicitement depuis une source courante mutable. Préserver les liens utiles sans copier tout l'objet ; définir les corrections permises d'après la référence, sans rendre tout historique absolument immuable.

**R05-014 — Identifier la source de chaque dérivé.** Pour projection, agrégat ou cache persistant, déclarer source, reconstruisibilité et fraîcheur attendue avec R06. Ne pas présenter un dérivé comme la vérité historique s'il ne peut la reproduire. Prévoir correction, remplacement et suppression selon R09 ; ne pas adopter partitionnement, archivage, snapshots universels ou event sourcing par anticipation. **Contrôle :** une modification de la source conserve ou renouvelle le dérivé selon son contrat, sans perte silencieuse du fait requis.

**Illustration B — Le catalogue change, le prix historique reste exact.** Hypothèse : la ligne confirmée conserve son prix retenu ; la source de devise/échelle associée est stable. Dans `dining_order_items`, `unit_price_minor` est un `bigint` et reçoit le montant de la proposition autorisée lors de l'enregistrement par le repository, sous l'orchestration de l'action. Le produit conserve séparément son prix courant. Aucun service/DTO ne sauvegarde le modèle à la place du repository.

| Étape | Valeur persistée ou attendue |
|---|---|
| Confirmation à 18,00 EUR, échelle 2 | `unit_price_minor = 1800` pour la ligne. |
| Prix catalogue modifié à 20,00 EUR | Prix courant `2000` ; aucune mise à jour implicite du fait historique. |
| Relecture de l'ancienne ligne | `1800`, devise et échelle historiques ; transport exact R04. |

**Preuve :** modifier le catalogue puis relire par le vrai parcours historique, pas seulement le modèle en mémoire. Une jointure qui remplace le prix de la ligne par le prix courant ferait échouer ce contrôle. Si la référence autorise la suppression du produit, vérifier que la stratégie de lien ne détruit pas l'instantané requis ; sinon tester le refus prévu. La politique de suppression/correction reste celle de la spécification.

## 5. Concevoir les index et leur évolution

**R05-015 — Justifier chaque index.** Relier sa présence à une contrainte ou à un accès défini avec R06 ; examiner colonnes, ordre, prédicat éventuel et index déjà créés par les contraintes. Ne pas ajouter un index par colonne ni supprimer comme « redondante » l'unicité cible requise par une FK composée. PostgreSQL n'indexe pas automatiquement le côté référençant d'une FK : examiner les recherches de dépendants et leur coût. Mesurer avec R06/R19 sur volumes représentatifs ; inclure espace et coût des écritures.

**R05-016 — Préparer la modification physique.** Avant création d'index, validation de contrainte ou changement de type : estimer scans, réécritures, verrous, durée et volume disque. Choisir les modalités compatibles avec le service attendu, fixer interruption/reprise avec R21 et vérifier le résultat réel. Pour `CREATE INDEX CONCURRENTLY`, utiliser une migration dédiée avec `public $withinTransaction = false;`, sans transaction englobante, y compris dans les tests. Après échec, contrôler définition et `pg_index.indisvalid` : corriger la cause puis supprimer/recréer l'index identifié ou le reconstruire si adapté ; `IF NOT EXISTS` ne le répare pas. Une contrainte ajoutée `NOT VALID`, lorsque supportée, exige la validation ultérieure des anciennes lignes. Aucun mode « sans blocage » ne se déduit du seul nom d'une commande.

## 6. Choisir le régime de migration et traiter les données

**R05-017 — Identifier le régime avant d'éditer.** Avant le gel du schéma initial, privilégier la modification des migrations de création plutôt que l'accumulation d'ajustements, uniquement si toutes les bases concernées sont explicitement reconstructibles. Consigner cette condition et coordonner les autres intervenants. Geler les migrations distribuées dès qu'un environnement concerné doit préserver ses données ou son historique de déploiement, même en pilote/préproduction. Après ce gel, ajouter les migrations d'évolution ; un reset local ne rend pas modifiables celles déjà utilisées ailleurs.

**R05-018 — Reconstruire de façon reproductible.** En régime initial, adapter migrations, factories et seeders ensemble ; annoncer les changements nécessitant une reconstruction. Vérifier connexion cible, base exclusivement dédiée et possibilité de supprimer ses données. Utiliser le lanceur du projet et `migrate:fresh --seed` pour reconstruire puis réensemencer ; la commande supprime toutes les tables de la connexion ciblée, indépendamment des préfixes. Adapter aussi la recette aux objets non-table créés, notamment vues/types PostgreSQL (`--drop-views`, `--drop-types` si nécessaires), et vérifier leur recréation. `migrate:refresh` exécute les retours arrière : un `down()` réécrit peut ne plus correspondre au schéma installé. Ne pas utiliser une ancienne base locale comme seule preuve du nouveau schéma ; tester la reconstruction réelle avec données synthétiques.

**R05-019 — Ajouter les évolutions d'une base durable.** Garder les migrations distribuées stables et livrer les nouvelles opérations dans leur ordre de dépendance, avec connexion et identité SQL appropriées. Utiliser `Schema`/`Blueprint` pour les opérations couvertes et du SQL PostgreSQL explicite lorsqu'il apporte la garantie nécessaire. Prévoir modification de structure et traitement des données, pas seulement changement de colonne. Ne pas dissimuler une dérive de schéma par des `hasColumn()` qui ignorent silencieusement une structure inattendue : vérifier les préconditions et traiter l'écart.

**R05-020 — Garder les migrations indépendantes du code courant.** Écrire les conversions historiques avec SQL ou Query Builder explicites, sans modèle Eloquent courant, cast, scope, observer, service métier ou enum dont l'évolution changerait leur résultat. Un auxiliaire nécessaire conserve un contrat versionné stable auprès de l'opération. Le SQL de ces migrations/rattrapages relève de cette responsabilité technique, sans réutiliser les repositories métier pour gagner quelques lignes. **Contrôle :** rejouer l'historique après évolution du code ne change pas les valeurs produites.

**R05-021 — Définir la conversion avant son exécution.** Fixer source, unité, arrondi, bornes, absence, anomalies et résultat attendu selon la référence. Qualifier la conversion sur les données concernées ; ne pas substituer une valeur courante à une valeur historique manquante. Une conversion courte et déterministe peut rester dans une migration si temps, verrouillage et atomicité sont maîtrisés. Le passage d'un ancien float vers un type exact ne récupère pas une précision déjà perdue ; résoudre les ambiguïtés avant de prétendre restaurer le montant historique.

**R05-022 — Organiser les rattrapages longs.** Ranger le traitement versionné, borné et reprenable dans `app/Modules/<Module>/Database/DataMigrations/<Operation>/`. Enregistrer sa commande Artisan dans le provider du module et préciser préconditions, avancement et contrôles ; ce dossier n'est pas exécuté automatiquement par `migrate`. Prévoir sélection stable des lots, portée SQL, points de reprise, échecs partiels et résultats vérifiables avec R06/R07/R10/R21. Reprendre ne doit ni reconvertir une ligne acquise, ni oublier des écritures concurrentes. Un marqueur « traité » ou une cible non nulle ne prouve pas seul sa conformité. Ne pas bloquer le déploiement dans un `up()` volumineux ni annoncer le traitement achevé à sa seule mise en file.

**R05-023 — Coordonner les producteurs et consommateurs.** Pour une transition nécessaire : ajouter une représentation compatible, adapter écritures/lectures, convertir l'existant, vérifier puis renforcer les contraintes et retirer l'ancien format. Définir la source faisant autorité pendant chaque phase et la synchronisation avec anciennes versions, jobs/imports/processus encore actifs ; éviter les doubles écritures non coordonnées. **Variante :** conversion directe ou arrêt contrôlé si volume, interruption admise et producteurs réellement arrêtés le permettent. Le mode maintenance HTTP ne stoppe pas à lui seul tous les workers ; R21 possède cette coordination d'exploitation.

**R05-024 — Décrire les limites du retour arrière.** Distinguer annulation d'une transaction, retour au code précédent, migration inverse et restauration. Préciser ce qui est réversible, les informations nécessaires et le moment où un ancien code devient incompatible. Un `down()` supprimant une colonne ne restitue pas son contenu ; ni sauvegarde ni rollback ne rendent une destruction automatiquement sûre. Prévoir la marche de correction en avant ou la reprise appropriée avec R21 ; ne pas fabriquer une migration inverse mensongère.

**Illustration C — Même changement, deux régimes.** Hypothèse de correction : une ancienne table `dining_order_items` conserve `unit_price` en flottant, exprimé en EUR. Le contrat cible attend `unit_price_minor` en entier, échelle 2. Cet ancien stockage simulé n'autorise pas de nouveaux calculs monétaires flottants.

| Base concernée | Application de la procédure |
|---|---|
| Toutes les bases sont initiales et reconstructibles | Remplacer la définition dans `…_create_dining_order_items_table.php` par `$table->bigInteger('unit_price_minor');`, adapter factories/seeders et reconstruire. Aucune conversion de données jetables n'est nécessaire. |
| Une base doit préserver ses données | Garder la migration initiale ; ajouter `…_add_unit_price_minor_to_dining_order_items.php` dans le même dossier, avec cible temporairement nullable. Livrer conversion, contrôle et bascule avant de retirer l'ancien format dans une étape autorisée. |

Pour le petit lot ci-dessous, les valeurs obligatoires sont préalablement vérifiées finies et dans les bornes cibles/métier ; les ambiguïtés ont été traitées et la conversion qualifiée. L'hypothèse adopte explicitement l'arrondi à la moitié en s'éloignant de zéro. Tous les producteurs du périmètre sont arrêtés pendant cette opération ; rôle SQL et périmètre permettent de traiter exactement les lignes annoncées. L'extrait s'exécute par `DB::statement()` sur cette connexion dans l'opération versionnée :

```sql
UPDATE dining_order_items
SET unit_price_minor = round(unit_price::numeric * 100)::bigint
WHERE unit_price_minor IS NULL;
```

Pour `12.50`, la cible attendue est `1250`, pas `12`. Convertir en `numeric` avant multiplication évite de prolonger le calcul en flottant, sans retrouver une intention historique perdue. `IS NULL` évite d'écraser une cible déjà renseignée ; il ne valide pas cette cible ni ne protège contre une ancienne écriture ultérieure. En rattrapage long/actif, appliquer R05-022/023 plutôt que reprendre ce SQL sans ses préconditions.

**Preuves :** contrôler les valeurs, compteurs, anomalies et restes nuls avant `NOT NULL` et bascule ; vérifier qu'une reprise ne multiplie pas à nouveau par 100. Tester notamment zéro, signes autorisés, limite et valeur ambiguë refusée. Préserver l'ancien format selon le retour prévu avant sa suppression. Comparer l'installation neuve et la base convertie une fois la transition terminée : même schéma cible et mêmes valeurs attendues pour les jeux équivalents.

## 7. Vérifier et documenter le changement

**R05-025 — Tester le moteur et les rôles réels.** Exercer contraintes, types et particularités sur la version PostgreSQL retenue, avec rôles/mécanismes appropriés. Séparer identité de migration et rôle runtime restreint ; un test sous propriétaire ne prouve pas RLS. Une simulation ou SQLite ne prouve pas les garanties PostgreSQL. Vérifier connexion, migrations modulaires et configuration réellement chargées ; R18/R20 organisent les environnements de preuve.

**R05-026 — Vérifier les deux chemins de schéma.** Tester reconstruction depuis zéro et, dès qu'une base doit être préservée, évolution depuis son ancien schéma avec données représentatives. Contrôler contraintes, index, types, nullabilité, données et raccordements finaux, pas seulement le code de sortie de `migrate`. En régime initial, prouver factories/seeders et reconstruction coordonnée ; ne pas imposer une conversion de données explicitement jetables.

**R05-027 — Exercer les garanties et la transition.** Tester écritures valides autant que refus SQL directs, nullabilité, unicité, références croisées, effets de suppression et transports exacts. Pour l'historique, modifier réellement la source courante puis relire par le parcours concerné. Pour une migration, tester versions coexistantes, interruption/reprise, source modifiée et limites de retour selon l'impact. Vérifier la contrainte attendue, pas un échec quelconque ; R22 possède sa restitution.

**R05-028 — Livrer les preuves et limites utiles.** Documenter chez le propriétaire l'exigence appliquée, les dépendances, préconditions, méthode de conversion, résultat et opérations restantes. Identifier ce qui est testé, seulement relu ou encore à qualifier, y compris volumes/verrous. Actualiser modèles, contrats, factories et contrôles concernés avec le changement ; ne pas faire du socle un inventaire de colonnes produit. La revue doit permettre à un autre intervenant de reproduire la procédure sans deviner une étape essentielle.

**Portée des illustrations :** extraits de conception, sans migration ni test applicatif exécuté. Les fichiers PHP maintenus suivent R03, notamment `declare(strict_types=1)` ; seuls les corps décisifs sont montrés, pas des fichiers prêts à déployer. Noms, hypothèses et colonnes illustratifs ne constituent pas le schéma adopté de l'application.

**Repères techniques :** [migrations Laravel](https://laravel.com/framework/docs/13.x/migrations), [UUID Eloquent](https://laravel.com/framework/docs/13.x/eloquent#uuid-and-ulid-keys), [contraintes PostgreSQL](https://www.postgresql.org/docs/current/ddl-constraints.html), [index et création concurrente](https://www.postgresql.org/docs/current/sql-createindex.html), [types numériques](https://www.postgresql.org/docs/current/datatype-numeric.html), [fonctions d'arrondi](https://www.postgresql.org/docs/current/functions-math.html), [types temporels](https://www.postgresql.org/docs/current/datatype-datetime.html). Vérifier les mécanismes sur les versions retenues ; ces sources ne prouvent pas notre implémentation.
