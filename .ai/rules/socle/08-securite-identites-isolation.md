# R08 — Sécurité, identités et isolation

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r08).

**Lire pour :** créer ou modifier un accès HTTP/IA/job/console, une permission, une session, un contexte d'accès ou d'isolation, un canal, un fichier protégé ou un secret. **Repères :** modules propriétaires sous `app/Modules/`, raccordements dans leurs providers et `bootstrap/`, mécanismes transversaux sous `app/Infrastructure/`, selon [R02](02-architecture-modules.md).

Les prescriptions `R08-001` à `R08-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). [R03](03-php-laravel-metier.md) possède les invariants métier, [R04](04-contrats-dto-validation.md) la forme des contrats, [R09](09-confidentialite-cycle-donnees.md) les données admissibles et [R22](../index.md#r22) les erreurs. Consulter les compléments selon l'impact et leur statut.

**Portée :** procédures d'implémentation ; droits effectifs, paramètres et parcours sont définis dans les [références de conception](../index.md#references-conception). Les exemples illustrent les mécanismes sous des hypothèses explicites, sans définir le produit ni constituer des preuves exécutées.

## 1. Définir acteur, droit et périmètre

**R08-001 — Décrire l'accès à implémenter.** Relever dans les spécifications l'opération, les acteurs admis, les ressources, champs et périmètres accessibles, puis identifier la preuve requise et les conditions de retrait. Distinguer compte, invité autorisé, appareil et traitement système selon le parcours. Une identité authentifiée ne prouve pas un droit ; l'absence de compte n'exclut pas une autre preuve d'accès. La policy applique la matrice produit sans en inventer les droits.

**R08-002 — Résoudre l'autorité côté serveur.** Partir d'une session, d'un appareil ou d'une identité de traitement vérifiés pour construire le contexte de l'opération. Un identifiant d'URL, un DTO valide, une propriété Vue ou une instruction IA ne prouvent aucun droit. Résoudre séparément l'acteur, le périmètre d'accès et les ressources concernés par l'opération ; vérifier leurs relations et leur état courant avant accès. Les lectures nécessaires à cette résolution ont leur propre périmètre d'identité/session ; elles n'exigent pas d'ouvrir d'abord toutes les données métier. L'IA reçoit des capacités déléguées, jamais une autorité supérieure à celle du parcours qu'elle sert.

**R08-003 — Appliquer des permissions nommées et bornées.** Associer chaque opération à une capacité anglaise stable et à son périmètre dans la matrice de droits propriétaire. Les rôles regroupent les permissions ; ils ne remplacent pas le contrôle de ressource et n'étendent pas une capacité aux autres. Refuser par défaut une capacité inconnue ou non accordée. Lors d'une attribution, vérifier le droit de déléguer la permission et son périmètre ; couvrir invitation, changement de rôle et auto-promotion.

**R08-004 — Qualifier la portée des données.** Distinguer données internes à un périmètre d'isolation, données personnelles d'un compte, projections publiques et données globales. Définir pour la ressource modifiée son accès, ses destinataires et champs exposables d'après les références de conception. Ni publication d'une projection, ni propriété d'une donnée, ni absence de clé d'isolation ne donne un accès général aux tables internes.

## 2. Appliquer la même procédure d'autorisation

**R08-005 — Placer la décision dans le propriétaire.** Utiliser une policy pour les opérations d'une ressource, y compris `viewAny` et `create` sans instance ; une gate nommée convient à une capacité sans ressource propriétaire. Ranger la policy auprès du sous-domaine et l'enregistrer avec `Gate::policy()` dans le provider du module, raccordé selon R02. Enregistrer les gates nommées de même façon. Ne pas présumer la découverte automatique dans l'arbre modulaire ; tester l'enregistrement réel.

**R08-006 — Suivre le parcours commun.** Pour chaque nouvelle opération protégée :

1. Établir le contexte serveur et les restrictions générales propres à l'entrée.
2. Autoriser, si nécessaire, une lecture préparatoire minimale dans ce périmètre ; le repository charge seulement l'état utile. Une recherche sans filtre suivie d'un refus tardif ne convient pas.
3. Dans l'action, appeler `Gate::authorize()` ou `Gate::forUser($actor)->authorize()` pour l'opération et la ressource. Gate invoque la policy enregistrée ; ne pas dupliquer sa décision dans un second moteur.
4. Exécuter le métier, les écritures et les effets selon R03/R07. Droit d'agir et validité de l'état métier restent deux questions distinctes.
5. Tester accès permis, refus et absence d'effet, directement sur le cas d'usage puis par ses entrées.

Pour une lecture sans action selon R02-006, l'adaptateur assure ces contrôles ; ne pas ajouter une action vide ni dupliquer la séquence entre entrées. Les policies décident sur des données fournies explicitement, sans SQL ni relation paresseuse. Un droit de lister n'autorise pas une requête non bornée ; R06 conserve le contrat de lecture.

**R08-007 — Raccorder les protections HTTP.** Charger les routes depuis `Routes/` du module par son provider selon R02-009 ; leur affecter les middlewares requis. Déclarer les éventuels alias/groupes et priorités dans `bootstrap/app.php` via `withMiddleware()` ; documenter l'ordre lorsqu'il conditionne session, contexte ou binding. Appliquer `auth`, vérification d'email et exigences de session selon l'audience, sans bloquer mécaniquement les invités. `can` et `FormRequest::authorize()` peuvent refuser tôt en réutilisant la même décision ; ils ne remplacent pas R08-006. Le chargement explicite d'un identifiant par repository est le parcours de référence. **Variante :** binding de modèle seulement si la lecture, son périmètre et son contexte RLS actif sont prouvés au moment du binding, pas seulement plus tôt dans un middleware.

**R08-008 — Rendre les acteurs explicites hors HTTP.** Passer le compte vérifié à `Gate::forUser()` plutôt que dépendre de la session ambiante dans un outil IA ou un job. Pour un invité, rendre le premier argument de la policy nullable et fournir la preuve d'accès vérifiée prévue pour le parcours : `null` seul n'accorde rien. Pour un traitement système, utiliser une autorité d'exécution identifiée et des capacités propres ; ne pas fabriquer un faux compte client ni faire passer n'importe quel DTO pour un utilisateur Laravel.

**R08-009 — Examiner les raccourcis.** Aucun droit essentiel ne dépend seulement d'un bouton caché, d'une prop Inertia ou d'un hook contournable. Un `before` qui retourne une décision peut empêcher l'exécution de la policy ; les autorisations inline Laravel ont aussi leurs particularités. **Revue :** tout hook global ou raccourci doit conserver les contrôles d'opération, de périmètre et de session requis. Ne pas introduire un `before` universel accordant tout aux administrateurs. L'interface utilise les capacités serveur pour guider l'affichage, puis le serveur revérifie à l'action.

<a id="exemple-autorisation"></a>
**Illustration A — Autoriser une mutation partagée par HTTP et IA.** Hypothèse : un invité dispose d'un accès de session à un repas. Cet extrait complète R03/R04. Avant l'appel, le résolveur serveur vérifie preuve de session, repas actif et version d'accès ; il produit `MealAccess`, sans accepter les droits déclarés par l'entrée. L'acteur éventuel passé à l'action provient du même contexte serveur vérifié, pas d'un paramètre client. La synchronisation avec clôture/révocation concurrente reste à assurer selon R07.

```text
bootstrap/providers.php
app/Infrastructure/Database/RestaurantSqlScope.php
app/Modules/Dining/
├── Providers/DiningServiceProvider.php
├── Routes/web.php
├── Meals/Access/MealAccess.php
└── Orders/
    ├── Models/OrderItem.php
    ├── Policies/OrderItemPolicy.php
    └── UseCases/ChangeCooking/
        ├── ChangeCookingInput.php
        ├── ChangeCookingAction.php
        ├── ChangeCookingRepository.php
        ├── ChangeCookingService.php
        ├── Http/ChangeCookingController.php
        └── Ai/ChangeCookingTool.php
