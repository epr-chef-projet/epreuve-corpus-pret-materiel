# Architecture et contrats

**Version 1.2 - spécification technique.** [Index technique](README.md). Les [comportements fonctionnels](../fonctionnel/README.md) restent propriétaires du produit. Ce chapitre fixe les responsabilités ; les signatures et paramètres sont possédés par les [contrats publics et dépendances](../../interne/architecture/contrats-et-lots.md).

<a id="epr-t-001"></a>

## EPR-T-001 - Propriétaires

Le monolithe comporte quatre modules, sans faire de chaque table un module.

| Module               | Responsabilités et données possédées                                                                                        | Exclusions                                              |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| `IdentityAccess`     | Comptes, ateliers, authentification, résolution du contexte d'accès fiable et établissement des unités SQL contextualisées. | Gestion des membres dans l'interface, règles de prêt.   |
| `Catalog`            | Références de matériel, descriptions, quantité totale, actif/archivé, images et leur génération.                            | Engagements, demandes et calcul de disponibilité.       |
| `Lending`            | Demandes, lignes, engagements dérivés, historique, instantané d'acceptation et bordereau.                                   | Écriture des références et du total du Catalogue.       |
| `WorkshopOperations` | Catalogue enrichi pour ses audiences et modification du total confrontée aux engagements.                                   | Copies persistées concurrentes des références ou prêts. |

Les tables et contraintes sont définies dans [données et sécurité](donnees-securite.md). `Infrastructure/Operations` possède la persistance des preuves d'opération ; `Infrastructure/Delivery` possède outbox, obligations durables et relais. Ces mécanismes reçoivent des valeurs construites par les modules, sans connaître leurs classes internes. Il n'existe ni module `Stock` autonome ni compteur de disponibilité faisant autorité.

<a id="epr-t-002"></a>

## EPR-T-002 - Dépendances et compositions

Les dépendances de code autorisées sont : `WorkshopOperations → Catalog, Lending, IdentityAccess` ; `Lending → Catalog, IdentityAccess` ; `Catalog → IdentityAccess`. `IdentityAccess` ne dépend d'aucun de ces trois modules. Les contrats communs de `Shared` ne dépendent d'aucun module ; les mécanismes techniques partagés ne connaissent pas leurs détails métier.

Le Catalogue ne rappelle jamais Prêts, directement ou par un type public réciproque. Une lecture enrichie compose une page publique de Catalog et une projection d'engagements de Lending. Une modification du total est une action de WorkshopOperations : elle protège la référence via Catalog, obtient les engagements via Lending, puis demande l'écriture à Catalog. Ce dernier reçoit des valeurs primitives suffisantes, sans importer un DTO Lending. Le protocole atomique est possédé par [transactions et états](transactions-etats.md).

<a id="epr-t-003"></a>

## EPR-T-003 - Construction des usages

Les usages suivent `app/Modules/<Module>/<SousDomaine>/UseCases/<Usage>/`. Les sous-domaines retenus sont Identity/Access, Equipment/Images, Requests/Documents et CatalogBrowse/Inventory, respectivement. Chaque module possède son provider, ses routes et ses migrations selon [R02](../../../../.ai/rules/socle/02-architecture-modules.md), avec raccordement explicite des middlewares.

L'entrée HTTP adapte et autorise ; l'action orchestre et possède l'unité SQL ; le service décide à partir de l'état fourni ; le repository charge/enregistre ; le DTO transporte des valeurs. Les services, factories et sérialisations ne déclenchent aucun SQL, y compris implicite. Les modèles Eloquent restent internes. Aucune façade, interface CRUD universelle ou copie indépendante du modèle métier n'est imposée. Les sessions Laravel conservent leur mécanisme standard.

<a id="epr-t-004"></a>

## EPR-T-004 - Surface publique

Les opérations et tous leurs types imbriqués consommés entre modules vivent dans le `PublicApi` du propriétaire. Ils exposent périmètre, entrée, résultat, erreurs et prérequis transactionnels ; aucun modèle, relation, builder, closure de chargement ou connexion n'en sort. Le provider lie seulement les interfaces ayant un consommateur ou une substitution utile.

Les capacités publiques portent sur les références par lots, leur protection et le changement du total, les agrégats d'engagements, et le contexte d'accès. Les commandes HTTP de Lending ne deviennent pas toutes des API intermodules par principe. Une jointure SQL directe sur les tables voisines n'est pas autorisée par cette conception.

IdentityAccess expose `PublicApi/SqlUnit` pour exécuter le travail d'un acteur ou d'un service sous son contexte SQL vérifié. Cette capacité ne transmet aucun objet SQL au travail, appelé sans argument, et ne retourne aucune valeur. Les repositories participants utilisent leur propre connexion métier ; `assertActor` vérifie cette connexion fournie, la transaction et le contexte, sans restituer de connexion. Les résultats des cas d'usage restent matérialisés selon les contrats publics.

