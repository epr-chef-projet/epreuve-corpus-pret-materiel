# R07 — Transactions, concurrence et états

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r07).

**Lire pour :** créer, auditer ou modifier une mutation concurrente, transaction, transition d'état, réservation, reprise ou protection contre les doublons. **Repères :** action propriétaire de l'unité, services propriétaires des décisions, repositories propriétaires des accès, selon [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md).

Les prescriptions `R07-001` à `R07-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). **Procédure :** exigence → unité transactionnelle → protection de la décision → répétitions et échecs → preuves. L'appliquer aussi aux corrections de l'existant.

Les [références de conception](../index.md#references-conception) définissent états, transitions, priorités et effets attendus. R07 explique leur protection. [R04](04-contrats-dto-validation.md) possède les contrats, [R05](05-schema-migrations-historique.md) les garanties structurelles, [R06](06-requetes-lectures-cache.md) les lectures, [R08](08-securite-identites-isolation.md) les accès/RLS, [R09](09-confidentialite-cycle-donnees.md) l'application des politiques de données, [R10](10-jobs-evenements-fiabilite.md) la publication fiable, [R17](17-integrations-effets-externes.md) les effets externes et [R22](../index.md#r22) les erreurs. Consulter selon impact et statut ; un renvoi futur ne prouve aucun dispositif installé.

## 1. Définir l'opération et les conflits possibles

**R07-001 — Partir du contrat.** Retrouver préconditions, transitions et résultat attendus ; lister les écritures qui doivent réussir ensemble et le moment où l'effet devient acquis. Une ambiguïté de priorité ou de parcours se résout dans la spécification selon R01, pas par l'ordre accidentel d'exécution. Distinguer transition autorisée, état persistant et observation du monde extérieur : ni succès SQL ni notification ne prouve un effet physique.

**R07-002 — Inventorier les concurrents.** Identifier données susceptibles de changer entre lecture et mutation, puis tous leurs producteurs : autres actions, jobs, console, imports et corrections. Inclure suppression, clôture et révocation lorsque leur simultanéité change l'autorisation ou la décision. Définir avec R08 le point d'arbitrage et la coordination commune ; un contrôle préalable isolé ne garantit pas l'absence de course. Une révocation ne défait pas rétroactivement une opération déjà acquise.

**R07-003 — Séparer les protections.** Un état métier persiste après commit ; un verrou SQL disparaît à la fin de la transaction ; une version attendue détecte un état devenu différent ; une clé d'opération reconnaît une répétition. Employer le mécanisme correspondant au risque, éventuellement plusieurs. Ni bouton désactivé, ni ordre WebSocket, ni verrou mémoire d'un seul processus ne constitue une garantie SQL partagée.

## 2. Posséder la transaction et ses préconditions

**R07-004 — Déclarer l'unité atomique.** L'action ouvre `DB::connection($name)->transaction(...)` sur la connexion réellement utilisée par ses repositories, puis orchestre lectures protégées, décisions et écritures. Toute écriture participant au même résultat appartient à cette unité. Garder les appels intermodules dans les contrats R02 ; leur participation doit être explicite. Ne pas étendre par défaut la transaction à toute la requête HTTP, à la conversation ou à un appel réseau. Une écriture SQL unique déjà atomique n'exige pas une transaction explicite sans autre besoin de coordination ; le contexte RLS local reste un tel besoin selon R08.

**R07-005 — Garder les méthodes intrinsèquement transactionnelles.** Si une méthode n'est correcte que sous transaction, vérifier **avant son accès protégé** `transactionLevel() >= 1` sur la connexion de cet accès, puis échouer explicitement sinon. Exemples : lecture avec verrou réutilisable, activation d'un contexte RLS local. Ne pas tester seulement la connexion par défaut ni ouvrir dans cette méthode une transaction qui libérerait son verrou avant la décision appelante. Une méthode utilisable sans transaction ne reçoit pas cette garde mécaniquement. Le compteur prouve la présence d'une transaction Laravel, pas l'ensemble du protocole.

**R07-006 — Composer sans faux commit.** Identifier le propriétaire extérieur et les méthodes participantes ; éviter les transactions intérieures autonomes par habitude. Un appel composable qui utilise `transaction()` dans une transaction existante reste soumis à celle-ci : son retour ne constitue pas un commit durable. Les savepoints permettent un rollback partiel, pas un commit indépendant. Une transaction sur une seconde connexion n'est pas atomique avec la première ; si plusieurs ressources sont nécessaires, concevoir explicitement leur coordination et leur reprise, avec R17/R21.

**R07-007 — Maintenir le contexte SQL.** Installer le contexte RLS avant la première requête protégée sur la même connexion et dans l'unité propriétaire, selon R08. L'adaptateur vérifie transaction active et contexte déjà présent, par exemple via `current_setting(..., true)` ; il refuse un périmètre d'isolation différent dans un appel imbriqué ordinaire. `set_config(..., true)`/`SET LOCAL` dure jusqu'à la fin de la transaction extérieure : réussir ou libérer un savepoint ne restaure pas l'ancien contexte. Une orchestration multi-périmètres exige un protocole dédié et des preuves ; ne pas la simuler en changeant silencieusement le contexte ambiant.

**R07-008 — Laisser annuler l'unité concernée.** Toute erreur exigeant rollback doit sortir de la closure transactionnelle, puis être traitée à la frontière responsable R22. La capturer à l'intérieur pour retourner un résultat ordinaire peut valider des écritures partielles. Un rattrapage intermédiaire reste possible s'il rétablit explicitement l'état attendu, notamment par rollback du savepoint prévu. Après une erreur SQL PostgreSQL ayant invalidé la transaction, ne pas poursuivre les requêtes sans rollback approprié. Conserver la cause native selon R22.

## 3. Choisir la protection adaptée

**R07-009 — Choisir à partir de l'invariant.** Examiner les lectures nécessaires, les écritures adverses et l'isolation effective. Utiliser le mécanisme le plus simple qui protège toute la décision :

| Situation | Mécanisme et vérification |
|---|---|
| Lire plusieurs valeurs puis décider et écrire | Lecture verrouillée dans une transaction courte ; décider sur les valeurs ainsi relues. |
| Mutation exprimable par une condition SQL simple | `UPDATE ... WHERE ...` atomique, puis nombre de lignes affectées ; poursuivre seulement si le résultat attendu est acquis. |
| Intention dépendant d'une ancienne version affichée | Version attendue dans la condition d'écriture, incrémentée atomiquement ; conflit traité explicitement. |
| Ligne encore absente ou invariant sur un ensemble | Contrainte adaptée, ressource de coordination commune ou isolation appropriée ; prouver le cas d'insertion concurrente. |

**R07-010 — Protéger jusqu'à l'écriture.** Acquérir le verrou avant la décision sur l'état mutable, garder lecture/décision/écriture sur la même connexion jusqu'à la fin de l'unité. Appliquer les filtres de périmètre R08. Tous les producteurs concurrents doivent suivre un protocole compatible ; verrouiller uniquement dans un chemin ne protège pas un autre chemin qui décide sur une ancienne lecture puis écrase l'état. Les services reçoivent l'état protégé sans requête implicite ; les repositories exécutent accès et conditions techniques.

**R07-011 — Interpréter les mutations conditionnelles.** Le repository renvoie un résultat explicite ; l'action/service décide de la suite. Zéro ligne affectée n'est ni succès ni preuve, à lui seul, d'une cause métier précise. Une éventuelle relecture sert à établir un diagnostic autorisé, sans transformer après coup l'échec en réussite. Pour une version attendue, tous les changements pertinents doivent faire évoluer la version ; un verrou obtenu aujourd'hui ne rend pas valide une intention fondée sur l'état d'hier.

**R07-012 — Protéger l'absence et les ensembles.** `lockForUpdate()` sur une requête vide ne réserve pas une future ligne. Ne pas vérifier « absent » puis insérer sans contrainte d'unicité adaptée R05. Pour une somme/capacité ou un ensemble mutable, verrouiller une ressource commune existante respectée par tous les producteurs, utiliser une contrainte appropriée ou qualifier `SERIALIZABLE` avec reprise des échecs de sérialisation. Verrouiller les seules lignes trouvées n'empêche pas toute nouvelle insertion concurrente. Motiver la solution retenue, sans changer globalement l'isolation par défaut.

**R07-013 — Borner les verrous.** Définir portée et ordre stable commun d'acquisition lorsque plusieurs ressources sont visées ; préparer les entrées avant, limiter le travail sous verrou et examiner le coût des requêtes avec R06. Fixer délais d'attente et reprises selon les exigences, puis mesurer. Ne pas attendre un humain ni effectuer un appel externe long sous transaction. `NOWAIT` et `SKIP LOCKED` changent la sémantique : les utiliser uniquement si refus immédiat ou saut temporaire est admis ; une ligne sautée n'est pas inexistante. Aucun verrou ne promet à lui seul une priorité métier par ordre d'arrivée.

**Illustration A — Dernière portion.** Hypothèse : une ligne de stock existe avec `remaining = 1` ; commande et réservation doivent être acquises ensemble, en isolation **READ COMMITTED**. Dans l'action, sous contexte autorisé et transaction commune : repository lit cette ligne avec `lockForUpdate()` → service vérifie la quantité relue → repositories diminuent la quantité et créent la commande → commit. Tous les producteurs suivent cette coordination.

| Entrelacement | Résultat |
|---|---|
| A verrouille ; B tente de verrouiller la même ligne | B attend, sans décider sur sa lecture précédente. |
| A réserve et commit | B relit `remaining = 0` ; le service refuse selon le contrat ; aucune seconde commande acquise. |
| A échoue après la diminution, avant la commande | Rollback de A : quantité restaurée en base ; B peut ensuite réserver la portion. |

**Variante :** pour une quantité strictement positive déjà validée et une règle simple, le repository peut employer cette mutation, toujours dans l'unité qui crée la commande :

```sql
UPDATE catalogue_stocks
SET remaining = remaining - :quantity
WHERE restaurant_id = :restaurant_id AND id = :stock_id
  AND remaining >= :quantity;
