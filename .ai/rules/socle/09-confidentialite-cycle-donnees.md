# R09 — Confidentialité et cycle de vie des données

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r09).

**Lire pour :** créer, auditer ou corriger une collecte, transformation, transmission, conservation, suppression ou un diagnostic ; appliquer une évolution des exigences de données. **Repères :** usages dans `app/Modules/`, raccordements dans `bootstrap/` et `config/`, mécanismes communs dans `app/Infrastructure/`, état frontend dans `resources/js/`, selon [R02](02-architecture-modules.md).

Les prescriptions `R09-001` à `R09-023` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) décident des données, finalités, destinataires, conditions et durées. R09 explique comment les appliquer ; « politique de données » désigne ces exigences, pas une Laravel Policy d'autorisation.

**Procédure :** exigence → traitement et copies → représentation de chaque destination → cycle de vie → preuves. Appliquer le même parcours à l'existant, en relevant les écarts et la transition demandée. [R04](04-contrats-dto-validation.md) possède les contrats, [R08](08-securite-identites-isolation.md) les accès, [R22](../index.md#r22) les erreurs et [R19](19-performance-observabilite.md) l'instrumentation. Consulter les compléments selon l'impact et leur statut ; aucun renvoi à une règle future ne prouve un mécanisme déjà installé.

## 1. Retrouver et traduire l'exigence applicable

**R09-001 — Partir de la référence propriétaire.** Identifier source, statut et portée de l'exigence. Pour le flux concerné, retrouver information utilisée, finalité, propriétaire, destinataires/supports, transformations, conditions de conservation et fin de vie. Distinguer durée indéfinie faute de décision et absence d'expiration explicitement décidée. Réutiliser les contrats et la documentation de l'usage ; compléter au plus petit propriétaire utile selon R02, sans imposer une fiche par champ ni un registre parallèle aux spécifications.

**R09-002 — Faire préciser ce qui manque.** Après recherche ciblée dans les sources applicables, si une exigence reste absente, ambiguë ou contradictoire, exposer la question et ses conséquences : l'agent interroge l'utilisateur, le développeur son responsable habilité. Proposer les options et une recommandation lorsque nécessaire. Consigner la réponse dans la référence propriétaire selon R01, puis l'appliquer ; ne pas inventer durée, destinataire ou purge. Attendre la réponse avant d'implémenter la partie qui en dépend ; poursuivre les travaux indépendants autorisés. Ne pas redemander un accord déjà acquis ni faire passer une dérogation pour une clarification.

**R09-003 — Relever la portée du changement.** En correction, comparer l'exigence précédente et la nouvelle : date d'effet, nouvelles données, traitements en cours, anciennes copies et transition. L'arrêt d'une collecte ne décide pas du sort des données antérieures. **Contrôle :** l'implémentation et les tests se rattachent à une exigence identifiable, sans décision de produit ajoutée dans une règle technique.

## 2. Suivre le traitement réel et ses copies

**R09-004 — Tracer le flux concerné.** Partir de l'entrée réelle et suivre producteurs, transformations, consommateurs et supports, dans le code et la configuration. Examiner selon l'impact : mémoire, navigateur/session, base/cache/index, jobs/événements, fichiers, diagnostics, fournisseurs, dérivés et sauvegardes. Noter où chaque copie est créée, qui la possède et comment elle atteint sa fin de vie. Un contrat de sortie ne décrit pas à lui seul tout le parcours ; limiter l'inventaire au changement et à ses dépendances effectives.

**R09-005 — Parcourir aussi les sorties anormales.** Suivre validation refusée, exception, annulation, interruption et reprise, en plus du succès. Inclure anciennes versions de clients/messages et travaux déjà en attente s'ils peuvent encore écrire ou transmettre les données. **Contrôle :** aucun chemin secondaire concerné n'est couvert seulement par l'affirmation « le DTO est filtré ».

**R09-006 — Vérifier les propriétés du support.** Examiner sa configuration réelle : une valeur annoncée « en mémoire » peut finir dans une session, la persistance Redis ou un diagnostic. Chiffrement, hash, résumé et structuration ne prouvent ni anonymat ni admissibilité pour une destination. Une donnée dérivée suit aussi l'exigence applicable ; qualifier les propriétés démontrées sans les déduire du nom d'un service.