```

Dans cet exemple, le provider charge les routes sous `web`, avec session/CSRF et résolution d'accès ; `auth` n'est pas requis pour l'invité. Le contrôleur reçoit un identifiant d'article sans binding implicite. HTTP/IA valident l'entrée selon R04 et appellent la même action. Restaurant/repas de `ChangeCookingInput` viennent du contexte serveur ; la policy vérifie leur correspondance.

```php
// Meals/Access/MealAccess.php
final readonly class MealAccess
{
    public function __construct(
        public string $restaurantId,
        public string $mealId,
        public bool $canReadOrders,
        public bool $canChangeOrders,
    ) {}
}

// DiningServiceProvider::boot(), avec ses autres raccordements
Gate::policy(OrderItem::class, OrderItemPolicy::class);
// bootstrap/providers.php inclut DiningServiceProvider::class.

// OrderItemPolicy : méthodes, sans accès à la base
public function viewAny(
    ?Authenticatable $actor,
    MealAccess $access,
    string $restaurantId,
    string $mealId,
): bool {
    return $access->canReadOrders
        && $restaurantId === $access->restaurantId
        && $mealId === $access->mealId;
}

public function changeCooking(
    ?Authenticatable $actor,
    OrderItem $item,
    MealAccess $access,
): bool {
    return $access->canChangeOrders
        && (string) $item->restaurant_id === $access->restaurantId
        && (string) $item->meal_id === $access->mealId;
}

