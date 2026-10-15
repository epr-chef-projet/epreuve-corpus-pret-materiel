# Initialiser un projet Laravel avec Docker et Sail

**Version 1.2 — contenu validé ; parcours opérationnel à exercer.** Commandes et interfaces examinées dans les sources officielles Laravel 13, Inertia 3 et des outils cités. Vérifier leur compatibilité avec les versions retenues avant application ; ce document n'adopte pas ces versions pour le projet.

**Résultat :** un squelette officiel avec le frontend sélectionné, les services nécessaires raccordés, des dépendances verrouillées et une recette de reprise partageable. Cette procédure crée une base technique, sans fonctionnalités métier, publication GitHub, image de production ou livraison serveur.

Le parcours concerne un **projet neuf** dont le profil retient Laravel et Docker/Sail. Une personne chargée de l'initialisation le conduit, avec ou sans agent ; les décisions nécessaires sont reçues des responsables compétents. Les responsabilités peuvent être réparties ou réunies. Pour un clone déjà préparé, suivre sa recette d'installation : ne pas rejouer la création du squelette ni les installateurs de configuration.

## 1. Recevoir les choix avant les commandes

Retrouver le profil technique, les décisions applicables et les règles par le [point d'entrée documentaire](../README.md). Les documents doivent être consultables avant de déléguer l'installation. Les conserver dans leur emplacement de référence tant que l'installateur exige une destination vide ; les intégrer au projet après création du squelette.

| Information non secrète                                             | Source et utilisation                                                                               |
| ------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Responsable, périmètre initial et documents à intégrer              | Mandat et profil ; distinguer base nécessaire et outils destinés à des fonctionnalités ultérieures. |
| Dossier parent, nom du projet, utilisateur et environnement         | Recette du poste ; création des fichiers et raccordement de l'éditeur.                              |
| PHP/extensions, Composer, Laravel, Sail et frontend                 | Profil technique et contraintes des packages ; installation compatible et premiers verrouillages.   |
| Images d'amorçage et services Docker, Node et gestionnaire frontend | Références identifiées dans la recette ; construction locale et vérification du runtime réel.       |
| Services requis, adresses, ports, nom Compose et stockage           | Profil local ; éviter les collisions et identifier les données qui persistent.                      |
| Base, schéma, identités application/migration/provisionnement       | Contrat de sécurité ; préparer les accès avant scripts et migrations.                               |
| Mode frontend, outillage qualité et assistance de développement     | Profil ; build ou Vite, commandes de contrôle et raccordements d'outils/agents.                     |

Une décision essentielle absente ou contradictoire demande clarification avant l'étape dépendante. Ne pas envoyer les secrets dans un prompt ou un compte rendu : les créer dans la configuration locale protégée. Pour un outil ou une extension supplémentaire, recevoir sa nécessité et sa version compatible avant ajout.

**Conventions des exemples :** les blocs `bash` sont exécutés dans le terminal Linux du dossier indiqué, sous l'utilisateur de travail. Remplacer les valeurs entre `<…>` depuis le profil avant exécution. Les exemples frontend utilisent npm et les interfaces Inertia 3 ; un autre profil garde sa recette correspondante. S'arrêter sur une erreur avant la commande dépendante.

## 2. Préparer le dossier et le runtime d'amorçage

### Vérifier le poste

Dans le terminal Linux de travail :

```bash
pwd -P
id
docker version
docker compose version
docker context show
```

Attendus : utilisateur non privilégié de travail, client **et moteur** Docker accessibles, contexte prévu et version Compose compatible avec la recette. Examiner les variables héritées telles que `DOCKER_HOST`, `DOCKER_CONTEXT`, `COMPOSE_FILE` ou `APP_ENV` si la cible résolue est inattendue ; ne pas publier une configuration complète contenant des secrets.

Avec Windows/WSL, conserver le code actif dans le système de fichiers Linux et connecter éditeur et terminal à cette copie. L'accès depuis Windows ne crée pas une deuxième copie à synchroniser. Sans accès au moteur ou au dossier, corriger ce prérequis avec le responsable du poste ; ne pas généraliser l'exécution en root ou les droits `777`. [Fichiers et WSL](https://docs.docker.com/desktop/features/wsl/best-practices/).

Depuis le **dossier parent** où créer le projet, recevoir puis renseigner :

```bash
INIT_PARENT="$(pwd -P)"
INIT_PROJECT_NAME='<nom-du-nouveau-dossier>'
INIT_LARAVEL_CONSTRAINT='<contrainte-du-squelette-laravel>'
INIT_SAIL_CONSTRAINT='<contrainte-du-package-sail>'
INIT_SAIL_PHP='<version-mineure-prise-en-charge>'
INIT_SAIL_SERVICES='<services-separes-par-des-virgules>'
INIT_PHP_IMAGE='<reference-officielle-php-cli-debian>'
INIT_COMPOSER_IMAGE='<reference-officielle-composer>'
INIT_BOOTSTRAP_IMAGE='<nom-local-image-amorcage>:<version>'
```

Le nom du nouveau dossier est un nom simple, sans séparateur de chemin. La destination doit être neuve : si elle contient déjà des fichiers, ne pas forcer leur écrasement. Créer une autre destination compatible, puis transférer volontairement les documents nécessaires. Un dossier d'IDE ou un CDC présent peut suffire à rendre la destination non vide.

### Disposer de PHP et Composer avant Sail

Sail n'existe pas encore sans `vendor/`. Le parcours principal ci-dessous utilise une image d'amorçage PHP/Composer compatible : réutiliser celle de la recette si elle est déjà disponible et reçue, sinon la construire à partir des références officielles sélectionnées. Il fonctionne également sur un poste possédant PHP, sans dépendre de sa version native.

Créer un dossier de construction **hors de la destination du projet** et y enregistrer le Dockerfile suivant. Cette variante utilise une base PHP CLI Debian ; une base différente demande sa recette adaptée.

```dockerfile
ARG PHP_IMAGE
ARG COMPOSER_IMAGE
FROM ${COMPOSER_IMAGE} AS composer-bin
FROM ${PHP_IMAGE}
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
RUN set -eu; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip libonig-dev libxml2-dev libzip-dev; \
    for ext in mbstring dom xml xmlreader xmlwriter zip; do \
        if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then \
            docker-php-ext-install "$ext"; \
        fi; \
    done; \
    rm -rf /var/lib/apt/lists/*
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /work
```

Cette liste couvre des besoins usuels du squelette et de ses dépendances de développement ; comparer les exigences effectives des versions sélectionnées. Les extensions déjà présentes ne sont pas recompilées. Ajouter une extension requise avec ses bibliothèques officielles, puis reconstruire ; ne pas masquer le manque avec `--ignore-platform-reqs`. Le runtime Sail sera contrôlé séparément. [Images PHP](https://hub.docker.com/_/php) et [Composer](https://hub.docker.com/_/composer).

Dans le terminal hôte, indiquer le dossier contenant ce Dockerfile puis construire :

```bash
INIT_BOOTSTRAP_DIR='<chemin-absolu-du-dossier-de-construction>'
docker build \
    --build-arg "PHP_IMAGE=$INIT_PHP_IMAGE" \
    --build-arg "COMPOSER_IMAGE=$INIT_COMPOSER_IMAGE" \
    --tag "$INIT_BOOTSTRAP_IMAGE" "$INIT_BOOTSTRAP_DIR"
docker run --rm "$INIT_BOOTSTRAP_IMAGE" php --version
docker run --rm "$INIT_BOOTSTRAP_IMAGE" composer --version
docker run --rm "$INIT_BOOTSTRAP_IMAGE" php -m
```

Maintenir les versions, références et prérequis actuels dans la recette du projet. Conserver les résultats d'exécution dans les outils ou artefacts prévus et, si nécessaire, dans le suivi privé. Les versions sont celles constatées, pas déduites du nom local de l'image. L'image d'amorçage n'est pas une image de production.

## 3. Créer le squelette et rendre Sail disponible

Ouvrir depuis le terminal hôte une session dans l'image d'amorçage, avec le dossier parent monté et les identifiants numériques de l'utilisateur :

```bash
docker run --rm -it \
    --user "$(id -u):$(id -g)" \
    --mount "type=bind,source=$INIT_PARENT,target=/work" \
    --workdir /work \
    -e "INIT_PROJECT_NAME=$INIT_PROJECT_NAME" \
    -e "INIT_LARAVEL_CONSTRAINT=$INIT_LARAVEL_CONSTRAINT" \
    -e "INIT_SAIL_CONSTRAINT=$INIT_SAIL_CONSTRAINT" \
    -e "INIT_SAIL_PHP=$INIT_SAIL_PHP" \
    -e "INIT_SAIL_SERVICES=$INIT_SAIL_SERVICES" \
    "$INIT_BOOTSTRAP_IMAGE" sh
```

Les commandes du bloc suivant sont exécutées **dans cette session**. Pour une automatisation sans terminal interactif, retirer `-t`, conserver l'entrée standard nécessaire et transmettre ces commandes à `sh` ; aucun alias personnel n'est requis.

```bash
set -eu
test ! -e "$INIT_PROJECT_NAME"
composer create-project --prefer-dist --no-interaction --no-scripts \
    laravel/laravel "$INIT_PROJECT_NAME" "$INIT_LARAVEL_CONSTRAINT"
cd "$INIT_PROJECT_NAME"
test -f .env || cp .env.example .env
chmod 600 .env
composer run-script post-autoload-dump
```

Ce parcours utilise le **squelette officiel**, sans starter. La commande résout les dépendances PHP, prépare `vendor/` et reçoit ou produit `composer.lock`. Le verrouillage frontend sera établi par npm à l'étape correspondante ; ce n'est pas encore une installation depuis les locks reçus d'un autre développeur. `post-autoload-dump` reprend la découverte des packages après préparation de `.env`.

**Pourquoi `--no-scripts` ici ?** Le squelette Laravel 13 consulté possède un hook de création qui génère la clé, crée SQLite et lance des migrations. Le différé conserve un ordre explicite lorsque le profil retient une autre base ou des identités séparées. Il ne désactive pas les plugins Composer. Examiner les hooks de la version reçue, préparer leurs prérequis et reprendre les opérations nécessaires ; ne pas rejouer en bloc `post-create-project-cmd` ou `composer setup`. Pour une recette autorisant ces effets par défaut, leur exécution suit sa propre séquence. [Création Composer](https://getcomposer.org/doc/03-cli.md#create-project) et [scripts du squelette](https://github.com/laravel/laravel/blob/13.x/composer.json).

Dans cette même session, générer la clé **seulement si elle manque**, puis ajouter Sail :

```bash
if ! grep -q '^APP_KEY=.' .env; then php artisan key:generate; fi
composer require --dev "laravel/sail:$INIT_SAIL_CONSTRAINT"
php artisan sail:install --with="$INIT_SAIL_SERVICES" \
    --php="$INIT_SAIL_PHP" --no-interaction
exit
```

`sail:install` prépare Compose et modifie des paramètres locaux. Examiner les fichiers produits **avant démarrage**. Reprendre chaque autre hook nécessaire identifié, sans y ajouter de migration implicite. Les données et clés existantes ne sont pas régénérées pour corriger une erreur.

De retour dans le terminal hôte :

```bash
cd "$INIT_PARENT/$INIT_PROJECT_NAME"
pwd -P
```

Ouvrir ce dossier dans l'éditeur et vérifier la propriété des fichiers. Conserver la recette d'amorçage partageable, son Dockerfile et ses références dans l'organisation du projet, par exemple `docker/bootstrap/`. Importer maintenant les documents retenus et les points d'entrée des règles, avec chemins adaptés, sans dépendance au poste d'origine. [Installation Sail](https://laravel.com/framework/docs/13.x/sail).

## 4. Préparer la configuration avant les consommateurs

### Compose et fichiers locaux

Configurer `.env` et Compose depuis le profil : `APP_URL`, `WWWUSER`/`WWWGROUP` correspondant aux UID/GID, nom de projet Compose, runtime PHP/Node, ports et stockage. Entre services, utiliser leurs noms et ports internes. Le navigateur utilise l'adresse publiée, pas nécessairement ces noms internes.

Les données doivent avoir un stockage persistant identifié, distinct des autres projets. Ne publier SQL/Redis sur le poste que si nécessaire, sur l'interface autorisée. Préparer avant lancement les secrets de provisionnement et accès : un secret monté doit être lisible par son utilisateur réel, sans rendre tous les fichiers accessibles. Vérifier la configuration Compose sans l'afficher avec ses secrets :

```bash
docker compose config --quiet
docker compose build laravel.test
```

`laravel.test` est le nom généré usuel ; utiliser le nom effectif si la recette l'a changé et régler `APP_SERVICE` pour Sail. Épingler les images de services selon la recette, en tenant compte de leurs chemins de données propres à la version. Un montage PostgreSQL repris d'une autre version ne se substitue pas à cette vérification.

Vérifier les arguments de construction effectifs. Une version Node renseignée dans `.env` ne change le runtime que si Compose la transmet à l'argument `NODE_VERSION` du Dockerfile concerné. Si des extensions ou fichiers du runtime doivent être adaptés, publier sa recette avec `php artisan sail:publish` dans le runtime disponible, conserver la source partagée, revoir le contexte/nom d'image puis reconstruire. Ne pas modifier seulement un fichier de `vendor/` qui sera remplacé à la prochaine installation.

### Base et identités

Pour PostgreSQL, préparer la recette SQL avant le premier démarrage : nom de base et schéma, compte de provisionnement, comptes applicatif/migrateur et droits. Le compte qui initialise l'image PostgreSQL ne devient pas automatiquement un compte applicatif restreint. Avec des comptes séparés, adapter les paramètres `POSTGRES_*` du service pour le provisionnement ; `DB_USERNAME`/`DB_PASSWORD` de Laravel désignent l'identité applicative.

La séquence de préparation est : créer la base ; créer les identités avec les attributs autorisés ; définir propriétaire, schéma et `search_path` ; accorder connexion/usage et privilèges actuels et futurs nécessaires ; fournir au migrateur l'autorité DDL prévue. L'initialisateur prépare le SQL correspondant au contrat et le conserve comme recette partageable sans secrets. L'application ne reçoit pas les credentials de provisionnement ou de migration, ni des droits superutilisateur pour faire fonctionner les migrations. Exécuter le provisionnement avec le client SQL et l'accès d'administration du profil, secrets fournis localement hors arguments et logs. Les noms et droits précis viennent du contrat de sécurité ; une installation de rôles ne prouve pas des politiques RLS métier.

Avec un schéma différent du défaut, vérifier aussi `config/database.php` : le connecteur Laravel applique le `search_path` de sa configuration, qui peut remplacer celui défini sur le rôle. Rendre ce paramètre explicite, par exemple `'search_path' => env('DB_SEARCH_PATH', 'public')`, puis recevoir `DB_SEARCH_PATH` pour chaque profil concerné. Vérifier également `DB_URL`, qui peut remplacer les paramètres `DB_*` séparés.

Démarrer **les dépendances nécessaires**, par exemple lorsque `pgsql` et `redis` sont les services sélectionnés :

```bash
docker compose up -d pgsql redis
docker compose ps
```

Attendre leur disponibilité réelle avant les migrations. Si le volume contient déjà des données ou une initialisation partielle, examiner et décider la reprise ; les scripts d'initialisation PostgreSQL ne sont pas rejoués sur un cluster existant. Ne pas supprimer le volume pour obtenir un premier démarrage réussi.

Pour une opération préparatoire avant HTTP, utiliser un conteneur ponctuel, sans lanceur spécifique :

```bash
docker compose run --rm --no-deps laravel.test php --version
docker compose run --rm --no-deps laravel.test composer check-platform-reqs
```

Le profil de migration doit recevoir **sa propre configuration**. Exemple de surcharge partagée sans secrets, à intégrer dans la recette :

```yaml
services:
    laravel.test:
        env_file:
            - ${MIGRATION_ENV_FILE:?fichier_prive_migration_requis}
```

Ce fichier privé fournit la cible et le rôle migrateur, avec `APP_ENV` local et ses credentials. Le conserver hors Git, contexte de construction **et montage accessible au runtime HTTP** ; Compose transmet ses valeurs uniquement au conteneur ponctuel. Exemple de paramètres à renseigner dans ce fichier protégé, jamais dans une commande ou un rapport :

```dotenv
APP_ENV=local
APP_CONFIG_CACHE=/tmp/initialisation-migration-config.php
DB_URL=
DB_CONNECTION=pgsql
DB_HOST=<service-postgresql>
DB_PORT=<port-interne>
DB_DATABASE=<base-locale>
DB_USERNAME=<compte-migrateur>
DB_PASSWORD='<secret-local>'
DB_SEARCH_PATH=<schema-autorise>
```

Le chemin de cache temporaire appartient au nouveau conteneur ponctuel et ne doit pas contenir un ancien cache. `DB_URL` est vide dans cet exemple pour utiliser les paramètres séparés ; un profil fondé sur une URL fournit à la place sa propre URL privée. Le chemin de recherche n'est appliqué que si la configuration Laravel le lit comme décrit ci-dessus.

Contrôler d'abord la cible et les identités sans imprimer le mot de passe :

```bash
INIT_MIGRATION_ENV='<chemin-absolu-du-fichier-prive-migration>'
MIGRATION_ENV_FILE="$INIT_MIGRATION_ENV" docker compose \
    -f compose.yaml -f compose.migration.yaml \
    run --rm --no-deps laravel.test php artisan tinker \
    --execute='dump(\Illuminate\Support\Facades\DB::selectOne("select current_database() as db_name, session_user as login_role, current_user as effective_role, current_schema() as schema_name"));'
```

Comparer ces valeurs au profil : base prévue, compte migrateur, propriétaire effectif et schéma autorisés. S'arrêter sur une divergence. Puis, avec le même fichier privé :

```bash
MIGRATION_ENV_FILE="$INIT_MIGRATION_ENV" docker compose \
    -f compose.yaml -f compose.migration.yaml \
    run --rm --no-deps laravel.test php artisan migrate
```

Le nom `compose.migration.yaml` désigne la surcharge ci-dessus, à créer si cette méthode est retenue. Elle s'ajoute à Compose sans remplacer ses montages. Ne pas exécuter `migrate:fresh`, semis métier ou reset implicite. Vérifier ensuite les objets, leur propriétaire et l'accès applicatif avec l'identité restreinte. [Configuration Laravel](https://laravel.com/framework/docs/13.x/configuration) et [migrations](https://laravel.com/framework/docs/13.x/migrations).

### Passer au runtime quotidien

Après préparation des accès et ressources, démarrer l'application :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail php --version
./vendor/bin/sail node --version
./vendor/bin/sail npm --version
```

PHP, Composer, Artisan et npm sont désormais exécutés via Sail. Git et l'éditeur peuvent rester sur le poste. Le PHP de l'image temporaire n'est pas une preuve de celui de Sail ; vérifier aussi les extensions et la compatibilité des outils de contrôle. Aucun fichier applicatif nécessaire ne doit dépendre d'un cache personnel.

## 5. Installer le frontend et les services retenus

### Première page Inertia/Vue/TypeScript

Lorsque ce frontend est retenu, installer les dépendances compatibles du profil dans Sail. Remplacer chaque contrainte ci-dessous par celle reçue ; la syntaxe est `package:contrainte` pour Composer et `package@contrainte` pour npm :

```bash
./vendor/bin/sail composer require 'inertiajs/inertia-laravel:<contrainte-inertia-php>'
./vendor/bin/sail artisan inertia:middleware
./vendor/bin/sail npm install 'vue@<contrainte-vue>' '@inertiajs/vue3@<contrainte-inertia-vue>'
./vendor/bin/sail npm install --save-dev \
    '@inertiajs/vite@<contrainte-inertia-vite>' '@vitejs/plugin-vue@<contrainte-plugin-vue>' \
    'typescript@<contrainte-typescript>' 'vue-tsc@<contrainte-vue-tsc>' '@vue/tsconfig@<contrainte-tsconfig-vue>'
```

Cette première résolution npm produit ou actualise `package-lock.json`. Conserver ce fichier avec les manifestes après vérification ; il servira ensuite à `npm ci`.

Adapter les fichiers existants, sans écraser leurs options encore retenues :

| Fichier                         | Configuration attendue pour les interfaces Inertia 3                                                                                                                                                                                                                    |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `bootstrap/app.php`             | Dans le callback middleware existant, ajouter `$middleware->web(append: [\App\Http\Middleware\HandleInertiaRequests::class]);` sans remplacer les autres réglages.                                                                                                      |
| `resources/views/app.blade.php` | Vue racine HTML ci-dessous, langue du projet et entrée TypeScript.                                                                                                                                                                                                      |
| `resources/js/app.ts`           | Importer `../css/app.css`, puis `createInertiaApp` de `@inertiajs/vue3` et appeler `createInertiaApp()` ; le plugin ci-dessous fournit la résolution. Ne pas importer un ancien `bootstrap.js` absent.                                                                  |
| `vite.config.js` ou `.ts`       | `defineConfig` vient de `vite`. Adapter l'entrée Laravel vers `resources/js/app.ts`, conserver les options pertinentes, ajouter Vue et `inertia({ ssr: false })` lorsque SSR n'est pas retenu. Pour Vue : `transformAssetUrls: { base: null, includeAbsolute: false }`. |
| `.env`                          | Avec ce parcours sans SSR, `INERTIA_SSR_ENABLED=false` désactive aussi sa branche Laravel ; ne pas démarrer un serveur SSR non sélectionné.                                                                                                                             |
| `tsconfig.json`                 | Étendre `@vue/tsconfig/tsconfig.dom.json`, inclure les `.ts`, `.d.ts` et `.vue` de `resources/js`, types `vite/client` et vérification stricte.                                                                                                                         |
| `package.json`                  | Conserver les scripts `build` (`vite build`) et `dev` (`vite`) ; ajouter le contrôle de types ci-dessous. Le build ne le remplace pas.                                                                                                                                  |

Le squelette consulté utilise déjà Vite classique et peut inclure Tailwind et des polices. Préserver les outils retenus et traiter explicitement ceux qui ne le sont pas. Ne pas convertir un starter pour obtenir ce résultat. [Inertia serveur](https://inertiajs.com/docs/v3/installation/server-side-setup), [client](https://inertiajs.com/docs/v3/installation/client-side-setup), [Vue/TypeScript](https://vuejs.org/guide/typescript/overview.html) et [Laravel Vite](https://laravel.com/framework/docs/13.x/vite).

Pour une vue racine encore absente, `resources/views/app.blade.php` peut commencer ainsi :

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/js/app.ts')
    <x-inertia::head />
</head>
<body>
    <x-inertia::app />
</body>
</html>
```

Contenu minimal de `resources/js/app.ts`, avant les raccordements supplémentaires retenus :

```ts
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp();
```

Dans la configuration Vite existante, ajouter les imports :

```js
import vue from '@vitejs/plugin-vue';
import inertia from '@inertiajs/vite';
```

Ajouter ces éléments à son tableau `plugins`, après les plugins conservés, et adapter l'entrée Laravel vers `resources/js/app.ts`. Les styles sont importés depuis cette entrée :

```js
vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
inertia({ ssr: false }),
```

Pour la première configuration TypeScript de ce parcours, `tsconfig.json` contient :

```json
{
    "extends": "@vue/tsconfig/tsconfig.dom.json",
    "compilerOptions": {
        "strict": true,
        "types": ["vite/client"]
    },
    "include": [
        "resources/js/**/*.ts",
        "resources/js/**/*.d.ts",
        "resources/js/**/*.vue"
    ]
}
```

Ajouter son script npm depuis le terminal hôte du projet :

```bash
./vendor/bin/sail npm pkg set "scripts.types:check=vue-tsc --noEmit"
```

Préserver les réglages existants lorsqu'un projet possède déjà une configuration reçue ; intégrer les sources réellement utilisées sans masquer les diagnostics par un shim permissif.

Créer une page technique neutre `resources/js/pages/Welcome.vue`, avec un élément visible et `<script setup lang="ts">`, puis remplacer la route d'accueil existante par `Route::inertia('/', 'Welcome');`. Il s'agit d'un contrôle du raccordement, sans écran métier ni authentification ajoutée par défaut. Le nom de page et sa casse doivent correspondre exactement.

### Redis, Horizon, Reverb et tâches planifiées

Installer seulement les services retenus pour la première base. Avec le service Compose nommé `redis`, Laravel utilise `REDIS_HOST=redis` et son port interne ; `REDIS_CLIENT=phpredis` exige l'extension correspondante. Cache et sessions Redis sont des choix distincts de la queue. Le profil fixe leurs connexions et préfixes.

Si Horizon est retenu :

```bash
./vendor/bin/sail composer require 'laravel/horizon:<contrainte-horizon>'
./vendor/bin/sail artisan horizon:install
```

Régler `QUEUE_CONNECTION=redis`, les files et la configuration Horizon pour l'environnement local. Horizon est un processus PHP qui démarre ses workers ; ne pas lancer en parallèle un autre `queue:work` sur les mêmes files sans besoin identifié. La connexion Redis nommée `horizon` est réservée. [Horizon](https://laravel.com/framework/docs/13.x/horizon).

Si Reverb est retenu :

```bash
./vendor/bin/sail artisan install:broadcasting --reverb
```

Examiner les ajouts Composer/npm, la configuration et le client Echo produits : cet installateur effectue ces raccordements, ne pas installer les mêmes packages une seconde fois. Fixer `BROADCAST_CONNECTION=reverb` et distinguer : écoute `REVERB_SERVER_*` ; publication Laravel `REVERB_HOST/PORT/SCHEME` ; accès navigateur `VITE_REVERB_HOST/PORT/SCHEME`. La clé publique peut arriver au frontend, le secret reste serveur. Déclarer les origines autorisées dans la configuration Reverb plutôt que conserver une autorisation globale implicite. [Broadcasting](https://laravel.com/framework/docs/13.x/broadcasting#reverb) et [Reverb](https://laravel.com/framework/docs/13.x/reverb).

Rapprocher les versions résolues des contraintes du profil avant réception. Un installateur peut choisir ses propres contraintes par défaut ; corriger une divergence dans les manifestes et reprendre la résolution concernée, sans changer silencieusement le choix du projet.

Pour le premier fonctionnement, utiliser les commandes conventionnelles dans des **terminaux dédiés**, ou les services de maintien explicitement préparés par la recette :

```bash
./vendor/bin/sail artisan horizon
./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port="$INIT_REVERB_PORT"
./vendor/bin/sail artisan schedule:work
```

Exécuter chaque commande sélectionnée dans son propre terminal ; leur arrêt met fin au processus. Renseigner `INIT_REVERB_PORT` avec le port interne du profil dans le terminal Reverb avant la commande. Ce port doit être publié par Compose vers celui prévu pour le navigateur. L'ordonnanceur est activé seulement si nécessaire ; ne pas créer de jobs métier pour remplir cette étape. En cas de configuration modifiée, renouveler les processus persistants selon leur mécanisme réel : une commande de terminaison ne les redémarre pas sans gestionnaire.

### Choisir build ou serveur Vite

**Mode build :** arrêter tout serveur Vite de cette instance ; si son marqueur `public/hot` est resté après l'arrêt, retirer uniquement ce marqueur, puis :

```bash
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
```

Attendus : contrôle de types réussi, manifeste et ressources compilées présents, navigateur chargeant ces ressources sans serveur Vite. Ne pas supprimer le build pour réparer un mode mal configuré.

**Mode développement Vite :** un seul processus, écoute accessible depuis le conteneur, port publié et URL/HMR joignables par le navigateur. Par exemple, avec `INIT_VITE_PORT` reçu du profil :

```bash
INIT_VITE_PORT='<port-vite-retenu>'
./vendor/bin/sail npm run dev -- --host 0.0.0.0 --port "$INIT_VITE_PORT" --strictPort
```

La publication du port doit appartenir au service qui exécute réellement ce processus. Examiner le marqueur et l'adresse client sans considérer un nom interne Docker comme une adresse navigateur. Après changement de variables `VITE_*`, relancer le serveur Vite ou reconstruire le frontend, selon le mode. Utiliser l'origine HTTP prévue dans `APP_URL`, pas une autre IP si sessions, CORS ou URLs la rendent incompatible.

## 6. Vérifier la base et préparer sa reprise

### Examiner et améliorer les configurations reçues

Les fichiers d'un installateur sont un point de départ. La personne chargée de l'initialisation les examine et prend les initiatives techniques nécessaires pour atteindre les exigences reçues ; cette revue s'applique également à un squelette minimal. Distinguer adaptation au profil, correction d'un défaut et optimisation. Expliquer les changements utiles dans leur recette et vérifier leurs effets, sans reproduire automatiquement des réglages d'un autre projet.

| Configuration           | Points à examiner avant réception                                                                                                                                                                   |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| TypeScript/Vue          | Sources et composants réellement analysés, résolution des imports et générés, vérification stricte et contrôle de types séparé du build ; aucun shim permissif masquant un défaut.                  |
| Vite                    | Entrées utiles, plugins/options conservés, ressources et génération, adresses et mode retenus ; éviter processus et options inutiles.                                                               |
| Tests                   | Framework du profil, suites pertinentes, base/identités de test réellement isolées, configuration héritée et effets du bootstrap. Les tests fournis en SQLite ne prouvent pas un profil PostgreSQL. |
| Analyse, lint et format | Couverture PHP/Vue/TypeScript requise, diagnostics et exclusions justifiés, commandes reproductibles. Préférer les commandes des exécutables locaux aux téléchargements implicites.                 |
| Runtime et services     | Extensions, arguments de construction, droits, connexions, maintien/arrêt et ressources adaptées. Séparer temps d'installation et réactivité quotidienne.                                           |

Une optimisation doit conserver les garanties et être vérifiée : reprise des contrôles affectés, mesure comparable lorsqu'un gain de performance est avancé. Modifier ou organiser un fichier de configuration n'est pas en soi un détour inutile. Installer et configurer les outils qualité retenus, puis documenter leurs commandes habituelles. Versionner leurs configurations et les éventuelles automatisations justifiées selon R20-017.

### Contrôles et remise

Exécuter les contrôles dans le runtime déclaré, sur les seules ressources locales autorisées. Vérifier le profil d'essai avant les tests : ils ne doivent pas utiliser par défaut la base de développement ou un accès de production.

| Contrôle                     | Résultat utile à constater                                                                                                                                                                                                                       |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Plateforme et dépendances    | `sail composer validate`, `sail composer check-platform-reqs` et audits du profil sans manque ignoré ; versions/locks cohérents.                                                                                                                 |
| HTTP et frontend             | Première page montée par Vue/Inertia à l'URL déclarée, ressources chargées, aucun échec console/réseau ; types et build réussis.                                                                                                                 |
| SQL                          | Cible et rôle effectifs, migrations présentes, propriétaire et accès applicatif autorisés ; aucun privilège de migration transmis au runtime ordinaire.                                                                                          |
| Redis et queues sélectionnés | Connexion effective Laravel ; `sail artisan horizon:status` lorsque Horizon est actif et preuve de traitement technique autorisée si le parcours exige une queue. Un `redis-cli ping` seul ne prouve pas ce raccordement.                        |
| Reverb sélectionné           | Connexion WebSocket réellement acceptée depuis le client configuré ; un port ouvert ne suffit pas. Réutiliser une sonde technique existante pour les étapes suivantes, sans inventer un scénario métier.                                         |
| Qualité                      | Commandes du profil présentes et exécutées sur une portée utile ; par exemple Pint fourni par le squelette et tests existants applicables. Ajouter les outils expressément retenus, sans écrire une suite anticipant toutes les fonctionnalités. |
| Arrêt/reprise                | Processus persistants arrêtés/repris explicitement, puis `sail down` et `sail up -d` sans suppression des volumes ; clé, données et raccordements attendus conservés.                                                                            |

Dans ce tableau, `sail` est l'abréviation de `./vendor/bin/sail`, pas un alias obligatoire. Pour vérifier les raccordements Laravel sans afficher les connexions complètes :

```bash
./vendor/bin/sail artisan tinker \
    --execute='dump(\Illuminate\Support\Facades\DB::selectOne("select current_database() as db_name, session_user as login_role, current_user as effective_role, current_schema() as schema_name"));'
./vendor/bin/sail artisan tinker \
    --execute='dump(\Illuminate\Support\Facades\Redis::connection()->ping());'
```

La première commande reçoit cette fois l'identité applicative, sans surcharge de migration. La réponse Redis est positive selon le client utilisé ; elle ne prouve pas la consommation d'une queue.

Avec Horizon, la connexion Redis et la commande technique `inspire` présentes, envoyer un travail natif sans créer de classe métier :

```bash
./vendor/bin/sail artisan tinker \
    --execute='dump(\Illuminate\Support\Facades\Bus::dispatch((new \Illuminate\Foundation\Console\QueuedCommand(["inspire", []]))->onConnection("redis")->onQueue("<file-ecoutee-par-Horizon>")));'
```

Remplacer la file par celle du profil. Relever l'identifiant retourné et constater sa réussite dans Horizon, avec connexion/file correspondantes ; un envoi seul n'est pas un traitement réussi. Réutiliser une sonde existante si le projet en fournit une. [Tinker et dispatch](https://laravel.com/framework/docs/13.x/artisan#tinker).

Conserver commandes exécutées, résultats, état contrôlé et limites dans les outils ou artefacts prévus, ou dans le suivi privé selon [Documentation et partage](documentation-et-partage.md), sans ajouter de journal à la recette collective. Ne pas y consigner de secrets. Si une preuve manque pour un composant déclaré indispensable, compléter sa réception avant d'annoncer la base terminée.

**Verrouillages :** une fois les premiers locks établis, les installations suivantes utilisent `composer install` et, avec npm, `npm ci`. Ne pas employer `npm ci` avant `package-lock.json`, ni `composer update` pour l'installation ordinaire. Une modification de dépendance résout de nouveau uniquement le changement autorisé et actualise son lock. Les options et scripts npm/Composer font partie de la recette. [Composer install](https://getcomposer.org/doc/03-cli.md#install-i) et [npm ci](https://docs.npmjs.com/cli/v11/commands/npm-ci/).

Le README de remise indique état, prérequis, URL, installation, démarrage, arrêt et reprise, puis renvoie à la recette propriétaire. Fournir manifestes/locks, Compose et Dockerfiles nécessaires, `.env.example` sans secrets et points d'entrée des documents/règles. Vérifier les exclusions Git **et** Docker, y compris fichiers locaux, données, logs privés et credentials. Un fichier déjà suivi n'est pas retiré par `.gitignore` ; un fichier ignoré par Git peut encore entrer dans un contexte Docker mal filtré.

Si l'assistance de développement est retenue, installer et raccorder ses outils dans le runtime prévu, préserver les instructions existantes et vérifier qu'une session neuve retrouve index des règles et profil.

Avec Boost sélectionné, préparer **avant son installation/génération** la source `.ai/guidelines/` de ses renvois. Lorsque le corpus `.ai/rules/` possède déjà son index canonique, désactiver la gestion concurrente de ce répertoire par Boost ; la version consultée prévoit `BOOST_RULES_ENABLED=false`, à appliquer dans les environnements de développement concernés, puis à vérifier. Installer ensuite :

```bash
./vendor/bin/sail composer require --dev 'laravel/boost:<contrainte-boost>'
./vendor/bin/sail artisan boost:install
```

Le MCP doit exécuter le runtime déclaré, par exemple `./vendor/bin/sail artisan boost:mcp`, pas supposer un PHP natif. Vérifier les mécanismes et fichiers de génération de la version installée. Les entrées nécessaires doivent parvenir dans le clone, sans import en bloc des journaux de préparation. [Boost](https://laravel.com/framework/docs/13.x/boost) et [entretien documentaire](../interne/developpement/contribution-documentation.md).

La qualification opérationnelle doit ensuite exercer la recette depuis une préparation propre, sans cache ou fichier privé indispensable d'un autre poste. Une relecture du guide ne constitue pas cette exécution. Les [méthodes d'environnement](../../../.ai/rules/socle/20-environnement-dependances-qualite.md) et [Sail](../../../.ai/rules/projet/environnements/laravel-sail.md) conservent leurs garanties et statuts.

## Difficultés : examiner, corriger, vérifier

| Symptôme                                        | Action normale et nouvelle vérification                                                                                                                                                                                                                                                                                                                             |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Destination non vide                            | Examiner les fichiers, choisir une destination neuve et raccorder ensuite l'éditeur/documents. Aucun écrasement forcé.                                                                                                                                                                                                                                              |
| Création interrompue avec des fichiers partiels | Identifier la dernière étape réussie et préserver la destination. Si les sources attendues et `composer.json` sont présents, reprendre leur installation depuis le runtime d'amorçage et les locks disponibles, puis les hooks nécessaires. Ne pas relancer `create-project` sur ce dossier ni annoncer une reproduction verrouillée si aucun lock n'existe encore. |
| Permission refusée ou mauvais fichier affiché   | Vérifier UID/GID, montage, `pwd` et copie ouverte dans l'éditeur ; corriger la cause ciblée, puis recréer un fichier de contrôle et observer l'application.                                                                                                                                                                                                         |
| Extension ou version incompatible               | Lire le diagnostic et la contrainte réelle ; corriger le runtime puis reprendre plateforme/installation. Si les choix du profil se contredisent, demander leur arbitrage.                                                                                                                                                                                           |
| Alerte de sécurité sur une dépendance           | Identifier package, version et correctif ; retirer un composant inutilisé ou résoudre sa mise à jour compatible, puis reprendre audits/contrôles affectés. Un changement de choix important demande arbitrage ; ne pas désactiver le contrôle pour poursuivre.                                                                                                      |
| Script échoue sur une base ou un outil absent   | Identifier ses prérequis, les préparer et reprendre l'opération identifiée ; ne pas désactiver les scripts/plugins durablement ni ajouter un reset.                                                                                                                                                                                                                 |
| Volume PostgreSQL déjà initialisé               | Inspecter sa cible et son état ; le provisionnement initial ne se rejoue pas par un simple restart. Faire approuver une reprise si elle touche des données existantes.                                                                                                                                                                                              |
| Migration sans droits ou connexion inattendue   | Vérifier identité, base, schéma, configuration effective et cache du processus dédié ; corriger sa recette, sans rendre le compte applicatif superutilisateur.                                                                                                                                                                                                      |
| Page blanche ou ressources introuvables         | Vérifier mode choisi, serveur Vite, marqueur hot, manifeste, origine, port publié et page Inertia ; corriger, relancer/recompiler puis recevoir console/réseau.                                                                                                                                                                                                     |
| Service sain mais Laravel échoue                | Examiner destination interne, extension/client, credentials, configuration/cache et processus persistants ; reprendre le parcours depuis Laravel ou le navigateur, pas seulement le healthcheck.                                                                                                                                                                    |
| Arrêt/reprise perd un processus ou des données  | Vérifier maintien, volumes et commandes réellement utilisées ; corriger puis reprendre l'essai. Ne pas compenser par des workers concurrents ou `down -v`.                                                                                                                                                                                                          |

Quand la correction change une décision du projet, touche une cible non autorisée ou nécessite une action destructive, suspendre cette étape et obtenir la décision compétente. Les opérations indépendantes peuvent continuer dans leur périmètre reçu. Les réglages de GitHub, la publication d'image et l'installation du serveur sont des parcours distincts.
