# ENV-SAIL — Environnement de développement Laravel Sail

**Version 1.0 — contenu validé.** [Index des compléments](../index.md).

**Applicabilité :** profils de développement sélectionnant Sail ; la variante WSL concerne seulement Windows/WSL. Retrouver les choix dans leurs [sources propriétaires](../../index.md#references-conception). Images, versions, chemins, ports, certificats, processus et recettes exécutables appartiennent au projet ; les exemples ne les adoptent pas. Ce complément ne sélectionne ni le profil CI ni celui de production.

**Lire pour :** installer depuis une copie propre, changer le profil local, exécuter commandes/tests, raccorder éditeur/outils/services ou diagnostiquer un écart. **Procédure :** profil/copie → amorçage → installation → raccordements → travail/cycle → preuves.

Les prescriptions `ENV-SAIL-001` à `ENV-SAIL-012` sont obligatoires dans leur périmètre, sauf **Revue** ou **Variante** selon [R01](../../socle/01-noyau-agent.md). [R20](../../socle/20-environnement-dependances-qualite.md) garde les garanties communes de runtime, configuration, dépendances, contrôles et collaboration. Ce complément précise leur application avec Sail ; aucun renvoi ne déclare l'environnement installé.

**Frontières :** R20 pour la configuration effective et l'installation ; [R08](../../socle/08-securite-identites-isolation.md)/[R09](../../socle/09-confidentialite-cycle-donnees.md) pour accès/données ; [R10](../../socle/10-jobs-evenements-fiabilite.md)/[R11](../../socle/11-temps-reel-synchronisation.md) pour consommateurs/transport ; [R14](../../socle/14-assets-fichiers-stockage.md)/[R16](../../socle/16-voix-avatar-medias.md) pour frontend/médias ; [R18](../../socle/18-tests-preuves-acceptation.md)/[R19](../../socle/19-performance-observabilite.md) pour preuves/mesures ; [R21](../../socle/21-livraison-exploitation-reprise.md) pour livraison. Consulter seulement les propriétaires affectés, selon leur statut.

## 1. Retrouver le profil et la copie

**ENV-SAIL-001 — Résoudre l'environnement effectif.** Identifier racine, configuration Compose utilisée, éventuelles surcharges/profils, contexte et moteur Docker, références d'images et recette du projet. Examiner les variables héritées qui peuvent modifier cette résolution, notamment `COMPOSE_FILE`, `COMPOSE_PROJECT_NAME`, `DOCKER_CONTEXT` et `DOCKER_HOST`. Contrôler aussi le lanceur Sail : fichiers sélectionnés par `SAIL_FILES`, service/utilisateur `APP_SERVICE`/`APP_USER` et fichier `.env.<APP_ENV>` si `APP_ENV` est renseigné et ce fichier existe, sinon `.env`. Une invocation Compose directe ne résout pas nécessairement le même profil. Vérifier versions et accès au moteur selon R20-001/002 ; relever les valeurs utiles sans diffuser une configuration contenant des secrets. Une commande Docker réussie ne prouve pas qu'elle vise le moteur ou le projet prévu.

**ENV-SAIL-002 — Raccorder une copie de travail identifiable.** Vérifier répertoire du terminal, sources réellement montées, répertoire de travail du conteneur, utilisateur et droits des fichiers créés. Raccorder éditeur et terminal à ces mêmes sources ; préserver les modifications lors d'un déplacement et vérifier quelle copie sert HTTP. **Variante Windows/WSL :** placer sources/dépendances actives dans le système de fichiers Linux, puis ouvrir cette copie depuis l'éditeur et utiliser un terminal WSL ; ne pas supposer une synchronisation avec une copie Windows. Ce raccordement ne déplace aucun autre dossier préparatoire. Corriger montage ou propriété plutôt qu'accorder indistinctement tous les droits ou généraliser l'exécution privilégiée.

## 2. Amorcer puis installer

**ENV-SAIL-003 — Obtenir le runtime avant le lanceur.** Depuis une copie sans `vendor/`, suivre la recette d'amorçage versionnée : image PHP/Composer qualifiée ou runtime explicitement déclaré et qualifié pour cette étape. Avec une image, vérifier référence, montage, répertoire, utilisateur et plateforme avant l'installation ; ne pas invoquer `vendor/bin/sail` avant sa disponibilité. Examiner hors bootstrap Laravel les sources de configuration héritées et le cache effectif selon R20-010/014. Si la recette manque, l'établir et la qualifier dans le périmètre autorisé avant l'étape dépendante ; ne pas remplacer silencieusement son runtime par celui du poste.

**ENV-SAIL-004 — Préparer les prérequis avant leurs consommateurs.** Suivre l'installation verrouillée R20-005 dans ce runtime ; préparer configuration locale, accès et ressources nécessaires avant tout script/plugin qui les utilise. Si des scripts sont différés, identifier lesquels, leurs prérequis et leur reprise ; cette préparation ne doit pas dépendre des scripts différés. Une dépendance circulaire exige de revoir la recette. Après disponibilité de Sail, vérifier sa plateforme effective séparément, reprendre les étapes prévues puis les initialisations restantes, avant d'activer les consommateurs. Ne pas régénérer une clé existante ni masquer un échec pour poursuivre. L'illustration A explicite un différé particulier, pas une procédure universelle.

## 3. Exécuter et maîtriser le cycle local

**ENV-SAIL-005 — Utiliser le runtime déclaré par Sail.** Une fois le lanceur et les services nécessaires disponibles, exécuter PHP, Artisan, Composer, commandes frontend et contrôles via `./vendor/bin/sail` et le gestionnaire retenu. Les scripts communs gardent leur configuration R20-017 ; un alias personnel reste facultatif. Vérifier aussi les chemins/interpréteurs de l'IDE et des outils des agents : fichiers visibles, accès au runtime et résultat utile. Le raccordement des agents garde sa procédure propriétaire R20-022. Préserver arguments, codes d'échec et interruptions jusqu'à l'appelant ; Git et l'éditeur peuvent rester hors du conteneur sans substituer son runtime applicatif.

**ENV-SAIL-006 — Choisir l'opération correspondant au changement.** Suivre la recette déclarée pour démarrer, arrêter, recréer les conteneurs, reconstruire une image ou mettre à jour l'environnement ; identifier effets et ressources avant exécution R20-007/008. Un redémarrage n'applique pas nécessairement une nouvelle image ou configuration. Après changement, observer la prise en compte par HTTP, console, frontend et processus persistants concernés R20-011. Avec Compose, `down` ordinaire retire conteneurs/réseaux concernés ; `down -v` retire aussi volumes nommés déclarés et anonymes attachés, hors volumes externes. Il n'efface pas les sources d'un bind mount. Aucun démarrage ou reconstruction ne contient implicitement un reset.

## 4. Raccorder réseau et processus

**ENV-SAIL-007 — Résoudre les adresses selon le consommateur.** Entre services du réseau Compose, utiliser noms de services et ports internes, sans IP de conteneur figée. Pour IDE/poste, retrouver moteur et port publié ; pour navigateur/téléphone, vérifier adresse réellement joignable et URLs frontend/WebSocket. `localhost` désigne le contexte qui l'utilise, pas universellement le serveur. Distinguer écoute, publication serveur et destination cliente, notamment pour Reverb ; ne pas remplacer l'une par l'autre. Vérifier interface de publication, accès et certificats selon le profil : une connexion interne n'exige pas à elle seule un port hôte publié.

**ENV-SAIL-008 — Démarrer et observer les consommateurs requis.** La recette identifie commandes/services et mécanisme de maintien ou terminal nécessaire pour HTTP, Vite, Horizon/workers, Reverb et ordonnanceur lorsqu'ils sont utilisés. Vérifier disponibilité des dépendances avant leurs consommateurs ; `depends_on` sans condition appropriée ne démontre pas cette disponibilité. Un healthcheck ne prouve que son contrôle. Vérifier ensuite un parcours autorisé : connexion avec le rôle applicatif, traitement d'un travail et réception/convergence côté client selon R10/R11. Contrôler code/configuration utilisés et renouvellement après changement ; ne pas ajouter des workers concurrents pour compenser un démarrage incomplet.

## 5. Protéger les ressources et réaliser les essais

**ENV-SAIL-009 — Identifier stockage et ressources partagées.** Relier données/fichiers aux volumes ou montages effectifs et vérifier leur reprise après arrêt/recréation. Un volume anonyme conservé n'est pas automatiquement remonté par un prochain `up` ; la persistance attendue exige un raccordement stable déclaré. Pour plusieurs projets/worktrees, examiner ports, identité Compose, noms de volumes/réseaux, bases, caches, files et consommateurs. Des noms explicites ou ressources externes peuvent rester partagés malgré deux noms de projet distincts. Avant migration, semis ou reset, résoudre la cible et coordonner les usages selon R20-008/024 ; une autre branche n'est pas une isolation de données.

**ENV-SAIL-010 — Exercer le profil d'essai déclaré.** Vérifier cibles effectives, services, rôles et données autorisés avant l'exécution selon R18 et R20-008/018, puis exécuter tests et contrôles dans Sail ; un fichier de test ne suffit pas à isoler une base. Pour les essais sur téléphone, qualifier chemin réseau, origine/certificats, frontend/HMR et WebSocket depuis cet appareil, sans supposer les adresses du navigateur du poste réutilisables. Adapter exposition et accès au seul profil prévu R08/R09. Simulations et appels externes réels suivent leurs profils déclarés ; aucun accès, secret ou effet réel n'est ajouté pour obtenir un résultat vert.

## 6. Mesurer et entretenir les recettes

**ENV-SAIL-011 — Diagnostiquer avec des mesures comparables.** Distinguer téléchargement/construction/installation initiaux, démarrage et réactivité quotidienne. Comparer requête locale représentative, actualisation frontend, tests et ressources au repos/en activité selon R19. Examiner emplacement des fichiers, montages, débogage/Xdebug, surveillance, processus et limites CPU/RAM avant de conclure. Une scrutation des fichiers, augmentation de ressources ou autre variante doit être motivée et mesurée ; ne pas imposer de réglage global WSL affectant d'autres projets sans examiner sa portée. Seuils et conditions de mesure appartiennent à la conception ; une lenteur inexpliquée n'est pas qualifiée par le seul choix de Docker.

**ENV-SAIL-012 — Qualifier puis transmettre la recette.** Éprouver depuis une copie propre l'amorçage, les commandes, raccordements, cycle et essais requis R20-026 ; conserver versions, résultats et limites dans les supports existants. Après changement de runtime, images, montages, configuration ou gestion des processus, reprendre les preuves invalidées et actualiser les recettes propriétaires. Une commande manquante ou une plateforme différente impose clarification/qualification avant l'étape concernée. Un repli sortant du profil Sail relève de R20 et de sa recette qualifiée ; ce complément ne l'adopte pas. Ne pas présenter les illustrations, le laboratoire ou une page accessible comme preuve d'un environnement complet.

## Illustrations — Hypothèses et parcours décisifs

### A — Arriver sur une copie propre

Hypothèse : Sail et npm sélectionnés, recette d'amorçage et configuration locale qualifiées. L'arbre ne montre que les sources utilisées ; `README.md` identifie aussi image/runtime d'amorçage, services/profils et initialisations nécessaires :

```text
README.md
compose.yaml
.env.example
composer.json + composer.lock
package.json + package-lock.json
```

**Avant Sail :** vérifier copie/montage, utilisateur/permissions, locks, configuration locale isolée et sources héritées R20-010/014. Obtenir une session PHP/Composer via l'image qualifiée définie par le projet, à la racine montée ; elle doit satisfaire la plateforme requise. Le nom/tag et la commande de lancement ne sont pas fournis ici : ne pas inventer un helper ni utiliser un PHP global différent. Préparer les prérequis des plugins/hooks avant leur exécution.

**Hypothèse du différé illustré :** plugins nécessaires disponibles pendant l'installation ; scripts temporairement désactivés, avec seul hook requis `post-autoload-dump` identifié dans `composer.json`. Composer reste responsable de l'autoload. Des hooks supplémentaires imposeraient leur propre séquence qualifiée, pas une reprise automatique de cette recette.

Dans la session d'amorçage :

```sh
composer install --no-scripts
```

Sail est maintenant disponible. Depuis le terminal de cette copie, Linux sous WSL lorsqu'il est utilisé, avec configuration Compose qualifiée pour démarrer les ressources locales nécessaires sans consommateurs prématurés :

```sh
./vendor/bin/sail up -d
./vendor/bin/sail composer check-platform-reqs
```

Vérifier disponibilité réelle et prérequis des hooks Composer/npm : configuration/cache sûrs, droits et services, rôles/données/clé locale lorsqu'ils en dépendent. Cette préparation doit fonctionner sans les hooks différés ; sinon revoir l'ordre/la recette. `up -d` ne prouve pas leur disponibilité. Puis :

```sh
./vendor/bin/sail composer run-script post-autoload-dump
./vendor/bin/sail npm ci
```

Exécuter dans cet ordre et s'arrêter sur échec. L'image d'amorçage ne prouve pas la plateforme de Sail. `--no-scripts` n'a pas différé les plugins ; rejouer le hook nommé ne répare pas universellement une installation sans plugin indispensable. Achever les initialisations dont ces scripts ne dépendaient pas, puis activer les autres processus et vérifier le résultat utile R20-006. Options/scripts npm examinés eux aussi ; aucun reset de volume requis ici.

### B — Application, PhpStorm et téléphone

Hypothèse fictive : moteur Docker local, service PostgreSQL nommé `pgsql`, port interne 5432 et publication locale 15432 explicitement autorisée. Ces valeurs ne sont pas celles du projet ; qualifier l'accès WSL/hôte effectivement utilisé.

| Consommateur | Raccordement et vérification |
|---|---|
| Laravel dans le réseau Compose | `pgsql:5432` ; exercer l'accès SQL avec le rôle applicatif configuré. |
| PhpStorm sur le poste | `127.0.0.1:15432` seulement si la publication y est joignable ; vérifier sa cible et ses droits. |
| Navigateur du poste | URL publique de développement déclarée ; vérifier frontend et WebSocket, pas uniquement HTML. |
| Téléphone | Adresse joignable de l'hôte/profil d'essai, origine/certificats requis ; vérifier HTTP, médias et WebSocket depuis ce téléphone. |

Pour Reverb, vérifier séparément son écoute, la destination utilisée par Laravel pour publier et celle du navigateur. Les noms de services internes ne deviennent pas automatiquement des noms accessibles au téléphone. Ne pas modifier le raccordement SQL interne pour réparer une publication hôte ; les connexions ont des consommateurs différents.

### C — Deux espaces de travail et une reconstruction

Hypothèse : deux worktrees A/B et recettes locales déclarées. Avant démarrage, relever moteurs, identités Compose, volumes/montages, bases, files et ports ; vérifier si des noms explicites ou ressources externes neutralisent l'isolation supposée.

| Opération | Procédure et preuve attendue |
|---|---|
| Recréer l'environnement A après changement d'image | Suivre construction/recréation déclarées, conserver stockage prévu, vérifier version active et accès à un jeu de données synthétiques repère. |
| Relancer un consommateur de A | Utiliser le mécanisme déclaré, observer disparition/renouvellement et traitement utile ; vérifier qu'il ne consomme pas la file de B. |
| Réinitialiser les données de test de A | Résoudre la base/rôle/volumes, examiner les usages concurrents ; si B utilise aussi la cible, coordonner ou isoler avant l'opération autorisée. |
| Comparer la réactivité | Refaire le même parcours avec conditions/données identifiées ; séparer construction initiale et requêtes quotidiennes, puis examiner montages/débogage/ressources. |

Une reconstruction réussie ne démontre ni conservation ni nouvelle version chargée. Un nom de projet différent ne suffit pas à autoriser le reset d'une ressource partagée ; mesurer ensuite ne répare pas une cible incorrecte.

**Repères vérifiés le 5 octobre 2026 :** [Sail et commandes](https://laravel.com/framework/docs/13.x/sail)/[lanceur](https://github.com/laravel/sail/blob/1.x/bin/sail), [scripts Composer](https://getcomposer.org/doc/articles/scripts.md)/[options et plateforme](https://getcomposer.org/doc/03-cli.md), [réseau Compose](https://docs.docker.com/compose/how-tos/networking/), [ordre/disponibilité](https://docs.docker.com/compose/how-tos/startup-order/), [volumes](https://docs.docker.com/reference/compose-file/volumes/)/[down](https://docs.docker.com/reference/cli/docker/compose/down/), [fichiers WSL](https://docs.docker.com/desktop/features/wsl/best-practices/)/[réseau WSL](https://learn.microsoft.com/en-us/windows/wsl/networking), [Reverb](https://laravel.com/framework/docs/13.x/reverb). Vérifier les versions retenues avant application ; aucune commande applicative, installation ou mesure exécutée par cette rédaction.