## 3. Construire la représentation de chaque destination

**R09-007 — Sélectionner avant la frontière.** Construire explicitement les champs et transformations admis avant stockage ou transmission. Conserver les informations nécessaires et leur sens ; ne pas supprimer aveuglément tout champ personnel pour faire passer un contrôle d'absence. Utiliser les projections R04 auprès de leur propriétaire R02 ; ne pas sérialiser requête, modèle ou graphe d'objets complet par commodité. L'autorisation de l'opération et la validation du format ne rendent pas tous les champs admissibles partout.

**R09-008 — Nommer la transformation réelle.** Distinguer sélection des champs, masquage, suppression d'un lien, pseudonymisation et anonymisation. Un identifiant de remplacement ou une empreinte peut rester rattachable à une personne ; masquer l'affichage ne retire pas la valeur du stockage. Vérifier la propriété attendue, y compris pour les champs imbriqués et les dérivés, sans qualifier d'anonyme un résultat dont la réidentification reste possible.

**R09-009 — Traiter les contenus non structurés.** Pour texte libre, message natif ou structure inconnue, une liste de noms interdits ou quelques expressions régulières ne démontrent pas un filtrage complet. Préférer une projection explicite des informations nécessaires et maîtrisées. Si la transformation requise ne peut être assurée, ne pas envoyer cette représentation à la destination concernée ; traiter la limite selon R01/R22, sans inventer une donnée de remplacement ni masquer l'échec du traitement.

**R09-010 — Partager selon le contrat.** Réutiliser une transformation seulement si sens, destinataires, exigences et évolution sont réellement communs. Une sélection locale explicite suffit lorsqu'elle n'introduit pas de contrat partagé ; aucune classe ou couche de nettoyage systématique par champ/DTO. **Contrôle :** vérifier chaque représentation émise, pas seulement l'objet source ; les différences utiles entre consommateurs restent visibles.

**Illustration A — Une entrée, deux représentations.** Hypothèse fictive : une demande d'envoi peut conserver le document choisi et l'adresse exacte du destinataire jusqu'à la fin prévue par sa spécification. Le suivi technique permet seulement l'événement, le restaurant et l'identifiant de demande, selon son propre cycle de vie. L'adresse n'y est pas admise. Ces choix illustrent la procédure ; ils ne fixent pas la politique du produit.

```text
app/Modules/Documents/Delivery/
└── UseCases/RequestDelivery/
    ├── RequestDeliveryInput.php
    ├── RequestDeliveryAction.php
    ├── RequestDeliveryRepository.php
    └── Http/RequestDeliveryController.php
bootstrap/app.php               # Raccordement illustré en B
tests/Feature/Modules/Documents/Delivery/RequestDeliveryTest.php
```

Le contrôleur valide `documentId` et `destinationEmail`, construit l'entrée puis appelle l'action. Le contexte restaurant vient du serveur. L'action autorise l'accès au document et l'envoi selon R08 avant l'extrait ci-dessous. Les lectures et écritures SQL appartiennent au repository ; l'extrait montre seulement la séparation des représentations, pas le mécanisme complet d'envoi/reprise.

```php
// RequestDeliveryInput.php — namespace omis ; fichier PHP en strict_types=1.
final readonly class RequestDeliveryInput
{
    public function __construct(
        public string $documentId,
        public string $destinationEmail,
    ) {}
}

// RequestDeliveryAction.php — extrait après autorisation et validation.
// $restaurantId : contexte serveur ; $input : RequestDeliveryInput.
$requestId = $this->repository->create(
    restaurantId: $restaurantId,
    documentId: $input->documentId,
    destinationEmail: $input->destinationEmail,
);

\Illuminate\Support\Facades\Log::channel('delivery')->info(
    'Document delivery requested',
    ['restaurantId' => $restaurantId, 'deliveryRequestId' => $requestId],
);
```

Le repository enregistre les trois valeurs explicites et retourne l'identifiant opaque de la demande. Le canal `delivery` est à configurer selon R19 ; ses enrichissements/collecteurs doivent respecter la même projection. Le log décrit la demande, pas une livraison réussie. Si l'envoi utilise un job, choisir un contrat réduit selon R04/R10 : une référence peut permettre de relire cette demande autorisée, sans recopier l'adresse dans le message. Cette indirection ne supprime pas les obligations sur l'adresse stockée ; ne pas la remplacer lors d'une reprise par l'adresse courante d'un profil modifié.

