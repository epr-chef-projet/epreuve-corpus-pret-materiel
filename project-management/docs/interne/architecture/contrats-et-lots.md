# Contrats publics et dépendances d'intégration

**Version 1.2 - contrats d'intégration.** [Architecture](../../cahiers-des-charges/technique/architecture-contrats.md) et [transactions](../../cahiers-des-charges/technique/transactions-etats.md) possèdent les choix de conception ; ce document possède les formes exactes. Les noms d'opérations ci-dessous sont des signatures sémantiques à implémenter, pas du code fourni ni une qualification acquise.

<a id="vocabulaire"></a>

## 1. Vocabulaire et réception

| Élément     | Contrat                                                                                                                                                                        |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Identifiant | UUID opaque, chaîne canonique minuscule ; génération UUIDv7 côté serveur. `operationKey` est un UUID créé pour une intention, conservé pour ses reprises.                      |
| Version     | Entier `1..2147483647`, nombre JSON sûr ; saturation refusée, aucun retour à zéro. `expectedVersion` obligatoire pour modifier une ressource existante.                        |
| Quantité    | Ligne `1..999999` ; total `0..999999`. Demande ≤100 lignes uniques ; brouillon vide admis, confirmation/remplacement non vides.                                                |
| Texte       | Nom 1..120, catégorie 1..80, description nullable ≤2000, motif d'équipe 1..500 ; caractères Unicode, espaces extérieurs retirés. Description vide normalisée en null.          |
| Recherche   | Fragment littéral du nom ≤120 caractères, insensible à la casse ; catégorie facultative, correspondance exacte. Aucun joker SQL fourni par le client.                          |
| Navigation  | `page` entier ≥1 ; `perPage` défaut 25, maximum 100. Filtres modifiés : page 1. Pas de total global obligatoire.                                                               |
| Instant     | ISO 8601 UTC, précision milliseconde, suffixe `Z`. `observedAt` indique l'heure de préparation de la projection ; ce n'est ni une version ni une promesse d'actualité.         |
| États       | Demande : `draft`, `pending`, `accepted`, `handed_over`, `returned`, `rejected`, `cancelled`. Image/document : `pending`, `ready`, `failed`. Référence : `active`, `archived`. |

Les clés inconnues sont rejetées. Entiers JSON ou chaînes décimales de chiffres, après retrait des espaces extérieurs, sont admis puis normalisés ; booléens, fractions et notation exponentielle sont refusés. UUID, états et versions sont validés avant construction. Acteur, rôle et atelier ne sont pas des champs de commande du navigateur.

<a id="capacites"></a>

## 2. Capacités PHP publiques

`ActorContext` est produit par IdentityAccess depuis la session valable : `actorId`, `workshopId`, `role: borrower|staff|manager`. Il ne remplace pas les contrôles de droits courants. Les DTO sont des valeurs sans accès différé ; chaque signature métier reçoit ce contexte serveur. Les jobs utilisent un `ServiceContext` distinct, construit depuis la capacité configurée et le routage contrôlé des effets : aucune session humaine inventée ni privilège hérité du rôle du demandeur. `Page<T>` contient `items`, `page`, `perPage`, `hasMore` et `observedAt`, avec liste dense ordonnée.

| Propriétaire / capacité                     | Entrée → résultat                                                            | Préconditions et sens                                                                                                                                     |
| ------------------------------------------- | ---------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| IdentityAccess / `ResolveActor`             | Session → `ActorContext`                                                     | Authentification et contexte fiable selon [sécurité](../../cahiers-des-charges/technique/donnees-securite.md), aucun atelier choisi par le client.        |
| Catalog / `ReadEquipmentPage`               | Recherche, catégorie, page, taille, audience → `Page<EquipmentData>`         | Tri nom puis UUID ; actif pour catalogue de prêt, archivés seulement pour gestion responsable.                                                            |
| Catalog / `ReadEquipmentBatch`              | ≤100 UUID distincts → liste `EquipmentData`                                  | Lecture du même atelier ; manque/hors atelier indistinguables, sans données étrangères.                                                                   |
| Catalog / `LockEquipmentBatch`              | ≤200 UUID distincts → liste `EquipmentState`                                 | Transaction active et même contexte ; ordre UUID global ; protection conservée jusqu'au commit extérieur. Borne 200 réservée à l'union d'un remplacement. |
| Catalog / `ReplaceTotalWithinUnit`          | UUID, total, version attendue, `minimumCommittedQuantity` → nouvelle version | Responsable ; référence protégée sur la même connexion ; minimum fourni par WorkshopOperations depuis les engagements protégés, total ≥minimum.           |
| Lending / `ReadCommitments`                 | ≤100 UUID distincts → `CommitmentTotals[]`                                   | Agrégat de tous les engagements de l'atelier, via capacité SQL étroite possédée par Lending ; aucun prêt, auteur ou historique retourné.                  |
| WorkshopOperations / `ReadEnrichedCatalog`  | Filtres/page/audience → page autorisée                                       | Composition dans un instantané commun court ; absences d'engagement valent zéro.                                                                          |
| WorkshopOperations / `ChangeEquipmentTotal` | UUID, total, version attendue, clé d'opération → `CommandReceipt`            | Action extérieure atomique appelant Catalog et Lending ; aucun SQL voisin.                                                                                |

