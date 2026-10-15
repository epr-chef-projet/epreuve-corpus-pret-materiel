# R12 — Vue, TypeScript et Inertia

**Version 1.1 — applicabilité à valider ; fond v1.0 validé.** [Index des règles](../index.md#r12).

**Lire pour :** créer, auditer ou corriger une page Vue/Inertia, un composant, un composable, un store ou un échange navigateur. **Procédure :** exigence → responsabilités → contrats/état → interactions → échanges → cycle de vie → preuves. **Repères :** pages dans `resources/js/pages/`, fonctionnalités dans `resources/js/modules/` selon R02.

Les prescriptions `R12-001` à `R12-028` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent comportements, permissions, conservation et résolution des conflits ; clarifier une exigence nécessaire introuvable. Les illustrations ne fixent aucun parcours produit.

**Applicabilité :** ce fichier explique les méthodes Vue/TypeScript/Inertia. Retrouver le choix du partage d'état dans le profil du projet ; les branches Pinia et les exemples qui l'utilisent s'appliquent lorsqu'il est retenu. Responsabilité, autorité des données, réactivité et cycle de vie restent à maîtriser sans imposer l'installation de Pinia.

**Frontières :** [R02](02-architecture-modules.md) possède placement/exports, [R03](03-php-laravel-metier.md) métier, [R04](04-contrats-dto-validation.md) contrats/validation, [R07](07-transactions-concurrence-etats.md) transactions/idempotence, [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) droits/données et [R11](11-temps-reel-synchronisation.md) convergence des vues. Consulter selon impact [R13](13-interface-accessibilite-internationalisation.md), [R14](14-assets-fichiers-stockage.md), [R16](16-voix-avatar-medias.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R20](20-environnement-dependances-qualite.md), [R21](21-livraison-exploitation-reprise.md), [R22](../index.md#r22) pour restitution, assets, médias, tests, mesures, outillage, livraison et erreurs. Respecter leur statut ; aucun mécanisme décrit n'est réputé installé.

## 1. Qualifier l'usage et ses sources d'état

**R12-001 — Retrouver le contrat du parcours.** Identifier exigence, propriétaire, audience, entrées, résultat attendu, contexte d'accès et transitions possibles. Pour une correction, reproduire le défaut et suivre les données/effets existants avant de modifier leur propriétaire. Relever les consommateurs et copies impactés, pas seulement le composant visible. **Contrôle :** emplacement et résultat observables, conditions de validité et exigences manquantes identifiés.

**R12-002 — Désigner une autorité par information.** Classer les données avant de créer des refs ou un store :

| Nature | Propriété et traitement |
| --- | --- |
| Prop ou résultat serveur | Projection reçue ; ne pas en faire une donnée métier modifiable librement. |
| Brouillon | Saisie locale distincte, avant normalisation/validation serveur. |
| État UI | Sélection, ouverture ou chargement, auprès de l'usage. |
| Dérivation | Calcul depuis ses sources, sans copie synchronisée par plusieurs watchers. |
| État partagé | Instance commune avec propriétaire et consommateurs identifiés. |
| Projection synchronisée | Un coordinateur R11 ; les composants consomment son résultat. |

Le serveur décide droits et faits métier. Un store, une validation locale ou un bouton masqué ne constitue pas une protection serveur. **Contrôle :** chaque champ a une source faisant autorité ; aucune deuxième copie indépendante sans besoin et procédure de rapprochement.

**R12-003 — Définir validité et conservation.** Préciser initialisation, durée de validité, remplacement et fin de vie de chaque état. Distinguer identité de ressource, périmètre d'accès et génération locale de contexte ; une même identité retrouvée ne rend pas valide une ancienne opération. Examiner props, stores, brouillons, historique et autres copies selon R09. Ne pas déduire une politique de conservation de leur présence dans un exemple.

## 2. Segmenter et typer l'implémentation

**R12-004 — Placer chaque responsabilité selon R02.** La page assemble ; le composant affiche/interagit ; le composable coordonne une responsabilité réactive ; la fonction pure calcule ; `requests/` adapte le réseau et restitue les données/erreurs du contrat. Le consommateur décide de l'affectation de l'état. Ranger contrats échangés dans `contracts/`, états locaux dans `types/`, stores auprès de leur propriétaire commun ; utiliser les exports limités du module. Ne pas importer une page pour réutiliser sa logique.

**R12-005 — Contrôler le typage réel.** Employer Composition API et `<script setup lang="ts">`, avec TypeScript strict. Consommer les types générés R04 sans copie manuelle ; garder les types locaux si leur sens diffère. Traiter `unknown` aux frontières par les contrôles appropriés au risque, pas par un cast. Interdire `any` implicite et assertions masquant un écart ; justifier une exception localisée avec sa condition de sûreté. Typage statique distinct de validation à l'exécution.

**R12-006 — Exposer props et intentions explicites.** Typer `defineProps` et `defineEmits` ; un événement décrit l'intention ou la nouvelle valeur locale, sans prétendre confirmer une mutation serveur. Passer seulement les données utiles au composant, sans page entière ni magasin universel. Conserver la réactivité par un getter/ref adapté ; ne pas transmettre une valeur déstructurée devenue statique. Les propriétés de props déstructurées et macros récentes dépendent de la version Vue qualifiée. [Props et événements typés](https://vuejs.org/guide/typescript/composition-api.html)

**R12-007 — Extraire selon les raisons de changement.** **Revue :** extraire quand responsabilités, consommateurs ou cycle de vie divergent ; garder un traitement court auprès de son propriétaire si l'extraction n'apporte pas de frontière utile. Ne pas déplacer une vue géante dans un composable géant, créer des wrappers vides ou une abstraction réseau pour chaque appel. Un nombre de lignes seul ne décide pas de la segmentation.

## 3. Maîtriser la réactivité et les brouillons

**R12-008 — Garder les props en lecture.** Ne modifier ni prop ni objet imbriqué reçu. Pour éditer, copier les seuls champs nécessaires ; détacher aussi les structures imbriquées éditables. Une copie initiale ne suit pas automatiquement la prochaine prop. Émettre une intention au propriétaire ou envoyer le brouillon par l'entrée adaptée. **Contrôle :** saisir/annuler localement ne modifie pas la projection reçue.

**R12-009 — Traiter explicitement les nouvelles valeurs.** Définir le traitement des props remplacées, normalisations et réponses concurrentes : nouvelle base, maintien de saisie, rapprochement ou conflit selon le parcours. Examiner la réception du résultat, même si une normalisation retrouve l'ancienne valeur. Ne pas écraser un brouillon modifié ni effacer ses erreurs par un watcher général. Une fin de soumission ne réinitialise pas une nouvelle saisie ou un autre contexte ; associer réponse et intention si l'édition continue. **Contrôle :** actualisation, refus et réponse tardive suivent la stratégie déclarée.

**R12-010 — Dériver sans effet.** Utiliser `computed` pour une valeur calculée réactive ; garder une fonction pure pour un calcul indépendant de la réactivité. Ne pas écrire d'état, appeler le réseau ni modifier le DOM dans le calcul. Éviter les watchers qui recopient une dérivation. **Contrôle :** dépendances et coût lisibles, sans effet à chaque évaluation.

**R12-011 — Posséder les effets.** Utiliser `watch` pour des sources explicites ; `watchEffect` lorsque ses dépendances implicites restent compréhensibles. Celui-ci ne collecte que les lectures avant le premier `await`. Choisir l'exécution initiale et le moment du callback selon le besoin ; ne pas ajouter `deep` ou `flush: 'sync'` par habitude. Enregistrer le nettoyage avant le travail asynchrone. Arrêter les watchers créés hors du scope synchrone du propriétaire. `onWatcherCleanup` exige Vue 3.5+ et un appel synchrone ; le callback `onCleanup` reste une autre primitive à qualifier. [Watchers et nettoyage](https://vuejs.org/guide/essentials/watchers.html)

## 4. Encadrer le partage d'état

**R12-012 — Choisir le plus petit partage utile.**

| Besoin | Choix habituel |
| --- | --- |
| Propre à un composant/parcours | État local et composables. |
| Simple, consommateurs dans un périmètre limité | Instance commune explicite ; `provide/inject` typé si pertinent. |
| Complexe ou transversal entre plusieurs écrans | Store ciblé avec opérations et cycle de vie ; Pinia lorsqu'il est retenu par le projet. |

Justifier par responsabilité, consommateurs, transitions et diagnostic nécessaires ; ni nombre de composants ni un champ scalaire seuls n'imposent Pinia. Ne pas recopier toutes les props dans les stores. Ce partage concerne une application dans un navigateur ; R11 possède la synchronisation entre appareils.

**R12-013 — Rendre le partage natif explicite.** Créer l'instance chez le propriétaire et transmettre la même instance aux consommateurs : des appels séparés à une factory peuvent produire des états indépendants. Exposer lecture et opérations intentionnelles, éventuellement une vue `readonly`. **Si `provide/inject` retenu :** utiliser une `InjectionKey` typée ; l'absence d'un provider obligatoire est une erreur de raccordement, pas une invitation à créer un autre état. Éviter le singleton contenant des données sans périmètre ni reset. [Partage réactif Vue](https://vuejs.org/guide/components/provide-inject.html#working-with-reactivity)

**R12-014 — Définir des stores ciblés.** **Si Pinia est retenu :** utiliser `defineStore`, fonction `use…Store` et identifiant unique qualifié par responsabilité. **Défaut de cette branche :** Setup Store, avec refs pour l'état, computed pour les getters et fonctions pour les opérations. Retourner tout l'état attendu par Pinia sans envelopper ses refs dans `readonly` ; ne pas cacher un état métier dans une closure pour contourner le suivi. Les handles techniques non réactifs peuvent rester internes. Faire passer les mutations des consommateurs par les opérations nommées du propriétaire : Pinia permet les écritures directes, cette discipline relève du code et de sa revue. **Variante :** Option Store si un avantage concret le justifie, avec les mêmes obligations de consommation. [Définition des stores](https://pinia.vuejs.org/core-concepts/)

**R12-015 — Préserver réactivité et remise à zéro.** **Si Pinia est retenu :** utiliser `storeToRefs` pour déstructurer état/getters ; les actions se déstructurent directement. Implémenter un `$reset` complet pour un Setup Store ; l'Option Store possède son mécanisme natif. Nommer le propriétaire qui déclenche la remise à zéro et son contexte. Un reset ne stoppe pas automatiquement lectures/abonnements ; `$dispose` stoppe le scope et retire le store du registre sans supprimer son état de `pinia.state`. Examiner la réutilisation avant de choisir destruction ou reset. [Reset](https://pinia.vuejs.org/core-concepts/state.html#resetting-the-state), [disposal](https://pinia.vuejs.org/api/pinia/interfaces/StoreWithState.html#dispose)

## 5. Échanger avec Laravel

**R12-016 — Choisir le protocole avant le helper.** Employer visites/formulaires Inertia pour les pages associées ; choisir un endpoint ciblé lorsqu'un échange spécifique le justifie, sans transformer chaque interaction en visite. Désigner entrée, réponse, contrôles d'accès et résultat attendu selon R04/R08. Utiliser les mécanismes qualifiés de session et CSRF ; ne pas fabriquer une nouvelle authentification dans le composable.

**R12-017 — Posséder chaque formulaire.** Utiliser `useForm` comme parcours habituel Inertia : valeurs initiales distinctes des props → soumission → erreurs ou résultat serveur → traitement du brouillon suivant R12-009. Une instance porte un formulaire ; isoler erreurs/soumissions de formulaires voisins, avec les mécanismes adaptés tels que les error bags si nécessaires. Respecter redirections et erreurs Inertia, distinctes des statuts/erreurs d'un endpoint JSON. Gérer doubles soumissions et modifications pendant l'envoi selon le contrat ; un bouton désactivé n'assure pas l'idempotence serveur. [Formulaires](https://inertiajs.com/docs/v3/the-basics/forms), [validation](https://inertiajs.com/docs/v3/the-basics/validation)

**R12-018 — Garder les lectures remplaçables cohérentes.** Placer l'appel dans l'adaptateur `requests/` et sa coordination auprès de l'usage. À remplacement : invalider l'ancienne identité d'opération, annuler si possible, puis lancer la nouvelle lecture. Vérifier identité et contexte avant toute affectation dans succès, `catch` et `finally` ; l'ancienne fin ne désactive pas le nouveau chargement. Relier cet écran au coordinateur R11 s'il consomme une projection synchronisée : ce contrôle local ne remplace pas son rapprochement par révision. **Contrôle :** inversion des réponses, erreur tardive et annulation tardive sans écrasement.

**R12-019 — Restituer une issue vérifiée.** Consommer les données/erreurs sûres R04/R22 ; R13 possède affichage et traduction. Intercepter/signaler aux frontières responsables selon R22, sans diagnostic technique dans l'interface/store. Distinguer refus, incident et résultat incertain : annuler l'attente réseau ne prouve pas le rollback d'une mutation. Retrouver son résultat ou reprendre selon R07/R11, sans répétition automatique comme pour une lecture.

## 6. Raccorder et terminer les ressources

**R12-020 — Initialiser une application cohérente.** Dans l'entrée Vue/Inertia, installer les plugins retenus avant leurs consommateurs/montage. **Avec Pinia :** créer une instance par application ; éviter `useStore` exécuté au chargement d'un module avant ce raccordement. Raccorder les pages à leur nom serveur par un motif stable respectant la casse Linux ; garder les imports des fonctionnalités/styles chez leurs propriétaires R02/R14. **Contrôle :** nouvelle page dans la convention résolue en développement et après build sans entrée Vite supplémentaire.

**R12-021 — Renouveler le contexte sans attendre le démontage.** Sur changement pertinent de compte/périmètre/ressource, invalider les opérations anciennes, arrêter les effets obsolètes, réinitialiser les données concernées puis activer le contexte courant. Une navigation ordinaire ne réinitialise pas mécaniquement tout ; suivre les durées de validité déclarées. Dans un layout persistant, observer aussi le contexte. Le retour A → B → A exige une génération renouvelée : égalité d'identifiant, révision métier et identité de requête sont des informations différentes.

**R12-022 — Libérer selon le propriétaire réel.** Enregistrer le nettoyage des requêtes, watchers, écouteurs et temporisations avec `onScopeDispose`, `onUnmounted` ou le cleanup du watcher selon leur durée de vie. Invalider avant abort/retrait. Un consommateur retire ses ressources sans détruire un état/abonnement encore partagé ; fin commune selon R11-022, médias selon R16. **Si `KeepAlive` utilisé :** traiter activation/désactivation par `onActivated`/`onDeactivated` selon la validité déclarée ; désactivation distincte du démontage. [Cycle de vie Vue](https://vuejs.org/guide/built-ins/keep-alive.html#lifecycle-of-cached-instance)

**R12-023 — Raccorder les chargements de props.** Appliquer R04-022 : non chargé, vide, erreur et disponible restent distincts. Selon la version qualifiée, `router.reload({ only: [...] })` recharge partiellement le même composant de page ; `Deferred` coordonne l'attente d'une prop différée, sans faire de son fallback un traitement d'échec. Vérifier validité des props conservées, copies et contexte ; rendu réussi distinct de fraîcheur R11. Éviter les watchers qui bouclent en relançant leur visite. [Rechargements partiels](https://inertiajs.com/docs/v3/data-props/partial-reloads), [props différées](https://inertiajs.com/docs/v3/data-props/deferred-props)

**R12-024 — Examiner les copies et modes d'exécution.** `useRemember` ou une clé de `useForm` peut conserver données/erreurs dans l'historique. Utiliser ces mécanismes seulement suivant les exigences R09, avec clés et invalidation pertinentes ; ni reset Pinia ni absence de localStorage ne supprime ces copies. Vérifier retour arrière et renouvellement de contexte. **Si SSR adopté :** instances isolées par requête et accès DOM/médias côté client, sans singleton utilisateur entre requêtes. Cette clause n'adopte ni SSR ni persistance. [État mémorisé](https://inertiajs.com/docs/v3/data-props/remembering-state), [isolation SSR Vue](https://vuejs.org/guide/scaling-up/ssr.html#cross-request-state-pollution)

## 7. Borner et vérifier le parcours

**R12-025 — Examiner le coût collectif.** **Revue :** compter appels, données téléchargées/réactives, recalculs, observateurs, rendu et ressources retenues sur le parcours. Utiliser des clés stables pour les listes ; borner les données et opérations coûteuses. Évaluer rafales, visites répétées et appareils représentatifs. Ne pas transférer un ensemble non borné pour éviter une lecture ciblée ; une collection complète bornée exige coût, fraîcheur et périmètre justifiés. Pas de cache/bibliothèque de requêtes sans contrat et gain démontrés. Mesures propres à R06/R14/R19.

**R12-026 — Vérifier les SFC indépendamment du build.** Exécuter un contrôle strict des types incluant `*.vue` et les contrats générés, avec l'outil qualifié R20, par exemple `vue-tsc`. Vite transpile sans fournir cette preuve de typage. Vérifier aussi résolution des pages et imports par compilation, puis les comportements par tests ciblés R18. Ne pas confondre ces trois preuves. [Vue et TypeScript](https://vuejs.org/guide/typescript/overview.html)

**R12-027 — Qualifier les mécanismes utilisés.** Avant installation ou changement de dépendance, vérifier versions compatibles Vue/Inertia/Vite/TypeScript et Pinia lorsqu'il est retenu, API et contrats réellement utilisés, mise à jour et contrôles de dépendances R20/R21. Consigner commandes, résultats et limites ; la documentation courante ne sélectionne pas une version. Utiliser le vrai comportement des actions dans les tests qui le vérifient : un store de test dont les actions sont stubées ne prouve ni mutation ni reset. [Tests Pinia, si utilisé](https://pinia.vuejs.org/cookbook/testing.html)

**R12-028 — Apporter les preuves proportionnées.** Sélectionner les cas utiles ci-dessous ; tester résultat et effets interdits plutôt que recopier l'implémentation. Contrôles locaux près de leur propriétaire selon R02, parcours transversaux séparés. Distinguer calcul isolé, composant/composable, intégration Inertia/API et navigateur réel. Déclarer preuves exécutées et manquantes selon R01.

| Risque du changement | Preuve observable |
| --- | --- |
| Contrat/réactivité | Prop/événement incompatible détecté ; dérivation à jour, props non mutées. |
| Brouillon/formulaires | Actualisation pendant saisie, normalisation vers une valeur inchangée, refus et erreurs propres à chaque formulaire. |
| Lecture remplacée | Ancien succès, erreur ou `finally` sans effet ; non chargé distinct du dernier résultat vide. |
| État partagé | Deux consommateurs à jour ; reset complet et sortie sans interrompre les autres. |
| Cycle de vie | A → B → A, layout persistant, visites répétées sans effet orphelin. |
| Copies Inertia | Initial/partiel/différé, échec distinct du vide, retour historique selon exigence. |
| Raccordement/coût | Nouvelle page/composant/style sans registre manuel ; build et mesures du parcours. |

## Illustrations minimales

**Portée :** extraits de conception, sans installation, compilation TS/Vue ni test applicatif exécuté. Noms/routes illustratifs ; contrats représentant des sorties R04, source PHP et adaptateurs réseau non détaillés. Les versions et API restent à qualifier.

### A — Prop, brouillon et formulaire Inertia

Hypothèse : page d'édition dédiée à un produit, remplacée si son identité/périmètre change. Pendant la saisie, une nouvelle valeur serveur conserve le brouillon et invite à reprendre explicitement la valeur enregistrée. Cette stratégie illustre R12-009 ; elle n'impose pas le comportement de tout formulaire.

Trajet : **lecture autorisée → `ProductEditData` → page → brouillon → composant → PATCH Inertia → validation/action serveur → redirection et props confirmées**. Le backend fournit `updateUrl` et recontrôle droits/invariants ; une erreur de validation alimente `form.errors`.

```text
resources/
├── css/app.css
└── js/
    ├── app.ts
    ├── pages/Restaurant/Catalog/EditProduct.vue
    └── modules/
        ├── catalog/
        │   ├── public/products.ts
        │   └── products/edit-product/
        │       ├── components/ProductNameField.vue
        │       ├── contracts/edit-product.ts        # Généré
        │       └── types/product-form.ts
        └── dining/
            ├── public/workboard.ts
            └── orders/workboard/
                ├── contracts/workboard.ts          # Généré
                ├── composables/useLatestTickets.ts # B
                ├── composables/useWorkboardContext.ts # C
                ├── stores/useWorkboardStore.ts     # C
                └── requests/…                      # Adaptateurs fournis, non détaillés
```

Formes utilisées, pas de deuxième contrat maintenu manuellement :

| Fichier | Définition ou exports utiles |
| --- | --- |
| `contracts/edit-product.ts` | `ProductEditData = { id: string; name: string; updateUrl: string }`. |
| `types/product-form.ts` | `ProductForm = Pick<ProductEditData, 'name'>` : brouillon du champ éditable. |
| `public/products.ts` | Exporter seulement `ProductNameField`, `ProductEditData` et `ProductForm` nécessaires à cette page. |
| `contracts/workboard.ts` | `TicketListData = { items: { id: string; label: string }[] }` ; `WorkboardData = { openTicketCount: number }`, deux projections illustratives distinctes. |

Dans `ProductNameField.vue`, valeur reçue et intention émise :

```vue
<script setup lang="ts">
defineProps<{ id: string; modelValue: string; disabled: boolean }>();
const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

function updateName(event: Event) {
    const input = event.target;
    if (input instanceof HTMLInputElement) {
        emit('update:modelValue', input.value);
    }
}
</script>

<template>
    <label :for="id">Name</label>
    <input :id="id" :value="modelValue" :disabled="disabled" @input="updateName" />
</template>
```

Dans `EditProduct.vue`, les props restent séparées du formulaire :

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    ProductNameField,
    type ProductEditData,
    type ProductForm,
} from '@/modules/catalog/public/products';

const props = defineProps<{ product: ProductEditData }>();
const form = useForm<ProductForm>({ name: props.product.name });
const baseName = ref(props.product.name);
const remoteChange = ref(false);

function reloadDraft() {
    baseName.value = props.product.name;
    form.defaults({ name: props.product.name });
    form.reset();
    form.clearErrors();
    remoteChange.value = false;
}

watch([() => props.product, () => form.processing], () => {
    if (form.processing) return;
    const unchanged = form.name === baseName.value;
    const matchesServer = form.name === props.product.name;
    if (!form.hasErrors && (unchanged || matchesServer)) {
        reloadDraft();
    } else {
        remoteChange.value = !matchesServer;
    }
});

function submit() {
    if (!form.processing) {
        form.patch(props.product.updateUrl);
    }
}
</script>

<template>
    <form @submit.prevent="submit">
        <ProductNameField
            :id="`product-name-${props.product.id}`"
            v-model="form.name"
            :disabled="form.processing"
        />
        <p v-if="form.errors.name">{{ form.errors.name }}</p>
        <p v-if="remoteChange && form.name !== props.product.name">
            Saved value differs from draft: {{ props.product.name }}
        </p>
        <button type="submit" :disabled="form.processing">Save</button>
        <button type="button" :disabled="form.processing" @click="reloadDraft">
            Reload saved value
        </button>
    </form>
</template>
```

Le watcher suit la réception de la projection et la fin d'envoi, même si le serveur retrouve l'ancienne valeur. `baseName` est la base acceptée du brouillon, indépendante des defaults que `useForm` peut promouvoir après succès. Reprise automatique si aucune saisie ne serait perdue, sans effacer un refus ; sinon bouton explicite. Autres formulaires indépendants, restitution R13.

Raccordement dans `app.ts`, après qualification des packages et configuration Vite R14/R20 :

```ts
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h, type DefineComponent } from 'vue';

const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');

createInertiaApp({
    resolve: async (name) =>
        (await resolvePageComponent(`./pages/${name}.vue`, pages)).default,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .mount(el);
    },
});
```

Le nom serveur `Restaurant/Catalog/EditProduct` correspond exactement au chemin. Le glob fournit des chargeurs sans `eager` ; il n'importe pas immédiatement toutes les fonctionnalités. Le helper retourne le module, dont `.default` est le composant. Le build et le contrôle des SFC restent à exécuter. [Raccordement Laravel/Vite](https://laravel.com/framework/docs/13.x/vite#inertia)

### B — Une lecture remplacée protège toutes ses sorties

Dans `useLatestTickets.ts`, `load` est l'adaptateur fourni de signature `(query: string, signal: AbortSignal) => Promise<TicketListData>`. Il applique le contrat réseau R04/R08 ; `reportFailure` est le signalement de frontière R22, sans message brut dans l'état UI.

```ts
import { onScopeDispose, readonly, ref, shallowRef, watch, type Ref } from 'vue';
import type { TicketListData } from '../contracts/workboard';

export function useLatestTickets(
    query: Readonly<Ref<string>>,
    load: (query: string, signal: AbortSignal) => Promise<TicketListData>,
    reportFailure: (error: unknown) => void,
) {
    const snapshot = shallowRef<{ query: string; data: TicketListData } | null>(null);
    const loading = ref(false);
    const failed = ref(false);
    let operation = 0;
    let controller: AbortController | null = null;

    async function refresh() {
        const current = ++operation;
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const appliedQuery = query.value;
        loading.value = true;
        failed.value = false;
        try {
            const data = await load(appliedQuery, request.signal);
            if (current === operation) snapshot.value = { query: appliedQuery, data };
        } catch (error) {
            if (current === operation) {
                failed.value = true;
                reportFailure(error);
            }
        } finally {
            if (current === operation) {
                loading.value = false;
                controller = null;
            }
        }
    }

    onScopeDispose(() => {
        ++operation;
        controller?.abort();
        controller = null;
    });
    watch(query, refresh, { immediate: true, flush: 'sync' });

    return { snapshot: readonly(snapshot), loading: readonly(loading), failed: readonly(failed) };
}
```

Hypothèse : contexte d'accès fixe pendant ce scope ; sinon le renouveler ou inclure le contexte dans la source invalidante. Un appel par propriétaire, filtre appliqué réactif ; fréquence/regroupement selon R12-025. `snapshot === null` signifie aucune lecture acquise ; sinon il associe le dernier résultat à son filtre, même vide. `loading`/`failed` qualifient la tentative courante ; restitution R13. La promesse retournée au watcher rejoint le traitement Vue des erreurs.

| Ordre réel | Effet |
| --- | --- |
| A lancée, puis B | Nouvelle identité ; abort de A ; chargement de B actif. |
| A répond ou échoue après B | Aucun résultat/échec de A affecté ; pas de signalement d'erreur obsolète. |
| Le `finally` de A arrive pendant B | Ne termine pas le chargement de B. |
| Sortie du scope | Identité invalidée avant abort ; watcher arrêté par Vue. |

Une projection temps réel utiliserait aussi le rapprochement R11 ; cette identité locale ne remplace pas sa révision.

### C — Pinia partagé et layout persistant

Hypothèse : un espace de travail possède sélection, données et statut utilisés dans plusieurs pages et panneaux ; ses transitions et le diagnostic justifient Pinia. Le petit instantané ci-dessous simplifie la projection réelle. Le layout est le propriétaire unique du contexte ; ses consommateurs ne réinitialisent pas le store chacun de leur côté.

Dans `useWorkboardStore.ts` :

```ts
import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import type { WorkboardData } from '../contracts/workboard';

export const useWorkboardStore = defineStore('dining.orders.workboard', () => {
    const contextId = ref<string | null>(null);
    const selectedTicketId = ref<string | null>(null);
    const snapshot = shallowRef<WorkboardData | null>(null);
    const loading = ref(false);
    const failed = ref(false);
    let generation = 0;

    function $reset() {
        ++generation;
        contextId.value = null;
        selectedTicketId.value = null;
        snapshot.value = null;
        loading.value = false;
        failed.value = false;
    }
    function enterContext(id: string) {
        $reset();
        contextId.value = id;
        loading.value = true;
        return generation;
    }
    function isCurrent(token: number) {
        return token === generation && contextId.value !== null;
    }
    function accept(token: number, data: WorkboardData) {
        if (isCurrent(token)) snapshot.value = data;
    }
    function fail(token: number) {
        if (isCurrent(token)) failed.value = true;
    }
    function finish(token: number) {
        if (isCurrent(token)) loading.value = false;
    }
    function selectTicket(id: string | null) {
        selectedTicketId.value = id;
    }

    return {
        contextId, selectedTicketId, snapshot, loading, failed,
        $reset, enterContext, isCurrent, accept, fail, finish, selectTicket,
    };
});
```

Dans `useWorkboardContext.ts`, installé une fois par le layout propriétaire :

```ts
import { watch, type Ref } from 'vue';
import type { WorkboardData } from '../contracts/workboard';
import { useWorkboardStore } from '../stores/useWorkboardStore';

export function useWorkboardContext(
    context: Readonly<Ref<string>>,
    load: (contextId: string, signal: AbortSignal) => Promise<WorkboardData>,
    reportFailure: (error: unknown) => void,
) {
    const store = useWorkboardStore();
    watch(context, async (id, _previous, onCleanup) => {
        const token = store.enterContext(id);
        const request = new AbortController();
        onCleanup(() => {
            store.$reset();
            request.abort();
        });
        try {
            const data = await load(id, request.signal);
            store.accept(token, data);
        } catch (error) {
            if (store.isCurrent(token)) {
                store.fail(token);
                reportFailure(error);
            }
        } finally {
            store.finish(token);
        }
    }, { immediate: true, flush: 'sync' });

    return store;
}
```

Le ref du layout change aussi lorsque compte/périmètre invalide l'état ; il ne représente pas seulement un ID de ressource. À disparition du contexte, arrêter le scope propriétaire. `public/workboard.ts` expose `useWorkboardContext`, `useWorkboardStore` et leurs contrats utiles. Un panneau lit via `const { selectedTicketId } = storeToRefs(store)` et appelle `store.selectTicket(id)` sans mutation directe.

A → B → A renouvelle la génération à chaque transition : le résultat du premier A reste invalide malgré l'identifiant revenu à A. Le cleanup invalide les données avant abort ; le layout peut rester monté. Une fois son scope arrêté, le watcher nettoie le dernier contexte. La fin d'un panneau consommateur ne déclenche pas ce reset global. Pour abonnements partagés et médias, appliquer leurs propriétaires R11/R16 au lieu d'ajouter ici un autre protocole.