**Preuves dans `RequestDeliveryTest.php` :** avec `delivery-check@example.invalid`, vérifier que l'écriture contient exactement le document et l'adresse demandés, puis que le message technique et son contexte ont exactement la représentation attendue, sans cette adresse. Contrôler aussi les enrichissements du canal réellement raccordé ; une simulation du logger seule ne les couvre pas. Vérifier la fin de vie de chaque support selon l'hypothèse, sans déduire la durée du diagnostic de celle de la demande.

## 4. Raccorder les automatismes et les fournisseurs

**R09-011 — Examiner les copies natives.** Pour chaque mécanisme activé dans le parcours, inspecter son point de capture sur la version utilisée, le configurer selon la politique puis contrôler le résultat. Cette table guide la recherche ; elle n'exige pas l'installation des outils cités.

| Mécanisme | Copie possible | Point concret à examiner |
| --- | --- | --- |
| Validation HTTP / redirection | Anciennes saisies en session, messages d'erreur | Handler de validation, `dontFlash`, appels `withInput()`/`flash*()` et messages personnalisés. |
| Inertia / Vue | Props partagées, historique, état mémorisé, stockage navigateur | Props réellement envoyées, données de formulaire, `useRemember`, persistances ajoutées et outils de diagnostic. |
| Queue / Horizon | Charge sérialisée, métadonnées, tâches échouées et exceptions | Propriétés du job, sérialisation effective, backend de queue, `failed_jobs`, stockage/rétention Horizon et écouteurs d'échec. |
| Logs / traces | Message natif, contexte, requête SQL, paramètres, causes | Reporters, enrichissements, collecteurs APM, écouteurs SQL, instrumentation SDK et éventuels dumps de débogage. |
| Fichiers / caches / tiers | Temporaires, exports, index, traces ou cache distant | Écriture effective, nettoyage en échec, options de l'adaptateur/SDK et capacités de l'offre fournisseur. |

**R09-012 — Couvrir toute la représentation de diagnostic.** Appliquer la politique aux messages natifs, paramètres, traces, contextes et exceptions précédentes, ainsi qu'aux ajouts automatiques. Conserver le diagnostic original quand sa destination le permet ; sinon produire les informations techniques autorisées, sans transmettre le contenu exclu. Un message filtré n'empêche pas un reporter par défaut d'enregistrer ensuite l'exception originale. R22 possède interception/signalement/restitution ; R19 possède canaux et exploitation. **Contrôle :** inspecter l'enregistrement final, pas uniquement l'appel au logger.

**R09-013 — Vérifier les limites du tiers.** Confronter flux envoyés, traitements, localisation, conservation et suppression effectifs aux exigences applicables. Distinguer options configurées, engagements de l'offre et résultats observables ; une simulation locale ne prouve pas le traitement distant. Consigner l'écart ou la qualification manquante auprès de l'intégration propriétaire et appliquer R01/R17 avant le parcours dépendant ; ne pas déclarer conforme un fournisseur sur une formule commerciale ou son seul chiffrement.

**Illustration B — La validation refuse cette demande.** Hypothèse complémentaire : `destinationEmail` ne doit pas être conservé dans la session, même en cas d'erreur ; `documentId` peut servir à repeupler le formulaire. Une redirection de validation Laravel peut enregistrer les anciennes saisies avant même que l'action de A soit appelée.

Le raccordement suivant s'ajoute au `withExceptions()` existant de `bootstrap/app.php`, sans remplacer les autres réglages. Sur Laravel 13, `dontFlash()` complète les exclusions natives pour cette redirection :

```php
->withExceptions(function (
    \Illuminate\Foundation\Configuration\Exceptions $exceptions,
): void {
    $exceptions->dontFlash(['destinationEmail']);
})
```

Ce choix global suppose que ce nom de champ a partout la même contrainte. Sinon, adapter la réponse de validation de l'entrée concernée avec une sélection explicite, sans changer les autres formulaires. `dontFlash` ne filtre ni messages d'erreur ni appels explicites à `withInput()` :

