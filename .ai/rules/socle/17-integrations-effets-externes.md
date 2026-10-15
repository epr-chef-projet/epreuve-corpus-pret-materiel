# R17 — Intégrations et effets externes

**Version 0.1 — à valider.** [Index des règles](../index.md#r17).

**Lire pour :** créer, auditer ou modifier un appel extérieur, adaptateur fournisseur, webhook, envoi d'email, échange de fichier, reprise ou remplacement d'intégration. **Procédure :** exigence → contrat/adaptateur → échange protégé → résultat vérifié → reprise → preuves.

Les prescriptions `R17-001` à `R17-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent fournisseurs, effets attendus, garanties requises, limites et données admises ; R17 explique leur mise en œuvre. Clarifier une exigence nécessaire introuvable. Un exemple n'adopte ni prestataire, ni schéma, ni parcours produit.

**Frontières :** [R02](02-architecture-modules.md)/[R03](03-php-laravel-metier.md)/[R04](04-contrats-dto-validation.md) possèdent placement, métier et contrats ; [R07](07-transactions-concurrence-etats.md)/[R10](10-jobs-evenements-fiabilite.md) transactions SQL, files et suivi durable ; [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès et données. Consulter selon impact [R14](14-assets-fichiers-stockage.md), [R15](15-ia-outils-fournisseurs.md), [R16](16-voix-avatar-medias.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R21](21-livraison-exploitation-reprise.md), [R22](../index.md#r22) pour fichiers, IA, médias, tests, supervision, exploitation et erreurs. Respecter leur statut ; un renvoi futur ne prouve aucun mécanisme installé.

## 1. Qualifier l'intégration et placer les responsabilités

**R17-001 — Définir l'effet et sa preuve.** Retrouver capacité, propriétaire, préconditions, délai utile, données nécessaires et conséquence d'une répétition/panne. Nommer ce qui prouve le résultat requis : acceptation du prestataire, état distant confirmé ou fichier finalisé, selon le contrat. Distinguer envoyé, accepté et accompli ; un statut HTTP ou une fin de job ne prouve pas tous ces faits. Examiner aussi coûts/effets d'une lecture ou génération distante. Confronter les exigences aux capacités documentées et qualifiées de l'offre/API ; un SDK ne crée pas une garantie absente du fournisseur.

**R17-002 — Isoler le protocole chez son propriétaire.** Placer l'adaptateur dans `Integrations/<Capacite>/` auprès de l'usage ou du sous-domaine R02 ; réserver `app/Infrastructure/` aux mécanismes transversaux sans détails métier. L'action orchestre, le repository accède à SQL, le service décide sur les données fournies et l'adaptateur réalise l'échange. Ne pas appeler le fournisseur depuis un service métier ni copier le protocole dans chaque contrôleur/job. Utiliser client HTTP Laravel, transport natif ou SDK pertinent ; justifier interface et partage selon R02, sans client universel ni enveloppe systématique de chaque méthode native.

**R17-003 — Raccorder explicitement et éviter l'état résiduel.** Déclarer le provider selon R02, lier le contrat utile dans `register()` et l'injecter chez son consommateur. Résoudre configuration et capacité effectives, puis construire un client/requête portant seulement ce contexte. Ne pas conserver dans un singleton mutable compte, token, périmètre d'accès ou en-têtes d'une opération précédente. Prévoir renouvellement des processus/configuration avec R10/R21. Vérifier résolution et appel effectif, y compris avec configuration mise en cache ; la présence d'un adaptateur ne réalise aucun binding.

**R17-004 — Traduire les données à la frontière.** Définir entrée, résultat et faits à préserver avec R04 ; garder objets SDK, réponses HTTP et champs protocolaires hors du contrat métier. Mapper explicitement noms, unités, dates et versions ; conserver l'anglais des identifiants internes sans renommer les clés imposées par le protocole. Sélectionner les données admises R09 avant transmission. Une donnée fournisseur reste à valider ; elle ne choisit pas arbitrairement une classe, une URL à appeler ou une capacité IA. Ne pas introduire un DTO entre chaque appel interne.

## 2. Configurer, protéger et borner les échanges

**R17-005 — Valider la configuration de la capacité utilisée.** Définir endpoint, environnement, compte, authentification, version et limites nécessaires. Utiliser `env()` dans les fichiers de configuration, puis `config()` dans le code ; convertir/valider les valeurs avant usage, sans casts masquant une valeur invalide. Garder cohérents endpoint, compte, identifiants externes et mode test/réel. Une configuration absente/incompatible donne une issue explicite ; ne pas utiliser silencieusement compte global, environnement réel ou réponse simulée. Valider les intégrations effectivement activées sans imposer les secrets de toutes les variantes au démarrage.

**R17-006 — Protéger authentification et contenu.** Appliquer R08/R09 aux secrets, scopes, environnements et destinations, y compris aux options de debug, traces SDK et journaux du tiers. Vérifier TLS ; ne pas désactiver sa validation pour contourner un incident. Employer les protocoles/bibliothèques éprouvés du fournisseur, sans cryptographie sur mesure. Si l'authentification doit être renouvelée, coordonner renouvellement concurrent et reprise bornée ; un changement de token ne doit ni changer le compte ni réautoriser un effet incertain. Consigner qualification et limites de traitement distant, pas seulement le réglage local annoncé.

**R17-007 — Contrôler les destinations variables.** Pour une URL issue d'une entrée ou d'une réponse fournisseur, vérifier protocole, destination effective et accès permis, notamment adresses internes, loopback et métadonnées. Préférer les endpoints maîtrisés ; désactiver les redirections inutiles, sinon vérifier chaque destination. Couvrir résolution DNS et IPv4/IPv6 avec les protections réseau appropriées ; une vérification textuelle du domaine seule ne suffit pas. Ne pas transférer un secret à une autre origine. R14 possède traitement et exposition du fichier téléchargé ; R17 borne le transfert et sa destination.

**R17-008 — Fixer des limites effectives.** Définir connexion, réponse/durée totale, taille, concurrence et quota selon l'usage, le compte et la capacité du fournisseur. Configurer `connectTimeout()`/`timeout()` ou les équivalents du transport ; borner aussi séries d'appels, pagination, pooling, corps reçus et coût cumulé. Une limite après chargement complet ne protège pas le téléchargement. Pour un flux, traiter délai sans progression, interruption, limite de contenu et fermeture des ressources. Raccorder ces limites au budget du travail R10 ; nombre de workers ou timeout PHP ne suffit pas à les établir.

## 3. Vérifier et traduire les réponses

**R17-009 — Vérifier statut, forme et sens.** Interpréter les codes et données selon le protocole ; contrôler les champs requis avant construction du résultat R04. Dans un appel HTTP Laravel ordinaire, les réponses `4xx`/`5xx` ne lèvent pas automatiquement d'exception : inspecter la réponse ou utiliser `throw()` au point de traduction utile. `successful()` ou `throw()` seul ne valide pas le contenu ni l'accomplissement. `ConnectionException`, timeout ou réponse illisible exigent aussi une qualification de l'effet possible. Ne pas produire résultat vide, identifiant inventé ou succès de substitution après erreur.

**R17-010 — Expliciter l'issue et la suite permise.** Déclarer les issues de chaque contrat, sans déduire la reprise du texte d'un message. L'adaptateur traduit le protocole ; le cas d'usage applique la conduite autorisée, puis la frontière R22 restitue le résultat.

| Issue établie | Procédure |
|---|---|
| Effet requis prouvé | Enregistrer/retrouver sa preuve autorisée, puis terminer cet effet sans le répéter. |
| Demande acceptée, effet encore attendu | Conserver référence et suivi requis ; ne pas annoncer l'accomplissement avant sa preuve. |
| Refus établi | Résultat ordinaire ou exception ciblée selon le contrat R03/R22 ; aucune reprise aveugle. |
| Incident transitoire | Reprise uniquement si l'effet peut être répété sans risque non accepté, sous budget R17-014. |
| Réponse invalide/contrat incompatible | Signaler l'incompatibilité ; qualifier l'effet possible avant une nouvelle demande. |
| Résultat inconnu | Rapprocher selon R17-015 ; ne pas affirmer succès, non-exécution ou rollback sans preuve. |

**R17-011 — Préserver la cause sans l'exposer.** Intercepter là où classement, adaptation ou traitement a une responsabilité réelle. Conserver cause native, code/statut et références utiles dans les représentations autorisées ; lors d'une traduction d'exception, préserver la cause précédente selon R22. Filtrer également messages, corps, en-têtes et instrumentation R09. Ne pas journaliser le même échec à chaque couche ni retourner le diagnostic brut à l'interface/IA. `errorCode`, signalement, catalogue et langue de restitution restent chez R22 ; aucun deuxième catalogue fournisseur n'est défini ici.

## 4. Coordonner les reprises et protéger l'effet distant

**R17-012 — Qualifier l'identité et la protection distantes.** Relier l'identité de travail R07/R10 à celle reconnue par le fournisseur, avec compte/environnement/opération et contenu comparable. Pour la même intention, conserver identité et données sémantiques ; nouvelle intention, nouvelle identité. Vérifier opérations couvertes, durée/portée de déduplication, appels concurrents et contenu différent. Une clé conservée chez nous ne prolonge pas la garantie distante. Examiner aussi la reconstruction du payload : URL temporaire, contenu ou destinataire courant peuvent changer lors d'une reprise. Ne pas supposer tout `GET` gratuit ou tout `POST` répétable.

**R17-013 — Séparer acquisition locale et appel réseau.** Pour un effet durable, sous autorité/contexte R08 : acquérir préparation, identité et faits utiles avec R07/R10, terminer la transaction, appeler le tiers, puis enregistrer son résultat dans une nouvelle transaction protégée. Chaque accès SQL rétablit son contexte ; ne pas maintenir verrou/transaction SQL pendant l'attente réseau. Conditionner mises à jour à l'opération, à son contenu et à l'état/possession encore valides, sans écraser une preuve acquise. Une réservation locale expirée ne prouve pas l'arrêt du premier exécutant ; protéger l'effet distant ou appliquer la limite explicite du contrat. Ne pas recopier suivi et preuve sans besoin.

**R17-014 — Coordonner le budget de reprise.** Inventorier retries SDK, HTTP, job et rapprochement ; allouer leur budget commun, conservé selon R10-011. Définir erreurs/conditions admissibles, échéance, espacement et concurrence. Utiliser `Http::retry()` seulement avec conditions sûres ; ni `5xx` ni `ConnectionException` ne prouve à lui seul l'absence d'effet. Intégrer `Retry-After`, renouvellement d'authentification et quotas dans la limite globale sans prolongation implicite. Vérifier `RequestException`, `ConnectionException` et l'option `throw` sur la version utilisée ; supprimer les boucles indépendantes qui multiplient appels et coûts.

**R17-015 — Rapprocher avant de répéter un effet incertain.** Rechercher via référence/clé, notification authentifiée ou autre preuve prévue. Une absence dans une recherche éventuellement retardée ne prouve pas la non-exécution. Enregistrer la preuve retrouvée ; n'envoyer de nouveau que selon les garanties encore valides. Couvrir réponse perdue, effet distant acquis puis panne SQL, arrêt du worker et perte d'une preuve après restauration R10/R21. Sans recherche/idempotence suffisante, appliquer la conduite explicitement acceptée pour cet usage ou clarifier l'exigence ; ne pas promettre « exactement une fois ». Rendre le travail et l'intervention requise retrouvables, avec conservation R09 et reprise bornée.

**R17-016 — Traiter interruption et résultat tardif.** Distinguer abandon local, demande d'annulation distante et confirmation de cette annulation. L'arrêt d'une requête/du navigateur ou l'expiration d'un verrou ne prouve pas l'arrêt du fournisseur. Revalider les préconditions avant un effet encore dû, selon R07/R10 ; préserver les preuves d'un effet déjà acquis. Rapprocher une réponse tardive sans rétablir aveuglément un ancien état ni effacer une annulation demandée. La restitution et la suite autorisée dépendent du contrat ; R15/R16 possèdent la conduite du dialogue et des flux.

**Illustration A — Document envoyé, réponse perdue.** Hypothèses : une demande d'envoi autorisée existe selon R09/R10 ; le fournisseur fictif accepte un message par clé stable, propose une recherche par cette clé et exige un payload identique pendant sa fenêtre de protection. Sa référence de document reste utilisable pendant la reprise. L'effet suivi ici est l'acceptation du message, pas sa livraison au destinataire. Aucune de ces capacités n'est sélectionnée pour le produit.

```text
app/Modules/Documents/
├── Providers/DocumentsServiceProvider.php
└── Delivery/UseCases/SendDocument/
    ├── SendDocumentAction.php
    ├── SendDocumentRepository.php
    └── Integrations/Email/
        ├── EmailGateway.php
        ├── EmailSubmissionInput.php
        ├── EmailSubmissionResult.php
        └── ExampleEmailAdapter.php
config/services.php               # Configuration example_email
bootstrap/providers.php           # Provider Documents explicitement déclaré
```

Contrats PHP internes de ces trois fichiers, namespaces omis ; chacun suit `strict_types=1`. Validation de l'entrée et sélection des données autorisées précèdent leur construction :

```php
final readonly class EmailSubmissionInput
{
    public function __construct(
        public string $submissionKey,
        public string $recipient,
        public string $documentReference,
    ) {}
}

final readonly class EmailSubmissionResult
{
    public function __construct(public string $providerSubmissionId) {}
}

interface EmailGateway
{
    public function submit(EmailSubmissionInput $input): EmailSubmissionResult;
}
```

Dans `DocumentsServiceProvider::register()`, après chargement, validation et normalisation typée de `$settings = config('services.example_email')` :

```php
$this->app->bind(EmailGateway::class, fn () => new ExampleEmailAdapter(
    endpoint: $settings['endpoint'],
    token: $settings['token'],
    connectTimeoutSeconds: $settings['connect_timeout_seconds'],
    timeoutSeconds: $settings['timeout_seconds'],
));
```

L'action injecte `EmailGateway`. Les quatre paramètres sont des propriétés immuables du constructeur de l'adaptateur ; secrets et variables d'environnement restent hors des contrats. Extrait transport/validation dans sa méthode `submit()` ; `Http` désigne la façade Laravel et `UnexpectedValueException` la classe PHP native :

```php
$response = Http::baseUrl($this->endpoint)
    ->withToken($this->token)
    ->acceptJson()
    ->connectTimeout($this->connectTimeoutSeconds)
    ->timeout($this->timeoutSeconds)
    ->withOptions(['allow_redirects' => false])
    ->withHeaders(['Idempotency-Key' => $input->submissionKey])
    ->post('/messages', [
        'recipient' => $input->recipient,
        'document_ref' => $input->documentReference,
    ]);

$response->throw();
$body = $response->json();
if (! $response->accepted() || ! is_array($body)
    || ($body['state'] ?? null) !== 'accepted'
    || ! is_string($body['submission_id'] ?? null)
    || $body['submission_id'] === '' || strlen($body['submission_id']) > 200) {
    throw new UnexpectedValueException('Unusable email acceptance response.');
}

return new EmailSubmissionResult($body['submission_id']);
```

Le statut `202`, les champs et la borne `200` appartiennent uniquement au protocole fictif. **Omissions annoncées :** validation de configuration, traduction des échecs dans l'adaptateur selon R17-009 à 011, recherche distante et code SQL du repository. Ces responsabilités restent requises : `throw()` ne décide pas de la reprise, et une réponse d'acceptation inexploitable peut laisser l'effet inconnu. L'interface montrée expose seulement la soumission ; ajouter la capacité de recherche nécessaire au contrat réel, sans renvoyer `null` comme preuve de non-exécution.

1. `SendDocumentAction` retrouve le travail autorisé, son destinataire et le document choisis. Avec son repository, elle acquiert la préparation selon R07/R10, puis termine cette transaction avant l'appel. Elle conserve le payload comparable pour les reprises admises ; aucun prix/document/adresse n'est reconstruit depuis une valeur courante différente.
2. L'adaptateur transmet la demande. Une acceptation vérifiée produit `EmailSubmissionResult` ; l'action enregistre cette preuve dans sa nouvelle transaction. Le journal autorisé peut corréler restaurant, travail et référence fournisseur ; il n'obtient pas automatiquement adresse, document ou corps de réponse.
3. Si la réponse est perdue, le travail reste incertain et retrouvable. La recherche peut retrouver l'acceptation ; une nouvelle soumission, si autorisée par les garanties encore valides, conserve clé et contenu. Si la preuve SQL n'a pas été enregistrée malgré acceptation distante, le même rapprochement s'applique.
4. Sans recherche ni déduplication suffisante, la variante suit la conduite explicitement admise pour cet envoi : reprise avec risque accepté, arrêt/intervention ou autre issue prévue. R17 ne choisit pas cette conduite produit. L'interface/IA reçoit l'issue sûre via R22, jamais « email livré » sur la seule acceptation.

## 5. Recevoir et traiter les webhooks

**R17-017 — Vérifier la notification avant son effet.** Raccorder route, limites, middleware et vérification propres à l'intégration. Utiliser le protocole/SDK officiel avec les éléments d'authentification et en-têtes requis ; si la signature porte sur le corps, employer les octets exacts, pas un JSON reconstruit. Appliquer les protections contre rejeu prévues par ce protocole, avec horloge et rotation maîtrisées lorsqu'elles interviennent. Borner réception avant parsing ; ne pas conserver/journaliser automatiquement le corps brut R09. Limiter toute exemption CSRF à cette entrée protégée selon R08. Une IP admise ou CORS seul ne prouve pas l'authenticité.

**R17-018 — Résoudre le bon périmètre.** Après vérification, valider type, champs utiles et version lorsqu'elle fait partie du contrat R04 ; rattacher compte, environnement et objet externe via correspondances maîtrisées. Un identifiant de périmètre reçu ou une signature valide ne donne pas de droits globaux. Résoudre l'autorité système et la portée R08 avant accès protégé, y compris pour la réception durable et le rapprochement ; déclarer le resolver minimal autorisé sans ouvrir les tables métier de tous les périmètres. Prévoir objet inconnu, notification non prise en charge et contrat incompatible avec une issue contrôlée, sans mutation arbitraire ni acquittement trompeur.

**R17-019 — Acquérir la prise en charge avant acquittement.** Si le contrat interdit la perte d'un traitement différé, enregistrer réception utile, identité et possibilité de reprise dans l'unité SQL appropriée ; publier sa suite selon R10-008 à R10-011. Répondre positivement seulement après acquisition de cette responsabilité, même si l'effet métier reste à faire. Pour un traitement synchrone, acquitter après son résultat acquis. Une simple publication Redis ne prouve pas la réception durable. Traiter un doublon déjà acquis selon son résultat, sans nouvelle exécution ; un échec avant acquisition reste visible selon le protocole. Conserver uniquement la représentation autorisée et suffisante, sans inbox/table universelle ni corps brut imposé.

**R17-020 — Protéger répétitions et ordre métier.** Définir la portée de l'identité d'événement/livraison avec fournisseur, compte, environnement et consommateur utiles ; coordonner acquisition et résultat R07/R10. Deux événements distincts peuvent porter le même effet : protéger également transition et état métier. Traiter explicitement un contenu contradictoire pour le même fait. Appliquer version/prérequis fiables, transitions autorisées ou recherche d'état courant ; ni heure d'arrivée ni timestamp seul ne fournit une règle générale d'ordre. Un fait ancien peut encore compléter une preuve historique utile ; ne pas le rejeter uniquement parce qu'il est ancien. Prévoir dépendance manquante et incohérence sans boucle de reprise illimitée.

**R17-021 — Rapprocher les chemins concurrents.** Couvrir notification reçue avant/après réponse sortante, deux consommateurs, recherche distante et réponse tardive. Utiliser les mêmes correspondances et protections du propriétaire : conserver preuve de l'effet et fraîcheur de l'état projeté selon leur sens, sans recopier aveuglément un ancien snapshot. Une réponse retardée ne doit pas écraser la notification plus récente. Si le rattachement n'est pas encore disponible, garder une possibilité de rapprochement bornée et autorisée selon le contrat, pas une affectation à un périmètre par défaut.

**Illustration B — Notification d'abonnement.** Hypothèses : un fournisseur fictif signe ses notifications ; chaque événement porte une identité, un compte/environnement, un objet et une version monotone de cet objet. L'exemple met à jour sa projection courante seulement depuis une version supérieure et suit durablement les réceptions acceptées. Ce sont des choix pédagogiques, pas les états ni règles d'abonnement du produit.

Chemin R02 : entrée dans `Integrations/Subscriptions/` du propriétaire → vérification/contrat → cas d'usage de réception → traitement de l'état avec repository/service. L'adaptateur connaît la signature et le protocole ; le service applique la transition illustrative ; le repository protège la réception et la projection. Les noms du module, des classes, tables et package restent à définir dans la conception effective.

| Situation | Procédure et preuve |
|---|---|
| Signature invalide, corps modifié ou taille refusée | Refus du protocole ; aucune réception métier acquise ni mutation. Diagnostic filtré. |
| Notification authentique visant un mauvais compte/environnement | Refus ou issue contrôlée selon contrat ; aucun changement de périmètre implicite. |
| Notification valide prise en charge en différé | Réception minimale et suite durable acquises ensemble selon R10, puis acquittement ; l'effet métier reste suivi séparément. |
| Même événement simultané ou renvoyé après réponse perdue | Acquisition R07 et résultat commun ; un seul effet, même si plusieurs réponses HTTP sont émises. |
| Événements distincts, version identique | Réception identifiée, transition protégée ; ne pas reproduire l'effet sur la seule différence d'identité d'événement. |
| Version 8 avant version 7 | Projection reste à 8 ; examiner les faits/preuves utiles de 7 sans régresser l'état. |
| Notification avant réponse de l'appel sortant | Rattacher par référence maîtrisée ou rapprochement borné ; la réponse tardive ne remplace pas aveuglément la projection acquise. |
| Arrêt après réception avant traitement | Travail retrouvé puis repris sous le même périmètre et les mêmes protections. |

Sans version fiable, choisir la procédure prévue, par exemple rapprochement avec un état courant consultable ; ne pas fabriquer cet ordre depuis les dates. Vérifier aussi l'autorité de la lecture distante. L'acquittement doit respecter le protocole du fournisseur et le résultat réellement acquis.

## 6. Faire évoluer, annuler et remplacer une intégration

**R17-022 — Préserver les opérations actives lors d'une évolution.** Inventorier contrats, références, comptes, jobs/réceptions et effets incertains avant remplacement ou changement de version/secret. Avec R04/R10/R21, prévoir lecteurs compatibles, raccordements anciens nécessaires et rotation/révocation contrôlées. Ne pas reprendre une ancienne opération sous le nouveau compte ni rendre ses preuves introuvables. Tester anciennes notifications et réponses tardives ; retirer l'ancien chemin selon la fenêtre et la conduite définies, pas sur le seul succès du déploiement web.

**R17-023 — Conditionner la bascule.** Vérifier que le prestataire de remplacement satisfait contrat, accès et exigences de données. Examiner l'état de chaque opération avant bascule : l'idempotence d'un fournisseur n'est pas présumée partagée par l'autre. Résultat inconnu ou flux partiellement consommé exige sa conduite explicite ; ne pas mélanger deux réponses ni dupliquer un effet pour rétablir le service. **Variante :** suspension temporaire des appels/circuit breaker si coût et fréquence justifient ce mécanisme ; définir portée, expiration, reprise contrôlée et coordination sans bloquer des intégrations indépendantes. Aucun fallback ni seuil universel n'est imposé.

**R17-024 — Traiter la compensation comme une opération.** Vérifier autorisation, préconditions et garanties de l'annulation ou correction distante selon le contrat. Donner à cette nouvelle intention sa propre identité, preuve et budget ; appliquer R07/R10/R17 à son exécution. Conserver le fait de l'effet initial, même si sa compensation réussit. Une compensation peut échouer, rester incertaine ou être impossible ; prévoir son suivi et l'intervention requise, sans présenter le rollback SQL comme son accomplissement.

**Illustration C — Saturation puis solution de remplacement.** Hypothèse : un appel de génération possède un délai utile, un budget et un fournisseur alternatif qualifié dans sa conception. Nombres, modèles et conduite du dialogue ne sont pas fixés ici.

| Étape | Application de la procédure |
|---|---|
| Examiner les reprises | SDK, HTTP et job tentant chacun trois fois en enveloppant le précédent peuvent produire 27 appels. Inventorier/allouer un budget commun plutôt qu'additionner trois réglages isolés. |
| Recevoir quota/incident | Appliquer classification, attente bornée et concurrence par capacité/compte ; une reprise du travail ne remet pas le budget à zéro. |
| Interrompre un flux | Fermer les ressources locales ; conserver seulement la preuve/diagnostic admis. Distinguer contenu déjà consommé et absence de réponse, sans prétendre annuler consommation/coût distant. |
| Examiner une bascule | Vérifier résultat possible du premier appel, compatibilité et données du second ; appliquer la conduite prévue pour flux partiel/résultat inconnu, avec R15/R16 pour le dialogue. |
| Recevoir une réponse tardive | Rapprocher cette réponse au travail initial ; ne pas la publier comme nouvelle réponse après remplacement ou annulation sans son contrat. |

Autre effet du même protocole : si un fichier d'export distant est finalisé puis que la preuve SQL échoue, reprendre avec son identité et vérifier référence, contenu/finalisation avant enregistrement ; ne pas créer automatiquement un autre fichier. Une absence de garantie de recherche/déduplication reste une limite explicite. R14 possède format/stockage et R10 progression ; cette situation ne promet aucune transaction globale fichier/SQL.

## 7. Éprouver et transmettre les garanties

**R17-025 — Distinguer simulation et transport réel.** Utiliser `Http::fake()`, réponses séquencées et assertions de requêtes pour contenu, en-têtes autorisés, mapping et reprises ; activer `Http::preventStrayRequests()` pour ce transport dans les tests simulés. Un SDK utilisant son propre client n'est pas couvert automatiquement : raccorder ses moyens de simulation et de blocage. Une réponse simulée n'exerce pas DNS, TLS, délais, streaming ou garantie distante ; qualifier ces propriétés avec un serveur contrôlé ou environnement fournisseur explicitement isolé. Aucun appel réel payant ni effet de production accidentel en suite ordinaire.

**R17-026 — Exercer les fenêtres de panne et les courses.** Selon l'effet : succès distant puis réponse perdue, panne SQL après effet, doublon concurrent, même clé/contenu différent, protection expirée, annulation et réponse tardive. Pour webhooks : signature/corps, mauvais compte, réception avant acquittement, répétition, événements distincts de même effet, contenu contradictoire, désordre et les deux ordres notification/réponse. Piloter les entrelacements observables R07 et relire effet/preuve/suivi, pas seulement statut HTTP. Centraliser les tests du mécanisme commun en régression, puis compléter par raccordements et particularités utiles ; ne pas imposer chaque scénario à toutes les lectures.

**R17-027 — Vérifier limites, isolation et compatibilité.** Exercer budget multi-niveaux, saturation/quota, configuration invalide, réponse mal formée et flux sans progression/interrompu. Tester deux contextes distincts sur un processus réutilisé, en couvrant les changements de compte et de périmètre applicables, après succès et erreur, ainsi qu'anciennes références/versions et rotation nécessaire. Inspecter données réellement émises et diagnostics R09. Qualifier sur l'offre/API retenue délais, coûts, recherche, idempotence et traitement des données ; les mocks seuls ne prouvent pas ces garanties. R19 possède indicateurs/alertes et R21 les exercices d'exploitation.

**R17-028 — Transmettre le contrat et ses limites.** Conserver auprès de l'intégration propriétaire configuration utile sans secrets, preuve attendue, identité/portée, garanties distantes, issues, reprises, réception, compatibilité et procédure d'incident. Réutiliser contrat/tests/documentation existants sans fiche obligatoire par appel. Rendre explicites données de qualification, tests exécutés, limites et travaux restant à réaliser ; actualiser les consommateurs affectés. Une exigence ou capacité produit différente demande sa décision de conception, pas un assouplissement silencieux du protocole.

**Références :** [client HTTP Laravel](https://laravel.com/framework/docs/13.x/http-client), [configuration mise en cache](https://laravel.com/framework/docs/13.x/configuration#configuration-caching), [implémentation du client](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Http/Client/PendingRequest.php), [prévention SSRF OWASP](https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.md). [Idempotence Stripe](https://docs.stripe.com/api/idempotent_requests) et [webhooks Stripe](https://docs.stripe.com/webhooks) illustrent des garanties propres à un fournisseur, sans le sélectionner ni les généraliser. Vérifier versions et offre effectives avant implémentation ; aucun test applicatif ni mécanisme installé n'est revendiqué par ces illustrations.