// ChangeCookingAction : dépendances injectées, même connexion pgsql
public function execute(
    ChangeCookingInput $input,
    MealAccess $access,
    ?Authenticatable $actor,
): void {
    DB::connection('pgsql')->transaction(function () use ($input, $access, $actor): void {
        Gate::forUser($actor)->authorize('viewAny', [
            OrderItem::class, $access, $input->restaurantId, $input->mealId,
        ]);
        $this->scope->activate($access->restaurantId);
        $item = $this->repository->findForUpdate($input);
        Gate::forUser($actor)->authorize('changeCooking', [$item, $access]);
        $this->service->changeCooking($item, $input->cooking);
        $this->repository->save($item);
    });
}
```

`Authenticatable` est le contrat Laravel, `Gate`/`DB` ses façades. Imports, constructeurs, résolution de session et réponses sont omis. Ici, le droit vient du contexte de repas ; un compte éventuel ne l'élargit pas. `viewAny` reçoit les identifiants utiles, sans dépendre de la commande de cuisson ; leur représentation commune suit R04.

`findForUpdate()` est celui de R03 : filtres `restaurant_id`, `meal_id`, identifiant d'article, projection `id/restaurant_id/meal_id/accepted_at/cooking` et verrou. Modèle, repository et portée SQL emploient `pgsql`. Le service refuse un article accepté ; la policy n'en duplique pas la règle métier. `save()` reste dans le repository. `RestaurantSqlScope::activate()` est montré en B. Tester un invité autorisé, un contexte révoqué avant résolution, un article étranger et l'appel direct de l'action, sans remplacer Gate par un mock qui autorise tout.

## 3. Gérer comptes, sessions et appareils

**R08-010 — Réutiliser le socle d'identité.** Pour un parcours de compte, raccorder les mécanismes Fortify retenus aux adaptateurs d'interface, sans doubler leur implémentation. Appliquer les facteurs et conditions d'accès définis dans la référence technique. Utiliser le hachage Laravel pour mots de passe ou codes personnels, un stockage protégé adapté pour les secrets devant rester utilisables et les mécanismes natifs pour les jetons ; aucune cryptographie maison. Borner les tentatives, prévenir l'énumération et vérifier routes, notifications et chemins de récupération concernés.

**R08-011 — Protéger le cycle des sessions.** Régénérer l'identifiant à la connexion et aux changements de privilège pertinents ; invalider côté serveur à la déconnexion/révocation. Configurer HTTPS, cookies `Secure`/`HttpOnly`, `SameSite` adapté et CSRF pour les mutations par cookies ; aucun jeton de session dans URL ou logs. Charger durées, inactivité et réauthentification depuis la politique prévue pour l'audience, sans les disperser dans les écrans. Invalider aussi les caches de droits concernés.

**R08-012 — Préserver le niveau d'authentification requis.** Avant une opération ou une élévation de privilège, vérifier les facteurs exigés par sa politique d'accès. Appliquer cette exigence aux chemins de remplacement/perte d'un facteur et de récupération : un reset de mot de passe ou une intervention ne doit pas l'abaisser implicitement. Identifier les preuves et acteurs habilités, tracer les opérations sensibles et tester accès direct, enrôlement et secours selon le parcours retenu.

**R08-013 — Séparer appareil et opérateur.** Quand un parcours utilise un appareil partagé, vérifier séparément son autorisation et l'identité de l'opérateur ; attribuer chaque action à son auteur réel. Implémenter le verrouillage côté serveur selon le délai de référence : seuls les événements qualifiés comme interaction le prolongent, pas les heartbeats ni mises à jour reçues. Au verrouillage, changement d'opérateur ou retrait de l'appareil, réévaluer lecture et mutation selon leurs droits propres ; ne pas déduire le droit d'agir du droit d'afficher.

**R08-014 — Séparer point d'entrée et preuve d'accès.** Un lien public peut identifier une ressource sans autoriser son contenu. Résoudre la preuve prévue avant d'établir le contexte de session ; ne pas déduire une présence physique de la possession d'une URL. Lors d'un changement de ressource, de rattachement ou de version d'accès, réévaluer sessions et capacités sans transférer silencieusement les anciens droits. Tester ces changements et documenter les limites du mécanisme choisi dans sa conception.

**Illustration C — Vérifier une transition d'accès.** Appliquer les paramètres du parcours concerné, puis tester les protections suivantes.

| Événement | Traitement à vérifier |
| --- | --- |
| Droit révoqué ; connexion encore ouverte | Refuser actions et abonnements devenus interdits ; arrêter aussi les diffusions concernées. |
| Ressource réaffectée | Invalider l'ancien périmètre ; aucun accès automatique à la nouvelle ressource. |
| Seuil d'inactivité atteint | Appliquer le verrou côté serveur ; lecture et mutation suivent leurs autorisations distinctes. |
| Changement d'opérateur ; appareil retiré | Attribuer les actions au nouvel auteur ; retirer toutes les capacités dont la preuve dépend de l'appareil révoqué. |

Une révocation arrête les accès futurs ; elle n'efface pas ce qui a déjà été vu et ne défait pas un effet métier acquis. Pour une requête en cours, définir le point de contrôle et la synchronisation avec R07 ; ne pas promettre l'annulation rétroactive.

## 4. Isoler la base et les autres supports

**R08-015 — Cumuler contrôle applicatif et RLS.** Pour les tables internes partagées entre périmètres d'isolation, filtrer le périmètre dans les repositories et appliquer les politiques PostgreSQL à visibilité et nouvelles valeurs (`USING`/`WITH CHECK`). Vérifier liens et écritures entre périmètres avec R05. RLS ne décide pas seule du droit métier ou de la confidentialité d'un champ ; les portées publiques/personnelles/globales ont leurs politiques explicites.

**R08-016 — Utiliser le vrai rôle restreint.** Exécuter l'application et ses tests d'isolation avec un rôle sans superuser, sans `BYPASSRLS`, non propriétaire des tables, ni appartenance permettant d'obtenir ces privilèges. Séparer migrations et sauvegardes sous identités dédiées. Vérifier droits SQL et activation effective de RLS : tester comme propriétaire peut masquer une politique défectueuse. Les opérations administratives complètes ne doivent ni produire une sauvegarde partielle ni prêter leurs identifiants au runtime ordinaire.

**R08-017 — Établir le contexte avant toute requête protégée.** À partir du contexte serveur vérifié, ouvrir la transaction pertinente, installer la portée sur la même connexion, puis lire/écrire ; les lectures protégées sont concernées aussi. Limiter ce réglage à la transaction, refuser contexte absent/invalide et ne jamais revenir à un accès global par défaut. Une opération imbriquée ne change pas silencieusement de périmètre d'isolation ; une autre connexion exige son propre cadre. Les gardes et frontières détaillées appartiennent à R07.

**Illustration B — Portée PostgreSQL locale.** Méthode de `RestaurantSqlScope`, appelée à l'intérieur de la transaction de A ; `restaurantId` est déjà vérifié. Cet exemple utilise la connexion nommée `pgsql`, comme le modèle et l'action.

```php
public function activate(string $restaurantId): void
{
    DB::connection('pgsql')->selectOne(
        "select set_config('app.restaurant_id', ?, true)",
        [$restaurantId],
    );
}
```

Le troisième argument `true` limite le réglage à la transaction. La politique d'une table illustrative à identifiants UUID peut comparer `restaurant_id` à `NULLIF(current_setting('app.restaurant_id', true), '')::uuid` dans `USING` et `WITH CHECK`. Sans valeur, aucune ligne ne doit être autorisée ; une valeur malformée doit échouer, jamais ouvrir le périmètre. Ce réglage n'authentifie pas le restaurant : l'application demeure responsable de sa provenance. R05 portera les migrations complètes et droits SQL, R07 la garde de transaction ; aucun de ces extraits n'est une politique déjà installée.

**Recette sur une connexion réutilisée :** transaction A → seulement A ; transaction B → seulement B ; transaction sans contexte → aucun accès interne ; exception/rollback de A puis B → aucun reliquat de A. Tester lecture, insertion, mise à jour, suppression et changement de `restaurant_id`, sous le rôle R08-016. Ne conserver aucun contexte de tenant dans un singleton, une variable globale ou un réglage SQL de session utilisé comme défaut.

**R08-018 — Refaire l'isolation hors SQL.** Identifier toutes les copies/sorties de données : cache, événement, canal, fichier, PDF, export, index et props. Définir clé/périmètre et contrôle d'accès pour chacune. Une clé préfixée n'est pas une autorisation ; une lecture RLS correcte n'autorise pas sa rediffusion à d'autres destinataires. Ne pas sérialiser automatiquement tout un modèle dans un événement ou une réponse.

**Illustration D — Même donnée, trois protections.**

| Support | Parcours requis |
| --- | --- |
| Cache des commandes | Autoriser le lecteur ; utiliser une clé tenant/repas/projection/version pertinente ; ne partager que les champs permis à tous les destinataires de cette entrée. |
| Canal du repas | Autoriser l'abonnement privé et son périmètre ; à révocation, empêcher les futures livraisons aux anciens abonnés, pas seulement les prochaines connexions. R11 définit le protocole. |
| Note PDF/export | Stocker en privé ; autoriser génération et téléchargement/envoi. Une URL temporaire est un accès transférable jusqu'à son expiration : définir durée et révocation selon le besoin, sans la traiter comme une nouvelle vérification d'identité. |

## 5. Protéger les exécutions différées

**R08-019 — Donner au traitement sa propre autorité.** Déclarer dans le contrat du job opération, périmètre, auteur historique et autorité d'exécution, sans secret de session ni donnée exclue d'après R09. Déterminer si le traitement prend une nouvelle décision, exigeant les droits actuels, ou achève un engagement acquis sous une capacité système bornée. Vérifier le contrat de l'opération avant de refuser ou poursuivre après révocation ; ne pas confondre son auteur passé avec son autorité présente.

**R08-020 — Réinitialiser puis résoudre avant accès.** À chaque job/listener/commande planifiée, établir l'autorité et la portée avant toute lecture, puis libérer le contexte même en échec. Examiner la restauration automatique des modèles sérialisés : elle peut lire avant `handle()` ou son middleware. Préférer identifiants et faits autorisés, puis résolution explicite sous contexte ; **variante** seulement si la restauration elle-même est protégée et testée. La reprise ne réutilise pas une session utilisateur supposée encore ouverte. R10 possède publication et répétition fiables.

## 6. Protéger frontières et secrets

**R08-021 — Traiter chaque entrée comme non fiable.** Valider selon R04, autoriser, puis utiliser paramètres SQL liés et noms de colonnes/tri issus d'une liste admise. Affecter explicitement les champs éditables ; empêcher toute modification implicite de permission, de périmètre ou de valeur faisant autorité. Conserver l'échappement Vue ; tout HTML rendu volontairement exige une sanitisation adaptée. Traiter contenus utilisateurs et fournisseurs comme des données, jamais comme des instructions accordant de nouveaux droits aux outils IA.

**R08-022 — Fermer les frontières réseau et fichiers.** Pour un upload, vérifier autorisation, taille, type réel et traitement prévu ; générer son chemin, éviter l'exécution et servir selon son exposition autorisée. Pour une URL fournie à télécharger, contrôler destination, redirections et accès aux adresses internes/métadonnées ; appliquer limites et délais. Pour un webhook, vérifier authenticité et protections contre rejeu avant effet, sans confondre CORS avec authentification. R14/R17 détaillent stockage et intégrations ; toute exception CSRF est limitée au point d'entrée ayant sa protection équivalente.

**R08-023 — Borner les abus et coûts.** Configurer les limiteurs Laravel aux entrées pertinentes, par exemple authentification, récupération de compte, accès publics, mutations, uploads et outils coûteux. Combiner les dimensions utiles (acteur, appareil, périmètre, opération), sans bloquer inutilement les utilisateurs partageant une adresse IP. Les quotas, tailles et délais ont une source identifiable et une réponse contrôlée ; leurs valeurs sont à qualifier. Protéger aussi les endpoints directs et consommations IA, pas seulement les clics de l'interface.

**R08-024 — Maîtriser les secrets et diagnostics.** Garder les secrets côté serveur, hors dépôt, builds publics, payloads, prompts et logs ; toute variable exposée par Vite est publique. Séparer environnements, droits de lecture, rotation et révocation ; vérifier debug, sauvegardes et interfaces d'exploitation. En production, désactiver le debug exposé et restreindre ces interfaces et transports internes. Restituer une erreur sûre selon R22 ; appliquer aux diagnostics les exclusions et accès définis par R09/R19, y compris dans les outils de supervision.

## 7. Implémenter les opérations privilégiées et leurs preuves

**R08-025 — Contrôler une opération privilégiée.** Identifier sa permission et son périmètre dans la matrice d'habilitations, puis appliquer R08-005/009 dans le cas d'usage, quel que soit le rôle de l'opérateur. Conserver auteur réel et niveau d'authentification requis ; si une identité est représentée, la distinguer de l'opérateur. Tracer selon R08-026 et tester acteur habilité, droit insuffisant et ressource hors périmètre. Une permission applicative étendue ne justifie ni rôle SQL privilégié ni bypass global.

**R08-026 — Implémenter et tracer une correction exceptionnelle.** Partir de l'opération de réparation prévue : nommer préconditions, permission, effets et contrôle d'intégrité ; réutiliser le parcours ordinaire s'il convient. Ne pas disperser des `isAdmin` supprimant les invariants ni fabriquer de faits non établis. Tracer action, auteur, périmètre, motif utile, horodatage, corrélation et issue ; avant/après seulement pour les champs admissibles. Protéger l'audit contre modification par les acteurs audités et limiter son accès selon R09/R19.

**R08-027 — Relier le changement aux exigences vérifiables.** Consulter le référentiel, sa version et le niveau retenus dans la référence technique. Dans sa matrice de vérification, relier exigence applicable, implémentation, test/preuve et écart ; motiver les non-applicabilités. Actualiser le modèle de menace et les lignes affectées quand une frontière change. Une case non vérifiée n'est pas non applicable ; aucune conformité globale sans preuve. R18/R21 organisent exécution et livraison.

**R08-028 — Prouver les refus autant que les succès.** Pour le périmètre modifié, sélectionner les scénarios suivants et expliquer toute absence pertinente :

| Famille | Preuves attendues |
| --- | --- |
| Autorisation | Enregistrement réel de policy ; acteur permis/interdit ; appel direct d'action, HTTP et IA ; création/liste ; refus sans effet. |
| Périmètre | Autre périmètre d'isolation, autre ressource du même périmètre, objet deviné, champ masqué, contexte absent ; aucune donnée renvoyée ou écrite hors droit. |
| Cycle des accès | Délégation excessive, authentification renforcée/recours, logout, inactivité, retrait de permission et réaffectation de ressource avec ancienne session. |
| SQL/processus | Rôle restreint réel ; contexte A/B/absent/rollback ; écriture étrangère ; connexion/worker réutilisés, restauration de modèle ; après retrait d'un droit, nouvelle action refusée et engagement acquis achevé sous l'autorité prévue. |
| Copies et interventions | Cache/canal/fichier non accessibles après retrait pertinent ; opérateur limité refusé, opérateur habilité autorisé et réellement identifié. |
| Frontières | CSRF, entrées injectées, upload/URL/webhook abusifs, secrets et diagnostics ; protections natives réellement configurées. |

Simulations et SQLite ne prouvent pas RLS ; tests avec un rôle administrateur ne prouvent pas l'isolation runtime. Conserver les preuves exécutées, leurs conditions et les manques selon R01. Une matrice détaillée de produit ou une valeur de durée encore ouverte reste à décider avant son implémentation, pas à inventer dans le test.

**Repères primaires, consultés le 29 septembre 2026 :** [autorisation Laravel](https://laravel.com/framework/docs/13.x/authorization), [middlewares](https://laravel.com/framework/docs/13.x/middleware), [RLS PostgreSQL](https://www.postgresql.org/docs/current/ddl-rowsecurity.html), [OWASP ASVS](https://owasp.org/projects/asvs). Vérifier les API sur les versions effectivement retenues ; ces références étayent les mécanismes, sans remplacer les décisions du projet.