```php
// Mauvais dans cette hypothèse : copie aussi destinationEmail en session.
return back()->withInput();

// Si une redirection manuelle est nécessaire dans ce contrôleur :
return back()->withInput($request->only(['documentId']));
```

**Contrôle HTTP à ajouter au test de A :** formulaire sous middleware `web`, contexte autorisé, `documentId = 'invalid-id'` envoyé dans le corps sans model binding préalable, refusé par une contrainte UUID de l'exemple, et adresse valide. Effectuer un POST HTML avec URL de retour, laisser le handler normal s'exécuter, puis vérifier avant de suivre la redirection :

```php
// $response : réponse du POST décrit ; assertions Laravel de l'exemple.
$response->assertRedirect();
$response->assertSessionHasErrors(['documentId']);
$response->assertSessionHas('_old_input.documentId', 'invalid-id');
$this->assertArrayNotHasKey('destinationEmail', session()->getOldInput());
```

Vérifier également absence d'écriture métier et absence de l'adresse dans les messages de session. Le contrôle de clé exclut aussi une présence avec valeur `null`. Un test JSON de statut 422 ne couvre pas cette redirection ; suivre la redirection avant l'assertion risquerait de tester après expiration naturelle des saisies flash. Ce raccordement ne nettoie pas les anciennes sessions. Ces contrôles ne prouvent pas l'absence de la valeur dans l'état Vue, un autre collecteur ou un stockage fournisseur : examiner les destinations pertinentes de R09-011 séparément.

## 5. Appliquer le cycle de vie et la transition

**R09-014 — Borner les traitements transitoires.** Identifier début et fin d'utilisation ; libérer ressources, références et fichiers temporaires sur succès, erreur et annulation. Employer les mécanismes de nettoyage adaptés (`finally`, fermeture/annulation de flux, nettoyage du composant) sans compter sur eux après un arrêt brutal : prévoir la reprise ou le nettoyage des restes concernés. Examiner buffers, caches et processus réutilisés. Libérer une variable ne prouve pas l'effacement physique de toute mémoire.

**R09-015 — Implémenter les conditions de conservation.** Raccorder point de départ, durée ou absence décidée, événements de fin et exceptions prévues à une définition propriétaire retrouvable, sans recopier les mêmes valeurs dans plusieurs chemins. Vérifier le traitement périodique ou événementiel réellement exécuté et les effets d'une reprise après interruption. Ne pas ajouter d'expiration universelle ni confondre configuration déclarée et nettoyage effectué.

**R09-016 — Appliquer l'opération demandée.** Distinguer effacement, dissociation, transformation et restriction d'accès selon la référence. Traiter les copies/dérivés concernés ainsi que les liens aux données partagées et engagements encore requis. Désactivation ou suppression logique Eloquent (`SoftDeletes`) ne prouve pas un effacement demandé. R05/R06/R07 possèdent schéma, accès bornés et cohérence concurrente ; R09 ne décide pas quels enregistrements métier doivent disparaître.

**R09-017 — Corriger producteurs et existant ensemble.** Suivre la transition prescrite pour les anciennes données, versions de contrats, jobs en attente et traitements actifs ; éviter qu'un ancien producteur recrée après nettoyage la copie à retirer. Préparer un traitement borné, reprenable et vérifiable avec R05/R06/R07/R10/R21 lorsqu'un rattrapage est nécessaire. Ne pas supprimer silencieusement un engagement en cours pour simplifier sa migration ; faire préciser la transition si elle manque.

**R09-018 — Conserver une preuve proportionnée.** Distinguer action demandée, exécutée, partielle et non vérifiée. Enregistrer seulement les éléments admis pour sa destination : identifiant de traitement, périmètre, résultat ou compteurs selon l'exigence. Ne pas recréer dans la preuve une copie que sa propre politique exclut. Un effacement dans un support ne décide pas à lui seul de la conservation permise dans un audit distinct.

**R09-019 — Prévoir restauration et copies externes.** Identifier les restrictions/nettoyages à réappliquer avant de remettre des données restaurées en circulation, selon la politique et la procédure R21. Une sauvegarde n'est pas nécessairement modifiable ligne par ligne. Pour un tiers ou un fichier déjà remis, distinguer demande de suppression, effet confirmé et limite de contrôle ; ne pas promettre de révoquer une copie hors de portée. Reporter toute incompatibilité dans la décision propriétaire.