```

Continuer uniquement si une ligne est affectée ; sinon appliquer l'issue prévue. Le prédicat ne remplace pas les autres préconditions métier. **Preuve :** deux connexions pour une portion, un seul effet acquis ; une exception injectée après diminution ne laisse ni réservation ni commande partielle. Le périmètre SQL reste autorisé et le stock non négatif.

## 4. Relire correctement avec Eloquent

**R07-014 — Choisir la relecture.** Dans le repository, préférer une requête filtrée avec `lockForUpdate()` lorsqu'il faut établir périmètre, colonnes ou critères. `refreshForUpdate()` est une variante native pour une instance persistée déjà disponible, si ses limites ci-dessous sont compatibles. Une lecture sans verrou reste appropriée si l'opération ne dépend pas de sa stabilité, ou si la mutation conditionnelle porte toute la protection requise. Ni `refresh()` seul ni une transaction seule ne réserve automatiquement une ligne contre les autres auteurs.

**R07-015 — Qualifier `refreshForUpdate()`.** La méthode est native depuis **Laravel 13.27.0** ; vérifier la version installée avant usage. Elle n'ouvre pas de transaction. Elle écrase les attributs non sauvegardés et synchronise l'original ; conserver séparément intention et version attendue. Sa requête utilise `newQueryWithoutScopes()` : filtres de la requête d'origine et scopes Eloquent ne sont pas réappliqués, RLS reste effectif. Garantir que la lecture elle-même reste autorisée malgré ces absences ; sinon employer la requête filtrée, sans charger d'abord puis refuser. Un modèle non persisté est rendu inchangé ; une ligne persistée désormais introuvable provoque une exception. Les relations déjà chargées hors pivots sont rechargées, **sans propagation du verrou principal** : protéger séparément celles dont dépend la décision. [Version native](https://github.com/laravel/framework/releases/tag/v13.27.0), [implémentation Eloquent](https://github.com/laravel/framework/blob/v13.27.0/src/Illuminate/Database/Eloquent/Model.php).

**R07-016 — Reconstruire l'état après annulation.** Rollback SQL ne restaure pas attributs, relations, identifiants ou événements déjà manipulés en PHP. Écarter ou recharger les objets concernés avant réutilisation ; lors d'une reprise, relire sous la protection requise et refaire les contrôles. Ne pas capturer hors de la closure rejouée un modèle mutable pour le réutiliser comme vérité à chaque tentative. Une version attendue provenant de l'appelant garde sa valeur ; la remplacer par la version relue ferait disparaître le conflit recherché.

**Illustration B — Modifier ou accepter.** Hypothèse : la cuisson est modifiable tant que `accepted_at` est nul ; les deux opérations utilisent **READ COMMITTED**. Reprendre le cas `ChangeCooking` de R03/R08 ; les noms ci-dessous situent les extraits, sans créer une seconde implémentation :

```text
app/Modules/Dining/Orders/
├── Models/OrderItem.php
└── UseCases/ChangeCooking/
    ├── ChangeCookingAction.php
    ├── ChangeCookingInput.php
    ├── ChangeCookingService.php
    └── ChangeCookingRepository.php
