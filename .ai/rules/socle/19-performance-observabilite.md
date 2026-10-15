# R19 — Performance et observabilité

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r19).

**Lire pour :** créer, auditer ou corriger une mesure, un diagnostic, une alerte, un traitement coûteux ou une qualification de capacité. **Procédure :** exigence → résultat/population → instrumentation → collecte vérifiée → charge représentative → analyse/comparaison → preuves et limites.

Les prescriptions `R19-001` à `R19-028` sont obligatoires, sauf mention **Revue** ou **Variante** selon [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent objectifs, volumes, droits, données admissibles, conservation et dégradation acceptable ; clarifier une exigence nécessaire introuvable. Les exemples ne fixent aucune valeur ni fonctionnalité produit.

**Frontières :** [R02](02-architecture-modules.md) possède les emplacements ; [R06](06-requetes-lectures-cache.md), [R15](15-ia-outils-fournisseurs.md) et [R16](16-voix-avatar-medias.md) leurs mécanismes ; [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès et représentations ; [R10](10-jobs-evenements-fiabilite.md)/[R11](11-temps-reel-synchronisation.md)/[R17](17-integrations-effets-externes.md) travaux, synchronisation et effets externes. [R18](18-tests-preuves-acceptation.md) organise les preuves, [R20](20-environnement-dependances-qualite.md) versions/configuration/commandes, [R21](21-livraison-exploitation-reprise.md) exploitation et reprise, [R22](../index.md#r22) erreurs et signalement. Consulter les propriétaires affectés selon leur statut ; aucun renvoi ne déclare un dispositif installé.

## 1. Définir le contrat de mesure

**R19-001 — Retrouver le critère.** Identifier sa source, son statut, le consommateur et la décision que la mesure doit éclairer. Préciser conditions d'acceptation et portée ; diagnostic/exploration restent possibles sans objectif chiffré, mais aucune capacité acceptée ne peut en être déduite. Ne pas inventer seuil, priorité ou politique de perte pour terminer une qualification.

**R19-002 — Définir le résultat et la population.** Nommer début/fin observables, unité, fenêtre et issues comptées. Distinguer demande admise, effet acquis et résultat utilisable ; conserver refus attendus, erreurs, expirations, abandons et opérations en cours selon leurs contrats. Identifier parcours, volumes, appareils/réseaux et exclusions utiles ; une population limitée doit limiter la conclusion.

**R19-003 — Stabiliser le sens des signaux.** Définir près de leur propriétaire noms anglais, unités, bornes, issues et dimensions admises, dans les supports existants. Décrire ce que le signal observe et ce qu'il ne prouve pas. Toute évolution de sens, d'unité ou de population actualise producteurs, agrégations, tableaux et alertes concernés ; aucun mélange silencieux entre anciennes et nouvelles versions.

## 2. Instrumenter les frontières et le temps

**R19-004 — Raccorder au bon endroit.** Placer les mécanismes communs dans l'infrastructure R02 : middleware d'entrée, hooks du cycle des jobs, provider d'écouteurs et adaptateurs de transport ; placer jalons métier et mesures frontend auprès de leur propriétaire. Enregistrer un écouteur une fois au démarrage, pas à chaque travail. Mesurer jusqu'au consommateur lorsque requis : émission Reverb, réception et vue cohérente ne sont pas équivalentes. Aucune instrumentation à chaque ligne ni abstraction vide ; choisir les frontières nécessaires au risque.

**R19-005 — Relier sans confondre les identités.** Reprendre opération/travail/tentative R10 ; requête et trace décrivent leur exécution, sans remplacer idempotence ni preuve métier. Relier les reprises à leur origine et identifier la version réellement exécutée, distincte de celle ayant produit le travail. Sélectionner le contexte propagé à chaque frontière, y compris queue, broadcast et fournisseur ; borner/valider les métadonnées externes, résoudre autorité et périmètre selon R08. Un identifiant de diagnostic ne donne aucun droit d'accès.

**R19-006 — Séparer durée et date.** Utiliser un horodatage interprétable, avec fuseau explicite, pour recherche/corrélation ; distinguer occurrence et observation lorsque leur retard importe. Mesurer les durées locales avec une horloge monotone qualifiée, par exemple `hrtime()` ou `performance.now()`. Ne pas soustraire naïvement des horloges de machines différentes ; qualifier synchronisation, précision et limites après veille. Signaler une mesure incohérente plutôt que la corriger silencieusement. Le délai global se mesure à ses propres bornes ; les étapes parallèles ne s'additionnent pas.

**R19-007 — Qualifier les instruments natifs.** Vérifier documentation et comportement des versions utilisées avec R20. `DB::listen` observe les événements `QueryExecuted` ; leurs durées déclarées ne couvrent pas automatiquement connexion, hydratation, PDO direct, transactions et échecs définitifs. `whenQueryingForLongerThan` surveille un temps SQL cumulé, pas une durée globale. Hooks de queue/Horizon et mesures navigateur ont aussi leurs fenêtres ; ne pas présenter un fragment ou un état technique comme preuve de tout le parcours.

## 3. Organiser les journaux et leur contexte

**R19-008 — Raccorder la chaîne réelle.** Partir du responsable de signalement R22 et de la représentation R09, puis configurer émission, routage, format et destination. Déclarer les canaux utiles dans `config/logging.php`, utiliser contexte/formatters/processors selon le mécanisme retenu et vérifier leur configuration effective. Distinguer canal logique, destination de stockage et notification d'alerte. Un nom de canal ne prouve ni collecte ni recherche ; aucun canal choisi depuis une entrée utilisateur.

**R19-009 — Borner la durée de vie du contexte.** Initialiser puis libérer compteurs, contexte de logs et scopes de trace par traitement, succès et erreur compris, selon R10. Résoudre une instance `scoped` courante dans un écouteur persistant, sans capturer celle d'un ancien travail. Examiner `Context`, `withContext`/`shareContext` et enrichissements automatiques : caché aux logs ne signifie pas absent des jobs. Qualifier aussi désérialisation/échec avant le corps du job ; ne pas compter sur `finally` après arrêt forcé.

**R19-010 — Rendre le diagnostic retrouvable et admissible.** Inspecter l'enregistrement final après enrichissements, reporters, SDK et collecteurs, pas uniquement l'appel au logger. Qualifier aussi erreurs d'initialisation et logger natif `emergency` : Laravel peut y écrire une exception avant votre interception. Employer des champs structurés, échappés et bornés : date, domaine, environnement/release, événement/issue et références utiles autorisées ; aucun contexte brut ajouté par défaut. Vérifier recherche par heure/fuseau/périmètre, accès, exports, rotation et retrait des copies selon R08/R09. Un outil de logs externe n'hérite pas automatiquement de la RLS. Messages applicatifs anglais ; codes et diagnostic natif utile selon R22, sans conversion destructive. Restitution utilisateur selon R13/R22.

## 4. Concevoir métriques et traces maîtrisées

**R19-011 — Choisir le signal selon la question.** Métriques pour tendances/distributions, journaux pour événements et traces pour étapes corrélées ; les combiner selon le risque sans imposer une trace détaillée à chaque parcours. Mesurer selon besoin délais, débit utile, issues, ressources CPU/mémoire/GPU, connexions, volumes transmis et coût fournisseur. Distinguer total d'opérations et total de tentatives ; un taux d'échec doit nommer son dénominateur.

**R19-012 — Borner les dimensions.** Définir domaines de valeurs et estimer leurs combinaisons, pas seulement le nombre d'étiquettes. Préférer route normalisée, famille d'opération, issue ou file aux identifiants uniques, URLs paramétrées et textes libres. Une dimension par périmètre métier demande justification de coût/accès ; son utilité en recherche ne justifie pas son ajout à toutes les métriques. Observer volumes indexés, attributs rejetés et limites de cardinalité.

**R19-013 — Agréger sans fabriquer un résultat.** Présenter fenêtre, échantillon, précision et populations utiles ; garder les cas difficiles visibles plutôt que les noyer dans le nominal. Calculer un percentile à partir d'observations/distributions compatibles, jamais en moyennant les percentiles des workers ni en additionnant ceux des étapes. Vérifier données manquantes, remise à zéro des compteurs et biais de sélection ; une moyenne ou un percentile isolé ne qualifie pas un parcours.

**R19-014 — Maîtriser échantillonnage et propagation.** Distinguer indicateurs agrégés, incidents conservés et traces détaillées sélectionnées ; documenter population, règle/taux, changements et pertes connues. Un échantillon favorisant les erreurs n'en donne pas la fréquence générale ; une sélection au départ ne garantit pas de conserver toute erreur future. Borner buffers d'une sélection tardive. Ne pas laisser un indicateur de trace entrant imposer la collecte ni propager tout contexte aux tiers ; vérifier destinations et champs autorisés R09/R17.

## 5. Qualifier charge et capacité

**R19-015 — Construire des conditions reproductibles.** Identifier révision, configurations, ressources, données admissibles et distribution des usages ; faire varier volume stocké, densité des relations, tailles, filtres et concentration utile, pas uniquement le nombre d'appels sur une même clé. Distinguer connexions ouvertes, sessions actives, opérations, messages et destinataires. Reproduire accès/RLS et contraintes pertinentes ; toute simplification, dont fournisseur simulé ou instrumentation différente, limite la qualification.

**R19-016 — Vérifier la charge réellement produite.** Choisir selon la question sessions attendant leur résultat ou arrivées indépendantes des réponses. Comparer charge prévue, départs émis et résultats obtenus ; surveiller ressources/connexions du générateur et départs non émis. Un générateur qui ralentit avec le service peut masquer sa saturation. Qualification de connexions inactives, d'activité utile et de rafale de reconnexion/rapprochement restent distinctes.

**R19-017 — Suivre attente, accumulation et récupération.** Pour travaux différés, distinguer attente initiale, attente entre reprises, exécution, résultat durable et restitution. Observer débit entrant/sortant, profondeur et âge de l'arriéré, travaux encore dus R10 et ressources limitantes. La durée d'essai couvre les cycles pertinents : régime courant, pic, expirations/reprises et récupération. Compter les opérations encore en cours à l'arrêt ; queue vide ou acquittement HTTP ne prouve pas leur accomplissement.

**R19-018 — Exercer les coûts cachés.** Selon le risque : démarrage, caches chauds/froids identifiés, mutations/invalidation, expiration/reconstruction concurrente, panne fournisseur et diffusion à plusieurs destinataires. Inclure essais sur appareils/réseaux utiles et ressources conservées après répétition/veille. Contrôler fraîcheur/isolation pendant l'essai ; aucune capacité extrapolée depuis un petit historique, une clé toujours chaude ou un seul parcours favorable.

## 6. Diagnostiquer et comparer une amélioration

**R19-019 — Localiser le chemin déterminant.** Établir une mesure initiale, décomposer attente, travail, transport et restitution, puis approfondir l'étape limitante par profilage adapté. Inclure contention, mémoire et coût collectif ; une requête rapide isolée ne prouve pas l'efficacité de la page. Pour IA/voix, distinguer première sortie, première réponse utile, résultat d'outil et lecture/arrêt effectifs. Comparer SDK/accès direct seulement à capacités et conditions équivalentes ; changer l'outil ne prouve pas un gain.

**R19-020 — Comparer des conditions identifiables.** Répéter selon la variabilité, conserver populations/configurations et examiner distributions plutôt qu'une durée favorable. Déclarer différences de cache, charge, données et coût de l'instrumentation ; ne pas comparer un résultat complet à un travail simplement admis. Localiser la correction chez son propriétaire R06/R15/R16 ou autre, sans modifier une politique produit pour faciliter la mesure.

**R19-021 — Vérifier bénéfice et garanties.** Comparer délai global, débit utile, erreurs, ressources et coût total, tentatives/préparations abandonnées comprises. Compter les coûts déplacés vers workers, fournisseurs ou appareils. Vérifier exactitude, fraîcheur, isolation, sécurité, durabilité et restitution selon les garanties applicables. Une réponse plus tôt, une projection périmée ou davantage d'abandons ne constitue pas à elle seule une amélioration acquise.

## 7. Rendre collecte et alertes fiables

**R19-022 — Mesurer aussi le coût de collecte.** Borner fréquence, tailles, profondeur des contextes/causes, détails et stockage selon les budgets. Qualifier surcoût CPU/mémoire/réseau/disque et impact sur le chemin critique ; séparer visibilité courante et diagnostic approfondi ciblé. **Variante :** activation temporaire ou échantillonnée lorsque justifiée, avec portée, limites et désactivation ; aucune capture brute implicite ni réserve illimitée.

**R19-023 — Raccorder une collecte bornée.** Définir buffers, délais, reprises, limites mémoire/disque, comportement de saturation et destination de secours selon les exigences. Une file d'export ou un stockage local ne rend pas le diagnostic infaillible. Observer retard, rejets, pertes connues et fraîcheur ; signaler collecte absente avec un contrôle capable de survivre à la panne concernée. Ne pas conclure à l'absence d'incidents depuis un tableau devenu silencieux.

**R19-024 — Préserver la cause et les engagements.** Protéger émission et nettoyage : leur échec ne doit ni masquer l'erreur initiale ni réémettre une mutation. Un secours reçoit une représentation déjà autorisée, suit un chemin indépendant borné et évite la récursion ; aucune lecture SQL indispensable au seul signalement d'une panne SQL. Appliquer la politique prévue si aucun secours ne fonctionne, sans inventer un succès. L'audit métier durable garde ses garanties R07/R10 ; ne pas lui appliquer automatiquement l'échantillonnage ou la dégradation des diagnostics.

**R19-025 — Définir une alerte exploitable.** Retrouver critère, population/fenêtre, données manquantes, gravité et destinataire autorisé. Définir regroupement/déduplication, déclenchement et retour à la normale ; relier au diagnostic et à la procédure R21. Distinguer tentative et incident collectif pour éviter une notification autonome par reprise. Seuils, contenu et accès proviennent des sources applicables ; une alerte ne copie pas automatiquement l'exception ou le contexte brut.

**R19-026 — Éprouver incident et récupération.** Provoquer les cas pertinents : panne, retard, saturation, doublon, collecte absente et reprise. Vérifier réception/recherche réelles, signal de perte et secours, puis acheminement sûr de l'alerte et retour à la normale. Réutiliser les garanties/tests des propriétaires ; une simulation du logger seule ne prouve ni destination, ni droits, ni capacité du système de collecte.

## 8. Vérifier et transmettre les preuves

**R19-027 — Couvrir les raccordements concernés.** Selon R18, vérifier valeurs/unités, contexte A → B après succès/échec, absence de contenu exclu après tous les enrichissements, accès refusé et nettoyage. Vérifier aussi métriques agrégées, sélection de traces, opérations inachevées et limites sous charge. Qualifier transport/instruments réels requis ; aucun test local ne démontre automatiquement réception fournisseur, rendu/son matériel ou capacité de production.

**R19-028 — Transmettre protocole et limites.** Conserver près du propriétaire critère/source, bornes/populations, conditions/données, révision/configuration, commandes R20, résultats et limites dans les supports existants. Distinguer prévu, simulé, exécuté, accepté et indisponible ; preuve essentielle manquante selon R01/R18. Actualiser signaux/tableaux/alertes affectés, vérifier les raccordements de livraison R21. Aucun dossier administratif par mesure ni capacité déclarée à partir de cette rédaction.

## Illustrations — Mécanismes décisifs, pas une application installée

Hypothèses et valeurs fictives ; aucune route, file, destination ou politique produit n'est adoptée. Les extraits situent tous les symboles détaillés. Assemblage, versions et raccordements non fournis restent des prérequis à qualifier avant utilisation.

### A — Du déclenchement au résultat utilisable

Hypothèse : une opération admise déclenche un travail puis un état utilisable à l'écran. Les identités R10 relient les étapes ; chaque exécution mesure sa propre fenêtre.

| Jalon | Mesure et limite |
| --- | --- |
| Interaction navigateur → état actuel utilisable | Délai global depuis ce navigateur ; émission d'une réponse ne constitue pas sa fin. |
| Entrée HTTP → sortie du traitement mesuré | Durée locale, issue et lectures observées ; ne couvre ni l'avant-middleware ni le rendu. |
| Publication → début de tentative → résultat acquis | Attente, tentative et arriéré ; âge entre machines avec horloges qualifiées. |
| Notification → réception → vue cohérente | Observer le consommateur requis R11, pas seulement le succès de publication. |

**Compteur SQL local à une fenêtre**, sans SQL/paramètres stockés. PHP 64 bits pour `hrtime(true)` ; provider enregistré une fois et scope requête/job qualifié sur le runtime. Arbre limité au mécanisme :

```text
app/Infrastructure/Observability/
├── Measurements/SqlWindow.php
└── Providers/ObservabilityServiceProvider.php
bootstrap/providers.php                 # Enregistrement du provider
```

```php
declare(strict_types=1);

namespace App\Infrastructure\Observability\Measurements;

use Illuminate\Database\Events\QueryExecuted;

final class SqlWindow
{
    private ?int $startedAt = null;
    private int $observedQueryCount = 0;
    private float $reportedQueryTimeMs = 0.0;

    public function start(): void
    {
        $this->reset();
        $this->startedAt = hrtime(true);
    }

    public function record(QueryExecuted $event): void
    {
        if ($this->startedAt === null) {
            return;
        }
        $this->observedQueryCount++;
        $this->reportedQueryTimeMs += $event->time;
    }

    /** @return array{processingTimeMs: float, observedQueryCount: int, reportedQueryTimeMs: float}|null */
    public function close(): ?array
    {
        if ($this->startedAt === null) {
            return null;
        }
        $result = [
            'processingTimeMs' => (hrtime(true) - $this->startedAt) / 1_000_000.0,
            'observedQueryCount' => $this->observedQueryCount,
            'reportedQueryTimeMs' => $this->reportedQueryTimeMs,
        ];
        $this->reset();
        return $result;
    }

    public function reset(): void
    {
        $this->startedAt = null;
        $this->observedQueryCount = 0;
        $this->reportedQueryTimeMs = 0.0;
    }
}
```

```php
declare(strict_types=1);

namespace App\Infrastructure\Observability\Providers;

use App\Infrastructure\Observability\Measurements\SqlWindow;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SqlWindow::class, fn () => new SqlWindow());
    }

    public function boot(): void
    {
        DB::listen(static function (QueryExecuted $event): void {
            app(SqlWindow::class)->record($event);
        });
    }
}
```

À l'entrée choisie, appeler `start()` avant le traitement ; à sa sortie normale ou exceptionnelle, `close()` avant l'émission protégée R19-024. Ce raccordement HTTP/job n'est pas fourni ici. `null` signifie fenêtre inactive, pas durée nulle. Pas de fenêtres imbriquées sur ce compteur ; une mesure imbriquée exige un support distinct. Le callback résout le scope courant et ne fait ni SQL ni export ; ne pas capturer un compteur au boot, ajouter des listeners par travail ou activer `enableQueryLog()`.

`observedQueryCount` et `reportedQueryTimeMs` portent sur les événements reçus. Une erreur SQL définitive peut ne pas en produire ; une reprise native peut aboutir à un seul événement. Le temps déclaré par Laravel n'est pas le chrono monotone du traitement : qualifier sa précision/incohérences et ne pas soustraire sa somme pour inventer un « temps PHP ». Le raccordement R22 compte séparément les issues d'erreur. Vérifier reset après succès/échec, compteur inactif pendant export et scope neuf ; l'exemple ne prouve pas ces raccordements.

**Variante voix :** jalons fin de parole, transcription exploitable, première réponse utile, résultat d'outil, lecture/arrêt effectifs R15/R16. Préparation, inférence et restitution peuvent se chevaucher ; mesurer directement le délai global et garder coût/tentatives abandonnées. Une formule d'attente ou un fragment reçu n'est pas une preuve de parole utile entendue.

### B — Une amélioration apparente sous charge

Valeurs illustratives sur une fenêtre identique ; aucune cible de capacité. Hypothèse : la demande devait produire un résultat utilisable.

| Observation | Avant | Après |
| --- | --- | --- |
| Départs prévus / émis / résultats utilisables dans la fenêtre | 100 / 100 / 100 | 100 / 70 / 40 |
| Traitement HTTP des seuls succès observés | 200 ms | 20 ms |
| Travaux restant dus à la fin | 0 | 30 |

Le bilan « dix fois plus rapide » serait injustifié : le générateur a émis moins de demandes et le travail a été déplacé. Retrouver les 30 départs non émis, les issues et le délai global des 70 opérations initiées ; expliquer aussi les différences de populations. Mesurer âge/progression de l'arriéré et récupération après le pic, sans effacer les inachevés du rapport. Une exécution de reprises n'est pas une nouvelle intention métier.

Reprendre avec modèle de charge répondant à la question, données/ressources/caches identifiables et instrumentation comparable. Vérifier résultats, refus/erreurs, fraîcheur et débit utile. Ni compter des sockets inactives ni moyenner les p95 de plusieurs workers ne corrige ce protocole.

### C — Retrouver l'incident et éprouver la collecte

Reprendre le canal illustratif `delivery` de [R09](09-confidentialite-cycle-donnees.md). **Hypothèses locales :** représentation ci-dessous autorisée, fichier privé retenu pour l'exemple, format JSON et fuseau du logger effectivement configuré en UTC ; accès, rotation/retrait et limites raccordés selon la politique avant utilisation. Le formatter ne fixe pas le fuseau. Ce choix documentaire n'adopte ni stockage du produit ni rétention.

Dans `config/logging.php`, fusionner dans `channels` existant :

```php
'delivery' => [
    'driver' => 'single',
    'name' => 'delivery',
    'path' => storage_path('logs/delivery.log'),
    'level' => 'info',
    'formatter' => \Monolog\Formatter\JsonFormatter::class,
],
```

Le responsable R22 émet une projection admise vers `Log::channel('delivery')`. Le code de signalement/secours n'est pas fourni : ne pas mettre un logger non protégé dans un `finally` ni fusionner un tableau entrant avec le contexte. Vérifier configuration chargée, permissions et résultat après tous les enrichissements. Exemple fictif d'enregistrement final, extrait :

```json
{
  "message": "Document delivery failed",
  "channel": "delivery",
  "datetime": "2026-10-02T18:30:03+00:00",
  "context": {
    "occurredAt": "2026-10-02T18:30:00+00:00",
    "restaurantId": "restaurant-demo",
    "operationId": "operation-demo",
    "workId": "work-demo",
    "attemptId": "attempt-2",
    "release": "example-worker-r1",
    "causeType": "ConnectionException",
    "causeMessage": "Connection timed out"
  }
}
```

`causeMessage` représente ici un diagnostic natif fictif admis, pas un remplacement systématique du message d'origine ; codes, causes et restitution restent R22. Aucun email, contenu conversationnel ou secret ajouté par l'exemple. Date du logger et occurrence distinguent le retard ; un collecteur peut encore avoir une autre date de réception.

**Recherche :** partir de l'heure locale/fuseau signalés et du périmètre autorisé ; convertir puis chercher une fenêtre tenant compte du retard, rapprocher opération/travail/tentatives et vérifier release exécutée. Par exemple, 20 h 30 à Paris le 2 octobre 2026 correspond ici à 18 h 30 UTC. Distinguer refus d'accès, diagnostic absent et collecte indisponible ; une référence publique ne permet pas sa consultation privée.

**Défaillance :** rendre la destination inaccessible ou saturée dans un essai isolé ; vérifier cause initiale préservée, buffers/reprises bornés et secours filtré indépendant. Si aucun signal n'est conservé, déclarer cette limite plutôt qu'inventer sa disponibilité. Observer retard/perte/collecte silencieuse, puis provoquer l'alerte et vérifier destinataire autorisé, regroupement, accès et retour à la normale. Aucune erreur native brute ne contourne R09 dans le secours ; aucun journal technique ne remplace la preuve métier R10.

**Repères techniques consultés le 2 octobre 2026 :** [SQL Laravel](https://laravel.com/framework/docs/13.x/database#monitoring-cumulative-query-time), [implémentation Connection](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Database/Connection.php), [scopes Laravel](https://laravel.com/framework/docs/13.x/container#binding-scoped-singletons), [journaux Laravel](https://laravel.com/framework/docs/13.x/logging), [initialisation et secours](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Log/LogManager.php), [chrono PHP](https://www.php.net/manual/en/function.hrtime.php), [horloge navigateur](https://developer.mozilla.org/en-US/docs/Web/API/Performance/now), [modèles de charge](https://grafana.com/docs/k6/latest/using-k6/scenarios/concepts/open-vs-closed/), [agrégation des quantiles](https://prometheus.io/docs/practices/histograms/). Vérifier les versions qualifiées ; aucune installation, compilation, émission réelle, mesure de charge ou qualification exécutée par cette rédaction.
