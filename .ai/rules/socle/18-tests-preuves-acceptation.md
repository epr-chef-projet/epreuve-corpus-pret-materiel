# R18 — Tests, preuves et acceptation

**Version 1.2 — précision sur les tests locaux validée ; applicabilité à valider ; fond v1.0 validé.** [Index des règles](../index.md#r18).

**Lire pour :** créer, auditer ou corriger un comportement, invariant, contrat ou frontière ; préparer ses tests, examiner une régression ou qualifier une recette. **Procédure :** exigence → risque → résultat observable → niveau probant → données/dépendances → exécution → écarts → acceptation.

Les prescriptions `R18-001` à `R18-025` sont obligatoires, sauf mention **Revue** ou **Variante** selon [R01](01-noyau-agent.md). Les [références propriétaires](../index.md#references-conception), contrats et règles concernées possèdent les comportements et critères attendus ; clarifier une exigence nécessaire introuvable. Les exemples ne définissent aucune fonctionnalité ni valeur du produit.

**Applicabilité et base de rédaction :** retrouver les outils retenus et leur statut dans leurs sources propriétaires, par le profil du projet. Pest pour PHP, Vitest + Vue Test Utils pour Vue/TypeScript local et Playwright Test en TypeScript restent la base des illustrations ; leurs passages techniques s'appliquent lorsqu'ils sont utilisés, sans adoption définitive par cette règle. **Réserve provisoire :** qualifier leurs performances en conditions réelles avant de généraliser les suites ; adapter si nécessaire, puis retirer cette réserve après choix définitif dans la source propriétaire et actualiser les exemples. Garder décision et résultats dans le suivi de qualification R20. Niveaux, assertions, isolation et résultats observables restent à respecter avec les outils effectivement retenus.

**Frontières :** [R02](02-architecture-modules.md) possède rangement/dépendances ; les règles thématiques gardent leurs garanties. R18 sélectionne et organise leurs preuves, sans recopier leurs matrices. [R09](09-confidentialite-cycle-donnees.md) possède les données/diagnostics, [R19](19-performance-observabilite.md) les mesures, [R20](20-environnement-dependances-qualite.md) les versions/configurations/commandes, [R21](21-livraison-exploitation-reprise.md) la livraison et [R22](../index.md#r22) les erreurs. Consulter seulement les propriétaires affectés, en respectant leur statut ; aucun renvoi ne déclare un contrôle installé.

## 1. Préparer les preuves

**R18-001 — Partir du changement et de ses sources.** Identifier exigence, responsabilité, consommateurs et état initial ; préciser résultat attendu et conditions où il doit être refusé, différé ou déclaré incertain. Pour une correction, suivre le défaut existant avant de modifier son propriétaire. Retrouver les critères dans leurs sources, sans inventer permissions, résolution de conflit ou seuil pour rendre le test possible.

**R18-002 — Examiner les risques pertinents.** Relever nominal, entrées invalides, accès refusé, limites, erreurs et effets interdits selon le changement. Compléter lorsque concernés : répétition, concurrence, interruption, anciennes données/versions, changement de contexte et dépendances indisponibles. Réutiliser les garanties des propriétaires ; aucune matrice universelle à remplir pour toute modification.

**R18-003 — Relier risque et preuve.** Désigner résultat observable, niveau de test et dépendances nécessaires pour chaque garantie utile. Un contrôle peut couvrir plusieurs cas, sans masquer leurs différences. Pour un bug, ajouter une régression reproduisant le défaut et vérifier son échec sur l'état précédent lorsque réalisable ; expliquer la limite sinon. Réutiliser tests et supports existants, sans fiche obligatoire par test ni assertion artificielle pour une retouche sans comportement.

## 2. Choisir le niveau approprié

**R18-004 — Choisir selon la garantie.** Employer le niveau le moins coûteux capable de détecter la régression, puis compléter les raccordements nécessaires :

| Niveau | Ce qu'il permet de vérifier |
| --- | --- |
| Unitaire | Décision/calcul isolé ; démarrer Laravel seulement si une dépendance le requiert. |
| Intégration | Interaction avec base, service ou adaptateur réel nécessaire au mécanisme. |
| Entrée applicative (`Feature`) | Validation, middleware, autorisation, orchestration et réponse du point d'entrée. |
| Contrat | Format, conversion, sérialisation et compatibilité à la frontière réellement consommée. |
| Architecture | Emplacements, imports, dépendances et frontières automatisables. |
| Composant/composable | Interactions, émissions, état réactif et nettoyage local Vue/TypeScript. |
| Parcours navigateur | Raccordement depuis l'interface jusqu'au résultat visible, avec les dépendances utiles. |

Ces garanties peuvent se recouper ; un test de contrat n'impose pas un dossier supplémentaire. Ni dossier `Unit` ni navigateur ouvert ne prouve, à lui seul, le niveau réellement exercé. Ne pas répéter chaque variante de calcul dans un parcours complet.

**R18-005 — Placer les preuves auprès du propriétaire.** Suivre R02-021 : tests PHP par module/sous-domaine/usage dans `tests/Unit`, `Integration`, `Feature` ou `Architecture` ; tests locaux TS/Vue en `*.spec.ts` voisins du code. **Avec Playwright Test :** placer les parcours dans `tests/Browser/<parcours>/*.spec.ts`. Nommer fichiers/scénarios en anglais par comportement. Centraliser les preuves d'un mécanisme commun, compléter les raccordements particuliers chez ses consommateurs ; éviter un second classement concurrent.

## 3. Écrire des tests probants

**R18-006 — Rendre le scénario lisible.** Préparer les seules données/préconditions utiles, déclencher l'entrée ou l'opération concernée, puis constater l'issue. Distinguer action du test et résultat attendu. Employer des valeurs discriminantes : acteur autorisé/refusé, ancien/nouveau contexte, valeur initiale/finale ; une fixture dont les valeurs coïncident peut cacher un mauvais raccordement.

**R18-007 — Observer résultat et effets.** Vérifier données effectivement persistées, sortie publique et absence d'effet interdit lorsque requis. Un statut HTTP, un objet Eloquent modifié en mémoire, un bouton désactivé ou une assertion de dispatch ne suffit pas pour un engagement durable. Conserver l'état avant/après selon le scénario, et relire par la frontière appropriée ; vérifier une projection trop tôt peut seulement retrouver son cache local.

**R18-008 — Garder un attendu indépendant.** Utiliser valeurs ou propriétés tirées du contrat, pas le même calcul/helper métier pour produire l'attendu. Privilégier comportement observable et contrat public plutôt que méthode privée ou nombre arbitraire d'appels. Une assertion d'appel reste pertinente si cet appel est précisément l'effet garanti. **Revue :** un défaut plausible dans le mécanisme visé doit faire échouer une assertion utile ; éviter tests miroirs, succès sans assertion et snapshots massifs approuvés sans examen.

## 4. Maîtriser données et dépendances

**R18-009 — Construire un état représentatif et admissible.** Employer factories/builders et données synthétiques par défaut ; initialiser explicitement les attributs décisifs, sans dépendre d'un défaut implicite. Choisir cas limites et volumes selon le risque, pas un export général de données réelles. Utilisation de données réelles, corpus, captures, traces, emails et diagnostics suit R09, y compris en recette et chez les fournisseurs.

**R18-010 — Isoler et nettoyer les ressources.** Nommer le propriétaire des bases, clés cache/Redis, files, fichiers, comptes et sessions de test ; isoler aussi les processus parallèles. Garantir état initial et nettoyage, y compris après exception. Les suites ne ciblent pas une ressource de production ; un profil réel externe utilise les ressources d'essai prévues. Pour des échanges entre processus, préparer des données committées visibles ; éviter une transaction englobante masquant commit, garde transactionnelle ou callback. R05/R07/R20 définissent schéma, connexions et préparation appropriés.

En développement local, les tests habituels utilisent normalement l’installation existante du développeur. Lorsqu’ils utilisent une base de données, ils visent une base réservée aux tests, préparée par les migrations du projet. Les autres ressources utilisées par les tests sont également séparées de celles de l’application locale, ou simulées lorsque cela convient à la vérification attendue. Cette séparation n’impose ni une seconde copie du code ni un nouvel ensemble de conteneurs. Un environnement supplémentaire répond à un besoin identifié, par exemple vérifier une installation depuis zéro ; il n’est pas un préalable général au lancement des tests.

**R18-011 — Simuler une frontière identifiée.** Indiquer ce que le fake remplace et ce qu'il laisse réellement s'exécuter. Utiliser les mécanismes Laravel adaptés (`Queue::fake`, `Event::fake`, `Http::fake`, etc.) ou adaptateurs substituables ; un événement simulé n'exécute pas automatiquement ses listeners. Un mock du composable testé n'éprouve pas sa logique. Bloquer les appels externes non prévus dans la suite ordinaire, y compris ceux des clients SDK autonomes ; un fake HTTP Laravel n'intercepte pas tout client réseau.

**R18-012 — Piloter le temps et les aléas utiles.** Fixer horloge et génération de données lorsque l'attendu en dépend, restaurer timers/mocks après usage et conserver la graine utile en cas d'échec aléatoire. Contrôler résolution/rejet des promesses et attendre les mises à jour Vue nécessaires. Utiliser signaux observables avec attente bornée ; une pause arbitraire ne prouve pas qu'un travail est terminé. Les timers simulés ne prouvent pas les échéances effectives d'un worker ou fournisseur.

## 5. Vérifier les frontières sensibles

**R18-013 — Exercer la base et les accès effectifs.** Pour types, contraintes, transactions, RLS et garanties spécifiques, utiliser PostgreSQL avec le rôle applicatif approprié et le contexte réel R05/R07/R08. Garder middleware/policies actifs dans les tests de leur raccordement ; une entrée HTTP ne prouve pas automatiquement l'entrée console/job/IA. Couvrir les chemins affectés, dont refus sans mutation ni fuite. SQLite, rôle privilégié ou repository mocké ne démontre pas ces garanties. Une migration sur données existantes exige un état antérieur représentatif, pas seulement une installation vide.

**R18-014 — Provoquer la concurrence concernée.** Utiliser connexions/processus réellement indépendants, fixtures visibles et barrières observables bornées ; exercer les ordres décisifs selon R07. Vérifier état persistant, preuves d'opération et issues rendues. Même méthode appelée deux fois successivement ne constitue pas une course. Lancer plusieurs tests en parallèle n'organise pas non plus l'entrelacement interne d'un scénario concurrent.

**R18-015 — Vérifier les chaînes différées et externes.** Séparer intention envoyée, traitement exécuté et résultat retrouvé. Pour sérialisation, commit extérieur, file/worker, transport temps réel, reprises ou protocole fournisseur, compléter les simulations par les chaînes réelles pertinentes R10/R11/R17 ; traverser Redis ou Reverb/Echo lorsqu'ils sont retenus. Provoquer les fenêtres de panne utiles et constater la récupération attendue ; `sync` ne démontre pas une file durable. Les essais externes sont explicites, bornés et isolés, avec budgets et effets prévus ; aucune multiplication d'appels payants par défaut.

**R18-016 — Éprouver contrats, interface et architecture.** Vérifier représentation finale, consommateurs et compatibilité utile R04 ; type généré et build seuls ne prouvent pas la sérialisation. Exercer composants/composables réels, transitions et nettoyage R12/R13 avec les outils retenus, dont Vitest/Vue Test Utils lorsqu'ils sont utilisés. **Avec Playwright :** utiliser interactions accessibles et assertions qui attendent le résultat prévu. Garder des parcours traversant les raccordements réels : une réponse réseau stubée limite la preuve frontend. Les contrôles d'imports R02 sont complétés par revue/tests des accès implicites ou dynamiques qu'ils ne détectent pas.

**R18-017 — Séparer évaluations IA, médias et charge.** Tester décisions/actions avec doubles contrôlés, puis évaluer la combinaison réelle versionnée sur les critères R15 : outils, résultats, refus et suites autorisées, sans figer une phrase quand seule sa signification est exigée. Identifier répétitions, méthode d'évaluation et limites du jugement automatique. Pour permissions, audio, veille, rendu ou accessibilité matérielle, compléter par les appareils/conditions pertinents R13/R16. Sélectionner les preuves de volume/charge selon le risque ; R19 possède protocole et mesures, les sources de conception leurs objectifs. Aucun résultat simulé ne qualifie modèle, matériel ou capacité de production.

## 6. Garder des suites fiables et efficaces

**R18-018 — Réduire le coût sans retirer la preuve.** Ne démarrer application, base ou navigateur que lorsque la garantie le nécessite. Limiter fixtures et préparations coûteuses, réutiliser les supports compatibles sans état partagé résiduel, paramétrer les variantes utiles. Profiler les tests lents et examiner les doublons selon la garantie ajoutée ; ne pas supprimer une preuve de raccordement parce qu'une unité couvre déjà son calcul. Paralléliser seulement après isolation des ressources, avec une concurrence adaptée aux moyens disponibles ; ne pas remplacer une dépendance probante par une simulation pour obtenir un meilleur temps.

**R18-019 — Sélectionner et rapporter la portée réelle.** Pendant l'itération, exécuter les vérifications pertinentes pour le changement et ses dépendances ; aux validations prévues, exécuter l'ensemble requis par R01/R20/R21. Distinguer suite complète, sous-ensemble, résultat réutilisé et contrôle avec couverture ; un filtrage n'est pas une exécution plus rapide du même ensemble. Toute modification invalidant une preuve déclenche sa reprise, sans relancer mécaniquement les contrôles encore valables.

**R18-020 — Traiter les trous et l'instabilité.** Utiliser couverture pour rechercher des branches/risques non éprouvés, sans quota global ni promesse par nombre de tests. **Revue :** mutation ciblée pour vérifier qu'une assertion détecte un défaut plausible ; examiner les mutations équivalentes plutôt qu'imposer un score universel. Rechercher la cause des échecs intermittents, conserver le diagnostic admis et ajouter une régression pertinente. Une quarantaine/désactivation exige justification et suivi selon R01, sans déclarer vérifiée la garantie laissée ouverte ni masquer les échecs par des retries.

## 7. Réaliser la recette réelle

**R18-021 — Préparer et parcourir les usages.** Dès les premiers parcours utilisables, choisir exigences/scénarios, état initial, appareils et conditions représentatifs, avec données/effets autorisés. Parcourir le chemin utile, examiner compréhension, retours d'état/erreur, accessibilité et confort selon les critères applicables. Reprendre selon changements et risques ; aucune recette intégrale à chaque retouche ni équipe QA imposée. Une démonstration nominale ne couvre pas toutes les conditions retenues.

**R18-022 — Compléter les régressions par l'exploration.** Un agent navigateur peut explorer, reproduire et diagnostiquer ; conserver les faits observés et résultats vérifiables plutôt qu'une simple affirmation de succès. Garder les essais humains nécessaires à l'expérience et aux propriétés réelles ; pilotage automatisé ou émulation ne les qualifie pas seul. Transformer les constats reproductibles utiles en tests versionnés au niveau adéquat ; une exploration ponctuelle ne remplace pas la régression répétable.

**R18-023 — Classer et traiter les écarts.** Distinguer défaut d'implémentation, environnement, instabilité du test, limite de preuve et exigence manquante/inadaptée. Corriger le propriétaire concerné ou faire préciser la conception ; ne pas ajuster silencieusement l'attendu pour faire passer le code. Relancer les preuves affectées et reprendre la recette nécessaire. Un écart d'usage peut révéler un besoin absent des tests sans contredire leurs résultats.

## 8. Qualifier l'acceptation

**R18-024 — Rendre le résultat retrouvable.** Dans les supports existants, identifier contrôle/scénario, révision ou état du travail, environnement/conditions utiles, portée, résultat et écarts. Distinguer prévu, exécuté, réussi, échoué et indisponible R01 ; conserver seulement diagnostics autorisés R09. Les commandes reproductibles et leurs suites sont raccordées à R20/R21, sans compte rendu obligatoire par assertion ni déclaration de CI installée.

**R18-025 — Conclure selon les preuves disponibles.** Vérifier critères applicables, résultats et raccordements affectés, en précisant ce qui reste seulement relu, simulé ou non exécuté. Une preuve essentielle manquante impose remise à compléter ou arbitrage selon R01 ; un test réussi à un niveau inférieur ne la remplace pas. Actualiser tests/contrats et repères propriétaires du changement ; distinguer rédaction terminée, revue et validation du contenu lorsque le livrable est documentaire.

## Illustrations — Répartir les preuves sans refaire l'application

Extraits documentaires : outils/configuration et code illustré par les autres règles sont des prérequis, non une application installée ici. Les arborescences ci-dessous situent tous les fichiers détaillés ; aucune route, fixture ou méthode de support non définie n'est supposée exécutable.

### A — Une entrée de cuisson n'est pas toute la modification

Reprendre les contrats de [R04](04-contrats-dto-validation.md) et le métier de [R03](03-php-laravel-metier.md) : `ChangeCookingInputFactory::create(payload, restaurantId, mealId, orderItemId)`, `Cooking::Rare` et `ChangeCookingAction` retournant `void`. Hypothèse illustrative : article modifiable avant acceptation ; après acceptation, refus sans modification.

```text
app/Modules/Dining/Orders/
├── Enums/Cooking.php
└── UseCases/ChangeCooking/
    ├── ChangeCookingInput.php
    ├── ChangeCookingInputFactory.php
    └── ChangeCookingAction.php
tests/
├── Integration/Modules/Dining/Orders/ChangeCooking/ChangeCookingInputTest.php
├── Feature/Modules/Dining/Orders/ChangeCooking/ChangeCookingTest.php
└── Browser/Guest/change-cooking.spec.ts
```

Dans `ChangeCookingInputTest.php`, configuration Pest liée au `Tests\TestCase` Laravel pour le validateur. Les identifiants abrégés vérifient ici la provenance du contexte ; leur format est contrôlé par les adaptateurs R04, hors de cette factory.

```php
declare(strict_types=1);

use App\Modules\Dining\Orders\Enums\Cooking;
use App\Modules\Dining\Orders\UseCases\ChangeCooking\ChangeCookingInputFactory;
use Illuminate\Validation\ValidationException;

test('keeps the server context in a validated input', function () {
    $input = (new ChangeCookingInputFactory())->create(
        ['cooking' => 'rare'], 'restaurant-a', 'meal-a', 'item-a',
    );

    expect($input->cooking)->toBe(Cooking::Rare)
        ->and($input->restaurantId)->toBe('restaurant-a')
        ->and($input->mealId)->toBe('meal-a')
        ->and($input->orderItemId)->toBe('item-a');
});

test('rejects invalid cooking or extra fields', function (array $payload) {
    expect(fn () => (new ChangeCookingInputFactory())->create(
        $payload, 'restaurant-a', 'meal-a', 'item-a',
    ))->toThrow(ValidationException::class);
})->with([
    'unknown cooking' => [['cooking' => 'invalid']],
    'extra context' => [['cooking' => 'rare', 'restaurantId' => 'restaurant-b']],
]);
```

Cette preuve valide la frontière d'entrée, pas l'autorisation, la sauvegarde ou le parcours IA. Compléments selon l'impact :

| Frontière | État/action/attendu |
| --- | --- |
| Service isolé | Modèle avec `id`, `accepted_at`, `cooking` et cast explicités → décision → nouvelle valeur, ou exception métier et valeur inchangée. Cela reste une preuve en mémoire. |
| Entrée et base | Fixture committée et contexte autorisé → entrée réelle de `ChangeCookingTest.php`, middleware/handler actifs → réponse du protocole et relecture PostgreSQL. Pour refus/entrée invalide, vérifier absence d'écriture et d'effets interdits. |
| Composant local | Saisie/annulation, émission et erreur dans le `*.spec.ts` voisin → brouillon/props respectés et issue utilisable. |
| Parcours Playwright | Fixture isolée, interface réelle, locale explicite → choix/confirmation du parcours → retour visible et valeur acquise après nouvelle lecture. Préparation des données et contrat de page à définir au propriétaire avant écriture du test. |

Les variantes de validation n'imposent pas chacune un parcours navigateur. Ne pas désactiver une policy pour faciliter le nominal ni attendre une valeur de retour que l'action `void` ne fournit pas.

### B — Deux connexions doivent réellement se rencontrer

Reprendre la course modification/acceptation de [R07](07-transactions-concurrence-etats.md) dans `tests/Integration/Modules/Dining/Orders/ChangeCooking/ChangeCookingConcurrencyTest.php`. Préconditions : données committées, deux connexions indépendantes, contexte et rôle runtime appropriés sur chacune, mécanisme de barrières observables bornées, nettoyage assuré. Pas de transaction de test englobante masquant le commit extérieur.

| Entrelacement piloté | Vérification après clôture des deux opérations |
| --- | --- |
| Modification protège/lit d'abord ; acceptation atteint son accès concurrent ; modification commit puis acceptation continue. | Cuisson acquise et acceptation cohérentes avec cet ordre ; état relu depuis une connexion autorisée. |
| Acceptation protège/lit d'abord ; modification atteint son accès concurrent ; acceptation commit puis modification reprend. | Refus de modification sur l'état accepté, cuisson inchangée et aucun effet de succès interdit. |
| Modification écrit puis rollback avant de libérer l'autre opération. | Aucun changement durable de cuisson ; le concurrent décide depuis l'état réellement disponible. |

Le harnais établit que le second participant a atteint son point concurrent ; il borne attente/échec et termine les participants avant nettoyage. Il ne se contente pas de lancer deux appels ou d'espérer une collision avec `sleep`. Cette illustration décrit les preuves du protocole R07, sans réimplémenter son mécanisme transactionnel.

### C — A revient, mais son ancien résultat reste périmé

Reprendre [R12, illustration C](12-vue-typescript-inertia.md#c--pinia-partag%C3%A9-et-layout-persistant) : `WorkboardData = { openTicketCount: number }`, vraies actions du store et watcher synchrone du composable. Types générés conservés, aucune copie manuelle du contrat.

```text
resources/js/modules/dining/orders/workboard/
├── contracts/workboard.ts
├── stores/useWorkboardStore.ts
└── composables/
    ├── useWorkboardContext.ts
    └── useWorkboardContext.spec.ts
```

Dans `useWorkboardContext.spec.ts`, le loader est une doublure volontairement insensible à l'abort : la preuve doit exercer la génération même si l'annulation réseau ne suffit pas. Le runner et le typage doivent prendre en charge `Promise.withResolvers` (ES2024), ou fournir un helper de test équivalent qualifié.

```ts
import { flushPromises } from '@vue/test-utils';
import { createPinia, disposePinia, getActivePinia, setActivePinia } from 'pinia';
import { effectScope, ref } from 'vue';
import { expect, test, vi } from 'vitest';
import type { WorkboardData } from '../contracts/workboard';
import { useWorkboardContext } from './useWorkboardContext';

test.each(['resolve', 'reject'] as const)(
    'ignores stale %s and finalization after A returns', async (outcome) => {
        const previousPinia = getActivePinia();
        const testPinia = createPinia();
        setActivePinia(testPinia);
        const scope = effectScope();
        const context = ref('A');
        const first = Promise.withResolvers<WorkboardData>();
        const second = Promise.withResolvers<WorkboardData>();
        const current = Promise.withResolvers<WorkboardData>();
        const load = vi.fn<(id: string, signal: AbortSignal) => Promise<WorkboardData>>()
            .mockReturnValueOnce(first.promise)
            .mockReturnValueOnce(second.promise)
            .mockReturnValueOnce(current.promise);
        const reportFailure = vi.fn<(error: unknown) => void>();

        try {
            const store = scope.run(() => useWorkboardContext(context, load, reportFailure));
            if (!store) throw new Error('Missing test scope');
            context.value = 'B';
            context.value = 'A';
            if (outcome === 'resolve') first.resolve({ openTicketCount: 99 });
            else first.reject(new Error('Stale request failed'));
            second.resolve({ openTicketCount: 88 });
            await flushPromises();

            expect(load.mock.calls.map(([id]) => id)).toEqual(['A', 'B', 'A']);
            expect(store.snapshot).toBeNull();
            expect(store.failed).toBe(false);
            expect(store.loading).toBe(true);
            expect(reportFailure).not.toHaveBeenCalled();

            current.resolve({ openTicketCount: 3 });
            await flushPromises();
            expect(store.snapshot).toEqual({ openTicketCount: 3 });
            expect(store.loading).toBe(false);
        } finally {
            scope.stop();
            disposePinia(testPinia);
            setActivePinia(previousPinia);
        }
    },
);
```

Le test exerce succès/rejet et finalisation périmés pendant A2 ; retirer la garde de génération doit rendre observable un état incorrect. Il ne prouve ni transport réel ni comportement d'un téléphone après veille.

**Recette complémentaire :** choisir le parcours pertinent avec accès valide, données fictives et conditions représentatives ; interrompre le réseau ou passer en veille, revenir, puis vérifier état affiché, résultat d'opération et possibilité de poursuivre selon la conception. Agent/humain consigne état testé, appareil/conditions, action et observation. Si le résultat est correct mais l'attente incompréhensible, traiter un écart d'usage avec R13 ; si une ancienne réponse écrase l'état, conserver une régression comme ci-dessus et vérifier aussi le raccordement réel R11. Toute issue incertaine suit son contrat, sans nouvelle mutation automatique.

**Repères techniques vérifiés le 2 octobre 2026 :** [tests Laravel](https://laravel.com/framework/docs/13.x/testing), [tests de base](https://laravel.com/framework/docs/13.x/database-testing), [Pest](https://pestphp.com/docs/writing-tests), [Vue Test Utils et asynchronisme](https://test-utils.vuejs.org/guide/advanced/async-suspense.html), [nettoyage Pinia](https://pinia.vuejs.org/api/pinia/functions/disposePinia.html), [Vitest et doubles](https://vitest.dev/guide/mocking), [assertions Playwright](https://playwright.dev/docs/test-assertions). Vérifier la documentation des versions qualifiées ; aucun extrait compilé ou test applicatif exécuté par cette rédaction.