tests/Integration/Modules/Dining/Orders/…
```

L'action ouvre la transaction sur la connexion du modèle, active le contexte RLS et applique les Gates prévues par R08 ; elle appelle `findForUpdate()`, le service, puis la sauvegarde par le repository. Corps de la méthode `findForUpdate(ChangeCookingInput $input): OrderItem` dans ce repository :

```php
$model = new OrderItem();
$connection = $model->getConnection();

if ($connection->transactionLevel() < 1) {
    throw new \LogicException('An active transaction is required.');
}

return $model->newQuery()
    ->where('restaurant_id', $input->restaurantId)
    ->where('meal_id', $input->mealId)
    ->select(['id', 'restaurant_id', 'meal_id', 'accepted_at', 'cooking'])
    ->lockForUpdate()
    ->findOrFail($input->orderItemId);
```

Le service reçoit la ligne relue et refuse si `accepted_at` est renseigné ; il ne consulte pas la base. L'opération d'acceptation protège la même ligne selon le même protocole. Si elle commit d'abord, la modification voit l'acceptation et échoue ; si la modification commit d'abord, l'acceptation voit la nouvelle cuisson. Le verrou prend fin, l'état accepté reste. Si le cas dépend aussi d'une clôture du repas, coordonner les deux opérations sur le repas selon R07-002/013 ; le verrou de l'article seul ne suffit pas.

**Variante native :** uniquement si la relecture elle-même reste autorisée sans les filtres/scopes, le repository vérifie la transaction via `$item->getConnection()`, appelle `$item->refreshForUpdate()`, puis rend l'état au service pour décision. Si un article peut avoir changé de repas au sein du même restaurant et que ce repas limite la lecture, RLS restaurant ne suffit pas : garder la requête filtrée. **Preuves :** les deux entrelacements, appel sans transaction, transaction sur une autre connexion, modèle périmé et attribut non sauvegardé. La garde ne doit pas être masquée par une transaction automatique du framework de test. Sous une isolation différente, qualifier aussi les erreurs de sérialisation possibles à la place de la relecture attendue en A/B.

## 5. Coordonner les répétitions et l'idempotence

**R07-017 — Identifier l'intention.** Lorsqu'une opération doit supporter une répétition, définir clé stable, portée autorisée, type d'opération et contenu sémantique comparable selon R04. Conserver la même clé pour reprendre la même intention ; une nouvelle intention reçoit une nouvelle clé, même avec des valeurs identiques. Distinguer clé d'opération, version attendue et identifiant de corrélation technique. Deux charges utiles égales ne suffisent pas à reconnaître une répétition ; la clé seule sans comparaison de contenu ne suffit pas non plus.

**R07-018 — Acquérir, muter et prouver ensemble.** Pour un effet SQL atomique, coordonner dans la même transaction PostgreSQL acquisition de la clé, mutation et résultat minimal récupérable. Protéger l'acquisition par unicité/coordination réelle R05, pas « rechercher puis créer » sans arbitrage. Définir les issues mémorisées : si un refus annule l'unité entière, la clé acquise dans cette unité disparaît aussi. Un cache ou un verrou Redis peut compléter le dispositif, sans devenir la seule preuve durable de l'effet. Une exécution longue ou impliquant plusieurs ressources exige un protocole explicite distinct, sans prétendre prolonger cette atomicité SQL.

**R07-019 — Reprendre selon la preuve.** Même clé et même contenu : rendre le résultat déjà acquis, après contrôle des droits actuels, sans refaire l'effet. Même clé et contenu différent : conflit, sans écraser la première preuve. Traitement en cours, échec établi et résultat inconnu sont distincts ; une attente expirée n'établit pas que le premier traitement a échoué. Une preuve attendue mais introuvable après conflit exige vérification/réconciliation, pas une répétition aveugle. Le résultat conservé doit permettre de retrouver ce qui a été acquis, sans dépendre exclusivement d'un état courant qui a pu changer.

**R07-020 — Placer le protocole chez son propriétaire.** L'action orchestre l'opération ; son repository gère acquisition et résultat persisté ; le service décide sur les données fournies. Une extraction commune suit R02 lorsqu'un besoin partagé réel existe. Ne pas imposer un gestionnaire universel, une table globale ou un framework d'états à tous les cas. Préserver les préconditions de transaction et les frontières publiques entre modules.

**R07-021 — Borner la conservation sans perdre le contrat.** Déduire contenu autorisé, protection et durée des spécifications, en appliquant R09 ; conserver seulement la preuve nécessaire. Documenter ce qu'il advient d'une reprise tardive et coordonner purge et traitements actifs. Expirer une clé peut permettre un nouvel effet : ne pas supprimer la preuve tant que le contrat doit encore reconnaître la répétition. Aucun TTL, corps de conversation ou réponse brute n'est ajouté par défaut au nom de l'idempotence.

**Illustration C — Répéter après réponse perdue.** Hypothèse : un changement de cuisson possède une clé conservée pour ses reprises ; cet exemple ne mémorise que les réussites. Le repository du cas précédent utilise une table illustrative `dining_order_operations`, créée dans `app/Modules/Dining/Database/Migrations/`. Elle porte UUIDv7 `id`, `restaurant_id`, `operation`, `operation_key`, empreinte sémantique `request_hash` et résultat minimal `result` selon R04/R09. `result` est nullable pour l'acquisition, puis obligatoirement renseigné avant commit. Les trois colonnes de portée sont non nulles avec unicité **non différable** `(restaurant_id, operation, operation_key)` ; l'empreinte inclut notamment repas, article, cuisson et version attendue si applicable. Le résultat terminé reste immuable pendant la fenêtre de reprise ; schéma, RLS et accès suivent R05/R08.

Sur la connexion primaire, sous transaction et contexte autorisés, en isolation **READ COMMITTED**, le repository tente, avec paramètres liés :

```sql
INSERT INTO dining_order_operations
    (id, restaurant_id, operation, operation_key, request_hash)