`EquipmentData/State` : `equipmentId`, `name`, `description`, `category`, `totalQuantity`, `status`, `equipmentVersion`, `image: ImagePublishedData|null`. Image publiée : `imageId`, `generation`, `thumbnail` et `detail`, chacun `{url,width,height,mimeType:"image/webp"}` ; les dimensions vérifiées sont stockées à la publication, les URLs sont des routes privées. `CommitmentTotals` : `equipmentId`, `reservedQuantity`, `handedOverQuantity`. Ces données intermodules ne sont pas exposées intégralement au navigateur. Les appels d'agrégat portant sur une union de 200 sont fractionnés en lots ≤100. Les exceptions publiques utiles sont celles de la section erreurs.

### Accès et unités SQL utilisables

`IdentityAccess/PublicApi/ResolveActor::handle(Request)` produit le contexte depuis la session serveur revalidée, y compris lors d'un appel direct. `AuthorizeActor::handle(context, ability)` recontrôle l'identité courante et applique les capacités fermées `borrow`, `operate-loans`, `manage-catalog` ; une capacité inconnue est refusée. Les Gates HTTP utilisent la même matrice. Les actions des consommateurs peuvent ainsi autoriser un contexte sans importer le modèle interne d'utilisateur.

`App\Modules\IdentityAccess\PublicApi\SqlUnit::forActor(context, closure)` ouvre l'unité sur la connexion métier `pgsql` (connexion logique `app` du CDC), vérifie l'identité courante et établit les paramètres PostgreSQL locaux avant le travail. Cette capacité appartient à IdentityAccess, qui possède la résolution de l'autorité et son établissement SQL ; les consommateurs passent par son API publique. `forActor(ActorContext, Closure(): void): void` appelle le travail sans argument et ne retourne aucune valeur. Le cas d'usage propriétaire constitue son résultat matérialisé ; la capacité ne lui transmet ni connexion, ni modèle, ni builder, ni chargement différé. L'unité fixe localement `lock_timeout=2s` et `statement_timeout=5s` ; elle n'effectue pas de rejeu automatique. Les repositories participants appellent `assertActor(connection, context): void` avant leur premier accès protégé, en fournissant leur propre connexion ; cette garde refuse absence de transaction, autre connexion ou contexte différent. Une unité imbriquée ne change pas silencieusement d'atelier, d'acteur ou de capacité.

`forService(ServiceContext, Closure(): void): void` suit le même contrat de travail sans argument ni résultat et exige l'identité et la capacité configurées du processus (`IDENTITY_SERVICE_ID`, `IDENTITY_SERVICE_CAPABILITIES`), sans rôle utilisateur. Aucun droit de service n'est activé par défaut dans HTTP. La seule capacité introduite pour les sessions est `identity_access.publish` ; les futures capacités de traitement doivent être ajoutées avec leurs usages et preuves.

`AdmitSessionPublication::handle(service, actorId, role, broadcastToken)` est réservé au producteur ayant déjà établi l'audience autorisée depuis sa source métier. Il ne recherche pas des destinataires à partir d'une demande navigateur. Il rend `null` ou `{channel,authGeneration}` après vérification du même atelier, du compte/rôle courant, de l'expiration/révocation et de la session vivante via le handler Laravel configuré. Il partage le verrou de la ligne de session avec la révocation : une admission acquise avant celle-ci peut déjà être engagée ; aucune nouvelle admission après sa validation. Le résultat est destiné à la publication immédiatement admise, pas à un cache de routes réutilisables. Aucun identifiant de session ni ciphertext n'est transmis au publisher. L'abonnement navigateur et la qualification du socket maintenu après sortie complètent ce contrat avec le cadre frontend.