<a id="epr-t-005"></a>

## EPR-T-005 - DTO et transport

Les échanges backend utilisent des DTO PHP ordinaires immuables, avec collections typées. Les contrats serveur exportables sont la source des types TypeScript ; aucune copie manuelle concurrente n'est entretenue. Les états locaux de formulaire restent des types frontend distincts.

`spatie/laravel-data` v4 pour certaines frontières Inertia et `spatie/laravel-typescript-transformer` v3 pour la génération sont des candidats à qualifier sur les contrats ou la génération concernés. La [documentation Data](https://spatie.be/docs/laravel-data/v4/advanced-usage/typescript) renvoie à l'[intégration du générateur v3](https://spatie.be/docs/typescript-transformer/v3/laravel/laravel-data). Leur présence documentaire n'adopte ni résolution Composer ni validation implicite : normaliser et valider avant construction ; vérifier JSON réel, nullabilité, champs optionnels et compilation. Un rejet du package conserve l'obligation de génération des contrats.

<a id="epr-t-006"></a>

## EPR-T-006 - Lectures collectives

WorkshopOperations sélectionne la page Catalog, demande à Lending les seuls engagements des identifiants sélectionnés, puis compose le résultat autorisé. Les demandes et historiques utilisent leurs projections propres. Aucune boucle ne déclenche une requête par ligne ou ne recharge tout l'historique pour produire un aperçu.

Une composition nécessitant total et engagements cohérents utilise une unité courte, en lecture seule `REPEATABLE READ`, sur une seule connexion et sous le même contexte RLS. Détail/`RequestCoreData` lisent également état, version et toutes les lignes dans ce même instantané court ; ne pas associer un ancien en-tête à de nouvelles lignes. L'instantané est acquis à la première lecture ; aucun verrou de demande ou d'équipement. Les listes sont bornées et les DTO matérialisés avant la fin de l'unité. Une nouvelle page est une nouvelle observation, pas la continuation d'un ensemble figé. Coût, plans et volumes sont à éprouver selon [R06](../../../../.ai/rules/socle/06-requetes-lectures-cache.md).

<a id="epr-t-007"></a>

## EPR-T-007 - Erreurs possédées

Une issue normale a un résultat explicite ; un refus métier bloquant produit une exception du propriétaire et sort de l'unité à annuler. Le contrat commun `DomainError` expose `errorCode(): string`. Une exception intermodule et ses paramètres typés restent publics ; les classes natives conservent leur cause.

Les codes anglais suivent `owner.condition_snake_case`. Le catalogue par propriétaire et la correspondance globale code/classes sont définis dans les contrats ; les déclarations futures dans le code feront autorité. Chaque adaptateur construit une restitution sûre avec `errorCode`, clé de traduction et paramètres admis. FR/EN appartiennent à la présentation ; message interne, SQL, trace et objet exception ne sont pas transportés.

<a id="epr-t-008"></a>

## EPR-T-008 - Correspondance frontend

Les pages `Borrower/Catalog`, `Borrower/Requests`, `Team/Requests` et `Manager/Catalog` assemblent les fonctionnalités. Le code métier Vue est dans `resources/js/modules/{identity-access,catalog,lending,workshop-operations}/` avec sous-domaines/usages en kebab-case. Les consommateurs utilisent les exports publics limités ; aucun module n'importe une page ou les détails privés d'un autre module.

WorkshopOperations possède la composition du catalogue ; Lending possède composition de demande, tableau, détail et document ; Catalog possède édition et images. La navigation commune assemble ces capacités sans devenir un propriétaire métier supplémentaire. Props, état local, reconnexion et restitution sont précisés dans [frontend et temps réel](frontend-temps-reel.md).

<a id="epr-t-009"></a>

## EPR-T-009 - Intégration et preuves attendues

Les fondations partagées et les capacités publiques nécessaires doivent être utilisables avant les développements qui en dépendent. Les frontières publiques, migrations et raccordements communs suivent les [dépendances d'intégration](../../interne/architecture/contrats-et-lots.md#lots). Les doubles temporaires respectent ces contrats et ne constituent pas une preuve d'intégration.

Prévoir contrôles des imports/cycles/types/raccordements et revue du SQL réel, puis traversée des deux modules avec isolation et concurrence réelles. Tests rangés par propriétaire selon R02 ; scénarios transverses identifiables séparément. La [qualification](qualification.md) possède les profils et critères : aucun contrôle n'est installé ou réussi par la rédaction de ce chapitre.
