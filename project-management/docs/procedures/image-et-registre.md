# Construire, vérifier et publier une image

**Version 1.2 - contenu validé ; parcours opérationnel à exercer.** Commandes et interfaces examinées dans les sources officielles citées ; recevoir leurs versions, droits et capacités effectives avant application.

**Résultat :** une image de production identifiée, contrôlée et conservée, puis la même image téléchargée depuis le registre choisi. Le responsable de la construction prépare la recette ; celui de la publication reçoit et publie le livrable ; celui du déploiement reçoit sa référence et ses preuves. Une personne peut cumuler ces responsabilités, avec ou sans agent. Publier une image ne l'active pas sur un serveur.

Les sources sont les fichiers du projet ; le Dockerfile décrit leur construction ; l'image contient le code et les composants livrés ; un conteneur exécute cette image avec sa configuration. Le registre conserve les images distribuables. Base de données, Redis, fichiers téléversés et secrets ne sont pas automatiquement contenus dans l'image applicative : leur fourniture et leur état restent séparés.

Ce guide couvre Docker et le circuit révision intégrée vérifiée → construction/réception CI → archive contrôlée → publication autorisée sans reconstruction. Trivy et GHCR sont des branches d'exemple lorsqu'ils sont retenus. Les [règles](../../../.ai/rules/index.md), notamment [R20](../../../.ai/rules/socle/20-environnement-dependances-qualite.md), [R21](../../../.ai/rules/socle/21-livraison-exploitation-reprise.md), [R08](../../../.ai/rules/socle/08-securite-identites-isolation.md) et [LIV-CI](../../../.ai/rules/projet/livraison/validation-ci-promotion-humaine.md), possèdent les garanties selon leurs statuts. Le profil du projet possède choix, paramètres et recettes ; consulter seulement les propriétaires nécessaires.

## 1. Recevoir les entrées et le parcours

| À recevoir    | Résultat nécessaire avant la phase concernée                                                                                                                                                                |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sources et CI | Dépôt, SHA réellement intégré et exécution/tentative acceptée selon [GitHub et collaboration](github-et-collaboration.md). Une proposition ou une fusion synthétique ne se substitue pas à cette référence. |
| Construction  | Docker/Buildx qualifiés, moteur, contexte, Dockerfile, bases et étapes, locks, générations, paramètres publics de compilation ; accès temporaires nécessaires.                                              |
| Production    | OS/architectures/variantes, PHP/extensions et logiciels requis, configurations/processus, utilisateur et chemins accessibles. Une image commune ou des images par rôle suivent le choix reçu.               |
| Contrôles     | Commandes et données d'essai isolées, capacités à recevoir, critères de poursuite, scanner/version/configuration, sévérités bloquantes et traitement autorisé des diagnostics.                              |
| Distribution  | Registre, propriétaire, nom, visibilité, association au dépôt, lecteurs/publicateurs, déclencheur et décision de publication.                                                                               |
| Conservation  | Supports et accès des archives/preuves/images, capacité, durée et références à préserver pour les reprises.                                                                                                 |

Clarifier un prérequis essentiel manquant avant sa phase, sans inventer une base, un seuil ou un droit pour débloquer le parcours. Qualifier d'abord une candidate complète dans un périmètre isolé, avant d'adapter tout le circuit CI. Cette candidate locale prépare la recette ; l'image sélectionnée pour publication proviendra ensuite du SHA réellement intégré et de sa CI reçue.

Les exemples suivants utilisent **Bash sur le poste/moteur de construction ou sur un runner autorisé**, hors du conteneur applicatif. Remplacer les valeurs entre `<…>`, vérifier le contexte et s'arrêter à chaque erreur avant la phase dépendante. Les commandes sont à intégrer à la recette du projet ; elles ne forment pas un lanceur universel.

```bash
IMG_SHA='<sha-integral-de-la-revision-concernee>'
IMG_PLATFORM='<plateforme-recue-par-exemple-linux/amd64>'
IMG_CONTEXT='<contexte-de-construction-recu>'
IMG_DOCKERFILE='<dockerfile-de-production-recu>'
IMG_TAG='<nom-et-tag-local-reserves-a-cette-construction>'
IMG_PROOF_DIR='<nouveau-dossier-absolu-prive-hors-contexte>'

docker version
docker buildx version
docker context show
git rev-parse HEAD
git status --short --untracked-files=all
```

Attendus : moteur/versions prévus, sources de la bonne référence et entrées connues. Dans une candidate locale, qualifier les modifications autorisées ; dans la CI publiable, recevoir le checkout exact et les entrées supplémentaires. Ni un label SHA ni un arbre Git propre ne prouvent seuls que le contexte ne contient aucun fichier étranger. Préparer le dossier de preuves avec droits et capacité adaptés ; ne pas le copier dans le contexte.

## 2. Préparer puis construire le runtime de production

### Choisir et entretenir l'assemblage

