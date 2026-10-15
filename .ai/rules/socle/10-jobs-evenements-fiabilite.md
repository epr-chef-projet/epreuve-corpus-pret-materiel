# R10 — Jobs, événements et fiabilité

**Version 1.1 — applicabilité à valider ; fond v1.0 validé.** [Index des règles](../index.md#r10).

**Lire pour :** créer, auditer ou modifier un job, événement, listener, relais, tâche planifiée, file, configuration Horizon ou reprise de traitement. **Procédure :** exigence → responsabilités → message → publication → exécution → reprise → preuves.

Les prescriptions `R10-001` à `R10-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Elles s'appliquent aussi aux corrections. Les [références de conception](../index.md#references-conception) fixent effets, criticité, priorités, délais et conservation ; R10 explique leur mise en œuvre.

**Applicabilité :** retrouver les services de files et leur gestionnaire dans le profil du projet. Les passages Redis/Horizon expliquent leur emploi lorsqu'ils sont retenus ; ils ne les adoptent pas. Les garanties de publication, d'effet, de reprise et de nettoyage s'appliquent aux mécanismes effectivement utilisés.

**Frontières :** [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md) possèdent placement et métier, [R04](04-contrats-dto-validation.md) les contrats, [R05](05-schema-migrations-historique.md) le schéma, [R06](06-requetes-lectures-cache.md) les lectures/lots, [R07](07-transactions-concurrence-etats.md) les transactions/idempotence et [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) les accès/données. Consulter [R11](11-temps-reel-synchronisation.md), [R17](17-integrations-effets-externes.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R21](21-livraison-exploitation-reprise.md), [R22](../index.md#r22) selon synchronisation, effets externes, tests, mesures, exploitation et erreurs. Respecter leur statut ; un renvoi futur ne prouve aucun mécanisme installé.

## 1. Qualifier le travail et placer les responsabilités

**R10-001 — Définir l'effet et sa preuve.** Retrouver résultat attendu, préconditions, délai utile, répétitions possibles et garantie de reprise. Nommer ce qui établit l'accomplissement : ligne enregistrée, fichier finalisé ou résultat distant confirmé, selon le contrat. Distinguer demande acceptée, publication, exécution et action physique ; un job terminé ne prouve pas une intervention humaine. Clarifier une exigence absente selon R01. Conserver les écritures indivisibles dans l'unité R07 ; ne pas différer une décision nécessaire à la réponse ni découper une transaction pour utiliser des queues.

**R10-002 — Choisir le chemin et son propriétaire.** Une action demande/exécute un cas d'usage ; un job adapte son exécution différée ; un événement décrit un fait ; un listener y réagit, immédiatement ou en file. Exprimer une demande explicite par l'appel du cas d'usage ou un job dédié selon le délai requis ; utiliser un événement pour un fait intéressant des consommateurs identifiés. Jobs/listeners délèguent au cas d'usage, sans dupliquer services ou SQL. Ranger près de l'usage R02 ; exposer les seuls contrats intermodules nécessaires. Garder le mécanisme transversal d'outbox dans l'infrastructure et ses adaptateurs connaissant le métier chez leur propriétaire. Ne pas imposer événement → listener → job ni workflow universel.

**R10-003 — Raccorder les réactions.** Enregistrer ordinairement les listeners avec `Event::listen` dans `boot()` du provider propriétaire, lui-même déclaré selon R02. **Variante :** découverte Laravel si ses chemins modulaires sont configurés et testés ; ne pas cumuler deux enregistrements de la même réaction. Vérifier `event:list` avec et sans cache d'événements, puis traverser le parcours réel. Déclarer quelles réactions sont synchrones/différées et leur connexion/file ; un nom d'événement ne les détermine pas. Examiner les boucles de réactions ; si l'une repose sur un observer, couvrir les chemins Eloquent pouvant le contourner.

## 2. Définir le message et son autorité

**R10-004 — Identifier chaque effet attendu.** Distinguer opération d'origine, travail d'un consommateur et livraison/tentative technique. Conserver la même identité de travail lors des republications ; une nouvelle intention reçoit sa propre identité. Définir portée et contenu comparable avec R07 : même clé et contenu différent est un conflit. Une clé d'opération seule ne doit pas empêcher deux effets distincts. Identifier durablement les consommateurs attendus par contrat/version ; ajouter un listener ne doit pas changer implicitement la définition de « terminé » des anciens travaux. L'identifiant de corrélation sert au diagnostic, pas à l'unicité métier.

**R10-005 — Transporter les données nécessaires.** Définir et valider type, identités, périmètre et charge utile selon R04 ; expliciter la compatibilité et versionner lorsque nécessaire. Distinguer état courant à relire et fait acquis à préserver : ne pas reconstituer un fait passé depuis une valeur courante susceptible d'avoir changé. Un identifiant vers un fait historique durable peut éviter sa copie ; garantir sa disponibilité pendant les reprises autorisées. Préférer identifiants et faits ciblés aux modèles Eloquent sérialisés. Utiliser une correspondance maîtrisée entre types de messages et handlers ; aucune classe arbitraire choisie par une entrée externe. Appliquer R09 à toutes les copies, y compris échecs, métadonnées et diagnostics Horizon ; un chiffrement ne décide pas de leur admissibilité.

**R10-006 — Établir l'autorité avant les accès.** Pour job, listener, relais et rapprochement, résoudre autorité et périmètre avant toute lecture protégée, puis utiliser le contexte transactionnel RLS R08 sur la connexion effective. L'identifiant de périmètre reçu ne constitue pas une autorisation. Distinguer nouvelle décision soumise aux droits actuels et achèvement d'un engagement acquis sous capacité système bornée. Examiner les lectures de désérialisation avant `handle()`/middleware ; ne pas les laisser contourner ce contexte. Un relais multi-périmètres n'obtient pas implicitement accès à toutes les données métier. Prévoir ressource supprimée, contrat incompatible et contexte invalide sans repli global.

## 3. Publier et conserver la possibilité de reprise

**R10-007 — Raccorder la publication au commit extérieur.** Pour la voie de publication directe dépendant d'écritures SQL, employer le mécanisme natif adapté : `after_commit` sur la connexion de queue, `afterCommit()` au dispatch, `ShouldDispatchAfterCommit` pour l'événement ou `ShouldQueueAfterCommit` pour le listener différé. La voie durable relève de R10-008. Vérifier leur portée réelle ; différer un listener ne diffère pas les autres réactions synchrones à l'événement. Rollback ne doit pas laisser partir le travail correspondant. Ces mécanismes attendent le commit ; ils ne conservent pas seuls l'intention si le processus disparaît avant publication. Une erreur de publication après commit n'annule pas les écritures déjà acquises.

**R10-008 — Enregistrer le travail durable avec l'opération.** Quand le contrat exige une reprise après panne, enregistrer mutation et intention d'outbox dans la même transaction PostgreSQL et sur la même connexion, puis laisser le relais publier après commit ; suivre le résultat selon R10-010/011. Pour cet effet, ne pas ajouter de dispatch direct indépendant. L'action d'acceptation conserve l'identité du travail à retrouver ; la réponse distingue accepté et exécuté. **Variante :** publication simple après commit si sa perte est explicitement acceptable. L'outbox possède l'intention de publication ; résultat métier et preuve de l'effet restent chez leur propriétaire, sans copie de suivi systématique.

**R10-009 — Coordonner les relais sans transaction réseau longue.** Sous l'autorité prévue, acquérir atomiquement un lot borné d'intentions éligibles avec réservation temporaire et jeton de possession, puis terminer cette transaction. Publier ensuite ; enregistrer succès ou échec par une mise à jour conditionnée à la réservation encore détenue. Un jeton remplacé/expiré ne permet pas au retardataire d'écraser la nouvelle tentative. Prévoir reprise des réservations abandonnées, ordre de sélection et coût R06. L'expiration ne prouve pas l'arrêt du premier relais : une publication peut se répéter. Conserver l'identité du travail ; protéger son effet au consommateur. Tester l'acquisition concurrente et l'acquittement tardif.

**R10-010 — Retrouver les effets non terminés.** Relier intention et résultats attendus, par consommateur critique ; ni accusé Redis, ni statut « publié », ni réussite d'un autre consommateur ne clôt le travail manquant. Un rapprochement borné recherche les effets encore dus, examine état actif/résultat, puis programme la reprise nécessaire. Son déclenchement doit pouvoir repartir après perte des files, sans dépendre exclusivement d'un job contenu dans celles-ci ; raccorder son maintien en fonctionnement à R21. Ne pas confondre absence de résultat et certitude de non-exécution. Limites, temporisation et idempotence empêchent qu'un travail lent soit relancé indéfiniment.

**R10-011 — Conserver le budget et la preuve pendant les reprises.** Porter échéance, limites globales et prochaine action au niveau du travail durable ; une nouvelle livraison Redis ne remet pas son budget à zéro. Si une borne en nombre est requise, coordonner atomiquement sa consommation et conserver son avancement malgré rollback métier ou interruption ; ce suivi est distinct de la preuve d'effet R07. Coordonner les décideurs de reprise pour éviter que worker et rapprochement multiplient simultanément les renvois. Rendre les échecs persistants et les travaux expirés retrouvables pour l'intervention prévue. Appliquer R09 et R07-021 au nettoyage : ne pas supprimer preuve de déduplication, résultat ou données requises tant qu'une reprise couverte par le contrat peut encore les utiliser. Définir aussi l'issue d'une livraison tardive après cette période.

**Illustration A — Un ticket logiciel créé une seule fois.** Hypothèses pédagogiques : une demande autorisée peut être enregistrée avant la création de son ticket ; cette création doit survivre à une panne et reste révocable avant effet selon son contrat. Ces hypothèses ne fixent pas le parcours produit. Le résultat suivi est `ticket_created`, jamais « boisson préparée » ou « accepté par le bar ».

```text
app/
├── Infrastructure/Outbox/…                     # Relais, stockage et contrat de publication
└── Modules/Dining/
    ├── Providers/DiningServiceProvider.php
    └── Orders/UseCases/
        ├── RequestPreparationTicket/
        │   ├── RequestPreparationTicketAction.php
        │   └── RequestPreparationTicketRepository.php
        └── CreatePreparationTicket/
            ├── CreatePreparationTicketAction.php
            ├── CreatePreparationTicketRepository.php
            ├── CreatePreparationTicketService.php
            ├── Jobs/CreatePreparationTicketJob.php
            └── Publication/PreparationTicketPublisher.php
```

Déroulement ; les repositories portent les accès SQL, les actions leurs transactions et le service la décision d'éligibilité. Les contrats de l'infrastructure sont à qualifier ; les noms ci-dessus illustrent leur placement, pas une API déjà installée.

1. `RequestPreparationTicketAction` autorise la demande et coordonne sa répétition selon R07. Dans sa transaction avec contexte RLS, elle enregistre la demande, son fait historique utile et l'intention `dining.create_preparation_ticket.v1`. Un `workId` stable désigne cet effet ; aucun envoi Redis n'a lieu ici. Après commit, l'appelant reçoit cette référence pour retrouver l'avancement ; rollback ne laisse ni nouvelle demande ni intention correspondante.
2. Le relais applique R10-009. Le provider raccorde le type connu à `PreparationTicketPublisher`, adaptateur du module ; celui-ci valide le message puis publie `CreatePreparationTicketJob` avec `restaurantId` et `workId` sur la connexion/file déclarée. Il transmet seulement ces références, pas un modèle ou les données courantes d'un produit. Une publication confirmée n'est pas encore `ticket_created`.
3. Le job délègue à `CreatePreparationTicketAction`. Celle-ci résout l'autorité système bornée, ouvre sa transaction et pose le contexte RLS avant lecture. Le repository coordonne l'acquisition de la clé d'effet, protégée par unicité `(restaurant_id, work_id)`, selon R07-018/019. Pour une même intention dont le résultat est déjà acquis, l'action le retrouve sans recréer le ticket ni requalifier le passé. Sinon, le repository charge la demande sous la protection commune à la création et à l'annulation selon R07 ; le service vérifie si elle reste exécutable. Une issue d'annulation est enregistrée selon le contrat.
4. Si l'effet reste dû, le repository crée le ticket depuis les faits conservés et enregistre son identifiant comme preuve, dans cette même transaction. L'unicité et l'acquisition R07 coordonnent deux workers ; une simple recherche préalable ne suffit pas. Un arrêt après commit mais avant retrait du job conduit à retrouver ce résultat lors de la nouvelle livraison.

Le job reste un adaptateur ; extrait PHP avec namespace/import applicatif omis, fichier en `strict_types=1` :

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CreatePreparationTicketJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $restaurantId,
        public readonly string $workId,
    ) {}

    public function handle(CreatePreparationTicketAction $action): void
    {
        $action->execute($this->restaurantId, $this->workId);
    }
}
```

La configuration de file/délais et la traduction des issues R10-014 sont requises, mais omises de cet extrait. L'action porte le protocole décrit à l'étape 3 ; passer `restaurantId` ne suffit pas à l'autoriser. La transaction d'effet enregistre le résultat utilisé par le rapprochement : aucune réussite en mémoire seulement.

**Raccordement événementiel distinct.** Pour un recalcul secondaire dont la perte est explicitement acceptable, les fichiers illustratifs sont `Dining/PublicApi/Orders/OrderConfirmed.php` et `Dining/Orders/UseCases/RefreshOrderSummary/Listeners/RefreshOrderSummaryListener.php`, sous `app/Modules/`. Dans `DiningServiceProvider::boot()`, avec leurs imports :

```php
Event::listen(OrderConfirmed::class, RefreshOrderSummaryListener::class);
```

`Event` désigne `Illuminate\Support\Facades\Event`. Le listener implémente `Illuminate\Contracts\Queue\ShouldQueueAfterCommit` et expose `handle(OrderConfirmed $event): void`, délégant au cas d'usage propriétaire. Vérifier découverte/cache et parcours réel. Cette variante illustre un autre effet ; elle n'ajoute pas un second chemin de création du ticket. Si une réaction exige une garantie durable, la raccorder au protocole R10-008 à R10-011 plutôt que déduire cette garantie du listener différé.

## 4. Exécuter, répéter et terminer sans ambiguïté

**R10-012 — Revalider l'effet encore dû.** Sous contexte autorisé, charger explicitement faits conservés et état courant nécessaire, puis appliquer le cas d'usage partagé. Distinguer l'action d'acceptation du travail et celle de son exécution : rejouer la seconde ne doit pas recréer la première demande. Vérifier annulation, suppression, échéance et prérequis selon leur contrat. Pour des opérations dépendantes, utiliser versions/prérequis et prévoir attente bornée ou issue obsolète ; plusieurs workers peuvent finir dans un autre ordre que l'envoi. Ne pas réinventer ici l'ordre des vues R11.

**R10-013 — Protéger l'effet malgré les répétitions.** Pour un effet SQL, acquérir l'identité, muter et prouver le résultat dans l'unité R07, avec contrainte R05 ; ne pas utiliser « absent puis créer » sans coordination. Une reprise consulte la preuve du même effet et vérifie le contenu. `ShouldBeUnique` limite certains dispatchs via verrou de cache : qualifier le chemin de publication réellement utilisé. `WithoutOverlapping` limite les exécutions simultanées selon clé et expiration ; un abandon de livraison faute de verrou ne clôt pas à lui seul le travail durable. Aucun des deux ne remplace cette preuve ; les contraintes de jobs uniques ne s'appliquent pas aux jobs des batches. Pour un effet externe, appliquer le protocole R17, sans promettre une exécution exactement une fois faute de garantie du destinataire.

**R10-014 — Rendre chaque issue explicite.** Traduire l'issue du cas d'usage à la frontière job/listener selon cette table. Un retour normal marque généralement le job traité par la queue : ne pas intercepter une erreur, la journaliser puis retourner comme si l'effet avait réussi. Le signalement et les restitutions suivent R22 ; les copies techniques suivent R09.

| Issue constatée | Procédure |
|---|---|
| Effet accompli ou déjà prouvé | Retrouver/enregistrer le résultat autorisé, puis terminer sans répéter l'effet. |
| Erreur transitoire | Propager une erreur classifiée ou remettre explicitement en file avec délai et budget ; quitter immédiatement le chemin courant après `release()`. |
| Refus définitif | Enregistrer l'issue ; terminer normalement si le contrat la traite comme résultat, sinon signaler l'échec terminal avec `fail()` puis sortir. Aucune reprise aveugle. |
| Travail annulé, obsolète ou ressource disparue | Vérifier la politique applicable ; conserver l'issue ou signaler l'incohérence. Ne pas supprimer silencieusement un engagement. |
| Résultat externe incertain | Rechercher une preuve selon R17 avant un nouvel effet ; une réponse manquante ne prouve pas l'échec distant. |

**R10-015 — Coordonner les bornes de reprise.** Définir nombre/échéance, `backoff`, timeout et traitement terminal avec le budget R10-011. Sur Laravel, `retryUntil()` prend priorité sur `tries` lorsque les deux sont définis : ne pas présumer deux limites cumulées. Si les deux garanties sont nécessaires, contrôler explicitement le budget durable. L'échéance vient de l'engagement d'origine, sans être prolongée à chaque republication. `release()`, limitation de débit ou verrou d'exclusion peuvent consommer des tentatives sans exécuter le métier ; les intégrer au calcul. Espacer les reprises et borner leur concurrence pour qu'une panne persistante ne sature pas les autres traitements.

**R10-016 — Retrouver l'état après interruption.** Couvrir effet acquis avant acquittement, arrêt forcé, nettoyage en erreur et diagnostic incomplet. `failed()` reçoit une nouvelle instance du job : utiliser données sérialisées autorisées et preuve durable, pas les propriétés seulement modifiées dans `handle()`. Un arrêt brutal peut empêcher callbacks/nettoyage ; le rapprochement doit retrouver le travail sans eux. Une erreur de nettoyage ne doit ni effacer la preuve d'un effet acquis ni réautoriser sa répétition. Ne pas laisser une tentative tardive écraser un résultat terminal plus récent.

**Illustration B — Pannes du parcours A.** « Reprise » signifie retrouver le même travail sous le même contrat, pas réaccepter la commande.

| Interruption ou concurrence | Preuve et reprise attendues |
|---|---|
| Rollback avant commit extérieur | Aucune nouvelle intention acquise ; aucun travail correspondant envoyé. |
| Commit puis arrêt avant publication | Intention PostgreSQL retrouvée par le relais ; la demande reste acceptée. |
| Deux relais visent la même intention | Une réservation active acquise atomiquement ; si elle expire, l'ancien jeton ne peut pas confirmer la réservation suivante. |
| Redis accepte puis le relais s'arrête avant confirmation SQL | Nouvelle publication possible ; même `workId`, un seul effet protégé. |
| Job perdu après publication constatée | Rapprochement du résultat manquant, reprise selon état/budget ; le déclencheur de reprise n'a pas disparu avec la file. |
| Deux workers exécutent le même travail | Acquisition/contrainte SQL coordonnée ; le second retrouve la preuve après commit du premier. |
| Ticket et preuve acquis, worker arrêté avant acquittement | La nouvelle livraison retrouve `ticket_created` et son identifiant ; aucun second ticket. |
| Deux consommateurs requis, un seul a réussi | Son effet reste acquis ; seul le travail manquant demeure à reprendre. |
| Ancien relais/worker reprend après remplacement | Confirmation conditionnelle et protection d'effet ; ni écrasement du résultat récent ni nouvel effet illégitime. |

Une restauration d'une sauvegarde ancienne est un autre scénario : voir R10-024. L'outbox ne protège pas contre la disparition de sa propre preuve PostgreSQL.

## 5. Organiser les files et le cycle des workers

**R10-017 — Affecter la capacité aux usages.** Déclarer les connexions et leurs valeurs par défaut dans `config/queue.php`, affecter chaque travail à sa connexion/file, puis configurer les groupes consommateurs du gestionnaire retenu. **Avec Horizon :** les déclarer dans `config/horizon.php` ; son équilibrage `auto` ne garantit pas une priorité stricte. Vérifier que les files publiées sont consommées. Réserver la capacité requise aux traitements sensibles aux délais et séparer les longs travaux ; plusieurs files partageant les mêmes ressources peuvent encore se gêner. Mesurer coût, fréquence, âge des travaux et effet d'une panne massive avant dimensionnement ; ne pas choisir un nombre de workers sans budget mémoire/SQL/fournisseur. R19 possède la mesure et les alertes à partir des valeurs de conception ; R21 l'exploitation des services retenus.

**R10-018 — Aligner les délais effectifs.** Borner les appels réseau dans le job et leur coût cumulé. **Si Horizon est retenu :** prévoir un timeout de job inférieur à celui d'Horizon, lui-même inférieur à `retry_after`, avec marges adaptées ; vérifier les priorités des réglages dans la version installée et les arrêts pendant réduction des workers. Le délai de remise à disposition de la file, notamment Redis lorsqu'il est utilisé, ne doit pas expirer habituellement avant l'arrêt du premier traitement. Distinguer timeout d'une tentative et échéance du travail entier. Raccorder aussi délai d'arrêt du gestionnaire de processus et interruptions R21 ; une configuration ne dispense pas de tester le doublon résiduel.

**R10-019 — Nettoyer les processus réutilisés.** Initialiser puis libérer acteur, périmètre, langue, diagnostic, connexions et ressources par travail ; aucun singleton/statique ne conserve un périmètre d'opération par défaut. Nettoyer dans les sorties normales et anormales sans compter sur `finally` après arrêt forcé ; ne pas réutiliser un worker dont l'état sûr ne peut être rétabli. Prévoir renouvellement pour mémoire et changement de code. **Avec Horizon :** il organise les workers. Le maintien en fonctionnement des workers, du relais, du planificateur et de leur gestionnaire retenu appartient à R21. Ni un onglet Horizon ouvert ni un redémarrage ponctuel ne prouve ce maintien.

## 6. Préparer les travaux longs et les reprises

**R10-020 — Rendre les lots reprenables.** Appliquer R06 pour sélection, progression et mémoire bornées. Définir source/version, point de reprise et résultat de chaque lot ; coordonner effet et avancement pour ne ni sauter un lot ni le reproduire sans protection. Pour un fichier ou effet non transactionnel, prévoir l'étape de finalisation et la détection des restes selon R14/R17.

`Bus::chain` et les batches organisent des travaux ; ils ne fournissent ni transaction globale ni reprise durable complète par eux-mêmes. Conditionner les étapes suivantes au résultat métier préalable requis : la fin normale ou le saut d'un job peut faire poursuivre une chaîne sans prouver l'effet attendu. Une annulation de batch n'arrête pas automatiquement tout effet déjà commencé : vérifier l'annulation et les préconditions aux frontières utiles.

**R10-021 — Raccorder les tâches planifiées.** Définir déclenchement, fuseau, chevauchement et comportement après exécution manquée à partir du contrat. Utiliser `withoutOverlapping`/`onOneServer` lorsque requis, avec stockage de verrous partagé, clés/noms distincts et expiration cohérente ; leur portée ne remplace pas l'idempotence. Une tâche planifiée qui dispatch un job peut finir bien avant ce job : son verrou de planification ne protège pas toute l'exécution différée. Prévoir arrêt du planificateur, changement d'heure et reprise des échéances dues ; ne pas rejouer automatiquement toutes les occurrences manquées sans politique.

**R10-022 — Préserver les contrats pendant un déploiement.** Inventorier messages Redis/outbox/échecs, travaux actifs et checkpoints concernés par une évolution. Avec R04/R21, maintenir le lecteur compatible avant d'émettre le nouveau format ; traiter anciens messages, classes de jobs renommées et contrats retirés avant leur suppression. Prévoir conversion, achèvement avec l'ancien lecteur ou issue contrôlée selon la conception. Renouveler les workers pour charger le code voulu ; un redéploiement du web ne met pas à jour leur mémoire. Ne pas reprendre un checkpoint sous une sémantique devenue différente.

**R10-023 — Encadrer la reprise manuelle.** Vérifier habilitation, cause de l'échec, état métier, preuve d'effet et validité du contrat avant retry. Conserver l'identité du travail et ses compteurs ; une décision autorisée prolongeant son budget doit être explicite et tracée. Une nouvelle intention métier reçoit au contraire une nouvelle identité. Ne pas employer une relance globale des échecs pour des effets incertains ou des contrats retirés. Une intervention sur la file ne constitue pas l'autorisation métier d'exécuter tous ses messages.

**R10-024 — Distinguer restauration et redémarrage.** Après restauration d'un état ancien, des effets peuvent avoir eu lieu sans preuve présente dans la base restaurée. Suspendre les relances concernées jusqu'au rapprochement prévu par R21/R17 : sources externes, historique disponible ou intervention habilitée. Réconcilier aussi anciennes files, versions et restrictions de données R09 avant reprise. Ne pas déduire d'une clé d'idempotence absente que l'effet n'a jamais existé ; l'outbox ne rétablit pas une preuve supprimée par restauration.

**Illustration C — Export interrompu puis autre restaurant.** Hypothèse : un export doit reprendre sur une sélection et une version définies. R06 possède sa requête ; noms et tailles de files ne sont pas figés par cet exemple.

| Étape | Application de la procédure |
|---|---|
| Préparer le travail | Conserver référence d'export, sélection/version et progression autorisées ; affecter la capacité des travaux longs, distincte de celle réservée aux travaux courts. |
| Produire un lot | Charger sous contexte du restaurant A ; créer un fragment identifiable de ce lot et confirmer l'avancement après sa disponibilité vérifiée. La reprise détecte/réutilise un fragment déjà complet ; elle ne l'ajoute pas deux fois. |
| S'arrêter entre fragment et checkpoint | Reprendre le même lot, rapprocher le fragment et son état ; ne pas avancer aveuglément. Finaliser l'export uniquement après vérification des lots attendus. |
| Traiter ensuite le restaurant B | Libérer A, établir l'autorité et le contexte B avant lecture, y compris après exception de A. Aucun fichier, connexion ou contexte résiduel ne fournit les données de A. |
| Recevoir un vieux message après déploiement | Vérifier contrat et checkpoint ; utiliser le chemin compatible prévu ou rendre l'incompatibilité visible, sans produire un mélange des deux formats. |

Le stockage/finalisation des fragments appartient à R14/R17 ; cette illustration ne promet pas d'atomicité entre fichier et SQL. La recette vérifie reprise exacte, absence de fuite et maintien des délais des travaux courts sous charge concurrente.

## 7. Prouver le parcours et transmettre ses garanties

**R10-025 — Séparer intention et exécution.** Utiliser `Queue::fake`/`Event::fake` pour une assertion de dispatch ; les événements simulés n'exécutent pas leurs listeners. Compléter `event:list` par un parcours réel pour prouver raccordement, sérialisation, file et handler. Exercer les mécanismes dépendant de PostgreSQL/Redis avec ces services et les workers appropriés. Ne pas laisser une transaction englobante de test masquer le commit extérieur ; préparer des données visibles par les processus testés. Un driver `sync` ne prouve ni persistance de file ni reprise.

**R10-026 — Éprouver les fenêtres de panne utilisées.** Pour le mécanisme durable, provoquer les cas de B et vérifier effet, preuve, travail retrouvable, consommateurs et budget global. Ajouter contenu différent sous même clé, résultat externe incertain, message ancien/désordonné et refus définitif selon les usages. Pour l'annulation, tester les deux ordres : annulation acquise avant l'effet, puis effet acquis avant demande d'annulation ; vérifier les issues du contrat sans requalifier une preuve passée. Tester la borne de tentatives malgré rollback/concurrence et les préconditions d'une chaîne après une issue sans effet.

Employer entrelacements contrôlés et connexions réelles R07 plutôt que compter sur un délai arbitraire. Centraliser les tests du mécanisme commun et les conserver dans la suite de régression ; compléter par les raccordements et particularités des consommateurs. Ne pas imposer toute cette matrice à chaque notification dont la perte est admise.

**R10-027 — Vérifier isolation, limites et progression.** Tester A → B, puis échec de A → B, sous le rôle SQL restreint R08, ainsi que copies et diagnostics R09. Contrôler lots/checkpoints, ressources libérées, arrêts/redémarrages et tâches planifiées concurrentes. Mesurer saturation, âge des files, tentatives et délais des travaux courts pendant travaux longs ou panne fournisseur ; le débit nominal seul ne prouve pas la qualité du service. R19 possède les indicateurs et la méthode de mesure des seuils de conception ; R21 les exercices de reprise/restauration.

**R10-028 — Transmettre un contrat vérifiable.** Conserver près de l'usage effet/preuve, identité, autorité, voie de publication, compatibilité, politique de reprise et variantes, en réutilisant types/tests/documentation existants. Documenter les raccordements et commandes de vérification sans fiche obligatoire par job. Rapporter résultats et limites, tests exécutés et qualifications futures ; un exemple plausible n'est pas une garantie testée. Relire les consommateurs et propriétaires affectés, sans changer une priorité ou possibilité produit pour faciliter le traitement.

**Références natives :** [files et jobs Laravel](https://laravel.com/framework/docs/13.x/queues), [événements et listeners](https://laravel.com/framework/docs/13.x/events), [Horizon](https://laravel.com/framework/docs/13.x/horizon), [planification](https://laravel.com/framework/docs/13.x/scheduling). Vérifier la version réellement installée avant implémentation ; les protocoles d'outbox décrits ici restent à concevoir et éprouver dans l'application.
