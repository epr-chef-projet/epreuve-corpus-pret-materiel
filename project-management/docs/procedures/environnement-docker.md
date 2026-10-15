# Préparer et utiliser un environnement Docker avec Laravel Sail

**Version 1.2 — contenu validé ; parcours opérationnel à exercer.** Interfaces examinées dans les sources officielles citées ; vérifier leur compatibilité avec les versions retenues par le projet.

**Résultat :** une copie de développement identifiable, installée depuis ses verrouillages, accessible dans l'éditeur et le navigateur, avec commandes quotidiennes et reprise expliquées. Ce guide concerne un projet **déjà préparé** dont le profil retient Docker/Sail. La création d'un squelette appartient au [guide d'initialisation](initialisation.md).

Un développeur suit ce parcours avec ou sans agent. Le responsable du poste prépare les accès nécessaires ; le responsable technique remet les choix et la recette du projet. Une personne peut cumuler ces responsabilités. Les fonctionnalités, versions, chemins, services et habilitations viennent des documents du projet, par son README et son profil. Les garanties sont dans [R20](../../../.ai/rules/socle/20-environnement-dependances-qualite.md) et [ENV-SAIL](../../../.ai/rules/projet/environnements/laravel-sail.md), selon leurs statuts.

## 1. Préparer le poste et retrouver la bonne copie

### Comprendre où travaillent les outils

| Élément                | Emplacement et effet dans ce parcours                                                                                                                                                       |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sources et dépendances | Dans la copie Linux de travail. Un montage de dossier, ou _bind mount_, permet au conteneur de lire ces mêmes fichiers ; il ne les synchronise pas avec une autre copie.                    |
| Image                  | Assemblage construit de fichiers contenant le runtime et ses outils ; le Dockerfile en décrit la recette. Modifier ce fichier ne modifie pas immédiatement une image ni un conteneur actif. |
| Conteneur              | Processus isolé lancé depuis une image. PHP, Composer et Node s'y exécutent ; leur présence n'installe pas ces outils sur le poste.                                                         |
| Volume Docker          | Stockage raccordé au service, utilisé notamment pour les bases. Sa conservation et son nom se vérifient séparément de ceux des sources.                                                     |
| Sail                   | Lanceur du projet : depuis le terminal Linux, il transmet les commandes à Docker/Compose ou au conteneur applicatif. Git et l'éditeur peuvent fonctionner sur le poste.                     |

PHP et Composer natifs ne sont pas requis ici. Avant `vendor/`, l'amorçage fournit ces outils ; ensuite Sail utilise le runtime applicatif déclaré. Le port HTTP publié conduit au service de cette copie, pas à une application installée ailleurs sur le poste. [Conteneurs](https://docs.docker.com/get-started/docker-concepts/the-basics/what-is-a-container/), [montages](https://docs.docker.com/engine/storage/bind-mounts/) et [volumes](https://docs.docker.com/engine/storage/volumes/).

### Windows avec WSL2

Dans **PowerShell**, vérifier l'installation puis ouvrir la distribution et le compte retenus :

```powershell
wsl.exe --list --verbose
wsl.exe --version
wsl.exe -d "<distribution>" -u "<utilisateur-linux>"
```

Attendus : distribution en WSL2 et compte ordinaire de travail. Ce compte Linux est distinct des comptes Windows, GitHub et applicatifs. Réutiliser un compte existant ; un compte par projet n'est pas nécessaire. Sur une distribution neuve, son premier lancement demande généralement la création du compte. Si seul root existe, faire préparer le compte par l'administrateur avant de poursuivre. Ne pas changer les valeurs par défaut des autres distributions pour ouvrir celle-ci. Si WSL manque, suivre l'[installation Microsoft](https://learn.microsoft.com/en-us/windows/wsl/install) avec le responsable du poste, puis refaire ce contrôle. [Commandes et comptes WSL](https://learn.microsoft.com/en-us/windows/wsl/basic-commands).

Avec Docker Desktop, le démarrer, vérifier le moteur WSL2 dans **Settings → General** si l'option est visible, puis activer la distribution dans **Resources → WSL Integration**. Le parcours utilise les conteneurs Linux. Ne pas installer un second Docker Engine dans cette distribution pour réparer une commande absente ; une installation existante concurrente demande un diagnostic avant modification. [Docker Desktop/WSL](https://docs.docker.com/desktop/features/wsl/).

### Linux natif et contrôles communs

Si le profil retient Docker Engine directement, utiliser l'[installation officielle correspondant à la distribution](https://docs.docker.com/engine/install/). Installation et attribution des accès relèvent du responsable du poste ; le travail reprend sous le compte ordinaire. L'accès au groupe `docker` donne des privilèges importants sur le moteur, pas seulement sur ce projet. [Accès Linux](https://docs.docker.com/engine/install/linux-postinstall/).

Dans le **terminal Linux**, vérifier :

```bash
whoami
id
pwd -P
git --version
docker version
docker compose version
docker context show
```

Attendus : bon utilisateur, Git disponible, client **et serveur** Docker accessibles, contexte prévu et Compose compatible avec la recette. Le nom du contexte n'est pas universel. Examiner les variables héritées `DOCKER_HOST`/`DOCKER_CONTEXT` si le moteur atteint est inattendu ; ne pas modifier le contexte global ni publier une configuration complète pour résoudre un doute.

### Dossier et éditeur

Conserver les sources actives sous Linux, par exemple dans un parent choisi sous `/home/<utilisateur>/…`, plutôt que `/mnt/c` dans la variante WSL. Dans le terminal Linux sous l'utilisateur de travail :

```bash
DEV_PARENT='<chemin-absolu-du-parent-linux>'
mkdir -p -- "$DEV_PARENT" && cd -- "$DEV_PARENT" && pwd -P
```

Cloner ensuite dans une destination neuve à l'étape 2. Ouvrir le projet dans l'IDE après le clone : une ouverture préalable peut créer `.idea` dans sa destination. [Fichiers et performances WSL](https://docs.docker.com/desktop/features/wsl/best-practices/).

Depuis PhpStorm Windows, **File → Open** peut ouvrir les mêmes fichiers avec :

```text
\\wsl.localhost\<distribution>\home\<utilisateur>\<chemin-projet>
```

Dans l'éditeur, choisir un terminal de cette distribution et vérifier son `pwd -P`. Depuis un terminal PowerShell de l'IDE, reprendre la commande `wsl.exe -d "<distribution>" -u "<utilisateur-linux>"`, puis, dans Linux, `cd "<chemin-linux-du-projet>"`. Une fois les services disponibles, raccorder l'interpréteur PHP au service Docker Compose déclaré et vérifier la correspondance des chemins local/conteneur. Ne pas installer un PHP WSL différent pour satisfaire l'IDE. Pour un autre éditeur, utiliser son intégration Linux/WSL avec la même copie. [PhpStorm/WSL](https://www.jetbrains.com/help/phpstorm/how-to-use-wsl-development-environment-in-product.html) et [interpréteur Compose](https://www.jetbrains.com/help/phpstorm/configuring-remote-interpreters.html#configuring-docker-compose-based-interpreter).

## 2. Installer le clone depuis la recette et les verrouillages

### Recevoir les paramètres avant les commandes

| Information non secrète                                  | Source et contrôle                                                                                              |
| -------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Dépôt, référence de départ et dossier                    | Mandat/README ; identifier le contenu à récupérer et la copie à utiliser. Les accès Git suivent leur procédure. |
| Image ou Dockerfile d'amorçage, arguments et plateforme  | Recette versionnée ; rendre Composer disponible avant `vendor/`.                                                |
| Fichiers Compose, surcharges/profils, service applicatif | Recette et configuration ; utiliser le même ensemble pour bootstrap et Sail.                                    |
| Nom Compose, images, ports, adresses et stockage         | Profil local ; identifier les ressources et éviter les collisions.                                              |
| Configuration locale, secrets et identités SQL           | Exemple/procédure de sécurité ; créer les valeurs locales, sans copier celles d'un autre poste.                 |
| Hooks, migrations, générations, contrôles et processus   | Manifestes et recette ; recevoir leur ordre et leurs prérequis.                                                 |

Les blocs `bash` suivants s'exécutent dans le terminal Linux. Remplacer les valeurs entre `<…>` avant emploi ; s'arrêter sur une erreur avant la commande dépendante. Les exemples supposent npm, une image d'amorçage PHP/Composer et le service usuel `laravel.test`. Un profil différent utilise sa recette correspondante.

Depuis le parent choisi, pour une **nouvelle copie** :

```bash
DEV_REPOSITORY='<url-du-depot>'
DEV_REF='<branche-ou-tag-de-depart>'
DEV_FOLDER='<nom-du-nouveau-dossier>'
test ! -e "$DEV_FOLDER" &&
git clone --branch "$DEV_REF" -- "$DEV_REPOSITORY" "$DEV_FOLDER" &&
cd -- "$DEV_FOLDER" &&
pwd -P &&
git status --short &&
git rev-parse HEAD
```

L'enchaînement `&&` arrête cette séquence sur une erreur, sans entrer dans une ancienne copie après un clone refusé. Corriger l'erreur avant les étapes suivantes. Recevoir la référence réellement obtenue puis lire le README. Pour une copie existante, omettre le clone et examiner ses modifications avant toute mise à jour. Une destination contenant déjà des fichiers se conserve ; ne pas la vider pour faire réussir le clone. Une révision SHA précise se récupère selon le mandat Git, sans la transformer arbitrairement en branche ou tag.

Vérifier `composer.json`/`composer.lock`, le manifeste et le lock frontend. L'absence d'un lock annoncé se traite avec le responsable de la recette ; elle ne justifie pas une nouvelle résolution par `update`. Ne pas rejouer `create-project`, `sail:install`, les installateurs frontend ou ceux des services dans cette copie préparée.

### Configuration locale puis Composer avant Sail

Créer le fichier local seulement s'il manque, à partir de l'exemple prévu. Pour une recette utilisant `.env` :

```bash
test -f .env || cp .env.example .env
chmod 600 .env
id -u
id -g
```

Renseigner cible locale, adresses/ports, identités, nom Compose et UID/GID dans la configuration propriétaire. Ne pas copier la clé ou les secrets d'une autre personne ; conserver ceux déjà créés dans cette copie. Préparer les fichiers secrets et leur lisibilité selon les utilisateurs des services. Les credentials migrateurs restent hors Git, contexte de construction **et montage accessible au runtime HTTP**. Le [guide d'initialisation, section 4](initialisation.md#4-préparer-la-configuration-avant-les-consommateurs) explique ces raccordements et la séparation SQL.

Examiner les hooks/plugins Composer et scripts npm avant leur exécution. Les providers ou générateurs peuvent demander configuration, services ou clé ; préparer ces éléments avant le hook concerné. Avant une première commande Artisan ou un hook amorçant Laravel, examiner hors de ce bootstrap les variables héritées, le fichier d'environnement reçu et le cache effectif (`APP_CONFIG_CACHE` ou chemin par défaut). Corriger une cible ancienne selon la recette avant son chargement ; `config:clear` peut lui-même amorcer les providers avant de supprimer le cache.

Si certains scripts sont différés, la recette nomme lesquels, pourquoi et comment les reprendre. `--no-scripts` n'arrête pas les plugins ; ce n'est pas une option systématique. [Scripts Composer](https://getcomposer.org/doc/articles/scripts.md).

Retrouver l'image d'amorçage reçue, ou construire **le Dockerfile livré**, avec ses références et arguments. Pour la recette PHP/Composer décrite dans le premier guide :

```bash
DEV_ROOT="$(pwd -P)"
DEV_BOOTSTRAP_CONTEXT='<dossier-du-dockerfile-amorcage-livre>'
DEV_BOOTSTRAP_IMAGE='<nom-local-image-amorcage>:<version>'
DEV_PHP_IMAGE='<reference-php-prevue>'
DEV_COMPOSER_IMAGE='<reference-composer-prevue>'
docker build --build-arg "PHP_IMAGE=$DEV_PHP_IMAGE" \
    --build-arg "COMPOSER_IMAGE=$DEV_COMPOSER_IMAGE" \
    --tag "$DEV_BOOTSTRAP_IMAGE" "$DEV_BOOTSTRAP_CONTEXT"
docker run --rm "$DEV_BOOTSTRAP_IMAGE" php --version
docker run --rm "$DEV_BOOTSTRAP_IMAGE" composer --version
```

Omettre la construction si l'image prévue est déjà reçue et disponible. L'amorçage doit satisfaire le **lock du projet reçu**, y compris ses dépendances de développement et services ajoutés, pas seulement le squelette initial. Par exemple, Horizon requiert `pcntl` et `posix` : si une extension manque, actualiser la recette propriétaire dans le périmètre reçu avec sa bibliothèque officielle, puis reconstruire sans ignorer la plateforme. Ne pas inventer un autre Dockerfile ni substituer une version du poste. Ouvrir une session avec les sources montées et les identifiants numériques de l'utilisateur :

```bash
docker run --rm -it --user "$(id -u):$(id -g)" \
    --mount "type=bind,source=$DEV_ROOT,target=/work" \
    --workdir /work "$DEV_BOOTSTRAP_IMAGE" sh
```

Dans **cette session**, une fois les prérequis des scripts reçus :

```bash
set -eu
composer check-platform-reqs --lock
composer install --no-interaction
exit
```

Attendus : installation depuis `composer.lock`, dépendances de développement nécessaires et `vendor/bin/sail` disponible. Pour une recette différant les scripts, utiliser à cette place son `composer install --no-scripts`, puis reprendre explicitement ses hooks après préparation, sans migration implicite ni activation prématurée. La procédure doit pouvoir préparer ces prérequis sans dépendre des hooks encore différés. [Installation Composer](https://getcomposer.org/doc/03-cli.md#install) et [plateforme du lock](https://getcomposer.org/doc/03-cli.md#check-platform-reqs).

### Passer au runtime Sail et achever la préparation

De retour dans le terminal Linux, résoudre les fichiers/profils Compose avant démarrage. Sail peut lire `.env.<APP_ENV>` si ce fichier existe, et utiliser `SAIL_FILES`, `APP_SERVICE` ou `APP_USER`. Compose direct ne reproduit pas automatiquement cette sélection. Les exemples `docker compose` de ce guide supposent le profil local standard ; avec des surcharges, reprendre les mêmes options `-f`, `--env-file`, nom de projet et profils déclarés. Contrôler sans afficher les secrets :

```bash
docker compose config --quiet
docker compose config --services
```

Construire le runtime du service applicatif, puis démarrer d'abord ses dépendances nécessaires. Exemple PostgreSQL/Redis **si ces noms sont ceux de la recette** :

```bash
docker compose build laravel.test
docker compose up -d pgsql redis
docker compose ps
docker compose run --rm --no-deps laravel.test composer check-platform-reqs
```

Attendre leur disponibilité réelle. Recevoir le provisionnement local, la clé manquante, les hooks et les migrations dans l'ordre déclaré, avant les consommateurs qui en dépendent. Les commandes ponctuelles et le contrôle SQL du [premier guide](initialisation.md#4-préparer-la-configuration-avant-les-consommateurs) s'utilisent sous le profil correspondant, sans donner l'identité migratrice au service HTTP. Un volume SQL déjà rempli conserve sa procédure de reprise ; les scripts d'initialisation ne le reconfigurent pas automatiquement.

Quand ces prérequis sont reçus, activer le service applicatif et les autres services prévus, puis vérifier **le runtime effectif** et installer le frontend verrouillé :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail php --version
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail node --version
./vendor/bin/sail npm --version
./vendor/bin/sail npm ci
```

`npm ci` nécessite le lock cohérent et remplace `node_modules` ; examiner aussi les prérequis de ses scripts. Reprendre les générations déclarées avant leurs consommateurs. L'image d'amorçage ne prouve pas la plateforme Sail. Contrôler les fichiers créés sous le bon propriétaire, sans `sudo composer` ni droits `777`. [Commandes Sail](https://laravel.com/framework/docs/13.x/sail) et [installation npm verrouillée](https://docs.npmjs.com/cli/v11/commands/npm-ci/).

## 3. Raccorder et isoler les services

### Adresses selon le consommateur

| Consommateur                   | Adresse à recevoir et résultat attendu                                                                                                                             |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Laravel → PostgreSQL/Redis     | Nom du service et port interne du réseau Compose ; accès avec l'identité applicative prévue. `localhost` dans ce conteneur ne désigne pas le service voisin.       |
| Navigateur → application       | URL et port HTTP publiés du profil local ; page et ressources accessibles. Un nom local doit se résoudre depuis ce navigateur.                                     |
| IDE → base, si nécessaire      | Port hôte explicitement publié et joignable ; vérifier base/rôle. Ne pas modifier la destination SQL interne de Laravel pour réparer celle de l'IDE.               |
| Laravel et navigateur → Reverb | Distinguer écoute du serveur, destination de publication utilisée par Laravel et destination WebSocket du navigateur. Vérifier chacune et les origines autorisées. |
| Téléphone, si prévu            | Profil réseau/certificats autorisé, URLs frontend/WebSocket accessibles depuis l'appareil ; le `localhost` du téléphone désigne ce téléphone.                      |

Une liaison interne ne nécessite pas de port hôte publié. Publier SQL/Redis seulement pour un besoin reçu, sur l'interface autorisée ; ne pas exposer tous les services pour faciliter un diagnostic. [Réseau Compose](https://docs.docker.com/compose/how-tos/networking/) et [Reverb](https://laravel.com/framework/docs/13.x/reverb).

### Processus et copies parallèles

La recette indique le mécanisme de maintien de HTTP, Horizon/workers, Reverb et ordonnanceur : services Compose, superviseur ou terminaux séparés. Utiliser **un seul mécanisme pour chaque responsabilité**. `depends_on` ou un conteneur vert ne prouve pas la disponibilité complète ni le traitement d'un travail. [Ordre de démarrage](https://docs.docker.com/compose/how-tos/startup-order/).

Avant deux copies simultanées, comparer :

- Nom Compose, fichiers sélectionnés, ports publiés et montages des sources.
- Volumes/réseaux effectifs, bases, caches, files et préfixes des consommateurs lorsqu'une ressource est partagée.
- Noms explicites `name:`/`container_name:`, ressources externes et tags `image:` : ils peuvent neutraliser l'isolation attendue. Deux runtimes différents construits sous un même tag global peuvent affecter la prochaine recréation de l'autre copie.
- Adresses et portée des cookies. Des applications sous un même host ne sont pas isolées par le seul changement de port ; utiliser la convention de cookies/hôtes du profil.

Relier les données à un volume nommé ou montage stable et identifié. Une branche différente n'isole aucune donnée par elle-même. Corriger les collisions de sa copie plutôt qu'arrêter les services d'un autre projet. [Noms Compose](https://docs.docker.com/compose/how-tos/project-name/) et [nommage des images](https://docs.docker.com/reference/cli/docker/compose/build/).

## 4. Utiliser les commandes quotidiennes

Depuis la racine Linux de la copie, lorsque l'installation est reçue :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
./vendor/bin/sail artisan about
```

Les commandes PHP/Composer/npm utilisent alors le conteneur applicatif actif. Pour les commandes et scripts réellement déclarés dans les manifestes :

```bash
./vendor/bin/sail artisan route:list
./vendor/bin/sail composer show --locked
DEV_NPM_SCRIPT='<nom-du-script-recu>'
./vendor/bin/sail npm run "$DEV_NPM_SCRIPT"
```

Remplacer le nom du script par celui déclaré dans le manifeste ; `route:list` et `show --locked` illustrent des consultations sans changement de dépendances. Un alias personnel reste facultatif ; les recettes conservent le chemin complet. Les outils de l'éditeur ou des agents doivent atteindre la même copie et le même runtime, selon leur procédure de raccordement.

Pour consulter un service de cette copie, avec le profil Compose résolu :

```bash
DEV_SERVICE='<nom-du-service-a-examiner>'
docker compose logs --tail=100 "$DEV_SERVICE"
docker compose ps -a
```

Observer les erreurs utiles ; ne pas transmettre de logs contenant des secrets. Pour le travail quotidien, arrêt puis reprise :

```bash
./vendor/bin/sail stop
```

À la reprise :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

Ces opérations n'installent pas les dépendances et ne lancent pas de migration ou reset **dans la recette attendue** ; vérifier ses commandes, hooks et entrypoints. Les volumes et la clé sont conservés. Sail peut recréer des conteneurs arrêtés ; ce parcours ne promet pas de conserver leur identité ni des données stockées uniquement dans leur couche éphémère. Les processus lancés dans des terminaux séparés sont arrêtés et repris séparément.

### Assets compilés ou serveur Vite

**Mode build :** arrêter le processus Vite avec son mécanisme de maintien ; vérifier le chemin du marqueur de développement prévu par la configuration. Dans le cas usuel `public/hot`, vérifier qu'il est un fichier ordinaire de cette copie avant de le retirer, puis compiler :

```bash
test ! -e public/hot || ls -l public/hot
```

Après ce contrôle et l'arrêt de Vite, pour ce chemin usuel :

```bash
rm -f -- public/hot
./vendor/bin/sail npm run build
```

Recevoir le manifeste et les ressources compilées, puis leurs réponses dans le navigateur. Garder le build existant jusqu'à la recompilation nécessaire ; supprimer `public/build` n'est pas un prérequis. Le marqueur `hot` actif indique à Laravel de charger Vite, même si un build existe.

**Mode Vite :** si aucun autre processus Vite ne le maintient déjà, le lancer dans un terminal Linux séparé, avec le port interne déclaré et publié par Compose :

```bash
DEV_VITE_PORT='<port-interne-vite-de-la-recette>'
./vendor/bin/sail npm run dev -- --host 0.0.0.0 \
    --port "$DEV_VITE_PORT" --strictPort
```

Le script doit être celui du projet utilisant Vite. `--strictPort` refuse une collision plutôt que sélectionner un port non publié. L'écoute dans le conteneur, le port hôte, l'adresse écrite dans `hot` et l'adresse HMR/WebSocket doivent correspondre au profil. Une écoute `0.0.0.0` dans le conteneur ne choisit pas l'interface de publication hôte.

`npm run dev` ou `npm run build` via Sail s'exécute dans le service applicatif ; un service Compose dédié à Vite constitue un autre moyen de maintenir ce processus. Ne pas lancer les deux en parallèle. Après changement de variables frontend, recompiler en mode build ou relancer Vite selon sa configuration. Les variables `VITE_*` sont accessibles au navigateur : aucun secret serveur ne s'y place. [Laravel/Vite](https://laravel.com/framework/docs/13.x/vite) et [options Vite](https://vite.dev/guide/cli).

### Contrôles et services en terminaux

Les tests habituels s’exécutent dans l’installation de développement déjà disponible, avec les commandes fournies par les outils du projet. Il n’est pas nécessaire de cloner une deuxième fois le dépôt ou de démarrer un autre ensemble de conteneurs pour les lancer.

Préparer une base réservée aux tests lorsqu’ils utilisent des données. Elle peut être hébergée dans le même conteneur PostgreSQL que la base de développement. Les migrations du projet en préparent les tables ; la configuration des tests sélectionne cette base.

Si les tests produisent des fichiers ou utilisent réellement d’autres services, prévoir également des emplacements et des ressources réservés aux tests. Vérifier les destinations effectivement utilisées avant l’exécution : le nom d’un fichier de configuration ne suffit pas à garantir la séparation.

Une fois cette préparation effectuée, utiliser la commande de test prévue par la recette. Avec la commande habituelle de Laravel :

```bash
./vendor/bin/sail artisan test
```

Un environnement supplémentaire est réservé aux vérifications qui le nécessitent. Son utilité et sa procédure sont expliquées séparément, sans imposer cette préparation à tous les tests.

Si la recette retient des terminaux séparés, lancer **chacun dans son propre terminal**, après préparation des dépendances :

```bash
./vendor/bin/sail artisan horizon
```

```bash
./vendor/bin/sail artisan reverb:start
```

```bash
./vendor/bin/sail artisan schedule:work
```

Les ports/options de Reverb viennent de sa recette effective. Si ces rôles sont déjà maintenus par Compose ou un superviseur, ne pas lancer ces doublons. Observer traitement de file, réception WebSocket et exécution planifiée avec les sondes autorisées du projet ; la [réception du premier guide](initialisation.md#6-vérifier-la-base-et-préparer-sa-reprise) décrit les contrôles techniques sans fonctionnalité métier supplémentaire.

## 5. Appliquer le bon changement en conservant les données

Recevoir la nouvelle référence de code selon la procédure Git et préserver ses modifications locales. Une mise à jour de la copie ne déploie rien sur un serveur. Examiner manifestes/locks, Dockerfiles, Compose, configuration, migrations et générations affectés avant de reprendre les consommateurs.

| Changement                              | Opération utile et preuve attendue                                                                                                                                                |
| --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP monté, sans changement de runtime   | Une nouvelle requête utilise les sources sous le runtime de développement prévu ; vérifier cache/opcache si elle ne les voit pas. Renouveler les processus persistants concernés. |
| Vue/CSS ou paramètres frontend          | `npm run build` via Sail, ou actualisation/relance du seul Vite retenu ; vérifier assets et console, pas seulement HTML.                                                          |
| Lock PHP/frontend reçu                  | `composer install` et/ou `npm ci` via Sail, prérequis des hooks d'abord ; reprendre générations et contrôles affectés. Pas de `update` pour reproduire la révision reçue.         |
| Configuration Laravel                   | Recevoir les valeurs locales, traiter le cache de configuration suivant la recette, renouveler les consommateurs concernés et vérifier la valeur utile sans afficher les secrets. |
| Variables/montages/définition Compose   | Valider la configuration, puis `up -d` des services concernés ; `restart` seul ne reçoit pas la nouvelle définition.                                                              |
| Dockerfile ou arguments de construction | Construire l'image du service puis appliquer celle-ci avec `up -d` ; vérifier plateforme effective et reprise des données.                                                        |
| Image distante reçue                    | Récupérer la référence déclarée avec `pull`, puis appliquer avec `up -d` et vérifier. Une nouvelle version majeure de base de données exige sa transition prévue.                 |
| Migration                               | Vérifier cible/état, coordonner les consommateurs et utiliser l'identité/profil dédié ; pas de `migrate:fresh` comme mise à jour.                                                 |

Exemple de reconstruction **du service construit localement**, avec le profil Compose déclaré :

```bash
DEV_SERVICE='<service-construit-a-recreer>'
docker compose config --quiet
docker compose build "$DEV_SERVICE"
docker compose up -d "$DEV_SERVICE"
docker compose ps
```

Contrôler la version chargée, pas seulement la réussite de `build`. Utiliser `--force-recreate` si la prise en compte exige une recréation explicite, après identification des ressources ; cette option n'est pas systématique. `--no-cache` ne garantit pas une base plus récente et ne remplace pas la réception de sa référence. [Build](https://docs.docker.com/reference/cli/docker/compose/build/), [up](https://docs.docker.com/reference/cli/docker/compose/up/) et [restart](https://docs.docker.com/reference/cli/docker/compose/restart/).

Pour un changement Laravel non injecté dans la définition Compose, examiner d'abord hors bootstrap le chemin/provenance du cache et la cible attendue, comme à l'étape 2. La commande ciblée usuelle est ensuite `./vendor/bin/sail artisan config:clear`, si la recette autorise ce profil sans cache. Elle ne constitue pas un premier diagnostic sûr d'un contexte inconnu. Ne pas généraliser `optimize:clear` : certains nettoyages peuvent affecter un cache partagé. Une variable fournie à la création du conteneur demande aussi la recréation correspondante.

Horizon et Reverb chargent durablement leur code/configuration. Leur renouvellement suit le mécanisme réel : arrêter/reprendre le terminal concerné, recréer le service, ou utiliser `horizon:terminate`/`reverb:restart` avec un superviseur assurant leur retour. Sans maintien automatique, ces commandes ne garantissent pas le redémarrage. Renouveler également l'ordonnanceur et les autres consommateurs affectés, sans couper un travail en cours arbitrairement. [Horizon](https://laravel.com/framework/docs/13.x/horizon) et [Reverb](https://laravel.com/framework/docs/13.x/reverb).

`docker compose down` ordinaire retire conteneurs/réseaux concernés sans retirer les volumes nommés ; les volumes anonymes conservés ne sont pas automatiquement raccordés au prochain `up`. **`down -v` supprime des volumes et n'est pas nécessaire pour reconstruire.** Les commandes `prune`, suppressions de volumes, réinitialisations SQL et changements de versions de base ne font pas partie de l'arrêt/reprise courant. Elles nécessitent leur cible, autorisation et reprise propres. [Effets de down](https://docs.docker.com/reference/cli/docker/compose/down/).

## 6. Vérifier, diagnostiquer et maintenir la recette

### Recevoir le fonctionnement réel

Après première installation ou changement affectant la preuve, vérifier dans le périmètre concerné :

- Copie/révision, montages, utilisateurs, plateforme et services effectivement utilisés.
- Page, ressources compilées ou Vite, console/réseau navigateur et transport Reverb retenu.
- Accès SQL avec le rôle applicatif, Redis depuis Laravel, travail de file effectivement traité et ordonnanceur selon ses sondes déclarées.
- Contrôles communs dans leur configuration prévue, avec séparation des ressources de test concernées, puis arrêt/reprise avec clé, configuration, données et processus attendus conservés.

Relever des repères synthétiques avant l'arrêt/reconstruction pour comparer après reprise. Conserver versions, commandes exécutées, résultats et limites dans les outils ou artefacts prévus, avec leurs références dans le suivi privé si nécessaire. La recette collective ne porte que les paramètres, commandes, contraintes et limites en vigueur ; aucun nouveau test documentaire ou fichier administratif par commande n'est nécessaire. Une page accessible ne qualifie pas toute la stack.

### Résoudre les difficultés connues

| Symptôme                                    | Vérification et correction utile                                                                                                                                                                        |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Docker absent ou moteur inaccessible        | Vérifier Docker Desktop/Engine, distribution intégrée, client/serveur, contexte et accès du compte ; corriger ce prérequis avant Sail.                                                                  |
| Fichiers illisibles ou créés en root        | Examiner propriétaire, UID/GID, montage et utilisateur effectif ; corriger les éléments concernés avec le responsable du poste, sans `777` ni changement récursif indiscriminé.                         |
| Mauvaise page ou modification invisible     | Comparer dossier IDE/terminal, source montée, URL/port, révision et processus actifs ; corriger le raccordement avant de reconstruire.                                                                  |
| Installation bloque sur plateforme ou hook  | Identifier extension/version/script et prérequis manquant ; corriger la recette compatible, puis reprendre les étapes affectées. Ne pas ignorer la plateforme ni supprimer le lock.                     |
| Port occupé ou ressources partagées         | Identifier le service déjà présent et les noms effectifs ; attribuer les valeurs locales prévues, puis recréer sa copie. Ne pas arrêter un autre projet sans coordination.                              |
| SQL échoue ou migration vise une autre base | Vérifier la cible via le profil exact : base, session/current user, schéma, `DB_URL` et cache ; corriger avant migration, sans élargir le rôle applicatif.                                              |
| Page blanche ou Vite inaccessible           | Examiner la première erreur console/réseau, `hot`, mode choisi, écoute/port publié et URL réellement jointe ; en mode build recevoir le manifeste, en mode Vite recevoir aussi HMR.                     |
| Nom local non résolu                        | Vérifier depuis le consommateur concerné, Windows/WSL pouvant différer ; appliquer le raccordement déclaré ou l'adresse locale prévue. Une édition des fichiers système relève du responsable du poste. |
| Worker ancien ou travail jamais reçu        | Vérifier connexion/file, configuration, dépendances, maintien et renouvellement ; ne pas multiplier les workers pour masquer une mauvaise file.                                                         |
| Données absentes après reprise              | Examiner volume/montage effectivement raccordé et base sélectionnée ; retrouver le stockage avant toute initialisation ou suppression.                                                                  |

Pour une lenteur ou une forte consommation mémoire, mesurer séparément construction/téléchargements initiaux, démarrage, requêtes quotidiennes et contrôles. Première observation dans le terminal Linux :

```bash
docker stats --no-stream
free -h
```

Identifier les conteneurs de sa copie dans ces sorties ; la mémoire globale WSL n'est pas celle de l'application seule. Examiner emplacement des fichiers, surveillance, Xdebug, consommateurs actifs, limites et coexistence des autres projets. Comparer le même parcours avant/après correction selon [R19](../../../.ai/rules/socle/19-performance-observabilite.md). Modifier les ressources avec leur portée reçue ; un réglage global WSL affecte d'autres distributions/projets. La scrutation des fichiers ou l'ajout de RAM ne remplace pas le diagnostic. [Configuration WSL](https://learn.microsoft.com/en-us/windows/wsl/wsl-config).

Une revue senior reste utile lors d'un changement : adapter au profil, corriger un défaut et optimiser avec une preuve appropriée. Conserver les garanties des configurations TypeScript/Vite/tests/analyses ; ne pas supprimer un diagnostic pour rendre le contrôle vert. Le [premier guide](initialisation.md#examiner-et-améliorer-les-configurations-reçues) possède cette méthode, sans recopier ses configurations ici.

### Cinq situations de référence

| Situation                                | Application du parcours                                                                                                                                                               |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Un contributeur arrive sans PHP/Composer | Il ouvre le clone Linux, suit l'amorçage livré, installe les locks, prépare ses accès puis passe à Sail. Il reçoit l'application et sa reprise sans fichiers privés d'un autre poste. |
| Deux copies travaillent simultanément    | Chaque copie reçoit ses noms/ports/stockages et consommateurs. Une vérification depuis chacune constate ses propres données, assets et files, sans effet sur l'autre.                 |
| Une page Vue change en mode build        | Le développeur compile dans Sail et recharge la page ; les nouveaux assets sont servis sans Vite permanent ni marqueur `hot` actif.                                                   |
| Le traitement d'un travail PHP change    | Les sources montées changent immédiatement, mais le worker est renouvelé avec son mécanisme. Un travail autorisé constate ensuite la nouvelle logique.                                |
| Le Dockerfile change                     | Le développeur construit puis applique l'image et vérifie la nouvelle plateforme. Clé et repères de données sont conservés grâce à leur stockage déclaré.                             |

Actualiser la recette propriétaire et ses renvois lors d'un changement, avec les preuves invalidées reprises et les limites explicites. Si une décision essentielle manque, si la cible sort du mandat ou si une action destructive devient nécessaire, obtenir la décision compétente avant cette étape. Les opérations indépendantes restent possibles.

La validation de ce texte et sa qualification opérationnelle sont distinctes. Administration GitHub/CI, construction d'image de production et intervention sur le serveur ont leurs parcours dédiés ; ce guide ne les réalise pas.
