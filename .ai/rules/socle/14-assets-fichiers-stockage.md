# R14 — Assets, fichiers et stockage

**Version 1.1 — applicabilité à valider ; fond v1.0 validé.** [Index des règles](../index.md#r14).

**Lire pour :** créer, auditer ou corriger CSS, compilation Vite, image, police, ressource 3D, upload, document, export, téléchargement ou nettoyage de fichiers. **Procédure :** exigence → catégorie/propriétaire → préparation → vérification → mise à disposition → remplacement/nettoyage → preuves.

Les prescriptions `R14-001` à `R14-026` sont obligatoires, sauf mention **Revue** ou **Variante** au sens de [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent formats admis, destinataires, limites, données et durées ; clarifier une exigence nécessaire introuvable. Les exemples illustrent les mécanismes sans adopter fonctionnalités, fournisseurs ou schémas applicatifs.

**Applicabilité :** le profil du projet possède le choix des outils de styles. Les passages Tailwind et les exemples qui l'utilisent expliquent cette branche lorsqu'elle est retenue. Colocalisation, portée, chargement, préparation et publication des fichiers restent à maîtriser avec les mécanismes effectivement utilisés.

**Frontières :** [R02](02-architecture-modules.md)/[R12](12-vue-typescript-inertia.md) possèdent placement/raccordement, [R04](04-contrats-dto-validation.md) contrats, [R07](07-transactions-concurrence-etats.md) concurrence SQL, [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès/données et [R10](10-jobs-evenements-fiabilite.md) suivi/reprise durable. Consulter selon impact [R13](13-interface-accessibilite-internationalisation.md), [R16](16-voix-avatar-medias.md), [R17](17-integrations-effets-externes.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R20](20-environnement-dependances-qualite.md), [R21](21-livraison-exploitation-reprise.md), [R22](../index.md#r22) pour restitution, médias actifs, transferts externes, preuves, mesures, qualification, livraison et erreurs. Respecter leurs statuts ; R14 ne crée pas un second protocole de reprise ou catalogue d'erreurs.

## 1. Classer et placer les ressources

**R14-001 — Retrouver l'usage et ses garanties.** Identifier propriétaire, producteurs, consommateurs, exposition, données sources, formats, coût admissible et fin de vie. Pour une correction, suivre aussi anciennes références, versions, dérivés et traitements encore actifs. Distinguer exigence connue, choix technique à qualifier et information manquante ; ne pas déduire visibilité ou conservation d'un dossier existant. **Contrôle :** chaque étape critique et paramètre applicable a une source retrouvable.

**R14-002 — Séparer les catégories.** Appliquer le rangement R02 selon la nature réelle :

| Ressource | Emplacement et raccordement |
| --- | --- |
| Source de l'interface : CSS, icône, image, police… | Auprès du propriétaire ; importée par son consommateur et traitée par Vite selon sa configuration. |
| Fichier volontairement servi directement | Emplacement public explicitement prévu ; distribution et renouvellement maîtrisés. |
| Upload, dérivé, PDF ou export produit à l'exécution | Stockage applicatif ; accès et cycle de vie indépendants des sources et releases. |

Ne pas écrire des uploads dans `resources/` ou les artefacts de compilation. Distinguer `public/` Laravel, disque public Laravel et configuration `publicDir` Vite ; leurs mécanismes ne sont pas interchangeables. Un fichier sous une URL publique est accessible sans passage automatique par une policy.

**R14-003 — Configurer des destinations explicites.** Déclarer les disques dans `config/filesystems.php`, lire la configuration avec `config()` et utiliser `Storage::disk(...)`. Séparer les destinations lorsque visibilité, droits, cycle de vie ou exploitation l'exigent ; aucun disque par entité ni fournisseur universel. Résoudre côté serveur disque, clé et racine autorisés. Un nom aléatoire n'est pas une autorisation ; le nom fourni au téléchargement n'est pas le chemin de stockage. **Contrôle :** processus HTTP/jobs concernés accèdent à la même ressource attendue ; droits, erreurs et capacités du driver sont qualifiés. Les fichiers durables ne dépendent pas implicitement du disque d'un seul serveur applicatif. [Disques Laravel](https://laravel.com/framework/docs/13.x/filesystem#configuration)

**R14-004 — Placer chaque responsabilité.** L'entrée adapte/valide, l'action orchestre, le repository lit/écrit SQL, le service décide sur les données fournies et le mécanisme fichier traite ses entrées bornées. Placer transformation/génération auprès du propriétaire ; un mécanisme transversal sans métier peut aller dans `app/Infrastructure/`. Déclarer contrats et bindings utiles selon R02/R17, sans envelopper chaque appel `Storage` ni ajouter une couche vide. Les projections R04 exposent les informations/URLs nécessaires, pas le chemin absolu, la configuration du disque ou un modèle complet.

## 2. Segmenter les styles et vérifier le chargement

**R14-005 — Colocaliser les styles selon leur propriétaire.** Garder un style simple dans le composant ou extraire le CSS spécifique auprès de son propriétaire selon R02-020. **Si Tailwind est retenu :** l'utiliser pour les styles courants. Le commun d'un usage reste dans cet usage ; seuls tokens/fondations/thèmes réellement transversaux vont dans `resources/css/`. `app.css` assemble ces fondations sans importer toutes les feuilles particulières. Les composants génériques et leurs styles restent dans `resources/js/shared/`. **Revue :** extraire lorsque responsabilité, lisibilité ou évolution le justifie ; ne pas créer des fichiers vides ou une arborescence miroir par principe.

**R14-006 — Suivre le graphe des imports.** Garder des entrées Vite stables ; raccorder pages selon R12-020 et styles/ressources par leurs consommateurs. Une page ordinaire, son composant et son CSS ne doivent pas exiger d'ajout individuel dans `vite.config.ts`. Ne pas charger immédiatement toutes les feuilles avec un glob ; inspecter aussi layouts, exports publics et bibliothèques susceptibles d'importer des ressources inutiles au parcours. **Variante :** une entrée supplémentaire correspond à un besoin de livraison distinct, identifié et vérifié, pas à chaque nouvelle fonctionnalité. [Laravel/Inertia/Vite](https://laravel.com/framework/docs/13.x/vite#inertia)

**R14-007 — Rendre les variantes détectables et maîtrisées.** **Avec Tailwind :** écrire les classes complètes, notamment dans une correspondance de variantes autorisées ; ne pas construire `bg-${color}-500` en supposant sa génération. Pour une valeur réellement paramétrable, utiliser une variable CSS ou valeur compatible avec le mécanisme qualifié, après validation des formes admises. Vérifier détection des sources modulaires, composants externes et fichiers générés selon l'outil retenu ; toute inclusion explicite reste bornée. Ne pas exposer une feuille CSS arbitraire pour personnaliser une valeur. [Détection Tailwind, si utilisé](https://tailwindcss.com/docs/detecting-classes-in-source-files)

**R14-008 — Maîtriser portée et cascade.** Examiner styles globaux, reset Tailwind, tokens, couches CSS, ordre et styles tiers. Utiliser `scoped` ou un autre mécanisme qualifié pour la portée utile ; limiter les sélecteurs globaux/profonds à leur besoin réel. Une feuille partagée a un propriétaire et des consommateurs connus. Éviter les escalades de spécificité et `!important` pour masquer une cascade mal comprise. **Contrôle :** navigation dans un ordre différent, composants imbriqués et variantes ne provoquent pas de fuite de style. `scoped` agit sur les sélecteurs, pas sur le moment du téléchargement. [CSS Vue](https://vuejs.org/api/sfc-css-features.html#scoped-css)

**R14-009 — Contrôler ce qui est réellement livré.** **Revue :** examiner manifeste, imports, fichiers générés et réseau après compilation ; mesurer transferts, cache et coût des dépendances sur les parcours visés. Prendre en compte préchargements et ressources communes : segmentation des fichiers et chargement différé sont deux preuves distinctes. Appliquer R08 aux variables exposées par Vite et aux artefacts publics, y compris les sources/diagnostics éventuellement distribués. Ne pas supposer les valeurs par défaut du Vite générique identiques à celles de son plugin Laravel. [Découpage CSS Vite](https://vite.dev/guide/features.html#css-code-splitting), [préchargement Laravel](https://laravel.com/framework/docs/13.x/vite#asset-prefetching)

## 3. Adapter images, polices et ressources lourdes

**R14-010 — Livrer une image adaptée à son usage.** Choisir dimensions, ratio, format, qualité et variantes selon l'affichage et le budget R19. Fournir dimensions réservées et, lorsque pertinent, `srcset`/`sizes` cohérents avec les fichiers existants ; tenir compte des densités d'écran et du coût cumulé d'une liste. Déterminer chargement immédiat, différé ou anticipation justifiée : ne pas différer systématiquement l'image nécessaire au premier affichage. Prévoir erreur de chargement et repli R13 sans boucle de tentatives. Vite peut résoudre/versionner une URL ; cela ne prouve pas un redimensionnement ou une compression adaptée.

**R14-011 — Limiter et vérifier les polices.** Charger familles, graisses et caractères nécessaires ; examiner sous-ensembles, langues, fallback et comportement pendant le chargement. Précharger seulement les ressources utiles au parcours et vérifier leur coût réel. Contrôler provenance, redistribution et incorporation dans les documents selon R14-025. Ne pas supposer une police web disponible dans le générateur PDF ni supprimer les caractères nécessaires à une langue. **Contrôle :** rendu lisible avant/après chargement, texte traduit et document exporté.

**R14-012 — Examiner l'ensemble des dépendances lourdes.** **Revue :** inventorier fichier principal, textures, buffers, animations et autres ressources réellement requises ; vérifier chemins et disponibilité après distribution. Ne pas présumer qu'un format ou fichier unique contient toutes ses dépendances. Mesurer octets transférés, décodage et mémoire séparément ; téléchargement compact ne signifie pas mémoire faible. Charger selon besoin et traiter indisponibilité/annulation au bon propriétaire. R16 possède initialisation, rendu, interruption et libération audio/GPU ; R14 possède préparation et livraison de leurs ressources.

## 4. Recevoir et transformer avant exposition

**R14-013 — Autoriser avant préparation utilisable.** Appliquer R08/R04 aux entrées et ressources ; déposer la préparation dans un espace privé non exécutable, sans exposition automatique de l'original. Générer une clé côté serveur, empêcher traversal/choix libre du disque et distinguer préparation, résultat vérifié et ressource utilisable. Le stockage temporaire natif du transport n'est pas une publication. Ne copier vers une destination publique que du contenu vérifié dont l'exposition est autorisée ; valider sa référence SQL n'est pas nécessairement le moment où ses octets deviennent publics. [Uploads OWASP](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)

**R14-014 — Vérifier contenu et coût avant traitement.** Combiner contraintes de taille, formats/types réellement admis et examen du contenu ; ni nom, extension ni MIME fourni ne suffit. La détection native, par exemple `File::types(...)`, ne remplace pas le décodage prévu ni la qualification d'un contenu actif. Borner aussi dimensions, volume décompressé, nombre d'éléments/variantes et profondeur lorsque pertinents. Examiner SVG, archives, documents et références externes selon les formats effectivement acceptés ; aucune interdiction de format ou obligation universelle de scanner ne se déduit de l'exemple. [Validation Laravel](https://laravel.com/framework/docs/13.x/validation#validating-files)

**R14-015 — Transformer avec des limites effectives.** Définir données/paramètres admis, moteur qualifié, temps/mémoire/concurrence et sortie attendue ; appliquer les limites avant allocation ou expansion excessive, pas seulement après. Traiter orientation, métadonnées et dérivés selon R09. Vérifier que le résultat est lisible et correspond au format/dimensions/version attendus avant utilisation. **Variante :** traitement synchrone borné si le budget le permet ; job R10 pour les coûts ou reprises qui le justifient. Isoler/scanner davantage quand le risque identifié l'exige, sans imposer une bibliothèque par défaut. Tout téléchargement distant ou ressource externe chargée par le moteur suit R08/R17.

**R14-016 — Traiter les échecs d'entrée/sortie et fermer les ressources.** Qualifier le retour de chaque API : écriture/suppression peuvent retourner `false` ou lever selon `throw` ; un flux reçu doit être une ressource valide. Une fin de méthode n'est pas une preuve de fichier complet. Fermer flux et temporaires sur succès, refus, erreur et annulation ; un `finally` ne couvre pas l'arrêt brutal, qui exige rapprochement/nettoyage. Classer et propager l'erreur à la responsabilité R22, en conservant la cause autorisée sans diagnostic brut dans l'interface. Ne pas convertir une panne en fichier vide, URL inventée ou succès. [Écritures Laravel](https://laravel.com/framework/docs/13.x/filesystem#failed-writes), [implémentation des flux](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Filesystem/FilesystemAdapter.php)

## 5. Finaliser, remplacer et nettoyer

**R14-017 — Identifier le résultat et sa version.** Relier source/version, paramètres de transformation, identité d'opération et fichiers produits. Écrire une nouvelle version sous une clé distincte ; ne pas écraser le fichier encore référencé ni modifier les octets d'une version publiée. Pour une reprise, retrouver le résultat déjà acquis ou produire une tentative isolée dont la publication sera arbitrée ; une clé stable ne protège pas seule deux écrivains concurrents. Qualifier capacités d'écriture, intégrité et disponibilité du driver ; ne pas présumer `move()` atomique ou un même protocole pour tous les stockages.

**R14-018 — Séparer stockage et transaction SQL.** Pour une ressource liée à une référence SQL, l'action acquiert contexte/identité/préconditions avec R07/R10, termine l'unité SQL, effectue le traitement borné, puis fait publier sa référence par le repository dans une transaction courte. Revalider autorité, source/version, opération et état/possession avant publication. Ne pas garder un verrou SQL pendant un traitement ou transfert long. Commit SQL et écriture du fichier ne sont pas une transaction distribuée ; rollback SQL ne retire pas automatiquement un objet déjà écrit.

**R14-019 — Rapprocher avant de déclarer ou répéter.** Examiner opération, objet et référence durable après panne. Distinguer objet préparé, finalisé, référence acquise et nettoyage restant ; intégrer le suivi R10 pour les résultats qui doivent survivre. Une ancienne tentative ne publie pas sur la version plus récente. Si le résultat du stockage/commit est incertain, retrouver sa preuve avant répétition ; appliquer les limites du contrat R17/R22 lorsque cette preuve est insuffisante. Un échec de nettoyage après publication ne défait pas la nouvelle référence.

**R14-020 — Supprimer seulement une version éligible.** Examiner références courantes/historiques, traitements et livraisons actifs, possibilités de reprise et conditions R09. L'absence de pointeur courant ne suffit pas. Quand publication et nettoyage peuvent concourir, acquérir sous protection R07 l'état autorisant la suppression et empêchant une publication tardive, puis effacer hors transaction longue et enregistrer/retrouver le résultat. Tous les chemins de publication respectent cette exclusion. Reprendre un effacement échoué ; ne marquer terminé qu'après preuve prévue. Borner le balayage, retrouver temporaires/orphelins après arrêt brutal et appliquer le même protocole aux corrections de l'existant.

**R14-021 — Versionner la distribution et coordonner la livraison.** Garder cohérents références, fichiers et manifeste d'une version ; définir cache/renouvellement selon visibilité et identité. Ne pas supprimer immédiatement les anciens assets encore nécessaires aux pages ouvertes ou au repli ; R21 possède leur maintien/nettoyage pendant déploiement. Pour un fichier mutable ou un accès contrôlé, ne pas réutiliser sans examen le cache d'un asset public immuable. Tester référentiels anciens et nouveaux, ressource retirée et cache réel. [Déploiement et anciens fichiers Vite](https://vite.dev/guide/build.html#load-error-handling)

## 6. Produire et distribuer les documents

**R14-022 — Générer depuis des données cohérentes.** Identifier source/version et paramètres ; acquérir un jeu de données cohérent selon R05/R06/R07 avant génération coûteuse. Une reprise conserve cette identité : ne pas relire des valeurs courantes incompatibles sous le nom du document déjà demandé. Borner lectures, mémoire, moteur et sortie ; vérifier le format produit avant finalisation. Une génération temporaire synchrone reste admissible si elle respecte le contrat et ses limites ; ne pas imposer un enregistrement durable de tout export.

**R14-023 — Protéger le générateur et le format de sortie.** Échapper/encoder les données pour leur contexte ; maîtriser templates, chemins et ressources chargeables par le moteur, notamment URL/réseau/fichiers locaux. L'accès arbitraire du moteur n'est pas autorisé par une donnée valide. Pour un export CSV/tableur contenant du texte externe, qualifier les risques d'interprétation en formule et les protections du format sans altérer silencieusement le sens des nombres. Vérifier polices, images, caractères et taille finale ; email/transfert suit R17, pas une nouvelle implémentation de livraison. [Cellules interprétées comme formules](https://community.owasp.org/attacks/CSV_Injection)

**R14-024 — Choisir et vérifier le mécanisme d'accès.** Résoudre référence/version et droit R08 avant restitution. Employer l'URL publique pour du contenu effectivement public ; pour du privé, route contrôlée ou URL temporaire selon le contrat. Une signature protège intégrité/échéance, pas automatiquement identité ou permission actuelle. Une URL présignée peut être utilisable par son détenteur sans nouvelle vérification Laravel ; qualifier partage, retrait et expiration du transport choisi. Prévoir cache, type, disposition et nom de téléchargement sûrs, ainsi que accès refusé/fichier absent. Pendant un flux commencé, une panne ne garantit plus une réponse HTTP ordinaire : fermer/signaler sans injecter HTML/JSON technique dans le document ni affirmer livraison réussie. [URLs temporaires Laravel](https://laravel.com/framework/docs/13.x/filesystem#temporary-urls)

**R14-025 — Retrouver les droits de la ressource.** Conserver auprès de son propriétaire provenance, référence de licence/version et obligations pertinentes : modification, redistribution, attribution et incorporation. Inclure polices, images, personnages et ressources générées/acquises. Vérifier l'usage effectivement prévu, pas seulement la disponibilité du téléchargement. Si le droit nécessaire n'est pas établi, clarifier avant la livraison dépendante ; la règle ne crée pas une autorisation de licence.

**R14-026 — Vérifier le parcours complet.** Sélectionner preuves de contenu, coût, accès, cohérence, erreurs et nettoyage selon la recette ci-dessous. Pour une correction, inclure fichiers anciens et consommateurs existants. Documenter API/versions/configuration et limites réellement qualifiées chez le propriétaire, selon R18/R20 ; ne pas déclarer une bibliothèque, un fournisseur, un build ou une performance validés sur la seule présence de l'exemple.

## Illustrations minimales

### A — Carte de produit, CSS et image adaptée

Hypothèses : la projection autorisée fournit les variantes d'une image vérifiée ; la page adapte le DTO R04 en propriétés locales de présentation. Tailwind, Vue et Vite seront raccordés/qualifiés selon leurs versions. L'image de cet exemple complète le titre sans information supplémentaire ; elle est décorative. Les autres textes d'interface sont déjà localisés par R13.

```text
resources/
├── css/
│   ├── app.css                         # Tailwind et imports des seules fondations
│   └── tokens.css                      # Fondations communes, si nécessaires
└── js/
    ├── app.ts                          # Importe ../css/app.css ; résolution R12
    ├── pages/Guest/Catalog/Show.vue     # Assemble via public/products.ts
    └── modules/catalog/
        ├── public/products.ts          # Export ciblé du composant
        └── products/cards/components/ProductCard/
            ├── ProductCard.vue
            ├── product-card.css
            └── types/ProductCardPresentation.ts
```

```ts
// types/ProductCardPresentation.ts — props locales, pas un second DTO de transport.
export type ProductCardPresentation = {
  title: string;
  imageUnavailableLabel: string;
  image: {
    src: string;
    srcset: string;
    sizes: string;
    width: number;
    height: number;
    loading: 'eager' | 'lazy';
  };
};
```

```vue
<!-- ProductCard.vue -->
<script setup lang="ts">
import { ref, watch } from 'vue';
import type { ProductCardPresentation } from './types/ProductCardPresentation';

const props = defineProps<ProductCardPresentation>();
const imageFailed = ref(false);
watch(() => props.image.src, () => { imageFailed.value = false; });
</script>

<template>
  <article class="product-card rounded-lg border p-3">
    <img v-if="!imageFailed" :src="image.src" :srcset="image.srcset"
      :sizes="image.sizes" :width="image.width" :height="image.height"
      :loading="image.loading" alt="" @error="imageFailed = true">
    <p v-else>{{ imageUnavailableLabel }}</p>
    <h3 class="mt-2 font-semibold">{{ title }}</h3>
  </article>
</template>

<style scoped src="./product-card.css"></style>
```

```css
/* product-card.css — besoin local illustratif. */
.product-card { min-width: 0; }
.product-card img { display: block; width: 100%; height: auto; }
.product-card h3 { overflow-wrap: anywhere; }
```

`public/products.ts` exporte seulement `ProductCard` depuis ce chemin ; `Show.vue` le consomme et lui fournit les propriétés adaptées. Le contrat de transport généré et cette adaptation suivent R04/R12, sans duplication manuelle de ses types. `width`/`height` décrivent le ratio réel et `sizes` la largeur de rendu prévue ; le propriétaire choisit `eager` si nécessaire au premier affichage. Une nouvelle URL remet à zéro l'échec, sans réessayer en boucle la même image.

**Séquence :** projection de variantes → adaptation de présentation → composant → imports Vite/CSS → choix de variante par le navigateur → affichage ou repli. Ajouter cette page, le composant et la feuille dans la convention ne change pas les entrées Vite. Vérifier style et requêtes réseau en navigation directe puis après une autre page, avant/après build. `scoped` n'établit pas une économie de transfert ; les utilitaires Tailwind et dépendances communes peuvent appartenir au socle partagé.

### B — Remplacer une image sans perdre la version acquise

Hypothèses : la référence actuelle désigne la version N ; le remplacement peut être différé et exige un résultat durable. Le contenu reste privé pendant préparation et l'accès illustré passe par sa référence autorisée. Formats, variantes et conservation proviennent des exigences. Aucune bibliothèque d'image ou structure de tables n'est adoptée.

```text
app/Modules/Catalog/Products/UseCases/ReplaceProductImage/
├── ReplaceProductImageController.php   # Validation du transport
├── ReplaceProductImageAction.php       # Autorisation/contexte et orchestration
├── ReplaceProductImageRepository.php   # Référence/version et transitions SQL
├── ReplaceProductImageJob.php          # Reprise R10, même opération
└── Images/
    ├── ImageVariantsGenerator.php      # Contrat : entrée privée + paramètres bornés
    └── PreparedImageResult.php         # Clés/version et métadonnées vérifiées
config/filesystems.php                 # Destination de préparation explicite
```

Le contrat illustratif du générateur reçoit une référence privée maîtrisée et les paramètres validés ; il retourne les variantes vérifiées, leur identité et métadonnées, ou l'échec classé. Son implémentation/binding reste à qualifier ; ce nom ne désigne pas une API existante de traitement d'images.

**Raccordement natif illustratif :** un disque de préparation local peut être configuré ainsi ; ce choix pédagogique ne sélectionne pas le stockage de production.

```php
// Fragment de config/filesystems.php, dans 'disks'.
'prepared_files' => [
    'driver' => 'local',
    'root' => storage_path('app/prepared-files'),
    'visibility' => 'private',
    'throw' => true,
],
```

**Séquence :** entrée autorisée/validée → action acquiert opération/version attendue N → préparation et variantes sous clés distinctes hors transaction longue → résultat vérifié → nouvelle transaction avec contexte, autorité et version revalidés → repository publie la référence → projection d'accès adaptée → nettoyage éligible. Si le job est requis, sa publication et son rapprochement suivent R10 ; SQL reste dans le repository. L'opération et ses résultats sont retrouvables même si le processus s'arrête entre deux étapes.

| Incident | Conduite et preuve à vérifier |
| --- | --- |
| Contenu invalide, variante incomplète ou écriture échouée | N reste acquise ; pas de nouvelle référence ; préparation retrouvable pour nettoyage. |
| Fichier vérifié, publication SQL échouée ou réponse perdue | Rapprocher objet/opération/référence ; aucune suppression de N ni répétition supposée sûre. |
| Deux remplacements ou ancienne tentative tardive | Publication conditionnée à la version/opération encore valable ; le résultat récent n'est pas écrasé. |
| Commit acquis, nettoyage de N échoué | Nouvelle référence conservée ; reprendre le seul nettoyage selon éligibilité. |
| Nettoyage concurrent à une publication | Acquisition de suppression exclut publication ; tous les finaliseurs vérifient cet état. |
| Fichier référencé devenu indisponible | Issue explicite pour cette version ; pas de substitution silencieuse par un autre contenu. |

Une variante publique exige la vérification préalable de son exposition et de ses caches ; l'absence de référence SQL ne protège pas un objet déjà rendu public. Supprimer une URL de la page ne retire pas une copie déjà téléchargée.

### C — Produire et télécharger un PDF privé

Hypothèses : une demande identifiée vise une version de données cohérente ; un moteur qualifié produit un PDF vérifié, conservé si le contrat l'exige. L'accès illustré est contrôlé à chaque nouvelle requête, sans URL présignée. Le moteur et son intégration sont omis ; leurs entrées/sorties sont décrites, pas prétendues opérationnelles.

```text
app/Modules/Documents/Exports/UseCases/
├── GenerateDocument/
│   ├── GenerateDocumentAction.php      # Orchestration ; résultat suivi si requis
│   ├── GenerateDocumentRepository.php  # Données/version et référence finale
│   └── Generation/
│       ├── PdfGenerator.php            # Contrat illustratif, moteur à qualifier
│       └── GeneratedPdfResult.php      # Identité, version, clé et taille vérifiées
└── DownloadDocument/
    ├── DownloadDocumentAction.php      # Autorise et résout la référence exacte
    └── DownloadDocumentController.php  # Ouvre et restitue ; extrait ci-dessous
```

**Séquence :** action acquiert données/version cohérentes → générateur borné avec ressources admises → PDF vérifié et référence finalisée → demande de téléchargement → action R08 résout la version autorisée et ses métadonnées → contrôleur ouvre le flux → réponse. Les reprises de génération conservent les données/paramètres de cette demande ; les variantes de production éphémère ou URL temporaire appliquent leurs propres garanties R14-022/024.

```php
// Extrait de DownloadDocumentController.php, après l'action autorisée.
// $diskName, $key, $downloadName, $expectedBytes viennent de sa référence
// vérifiée côté serveur ; aucun chemin ou nom d'en-tête brut du client.
use Illuminate\Support\Facades\Storage;

$stream = Storage::disk($diskName)->readStream($key);
if (! is_resource($stream)) {
    throw new RuntimeException('Document stream unavailable.');
}

try {
    return response()->streamDownload(
        static function () use ($stream, $expectedBytes): void {
            try {
                $sentBytes = fpassthru($stream);
                if ($sentBytes === false || $sentBytes !== $expectedBytes) {
                    throw new RuntimeException('Document transfer incomplete.');
                }
            } finally {
                fclose($stream);
            }
        },
        $downloadName,
        ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
    );
} catch (Throwable $exception) {
    // Responsabilité réelle : libérer si la construction de réponse échoue.
    if (is_resource($stream)) fclose($stream);
    throw $exception;
}
```

Cet extrait global nécessite les imports `RuntimeException`/`Throwable` s'il est intégré dans un namespace. Le nom de téléchargement et la taille positive entière sont validés en amont ; le cache montré correspond au document privé de cette illustration, pas à toute ressource. Le catch protège la construction de réponse, pas son exécution ultérieure. L'ouverture anticipée permet de traiter une indisponibilité avant streaming ; la fermeture protège les chemins exécutant le callback et le processus doit aussi libérer ses ressources si la réponse est abandonnée avant son exécution.

Avant envoi, la frontière R22 peut restituer l'erreur sûre selon le destinataire ; après début du flux, interrompre/signaler sans ajouter une erreur HTML/JSON au PDF. Comparer la taille détecte ici un flux incomplet, pas une intégrité cryptographique ni la réception par le client. Ni callback terminé ni réponse créée ne prouve que le destinataire a reçu le document. Une reprise de téléchargement vise la même version si son contrat l'autorise. [Streaming Laravel](https://laravel.com/framework/docs/13.x/responses#streamed-downloads), [lecture PHP](https://www.php.net/manual/en/function.fpassthru.php)

## Recette proportionnée au changement

| Risque | Preuve attendue |
| --- | --- |
| Raccordement CSS/Vite | Page + composant + CSS sans nouvelle entrée ; résolution, cascade et liens corrects en développement/build. |
| Chargement collectif | Arrivée directe/après navigation, cache vide/réutilisé, préchargements ; octets et dépendances réellement observés. |
| Médias | Variantes et dimensions cohérentes, polices/langues, dépendances lourdes ; échec de chargement et coût de décodage. |
| Entrée/transformations | Type falsifié, contenu illisible, dimensions/expansion/coût excessifs, sortie partielle ; aucun contenu non vérifié exposé. |
| Accès/configuration | Droit insuffisant, autre périmètre, clé/disque imposés, URL partagée/expirée selon mécanisme ; aucune fuite publique ou secret de build. |
| Pannes/concurrence | Arrêt avant/après stockage/commit, réponse perdue, tentative tardive ; version acquise préservée et reprise bornée. |
| Nettoyage | Ancienne version encore utilisée, préparation active, course publication/suppression et suppression échouée ; aucun fichier requis effacé. |
| Documents | Données/version, échappement, ressources du moteur, cellules de texte externes ; caractère lisible et format vérifié. |
| Distribution | Ressource absente, flux interrompu, cache et ancienne page ouverte ; aucun faux succès ou diagnostic injecté dans le fichier. |
| Droits/audit | Provenance/incorporation retrouvables ; corrections couvrant dérivés, références et consommateurs anciens. |

Ces preuves sont à sélectionner et réaliser selon le changement. Les simulations de stockage/codec ne qualifient pas le driver, le moteur, le navigateur ou les coûts réels. Aucun package, build, test applicatif ni essai de charge n'est exécuté par cette rédaction.