<a id="commandes"></a>

## 3. Commandes et résultats HTTP

Toutes les commandes métier reçoivent `operationKey` ; les identifiants de route sont contrôlés dans le contexte courant. Le corps de création d'un brouillon comporte `items: [{equipmentId, quantity}]`. Sauvegarde de brouillon et remplacement transmettent la liste complète et `expectedVersion` ; retrait signifie omission explicite dans cette proposition complète. Confirmation transmet `expectedVersion` et `locale: fr|en`, fixée uniquement lors de la première réussite. Connexion/déconnexion conservent leur contrat de session distinct.

Acceptation, remise et retour transmettent version et clé. Refus et annulation d'équipe ajoutent `reason` ; annulation de l'auteur ne le demande pas. Suppression vise seulement un brouillon. Création/édition Catalog utilisent les champs décrits en section 1 ; création reçoit un total initial sans engagement, édition textuelle ne peut changer le total. Actif/archivé et changement du total sont des commandes séparées. Réception, retrait et reprise d'image, ainsi que reprise du document, suivent leurs contrats de [fichiers](../../cahiers-des-charges/technique/traitements-fichiers.md).

`CommandReceipt` contient `operationKey`, `operationType`, `resourceType: equipment|loanRequest|equipmentImage|loanDocument`, `resourceId`, `resultVersion`, `recordedAt`, et `replayed`. Le type d'opération appartient à une liste fermée déclarée par module, préfixée `catalog.`, `lending.` ou `workshop_operations.` ; il identifie la commande précise, jamais une classe libre. Le reçu atteste l'effet initial ; une lecture autorisée retrouve ensuite l'état actuel. Supprimer un brouillon conserve son reçu et sa dernière version supprimée. `ReadOperationResult(type, clé)` rend `applied` avec reçu ou `notEstablished`. L'absence ne prouve pas un échec : seule une reprise explicitement coordonnée, avec même clé/contenu et acquisition atomique, peut départager un appel initial encore actif ; jamais une nouvelle clé pour contourner l'incertitude.

<a id="projections"></a>

## 4. Projections de présentation

Catalogue emprunteur/agent : UUID, nom, catégorie, image publiée, quantité disponible, version de référence ; détail ajoute description. Gestion responsable ajoute total, réservé, remis et état actif/archivé. `equipmentVersion` ne classe pas les modifications d'engagement ; une disponibilité changée exige relecture, selon le [frontend](../../cahiers-des-charges/technique/frontend-temps-reel.md).

Liste personnelle : `requestId`, `reference`, `createdAt`, `state`, `requestVersion`, aperçu `{items:[{name,quantity}],remainingCount}` limité aux trois premières lignes, sans description. Tableau équipe ajoute l'identité de démonstration de l'auteur et `confirmedAt`, exclut brouillons. Ordres : création décroissante puis UUID décroissant pour liste personnelle ; confirmation croissante puis UUID croissant pour attente équipe ; confirmation décroissante puis UUID décroissant pour les autres états d'équipe. Détail : état/version, dates, lignes `{equipmentId,name,description,category,quantity}`, actions autorisées, motif, page d'historique et état du document nullable avant acceptation. Les textes viennent des instantanés dès confirmation ; dans un brouillon, ils viennent du Catalogue courant par lot, y compris pour identifier une ancienne ligne devenue non admissible, sans proposer cette référence à de nouveaux choix. Historique paginé : `id,action,actor:{actorId,displayName},occurredAt,requestVersion,reason` ; une ancienne composition se consulte séparément par sa version, jusqu'à 100 lignes. Aucun champ privé ne devient accessible par sa présence dans un type généré.

Contrat Reverb v1 : `eventId` UUID, `contractVersion: 1`, `authGeneration` entier, `type` et `payload` de l'union fermée suivante. La route par session détermine le destinataire ; un champ reçu ne choisit jamais ses droits.

