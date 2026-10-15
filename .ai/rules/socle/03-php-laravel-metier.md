# R03 — Implémentation du code métier en PHP/Laravel

**Version 1.1 — révision éditoriale à valider ; fond v1.0 validé.** [Index des règles](../index.md#r03).

Point d'entrée : [index](../index.md). Méthode : [R01](01-noyau-agent.md). Architecture et emplacements : [R02](02-architecture-modules.md). **Déclencheurs :** ajout ou modification de PHP métier, cas d'usage, invariant, calcul, dépendance, manipulation Eloquent ou adaptation HTTP/IA/job/console. Les vingt prescriptions `R03-001` à `R03-015` et `R03-024` à `R03-028` sont des obligations, sauf mention **Revue**. Les anciens identifiants `R03-016` à `R03-023`, orientés vers R22, ne sont pas réutilisés. Les contrôles décrits seront à exercer sur l'application ; ils ne sont pas des preuves déjà exécutées.

R03 explique comment construire et faire évoluer un cas d'usage dans l'architecture R02. Elle déclare ses issues, puis renvoie à R22 pour exceptions, interceptions, diagnostics et restitutions. [R04](04-contrats-dto-validation.md) possède les DTO ; [R06](06-requetes-lectures-cache.md) les requêtes ; [R07](07-transactions-concurrence-etats.md) les transactions. Vérifier dans l'index le statut des règles nécessaires : un renvoi ne rend pas un fichier futur applicable. Aucun package, framework métier ou outil de test n'est choisi ici.

## 1. Contrat du cas d'usage

**R03-001 — Définir le contrat avant son implémentation.** À partir des exigences approuvées, expliciter résultat recherché, entrées, préconditions, issues normales, refus bloquants, mutations et effets externes possibles. Rendre visibles les valeurs dont dépend la décision : périmètre autorisé, acteur, état métier, instant pertinent. Une lecture ne déclenche pas silencieusement une mutation. Un commentaire de contrat ou type existant suffit lorsqu'il décrit ces éléments ; aucun dossier documentaire par méthode n'est requis. **Contrôle :** identifier ce qui peut changer, échouer ou rester incertain ; une ambiguïté produit se résout dans sa spécification, pas par un comportement inventé dans le code.

**R03-002 — Appliquer les mêmes invariants à toutes les entrées.** Pour chaque canal qui expose l'opération — HTTP, outil IA, job ou console — appeler le même cas d'usage autorisé ; ne pas créer les autres canaux par principe. Distinguer validité de forme, autorisation et règle métier ; une validation frontend ou une instruction au modèle IA ne garantit pas un invariant. Résoudre les contextes de confiance côté application ; ne pas accepter rôle ou périmètre sur la seule déclaration de l'appelant. Les permissions relèvent de [R08](08-securite-identites-isolation.md), les formes de [R04](04-contrats-dto-validation.md). **Contrôle :** aucune entrée ne contourne un refus en réimplémentant partiellement l'opération.

**R03-003 — Rendre les effets identifiables.** Déclarer ce que l'opération écrit, publie ou demande à un tiers ; distinguer effet acquis, programmé et non confirmé. Ne pas cacher une commande métier dans un accesseur, une sérialisation ou un observer. Les événements Eloquent ne sont pas interdits : une responsabilité qui leur est confiée doit rester identifiable et couvrir les chemins qui peuvent les contourner. Les garanties transactionnelles et asynchrones appartiennent à [R07](07-transactions-concurrence-etats.md), [R10](10-jobs-evenements-fiabilite.md) et [R17](17-integrations-effets-externes.md). **Contrôle :** pas de succès annoncé sur la seule intention d'exécuter un effet.

**R03-025 — Posséder l'invariant au bon niveau.** Identifier ce qui doit toujours être vrai d'après les exigences et contraintes techniques, avec l'état nécessaire pour le vérifier. Une condition métier commune appartient à son plus petit propriétaire partagé, pas à plusieurs contrôleurs. Distinguer invariant et choix de parcours : une étape de validation humaine peut être une issue normale si le contrat le prévoit. Un service valide l'état fourni ; il ne prouve pas seul que cet état n'a pas changé en base. R05/R07 possèdent contraintes et concurrence, R22 le protocole des refus. **Contrôle :** chaque entrée utilise la même décision et aucune valeur calculée par le client ne devient une vérité métier sans vérification.

## 2. Action, service et persistance

**R03-004 — Orchestrer dans l'action.** Appliquer R02-004/005 : l'action séquence les accès et traitements nécessaires, porte la frontière transactionnelle pertinente et expose un contrat de retour explicite ; `void` convient si aucun résultat de données n'est nécessaire et que les échecs restent explicites. Elle n'écrit pas les requêtes ni les règles de calcul à la place de leurs propriétaires. Une lecture peut se passer de service ; l'omission de l'action suit les critères R02-006. **Contrôle :** lire le scénario dans l'action sans y trouver SQL, présentation ou duplication des invariants ; ne pas imposer une transaction à toute lecture.

**R03-005 — Traiter l'état fourni dans le service.** Vérifier, calculer et transformer à partir des données reçues ; Eloquent reste autorisé dans son module. Une mutation en mémoire est explicite ; le service ne sauvegarde, ne rafraîchit et ne charge aucune donnée, même indirectement. Fournir l'instant ou autre donnée variable lorsqu'elle influence la règle. **Contrôle :** les tests du service exercent décisions et mutations sans base ; les chemins réels ne provoquent pas de requête cachée par relation, accesseur ou cast.

**R03-006 — Donner un contrat précis au repository.** Exposer une opération adaptée à son consommateur : projection utile, état absent, protection requise et résultat ; ne pas retourner un builder que l'action doit terminer. Décrire les attributs/relations chargés. Ne pas masquer une défaillance par un résultat vide. Les méthodes intrinsèquement transactionnelles déclarent ce prérequis ; sa garde sur la bonne connexion et les verrous sont possédés par R07. **Contrôle :** SQL localisable, lectures bornées et adaptées avec R06 ; aucune décision de présentation ni chargement universel réutilisé par commodité.

**R03-007 — Maîtriser l'état Eloquent.** Identifier attributs, relations et conversions nécessaires au traitement ; ne pas supposer un chargement effectué par un autre parcours. Interdire les accès SQL implicites dans services et transformations de sortie. Ne pas exposer modèle ou builder hors des frontières autorisées par R02. Un rollback ne restaure pas les attributs déjà mutés en mémoire : invalider ou recharger par la couche autorisée avant réutilisation. **Contrôle :** état partiel, modèle périmé et fin de transaction ne deviennent pas une vérité métier supposée.

**Illustration A — Une mutation, trois responsabilités.** Extraits des méthodes de trois classes distinctes ; imports, constructeurs d'injection et modèles sont omis. Le contrôleur et l'outil IA préparent le même `ChangeCookingInput` (`restaurantId`, `mealId`, `orderItemId`, `cooking`) puis appellent l'action. Le contexte vérifié, les contrôles Gate et la portée RLS sont omis ici et montrés dans l'[illustration A de R08](08-securite-identites-isolation.md#exemple-autorisation) : le DTO seul n'autorise pas cette opération. Le plat de cet exemple admet les valeurs de `Cooking` ; une restriction propre au produit serait vérifiée dans le service, à partir d'une donnée chargée explicitement.

```php
// ChangeCookingAction
public function execute(ChangeCookingInput $input): void
{
    DB::transaction(function () use ($input): void {
        $item = $this->repository->findForUpdate($input);
        $this->service->changeCooking($item, $input->cooking);
        $this->repository->save($item);
    });
}

// ChangeCookingService
public function changeCooking(OrderItem $item, Cooking $cooking): void
{
    if ($item->accepted_at !== null) {
        throw new OrderItemAlreadyAccepted($item->id);
    }

    $item->cooking = $cooking;
}

// ChangeCookingRepository
public function findForUpdate(ChangeCookingInput $input): OrderItem
{
    return OrderItem::query()
        ->where('restaurant_id', $input->restaurantId)
        ->where('meal_id', $input->mealId)
        ->select(['id', 'restaurant_id', 'meal_id', 'accepted_at', 'cooking'])
        ->lockForUpdate()
        ->findOrFail($input->orderItemId);
}

public function save(OrderItem $item): void
{
    $item->save();
}
```

`Cooking` est un enum métier, avec cast Eloquent correspondant. Action et modèle utilisent la même connexion ; le contrat de `findForUpdate()` exige la transaction active (garde et preuves dans R07). Le service transforme le modèle chargé ; le repository seul l'enregistre. `void` suffit ici : l'appelant n'attend aucune donnée de retour, et une interruption n'est pas un succès. La définition et le traitement de `OrderItemAlreadyAccepted` relèvent de R22 ; cet exemple ne reproduit pas sa politique d'erreurs. En production, index, autorisations, RLS et concurrence exigent leurs propres preuves.

## 3. Dépendances et application de SOLID

**R03-008 — Montrer les dépendances réelles.** Injecter les collaborateurs applicatifs nécessaires ; réserver la résolution dynamique par le conteneur à l'assemblage ou à une intégration explicitement justifiée. Les façades Laravel restent utilisables dans leur responsabilité : `DB::transaction` dans l'action, accès Eloquent dans le repository, journalisation à sa frontière désignée. Ne pas lire `request()`, `auth()`, langue ou état global mutable depuis une règle métier. Résoudre la configuration technique aux frontières utiles, sans propager des paramètres sans rapport avec le cas d'usage. **Contrôle :** dépendances compréhensibles et remplaçables sans simuler toute une requête HTTP.

**R03-009 — Justifier les abstractions par un besoin.** Une responsabilité possède ses raisons de changer ; une interface expose les capacités réellement consommées, avec des implémentations respectant les mêmes préconditions, résultats et échecs. Introduire stratégie ou adaptateur lorsqu'une variation identifiée le justifie. Ne pas créer systématiquement interface, repository CRUD générique, classe de base ou wrapper autour d'une API Laravel. **Revue :** comparer la simplicité d'une solution directe et le bénéfice concret de l'abstraction ; une mutualisation ne se justifie pas par la seule ressemblance du code.

**R03-010 — Limiter la durée de vie des états.** Aucun singleton ou cache statique ne conserve implicitement acteur, périmètre d'accès, langue, modèle chargé ou incident entre requêtes/jobs. Distinguer service réutilisable et contexte d'une opération ; initialiser puis libérer ce contexte dans les processus persistants, notamment Horizon. **Contrôle :** deux opérations successives de périmètres distincts ne se transmettent ni données ni droits ni contexte de diagnostic. Les mécanismes précis relèvent de R08/R10/R19/R20.

**R03-026 — Séparer politique métier et mécanisme.** Lorsqu'une règle dépend d'une configuration, fournir les valeurs au service sans y consulter contexte HTTP ou stockage. Leur récupération n'est pas la règle qui les utilise. Un nouvel algorithme peut justifier une stratégie ; une différence de valeurs ne justifie pas à elle seule une nouvelle classe. Fournir l'instant de référence lorsque le temps change le résultat. **Contrôle :** décision reproductible avec état, politique et instant identiques ; aucune horloge cachée, branche métier codée en dur pour un identifiant particulier, interface mécanique ou configurabilité ajoutée sans besoin prévu.

**Illustration B — Une politique testable, sans dépendance ambiante.** Hypothèse de l'exemple : le seuil dépend d'une configuration par établissement. L'action fournit cette politique au service de réservation ; les tailles sont déjà validées comme entiers positifs.

```php
public function requiresStaffReview(
    int $partySize,
    ReservationPolicyData $policy,
): bool {
    return $partySize > $policy->maxAutomaticPartySize;
}
```

Le service ne lit ni `auth()->user()->restaurant`, ni `request()`, ni la base. Une taille égale au seuil ne demande pas de revue selon cette règle ; une taille supérieure en demande une. L'action orchestre également la vérification de capacité et les autres étapes, via leurs responsabilités propriétaires : `false` n'est pas une réservation confirmée. Ce prédicat est une méthode d'une responsabilité existante, pas une obligation de créer un service ou DTO pour chaque condition. Une vraie variation de politique peut être extraite sous un contrat restreint ; les implémentations doivent conserver le sens de ce booléen.

## 4. Discipline PHP et usage de Laravel

**R03-011 — Typer les contrats maîtrisés.** Utiliser `declare(strict_types=1);` dans les fichiers PHP applicatifs maîtrisés et types de paramètres, propriétés et retours compatibles avec le framework. Préciser les structures de collections/tableaux par PHPDoc lorsque PHP ne les exprime pas ; conserver `mixed` seulement à une frontière qui le nécessite, puis valider/réduire le type. Ne pas ajouter d'annotations redondantes ni corriger manuellement vendor ou artefacts régénérés automatiquement pour cette convention. Les squelettes créés par Artisan puis maintenus par l'équipe suivent les conventions applicatives. **Contrôle :** types utiles à l'analyse, sans prétendre que le typage garantit validation métier ou autorisation.

**R03-012 — Représenter les états sans ambiguïté.** Distinguer absence, valeur nulle, zéro, chaîne vide et échec ; choisir explicitement comparaisons et conversions. Employer enums ou types dédiés pour un ensemble métier fini lorsqu'ils rendent les états contrôlables. Éviter paramètres booléens qui sélectionnent des comportements sans nom ; expliciter options et résultats. Montants, dates et DTO relèvent de R04/R05. **Contrôle :** aucun défaut silencieux ne transforme une entrée invalide ou un incident en issue normale.

**R03-013 — Garder l'anglais technique.** Identifiants, clés, valeurs symboliques et messages internes produits par l'application sont en anglais, avec le vocabulaire cohérent de R02. Les services métier ne sélectionnent pas une langue d'affichage. Cette convention ne force pas noms de plats ou données saisies dans une autre langue. Les messages natifs, codes d'erreur et restitutions relèvent de R22. **Contrôle :** nom métier précis et constant, sans traduction qui modifie la décision.

**R03-014 — Choisir les API pour leur sens.** Préférer méthodes PHP/Laravel établies lorsqu'elles expriment précisément l'opération. Examiner comportement, requêtes, allocations et volume parcouru ; une chaîne de collections concise peut charger trop de données. Ne pas réécrire un mécanisme natif sans besoin démontré, ni compacter artificiellement pour réduire le nombre de lignes. R06 possède l'optimisation des lectures, R19 les mesures. **Contrôle :** code lisible, données et travail bornés ; performance justifiée autrement que par un petit jeu d'essai.

**R03-015 — Garder des unités localisables.** Une méthode et une classe conservent une responsabilité nommable ; extraire calcul, politique ou collaboration quand ses raisons de changer deviennent distinctes. Appliquer la segmentation R02 sans quotas de lignes ni helpers triviaux mécaniques. Documenter préconditions non évidentes, mutations et refus attendus des contrats publics ; commenter une raison utile, pas répéter le code. **Contrôle :** retrouver où ajouter une règle et quels consommateurs vérifier sans parcourir une classe généraliste ; aucun faux test ou commentaire ne remplace un contrat manquant.

**Illustration C — Zéro, absence et type.** Dans un calcul, une quantité entière est déjà fournie par un contrat validé ; `null` signifie utiliser la quantité par défaut. `0` est une valeur autorisée dans ce contrat illustratif, pas une règle universelle pour les commandes.

```php
// Incorrect: a valid zero is replaced by the default.
$quantity = $requestedQuantity ?: $defaultQuantity;

// Correct for this contract: only null selects the default.
$quantity = $requestedQuantity ?? $defaultQuantity;
```

Le choix de l'opérateur suit le sens des valeurs. Ni un cast `(int)` ni une valeur par défaut ne remplacent la validation. Une demande partielle où « absent » et « présent avec null » diffèrent exige un contrat qui les distingue selon R04.

## 5. Calculs, coût et évolution

**R03-027 — Composer des étapes cohérentes.** Séparer acquisition des données, décision/calcul et enregistrement. Pour un calcul à plusieurs étapes, rendre l'ordre, les unités, conversions et règles d'arrondi explicites selon R04 ; un résultat intermédiaire reste local sauf besoin réel de contrat. Ajouter une règle dans sa responsabilité plutôt qu'un paramètre de mode qui multiplie les variantes d'une méthode. Une action coordonne les résultats ; elle ne pilote pas un même parcours par des flags cachés. **Contrôle :** expliquer comment ajouter une option sans copier tout le cas d'usage ni réécrire les chemins existants ; garantir même résultat aux entrées équivalentes.

**R03-028 — Examiner le coût du traitement en mémoire.** **Revue :** identifier volumes et travail par élément avant d'accumuler boucles, conversions et collections temporaires. Éviter de rechercher à nouveau les mêmes données dans une boucle ou d'appeler un autre module pour chaque élément lorsqu'un contrat par lot est adapté. Une table de correspondance calculée une fois peut réduire des parcours répétés, au prix de mémoire à évaluer. Ne pas remplacer une requête bornée par un filtrage de toute la table en PHP. **Contrôle :** hypothèses et limites explicites ; R06 possède plans/index/requêtes, R19 les mesures. Aucun serveur plus puissant ne justifie une croissance non examinée.

*Repère de revue :* pour N lignes à rapprocher de M options déjà chargées, parcourir M pour chaque ligne réalise jusqu'à N × M comparaisons. Un index en mémoire par identifiant évite ces recherches répétées ; il ne remplace ni l'autorisation ni le contrôle métier d'une option. Mesurer le compromis si le volume ou la mémoire le justifie, sans imposer cette structure à toute petite collection.

## 6. Preuves métier et articulation

**R03-024 — Vérifier le contrat métier.** Pour l'usage modifié, sélectionner preuves isolées de décisions/calculs, intégration réelle et vérification des entrées selon les risques. Couvrir valeurs limites, transitions et absence d'effet après refus, sans recopier l'algorithme dans le test. Une simulation ne prouve ni persistance réelle ni concurrence. R18 consolide les outils et la politique ; R07 les preuves transactionnelles ; R22 les parcours d'erreur. **Contrôle :** mêmes invariants par toutes les entrées concernées, cas nominaux et limites exercés ; vérifications exécutées, futures et manquantes distinguées selon R01.

**Illustration D — Recette ciblée des exemples métier.**

| Situation | Preuve recherchée |
| --- | --- |
| Demande valide via HTTP et IA | Même invariant et même modification autorisée ; le service ne déclenche pas de SQL. |
| Article déjà accepté | Modification refusée et sauvegarde non exécutée ; le protocole de restitution est vérifié avec R22. |
| Taille du groupe au seuil, puis au-dessus | Revue non requise, puis requise ; aucun des deux résultats ne confirme seul la réservation. |
| Quantité nulle, zéro, positive | Défaut uniquement pour null ; zéro préservé lorsque le contrat l'autorise. |
| Ajout d'une restriction de cuisson | Donnée chargée par le repository, décision ajoutée au service, action stable si le scénario ne change pas. |
| Augmentation du nombre de lignes/options | Coût examiné ; pas de requête cachée ou de recherche complète répétée sans justification. |
| Deux restaurants successifs dans un worker | Contexte, modèles et diagnostics ne se mélangent pas. |

Les exemples illustrent des mécanismes sous des hypothèses explicites ; ils ne définissent pas les fonctionnalités à réaliser et ne constituent pas des preuves exécutées. Consulter R04 pour les contrats, R06/R07 pour la persistance, R22 pour le traitement des erreurs, R19/R20 pour leurs moyens de contrôle, selon les impacts et statuts dans l'index.
