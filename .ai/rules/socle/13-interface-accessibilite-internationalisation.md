# R13 — Interface, accessibilité et internationalisation

**Version 0.2 — applicabilité à valider.** [Index des règles](../index.md#r13).

**Lire pour :** créer, auditer ou corriger un écran, une interaction, une notification, une traduction ou un parcours dégradé. **Procédure :** exigences → états visibles → interactions → langue/restitution → preuves.

Les prescriptions `R13-001` à `R13-025` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Lorsque WCAG est la référence de revue retenue, l'obligation de l'examiner ne rend pas tous ses critères obligatoires : appliquer R13-010. Les [références de conception](../index.md#references-conception) possèdent informations affichables, actions, langues et préférences du parcours ; clarifier une exigence nécessaire introuvable. Les exemples n'adoptent aucun comportement produit.

**Applicabilité :** retrouver outillage de localisation et référentiel de revue dans le profil du projet. Les branches Vue I18n et WCAG 2.2 s'appliquent lorsqu'ils sont retenus ; elles ne fixent ni langues du produit ni niveau de conformité. Les garanties d'interaction, de restitution sûre et de cycle des catalogues restent à vérifier avec les mécanismes utilisés.

**Frontières :** [R02](02-architecture-modules.md) possède emplacements/raccordements, [R04](04-contrats-dto-validation.md) contrats/formats, [R07](07-transactions-concurrence-etats.md) résultat/reprise, [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) droits/données, [R11](11-temps-reel-synchronisation.md) convergence et [R12](12-vue-typescript-inertia.md) état/réseau/cycle de vie. Consulter selon impact [R14](14-assets-fichiers-stockage.md), [R15](15-ia-outils-fournisseurs.md), [R16](16-voix-avatar-medias.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R20](20-environnement-dependances-qualite.md) et [R22](../index.md#r22) pour assets, agents, médias, preuves, mesures, qualification et erreurs. Respecter leur statut ; R13 possède la restitution utilisable, sans définir un second catalogue de codes.

## 1. Qualifier les exigences et les états visibles

**R13-001 — Retrouver le parcours et son audience.** Identifier exigence, propriétaire, destinataires, informations/actions autorisées, modes d'interaction et résolution de langue. Pour une correction, reproduire le défaut et suivre ses consommateurs avant de modifier leur présentation. Ne pas déduire les droits d'un libellé ou inventer un comportement manquant. **Contrôle :** chaque résultat attendu et contrainte de présentation renvoie à sa source, ou à une clarification R01.

**R13-002 — Décrire les états utiles avant le rendu.** Pour les états possibles du parcours, associer information visible, actions utilisables et suite compréhensible. Ne pas ajouter des états impossibles ni une machine universelle pour tout composant.

| État | Restitution à distinguer |
| --- | --- |
| Initial / chargement | Information pas encore obtenue ; ce n'est pas un résultat vide. |
| Disponible / vide confirmé | Résultat acquis du chargement concerné, selon sa fraîcheur. |
| Actualisation | Nouvelle lecture en cours ; ancien résultat identifiable si sa conservation est permise. |
| Erreur / déconnexion / périmé | Limite réelle et reprise autorisée ; aucune disparition présentée comme un vide confirmé. |
| Mutation en cours / refusée / confirmée | Distinguer attente, refus et résultat acquis. |
| Issue incertaine | Résultat à vérifier selon R07/R11, sans déclaration de succès ou d'échec définitif. |

**R13-003 — Afficher des actions correspondant au contrat.** Rendre traitement en cours, refus et confirmation perceptibles ; le texte et la disponibilité des commandes suivent le résultat réel. Une annulation de requête, un toast fermé ou une réponse IA ne prouve pas l'annulation/la réussite de l'opération. Ne proposer une nouvelle tentative que selon les suites admises par R07/R22 ; une lecture peut être relancée sans que cela autorise à renvoyer une mutation.

**R13-004 — Préserver la continuité de l'interaction.** Selon l'exigence, garder saisie, sélection et contexte utile lors d'une actualisation ou d'un refus. Ne pas masquer silencieusement une erreur, remplacer le brouillon par la nouvelle prop ou voler le focus pour signaler un changement. Si un élément actif disparaît, choisir une destination logique et annoncer la transition utile. La coordination des états et réponses tardives suit R12 ; leur restitution suit cette règle.

## 2. Construire les interactions et les annonces

**R13-005 — Partir du HTML adapté.** Utiliser bouton pour action, lien pour navigation, titres hiérarchisés, listes/tableaux selon le sens et libellés associés aux champs. Donner aux commandes à icône un nom accessible localisé ; une icône décorative ne doit pas être annoncée deux fois. **Variante :** un contrôle personnalisé répond à un besoin identifié et implémente clavier, nom, rôle et état attendus ; des attributs ARIA seuls ne fournissent pas ces comportements. [Vue : accessibilité](https://vuejs.org/guide/best-practices/accessibility.html)

**R13-006 — Raccorder champs, instructions et erreurs.** Associer `label` et champ par des identifiants uniques ; relier aides/erreurs avec `aria-describedby` et utiliser `aria-invalid` lorsque le champ est réellement invalide. Après soumission refusée, rendre les erreurs retrouvables et orienter vers le résumé ou champ pertinent selon le formulaire. Ne pas déplacer systématiquement le focus à chaque validation pendant la frappe. Un message visuel générique seul ne remplace pas l'association avec le champ concerné.

**R13-007 — Maîtriser le clavier et le focus.** Vérifier ordre logique, activation et focus visible/non masqué par les éléments fixes. Ne pas reconstituer l'ordre avec des `tabindex` positifs. Prévoir navigation principale, arrivée sur une page et retour utile selon le parcours ; une actualisation en place conserve normalement le focus. Examiner aussi les composants conditionnels et les visites Inertia : une navigation n'est pas un simple remplacement de données.

**R13-008 — Traiter le cycle d'une modale.** Lorsqu'un dialogue modal est nécessaire : nom accessible, focus initial adapté au contenu, parcours clavier contenu dans la modale, arrière-plan non interactif, commande de fermeture et retour au déclencheur ou à une destination logique s'il a disparu. Prévoir le comportement de `Escape` selon le parcours. Préférer un mécanisme natif ou composant qualifié ; `aria-modal` sans ces comportements ne suffit pas. [Modales W3C](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)

**R13-009 — Annoncer ce qui aide à agir.** Employer une région de statut, par exemple `role="status"` pour une information non urgente, et réserver les annonces interruptives aux besoins justifiés. Initialiser la région puis mettre à jour un message concis ; ne pas rendre toute une liste temps réel « live ». Regrouper les rafales et éviter doublons entre focus déplacé et annonce. **Contrôle :** avec lecteur d'écran, résultat/erreur utile perceptible, navigation conservée et absence de répétitions inutiles. [Messages de statut W3C](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html)

## 3. Adapter la présentation et évaluer les compromis

**R13-010 — Appliquer le référentiel de revue retenu.** **Revue :** examiner le référentiel et les exigences effectivement adoptés ; **si WCAG 2.2 est retenu**, examiner ses critères pertinents au changement. Consigner dans le travail de conception les écarts significatifs, leur effet sur l'usage, les alternatives examinées et les vérifications ; ne pas maintenir une seconde matrice produit dans la règle.

| Situation | Procédure |
| --- | --- |
| Critère satisfait ou exception prévue par ce critère | Vérifier les conditions ; pas de dérogation à demander. |
| Adaptation dans la marge admise, sans obligation contraire | Motiver le compromis et vérifier l'usage ; pas d'approbation par bouton. |
| Écart à une obligation de la règle ou exigence adoptée | Chercher une solution conforme, puis appliquer R01-012 si une dérogation est recevable. |

Un style apprécié ne prouve pas l'utilisabilité ; une adaptation motivée ne prouve pas une conformité AA globale. Cette revue n'impose ni AA intégral ni objectif AAA. [WCAG 2.2](https://www.w3.org/TR/WCAG22/)

**R13-011 — Distinguer dessin et zone interactive.** **Revue :** vérifier taille réelle de la cible, espacement, précision tactile et absence de chevauchement. Une icône peut rester petite avec une cible plus grande ; augmenter la zone ne doit pas recouvrir une autre commande. Repères : 24 × 24 pixels CSS pour le critère AA 2.5.8 avec ses exceptions, 44 × 44 pour le critère AAA 2.5.5. Ils ne deviennent pas ici deux dimensions minimales universelles. Vérifier le rendu sur appareil représentatif. [Minimum](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html), [cible renforcée](https://www.w3.org/WAI/WCAG22/Understanding/target-size-enhanced.html)

**R13-012 — Vérifier la lisibilité dans les variantes réelles.** **Revue :** examiner contraste, information indépendante de la couleur, petit écran, zoom, redistribution et textes traduits plus longs. Préserver informations/actions essentielles ; tronquer seulement si le contenu complet reste accessible selon le parcours. Tester bandeaux fixes, clavier virtuel, erreurs multilignes et focus, pas seulement une capture avec un texte court. Les styles et points de rupture restent chez leur propriétaire R02/R14 ; aucun thème ou bibliothèque visuelle imposé.

**R13-013 — Adapter mouvement et médias sans perdre le sens.** **Revue :** examiner la préférence `prefers-reduced-motion` et proposer une présentation réduite lorsque le mouvement n'est pas nécessaire. Conserver l'information portée par l'animation sous une forme utilisable. Pour audio/avatar, appliquer les modes et alternatives prévus, puis raccorder leur restitution à R16 ; ne pas inventer un historique de conversation pour produire une alternative textuelle. Le critère 2.3.3 sur les animations d'interaction est AAA ; mouvement réduit n'est pas une assertion de conformité AA. [Animation W3C](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html)

## 4. Organiser les langues, catalogues et formats

**R13-014 — Séparer langue interne et présentation.** Garder anglais pour identifiants, clés, codes et messages internes produits par l'application. Traduire les libellés/phrases destinés à l'utilisateur à la frontière de présentation, y compris noms accessibles et notifications. Ne pas décider le métier en comparant un libellé traduit. Préserver la langue du contenu utilisateur et les diagnostics natifs ; un nom de produit n'est pas automatiquement une clé de traduction. Traductions métier et textes d'interface sont deux responsabilités.

**R13-015 — Donner un propriétaire aux traductions.** Ranger textes spécifiques et mappings de présentation auprès de leur module/usage selon R02 ; garder les textes techniques communs chez leur propriétaire commun. Employer clés anglaises stables et namespaces explicites, sans catalogue central accumulant tous les cas. Pour un message réellement partagé, désigner une source et adapter/générer les représentations nécessaires, sans deux copies maintenues indépendamment. Formulations propres à chaque canal admises si l'audience ou l'usage le justifie, sans changer le sens du code ou des faits ; syntaxes de paramètres/pluriels Laravel et Vue non présumées interchangeables.

**R13-016 — Raccorder les catalogues avant leurs consommateurs.** Raccorder le mécanisme retenu avant ses consommateurs. **Avec Vue I18n :** l'initialiser avec l'API de composition et l'installer avant montage ; utiliser `useI18n` dans le contexte `setup`, avec portée explicitement choisie. En Laravel, charger les traductions modulaires dans leur provider, par exemple `loadTranslationsFrom` ; garder les conventions natives pour validation et textes communs. Déclarer l'assemblage par propriétaire, sans nouvelle entrée Vite par composant. **Revue :** charger les catalogues nécessaires ; imports statiques de toutes les langues/modules ne constituent pas du chargement différé. Qualification de compilation/chargement avec R14/R20. [Vue I18n](https://vue-i18n.intlify.dev/guide/advanced/composition), [Laravel](https://laravel.com/framework/docs/13.x/localization)

**R13-017 — Résoudre la locale à la frontière du parcours.** Appliquer la source/priorité définie par la conception et vérifier la locale parmi celles admises pour ce contexte. Ne pas prendre partout la préférence du navigateur, ni imposer la langue commune d'un parcours à tous les espaces. Côté HTTP, régler `App::setLocale` dans le middleware approprié **avant** résolution du FormRequest ; transmettre la locale effective à Vue et régler `html[lang]`, ainsi que la direction d'écriture si pertinente. Pour un autre canal/processus durable, initialiser et libérer son contexte suivant R10/R15 ; aucune locale résiduelle d'un précédent utilisateur.

**R13-018 — Distinguer changement de langue et résultat métier.** Charger/vérifier les ressources avant activation de la nouvelle langue, puis actualiser libellés et messages structurés depuis leurs codes/paramètres. Préserver brouillon, focus et issue d'une opération en cours : sa langue d'émission ne rend pas son résultat métier obsolète. Pour une réponse déjà localisée, identifier sa langue et organiser la nouvelle restitution par une lecture ou un contrat dédié disponible ; ne pas la passer à `t()` ni rejouer la mutation. Si ce mécanisme manque, signaler la limite et clarifier le parcours sans cacher le refus ou inventer sa traduction. Un échec de chargement garde la langue utilisable et expose la limite prévue ; coordination R12.

**R13-019 — Construire phrases et formats sans changer les données.** Utiliser interpolation/pluriels, pas concaténation de fragments dépendant d'un ordre français. Employer les outils de présentation adaptés, par exemple `t`, `n`, `d` ou `Intl`, avec paramètres/fuseau/devise explicites selon le sens. Préserver exactitude monétaire et grands entiers R04-013 ; ne pas convertir arbitrairement une chaîne exacte en `Number` pour la formater. Distinguer date civile, instant, horaire et durée R04-014 ; langue, devise et fuseau indépendants. **Contrôle :** zéro/un/plusieurs, paramètres longs, changement de langue et absence de modification du montant/de la date. [Pluriels Vue I18n](https://vue-i18n.intlify.dev/guide/essentials/pluralization)

**R13-020 — Garder les interpolations sûres.** N'utiliser que les paramètres admis pour le destinataire ; conserver leur typage et leur sens selon R04/R22. Afficher le texte avec les mécanismes d'échappement normaux ; ni erreur native ni contenu reçu ne devient du HTML par `v-html`. Si un texte riche est nécessaire, utiliser des composants structurés et le traitement qualifié R08/R09, plutôt qu'une traduction arbitraire exécutable. Ne pas envoyer le diagnostic privé à un agent pour qu'il fabrique un message public.

## 5. Restituer les erreurs et apporter les preuves

**R13-021 — Mapper un contrat sûr vers la présentation.** Consommer code et paramètres autorisés, sans analyser le texte technique de l'exception. Placer le mapping près de son propriétaire ; choisir formulation et suite selon audience/langue et actions admises. Même cause, code stable ; message différent possible. R22 conserve sens des codes et suites, R04 leur forme, R15 la réponse conversationnelle. Ne pas valider indirectement une convention encore proposée dans une règle future.

**R13-022 — Conserver le parcours natif de validation.** En Laravel/Inertia, afficher les erreurs localisées transmises par redirection/session/props au formulaire concerné ; ne pas les transformer en `422` JSON général ni appliquer `t(form.errors.field)`. Les libellés d'attributs et traductions de validation nécessaires sont configurés avant la validation. Distinguer ce texte déjà présenté d'un contrat structuré à localiser. Le changement de langue suit R13-018 et ne supprime pas l'issue de validation. [Validation Inertia](https://inertiajs.com/docs/v3/the-basics/validation)

**R13-023 — Préparer et vérifier le repli.** Pour code inconnu, paramètres invalides, traduction/catalogue indisponible : conserver l'issue réelle et sélectionner le message sûr prévu, dans une langue autorisée et effectivement disponible. Vérifier aussi le message de secours ; `fallbackLocale` ou la suppression d'un warning ne garantit pas que `t()`/`__()` n'affichera pas la clé brute. Détecter le défaut selon R09/R19 sans recopier données privées. Pour un contenu métier absent dans une langue, appliquer sa politique de conception ; ne pas inventer une traduction ou choisir automatiquement l'anglais. [Replis Vue I18n](https://vue-i18n.intlify.dev/guide/essentials/fallback)

**R13-024 — Vérifier les parcours utiles, pas seulement le rendu.** Choisir selon le changement : clavier/toucher, focus, lecteur d'écran, zoom, texte long, états de chargement/refus/reprise et changement de langue en cours d'opération. Contrôles automatiques complétés par les essais manuels nécessaires ; aucun outil isolé ni exemple ne prouve une conformité globale. Vérifier résultat observable et effets interdits, sans tests recopiant chaque attribut ou obligation de tout rejouer après un simple ajustement. Organisation des preuves R18/R01.

**R13-025 — Qualifier la remise et les dépendances utilisées.** Vérifier API/versions réelles de Laravel, Inertia et des mécanismes de localisation retenus, dont Vue I18n s'il est utilisé, catalogues chargés et build adapté avec R14/R20. Contrôler concordance des clés/paramètres utiles entre langues et consommateurs, erreurs inconnues et ressources manquantes. Déclarer contrôles exécutés, manquants et limites ; distinguer validation documentaire, qualification des packages et preuves d'usage. Pour un audit, appliquer la même procédure aux représentations existantes, pas seulement au nouvel écran.

## Illustrations minimales

**Portée :** extraits de conception, sans installation, compilation ni test applicatif exécuté. Routes, produits, codes et traductions sont fictifs ; les droits restent recontrôlés par R08, contrats générés selon R04 et protocole R11 supposés. Les exemples français/espagnol n'adoptent ni liste de langues ni langue de secours globale.

### A — Formulaire localisé et validation déjà traduite

Hypothèse : édition d'un nom de produit ; français/espagnol disponibles, locale effective fournie par le serveur et contrôle d'accès avant validation. Les catalogues de secours ont été vérifiés. Les valeurs de l'assemblage ci-dessous viennent de la présentation préparée ; elles ne fixent pas la priorité des préférences.

```text
app/
├── Infrastructure/Http/Middleware/SetPresentationLocale.php
└── Modules/Catalog/
    ├── Providers/CatalogServiceProvider.php
    ├── Translations/{fr,es}/products.php
    └── Products/UseCases/UpdateProduct/Http/UpdateProductRequest.php
lang/{fr,es}/validation.php
resources/js/
├── app.ts
├── i18n/{fr,es}/ui.json
├── pages/Restaurant/Catalog/EditProduct.vue
└── modules/
    ├── catalog/
    │   ├── public/products.ts
    │   └── products/
    │       ├── i18n/{fr,es}.json
    │       ├── edit-product/contracts/ProductEditData.ts
    │       └── failures/
    │           ├── contracts/UiFailure.ts
    │           ├── types/FailureMessage.ts
    │           ├── presentation/presentItemFailure.ts
    │           └── components/FailureNotice.vue
    └── dining/orders/
        ├── i18n/{fr,es}.json
        ├── contracts/OrderRowData.ts
        └── components/OrderListStatus.vue
```

**Raccordement :** middleware affecté aux routes concernées après résolution/validation de la locale → `App::setLocale($presentationLocale)` → FormRequest → contrôleur/action R02 → redirection Inertia → props/`form.errors`. Le provider Catalog charge `loadTranslationsFrom(__DIR__.'/../Translations', 'catalog')`. `products.php` fournit le libellé `name` ; les fichiers natifs `validation.php` fournissent les messages des règles dans chaque langue. Controller, action et politique d'accès sont omis, pas remplacés par le FormRequest.

```php
// UpdateProductRequest.php — autorisation assurée par la route selon l'hypothèse.
declare(strict_types=1);

namespace App\Modules\Catalog\Products\UseCases\UpdateProduct\Http;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }

    public function attributes(): array
    {
        return ['name' => __('catalog::products.name')];
    }
}
```

Dans `app.ts`, au sein de l'initialisation Inertia existante, imports `createI18n` depuis `vue-i18n` et catalogues JSON depuis les chemins de l'arbre :

```ts
// modules/catalog/public/products.ts — surface utilisée par page/assemblage.
export type { ProductEditData } from '../products/edit-product/contracts/ProductEditData';
export { default as frCatalog } from '../products/i18n/fr.json';
export { default as esCatalog } from '../products/i18n/es.json';
```

```ts
// frUi/esUi : ui.json ; frCatalog/esCatalog : catalog/products/i18n.
// Importer frCatalog/esCatalog depuis '@/modules/catalog/public/products'.
// presentation : locale et fallbackLocale préparés et vérifiés pour ce parcours.
const i18n = createI18n({
  legacy: false,
  locale: presentation.locale,
  fallbackLocale: presentation.fallbackLocale,
  messages: {
    fr: { ui: frUi, catalog: frCatalog },
    es: { ui: esUi, catalog: esCatalog },
  },
});
// Installer dans l'application Vue créée par Inertia, avant son montage :
app.use(i18n);
```

Ces imports statiques conviennent à cette petite illustration ; ils ne prescrivent pas le chargement initial de tous les catalogues de production. `catalog.name`, `save` et `saving` donnent respectivement des libellés comme « Nom du produit », « Enregistrer », « Enregistrement… », et leurs équivalents espagnols. Pour le libellé `name` réellement partagé, la source est `Catalog/Translations/<locale>/products.php` ; sa représentation JSON est produite par l'adaptation déclarée, sans édition manuelle concurrente. La commande de production de ce fragment sera à qualifier dans le lot applicatif ; les autres textes UI restent possédés par leur catalogue.

```vue
<!-- EditProduct.vue : un seul formulaire ; identifiants uniques dans cette page. -->
<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { ProductEditData } from '@/modules/catalog/public/products';

const props = defineProps<{ product: ProductEditData }>();
const { t } = useI18n({ useScope: 'global' });
const nameInput = ref<HTMLInputElement | null>(null);
const form = useForm({ name: props.product.name });

function submit() {
  form.patch(props.product.updateUrl, {
    onError: async () => {
      await nextTick();
      if (form.errors.name) nameInput.value?.focus();
    },
  });
}
</script>

<template>
  <form @submit.prevent="submit">
    <label for="product-name">{{ t('catalog.name') }}</label>
    <input id="product-name" ref="nameInput" v-model="form.name"
      :aria-invalid="Boolean(form.errors.name)"
      :aria-describedby="form.errors.name ? 'product-name-error' : undefined">
    <p v-if="form.errors.name" id="product-name-error">{{ form.errors.name }}</p>
    <button type="submit" :disabled="form.processing">
      {{ t(form.processing ? 'catalog.saving' : 'catalog.save') }}
    </button>
  </form>
</template>
```

`ProductEditData` expose ici `name` et `updateUrl`. Si le nom vide est refusé, Laravel produit le texte de validation dans sa locale ; Inertia le place dans `form.errors.name`. La saisie reste présente et le focus revient au champ invalide après cette soumission. Ce texte n'est jamais passé à `t()`.

**Variante à traiter :** changer seulement la locale Vue traduit le label, pas cette erreur reçue comme chaîne. Identifier la langue de la restitution et utiliser le mécanisme de renouvellement prévu par le parcours, sans PATCH supplémentaire. La conservation/actualisation concurrente du brouillon et un changement de produit suivent R12 ; l'extrait ne les réimplémente pas.

### B — Actualisation échouée, résultat vide et annonces

Hypothèse : le coordinateur R11 fournit dernier résultat autorisé, lecture en cours et notification sûre traduite. `OrderRowData` expose ici `id` et `label`. Le composant ne charge rien et n'invente aucune fraîcheur.

```vue
<!-- OrderListStatus.vue — les textes du statut ont le propriétaire du parcours. -->
<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { OrderRowData } from '../contracts/OrderRowData';

defineProps<{
  rows: readonly OrderRowData[] | null;
  refreshing: boolean;
  notice: string;
}>();
const { t } = useI18n({ useScope: 'global' });
</script>

<template>
  <p role="status">{{ notice }}</p>
  <section :aria-busy="refreshing">
    <template v-if="rows !== null">
      <p v-if="rows.length === 0">{{ t('dining.lastResultEmpty') }}</p>
      <ul v-else>
        <li v-for="row in rows" :key="row.id">{{ row.label }}</li>
      </ul>
    </template>
  </section>
</template>
```

Le catalogue `dining.lastResultEmpty` est raccordé par son propriétaire selon R13-015/016 ; son texte précise qu'il s'agit du dernier résultat, dont le statut donne la fraîcheur. Une collection `null` n'est pas remplacée par `[]`.

| Événement du parcours | Résultat / statut |
| --- | --- |
| Première lecture en cours | `rows=null` ; attente annoncée, pas « aucune commande ». |
| Lecture réussie sans élément | `rows=[]` ; vide confirmé pour cette lecture. |
| Actualisation d'une liste présente | Même liste ; état d'actualisation signalé. |
| Actualisation échouée | Dernier résultat conservé si permis ; limite et suite autorisée exposées. |
| Plusieurs changements rapprochés | Liste à jour, annonce concise regroupée ; pas lecture de chaque ligne. |

La région de statut reste montée ; modifier son texte aide à annoncer l'information sans déplacer le focus. Une erreur urgente peut justifier un autre mécanisme R13-009. Une reprise de lecture éventuelle est raccordée au coordinateur, pas à une nouvelle commande. Changement de contexte/révocation, regroupement des annonces et renouvellement de `notice` lors d'un changement de langue restent à implémenter chez leurs propriétaires, pas supposés par le template.

### C — Code stable, traduction et commande compacte

Hypothèse : un refus expose `errorCode` et `parameters` déjà filtrés pour le destinataire. Le contrat `UiFailure.ts` illustre une sortie R04 avec `errorCode: string` et `parameters: Readonly<Record<string, unknown>>` ; il ne fixe pas le futur contrat applicatif R22. Le repli `ui.unexpectedError` est vérifié dans chaque locale activable de l'exemple.

```ts
// types/FailureMessage.ts — type local de présentation.
export type FailureMessage = {
  key: 'catalog.itemUnavailable' | 'ui.unexpectedError';
  parameters: { name?: string };
};
```

```ts
// presentation/presentItemFailure.ts
import type { UiFailure } from '../contracts/UiFailure';
import type { FailureMessage } from '../types/FailureMessage';

export function presentItemFailure(failure: UiFailure): FailureMessage {
  if (
    failure.errorCode === 'catalog.item_unavailable' &&
    typeof failure.parameters.itemName === 'string'
  ) {
    return {
      key: 'catalog.itemUnavailable',
      parameters: { name: failure.parameters.itemName },
    };
  }
  return { key: 'ui.unexpectedError', parameters: {} };
}
```

```vue
<!-- FailureNotice.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UiFailure } from '../contracts/UiFailure';
import { presentItemFailure } from '../presentation/presentItemFailure';

const props = defineProps<{ failure: UiFailure | null }>();
const emit = defineEmits<{ dismiss: [] }>();
const { t, te, locale } = useI18n({ useScope: 'global' });
const message = computed(() => {
  if (props.failure === null) return '';
  const presentation = presentItemFailure(props.failure);
  return te(presentation.key, locale.value)
    ? t(presentation.key, presentation.parameters)
    : t('ui.unexpectedError');
});
</script>

<template>
  <div class="failure-notice">
    <p role="status">{{ message }}</p>
    <button v-if="failure !== null" type="button" class="close-control"
      :aria-label="t('ui.dismissNotice')" @click="emit('dismiss')">
      <span aria-hidden="true">×</span>
    </button>
  </div>
</template>

<style scoped>
.failure-notice { display: flex; align-items: start; gap: 0.5rem; }
.failure-notice p { min-width: 0; overflow-wrap: anywhere; }
.close-control { min-width: 28px; min-height: 28px; flex-shrink: 0; }
.close-control:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
</style>
```

**Catalogues utiles :**

| Clé | Français | Espagnol |
| --- | --- | --- |
| `catalog.itemUnavailable` | `{name} n'est plus disponible.` | `{name} ya no está disponible.` |
| `ui.unexpectedError` | Un problème est survenu. Demandez de l'aide si nécessaire. | Se ha producido un problema. Pide ayuda si la necesitas. |
| `ui.dismissNotice` | Fermer ce message | Cerrar este mensaje |

Le composant reste monté avec `failure=null` et la région de statut vide avant réception du refus ; son propriétaire lui transmet ensuite le contrat. Monter seulement une notice déjà remplie ne garantit pas l'annonce initiale. Le contrôle `te(key, locale.value)` vise explicitement la locale active. Ici, une traduction connue absente ou des paramètres invalides mène au repli déjà qualifié ; si ce repli manque aussi, l'initialisation/changement de langue doit traiter ce défaut selon R13-023, pas afficher une clé. La petite croix garde une cible distincte, un nom accessible et un focus visible ; 28 pixels illustrent un choix à vérifier, pas une norme de boutons. Le texte peut passer sur plusieurs lignes.

**Séquence de bout en bout :** demande → opération métier → refus et contrat sûr → mapping → traduction échappée → affichage. Même code et paramètres, autre langue : le `computed` renouvelle la phrase, pas le refus. Si la langue change pendant la mutation, garder son résultat puis localiser sa restitution ; ne pas l'écarter comme une ancienne lecture ni renvoyer la commande. Fermer la notice ne modifie pas le résultat.

Une autre audience peut disposer d'une formulation ou d'une suite différente selon le contrat : choisir une alternative pour un client, consulter la disponibilité pour un responsable si ce parcours le permet. Ces suites restent définies hors du mapper montré. Un code inconnu ou incident technique reçoit la présentation sûre ; aucun message SQL n'entre dans le composant ou l'agent.

## Recette proportionnée au changement

| Risque | Preuve attendue |
| --- | --- |
| États / reprise | Non chargé, vide, échec, périmé et résultat incertain distincts ; aucun faux succès. |
| Formulaire / clavier | Saisie conservée, erreurs associées, commandes activables et focus utile/non masqué. |
| Modale / mise à jour | Entrée/sortie du focus ; actualisation sans interruption arbitraire. |
| Lecteur d'écran | Statut utile perçu, rafales regroupées et absence de double annonce. |
| Présentation | Petit écran/zoom/texte long, cible réelle, contraste et préférence de mouvement examinés. |
| Langues / messages | Vue/Laravel cohérents ; langue changée pendant une réponse, validation prélocalisée, code/ressource/repli absents traités. |
| Formats / paramètres | Pluriels et données exactes préservés ; aucune devise ou date modifiée par la langue. |
| Raccordement / audit | Catalogues réellement chargés et compilables ; mêmes contrôles sur une correction et ses consommateurs. |

Ces cas sont des vérifications à sélectionner et réaliser, pas une suite exécutée par cette rédaction.
