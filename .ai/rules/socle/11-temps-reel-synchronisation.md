# R11 — Temps réel et synchronisation

**Version 1.1 — applicabilité à valider ; fond v1.0 validé.** [Index des règles](../index.md#r11).

**Lire pour :** créer, auditer ou modifier une vue partagée, un événement diffusé, un canal, une mise à jour optimiste ou une récupération après interruption. **Procédure :** exigence → représentation → autorisation → publication → rapprochement → récupération → preuves.

Les prescriptions `R11-001` à `R11-030` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent fonctionnalités, destinataires, fraîcheur et comportement dégradé ; R11 explique leur mise en œuvre, aussi pour les corrections. Clarifier une exigence nécessaire introuvable ; les illustrations n'adoptent aucun parcours produit.

**Applicabilité :** retrouver transport et connecteur navigateur dans le profil du projet. Les passages Reverb/Echo et leurs API s'appliquent seulement à cette combinaison lorsqu'elle est retenue. Autorisation, rapprochement, fraîcheur, reprise et nettoyage restent à vérifier avec le transport effectivement utilisé ; une autre combinaison ne dispense pas de ces garanties.

**Frontières :** [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md) possèdent placement et métier, [R04](04-contrats-dto-validation.md) contrats/validation, [R06](06-requetes-lectures-cache.md) lectures/cache, [R07](07-transactions-concurrence-etats.md) transactions/idempotence, [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès/données et [R10](10-jobs-evenements-fiabilite.md) publication/reprise des travaux. Consulter selon impact [R12](12-vue-typescript-inertia.md), [R13](13-interface-accessibilite-internationalisation.md), [R16](16-voix-avatar-medias.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R20](20-environnement-dependances-qualite.md), [R21](21-livraison-exploitation-reprise.md), [R22](../index.md#r22) pour frontend, restitution, médias, tests, mesures, environnement, exploitation et erreurs. Respecter leur statut ; un renvoi futur ne prouve aucun mécanisme installé.

## 1. Qualifier l'état et placer les responsabilités

**R11-001 — Définir la convergence attendue.** Identifier propriétaire, lecteurs, projection utile, source faisant autorité, fraîcheur admissible et comportement pendant une interruption. Distinguer état métier durable, état partagé transitoire et état local ; synchroniser n'impose pas de tout enregistrer. Définir ce qui établit qu'une vue a retrouvé un état cohérent, sur quel périmètre et à quel moment de lecture. Une connexion ouverte ou l'heure du dernier message reçu ne prouve pas à elle seule la fraîcheur des données.

**R11-002 — Séparer action, résultat et notification.** Faire passer la mutation par son entrée serveur validée/autorisée et son cas d'usage R03/R07. Distinguer opération acquise, travail publié, réception par un navigateur et action physique ; un message manqué n'annule pas l'état enregistré. Une présence sur un canal, un identifiant de socket ou un message envoyé entre navigateurs ne confère aucun droit métier. Les interactions transitoires éventuellement transportées directement gardent leur contrat, leurs limites et leurs protections ; ne pas en déduire une confirmation serveur.

**R11-003 — Garder un propriétaire retrouvable.** Placer événements, données de diffusion et autorisation de canal auprès de leur usage ou du plus petit propriétaire commun R02. L'action coordonne lecture/décision, le repository porte SQL, l'événement transporte la représentation préparée. Mutualiser seulement les mécanismes réellement communs selon R02 ; la coordination frontend suit R11-021.

## 2. Choisir la branche hybride et définir les contrats

**R11-004 — Choisir par coût et cohérence.** Suivre l'approche hybride : diffuser directement une représentation ciblée complète/versionnée si elle est compacte, autorisée pour ses destinataires et suffisante pour actualiser sa projection ; sinon signaler le changement puis relire la projection utile. Examiner taille, fréquence, destinataires, calcul/SQL, travail navigateur et délai jusqu'à l'affichage cohérent. Conserver la justification avec l'usage R11-030 ; aucun seuil universel ni relecture systématique de toute la page. Chaque branche conserve la récupération de l'état courant autorisé.

**R11-005 — Définir ce que remplace la représentation.** Une projection complète remplace exactement son périmètre déclaré, pas nécessairement toute l'entité. Identifier dépendances, suppressions, appartenance aux listes, tri, filtres et pagination. Une modification de ligne ne prouve pas que les membres ou totaux d'une liste restent corrects ; compléter par représentation pertinente ou invalidation ciblée. **Variante :** delta dépendant d'un état préalable si gain démontré et contrat de base, détection des manques et récupération testés. Ne pas accumuler implicitement des deltas fragiles pour économiser quelques champs.

**R11-006 — Distinguer les identités du protocole.** Définir avec R04 type de message, ressource/projection, périmètre, données nécessaires et compatibilité. Distinguer version de format, révision des données, génération de contexte et identité de l'opération/livraison lorsqu'elles sont utiles ; elles ne sont pas interchangeables. Une génération sépare des états devenus incomparables, elle ne donne pas de droits. Valider les données reçues à l'exécution avant rapprochement ; types TS seuls insuffisants. Définir champs/sens communs avec la lecture de récupération et la réponse d'action, sans imposer une enveloppe universelle ni recopier les DTO indépendamment de R04.

**R11-007 — Faire correspondre données et révision.** Produire un état et sa révision dans une lecture/unité cohérente R06/R07, couvrant les producteurs et dépendances pertinents. Comparer seulement des révisions du même objet/projection et contexte ; ne pas déduire l'ordre de l'heure d'arrivée ni d'un timestamp seul. Éviter un compteur global sérialisant des usages indépendants. Définir le traitement d'une suppression/réapparition et d'une génération renouvelée ; une révision ne doit pas désigner silencieusement deux contenus différents. Ne pas associer état relu récemment et ancienne révision capturée, ou données d'une réplique en retard et révision du primaire.

## 3. Raccorder publication et abonnement

**R11-008 — Enregistrer le canal et son autorisation.** Déclarer provider, classe/callback `Broadcast::channel` et route HTTP d'autorisation avec leurs middlewares/guards effectifs. Garder l'assemblage central mince ; ranger la classe de canal chez son propriétaire et la raccorder dans son provider R02. Vérifier route, `channel:list` et parcours réel, aussi après mise en cache. Le nom du canal est un routage, pas une preuve d'accès ; distinguer public, privé et présence selon le contenu autorisé.

**R11-009 — Préparer explicitement la diffusion.** Définir canal via `broadcastOn()`, nom stable via `broadcastAs()` si nécessaire et payload sélectionné via `broadcastWith()`. L'événement ne charge pas de relations ni ne décide des droits ; préparer ces informations dans le parcours autorisé. Ne pas diffuser automatiquement les propriétés d'un modèle. Avec Echo, faire correspondre canal et nom d'écoute ; un nom personnalisé s'écoute avec le préfixe `.`. Le raccordement montré en A utilise les [mécanismes natifs](https://laravel.com/framework/docs/13.x/broadcasting).

**R11-010 — Maîtriser le chemin jusqu'au transport.** `ShouldBroadcast` utilise ordinairement un travail de diffusion en file ; déclarer connexion/file et publication après commit selon R10. **Variante :** `ShouldBroadcastNow` si diffusion synchrone justifiée, notamment depuis un travail déjà différé, sans ajouter une seconde file non maîtrisée. Cette interface ne remplace ni commit ni revalidation du contexte de publication. Quand la reprise est requise, appliquer R10-008 à 011 ; sinon qualifier la perte admissible et sa récupération. La convergence d'une vue ne nécessite pas à elle seule un historique de rejeu ni un acquittement durable de chaque appareil. Une erreur après commit ne devient pas un rollback métier.

**R11-011 — Raccorder aussi le navigateur émetteur.** Définir comment il acquiert le résultat confirmé : réponse contenant une représentation autorisée ou notification attendue, puis récupération si nécessaire. Les deux chemins utilisent le même rapprochement pour empêcher double application/régression. Si `toOthers()` exclut sa connexion, prévoir cette confirmation sans dépendre du message exclu ; ce mécanisme n'est ni idempotence ni exclusion de tous les appareils d'un utilisateur. Vérifier séparément écoute du transport, destination de publication Laravel et endpoint/configuration du connecteur navigateur ; **avec Reverb/Echo**, examiner leurs raccordements effectifs. Les secrets restent serveur ; R20/R21 possèdent installation, TLS/proxy et supervision détaillés.

## 4. Autoriser les connexions et leurs transitions

**R11-012 — Autoriser chaque sortie.** Appliquer R08 à abonnement, lecture de récupération et publication, R09 aux copies transportées. Résoudre acteur/preuve, périmètre et génération avant accès protégé ; déléguer lecture minimale au repository et décision à Gate/policy sans SQL caché. Ne pas laisser un binding de modèle lire hors contexte RLS ; l'identifiant reçu reste à vérifier. Un canal partagé transmet seulement des champs admis pour tous ses abonnés ; séparer les audiences incompatibles plutôt que compter sur un filtrage Vue.

**R11-013 — Qualifier l'accès sans compte.** Si le parcours comporte un invité, raccorder sa preuve R08 au mécanisme d'authentification du canal. Le pilote Laravel de canaux privés refuse normalement le non-authentifié avant callback : rendre son paramètre nullable ne suffit pas. Qualifier guard/adaptateur ou entrée d'autorisation équivalente, avec protections HTTP, signature native et refus attendus ; aucun compte fictif ni canal public pour contourner l'accès. Tester le vrai chemin d'Echo, pas seulement l'appel de la policy. [Authentification native](https://laravel.com/framework/docs/13.x/broadcasting#authorization-callback-authentication).

**R11-014 — Protéger les abonnements déjà ouverts.** Pour révocation ou changement de droits, choisir et qualifier une protection effective côté serveur : retrait des connexions/abonnements concernés ou routage renouvelé qui cesse réellement d'alimenter les anciens destinataires, selon les capacités du transport. Définir point de contrôle, coordination avec R07, délai exigé et traitement des publications déjà engagées/en attente. Revalider à la frontière de publication nécessaire, y compris une seconde file Laravel éventuelle. `Echo.leave()` chez un navigateur honnête ne constitue pas cette protection ; un simple refus des futurs abonnements est insuffisant. Ne pas promettre d'effacer les données déjà vues ni d'annuler rétroactivement les trames déjà engagées. Si le mécanisme ne satisfait pas l'exigence, clarifier avant implémentation.

**R11-015 — Renouveler le contexte sans héritage implicite.** Lors d'un changement d'acteur, ressource, droits ou génération, obtenir le contexte autorisé depuis le serveur ; aucune notification ne le choisit seule. Invalider l'ancien état selon R09, quitter les anciens abonnements et neutraliser leurs requêtes/handlers/timers. Réévaluer le droit d'abonnement et de lecture ; aucune conservation des capacités par simple réutilisation de socket. Utiliser une génération locale de requête pour écarter les réponses retardées, distincte de la génération serveur et de l'autorisation. Transmettre l'invalidation nécessaire aux propriétaires médias R16, sans prétendre piloter ici microphone ou avatar.

## 5. Recevoir les changements et récupérer l'état

**R11-016 — Fermer la fenêtre de l'ouverture.** Chemin ordinaire : contexte vérifié → écoute/capture bornée installée avant d'attendre l'abonnement → abonnement autorisé établi → lecture cohérente → rapprochement → fonctionnement courant. Ne pas installer l'écoute après la confirmation ni lire puis s'abonner sans fermer l'intervalle des changements manqués. Distinguer socket connecté et abonnement établi ; raccorder les signaux réels de l'adaptateur, y compris pour un abonnement partagé déjà établi. **Variante :** protocole à curseur/rejeu qualifié fermant la même fenêtre. Un buffer débordé invalide cette récupération et suit sa reprise bornée/issue explicite, sans prétendre être à jour.

**R11-017 — Rapprocher selon le contrat.** Vérifier contexte/format, puis appliquer les comparaisons pertinentes. Ignorer doublon compatible et représentation plus ancienne ; traiter une même révision de contenu contradictoire comme incohérence à diagnostiquer/récupérer. Une projection complète peut remplacer une plus ancienne sans recevoir toutes les versions intermédiaires. Un delta sans sa base ou une dépendance manquante impose la récupération prévue. Distinguer fraîcheur d'une ressource et couverture de sa liste ; ne pas rejeter globalement des objets indépendants parce qu'une autre révision est supérieure.

**R11-018 — Installer un état cohérent sans perdre la réception.** Vérifier périmètre, génération, variantes et révision de la réponse ; installer uniquement les parties effectivement couvertes, puis rapprocher les messages conservés. Pour un snapshot complet de collection, traiter aussi les éléments absents selon son contrat ; une page paginée n'autorise pas à supprimer ce qu'elle ne couvre pas. Coordonner lecture de récupération et notifications plus récentes : aucune réponse ancienne ne remplace un état déjà supérieur. Sous changements continus, borner récupération/buffer et rendre l'issue explicite plutôt que boucler jusqu'à une immobilité improbable.

**R11-019 — Vérifier la fraîcheur au-delà de la reconnexion.** Définir les déclencheurs pertinents : ouverture, réabonnement, reprise après veille/retour au premier plan, interruption et contrôle périodique adapté lorsque nécessaire. Couvrir le dernier message perdu, sans attendre un suivant révélant un manque. Préférer une vérification légère ou une récupération regroupée selon le contrat et son coût R06 ; aucun intervalle global arbitraire. Un heartbeat ou socket rétabli ne prouve pas la fraîcheur de la projection. Si transport indisponible, employer le repli explicitement prévu, borné et autorisé, ou signaler la vue périmée ; aucune fausse liste vide/réussite.

**R11-020 — Écarter les réponses devenues étrangères.** Associer chaque lecture à son contexte, filtres/pagination et génération locale ; à sa fin, vérifier qu'ils correspondent encore au consommateur. Annuler si possible, puis ignorer une réponse ancienne même si l'annulation a échoué. Dans le même contexte, confronter ses révisions à celles déjà connues et aux invalidations reçues pendant la lecture ; demander la suite utile si elle ne les couvre pas. Une reconnexion ne rejoue pas les anciennes mutations : leur résultat relève de R11-024.

## 6. Organiser l'état et les confirmations frontend

**R11-021 — Posséder la synchronisation par responsabilité.** Prévoir un coordinateur identifiable du périmètre et des projections : contrats R04, échanges dans `requests/`, rapprochement pur, coordination réactive dans un composable ciblé. Réutiliser une connexion technique et les abonnements réellement partagés, sans recréer le protocole dans chaque composant. Ne pas déplacer une page géante dans un composable géant ; un propriétaire commun justifie le partage R02/R12, pas une classe ou un magasin universel.

**R11-022 — Nettoyer selon les consommateurs réels.** À démontage/navigation/changement de contexte, retirer handlers, temporisations, lectures et buffers devenus inutiles. Pour un handler dédié, conserver son callback pour `stopListening(event, callback)` si le connecteur le permet ; préserver les écoutes encore partagées. Fermer l'abonnement partagé via `leaveChannel`/`leave` seulement sans consommateur restant. Les layouts persistants suivent aussi les changements de contexte. Nettoyage local distinct de R11-014 ; état transitoire borné, sans persistance navigateur implicite hors R09.

**R11-023 — Séparer provisoire et confirmé.** Représenter attente, confirmation, refus, résultat incertain et fraîcheur de la vue séparément. Une animation immédiate n'annonce pas un effet acquis. **Variante :** mise à jour optimiste si son contrat prévoit réversibilité, refus et conflits ; protéger sa correction par identité/révision. Ne pas restaurer toute une ancienne vue après refus d'une action locale en écrasant les changements légitimes des autres. Le serveur reste arbitre des permissions et transitions, même si l'écran ancien propose encore l'action.

**R11-024 — Retrouver une action dont la réponse manque.** Conserver l'identité stable et les données comparables requises par R07, puis rechercher son résultat par le parcours autorisé. Une réponse ou notification perdue n'établit ni échec ni réussite ; ne pas renvoyer aveuglément la mutation avec une nouvelle identité. Répéter seulement selon le protocole d'idempotence admis, avec la même intention. Relier le résultat confirmé à la mise à jour de la projection sans attendre indéfiniment un message. Restitution sûre/UI/IA et langue restent R13/R22.

## 7. Borner, faire évoluer et éprouver

**R11-025 — Regrouper sans masquer les changements.** Coordonner invalidations par projection/contexte/variantes ; éviter une lecture par événement ou composant. Conserver la révision/couverture à atteindre ou un indicateur équivalent. Une invalidation pendant la lecture peut nécessiter une seule suite regroupée ; ne pas l'effacer au seul retour HTTP. Borner la temporisation avec la fraîcheur attendue : une rafale continue ne doit pas repousser indéfiniment l'affichage. Ne pas supprimer indistinctement un fait/effet métier en regroupant les signaux d'une vue.

**R11-026 — Borner la reprise collective.** Définir tailles des messages/buffers, débit d'abonnement/récupération, concurrence et budget de reprise utiles. Espacer les tentatives, avec dispersion adaptée aux reconnexions simultanées ; prévoir transport lent, file en retard et lectures coûteuses. Limiter les consommateurs à leurs canaux/projections utiles ; ne pas diffuser toutes les données pour filtrer ensuite sur le téléphone. Les métriques R19 suivent retard et coût sans autoriser de nouvelles données de diagnostic R09. Dimensions et valeurs viennent de la conception/qualification, pas d'un nombre d'appareils supposé faible.

**R11-027 — Préserver la compatibilité et les générations.** Prévoir anciens onglets/messages et nouveaux producteurs selon R04/R10/R21. Un format non compris suit une récupération compatible ou un renouvellement contrôlé de l'application, avec libération R16 et restitution prévue ; aucune boucle de reconnexion/rechargement infinie. Après restauration, réaffectation ou révision redevenue incomparable, renouveler le contexte/protocole avant de comparer les états ; une ancienne révision élevée ne doit pas empêcher toute reprise. Retirer anciennes copies/producteurs selon R09/R21. Le redémarrage du transport ne constitue pas une restauration des vues.

**R11-028 — Tester le rapprochement et les courses.** Exercer remplacement complet, doublon, désordre, conflit de même révision, delta sans base, suppression et appartenance aux listes. Piloter les changements entre abonnement/lecture, pendant récupération et avant réponse HTTP ; vérifier l'état final et les parties couvertes, pas seulement la réception d'un message. Couvrir perte du dernier message, réponse de mutation perdue, action concurrente et corrections optimistes lorsque concernées. Centraliser les régressions du mécanisme commun, puis compléter les contrats/raccordements utiles ; aucun catalogue entier à rejouer pour chaque changement cosmétique.

**R11-029 — Traverser le raccordement réel.** Compléter les tests isolés par le parcours réel Laravel → publication/file retenue → transport → connecteur navigateur → affichage ; **si Reverb/Echo retenus**, traverser ces deux composants. Tester rôle SQL restreint/contexte R08, autre périmètre, invité lorsque prévu, révocation avec connexion ouverte et publication retardée ; observer absence de données interdites, pas seulement un refus HTTP. Exercer navigation, veille réelle, reconnexion/redémarrage et anciens contrats selon le risque. Ni `Event::fake()` ni un transport simulé ne prouve la chaîne ou le comportement mobile. R18/R21 possèdent organisation et exercices d'exploitation.

**R11-030 — Mesurer et transmettre les garanties.** Sur un profil représentatif, observer délai jusqu'à l'état cohérent, publications/destinataires/octets, requêtes/lignes SQL, travail navigateur et récupération collective. Comparer le coût des branches, pas seulement le nombre d'appels HTTP. Conserver contrat, choix hybride, limites, point de contrôle d'accès et preuves près de l'usage, en réutilisant tests/documentation existants. Séparer contrôles exécutés, mesures absentes et qualifications restantes ; aucune performance ou sécurité globale déduite d'un exemple fonctionnel. Une correction suit la même procédure sans modifier silencieusement le comportement produit.

**Illustration A — Une projection d'état mise à jour directement.** Hypothèses pédagogiques : un équipier autorisé accepte un article ; une petite projection expose seulement `pending/accepted`. Les lecteurs de cet extrait sont authentifiés ; le parcours invité suit R11-013. La notification peut manquer car une vérification de fraîcheur/récupération est requise et prévue. L'état métier, lui, reste acquis ; ces hypothèses ne fixent pas le produit.

```text
app/Modules/Dining/
├── Providers/DiningServiceProvider.php
└── Orders/
    ├── Broadcasting/OrderItemsChannel.php
    ├── Enums/OrderItemState.php
    └── UseCases/
        ├── AuthorizeOrderItemsChannel/…      # Action, repository ; preuve R08
        ├── AcceptOrderItem/…                 # Action, repository ; décision R03/R07
        └── PublishOrderItemState/
            ├── PublishOrderItemStateAction.php
            ├── PublishOrderItemStateRepository.php
            ├── Jobs/PublishOrderItemStateJob.php
            ├── Data/OrderItemStateData.php
            ├── Data/OrderItemStateMessageData.php
            └── Events/OrderItemStateChanged.php
resources/js/modules/dining/orders/watch-order-items/
├── contracts/order-item-state.generated.ts   # Type de transport généré selon R04
├── types/order-item-sync.ts                  # Contexte local, résultat du rapprochement
├── requests/readOrderItemStates.ts
├── reconcileOrderItemState.ts
└── composables/useOrderItemsSync.ts
```

1. L'entrée valide l'input selon R04 ; `AcceptOrderItemAction` autorise, puis coordonne la décision métier R03 et les écritures de son repository, dont la révision, dans l'unité R07 avec contexte R08. Elle renvoie le résultat acquis et programme `PublishOrderItemStateJob` après commit selon R10-007 ; le job porte seulement les références nécessaires. Rollback ne publie pas ; une panne avant publication laisse l'état récupérable. Si la notification exige une reprise durable, employer la voie R10-008 à 011 au lieu d'ajouter un second dispatch.
2. Le job délègue à `PublishOrderItemStateAction`. Celle-ci résout son autorité bornée, revalide le contexte/destinataires, charge via son repository état et révision cohérents, puis termine la transaction SQL avant le réseau. Elle prépare canal et DTO selon R11-014 : une référence de périmètre reçue ne constitue pas ce contrôle. Ici, on relit une projection courante ; on ne reconstruit pas un événement historique.
3. Depuis ce travail déjà différé, l'action appelle `Event::dispatch(new OrderItemStateChanged(...))`. L'événement utilise `ShouldBroadcastNow` pour ne pas ajouter une seconde attente non contrôlée ; le contrôle et ses courses restent R11-014/C. Une exception de transport suit R10/R22, sans prétendre annuler l'acceptation ni prouver la réception par tous les appareils.

Dans `DiningServiceProvider::boot()`, `Broadcast` désigne la façade Laravel :

```php
Broadcast::channel(
    'dining.meals.{scopeId}.{scopeGeneration}.items',
    OrderItemsChannel::class,
);
```

Le provider est déclaré selon R02 et importe la classe montrée dans l'arbre. `OrderItemsChannel::join()` délègue au cas d'usage `AuthorizeOrderItemsChannel` : acteur/preuve → contexte vérifié → lecture minimale autorisée → Gate/policy → booléen. La route `/broadcasting/auth`, ses middlewares/guard et la lecture de récupération sont raccordés selon R11-008/012 ; leur code est omis, jamais remplacé par une comparaison d'identifiant seule. Les identifiants illustratifs de repas sont uniques dans ce contrat et restent contrôlés dans leur périmètre réel.

Enum, DTO et événement dans les fichiers indiqués ; namespaces/imports applicatifs omis, fichiers en `strict_types=1`. La révision entière sûre pour JavaScript est maîtrisée avant construction. Le canal est préparé côté serveur, jamais accepté librement depuis le client. L'enum suivant fixe seulement les valeurs de la projection illustrative :

```php
enum OrderItemState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
}
```

```php
final readonly class OrderItemStateData
{
    public function __construct(
        public string $itemId,
        public OrderItemState $state,
        public int $revision,
    ) {}
}
```

`Data/OrderItemStateMessageData.php` porte l'enveloppe serveur exportable selon R04 :

```php
final readonly class OrderItemStateMessageData
{
    public function __construct(
        public string $scopeId,
        public string $scopeGeneration,
        public OrderItemStateData $data,
        public int $schemaVersion = 1,
    ) {}
}
```

```php
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class OrderItemStateChanged implements ShouldBroadcastNow
{
    public function __construct(
        private readonly string $channelName,
        private readonly OrderItemStateMessageData $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'dining.order-item-state.v1';
    }

    public function broadcastWith(): array
    {
        return [
            'schemaVersion' => $this->message->schemaVersion,
            'scopeId' => $this->message->scopeId,
            'scopeGeneration' => $this->message->scopeGeneration,
            'data' => [
                'itemId' => $this->message->data->itemId,
                'state' => $this->message->data->state->value,
                'revision' => $this->message->data->revision,
            ],
        ];
    }
}
```

La génération serveur sépare ici les audiences/contextes selon R11-006. La projection couvre uniquement l'état d'un article ; sa récupération conserve mêmes champs, périmètre/couverture et révision cohérente. Les effets sur une liste distincte suivent B.

4. `useOrderItemsSync` obtient le contexte/canal autorisés et installe `.dining.order-item-state.v1` via `Echo.private(channelName).listen(...)` avec capture bornée **avant d'attendre** la confirmation d'abonnement. Il lance ensuite la récupération et rapproche les messages conservés selon R11-016/018. Après validation runtime R04, il choisit la projection concernée et appelle le rapprochement ci-dessous ; une ressource inconnue exige le traitement d'appartenance/récupération, pas son ajout arbitraire. La réponse d'action suit le même rapprochement ; le nettoyage applique R11-022.

Dans `contracts/order-item-state.generated.ts`, formes TS attendues de la sérialisation des DTO/enum, à générer selon R04 après qualification ; aucune copie manuelle concurrente. Producteur et décodeur runtime v1 imposent `schemaVersion = 1`, que le type numérique seul ne vérifie pas :

```ts
export type OrderItemStateData = {
    itemId: string;
    state: 'pending' | 'accepted';
    revision: number;
};

export type OrderItemStateMessageData = {
    schemaVersion: number;
    scopeId: string;
    scopeGeneration: string;
    data: OrderItemStateData;
};
```

Dans `types/order-item-sync.ts`, `OrderItemsContext` porte `scopeId` et `scopeGeneration`, tous deux `string`. `OrderItemReconciliation` est l'union `{ kind: 'ignore' } | { kind: 'replace'; data: OrderItemStateData } | { kind: 'resync' }`, avec import du type de transport. Ce résultat ordinaire n'est pas un nouveau catalogue d'erreurs. Dans `reconcileOrderItemState.ts` :

```ts
import type { OrderItemStateData, OrderItemStateMessageData } from './contracts/order-item-state.generated';
import type { OrderItemReconciliation, OrderItemsContext } from './types/order-item-sync';

export function reconcileOrderItemState(
    current: OrderItemStateData,
    message: OrderItemStateMessageData,
    context: OrderItemsContext,
): OrderItemReconciliation {
    if (message.scopeId !== context.scopeId
        || message.scopeGeneration !== context.scopeGeneration
        || message.data.itemId !== current.itemId
        || message.data.revision < current.revision) {
        return { kind: 'ignore' };
    }

    if (message.data.revision === current.revision) {
        return message.data.state === current.state
            ? { kind: 'ignore' }
            : { kind: 'resync' };
    }

    return { kind: 'replace', data: message.data };
}
```

**Omissions annoncées :** code SQL/action/job, validation runtime, signaux d'abonnement, récupération, budgets et contrôle de publication. Leurs procédures ci-dessus/B/C restent requises ; le seul rapprochement ne suffit pas à synchroniser une page. Le composable applique `replace`, regroupe une récupération sur `resync` et conserve l'état sur `ignore`. Révision 18 reçue après 17 remplace la projection ; 17 tardive ou 18 identique ne la régresse pas ; 18 contradictoire déclenche récupération/diagnostic.

**Illustration B — Liste invalidée, lecture puis reprise.** Hypothèse : une liste filtrée dépend de plusieurs articles ; son contrat possède une révision monotone couvrant toutes ses dépendances et une récupération cohérente. Un signal `dining.order-items-invalidated.v1` porte contexte et révision utile, pas toute la liste. Sa production suit A/R10 ; `readOrderItemStates.ts` porte l'appel ciblé autorisé et le coordinateur applique R11-018/020/025. Ni ce nom ni un intervalle sont imposés au produit.

| Situation | Rapprochement et preuve |
|---|---|
| Révisions 31 et 32 reçues avant lecture | Regrouper : une lecture de la projection concernée, cible 32 ; aucun appel par composant. |
| Révision 33 reçue pendant cette lecture ; réponse couvrant 32 | Installer sans régression les parties utiles, conserver cible 33 et programmer une suite regroupée bornée. Ne pas effacer l'invalidation 33 à la fin de la première lecture. |
| Réponse déjà cohérente couvrant 33 | Satisfaire la cible ; pas de seconde lecture redondante. |
| Réponse 32 arrivant après un état déjà connu à 33 | Ne pas régresser cette projection ; la couverture reste celle du contrat de liste. |
| Article supprimé ou sorti du filtre | Une réponse complète de ce filtre retire l'élément absent ; une seule page n'autorise pas une purge de toutes les autres. |
| Changement dès la confirmation d'abonnement ou pendant la lecture | L'écoute est déjà installée ; conserver les signaux, comparer leurs révisions au snapshot puis relire si sa couverture est insuffisante. |
| Veille/coupure ou dernier signal manqué | Réabonnement si nécessaire et vérification de fraîcheur autorisée ; le snapshot courant rétablit l'état sans rejouer les achats. |
| Flux continu ou récupération en échec | Appliquer borne de temporisation/buffer, reprise espacée et fraîcheur/issue de référence ; ne pas annoncer une synchronisation complète artificielle. |

Les réponses incluent les variantes/couvertures effectivement lues. Un signal invalidant la liste ne signifie pas qu'une révision d'article est devenue une révision de liste ; ce contrat distinct doit être produit correctement, ou un protocole équivalent qualifié.

**Illustration C — Ancien contexte encore actif.** Hypothèse : une protection de publication par génération est choisie et qualifiée pour l'usage. Le serveur renouvelle le routage, autorise uniquement sa génération courante et cesse d'alimenter l'ancienne ; ce mécanisme n'est pas une fonction Reverb installée ni une solution imposée à tous les canaux.

| Étape | Traitement et limite |
|---|---|
| Accès retiré/rattachement changé | Coordonner la transition R07/R08 ; renouveler le contexte serveur et refuser ancien abonnement/lecture/action. Ne pas transférer les anciens droits. |
| Ancien navigateur ignore la demande de quitter | La protection serveur R11-014 empêche les publications futures interdites ; le nettoyage local n'est pas la preuve de cette protection. |
| Job préparé avant la transition, publication encore en attente | Revalider au point de contrôle ; abandonner l'ancienne destination/contenu ou préparer le nouveau parcours autorisé. Aucun envoi vers une génération fermée sur le seul ancien routage sérialisé. |
| Transition concurrente au réseau déjà engagé | Définir/qualifier le point de contrôle et le traitement des trames en cours ; une vérification SQL puis un envoi non coordonné ne démontre pas une coupure instantanée. Si le délai requis n'est pas satisfait, ce protocole ne convient pas. |
| Ancienne réponse HTTP ou ancien handler se termine | Le navigateur a invalidé sa génération locale ; ignorer la réponse, nettoyer son buffer et ne pas restaurer l'ancienne vue. |
| Nouveau contexte obtenu | S'abonner sous les nouvelles capacités, puis appliquer la récupération R11-016 à 020 ; aucune comparaison numérique entre générations étrangères. |

Tester réellement ces entrelacements, une ancienne publication différée et un navigateur non coopératif. Un arrêt de réception constaté sur le seul navigateur honnête ne prouve pas la révocation. Les données déjà vues restent hors de cette garantie. Après restauration, le même principe de séparation des états incomparables relève de R11-027/R21 ; il ne choisit pas ici un schéma de génération.

**Références :** [broadcasting Laravel](https://laravel.com/framework/docs/13.x/broadcasting), [configuration et fonctionnement Reverb](https://laravel.com/framework/docs/13.x/reverb), [écouteurs du connecteur Echo/Pusher](https://raw.githubusercontent.com/laravel/echo/2.x/packages/laravel-echo/src/channel/pusher-channel.ts). Vérifier les versions effectives et signaux disponibles avant implémentation. Reverb ferme les connexions à son redémarrage ; reprendre transport et état sont deux contrôles distincts. Les illustrations sont documentaires : aucun adaptateur invité, protocole de révocation, installation, benchmark ni test applicatif n'est déclaré exécuté.