| Type                   | Payload exact                                                                                                                                      |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `request.snapshot`     | `resource:{type:loanRequest,id}`, `projection:requestCore`, `version`, `data:RequestCoreData`.                                                     |
| `document.snapshot`    | `resource:{type:loanDocument,id}`, `projection:documentState`, `version`, `data:DocumentStateData`.                                                |
| `catalog.invalidated`  | `projection:catalog\|catalogManagement`, sans lignes ou identifiants privés.                                                                       |
| `requests.invalidated` | `projection:ownRequests\|teamRequests`, ou `projection:requestCore\|requestHistory\|documentState,requestId` seulement pour une demande autorisée. |

`RequestCoreData` : `reference`, `state`, `author:{actorId,displayName}`, `createdAt`, `confirmedAt`, `acceptedAt`, `items` historiques complets, `reason`, `allowedActions` ; dates non acquises/motif absent null, jamais champs omis. Actions fermées : `saveDraft`, `deleteDraft`, `confirm`, `replaceItems`, `accept`, `reject`, `cancel`, `handOver`, `return`, sélectionnées par droits/état. Historique/document sont actualisés séparément. `DocumentStateData` : `requestId`, `state`, `downloadUrl` nullable (route privée seulement si ready), `errorCode` public nullable, `canRetry` booléen. `version` est celle de la demande/document, jamais celle du Catalogue pour ses engagements. Snapshot ≤16 Kio ; sinon invalidation ciblée. Versions contradictoires/inconnues : relecture HTTP. Admission, buffer et récupération : [EPR-T-065 à 067](../../cahiers-des-charges/technique/frontend-temps-reel.md).

<a id="erreurs"></a>

## 5. IssueData et reprises

Forme exacte de la restitution sûre, DTO partagé `App\Shared\Errors\IssueData` : `errorCode: string`, `messageKey: string`, `parameters: objet`, `correlationId: UUID`, `outcome: notApplied|applied|unknown`, `nextAction: {kind,messageKey}` ; `fieldErrors` facultatif, objet indexé par chemin de champ, contenant des listes de `{messageKey,parameters}`. Ce DTO de présentation est candidat Data selon qualification ; les paramètres sont sélectionnés par code, sans objet exception, message interne, trace ou SQL.

`nextAction.kind` appartient à `correctInput`, `reload`, `signIn`, `verifyOperation`, `retrySameOperation`, `contactStaff`, `contactManager`. Sa clé est exactement `actions.<kind>` dans FR/EN. `messageKey` principal est `issues.<errorCode>` ; repli `issues.operations.unexpected_failure`. Un code inconnu ne déduit jamais une reprise. Exemple sémantique : `lending.content_conflict`, paramètres vides, `notApplied`, action `reload` ; pas de texte anglais diagnostique envoyé comme traduction.

| Famille / code proposé                                        | Paramètres publics admis                          | Suite principale                                                    |
| ------------------------------------------------------------- | ------------------------------------------------- | ------------------------------------------------------------------- |
| `validation.invalid_input`                                    | Erreurs par chemin de champ, bornes de ce contrat | Corriger ; nouvelle clé si intention modifiée.                      |
| `identity_access.authentication_required`                     | Aucun                                             | Reconnexion puis droits/état courants.                              |
| `identity_access.resource_unavailable`                        | Aucun                                             | Même réponse pour absent et privé inaccessible.                     |
| `identity_access.operation_not_allowed`                       | Aucun                                             | Action refusée par le rôle ; aucune ressource privée révélée.       |
| `lending.insufficient_quantity`                               | Lignes concernées et quantité disponible, ≤100    | Corriger ; aucune demande étrangère identifiée.                     |
| `lending.reference_not_admissible`                            | Lignes de la saisie et motif sûr                  | Corriger sans confirmer l'existence hors atelier.                   |
| `lending.content_conflict` / `lending.transition_not_allowed` | État actuel si déjà autorisé                      | Relire ; aucun retry automatique.                                   |
| `catalog.total_below_commitments`                             | `minimumQuantity`, responsable seulement          | Corriger.                                                           |
| `operations.intent_mismatch`                                  | Aucun contenu de la preuve initiale               | Nouvelle intention explicite, jamais écraser le reçu.               |
| `operations.temporarily_unavailable`                          | Aucun                                             | Même clé seulement après rollback établi et contrôle courant.       |
| `operations.unexpected_failure`                               | Aucun                                             | Vérifier l'opération si issue inconnue, sinon aide adaptée au rôle. |