Sélectionner des bases maintenues compatibles avec la plateforme et les composants réellement nécessaires. Recevoir références exactes, provenance, versions et inventaire. Une base officielle ou une distribution particulière ne garantit pas un assemblage sans alertes. Épingler une base par digest rend cette entrée identifiable, mais ses correctifs demandent ensuite une mise à jour explicite et une requalification. [Pratiques Docker](https://docs.docker.com/build/building/best-practices/).

Organiser le Dockerfile en étapes : outils de construction PHP/Composer et frontend, puis runtime final contenant seulement le livrable et ses dépendances. Installer depuis les locks ; vérifier PHP/extensions réels et examiner les hooks Composer/npm avant exécution. Aucun `composer update`, nouvelle résolution npm ou outil manquant du poste ne remplace silencieusement ces entrées. Une génération nécessitant PHP doit disposer de son runtime pendant le build frontend. [Construction multiétape](https://docs.docker.com/build/building/multi-stage/).

Préparer les copies ciblées vers l'étape finale : code requis, `vendor` de production, migrations, vues, assets compilés et configurations des processus. Exclure outils de développement inutiles, sources temporaires, `public/hot`, credentials et caches Laravel issus de l'environnement de construction. Garder l'inventaire des logiciels et des dépendances même si leurs fichiers de construction ne sont pas distribués. Sail prépare le développement ; son image, ses montages et ses outils ne deviennent pas le profil de production.

Classer les paramètres : une valeur Vite intégrée au build est publique et n'est pas réécrite par le `.env` du serveur. Les valeurs de runtime et les secrets arrivent à l'exécution par les mécanismes reçus. Aucun accès à la base de production, migration réelle, clé du serveur ou jeu de données réel pendant la construction. Si un accès privé temporaire est nécessaire, utiliser un secret BuildKit fourni à la seule étape concernée, jamais un credential dans `ARG`, `ENV`, URL, couche ou log. [Secrets de construction](https://docs.docker.com/build/building/secrets/).

Recevoir [sélection du contexte et exclusions Docker](documentation-et-partage.md) ; `COPY . .` sans examen et suppression ultérieure d'un secret ne constituent pas une protection. Une fuite exige confinement/rotation selon R08 et reconstruction depuis des entrées propres, pas effacement des métadonnées pour cacher l'alerte.

### Construire et identifier

Exemple **une plateforme**, avec Buildx et chargement dans le moteur reçu :

```bash
docker buildx build --load --platform "$IMG_PLATFORM" \
    --file "$IMG_DOCKERFILE" --tag "$IMG_TAG" \
    --label "org.opencontainers.image.revision=$IMG_SHA" \
    --metadata-file "$IMG_PROOF_DIR/build.json" "$IMG_CONTEXT"
```

Ajouter les arguments non secrets et montages temporaires déclarés par la recette. Un cache peut accélérer la construction, mais ses entrées et droits doivent être reçus ; il ne remplace aucun contrôle. Pour plusieurs plateformes, recevoir chaque variante et la conservation de l'index/provenance exigés : cet exemple d'archive Docker n'atteste pas à lui seul un ensemble multiarchitecture. [Buildx](https://docs.docker.com/reference/cli/docker/buildx/build/).

Recevoir aussi le digest de configuration OCI fourni dans `build.json` par `containerimage.config.digest`, pour la plateforme construite. Si l'exporteur ne le fournit pas, qualifier sa lecture officielle avant de poursuivre ; ne pas le remplacer par un champ `.Id` supposé équivalent.

```bash
IMG_ID="$(docker image inspect "$IMG_TAG" --format '{{.Id}}')"
test -n "$IMG_ID"
docker image inspect "$IMG_ID" --format '{{.Os}}/{{.Architecture}} {{.Variant}}'
docker image inspect "$IMG_ID" --format '{{json .RootFS.Layers}}'
docker image inspect "$IMG_ID" > "$IMG_PROOF_DIR/image.json"
IMG_ARCHIVE="$IMG_PROOF_DIR/image.tar"
docker image save --output "$IMG_ARCHIVE" "$IMG_ID"
sha256sum "$IMG_ARCHIVE"
```

Recevoir plateforme, identité, configurations, couches et contenu final dans les preuves protégées. Les métadonnées ou rapports peuvent révéler des informations sensibles ; ne pas publier automatiquement ces sorties brutes. Cette archive sert au scan et, après réception, au transfert. `save` conserve l'image ; ne pas utiliser `export/import` d'un conteneur modifié comme substitut. [Inspection](https://docs.docker.com/reference/cli/docker/image/inspect/), [archive Docker](https://docs.docker.com/reference/cli/docker/image/save/).

## 3. Scanner l'assemblage complet et résoudre les alertes

### Recevoir la portée et les résultats

Identifier scanner/version, configuration, base d'avis et fraîcheur. Vérifier distribution, paquets et dépendances reconnus ; examiner exclusions, variables héritées et fichiers implicites. Avec Trivy, recevoir notamment `.trivyignore`, configuration et règles de secrets : une exclusion présente dans le checkout n'est pas automatiquement autorisée.

Coupler le scan final à l'inventaire : paquets Linux ; dépendances Composer ; dépendances frontend avant compilation ; logiciels/extensions compilés hors gestionnaire. Les bundles navigateur et certains binaires ne rendent pas leur inventaire au scanner. Conserver les audits des locks/sources dans la CI, par exemple `composer audit --locked` et `npm audit --audit-level=<seuil-recu>` avec leurs profils et résultats reçus ; aucun `audit fix` automatique. Vérifier les avis officiels des composants non couverts. [Couverture Trivy](https://trivy.dev/docs/latest/scanner/vulnerability/), [Composer](https://getcomposer.org/doc/03-cli.md#audit), [npm](https://docs.npmjs.com/cli/v11/commands/npm-audit/).

Exemple **Trivy installé et qualifié**, sur l'archive, sans accès au socket Docker :

```bash
IMG_BLOCK_SEVERITIES='<severites-bloquantes-reçues-separees-par-virgules>'
trivy --version
trivy image --input "$IMG_ARCHIVE" --scanners vuln \
    --pkg-types os,library --severity UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL \
    --list-all-pkgs --format json \
    --output "$IMG_PROOF_DIR/vulnerabilites.json" --exit-code 0
```

Le premier scan conserve l'inventaire et les alertes, toutes sévérités ; son `--exit-code 0` **ne reçoit pas la sécurité**. Exécuter ensuite les deux gardes, chacune devant réussir :

```bash
trivy image --input "$IMG_ARCHIVE" --scanners vuln --pkg-types os,library \
    --severity "$IMG_BLOCK_SEVERITIES" --exit-code 1
trivy image --input "$IMG_ARCHIVE" --scanners secret \
    --image-config-scanners secret --severity UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL \
    --format json --output "$IMG_PROOF_DIR/secrets.json" --exit-code 1
```

L'option de métadonnées complète le scan des fichiers pour les secrets dans la configuration/historique de l'image. Trivy possède aussi des règles d'autorisation et peut éviter certaines couches de base : recevoir ces limites, sans annoncer une couverture exhaustive. Zéro détection ne remplace pas prévention et examen du contenu. Erreur d'outil/base/réseau, rapport manquant, scan interrompu ou inventaire non reconnu ne valent pas réussite. Conserver diagnostic et code natif ; arrêter la phase dépendante si la politique bloque. [Scan d'image](https://trivy.dev/docs/latest/target/container_image/), [secrets](https://trivy.dev/docs/latest/scanner/secret/).

### Résoudre sans tourner en boucle

Pour chaque CVE bloquante, relever paquet/version/distribution, composant effectivement présent, dépendance introductrice et correction annoncée. Plusieurs lignes peuvent concerner la même CVE ; regrouper son diagnostic sans perdre les composants touchés. Comparer avis de la distribution et du fournisseur : un correctif rétroporté peut porter une version différente de celle de l'amont.

| Situation                                          | Action et résultat attendu                                                                                                                                                                                                  |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Version maintenue corrigée disponible              | Mettre à jour la dépendance ou référence concernée ; reconstruire, rescanner et reprendre les preuves affectées.                                                                                                            |
| Utilitaire inutile introduit                       | Retirer seulement après examen des dépendances et usages ; préserver bibliothèques requises et métadonnées du gestionnaire.                                                                                                 |
| Pas de correctif annoncé pour cette distribution   | Examiner autre version/variante ou base maintenue compatible. L'absence de `FixedVersion` ne signifie ni sécurité ni impossibilité universelle de correction. Qualifier l'assemblage alternatif avant intégration générale. |
| Diagnostic inapplicable ou correctif mal reconnu   | Fournir la preuve précise et les avis correspondants ; faire recevoir le traitement du diagnostic selon la politique. Un composant absent n'autorise pas une exclusion tacite.                                              |
| Risque réellement présent sans solution compatible | Remettre le blocage au responsable. Une éventuelle dérogation doit être explicitement permise, ciblée, motivée et bornée ; elle ne transforme pas le risque en correctif.                                                   |

Ne pas ajouter `--ignore-unfixed`, abaisser le seuil, changer de scanner pour dissimuler un résultat ou supprimer des fichiers d'inventaire pour poursuivre. Un changement de base, driver ou branche logicielle suit les choix/habilitations du projet. Lorsqu'une solution compatible respecte déjà ces choix, la correction appartient au lot autorisé. Un scan sans alerte reste daté, pour une image et une couverture précises.

## 4. Vérifier la même image dans les conditions livrées

Après réception de la sécurité, utiliser l'identité exacte dans la recette isolée ; aucun montage de sources ou `vendor` de développement ne doit remplacer le contenu livré. Fournir configuration et secrets d'essai selon chaque rôle. Une commande d'entrée doit transmettre les signaux au processus réel ; vérifier utilisateur, droits des chemins inscriptibles et absence d'escalade inutile.

| Capacité sélectionnée        | Réception utile                                                                                                                                      |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Plateforme PHP               | PHP/extensions/bibliothèques réels et exigences de plateforme de production ; ne pas se fier seulement au builder ou à `config.platform`.            |
| HTTP et frontend             | Réponse utile, manifest et ressources compilées servis, Vue/Inertia si présents, absence de dépendance à Vite dev ou `public/hot`.                   |
| SQL/Redis                    | Connexions réelles sous identités et privilèges d'essai reçus ; schéma d'essai préparé par son rôle dédié. Un simple port ouvert ne suffit pas.      |
| Horizon/workers/ordonnanceur | Processus configurés et effet technique attendu constaté, sans scénario métier ou sortie externe involontaire.                                       |
| Reverb                       | Connexion WebSocket réellement acceptée depuis l'origine prévue, refus de l'origine non permise et comportement d'arrêt constaté.                    |
| Images/PDF                   | Driver, politique et polices réellement chargés ; traitement et document effectivement produits avec les seules dépendances de production.           |
| Arrêts                       | SIGTERM/mécanismes reçus, délai, connexions/jobs concernés et état constaté. Un signal envoyé ou un healthcheck vert ne prouve pas l'arrêt gracieux. |

Réutiliser sondes et tests existants adaptés. Les outils de contrôle peuvent rester dans un runtime de test séparé ; ils ne deviennent pas des dépendances de l'image finale. Une assertion incorrecte se corrige en vérifiant le contrat réel, sans changer l'exigence pour masquer un défaut. Un fonctionnement local sous Sail ne remplace pas cette réception.

Raccorder en CI les contrôles du code et de l'image au résultat bloquant selon le [guide GitHub](github-et-collaboration.md). Le checkout, les locks, le build, les scans et la recette doivent concerner la révision identifiée. Une phase requise sautée/annulée/échouée bloque la réception. Après interruption, constater les ressources et opérations avant reprise incompatible ; un arrêt réussi ultérieur ne résout pas automatiquement l'incident précédent.

## 5. Conserver le reçu et publier sans reconstruction

Une modification limitée à des documents exclus du contexte de construction ne change pas les sources applicatives distribuées et n'impose pas, à elle seule, une nouvelle publication au registre. La CI peut néanmoins construire une image pour vérifier chaque révision selon le circuit retenu. Distinguer cette construction automatique de la décision de publication ; une image conservée garde sa véritable révision source, même si la documentation du dépôt a avancé.

### Les identités ont des fonctions différentes

| Identifiant                           | Ce qu'il identifie                                                                                                                                     |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| SHA Git                               | Révision source ; ne prouve pas seul ce qui a été construit.                                                                                           |
| Exécution/tentative CI                | Production et réception des résultats ; lier aux entrées et contrôles réellement exécutés.                                                             |
| Image ID Docker                       | Identité renvoyée par le moteur/backend/API ; sa représentation doit être reçue, elle n'est pas universellement le digest de configuration.            |
| Digest de configuration OCI           | Empreinte du contenu de configuration, qui référence les couches décompressées ordonnées ; reçu depuis la construction et rapproché à la distribution. |
| SHA-256 de l'archive                  | Octets exacts du fichier transféré ; une nouvelle archive peut avoir une autre empreinte même pour la même image.                                      |
| Digest d'artefact Actions, si utilisé | Archive de transport GitHub, distincte de l'archive Docker qu'elle contient.                                                                           |
| Digest du registre                    | Manifeste de l'image ou index de variantes ; il n'a pas à être égal à l'Image ID.                                                                      |

Le reçu relie ces identités, plateformes, références des bases/locks, date/portée des scans, résultats requis et limites. Protéger son origine et ses droits : un hash joint par un producteur non fiable ou un label déclaratif ne suffit pas. Pour une plateforme, rapprocher digest de configuration, plateforme et liste ordonnée des couches. Comparer les Image IDs seulement dans une représentation reçue comme compatible : certains moteurs containerd renvoient un manifeste/index à la place du digest de configuration. Un écart ne s'accepte ni ne se refuse aveuglément ; qualifier sa sémantique sans enlever le contrôle du contenu. Pour plusieurs plateformes ou preuves de provenance/signature requises, conserver et recevoir aussi l'index et ces preuves ; le chemin simplifié ne doit pas les perdre. [Configuration OCI](https://github.com/opencontainers/image-spec/blob/main/config.md), [manifeste OCI](https://github.com/opencontainers/image-spec/blob/main/manifest.md), [lecture Docker/containerd](https://github.com/moby/moby/blob/docker-v29.8.0/daemon/containerd/image_inspect.go).

### Prévoir la capacité et la conservation

Distinguer caches réutilisables, archives d'images, preuves de contrôle et versions publiées au registre. Pour chaque catégorie, préciser support, taille, durée, quota applicable et opérations qui en dépendent. Les espaces ne partagent pas nécessairement leur quota : les caches Actions ont leur propre allocation, tandis que les artefacts Actions et certains stockages Packages partagent une allocation. Reconsulter les conditions du registre retenu ; GHCR annonce actuellement son stockage et sa bande passante gratuits. [Facturation Actions](https://docs.github.com/en/billing/concepts/product-billing/github-actions), [facturation Packages](https://docs.github.com/en/billing/concepts/product-billing/github-packages).

Estimer le besoin avec la taille mesurée, la fréquence des fusions, les relances et les générations conservées. Compter les fichiers déjà présents, les nouvelles productions, les preuves et une marge ; prévoir une rétention compatible avec le délai réel de publication, de vérification et de reprise. Distinguer occupation courante, consommation cumulée de facturation et plafond interne du projet. Indiquer si ce dernier représente un total conservable ou de la place encore disponible, afin de ne pas compter deux fois l'occupation existante.

Un compteur arrondi, une consommation passée ou un accès refusé ne prouvent ni saturation ni libération. Si le disponible exact est inaccessible, utiliser l'inventaire accessible et une estimation prudente des éléments inconnus, puis borner les opérations pour rester dans l'enveloppe autorisée. Si cette estimation ne suffit pas, proposer une réduction de conservation, un retrait ciblé ou une capacité supplémentaire à recevoir ; ne pas augmenter un budget ni exiger indéfiniment un relevé indisponible.

Définir les critères de retrait : référence exacte, durée utile échue et absence de dépendance encore nécessaire à une publication, une vérification ou une reprise. Recevoir l'autorisation de suppression selon les habilitations ; préserver les images et preuves encore requises, puis constater le retrait et actualiser l'inventaire. Une archive récente n'est pas automatiquement dispensable, et un nettoyage global ne remplace pas cette sélection. La suppression diminue l'occupation courante sans effacer la consommation passée déjà comptabilisée.

### Sélectionner et recevoir le transfert

Après tous les résultats requis, conserver l'archive, son empreinte et le reçu dans les supports autorisés. Une archive locale n'est pas choisie pour remplacer l'image de la CI. Avec Actions, utiliser un nom d'artefact unique pour l'exécution et la tentative, relier explicitement le SHA dans le reçu et recevoir également l'ID de l'artefact depuis la publication de cette tentative.

```bash
IMG_GH_REPO='<proprietaire/depot>'
IMG_RUN_ID='<identifiant-du-run-recu>'
IMG_RUN_ATTEMPT='<tentative-recue>'
IMG_ARTIFACT_ID='<identifiant-artefact-du-recu>'
IMG_ARTIFACT_NAME='<nom-unique-de-la-construction-recue>'
IMG_DOWNLOAD_DIR='<nouveau-dossier-prive-hors-contexte>'

gh run view "$IMG_RUN_ID" --repo "$IMG_GH_REPO" --attempt "$IMG_RUN_ATTEMPT"
gh api "repos/$IMG_GH_REPO/actions/artifacts/$IMG_ARTIFACT_ID" \
    --jq '{id,name,expired,digest,workflow_run}'
```

Recevoir CI réussie de cette tentative, SHA/run/ID/nom correspondants, artefact non expiré et producteur autorisé. L'API ne donne pas directement sa tentative : la relier au reçu et à l'étape d'upload identifiée. `gh run download` ne possède pas `--attempt` ; ne pas prendre automatiquement l'artefact le plus récent. Après ces vérifications, télécharger le nom unique et vérifier que son ID n'a pas été remplacé :

```bash
gh run download "$IMG_RUN_ID" --repo "$IMG_GH_REPO" \
    --name "$IMG_ARTIFACT_NAME" --dir "$IMG_DOWNLOAD_DIR"
```

Si l'artefact manque/expire ou ses références divergent, arrêter et choisir une reprise avec preuves reçues, pas une reconstruction discrète du même tag. Le contrôle automatique de `download-artifact` ne produit qu'un avertissement en cas de divergence : recevoir bloquamment l'empreinte attendue de l'archive Docker depuis le reçu préalablement accepté. [CLI](https://cli.github.com/manual/gh_run_download), [API artefacts](https://docs.github.com/en/rest/actions/artifacts), [intégrité Actions](https://docs.github.com/en/actions/tutorials/store-and-share-data#validating-artifacts).

```bash
IMG_ARCHIVE='<archive-docker-recue>'
IMG_EXPECTED_ARCHIVE_SHA256='<empreinte-du-recu-autorise>'
IMG_EXPECTED_CONFIG_DIGEST='<sha256:configuration-de-la-plateforme-dans-le-recu>'

printf '%s  %s\n' "$IMG_EXPECTED_ARCHIVE_SHA256" "$IMG_ARCHIVE" \
    | sha256sum --check --status \
    && tar --extract --to-stdout --file "$IMG_ARCHIVE" manifest.json
```

Cet exemple reçoit une archive Docker avec `manifest.json` ; recevoir format et outils `tar`/`sha256sum` avant transfert. Lire le membre `Config` de l'entrée de la plateforme attendue, puis vérifier les octets de cette configuration avant chargement. Un layout OCI différent demande sa lecture qualifiée, pas une conversion improvisée :

```bash
IMG_ARCHIVE_CONFIG_MEMBER='<chemin-Config-lu-dans-le-manifeste-de-la-variante>'
(
    set -euo pipefail
    IMG_CONFIG_SHA256="$(tar --extract --to-stdout --file "$IMG_ARCHIVE" \
        "$IMG_ARCHIVE_CONFIG_MEMBER" | sha256sum | cut -d ' ' -f 1)"
    test "sha256:$IMG_CONFIG_SHA256" = "$IMG_EXPECTED_CONFIG_DIGEST"
    docker image load --input "$IMG_ARCHIVE"
)
```

Relever la référence réellement annoncée par `load`, puis l'inspecter ; utiliser cette référence reçue comme `IMG_LOADED_REF` pour la publication. Inspecter un ancien ID déjà présent ne prouverait pas le chargement. Confronter plateforme/configuration/couches au reçu ; une différence de représentation d'ID n'autorise aucune différence de contenu.

```bash
IMG_LOADED_REF='<reference-ou-identifiant-restitue-par-load>'
docker image inspect "$IMG_LOADED_REF" --format '{{.Os}}/{{.Architecture}} {{.Variant}}'
docker image inspect "$IMG_LOADED_REF" --format '{{json .RootFS.Layers}}'
```

Le chargement et le retag ne reconstruisent pas l'image. Rescanner cette même archive avec une base actualisée avant publication si les conditions ont évolué ; conserver les preuves encore valides et reprendre celles invalidées. [Chargement Docker](https://docs.docker.com/reference/cli/docker/image/load/).

### Déclencher une publication avec GitHub Actions

Cette variante s'applique lorsque le projet a préparé un workflow de publication manuelle. Un workflow est un programme décrit dans un fichier de `.github/workflows/`. Il réalise sur le runner la sélection, le transfert et les contrôles de l'archive reçue, puis sa publication sans reconstruction. La publication Docker locale décrite ensuite constitue une autre voie, à choisir selon la recette du projet.

Retrouver son nom affiché, son fichier et ses instructions dans la recette partagée du projet, selon le [guide GitHub](github-et-collaboration.md#4-raccorder-la-ci-et-les-protections). Le workflow doit être présent sur la branche par défaut et accepter `workflow_dispatch` pour être lancé manuellement. Vérifier le compte autorisé, la branche d'exécution et les accès protégés prévus. La branche choisie fournit le programme de publication ; la révision de l'image à publier est une sélection distincte, provenant de la CI reçue.

Préparer les valeurs avant le lancement. Dans GitHub, ouvrir **Actions > exécution de construction retenue > résumé**, puis ses résultats et artefacts. La recette du projet précise, pour chaque champ du formulaire, le fichier ou la rubrique où lire sa valeur ; les noms des champs et des fichiers ne sont pas universels.

| Information demandée par le workflow               | Où retrouver la valeur attendue                                                                                                                                        |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Branche d'exécution et opération                   | Recette actuelle de publication : branche autorisée, opération de publication ou de vérification selon le circuit retenu.                                              |
| Révision source de l'image                         | SHA complet du commit intégré, rapproché du reçu de construction ; pas celui d'une proposition ou de la branche courante par défaut.                                   |
| Exécution et tentative de construction             | Identifiant de l'exécution CI et tentative ayant produit les preuves reçues ; ne pas choisir automatiquement la dernière tentative.                                    |
| Archive et empreinte                               | Identité de l'artefact et SHA-256 de l'archive Docker indiqués par le reçu et les preuves de cette construction. L'empreinte du ZIP de transport peut être différente. |
| Identité de l'image                                | Preuve de construction, avec le type d'identité attendu par le workflow ; conserver les distinctions du tableau précédent.                                             |
| Référence publiée pour une vérification ultérieure | Registre et digest obtenus à la publication de cette image, selon les champs prévus par le projet.                                                                     |

Les noms, paramètres non secrets et instructions courantes appartiennent à la recette partageable du projet. Les jetons et mots de passe sont fournis par les mécanismes protégés prévus ; ne pas les saisir dans le formulaire du workflow ni dans ses arguments CLI. Les journaux personnels et preuves brutes restent dans leurs supports autorisés.

Depuis le navigateur, après la décision de publication :

1. Ouvrir le dépôt, puis **Actions**, et sélectionner le workflow par son nom dans la colonne de gauche.
2. Cliquer sur **Run workflow**, choisir la branche autorisée et renseigner les champs avec les valeurs préparées.
3. Relire la sélection, puis cliquer sur **Run workflow** pour demander l'exécution.
4. Ouvrir l'exécution correspondante et suivre ses étapes. Si un environnement protégé attend une approbation, la personne habilitée la donne depuis cette exécution selon la politique retenue.
5. Attendre le résultat final, examiner les contrôles requis et relever le registre, le tag et le digest réellement publiés dans le résumé et les preuves prévus.

Un bouton absent peut signaler un workflow non prévu pour le lancement manuel ou des droits insuffisants : diagnostiquer ces prérequis. Demander une exécution ne signifie ni que l'approbation est acquise, ni que la publication a réussi. [Lancement manuel](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow), [environnements protégés](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).

Depuis un terminal Bash dont l'identité GitHub est reçue, le lancement équivalent peut recueillir les champs de manière interactive :

```bash
IMG_GH_REPO='<proprietaire/depot>'
IMG_PUBLICATION_WORKFLOW='<fichier-du-workflow-de-publication>'
IMG_WORKFLOW_REF='<branche-autorisee-contenant-le-workflow>'

gh workflow run "$IMG_PUBLICATION_WORKFLOW" --repo "$IMG_GH_REPO" \
    --ref "$IMG_WORKFLOW_REF"
```

Renseigner les mêmes valeurs que dans l'interface. Une recette non interactive peut utiliser `-f nom=valeur` pour chaque entrée prévue ; elle indique les noms exacts et toutes les valeurs nécessaires. Les autorisations et approbations restent applicables au lancement par CLI. [Commande de lancement](https://cli.github.com/manual/gh_workflow_run).

Retrouver ensuite l'exécution liée à cette demande, sans en sélectionner une autre uniquement parce qu'elle est plus récente :

```bash
gh run list --repo "$IMG_GH_REPO" --workflow "$IMG_PUBLICATION_WORKFLOW" \
    --branch "$IMG_WORKFLOW_REF" --event workflow_dispatch \
    --json databaseId,attempt,headSha,createdAt,status,conclusion,url

IMG_PUBLICATION_RUN='<identifiant-de-la-publication-selectionnee>'
IMG_PUBLICATION_ATTEMPT='<tentative-selectionnee>'
gh run view "$IMG_PUBLICATION_RUN" --repo "$IMG_GH_REPO" \
    --attempt "$IMG_PUBLICATION_ATTEMPT"
```

Rapprocher l'opération, les paramètres et les résultats de la sélection autorisée, puis recevoir les étapes requises et le digest publié. Une exécution en attente, annulée ou partiellement réussie n'est pas une publication reçue. Avant une reprise, constater l'état du registre pour ne pas répéter aveuglément une opération déjà effectuée. [Liste des exécutions](https://cli.github.com/manual/gh_run_list), [examen d'une tentative](https://cli.github.com/manual/gh_run_view).

### Publier dans GHCR lorsqu'il est retenu

Recevoir nom `ghcr.io/<proprietaire>/<image>:<tag-de-version>`, droits et visibilité. Un tag, même nommé par SHA, reste mutable ; le digest sera la référence finale. Le premier push crée le package, privé par défaut. Associer le dépôt et vérifier l'accès du workflow ; le nom ressemblant au dépôt ne réalise pas automatiquement cette association. Une image publique rend son code téléchargeable : ne pas changer la visibilité pour contourner un accès ou un problème de capacité. [GHCR](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry).

Depuis le profil ou l'organisation GitHub : **Packages → package concerné → Package settings**, avec droits d'administration. Recevoir visibilité, association et accès des personnes/workflows. Pour la page descriptive, utiliser les métadonnées OCI prévues ; ce n'est pas nécessairement le README entier du dépôt. Ne pas confondre droits du dépôt et droits du package.

La connexion Git ou `gh` ne vaut pas authentification Docker. En Actions, utiliser le `GITHUB_TOKEN` avec `packages: write` seulement si les droits effectifs respectent la séparation retenue entre contribution et publication. Un contributeur autorisé à modifier un workflow ne doit pas pouvoir s'accorder lui-même la publication de l'image officielle. Des permissions par défaut en lecture, une condition sur l'acteur ou la protection de `main` ne démontrent pas seules cette séparation : un workflow peut demander davantage de droits dans les limites accordées par GitHub. [Permissions des workflows](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/enabling-features-for-your-repository/managing-github-actions-settings-for-a-repository).

Vérifier ensemble droits du dépôt, héritage et accès du package, permissions des jetons et protection des éventuels environnements et secrets de publication, selon le forfait et la visibilité du dépôt. Si le jeton automatique ne permet pas la séparation requise, retenir un accès distinct, limité à la publication autorisée, protégé et fourni à la seule étape concernée. Le choix dépend du projet ; un PAT n'est pas une obligation universelle. Vérifier les succès et refus attendus sous les identités concernées, selon un périmètre autorisé, en distinguant réglages relus et opérations réellement éprouvées. [Accès des packages](https://docs.github.com/en/packages/learn-github-packages/configuring-a-packages-access-control-and-visibility), [environnements protégés](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).

Ne pas remettre ces credentials à une PR non fiable. Hors Actions, GHCR documente le PAT classique et ses scopes adaptés ; un lecteur privé n'a besoin ni de publier ni de supprimer. Fournir le credential par entrée standard, jamais comme argument de commande ou dans les logs.

Exemple **publication locale autorisée sur moteur Unix**, dans un contexte de credentials privé neuf. Recevoir moteur/socket, identité du moteur, dossier d'authentification et fichier de credential protégé hors sources/contexte/montages. Préparer dans ce dossier un `config.json` explicite, sans magasin partagé :

```json
{ "auths": { "ghcr.io": {} } }
```

Un dossier vide peut autodétecter un gestionnaire de credentials ; cette configuration explicite évite cette sélection pour l'exemple. Le stockage fichier est encodé, pas chiffré : protéger dossier/fichiers et cycle du secret selon le profil. Un helper choisi autrement doit être qualifié pour l'isolation voulue. [Configuration Docker](https://github.com/docker/cli/blob/master/cli/config/config.go), [stockage des credentials](https://docs.docker.com/reference/cli/docker/login/#credential-stores).

```bash
IMG_ENGINE_ENDPOINT='<socket-unix-du-moteur-recu>'
IMG_ENGINE_ID='<identifiant-du-moteur-recu>'
IMG_AUTH_DIR='<dossier-prive-authentification-prepare>'
IMG_REGISTRY_USER='<compte-publicateur-recu>'
IMG_REGISTRY_SECRET_FILE='<fichier-prive-du-credential-recu>'
IMG_REGISTRY_TAG='<ghcr.io/proprietaire/image:tag-recu>'

(
    set -eu
    unset DOCKER_CONTEXT DOCKER_HOST DOCKER_AUTH_CONFIG DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
    export DOCKER_HOST="$IMG_ENGINE_ENDPOINT" DOCKER_CONFIG="$IMG_AUTH_DIR"
    test "$(docker info --format '{{.ID}}')" = "$IMG_ENGINE_ID"
    docker login ghcr.io --username "$IMG_REGISTRY_USER" --password-stdin \
        < "$IMG_REGISTRY_SECRET_FILE"
    docker image tag "$IMG_LOADED_REF" "$IMG_REGISTRY_TAG"
    docker image push "$IMG_REGISTRY_TAG"
)
```

Recevoir le digest publié depuis le résultat du push et les métadonnées du registre ; préserver la chaîne vers le reçu, pas seulement un écran de package. Le sous-shell isole les variables et les options du terminal ; aucun `context use`, login/logout global ou remplacement des credentials des autres comptes. Un autre moteur/profil TLS suit sa recette qualifiée.

## 6. Recevoir le téléchargement et transmettre

La recette peut réaliser cette réception dans la même exécution que la publication ou par un workflow distinct. Dans ce dernier cas, reprendre le parcours de lancement avec l'opération de vérification et le digest réellement publié, en conservant le lien vers la même construction. Un contrôle public utilise le contexte anonyme prévu ; une image privée se vérifie avec les seuls accès de lecture autorisés. Le résultat de cette réception ne déclenche un déploiement serveur que si une procédure distincte l'a explicitement prévu et autorisé.

Dans un moteur de réception autorisé, distinct ou explicitement identifié, recevoir le digest et la plateforme. Utiliser le contexte Docker du **lecteur** : droits de lecture pour le privé ; pour le public, contexte neuf sans authentification avec le `config.json` explicite de l'étape 5. Ne pas utiliser le contexte du publicateur pour prétendre un téléchargement anonyme. Aucun besoin de supprimer une image ou des données du poste pour vérifier le téléchargement.

```bash
IMG_REGISTRY_IMAGE='<ghcr.io/proprietaire/image>'
IMG_REGISTRY_DIGEST='<sha256:manifeste-ou-index-publie>'
IMG_PULL_REF="$IMG_REGISTRY_IMAGE@$IMG_REGISTRY_DIGEST"
IMG_PLATFORM_MANIFEST='<sha256:manifeste-recu-pour-cette-plateforme>'
IMG_CONTENT_REF="$IMG_REGISTRY_IMAGE@$IMG_PLATFORM_MANIFEST"
IMG_READ_AUTH_DIR='<dossier-neuf-prive-du-contexte-lecteur-prepare>'
IMG_READ_ACCESS='<public-ou-prive-selon-le-profil>'
# Pour le prive, recevoir aussi IMG_READER_USER et IMG_READER_SECRET_FILE.

(
    set -eu
    unset DOCKER_CONTEXT DOCKER_HOST DOCKER_AUTH_CONFIG DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
    export DOCKER_HOST="$IMG_ENGINE_ENDPOINT" DOCKER_CONFIG="$IMG_READ_AUTH_DIR"
    test "$(docker info --format '{{.ID}}')" = "$IMG_ENGINE_ID"
    case "$IMG_READ_ACCESS" in
        public) ;;
        prive)
            docker login ghcr.io --username "$IMG_READER_USER" --password-stdin \
                < "$IMG_READER_SECRET_FILE"
            ;;
        *) exit 1 ;;
    esac
    docker buildx imagetools inspect --raw "$IMG_PULL_REF"
    docker buildx imagetools inspect --raw "$IMG_CONTENT_REF"
)
```

Recevoir d'abord le manifeste : si le digest publié désigne un index, relever le manifeste enfant de la plateforme et son appartenance à cet index ; sinon les deux digests sont identiques. La sortie doit être le manifeste de cette variante, dont `config.digest` correspond au digest de configuration reçu. Relever sa valeur réelle, puis poursuivre sous le même contexte lecteur :

```bash
IMG_REGISTRY_CONFIG_DIGEST='<valeur-config.digest-du-manifeste-lu>'
(
    set -eu
    unset DOCKER_CONTEXT DOCKER_HOST DOCKER_AUTH_CONFIG DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
    export DOCKER_HOST="$IMG_ENGINE_ENDPOINT" DOCKER_CONFIG="$IMG_READ_AUTH_DIR"
    test "$(docker info --format '{{.ID}}')" = "$IMG_ENGINE_ID"
    test "$IMG_REGISTRY_CONFIG_DIGEST" = "$IMG_EXPECTED_CONFIG_DIGEST"
    docker image pull --platform "$IMG_PLATFORM" "$IMG_PULL_REF"
    docker image inspect "$IMG_PULL_REF" --format '{{.Os}}/{{.Architecture}} {{.Variant}}'
    docker image inspect "$IMG_PULL_REF" --format '{{json .RootFS.Layers}}'
)
```

Renseigner endpoint/identité du moteur de réception et préparer le contexte lecteur comme à l'étape 5 ; pour le privé, fournir un credential de lecture. Rapprocher digest publié, configuration, plateforme/couches et reçu, puis reprendre les contrôles invalidés par le transfert ou le profil. Adapter l'inspection à la plateforme reçue si le moteur conserve plusieurs variantes ; ne pas recevoir seulement sa variante par défaut. Un téléchargement refusé se traite par droits/association/credential ; ne pas rendre le package public pour poursuivre. [Inspection du manifeste](https://docs.docker.com/reference/cli/docker/buildx/imagetools/inspect/), [téléchargement par digest](https://docs.docker.com/reference/cli/docker/image/pull/).

### Cinq situations et leur reprise

| Situation                                         | Résultat attendu                                                                                                                |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Première publication                              | Visibilité/droits reçus, package associé, téléchargement sous le compte ou anonymat prévu, digest transmis.                     |
| CVE dans une dépendance de l'assemblage           | Composant introducteur identifié, correction/retrait/base alternative qualifiée, nouvelle image reçue ; aucun filtre implicite. |
| Archive altérée, expirée ou mauvaise tentative    | Intégrité ou sélection refusée avant publication ; reçu retrouvé ou nouvelle production identifiée et qualifiée.                |
| Image publiée mais accès/plateforme incompatibles | Réception refusée ; corriger le droit ou produire/qualifier la variante requise, sans substitution de contenu.                  |
| Nouvelle alerte après attente                     | Recontrôler le même livrable, conserver le diagnostic et corriger/requalifier avant publication si la politique bloque.         |

Transmettre au responsable du déploiement le registre et le digest précis, plateformes, reçu/source CI, configuration à fournir, références des contrôles et limites. Les développeurs reçoivent les contraintes nécessaires à leurs contributions, pas les credentials ni les notices d'intervention réservées. Sélectionner cette diffusion selon [Documentation et partage](documentation-et-partage.md).

Le serveur, ses données, ses accès et l'activation de cette version appartiennent à la procédure de déploiement. Cette réception ne prouve ni une installation réelle sur serveur, ni un parcours d'exploitation, ni une sécurité permanente. Entretenir versions, inventaire, politique, commandes et preuves après tout changement pertinent ; validation du contenu du guide et qualification du parcours restent distinctes.
