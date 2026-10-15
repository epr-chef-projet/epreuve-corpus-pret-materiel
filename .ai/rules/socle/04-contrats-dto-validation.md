# R04 — Contrats, DTO et validation

**Version 1.1 — révision éditoriale à valider ; fond v1.0 validé.** [Index des règles](../index.md#r04).

**Lire pour :** créer ou modifier une entrée HTTP/IA, un DTO, une projection d'écran, des types de transport TS ou un message entre modules, jobs et fournisseurs. **Repères :** contrats PHP dans `app/Modules/` ; contrats frontend dans `resources/js/modules/…/contracts/`, selon [R02](02-architecture-modules.md).

Les prescriptions `R04-001` à `R04-027` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). [R03](03-php-laravel-metier.md) possède le métier, [R08](08-securite-identites-isolation.md) les accès, [R09](09-confidentialite-cycle-donnees.md) la confidentialité et [R22](../index.md#r22) les erreurs. Consulter ces compléments selon l'impact et leur statut dans l'index.

**Portée des exemples :** extraits de conception, sans installation ni test applicatif exécuté. Laravel Data et le générateur TS restent à qualifier selon R04-026. Les exemples ne fixent ni schéma de base ni inventaire définitif des modules.

## 1. Choisir et placer les contrats

**R04-001 — Définir les données et leur usage.**

À partir du parcours et de ses exigences, identifier propriétaire, consommateurs, sens des champs, limites et durée de validité. Distinguer entrée, commande interne, résultat, projection d'écran, message durable et échange fournisseur. Un DTO transporte des valeurs ; il ne remplace ni modèle Eloquent, ni objet garantissant une règle de valeur, ni état Vue.

**Variante :** paramètres typés ou tableau local de forme connue suffisent sans frontière évolutive. **Contrôle :** contrat compréhensible sans inspecter chaque appelant ; aucun DTO systématique entre méthodes.

**R04-002 — Partager seulement les contrats de même sens.**

Séparer les contrats si destinataire, confiance, champs ou évolution diffèrent. Éviter un DTO universel de produit pour administration, carte publique et événements, ainsi que deux objets identiques sans raison. Placer le DTO auprès de l'usage ; les contrats PHP intermodules et leurs types imbriqués vont dans `PublicApi` selon R02. Un échange navigateur n'est pas, à lui seul, un contrat intermodule.

**Contrôle :** aucun modèle, builder, type privé ou chargement de persistance exposé par cette API publique.

**R04-003 — Limiter l'usage de Laravel Data.**

Utiliser des DTO PHP ordinaires pour le backend seul. Qualifier Laravel Data pour les échanges frontend où transformations ou champs optionnels apportent un gain. Consigner les catégories couvertes dans l'outillage et appliquer le même critère à tous les usages ; ne pas étendre le package aux services/modèles ni l'envelopper systématiquement. C'est une convention du projet, pas une limite du package.

**Contrôle :** bénéfice et dépendance explicites ; un éventuel repli PHP conserve la génération TS prévue par R04-020.

## 2. Valider les entrées avant le métier

**R04-004 — Valider avant de construire le DTO.**

Pour HTTP, IA, fournisseur ou message reçu : normaliser les formes admises, valider explicitement, puis construire les valeurs typées et appeler l'action autorisée. Appliquer la séquence R08 : autoriser la lecture préparatoire minimale dans son périmètre, puis contrôler l'objet avant mutation ou restitution. Les invariants restent chez R03. Une construction interne depuis des valeurs maîtrisées n'exige pas de revalider le transport. Ni `new`, ni un type PHP/TS, ni un cast ne valide une entrée.

**Contrôle :** chaque chemin utilisé — tableau, Request, injection/factory — respecte cet ordre ; un rejet de forme empêche l'action.

**R04-005 — Convertir uniquement les formes admises.**

Déclarer les conversions permises : espaces, casse, nombres textuels, dates et alias. Préserver le choix exprimé ; ne pas convertir librement `"2 bouteilles"` en `2`, `"false"` en vrai ou un enum invalide en défaut. Vérifier aussi les middlewares Laravel qui traitent espaces et chaînes vides.

**Contrôle :** cas valides/invalides avant et après normalisation, sur chaque canal ; aucune distinction utile effacée avant validation.

**R04-006 — Séparer les champs reçus du contexte serveur.**

Définir champs acceptés, types, formats et bornes de taille/profondeur, objets imbriqués compris. Fixer rejet ou ignorance des clés inconnues ; ne jamais les transmettre implicitement. Résoudre côté serveur acteur, périmètre autorisé et valeurs faisant autorité. Un identifiant reçu reste à vérifier ; `request()->all()` ou les arguments IA ne sont pas des données de confiance à affecter directement.

**Contrôle :** champs privés, identifiants hors du périmètre autorisé et clés supplémentaires ne changent ni droits ni valeurs serveur.

**R04-007 — Partager les règles de validation communes.**

Posséder normalisation et contraintes communes auprès de l'usage. Dans le parcours illustré, la factory d'entrée valide pour HTTP et IA avant construction ; un éventuel FormRequest garde les contraintes propres au transport sans recopier celles de la factory. Les autres entrées n'ont pas à fabriquer une requête HTTP.

**Variante :** si le cycle de validation du FormRequest porte aussi les contraintes communes, extraire leur définition et leur normalisation auprès de l'usage : `rules()`/`prepareForValidation()` les utilisent côté HTTP, un validateur explicite côté IA. Construire ensuite le DTO depuis le résultat validé (`validated()` en HTTP), sans second jeu de règles ni seconde validation identique. Un chemin de construction après validation ne reçoit jamais directement les données brutes d'un autre canal. Ne pas créer systématiquement un validateur par DTO ni réintroduire une validation Data implicite par injection, factory ou inférence.

**Limite :** `exists`/`unique` exécutent du SQL. Ces accès doivent respecter les propriétaires R02/R06, sans requête cachée dans DTO/service ; ils ne garantissent ni invariant métier ni absence de concurrence (R03/R05/R07).

**Contrôle :** une modification de contrainte commune atteint tous les canaux concernés.

**R04-008 — Décrire les arguments attendus par l'outil IA.**

Fournir noms, sens, types et valeurs autorisées accessibles à l'agent ; relier les libellés aux valeurs techniques lorsque nécessaire. Maintenir la concordance avec la validation serveur par réutilisation ou contrôle. Cette description ne remplace pas la validation et ne révèle aucune donnée interdite.

**Contrôle :** l'agent n'a pas à deviner les choix ; toute évolution d'enum ou d'option atteint le contrat d'outil.

**R04-009 — Corriger un format avant toute exécution.**

Après rejet de forme attesté avant l'action, retourner via R22 les informations sûres : champ, forme attendue, valeurs accessibles et non-exécution. Autoriser une correction bornée selon R15, puis refaire toute la validation. Une conversion déterministe admise peut intervenir avant validation. Préserver l'intention et les confirmations requises ; exclure diagnostics privés et données interdites au destinataire ou au stockage selon R09.

**Contrôle :** aucune mutation avant validation ; après épuisement, transmettre la catégorie et les informations sûres au consommateur pour appliquer son parcours de repli. Un défaut de format ne devient pas un faux refus métier.

**R04-010 — Choisir la suite selon la situation.**

| Situation | Suite requise |
| --- | --- |
| Information connue, représentation incorrecte | Correction selon R04-009. |
| Choix absent ou ambigu | Signaler la donnée à clarifier ; appliquer les confirmations du parcours sans inventer le choix. |
| Refus d'accès ou métier | Restituer le refus selon R22, sans le contourner par une correction de format. |
| Réponse perdue, timeout, exécution incertaine | Vérifier l'état et appliquer l'idempotence/reprise R07/R10/R17 ; aucun nouvel envoi aveugle. |


**Contrôle :** aucune promesse de succès, de rollback ou de non-exécution sans preuve. R15 possède la conversation, R22 les codes et restitutions.

**Illustration A — Modifier la cuisson, puis afficher l'article.** Hypothèse de l'exemple, commune à R03 : HTTP et IA exposent la même mutation, puis l'écran effectue une lecture distincte. Ce parcours illustre les contrats, sans imposer ces entrées ou cette restitution à chaque fonctionnalité. Les fichiers détaillés sont situés dans cet arbre :

```text
app/Modules/Dining/Orders/
├── Enums/Cooking.php
└── UseCases/
    ├── ChangeCooking/
    │   ├── ChangeCookingInput.php
    │   ├── ChangeCookingInputFactory.php
    │   ├── ChangeCookingAction.php
    │   ├── ChangeCookingService.php
    │   ├── ChangeCookingRepository.php
    │   ├── Http/ChangeCookingController.php
    │   └── Ai/ChangeCookingTool.php
    └── ShowOrderItem/
        ├── ShowOrderItemAction.php
        ├── ShowOrderItemRepository.php
        └── OrderItemData.php
resources/js/modules/dining/orders/show-order-item/
├── contracts/order-item.generated.ts
└── components/OrderItemSummary.vue
```

Parcours : **entrée HTTP/IA → factory → action de modification → actualisation → action de lecture → DTO de sortie → JSON → Vue**. Le contrôleur et l'outil résolvent le contexte serveur et valident les identifiants reçus ; HTTP sépare les champs de protocole comme CSRF, et IA respecte la confirmation. L'action applique encore les contrôles d'accès : voir l'[illustration A de R08](08-securite-identites-isolation.md#exemple-autorisation) pour Gate et RLS, omis dans les extraits ci-dessous. Les contraintes propres au produit relèvent du service métier selon R03.

Chemins PHP ci-dessous relatifs à `app/Modules/Dining/Orders/`. Imports locaux/namespaces et injection sont omis ; chaque classe/enum constitue son propre fichier.

`Enums/Cooking.php` : valeurs techniques communes à la validation et au métier.

```php
declare(strict_types=1);

enum Cooking: string
{
    case Rare = 'rare';
    case Medium = 'medium';
    case WellDone = 'well_done';
}
```

`UseCases/ChangeCooking/ChangeCookingInput.php` : commande interne, sans méthode de validation ni accès aux données.

```php
declare(strict_types=1);

final readonly class ChangeCookingInput
{
    public function __construct(
        public string $restaurantId,
        public string $mealId,
        public string $orderItemId,
        public Cooking $cooking,
    ) {}
}
```

`UseCases/ChangeCooking/ChangeCookingInputFactory.php` : même entrée pour le contrôleur et l'outil. Restaurant et repas viennent du contexte vérifié ; l'identifiant d'article reçu reste à rechercher dans ce périmètre et à autoriser dans l'action. Le payload ci-dessous ne contient que le choix de cuisson.

```php
declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ChangeCookingInputFactory
{
    /** @param array<string, mixed> $payload */
    public function create(
        array $payload,
        string $restaurantId,
        string $mealId,
        string $orderItemId,
    ): ChangeCookingInput {
        $validated = Validator::make(['input' => $payload], [
            'input' => ['required', 'array:cooking'],
            'input.cooking' => ['required', 'string', Rule::enum(Cooking::class)],
        ])->validate();

        return new ChangeCookingInput(
            $restaurantId,
            $mealId,
            $orderItemId,
            Cooking::from($validated['input']['cooking']),
        );
    }
}
```

La factory rejette champs supplémentaires et cuisson invalide avant construction. HTTP et IA appellent la même `ChangeCookingAction`, illustrée dans R03, qui retourne `void`. Dans cet exemple, le succès mène à la lecture par redirection HTTP ou actualisation selon R11 ; R22/R15 adaptent la réponse à l'IA.

Exemple IA : « Saignant » peut être converti en `rare` si cette correspondance est déclarée ; sinon l'appel `saignant` est rejeté puis peut être corrigé. « Pas trop cuit » peut demander une précision. Un article déjà accepté reste un refus métier.

`UseCases/ShowOrderItem/OrderItemData.php` : projection pour Vue, distincte de la commande interne. L'usage de Data illustre la candidature décrite en R04-003.

```php
declare(strict_types=1);

use Spatie\LaravelData\Data;

final class OrderItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly Cooking $cooking,
    ) {}
}
```

`UseCases/ShowOrderItem/ShowOrderItemAction.php` : extrait de méthode. Le repository injecté fournit l'article autorisé avec `id` et `cooking` chargés ; le cast Eloquent de `cooking` retourne `Cooking`. La lecture vérifie à nouveau l'accès dans son propre contexte.

```php
public function execute(
    string $restaurantId,
    string $mealId,
    string $orderItemId,
): OrderItemData {
    $item = $this->repository->findForDisplay($restaurantId, $mealId, $orderItemId);

    return new OrderItemData(id: (string) $item->id, cooking: $item->cooking);
}
```

L'adaptateur de page sérialise ce résultat avec `toArray()` dans la prop `item`. Le modèle reste interne à `Dining` ; aucune requête n'est déclenchée par cette transformation. JSON attendu : `{"item":{"id":"item_42","cooking":"rare"}}`.

`contracts/order-item.generated.ts` : forme attendue du générateur, jamais entretenue manuellement.

```ts
export type OrderItemData = {
    id: string;
    cooking: 'rare' | 'medium' | 'well_done';
};
```

`components/OrderItemSummary.vue` : réception du contrat dans le composant, transmis par la page.

```vue
<script setup lang="ts">
import type { OrderItemData } from '../contracts/order-item.generated';
defineProps<{ item: OrderItemData }>();
</script>
```

## 3. Représenter les valeurs sans ambiguïté

**R04-011 — Distinguer absent, null, vide et zéro.**

Définir le sens de chaque valeur et du défaut. Pour une modification partielle, absent peut conserver et null effacer si autorisé. Ne pas perdre cette distinction avec `isset`, `??` ou une conversion ; un marqueur `Optional` doit être omis du JSON. Un champ différé absent n'est pas une valeur métier nulle.

**Contrôle :** sens conservé de la normalisation jusqu'à l'application et la sérialisation.

**R04-012 — Préserver identifiants et enums.**

Traiter les identifiants comme opaques. Leur représentation doit conserver toute leur précision en PHP/JSON/JS : ne pas utiliser un nombre JSON hors de la plage entière sûre JS ; `bigint` ne rend pas ce transport automatiquement compatible. Définir les valeurs d'enum sérialisées et le traitement de l'inconnu, sans défaut arbitraire ni interprétation d'un libellé traduit. R05 choisit les clés physiques.

**Contrôle :** aller-retour des grandes valeurs et comportement explicite face à l'inconnu.

**R04-013 — Conserver l'exactitude des montants.**

Définir devise et unité/échelle avec la valeur. Utiliser entiers bornés ou décimales exactes ; aucun float binaire pour les calculs monétaires exacts. Distinguer précision intermédiaire et montant final en unités mineures. R03 fixe conversion/arrondi, R05 le stockage. En JSON/JS, employer une chaîne si nécessaire ; un libellé localisé n'est pas une valeur de calcul.

**Contrôle :** bornes, unités et arrondis vérifiés ; aucune conversion frontend en `number` sans justification.

**R04-014 — Distinguer instant, date, horaire et durée.**

Échanger les instants en UTC, ISO 8601 avec `Z` et précision déclarée. Conserver les dates civiles sans conversion de fuseau. Un horaire local porte son fuseau IANA et la date nécessaire pour devenir un instant ; traiter explicitement les heures inexistantes/ambiguës. Une durée porte une unité, pas un fuseau.

**Contrôle :** aller-retour et changements d'heure ; aucun choix silencieux de bibliothèque ni dépendance au fuseau serveur/navigateur.

**R04-015 — Définir les clés et collections du JSON.**

Utiliser anglais et `camelCase` pour les clés applicatives maîtrisées ; mapper explicitement SQL et fournisseurs sans renommer les clés protocolaires imposées. Préserver la langue des données utilisateur. Définir éléments, bornes, ordre et métadonnées ; distinguer listes et objets JSON, notamment après filtrage des indices PHP. Adapter la pagination avec R06, sans total coûteux inutile.

**Contrôle :** forme/taille réelles, ordre stable, aucun champ écrasé ou sens modifié par mapping.

**Illustration B — Modification partielle.** Cas indépendant : sous-titre de carte `subtitle`. Ici, les espaces extérieurs sont retirés mais le vide n'est pas converti en null ; adapter le middleware HTTP pour conserver cette distinction jusqu'à validation.

| Entrée | Traitement |
| --- | --- |
| `{}` | Conserver le sous-titre. |
| `{"subtitle":null}` | Effacer. |
| Chaîne vide ou espaces seuls | Rejeter. |
| `{"subtitle":" Lunch "}` | Remplacer par `"Lunch"`. |
| `{"subtitle":0}` | Rejeter le type incorrect, sans conversion. |

```php
// Incorrect: explicit null is replaced by the current value.
$nextSubtitle = $validated['subtitle'] ?? $currentSubtitle;

// UpdateMenuSubtitleInput.php: internal PHP DTO.
final readonly class UpdateMenuSubtitleInput
{
    public function __construct(
        public bool $hasSubtitle,
        public ?string $subtitle,
    ) {}
}

// Input factory: preserve presence after normalization and validation.
$input = new UpdateMenuSubtitleInput(
    hasSubtitle: array_key_exists('subtitle', $validated),
    subtitle: $validated['subtitle'] ?? null,
);

// Service: absence preserves, explicit null clears.
$nextSubtitle = $input->hasSubtitle
    ? $input->subtitle
    : $currentSubtitle;
```

DTO, factory et service sont des fichiers distincts sous `app/Modules/Catalog/Menus/UseCases/UpdateMenuSubtitle/` selon R02 ; seuls leurs éléments décisifs sont montrés. L'action transmet le DTO au service. Ici `?? null` est sûr dans la factory car `hasSubtitle` conserve la présence ; le service ignore la valeur si ce marqueur est faux. Dans un autre contrat autorisant zéro, il faut le préserver.

**Illustration C — Représentations exactes.** Exemples de formats, sans adoption de bibliothèque monétaire ni arrondi des calculs intermédiaires.

| Sens | PHP préparé | JSON | Type/usage TS |
| --- | --- | --- | --- |
| 12,50 EUR | Entier décimal `'1250'`, devise `EUR`, échelle `2` | `{"minorUnits":"1250","currency":"EUR","scale":2}` | Chaîne exacte + devise/échelle ; formatage dédié. |
| Identifiant | `'9007199254740993'` | `"9007199254740993"` | `string` opaque. |
| Instant | `DateTimeImmutable` UTC, précision seconde | `"2026-09-29T18:30:00Z"` | Chaîne représentant un instant, pas un objet JS `Date`. |
| Date civile | `'2026-09-29'` validé | `"2026-09-29"` | Date sans changement de fuseau. |
| Réservation locale | Date, heure et fuseau validés | `{"date":"2026-09-29","time":"20:30:00","timeZone":"Europe/Paris"}` | Objet de chaînes, à convertir selon R04-014. |

Une chaîne TS ne valide ni date ni entier décimal. Un horaire `02:30` lors d'un changement d'heure peut demander rejet ou clarification/désambiguïsation.

## 4. Construire des sorties stables et sûres

**R04-016 — Garder les valeurs du DTO immuables.**

Après construction, produire une nouvelle valeur pour un changement. `readonly` ne protège pas les objets imbriqués : utiliser dates immuables, structures maîtrisées ou copies nécessaires. Respecter les contraintes d'héritage PHP. Séparer données stables et configuration mutable de transformation du package, sans partage involontaire entre consommateurs.

**Contrôle :** modifier la source ou un objet imbriqué ne change pas par surprise le contrat déjà remis.

**R04-017 — Transformer sans charger ni modifier de données.**

Constructeur, factory de sortie et sérialisation utilisent l'état préparé explicitement : aucun SQL, écriture métier, utilisateur/langue/horloge implicite. Examiner relations, accessors, casts et closures différées. Une donnée manquante doit être chargée par la lecture autorisée ou signalée ; ne pas transmettre entre modules une closure de chargement privé.

**Contrôle :** zéro requête pendant la transformation, y compris avec relation absente ou accessor.

**R04-018 — Sélectionner les champs exposés.**

Construire une liste explicite selon le destinataire, objets imbriqués et paramètres d'erreur compris. Ni sérialisation générale de modèle/requête/exception, ni champ caché dans Vue ou Data ne remplace l'autorisation serveur.

**Contrôle :** examiner le JSON réel avec les droits de chaque destinataire concerné ; aucune inclusion optionnelle ne révèle de champ privé.

**R04-019 — Examiner le coût de transformation.** **Revue.**

Vérifier volumes, parcours, copies et allocations. Transformer uniquement les données nécessaires à chaque consommateur ; ne pas charger un historique entier ou fabriquer un DTO géant pour en garder trois champs.

**Contrôle :** volume borné, coût mesuré avec R06/R19, absence de N+1 ; un petit jeu d'essai ne justifie pas une croissance ignorée.

## 5. Relier les contrats serveur à Vue

**R04-020 — Générer les types de transport TypeScript.**

Sélectionner les contrats serveur exportables et leurs dépendances ; ils font autorité. Générer leurs types TS sans copie manuelle concurrente ni export général des classes PHP. Les états locaux Vue et brouillons de formulaire restent frontend lorsque leur sens diffère. Ranger les fichiers générés selon R02, avec source/commande identifiables.

**Contrôle :** régénération commune reproductible et vérification TS/Vue avec R20/R21, au développement/livraison ; aucune édition manuelle ni génération par requête métier.

**R04-021 — Vérifier les types contre le JSON réel.**

Contrôler noms mappés, champs omis, nullabilité, enums, dates, collections et unions, y compris les transformations personnalisées. Les méthodes PHP ne sont pas transférées au navigateur. Les types TS ne valident pas le réseau à l'exécution : prévoir les contrôles nécessaires au risque de chaque entrée.

**Contrôle :** génération, compilation et sérialisation concordantes ; aucun cast TS ne masque un écart.

**R04-022 — Distinguer les états de chargement Inertia.**

Séparer formulaire, entrée validée, props initiales/différées/conditionnelles et état UI. Une donnée non chargée n'est ni vide ni en échec. Une requête différée refait ses contrôles d'accès avant lecture/projection. Lors d'un chargement partiel, conserver uniquement les données encore valides ; invalider celles dont le contexte ou les droits ont changé.

**Contrôle :** visites initiale/partielle/différée, nouvelle session et changement de droits ; fraîcheur et erreurs avec R11/R12/R13.

**R04-023 — Sérialiser les erreurs pour leur destinataire.**

Respecter le parcours de validation Inertia, distinct d'un endpoint JSON ou d'un outil IA. Typer les restitutions définies par R22, sans créer de second catalogue. Séparer diagnostic privé, informations sûres et langue d'affichage ; ne pas envoyer `Throwable` ou son message brut.

**Contrôle :** validation, incident imprévu et code inconnu restent sûrs ; ne pas inventer le sens d'un code ni annoncer un succès.

**Illustration D — Un contrat chargé ou modifié.** Variantes de l'article de l'illustration A ; une liste d'articles utilise la prop `items`.

| Situation | Comportement attendu |
| --- | --- |
| `items` différé | Absent avant chargement ; `[]` après lecture réussie sans article ; erreur distincte. |
| Rechargement partiel | Réutiliser seulement les props encore valides ; invalider/recharger après changement de repas ou de droits. |
| Champ ajouté | Ancien lecteur tolérant si son contrat le permet ; une écriture peut rejeter les clés inconnues. |
| Nouvelle valeur de `cooking` | Assurer la compatibilité ou traiter explicitement l'inconnu, sans conversion arbitraire ni action hasardeuse. |
| Renommage illustratif `cooking` → `cookingLevel` | Régénérer et vérifier TS/Vue : les consommateurs qui accèdent à `item.cooking` doivent être adaptés. Déclarer seulement la prop `item` ne provoque pas cette erreur. Prévoir la transition des navigateurs déjà ouverts avec R21. |
| `errorCode` inconnu | Retour général sûr selon R22/R13/R15, sans diagnostic brut ni reprise déduite du message. |

## 6. Préserver le sens des messages dans le temps

**R04-024 — Choisir entre identifiant à relire et instantané.**

L'identifiant implique une relecture autorisée d'un état pouvant changer ; l'instantané représente les valeurs d'un fait passé. Définir contexte, fraîcheur et données minimales. Ne pas faire dépendre le message durable du graphe Eloquent actuel ; revalider les conditions requises à l'exécution selon R03/R08/R10. Identifier les données transitoires ou interdites de persistance d'après les exigences et R09 ; les exclure des messages et erreurs durables et vérifier cette exclusion.

**Contrôle :** sens conservé après modification/suppression de l'objet, changement d'accès ou passage du temps ; reprise avec R10/R17.

**R04-025 — Prévoir les anciens consommateurs.**

Avant modification, examiner navigateurs ouverts, messages/jobs en attente et fournisseurs. Un ajout n'est pas toujours compatible : lecteurs stricts, enums exhaustifs, champs requis et changement de sens doivent être vérifiés. Fixer traitement de l'inconnu et transition par contrat ; versionner/adapter si nécessaire, sans version artificielle de chaque classe interne.

**Contrôle :** ancien client/message testé, sans perte ni réinterprétation ; génération TS seule insuffisante. Livraison/reprise avec R10/R11/R17/R21.

## 7. Qualifier et vérifier

**R04-026 — Qualifier Laravel Data et le générateur avant adoption.**

Verrouiller un ensemble compatible PHP/Laravel/Data/générateur/Inertia/Vue. Tester découverte des modules, construction/validation explicite, immutabilité, champs imbriqués/optionnels, mapping et concordance JSON/TS. Vérifier le cache de structures à froid, actif et après évolution/reconstruction ; il concerne les classes, pas les données clients. Mesurer temps, mémoire et requêtes sur volumes bornés représentatifs ; comparer au PHP simple lorsque pertinent.

**Contrôle :** versions, commandes, résultats et limites consignés ; adoption ciblée, adaptation ou rejet motivé. Composer seul ne prouve pas le comportement. Seuils avec R19, outillage avec R20/R21.

**R04-027 — Vérifier les risques du contrat modifié.**

Sélectionner les contrôles utiles ci-dessous avec R18. Tester valeurs reçues/émises et absence d'effets interdits, pas la copie du constructeur. Identifier version et consommateurs ; distinguer tests isolés, intégration et mesures. Une assertion de type ou une simulation ne prouve ni absence de SQL, ni accès, ni compatibilité.

**Contrôle :** preuves exécutées, manquantes et futures déclarées selon R01.

| Risque | Vérifications à sélectionner |
| --- | --- |
| Entrées | Équivalence HTTP/IA et invariants ; chemins de construction ; champs sensibles/imbriqués ; casts/enums invalides. |
| Appels IA | Correction de format avant action, confirmation préservée ; clarification/refus/reprise distincts. |
| Valeurs | Absent/null/vide/zéro ; objets imbriqués stables ; montants et grands identifiants exacts ; dates/fuseaux. |
| Lectures et sorties | Zéro SQL de transformation ; collections bornées et coût ; droits/fraîcheur des états Inertia. |
| Transport et évolution | JSON/TS concordants, renommages détectés ; anciens clients/jobs compatibles ; erreurs sans fuite. |
| Outillage | Découverte modulaire, cache actif et renouvelé après évolution des contrats. |

Sélectionner les vérifications selon le contrat, ses risques et les outils concernés, en appliquant les garanties de R04 et R18.