Pour Inertia, préserver redirection, error bags et `errors` standard traduits ; la prop `issue` porte cette forme lorsqu'une issue globale est à expliquer. La prop complémentaire `validationPresentation:{locale,fieldErrors}` reprend uniquement les clés/paramètres publics des erreurs produites, dans le même error bag. Le composable peut ainsi les reformater lors d'un changement de langue sans renvoyer la commande ; les erreurs natives sans mapping gardent le repli sûr traduit et leur détail dans la langue de réception. Un endpoint JSON déclaré rend la même restitution sous `issue` : validation 422, conflit 409, authentification 401, action de rôle refusée 403, ressource privée 404, indisponibilité 503, incident 500. Une validation Inertia n'est pas remplacée par un JSON 422 générique. Aucun client ne rejoue automatiquement une mutation après coupure.

<a id="catalogue-erreurs"></a>

## 6. Catalogue et correspondance des causes

Les fiches par propriétaire décriront sens, paramètres internes/publics, consommateurs, effets connus et preuves ; la déclaration future dans la classe ou le mapper possède le code. Cartographie globale proposée : `Lending\Requests\Errors\InsufficientQuantity`, `ReferenceNotAdmissible`, `ContentConflict`, `TransitionNotAllowed` correspondent aux quatre codes Lending ci-dessus ; `Catalog\PublicApi\Errors\TotalBelowCommitments` au code Catalog consommé par WorkshopOperations. Préfixe des classes : `App\Modules\`. Seules les exceptions réellement consommées entre modules sont publiques.

Le contrat `App\Shared\Errors\DomainError` ne contient que `errorCode()`. Les mappings IdentityAccess adaptent validation/authentification/autorisation/ressource natives sans wrapper mécanique. Infrastructure/Operations possède `IntentMismatch` et les mappings d'incident ; `QueryException` reste native et n'est classée transitoire que selon condition SQL et rollback établis. Le repli `Throwable` ne prend pas le sens d'un refus métier. Les erreurs de fichiers sont expliquées par leur source propriétaire. Classes et mappings seront vérifiés avec le code ; ils n'existent pas encore.

<a id="lots"></a>

## 7. Dépendances et raccordements communs

Chaque module possède ses migrations, ses routes et son provider. Les développements consommateurs s'appuient sur des fondations utilisables : IdentityAccess et contexte SQL, contrats partagés et génération des types, schéma Equipment et contraintes/FK nécessaires, capacités publiques Catalog. Ces dépendances n'autorisent ni réinstallation du projet ni préimplémentation des usages du module consommateur.

Catalog possède Equipment/Images, sans calcul des engagements. Lending possède demandes, engagements, historique et PDF. WorkshopOperations compose les capacités réelles Catalog/Lending pour les lectures enrichies et la modification du total ; ses pages et vérifications transverses ne deviennent pas des usages internes de Catalog.

Raccorder explicitement les providers dans `bootstrap/providers.php`, l'authentification/RLS, la navigation et la génération commune. Les contrats générés ont leurs sources chez chaque propriétaire, une seule génération commune et aucune édition manuelle. Coordonner les changements touchant ces raccordements. Pour une évolution de contrat partagé, identifier les consommateurs, examiner la compatibilité, faire approuver le changement puis actualiser tous les consommateurs concernés.

Les doubles temporaires respectent les contrats publics et restent bornés ; ils ne prouvent pas l'intégration des modules réels. Tout outillage personnalisé supplémentaire doit avoir un besoin et un périmètre explicites, sans être ajouté automatiquement à une contribution.

<a id="reception"></a>

## 8. Qualification des dépendances

L'installation et les raccordements techniques ne qualifient pas les contrats, fixtures et fonctionnalités qui les consomment. Vérifier le contexte fiable, l'isolation sous le rôle réel, le schéma/FK et les fixtures nécessaires, les signatures/types et la génération PHP/JSON/TS sans SQL dans les transformations. Identifier la révision commune utilisée par les contributions dépendantes.

La recette intégrée appelle les capacités réelles Catalog, Lending et WorkshopOperations. La compatibilité Data v4/générateur v3 se qualifie sur les contrats ou la génération concernés selon [R04](../../../../.ai/rules/socle/04-contrats-dto-validation.md). Les [recettes](../developpement/qualite-et-recette.md) et la [qualification technique](../../cahiers-des-charges/technique/qualification.md) possèdent commandes, profils et critères ; leur rédaction ne constitue pas un résultat d'exécution.
