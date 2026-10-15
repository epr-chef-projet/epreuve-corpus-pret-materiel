# R02 — Architecture et modules

**Version 1.1 — révision éditoriale à valider ; fond v1.0 validé.** [Index des règles](../index.md#r02).

Point d'entrée : [index du socle](../index.md). Méthode, approbations, exceptions et preuves : [R01](01-noyau-agent.md). **Déclencheurs :** ajout, déplacement ou extraction d'une responsabilité ; modification d'un contrat, d'une dépendance ou de l'organisation PHP, Vue, tests ou assets. Les prescriptions `R02-001` à `R02-024` sont des obligations, sauf mention **Revue** ou **Variante** au sens de R01. Leurs contrôles seront à exercer sur le projet ; leur description ne constitue pas une preuve d'exécution.

R02 possède les frontières et les emplacements. Consulter selon l'impact les fiches de [R03 — PHP](03-php-laravel-metier.md), [R04 — contrats](04-contrats-dto-validation.md), [R05 — schéma](05-schema-migrations-historique.md), [R06 — lectures](06-requetes-lectures-cache.md), [R07 — transactions](07-transactions-concurrence-etats.md), [R12 — Vue](12-vue-typescript-inertia.md), [R14 — assets](14-assets-fichiers-stockage.md) et [R18 — tests](18-tests-preuves-acceptation.md). Vérifier leur statut dans l'index ; un renvoi ne rend pas une règle future applicable.

## 1. Propriété et frontières

**R02-001 — Identifier le propriétaire.** Un **module** possède un ensemble cohérent de responsabilités métier et de contrats ; un **sous-domaine** organise une partie de ce métier ; un **cas d'usage** représente une opération ou lecture déterminée. Attribuer un propriétaire unique à chaque modèle, table, contrat, invariant et composant métier. Une donnée consultée par plusieurs modules ne devient pas une donnée modifiable par tous. Les responsabilités techniques communes ont aussi un propriétaire. **Contrôle :** pouvoir nommer qui garantit chaque comportement et qui autorise l'évolution de ses données.

**R02-002 — Tenir la carte applicative.** R02 fixe la grammaire d'organisation ; l'inventaire effectif des modules et leurs contrats concrets appartiennent à la cartographie possédée ou désignée par le CDC technique. Y déclarer, pour chaque module : responsabilité et exclusions, données possédées, opérations et types publics, dépendances autorisées, orchestrations transversales et points de raccordement. Faire approuver les frontières nécessaires avant l'implémentation dépendante ; actualiser cette source lors d'un changement. Un README de module peut fournir des repères et liens, sans devenir une carte concurrente. **Contrôle :** aucune frontière nécessaire ne repose uniquement sur une convention orale ou un exemple.

**R02-003 — Découper selon les responsabilités.** Créer un sous-domaine pour un groupe métier cohérent ; créer un module lorsque la responsabilité, ses données et ses contrats justifient une frontière explicite. Une audience d'écran, un endpoint ou un nombre de fichiers ne suffisent pas. Les parcours client, personnel et administration peuvent utiliser le même domaine. **Contrôle :** expliquer les raisons de changement propres à la frontière et ses dépendances ; ne pas transformer chaque table en module.

**Illustration — Vue globale.** Les noms métier sont des exemples, pas l'inventaire définitif du produit. Les éléments Laravel standards non utiles à la lecture sont omis.

```text
restaurant/
├── app/
│   ├── Modules/
│   │   ├── Catalog/
│   │   ├── Dining/
│   │   └── Reservations/
│   ├── Shared/
│   └── Infrastructure/
├── bootstrap/                   # Assemblage de l'application
├── config/
├── resources/                   # Vue, TypeScript et assets sources
├── tests/
├── docs/                        # CDC, documentation interne, décisions et validations
├── project-management/          # Travail et reprise, selon R01
└── .ai/rules/socle/              # Destination du corpus
```

## 2. Responsabilités Laravel

**R02-004 — Appliquer la répartition des rôles.** Utiliser Laravel et Eloquent dans les responsabilités autorisées, sans imposer un second modèle métier indépendant pour chaque modèle Eloquent.

| Rôle | Responsabilité et limites |
|---|---|
| Entrée HTTP, console, job ou outil IA | Adapter l'entrée au cas d'usage et restituer son résultat ; ne pas réimplémenter l'invariant métier. |
| Action | Orchestrer services, repositories et contrats autorisés ; porter la frontière transactionnelle nécessaire. Ne pas écrire les requêtes ni absorber les règles de calcul/décision dans l'orchestration. |
| Service métier | Vérifier, calculer et transformer à partir de l'état fourni ; peut appeler d'autres services métier. Ne pas déclencher de SQL direct ou implicite. |
| Repository / lecture spécialisée | Charger les données utiles, appliquer les protections de persistance et enregistrer les écritures prévues ; ne pas piloter le scénario métier ou la présentation. |
| Modèle Eloquent | Représenter la persistance, déclarer relations et conversions utiles ; rester interne au module. Ne pas concentrer les cas d'usage. |
| DTO | Définir les données d'une frontière pertinente ; aucune requête ni exposition implicite d'objets internes. |

**Contrôle :** rattacher chaque opération au rôle responsable. Cette répartition vise nos accès métier, sans réécrire les mécanismes internes de Laravel tels que les sessions. Les protections SQL d'un invariant relèvent de R05/R07 ; elles ne font pas du repository son décideur métier autonome.

**R02-005 — Contrôler les dépendances réelles.** Le trajet d'une mutation est entrée → action → accès aux données / traitement métier → résultat. L'action peut appeler directement repositories et services. Les services reçoivent l'état nécessaire, y compris un modèle Eloquent de leur module ; ils ne peuvent ni le sauvegarder, ni le rafraîchir, ni charger une relation manquante. Un accesseur, cast ou helper ne doit pas cacher cet accès. Repositories et services ne rappellent pas un contrôleur, une action orchestratrice ou un outil IA pour exécuter leur responsabilité. **Contrôle :** examiner les appels effectifs et les requêtes déclenchées, pas seulement les imports.

**R02-006 — Justifier chaque abstraction.** Une lecture suit entrée → action → repository de lecture → DTO, sans service vide. **Variante :** omettre l'action si l'adaptateur peut appeler le repository et restituer le résultat sans coordination supplémentaire ni duplication entre entrées ; il conserve les contrôles R08. Dès qu'il faut partager le parcours, coordonner plusieurs opérations ou porter une transaction/contexte SQL, conserver l'action. Le SQL reste dans le repository. Créer une interface pour un contrat ou une substitution utile, pas pour chaque classe. Ne pas imposer un DTO entre chaque appel interne ni une copie PHP pure de chaque modèle. **Contrôle :** chaque classe supplémentaire porte une responsabilité nommable ; sa suppression ferait perdre une séparation ou un contrat utile.

## 3. Grammaire de rangement

**R02-007 — Ranger par module, sous-domaine et usage.** Placer les usages PHP dans `app/Modules/<Module>/<SousDomaine>/UseCases/<Usage>/`, lectures et mutations selon la même convention. Garder ensemble action, service éventuel, repository spécifique, données d'entrée/résultat et adaptateurs d'entrée. Modèles et responsabilités communes restent au niveau de leur propriétaire commun. Ne créer que les fichiers nécessaires ; les ellipses d'un exemple ne prescrivent pas des dossiers vides. **Contrôle :** retrouver un usage sans parcourir des dossiers globaux généralistes.

**R02-008 — Nommer de manière prévisible.** Utiliser l'anglais pour les identifiants du code. Dossiers/namespaces PHP métier et classes en `PascalCase`, avec un fichier par classe et concordance nom/chemin ; méthodes et variables en `camelCase`. Employer les suffixes de rôle `Action`, `Service`, `Repository`, `Input`, `Result` ou `Data`, `Controller`, `Request`, `Tool` selon la responsabilité réelle. En Vue : composants/pages `PascalCase.vue` ; modules et dossiers de fonctionnalités en `kebab-case` ; composables `useNom.ts` et fonctions d'appel nommées par opération. Garder les chemins techniques standard de Laravel/Vite. **Contrôle :** même notion, même vocabulaire et même casse ; éviter les noms `Manager`, `Helper` ou `Utils` sans responsabilité délimitée.

**R02-009 — Raccorder explicitement.** Déclarer le provider du module dans `bootstrap/providers.php`. Dans `register()`, lier les interfaces nécessaires à leurs implémentations ; injecter ensuite le contrat par constructeur chez son consommateur. Une classe concrète résoluble par Laravel ne demande pas de binding ni d'interface artificielle. Dans `boot()`, charger routes/migrations et enregistrer les autres raccordements. Affecter explicitement les middlewares des routes modulaires selon R08 : le nom `web.php` ne leur ajoute pas le groupe `web`.

**Illustration de raccordement —** dans `Providers/DiningServiceProvider.php`, chemins relatifs au provider :

| Emplacement | Exemple ou responsabilité |
| --- | --- |
| `register()` | `$this->app->bind(Contract::class, Implementation::class);` — noms illustratifs, seulement lorsqu'un contrat existe ; le consommateur injecte `Contract`. |
| `boot()` | `$this->loadRoutesFrom(__DIR__.'/../Routes/web.php');` puis `$this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');` si ces ressources existent. |
| `bootstrap/providers.php` | Ajouter `App\Modules\Dining\Providers\DiningServiceProvider::class` au tableau existant. |

L'assemblage global reste mince ; ne pas copier les migrations métier dans le dossier central. Déclarer aussi la résolution des pages, factories, policies et traductions hors conventions du framework. **Contrôle :** résoudre les dépendances, vérifier routes/middlewares et découverte des migrations sur le projet, y compris après mise en cache des routes ; un dossier ne réalise aucun enregistrement.

**R02-010 — Placer les responsabilités annexes auprès de leur propriétaire.** Appliquer cette table et les critères d'extraction de la partie 5 ; ne pas créer un dossier global pour chaque type de fichier.

| Responsabilité | Emplacement et condition |
|---|---|
| Modèles, enums internes, policies | Sous-domaine propriétaire, catégories `Models/`, `Enums/`, `Policies/` ; la policy exprime les droits, pas une seconde version du cas d'usage. Les enums/types exposés appartiennent au contrat public. |
| Service ou accès réellement commun | Catégorie `Services/` ou `Repositories/`, subdivisée par responsabilité au plus petit propriétaire commun. |
| Job, listener, événement interne | `Jobs/`, `Listeners/` ou `Events/` auprès de l'usage propriétaire ; extraire seulement si responsabilité commune. |
| Contrat ou fait exposé à un autre module | `PublicApi/<Sujet>/` du module propriétaire ; détails de messages et de fiabilité dans R04/R10. |
| Adaptateur fournisseur propre au métier | `Integrations/<Capacite>/` auprès de l'usage ou du sous-domaine concerné ; mécanisme générique commun dans `app/Infrastructure/`. |
| Schéma et données d'essai | `Database/Migrations/`, `Factories/`, `Seeders/` dans le module ; tables techniques transversales dans `database/`, avec propriétaire déclaré. |
| Configuration et traductions | Configuration technique commune dans `config/` ; réglages et traductions spécifiques auprès du module, chargés explicitement. |
| Types/ressources générés | Emplacement déclaré auprès du consommateur ; source et commande de génération identifiables, sans modifications manuelles concurrentes. |

**Illustration — Module et usages.** Le service d'éditabilité porte une règle commune ; le service de cuisson garde le traitement spécifique.

```text
app/Modules/Dining/
├── PublicApi/Orders/…
├── Providers/DiningServiceProvider.php
├── Routes/…
├── Database/
│   ├── Migrations/…
│   ├── Factories/…
│   └── Seeders/…
└── Orders/
    ├── Models/OrderItem.php
    ├── Services/Editability/OrderItemEditabilityService.php
    └── UseCases/
        ├── ChangeCooking/
        │   ├── ChangeCookingAction.php
        │   ├── ChangeCookingService.php
        │   ├── ChangeCookingRepository.php
        │   ├── ChangeCookingInput.php
        │   ├── ChangeCookingResult.php # Seulement si des données de retour sont utiles
        │   ├── Http/…                 # Controller et Request
        │   └── Ai/…                   # Tool vers la même action
        └── ChangeQuantity/…
```

## 4. Communications entre modules

**R02-011 — Passer par les contrats publics.** Un module consomme les opérations et types explicitement exposés dans le `PublicApi` du propriétaire. Ce nom désigne un contrat PHP intermodule, pas une route Internet. Les contrats publics ne dépendent pas de classes internes ; ne pas exposer modèle Eloquent, relation, builder ou chargement différé donnant accès à la persistance. Une implémentation interne peut satisfaire directement un contrat public sans façade supplémentaire obligatoire. **Contrôle :** examiner imports et types imbriqués des entrées/sorties, pas seulement le premier DTO.

**R02-012 — Déclarer les dépendances et l'orchestrateur.** Garder acyclique le graphe des dépendances de code entre modules ; les interfaces réciproques ne suppriment pas un cycle. Une orchestration transverse a un propriétaire explicite, porte le cas d'usage et utilise les contrats autorisés ; l'assemblage technique ne doit pas absorber ce métier. Examiner aussi les chaînes de réactions événementielles pour prévenir les boucles involontaires. **Contrôle :** confronter dépendances effectives, graphe déclaré et scénarios transversaux.

**R02-013 — Adapter les échanges au besoin.** Utiliser un appel explicite pour demander une opération ou obtenir un résultat ; un événement décrit un fait survenu. Ne pas imposer de bus ou d'asynchronisme universel. Les contrats de lecture exposent des projections bornées et permettent les lots lorsque le consommateur traite plusieurs éléments. Ne pas contourner la frontière pour éviter un contrat mal adapté, ni créer un appel par élément sans examiner le coût. **Contrôle :** forme des données, limites/volumes et nombre d'appels dans le parcours ; garanties détaillées dans R04/R06/R07/R10.

**R02-014 — Encadrer les lectures transversales exceptionnelles.** L'accès direct aux tables d'un autre module, même par SQL brut, n'est pas une variante libre. Toute projection ou lecture transversale nécessitant cet accès demande une décision explicite selon R01 : besoin, propriétaire, contrat de schéma, consommateurs, dépendances, isolation et garanties de performance avec R05/R06/R08. Ne pas transformer cette décision en autorisation d'écrire les données du voisin ou d'affaiblir une exigence essentielle. **Contrôle :** décision approuvée retrouvable et portée respectée ; sinon utiliser le contrat public.

## 5. Partage et évolution

**R02-015 — Extraire au bon niveau.** Une responsabilité reste locale tant qu'elle appartient à un usage. La partager seulement si son sens, ses conditions et ses raisons d'évoluer sont communs ; choisir leur plus petit propriétaire commun. Éviter de dupliquer un invariant métier ; accepter des lectures distinctes pour des consommateurs qui évoluent indépendamment. **Contrôle :** justifier l'extraction autrement que par le nombre d'appels ou la ressemblance de quelques lignes.

**R02-016 — Limiter le socle partagé.** `Shared` contient des concepts communs explicitement admis et ne dépend d'aucun module. `Infrastructure` contient des mécanismes techniques transversaux, sans importer les détails métier des modules. Un adaptateur connaissant un contrat métier appartient au propriétaire de cette intégration ; son raccordement peut être déclaré par un provider. Ni l'un ni l'autre ne sert à masquer une dépendance circulaire. **Contrôle :** propriétaire, consommateurs et dépendances de chaque ajout.

**R02-017 — Revoir la segmentation lorsqu'elle se dégrade.** **Revue :** examiner une classe modifiée pour des raisons indépendantes, un dossier accumulant des groupes sans rapport, une dépendance qui s'étend ou une abstraction remplie de cas particuliers. Décider d'extraire, renommer ou maintenir avec justification dans le travail approuvé. Ne pas découper pour atteindre un quota de lignes/fichiers, ni raccourcir au détriment de la lisibilité. **Contrôle :** résultat de revue et responsabilité restante explicites.

**Illustration — Portée du partage et frontière.**

```text
app/
├── Modules/Dining/
│   ├── PublicApi/Orders/…                # Surface intermodule
│   └── Orders/
│       ├── Services/Editability/…        # Commune au sous-domaine
│       └── UseCases/ChangeCooking/…       # Locale à l'usage
├── Shared/…                              # Concepts communs admis
└── Infrastructure/…                      # Mécanismes techniques transversaux
```

Trajet autorisé : **module appelant → contrat public propriétaire → implémentation interne → résultat explicite**. La présence de `PublicApi/` ne rend pas publics les fichiers voisins.

## 6. Vue, TypeScript et assets

**R02-018 — Séparer pages et fonctionnalités.** Les pages Inertia de `resources/js/pages/<Audience>/<Parcours>/` assemblent des fonctionnalités ; la coordination propre au parcours reste auprès de celui-ci. Les fonctionnalités vivent dans `resources/js/modules/<module>/<sous-domaine>/<usage>/`, avec une correspondance métier explicite au backend, sans reproduire mécaniquement tous ses fichiers. Composants : affichage/interactions ; composables : responsabilités réactives ciblées ; `requests/` : échanges réseau ; `contracts/` : données échangées ; `types/` : état local. **Contrôle :** chaque comportement a un emplacement identifiable ; aucun module n'importe une page pour réutiliser son métier.

**R02-019 — Exposer une surface frontend limitée.** Les pages et modules consommateurs utilisent les exports explicitement prévus dans `modules/<module>/public/<sujet>.ts`, sans réexporter tout le module. À l'intérieur d'un module, une fonctionnalité peut importer ses fichiers et les responsabilités communes de son propriétaire ; ne pas coupler librement les détails privés de deux usages. Les contrats serveur ne sont pas réinventés séparément pour chaque écran ; leur génération ou vérification relève de R04/R12. Ne pas déplacer une page géante dans un composable géant ; extraire par responsabilité. **Contrôle :** imports, propriétaires d'état et responsabilités ; les exports ne doivent pas entraîner le chargement général de ressources lourdes.

**R02-020 — Placer et charger les assets selon leur propriétaire.** Les styles simples peuvent rester dans le composant Vue ; extraire les feuilles spécifiques auprès du composant, de la page ou de la fonctionnalité propriétaire. Le commun d'un usage ou module reste à ce niveau ; seules les fondations visuelles transversales vont dans `resources/css/`, segmentées par responsabilité (tokens, fondations, thèmes si nécessaires). `app.css` assemble ce socle sans agréger les feuilles particulières des fonctionnalités. Les éléments UI génériques et leurs styles restent dans `resources/js/shared/`. Ne créer ni feuille vide ni arborescence CSS miroir par principe. Chaque propriétaire référence ses styles ; leur découverte suit les imports, sans inscription de chaque nouveau composant dans la configuration Vite. La découverte des pages suit un motif stable ; elle ne justifie pas l'import immédiat de toutes les fonctionnalités. Les fichiers téléversés et produits à l'exécution vont dans le stockage applicatif, pas dans les sources. **Contrôle :** propriétaire et import identifiables ; ajout ordinaire dans l'arborescence sans modification du registre d'entrées Vite ; segmentation, portée des sélecteurs et chargement effectif vérifiés séparément. R12/R14 précisent les mécanismes et leur qualification ; aucune bibliothèque CSS n'est adoptée par cette prescription.

**Illustration — Ressources et fonctionnalité correspondante.**

```text
resources/
├── css/
│   ├── app.css
│   ├── tokens/…
│   ├── foundations/…
│   └── themes/…                         # Seulement si nécessaire
└── js/
    ├── pages/Guest/Meal/
    │   ├── Show.vue
    │   └── meal-page.css                # Si extraction utile
    ├── shared/ui/Button/
    │   ├── Button.vue
    │   └── button.css                   # Si extraction utile
    └── modules/dining/
        ├── public/meal.ts
        └── orders/change-cooking/
            ├── components/CookingPicker/
            │   ├── CookingPicker.vue
            │   └── cooking-picker.css  # Référencé par ce composant
            ├── styles/…                # Commun à cet usage, si besoin
            ├── composables/useChangeCooking.ts
            ├── requests/changeCooking.ts
            ├── contracts/change-cooking.ts
            └── types/cooking-form.ts
```

## 7. Tests et preuves architecturales

**R02-021 — Retrouver les tests depuis la responsabilité.** Reprendre module, sous-domaine et usage dans les chemins des tests PHP. `Unit` couvre les comportements isolés ; `Integration` les interactions réelles avec données/services ; `Feature` les points d'entrée applicatifs ; `Architecture` les frontières ; `Browser` les parcours d'interface. Les tests Vue/TypeScript locaux sont voisins du code testé, nommés `*.spec.ts` ; les parcours transversaux restent séparés. **Contrôle :** trouver les preuves associées sans deviner un second classement ; aucun type de test n'est obligatoire uniquement pour remplir l'arbre.

**R02-022 — Vérifier plus que le rangement.** Prévoir contrôles des imports, cycles, contrats publics, emplacements et raccordements, complétés par des tests ciblés de comportement et d'accès aux données. Un contrôle d'import ne détecte pas nécessairement le SQL brut, les dépendances dynamiques ou les requêtes d'une relation. **Contrôle :** distinguer ce qui est automatisé, revu manuellement ou non vérifié selon R01/R18 ; ne jamais présenter les contrôles futurs comme installés.

**Illustration — Correspondance des tests.**

```text
tests/
├── Architecture/…
├── Unit/Modules/Dining/Orders/ChangeCooking/…
├── Integration/Modules/Dining/Orders/ChangeCooking/…
├── Feature/Modules/Dining/Orders/ChangeCooking/…
└── Browser/Guest/…
```

Le test local de `useChangeCooking.ts` reste auprès de ce composable. Cette organisation ne choisit ni bibliothèque de test ni quota de couverture.

## 8. Ajouter, extraire ou déplacer

**R02-023 — Suivre le même parcours de conception.** Dans le plan approuvé selon R01 :

1. Identifier propriétaire métier, données, consommateurs et règles applicables.
2. Choisir l'usage, les responsabilités nécessaires et les contrats.
3. Vérifier dépendances, impacts transversaux et besoin réel de partage.
4. Placer les fichiers selon la grammaire et déclarer leurs raccordements.
5. Vérifier comportements, frontières, requêtes induites et consommateurs affectés.
6. Actualiser la cartographie propriétaire, contrats et repères utiles ; rendre les preuves et limites retrouvables.

**Contrôle :** pouvoir suivre ce parcours pour une création comme pour une correction structurelle, sans nouvelle approbation par fichier dans le périmètre acquis.

**R02-024 — Préserver les contrats lors d'un déplacement.** Examiner imports, bindings, routes, résolution des pages, migrations, factories, types générés et consommateurs HTTP/IA/asynchrones concernés. Préserver ou faire évoluer explicitement les contrats existants ; ne pas traiter un déplacement comme une autorisation de réécrire l'historique des migrations ou de casser les tâches déjà en attente. **Contrôle :** recherche des consommateurs et vérifications adaptées ; R04/R05/R10/R21 possèdent les modalités de compatibilité.

Pour un mécanisme non fixé ici, consulter sa règle propriétaire et la décision technique correspondante avant l'implémentation dépendante. Une convention de rangement ou son illustration ne vaut pas adoption d'une bibliothèque ou d'un protocole.