**Illustration C — La même politique change.** Hypothèse distincte pour le champ fictif `deliveryLabel` : initialement autorisé dans la demande stockée et certains anciens jobs ; une décision ultérieure exclut ce champ et demande le retrait des copies applicatives antérieures avant reprise des traitements concernés. Les envois restent à honorer. Sa portée doit être explicite, pas déduite par le développeur.

| Surface | Application de cette transition |
| --- | --- |
| Nouvelles entrées | Retirer le champ de la projection persistée et traiter les anciens clients selon R04 ; vérifier que l'adresse et le document requis restent intacts. |
| Ancien job | Adapter l'exécution/migration pour ne plus persister/transmettre le champ ; traiter aussi sa charge stockée et ses copies d'échec selon la portée prescrite. Conserver la demande d'envoi et sa reprise. |
| Copie existante | Retirer le champ dans les supports concernés, avec rattrapage borné, reprenable et preuve admise ; vérifier qu'aucun producteur encore actif ne le réintroduit. |
| Ancienne sauvegarde | Appliquer la procédure de restriction/nettoyage prévue avant réouverture aux traitements concernés, puis vérifier le résultat ; ne pas déclarer les archives effacées par cette seule opération. |

Si une autre exigence autorise au contraire ce champ pour une destination, la même procédure le préserve et teste son intégrité. Aucun changement de R09 n'est nécessaire pour inverser l'hypothèse. Les copies chez un tiers restent soumises à leur portée et aux limites de R09-019.

## 6. Vérifier et entretenir les preuves

**R09-020 — Tester présence et exclusion.** Déduire les assertions de l'exigence : informations attendues intactes, informations exclues absentes, transformations et fin de vie conformes. Utiliser des données synthétiques distinctives ; inspecter les représentations réellement écrites/émises aux destinations concernées. Le test du constructeur d'un DTO ou la recherche d'un seul marqueur ne suffit pas pour les copies automatiques ou les formes dérivées.

**R09-021 — Choisir les parcours probants.** Couvrir succès, refus, erreur, interruption/reprise, échéance et versions coexistantes selon l'impact. En correction, reproduire l'écart concerné, puis prouver sa résolution et la préservation du comportement encore attendu. Tester ancien job et restauration lorsque la transition les affecte. R18 possède l'organisation des tests ; les hypothèses de conception seules ne constituent pas leur exécution.

**R09-022 — Délimiter la preuve.** Rapporter destinations et configuration contrôlées, résultat et limites. Séparer tests de projection, contrôles d'intégration et preuves externes ; une absence dans quelques fichiers locaux n'établit pas l'absence universelle de copies. Qualifier les contrôles non réalisables et les décisions dépendantes selon R01, sans présenter une demande fournisseur comme une suppression attestée.

**R09-023 — Entretenir le parcours propriétaire.** Avec le changement, actualiser références d'exigence, copie/destination affectée, raccordements et tests chez leur propriétaire. En revue, vérifier que deux intervenants peuvent retrouver la même source, les mêmes frontières et les critères des variantes sans deviner. Garder dans le socle la procédure ; laisser données concrètes, durées et inventaire produit dans leur documentation.

**Portée des illustrations :** extraits de conception, sans installation ni tests applicatifs exécutés. Arbre, canal et contrats illustratifs ne constituent pas un inventaire applicatif adopté. Les protections et mécanismes non détaillés gardent les propriétaires indiqués ; vérifier les raccordements sur les versions retenues.

**Repères techniques :** [anciennes saisies Laravel](https://laravel.com/framework/docs/13.x/validation#repopulating-forms), [configuration des exceptions](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Configuration/Exceptions.php), [handler et exclusions de saisies](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Exceptions/Handler.php), [redirections avec saisies](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Http/RedirectResponse.php), [signalement des exceptions](https://laravel.com/framework/docs/13.x/errors#reporting-exceptions), [tâches échouées](https://laravel.com/framework/docs/13.x/queues#dealing-with-failed-jobs). Ces sources décrivent des mécanismes, pas la conformité de notre application.