VALUES (:id, :restaurant_id, :operation, :operation_key, :request_hash)
ON CONFLICT (restaurant_id, operation, operation_key) DO NOTHING
RETURNING id;
```

1. **Un identifiant revient :** l'action possède cette tentative ; elle effectue les lectures protégées, décisions et mutation du cas B, puis le repository enregistre le résultat minimal dans cette même ligne avant commit. Aucun résultat incomplet n'est validé.
2. **Aucun identifiant ne revient :** le repository fait une **seconde requête** de lecture sur la même connexion primaire, filtrée par cette portée. Il compare contenu et résultat ; l'action applique R07-019. Ne pas fusionner acquisition et relecture dans une seule requête : le conflit peut être détecté sans que sa ligne soit visible dans l'instantané initial. La nouvelle instruction obtient un instantané frais.

Le concurrent attend la décision du premier : après son commit il retrouve le résultat, après son rollback il peut acquérir la clé. Ce protocole n'expose normalement pas de ligne durable « en cours » : acquisition, effet et résultat deviennent visibles ensemble. Ne pas capturer une violation d'unicité pour continuer dans une transaction invalidée ; `ON CONFLICT` cible uniquement l'unicité prévue, les autres erreurs restent des erreurs. En Laravel, `selectOne($sql, $bindings, false)` permet d'exécuter l'acquisition avec `RETURNING` sur le PDO d'écriture du repository.

**Preuves :** même clé simultanée → une mutation ; rollback du premier → acquisition possible par le second ; commit puis réponse perdue → même résultat au nouvel appel ; clé identique/contenu différent → conflit ; nouvelle intention/nouvelle clé → application normale des préconditions courantes. Une ligne manquante après conflit ou un résultat incomplet ne déclenche pas la mutation. Relire les effets persistés, pas seulement le nombre de réponses réussies.

## 6. Traiter les échecs, reprises et suites

**R07-022 — Qualifier l'issue avant de réagir.** Distinguer rollback établi, commit établi et résultat indéterminé. Une connexion perdue pendant le commit ou une réponse perdue peut laisser l'appelant incertain ; une erreur de restitution/publication après commit n'annule pas la mutation. Vérifier la preuve d'opération lorsque disponible et appliquer le protocole de réconciliation, sans annoncer un échec annulé ou un succès non établi. R22 adapte diagnostic et restitution ; l'interface/IA ne doit pas déduire seule qu'il faut réexécuter l'effet.

**R07-023 — Rejouer seulement une unité sûre.** Borner les tentatives et l'attente ; identifier les erreurs transitoires justifiant une reprise. Laravel propose `transaction($callback, attempts: ...)` pour les erreurs de concurrence reconnues par sa version : qualifier ce comportement et l'imbrication, sans retry intérieur autonome si la transaction extérieure a échoué. Recommencer l'unité appropriée avec données fraîches, contrôles d'accès et invariants réévalués ; le code rejouable ne doit pas produire d'effet externe irréversible. Ne pas traiter automatiquement refus métier, conflit de version ou commit incertain comme un deadlock à retenter. Conserver la même clé pour la même intention.

**R07-024 — Séparer commit et publication.** Déclencher les suites dépendant des écritures après le commit **extérieur**, avec les mécanismes Laravel appropriés (`afterCommit()`/configuration des jobs, événements adaptés). Avant commit, le consommateur pourrait voir des données absentes ou finalement annulées. Après commit, une panne peut encore empêcher l'envoi : ce report ne constitue pas une livraison durable. Lorsque cette garantie est requise, appliquer une stratégie explicite R10/R17, avec preuve de reprise ; ne pas annoncer une atomicité PostgreSQL/Redis ni adopter implicitement une outbox.

**R07-025 — Traiter les effets hors SQL.** Rollback n'annule ni fichier écrit, ni email envoyé, ni appel fournisseur, ni objet PHP. Identifier pour chaque effet son point d'exécution, sa preuve et son traitement d'échec avec R10/R17 ; prévoir idempotence externe ou compensation lorsque nécessaire et autorisée. Une compensation est une nouvelle opération pouvant elle-même échouer, pas un retour magique de la transaction. Les migrations et rattrapages conservent leur procédure R05/R21.

## 7. Vérifier et transmettre

**R07-026 — Tester les vraies frontières.** Exercer les garanties sur PostgreSQL avec connexions réellement indépendantes et rôle applicatif approprié. Piloter les entrelacements par barrières observables et délais bornés, sans se fier à des pauses arbitraires. Préparer des données visibles par les deux connexions ; une transaction englobante de test peut masquer commit, garde absente et callbacks. Ranger les preuves SQL dans `tests/Integration/Modules/<Module>/...` selon R02 ; les tests d'entrée restent complémentaires. Ni mock, ni SQLite, ni test sous rôle contournant RLS ne prouve ces garanties.

**R07-027 — Exercer le protocole complet concerné.** Selon l'impact : deux ordres de course, rollback après écriture partielle, absence de ligne, invariant d'ensemble, attente/deadlock, version périmée et répétitions du cas C. Ajouter succès intérieur puis rollback extérieur, mauvaise connexion, changement de contexte imbriqué, état PHP après rollback et crash entre commit et publication lorsqu'ils concernent le changement. Vérifier données persistées, absence de doublon, périmètre et issue annoncée ; ne pas s'arrêter au statut HTTP. Pour `refreshForUpdate()`, exercer aussi modèle non persisté/disparu, filtres absents, attributs et relations utiles.

**R07-028 — Transmettre les garanties réelles.** Documenter au propriétaire : invariant, connexion, unité atomique, participants, ordre des verrous, conditions de reprise et limites nécessaires à sa maintenance. Réutiliser le contrat/tests existants plutôt qu'une fiche obligatoire par méthode. Actualiser les chemins concurrents avec le changement ; une protection locale sans revue des autres producteurs reste incomplète. Indiquer preuves exécutées, seulement relues et restant à qualifier, notamment sous charge. Une évolution de comportement appartient à sa spécification.

**Portée des illustrations :** extraits de conception, sans test applicatif exécuté. Hypothèses, colonnes et noms ne constituent pas le schéma adopté. Les fichiers PHP complets suivent R03 ; seuls les corps décisifs sont montrés. A utilise un nom de stockage illustratif, sans autoriser un accès SQL direct au module Catalogue depuis Dining : une éventuelle collaboration suit le contrat public et la participation transactionnelle R02/R07-004.

**Repères techniques :** [transactions Laravel](https://laravel.com/framework/docs/13.x/database#database-transactions), [verrouillage Laravel](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking), [jobs et transactions](https://laravel.com/framework/docs/13.x/queues#jobs-and-database-transactions), [connexion Laravel 13.27.0](https://github.com/laravel/framework/blob/v13.27.0/src/Illuminate/Database/Connection.php), [isolation PostgreSQL](https://www.postgresql.org/docs/current/transaction-iso.html), [verrous PostgreSQL](https://www.postgresql.org/docs/current/explicit-locking.html), [SET LOCAL](https://www.postgresql.org/docs/current/sql-set.html), [INSERT et ON CONFLICT](https://www.postgresql.org/docs/current/sql-insert.html). Vérifier les versions installées ; ces références ne prouvent pas notre implémentation.
