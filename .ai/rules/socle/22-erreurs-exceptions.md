# R22 — Gestion des erreurs et exceptions

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r22).

**Lire pour :** créer, modifier ou auditer une issue, une exception, une interception, un diagnostic ou une restitution d'erreur. **Procédure :** qualifier l'issue → définir cause/code → propager → signaler → adapter au consommateur → vérifier catalogue et parcours.

Les prescriptions `R22-001` à `R22-024` sont obligatoires, sauf mention **Revue** ou **Variante** selon [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent comportements, droits, données admissibles, langues, destinations et reprises permises ; faire préciser une exigence nécessaire introuvable. Les exemples illustrent des hypothèses, sans adopter une fonctionnalité, un canal ou une durée.

**Frontières :** [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md) possèdent emplacements et invariants ; [R04](04-contrats-dto-validation.md) validation, typage et sérialisation ; [R07](07-transactions-concurrence-etats.md)/[R10](10-jobs-evenements-fiabilite.md)/[R17](17-integrations-effets-externes.md) garanties des effets et reprises. [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) contrôlent accès et représentations ; [R13](13-interface-accessibilite-internationalisation.md)/[R15](15-ia-outils-fournisseurs.md) réalisent interface et conversation. [R19](19-performance-observabilite.md) possède collecte, recherche et secours ; [R18](18-tests-preuves-acceptation.md), [R20](20-environnement-dependances-qualite.md) et [R21](21-livraison-exploitation-reprise.md) preuves, outillage et exploitation. Consulter les propriétaires affectés selon leur statut ; aucun renvoi ne prouve un dispositif installé.

## 1. Qualifier l'issue

**R22-001 — Déclarer les issues du contrat.** À partir du cas d'usage R03, distinguer résultat normal, refus métier bloquant, erreur connue de validation/identité/accès/ressource et incident technique inattendu. Utiliser un résultat explicite pour une issue normale, une exception métier personnalisée pour un refus bloquant. Une panne SQL n'est pas une preuve d'indisponibilité métier. **Contrôle :** chaque branche conserve sa signification, sans succès ou résultat vide fabriqué pour éviter une erreur.

**R22-002 — Qualifier la connaissance des effets.** Distinguer non-exécution attestée, refus, échec confirmé, effet acquis et résultat non confirmé selon les garanties réelles. L'incertitude décrit ce qui est connu, pas une classe d'exception obligatoire. Une erreur reçue ou une réponse absente ne prouve pas que rien n'a changé. **Contrôle :** restitution et action suivante n'affirment ni rollback ni répétition sûre sans preuve R07/R10/R17.

**R22-003 — Identifier les responsabilités.** Pour les entrées concernées, localiser producteur de la cause, frontière d'interception, responsable de signalement et adaptateurs des consommateurs. Réutiliser une même cause lorsque son sens est identique ; adapter son traitement sans dupliquer l'invariant. Un commentaire de contrat ou une fiche existante suffit : aucun dossier par méthode requis. **Contrôle :** le parcours reste retrouvable par un autre intervenant, y compris quand la cause est native.

**R22-004 — Préserver les catégories connues.** Avant le repli inattendu, traiter ou déléguer explicitement validation, authentification, autorisation et ressource absente selon leur contrat et R08. Un refus d'accès ne devient pas une erreur 500 ni une confirmation de l'existence d'une ressource protégée. Pour un format d'outil IA rejeté avant action, fournir les informations sûres de correction R04-009 et cette non-exécution attestée ; ne pas déclarer la demande métier impossible.

## 2. Définir les exceptions et les codes

**R22-005 — Garder un contrat métier minimal.** Les exceptions métier implémentent un contrat commun exposant `errorCode(): string` ; chaque classe concrète appartient au propriétaire de la cause. Nom illustratif : `DomainError`. Garder paramètres typés et minimaux nécessaires au traitement, sans langue, HTTP ou logique de journalisation dans l'exception. **Variante :** une base commune est admise pour une duplication réelle, sans hiérarchie universelle imposée. Une exception consommée entre modules et ses types relèvent de `PublicApi/` R02.

**R22-006 — Déclarer un code stable et distinct.** Employer `errorCode` et la convention anglaise `<owner>.<condition_snake_case>`, avec propriétaire identifiable et sens unique. Déclarer le code auprès de son propriétaire, généralement en constante de classe ; un enum local convient à un véritable ensemble fini. Ne pas détourner `getCode()`, SQLSTATE, statut HTTP ou code fournisseur. Un mapping externe peut attribuer un code applicatif à une catégorie native sans wrapper ni modification de vendor.

**R22-007 — Séparer données internes et présentation.** Produire noms, codes et messages applicatifs internes en anglais ; traiter par type/code, jamais en analysant le texte. Ne pas retraduire artificiellement les messages natifs ou données saisies. Les paramètres internes ne sont pas automatiquement publics : chaque adaptateur sélectionne ceux admis par son contrat/destination R04/R09. **Contrôle :** aucun modèle, objet exception ou texte technique sérialisé par commodité vers l'UI ou l'IA.

**R22-008 — Préserver le sens lors de l'évolution.** Garder le code lorsque seul le libellé change ; attribuer un nouveau code si la signification change. Avant modification/retrait de type ou paramètre, identifier consommateurs, clients ouverts, messages en attente et compatibilité R04/R10/R21. **Contrôle :** aucune réutilisation d'un code retiré pour un autre sens ; code inconnu traité par un repli explicite, sans dépendre d'une traduction pour comprendre l'issue.

## 3. Propager et intercepter

**R22-009 — Intercepter pour une responsabilité réelle.** Laisser remonter l'erreur sans catch mécanique dans service, repository et action. Intercepter lorsqu'il faut récupérer sûrement, traduire un contrat ou enrichir utilement ; préciser ce que l'interception accomplit. La restitution relève de la frontière du consommateur. **Contrôle :** aucun catch qui masque l'échec, journalise seulement avant de relancer partout, ou transforme toutes les causes en un même refus métier.

**R22-010 — Conserver la cause d'origine.** Lors d'une traduction justifiée, transmettre l'exception avec `previous` et garder les informations nécessaires ; ne pas recréer une exception avec le seul `getMessage()`. Relancer la même instance si aucune traduction n'est utile. Protéger nettoyage et signalement afin que leur défaillance ne remplace pas la première cause ; appliquer le secours borné R19-024. Conserver une cause en mémoire ne permet pas de la transmettre brute à toutes les destinations.

**R22-011 — Laisser l'échec traverser la transaction.** Lorsque les écritures doivent être annulées, laisser l'exception sortir de la fermeture transactionnelle avant de produire la restitution. Ne pas y retourner un résultat d'échec qui autoriserait un commit involontaire. Les récupérations internes sûres suivent leur contrat explicite R07 ; ne pas réutiliser comme vérité le modèle muté en mémoire après rollback. **Contrôle :** état durable et effets externes évalués séparément, notamment après perte de connexion.

**R22-012 — Placer le dernier repli au bon endroit.** À la frontière responsable, couvrir `Throwable` après les catégories connues ; conserver le handler Laravel pour les erreurs HTTP non consommées. Un catch applicatif qui transforme l'exception en résultat devient responsable de son signalement ou de sa délégation explicite. Ne pas recopier un catch universel dans chaque contrôleur. **Contrôle :** faute PHP ou panne imprévue produit un incident et une restitution sûre, sans altérer validation, accès ou état du job.

## 4. Diagnostiquer et signaler

**R22-013 — Garder le diagnostic natif utile.** Associer au code applicatif les informations pertinentes et admissibles : classe, code natif sans conversion destructive, message réel, emplacement, trace et chaîne `getPrevious()`. Un SQLSTATE peut être une chaîne. Identifier omissions/transformations et borner le diagnostic selon R19 ; ne pas remplacer systématiquement tout message privé par une phrase générique. **Contrôle :** la cause reste analysable dans les limites de la politique R09, sans fausse promesse de conservation intégrale.

**R22-014 — Examiner la représentation entière et ses copies.** Appliquer R09 aux messages, SQL/bindings, causes, arguments de trace, contexte et enrichissements avant chaque destination. Si un texte ne peut être rendu admissible de façon fiable, l'omettre explicitement et garder les métadonnées permises. Inspecter aussi handler/logger par défaut, `exception->context()` éventuel, collecteurs, stockage des jobs échoués/Horizon et debug. Un logger filtré ne nettoie pas ces chemins indépendants ; ne pas créer de dump brut parallèle.

**R22-015 — Désigner un signalement responsable.** Définir le responsable par incident/tentative et l'intérêt de son signal : refus attendu, événement métier ou incident ne demandent pas automatiquement la même gravité. Éviter les doublons de propagation et maîtriser le passage au reporter Laravel par défaut. Plusieurs destinations intentionnelles restent un seul signalement logique ; les échecs de tentatives distinctes restent observables. Une déduplication d'instance seule ne réalise pas cette politique ; diagnostic et audit durable demeurent distincts.

**R22-016 — Corréler sans dépendre de la panne.** À partir du contexte de confiance déjà disponible, fournir les références autorisées permettant de retrouver date/heure, domaine, périmètre, opération, incident et tentative pertinente. Ne pas exiger une lecture SQL pour décrire une panne SQL. Initialiser/libérer ce contexte par traitement selon R19-009, y compris dans les workers persistants. Un identifiant public de corrélation ne donne aucun accès au diagnostic ; canaux, recherche/fuseaux et secours restent chez R19.

## 5. Adapter aux consommateurs

**R22-017 — Construire les faits sûrs avant la restitution.** Mapper explicitement code, paramètres admissibles, connaissance de l'issue et suites permises ; définir leur forme avec R04, sans imposer un format universel à HTTP, IA, job et console. Adapter aux droits/destinataire résolus par l'application et à la langue active R13/R15. Le modèle IA reçoit ces faits et non le diagnostic ; il ne décide pas seul si une mutation a réussi ni quelle reprise est autorisée.

**R22-018 — Raccorder HTTP et Inertia à leurs mécanismes.** Séparer reporting et rendering dans le handler. Enregistrer les mappings connaissant les exceptions concrètes auprès du module propriétaire et les raccorder par son provider ; garder l'assemblage transversal dépendant des seuls contrats communs R02. Pour un formulaire Inertia, préserver validation/redirection/error bags et partager la restitution attendue par le canal défini ; ne pas imposer un JSON 422 générique. Pour incident ou autre endpoint, respecter son statut/rendu déclaré, sans page technique publique.

**R22-019 — Préserver l'échec des jobs et de la console.** Un catch ne doit pas acquitter artificiellement un job ou renvoyer un statut console de succès. Propager, déclarer un échec terminal ou retourner une issue normale selon le contrat R10 ; vérifier qui signale chaque tentative/finalisation pour éviter les doublons. La console n'autorise pas automatiquement un diagnostic brut. Pour les processus arrêtés, requêtes jamais arrivées ou réponses perdues, traiter l'absence par le composant encore disponible et les garanties R07/R10/R17/R21, sans répétition aveugle.

**R22-020 — Fournir un repli compréhensible et sûr.** Prévoir code inconnu, traduction absente, paramètres invalides et agent indisponible. Afficher/formuler une réponse adaptée au destinataire avec les faits attestés et actions permises ; ne jamais prendre le message interne comme traduction de secours. Si le traitement s'est exécuté mais la restitution échoue, ne pas réexécuter la mutation pour fabriquer la réponse. **Contrôle :** le repli ne promet ni succès, ni non-exécution, ni accès inexistant au support ou au diagnostic.

## 6. Documenter et vérifier

**R22-021 — Maintenir une fiche chez le propriétaire.** Avec chaque code ajouté/modifié, documenter sens, propriétaire, catégorie/classes ou mapping natif, déclaration source, paramètres internes/admissibles, consommateurs, restitutions, connaissance des effets, suites et preuves. Relier les spécifications propriétaires plutôt que les recopier. Segmenter par module/usage ; aucun catalogue exhaustif dans le socle ni seconde liste exécutable concurrente. La déclaration source reste propriétaire du code, la fiche propriétaire de son explication.

**R22-022 — Tenir une cartographie globale légère.** Relier chaque code aux noms complets des classes correspondantes et aux fiches explicatives. Garder ici la correspondance, pas toutes les explications. Autoriser associations multiples et catégorie native/repli `Throwable` lorsque le mapping réel le requiert : pas de bijection artificielle ni exception dédiée inventée pour chaque code. Documenter dans la fiche les conditions d'un mapping générique afin qu'une association ne fasse pas croire que tous les objets de cette classe ont le même code.

**R22-023 — Contrôler la concordance avec le code.** Vérifier existence/noms des classes, déclarations et mappings exposés, absence de collisions de sens, couverture du catalogue et compatibilité des consommateurs. Coupler ajout, retrait et renommage à ces contrôles. Une vérification automatisée du déclaratif complète les revues des conditions et restitutions ; elle ne les déduit pas du seul FQCN. Aucun générateur obligatoire ni registre runtime créé uniquement pour entretenir la documentation.

**R22-024 — Vérifier le parcours, pas uniquement le message.** Selon l'impact, exercer deux entrées vers la même cause, catégories connues/inattendues, paramètres/langues/droits, propagation transactionnelle, signalement/tentatives et destinations réelles. Tester aussi panne du reporter/nettoyage, code inconnu, absence de réponse et deux opérations successives de périmètres différents. Prouver simultanément diagnostic utile, informations interdites absentes et effets correctement qualifiés ; organiser ces preuves avec R18, en précisant versions, raccordements et limites.

## Illustrations minimales

### A. Une cause, une interface et un outil IA

**Hypothèse :** l'exemple R03 refuse une modification lorsque l'article est déjà accepté. Les entrées sont validées/autorisées, les identifiants sont ici des chaînes UUID selon R04/R05, y compris l'attribut `id` chargé par le repository R03, et l'identifiant de cet article est admissible à ces deux consommateurs. Arbre partiel : les fichiers R03 sont repérés sans réécrire leur persistance ; les raccordements exacts restent à qualifier.

```text
app/Shared/Errors/DomainError.php
app/Modules/Dining/
├── Providers/DiningServiceProvider.php        # Enregistre le rendu HTTP
└── Orders/
    ├── Exceptions/OrderItemAlreadyAccepted.php
    └── UseCases/ChangeCooking/
        ├── ChangeCookingInput.php             # R03/R04 : entrée commune
        ├── ChangeCookingAction.php            # R03 : transaction
        ├── ChangeCookingService.php           # R03 : invariant
        ├── ChangeCookingRepository.php        # R03 : persistance
        ├── ChangeCookingErrorMapper.php       # Faits sûrs communs à ces entrées
        ├── Http/ChangeCookingController.php
        └── Ai/ChangeCookingTool.php
```

Extraits de deux fichiers, imports `DomainError`/`RuntimeException` omis ; un fichier par classe/interface, `strict_types` et namespaces cohérents R03. Le constructeur correspond à `new OrderItemAlreadyAccepted($item->id)` dans le service R03.

```php
interface DomainError
{
    public function errorCode(): string;
}

final class OrderItemAlreadyAccepted extends RuntimeException implements DomainError
{
    public const ERROR_CODE = 'dining.order_item_already_accepted';

    public function __construct(public readonly string $orderItemId)
    {
        parent::__construct('The order item has already been accepted.');
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
```

Le clic et l'outil IA construisent la même entrée puis appellent `ChangeCookingAction::execute($input, $access, $actor)` selon l'[illustration A de R08](08-securite-identites-isolation.md#exemple-autorisation), avec ses contrôles Gate/RLS. Repository → état verrouillé ; service → exception ; action → propagation hors transaction. La règle produit la cause une fois, sans log dans le service. Le mapper appartient à cet usage ; si un autre module consomme l'exception, son contrat passe sous `PublicApi/`.

```php
// ChangeCookingErrorMapper: local array shape, shared only by these two adapters.
/** @return array{errorCode: string, parameters: array{orderItemId: string}, outcome: string} */
public function acceptedItem(OrderItemAlreadyAccepted $error): array
{
    return [
        'errorCode' => $error->errorCode(),
        'parameters' => ['orderItemId' => $error->orderItemId],
        'outcome' => 'rejected',
    ];
}
```

| Entrée | Interception et sortie |
| --- | --- |
| Formulaire HTTP/Inertia | Le contrôleur appelle l'action sans catch redondant. Le provider enregistre un rendu pour ce type et ce parcours ; il utilise le mapper, redirige vers la page déclarée et partage ses faits via une prop d'erreur maîtrisée. L'UI sélectionne sa traduction et ses actions permises. Il ne s'agit pas d'une erreur de validation de champ fabriquée. |
| Outil IA | L'adaptateur intercepte ce refus après l'action et retourne les faits du mapper, complétés par les suites permises résolues côté application. L'IA formule dans la langue active ; elle ne reçoit ni l'exception ni son message interne. Les erreurs connues de forme/accès suivent leurs contrats avant le repli `Throwable`. |

Raccordement HTTP dans `DiningServiceProvider` : `ExceptionHandlerContract` désigne `Illuminate\Contracts\Debug\ExceptionHandler` et `Handler` le gestionnaire Laravel `Illuminate\Foundation\Exceptions\Handler`. Injecter le contrat réellement partagé, puis vérifier l'hypothèse de ce raccordement : un handler dérivé du handler Laravel. Les routes illustratives partagent ici un paramètre `meal`, identifiant textuel unique de repas validé dans le périmètre autorisé ; autres imports omis.

```php
public function boot(ExceptionHandlerContract $handler): void
{
    if (! ($handler instanceof Handler)) {
        throw new LogicException('The Laravel exception handler is required.');
    }

    $handler->dontReport([OrderItemAlreadyAccepted::class]);
    $handler->renderable(function (OrderItemAlreadyAccepted $error, Request $request) {
        if (! $request->routeIs('dining.orders.change-cooking')) {
            return null; // Delegate other HTTP paths to their declared rendering.
        }

        $facts = app(ChangeCookingErrorMapper::class)->acceptedItem($error);

        return to_route('dining.orders.show', ['meal' => $request->route('meal')])
            ->with('operationError', [
                'mealId' => $request->route('meal'),
                'facts' => $facts,
            ]);
    });
}
```

Le middleware Inertia vérifie forme, page cible, repas et périmètre autorisé avant de partager les seuls `facts` sous la prop déclarée : un onglet d'un autre repas/périmètre ne reçoit pas ce flash. La page utilise code/paramètres via son catalogue R13 et efface l'état local au changement de contexte R12. Le flash suit la redirection ; il n'est pas un diagnostic privé. Le mapper local est résolu au raccordement, sans import métier depuis `Infrastructure`. D'autres parcours exposant ce refus nécessitent leur propre rendu sûr ; `null` ne leur en construit pas un.

Extrait de l'adaptateur IA pour **cette seule branche**, après validation/autorisation R04/R08 ; les autres catégories sont traitées conformément à R22-004/012. `$access` est le `MealAccess` vérifié et `$actor` l'acteur éventuel (`?Authenticatable`), tous deux issus du contexte serveur selon R08 ; `null` correspond à l'invité de cette illustration.

```php
try {
    $this->action->execute($input, $access, $actor);
} catch (OrderItemAlreadyAccepted $error) {
    return $this->errorMapper->acceptedItem($error);
}
```

Hypothèse d'observation : ce refus courant ne déclenche pas de diagnostic d'incident ; il est exclu du reporting technique Laravel par le raccordement du propriétaire, et son suivi requis suit R19. Un audit métier éventuellement requis garde ses garanties distinctes. Dans une autre spécification, adapter ce suivi sans changer le sens du refus.

Exemples de restitution pour le même code, selon une spécification fictive :

| Destinataire/langue | Réponse et suite permise |
| --- | --- |
| Client, français | « Cet article est déjà pris en charge. Vous pouvez demander l'aide du personnel. » |
| Client, anglais | « This item has already been accepted. You can ask a member of staff for help. » |
| Équipier, français | « Article déjà pris en charge. Consultez la procédure de correction autorisée. » |
| Restaurateur, français | « Article déjà pris en charge. Utilisez la procédure prévue ; contactez le support en cas d'anomalie. » |

Ces suites dépendent des droits et du parcours déclaré ; le message ne crée aucune permission. La même projection est partagée ici parce que sens, données admissibles et évolution le permettent ; sinon, séparer les projections.

### B. Un incident natif, un diagnostic privé et une réponse sûre

Si le repository rencontre une panne SQL, laisser remonter la `QueryException`. La frontière attribue, par son mapping externe, le code illustratif `dining.operation_failed`, sans fabriquer `OrderItemAlreadyAccepted`. Le handler prend en charge l'exception HTTP non consommée ; un adaptateur IA qui la consomme doit explicitement déléguer le diagnostic.

```php
// Incorrect: technical text escapes to a consumer.
return ['errorCode' => 'dining.operation_failed', 'message' => $error->getMessage()];
```

Dernière branche de l'adaptateur IA, **après traitement des catégories connues**. `incidentReporter` est le mécanisme commun R19 : il reçoit cause/contexte, avec le code applicatif attribué par cette frontière, projette le diagnostic admis et maîtrise son secours sans remplacer la cause ni laisser passer un dump natif. Sa réalisation est omise ; il ne connaît pas les exceptions métier du module. Cet adaptateur consomme l'erreur et délègue le signalement une fois.

```php
catch (Throwable $error) {
    $this->incidentReporter->report($error, $context);

    return ['errorCode' => 'dining.operation_failed', 'outcome' => 'unconfirmed'];
}
```

Le responsable de diagnostic reçoit l'erreur et le contexte déjà disponible, construit une représentation privée admissible R09, puis émet avec R19. `getMessage()` apporte le vrai diagnostic, mais son accès seul ne le rend pas admissible. Hypothèse fictive ci-dessous : le message natif standardisé est vérifié/admis ; SQL/paramètres/arguments ne le sont pas. S'ils figurent dans un texte non filtrable sûrement, omettre ce texte et indiquer pourquoi.

```json
{
  "occurredAt": "2026-10-06T18:42:11Z",
  "domain": "dining",
  "restaurantId": "r-42",
  "operation": "change_cooking",
  "incidentId": "inc-7",
  "correlationId": "op-91",
  "errorCode": "dining.operation_failed",
  "exceptionClass": "Illuminate\\Database\\QueryException",
  "cause": {
    "exceptionClass": "PDOException",
    "nativeCode": "08006",
    "nativeMessage": "SQLSTATE[08006]: Connection failure"
  },
  "omitted": ["sql", "bindings", "wrapperMessage", "traceArguments"]
}
```

Extrait réduit : l'emplacement et la trace sans arguments, lorsqu'admis et nécessaires, complètent ce diagnostic ; ni niveau ni canal ne sont choisis par cet exemple. Une recherche « hier vers 20 h 42, restaurant r-42 » convertit l'heure du fuseau déclaré puis filtre périmètre/corrélation avec les outils R19. L'accès au log reste autorisé séparément.

Dans un raccordement Laravel, un callback `report` peut laisser continuer le logger par défaut : après le signalement maîtrisé, arrêter explicitement cette continuation par le mécanisme adapté (`return false` ou `stop()` dans la version examinée). Ne pas arrêter le seul signalement utile. Le `render` fournit séparément le rendu sûr du canal. Qualifier également `failed_jobs`, Horizon et les autres copies : la projection ci-dessus ne remplace ni ne filtre automatiquement leur stockage natif.

### C. Une interception utile et les limites d'un catch

**Hypothèse indépendante :** un adaptateur fournisseur convertit une panne précise en exception de son contrat public, tout en conservant la cause. Extraits de ses classes propriétaires ; le diagnostic sera signalé à la frontière consommatrice, pas également ici.

```text
app/Modules/Documents/
├── PublicApi/Receipts/ReceiptUnavailable.php
└── Receipts/Integrations/Provider/
    ├── ReceiptProviderAdapter.php
    └── ProviderConnectionException.php   # Type précis du fournisseur fictif
```

```php
// ReceiptUnavailable: owner exception; implements DomainError.
public function __construct(Throwable $previous)
{
    parent::__construct('The receipt is unavailable.', 0, $previous);
}

// Provider adapter: this catch translates a known contract failure.
try {
    return $this->provider->fetchReceipt($receiptId);
} catch (ProviderConnectionException $error) {
    throw new ReceiptUnavailable($error);
}
```

`ReceiptUnavailable` déclare son propre `errorCode()` et la fiche de ses paramètres ; ces membres inchangés de structure A sont omis. `ProviderConnectionException` désigne ici le type précis de panne du fournisseur, pas un catch de toute erreur transformée en indisponibilité. Aucun renvoi à une classe vendor supposée réellement installée.

| Situation | Conduite vérifiable |
| --- | --- |
| Job : `catch (Throwable)` affiche « failed » puis retourne | Incorrect si ce retour fait acquitter un travail non accompli. Laisser le worker observer l'échec ou déclarer son caractère terminal avec R10 ; vérifier reporting des tentatives et finalisation, ainsi que leurs copies R09. |
| Commande console en échec | Restituer une sortie admissible et un code non nul selon le contrat ; ne pas utiliser `errorCode` comme code de sortie natif. |
| Mutation réussie, réponse perdue ou processus tué | Le client connaît une issue non confirmée. Le composant encore disponible vérifie résultat durable/idempotence avant reprise R07/R10/R17 ; le processus mort ne peut plus exécuter son catch ou son nettoyage. |
| Restitution ou reporter défaillant | Conserver la cause et suivre le repli/ secours borné du propriétaire ; ne pas répéter la mutation, ni envoyer le dump brut faute de logger. |

### D. Un code ajouté, documenté et vérifié

Organisation **illustrative**, non créée dans ce lot : `docs/interne/erreurs/README.md` contient la cartographie ; `modules/dining.md` sa fiche explicative. Exemple de lignes concordantes :

| Cartographie globale : `errorCode` | Classes correspondantes | Fiche |
| --- | --- | --- |
| `dining.order_item_already_accepted` | `App\Modules\Dining\Orders\Exceptions\OrderItemAlreadyAccepted` | `modules/dining.md` |
| `dining.operation_failed` | `Throwable` — repli du consommateur | `modules/dining.md` |

La fiche précise que le second code s'applique après traitement des catégories connues dans les frontières concernées ; tout `Throwable` ne reçoit donc pas ce code. Pour le premier, elle renvoie à la constante A, décrit le refus, `orderItemId` chaîne UUID interne et exposée dans ces contrats, consommateurs, données admissibles, traductions/suites et preuves selon leurs sources. Une classe peut participer à plusieurs mappings natifs ; les conditions restent dans les fiches.

Avec une nouvelle exception : déclarer code et paramètres chez le propriétaire → compléter fiche et ligne globale → vérifier existence/FQCN, concordance du code et absence de collision → exercer consommateurs et repli ancien client. Lors d'un renommage, le code peut rester stable ; mettre à jour classe/mapping/fiches ensemble. Si une traduction manque, R13 choisit le repli déclaré, jamais `getMessage()` ; l'IA applique uniquement les faits/suites sûrs R15.

**Portée :** extraits de conception, sans installation ni test applicatif exécuté. Les protections, raccordements et politiques supposés exigent leurs preuves sur le projet ; ces illustrations ne forment pas une infrastructure d'erreurs prête à déployer.

**Repères vérifiés le 6 octobre 2026 :** [exceptions Laravel](https://laravel.com/framework/docs/13.x/errors), [assemblage du handler](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php), [handler Laravel](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Exceptions/Handler.php), [QueryException](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/QueryException.php), [getCode PHP](https://www.php.net/manual/en/exception.getcode.php), [échecs de jobs Laravel](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Queue/Failed/DatabaseUuidFailedJobProvider.php), [stockage Horizon](https://github.com/laravel/horizon/blob/5.x/src/Repositories/RedisJobRepository.php) et [validation Inertia](https://inertiajs.com/docs/v3/the-basics/validation). Requalifier les API et écritures automatiques sur les versions effectivement retenues avec R20 ; les versions examinées ne sont pas adoptées par ces liens.
