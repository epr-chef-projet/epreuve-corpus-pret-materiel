# Qualité et recette — prêt de matériel

**Version 1.0.** La [qualification technique](../../cahiers-des-charges/technique/qualification.md) possède les objectifs et le lien aux exigences/scénarios fonctionnels ; ce document possède leurs profils d'exécution. Les résultats sont conservés dans les outils ou artefacts de validation, avec leurs références dans le suivi privé.

`./vendor/bin/sail composer ci:check` exécute les [contrôles des sources et des générés](installation.md#générés-et-contrôles-de-base). Les suites de tests et les parcours nécessaires sont sélectionnés selon les fonctionnalités concernées ; la disponibilité d'une suite ne prouve pas sa réception. Les exigences du profil applicable restent obligatoires.

Le développement courant utilise Vite/HMR. Le [démarrage](installation.md#4-travailler-au-quotidien) vérifie HTTP et Vite, en plus des raccordements requis. Le build des assets de livraison reste dans la chaîne commune ; il ne remplace pas les contrôles de types, lint ou format. Les contrôles locaux et la CI restent obligatoires selon le circuit de contribution ; aucune étape réussie ne tient lieu de revue ou d'autorisation de livraison.

<a id="ci-de-base"></a>

## CI de base — portée et critères

Les [workflows de base](installation.md#7-exécution-de-la-base-en-ci) utilisent l'identifiant `local` dans leur reçu technique ; ils ne valident pas à eux seuls le profil complet de réception des fonctionnalités décrit ci-dessous. `base-pr` porte le SHA de fusion d'essai d'une PR vers `main` ou `qualification/p-epr-c-07` ; `base-main` porte le SHA réellement poussé sur l'une de ces deux branches. La branche de contrôle reste distincte de `main`. Aucun filtre de chemins n'omet les contrôles pour une contribution documentaire.

Le workflow commun exécute les onze contrôles existants et les tests PHP, Vue et navigateur. Le reçu exige leur liste exacte, sans omission ni doublon, chaque résultat `passed` avec code natif zéro, et la réussite des pulls, du bootstrap, du démarrage, des contrôles, des tests et de l’arrêt. Il rapproche HEAD, copie suivie inchangée, projet/run isolé et état final. Les jobs `base-pr`/`base-main`, exécutés après leurs dépendances même en échec, exigent en plus le succès du job commun et les mêmes SHA, run GitHub et tentative. Résultat absent, sauté, neutralisé, annulé, timeout ou d'une autre exécution bloque le statut final ; aucun `continue-on-error`.

Runner éphémère `ubuntu-24.04`, actions épinglées par SHA complet et token limité à `contents: read` et `actions: read`. Les PR restent sans secrets d'exploitation, cache de poste ou publication privilégiée. Le reçu non secret est conservé quatorze jours comme artefact et dans le résumé du run ; les logs privés complets ne sont pas exportés. Le job natif est borné à 45 minutes, arrêt à 5 minutes et statut final à 3 minutes. Ces bornes de préparation ne mesurent pas les objectifs des profils complets ci-dessous. L'archive d'image est conservée sept jours et ses pièces de qualification quatre-vingt-dix jours selon le workflow commun.

Le workflow commun construit et vérifie aussi l'image finale : scan, médias, cinq rôles, Reverb, arrêt et intégrité de l'archive. Ces étapes techniques ne reçoivent pas les profils complets `ci-pr`/`ci-main`, les suites métier ou une publication GHCR. Vérifier leurs résultats sur la révision concernée et appliquer les [conditions de contribution](../exploitation/collaboration-livraison.md#contribution). Après annulation ou interruption, constater l'état réel des processus ; un arrêt réussi sur une autre exécution ne prouve pas leur arrêt.

<a id="outils"></a>

## EPR-Q-01 — Chaîne et portée

| Responsabilité                 | Outil/configuration                                                                                                                                                    |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Format PHP                     | Pint, vérification seule ; configuration et portée versionnées.                                                                                                        |
| Analyse PHP                    | PHPStan/Larastan compatibles Laravel 13/PHP 8.5, niveau 8 sans baseline initiale. Providers démarrés dans le profil sûr d'analyse, jamais avec secrets d'exploitation. |
| Contrats et types              | Génération qualifiée Laravel Data/Transformer ; `vue-tsc --noEmit` sur le projet incluant Vue, TS et types générés.                                                    |
| Conventions et format frontend | ESLint avec Vue/TypeScript, aucun avertissement admis initialement ; Prettier harmonisé, exclusions justifiées.                                                        |
| PHP                            | Pest : unités, intégration PostgreSQL, Feature, contrats et architecture.                                                                                              |
| Vue/TypeScript                 | Vitest + Vue Test Utils, composants/composables réels et doubles de frontières identifiées.                                                                            |
| Navigateur                     | Playwright Test TypeScript ; Chromium initialement, un worker, zéro retry masquant un échec. Firefox/WebKit restent une qualification séparée si ajoutés.              |

Versions compatibles à résoudre ensemble et verrouiller, pas installation des dernières versions indépendantes. Node 24 entre dans les plateformes actuellement déclarées par [Playwright](https://playwright.dev/docs/intro) et satisfait les prérequis documentés de [Vite](https://vite.dev/guide/). Recevoir images Linux, bibliothèques système et navigateurs de la version verrouillée lorsqu'ils sont introduits pour ces contrôles. La présence d'un exécutable ne prouve pas sa portée ; les tests nécessaires accompagnent les mécanismes et fonctionnalités concernés.

<a id="commandes"></a>

## EPR-Q-02 — Contrôles de base et profils futurs

`./vendor/bin/sail composer ci:check` conserve onze contrôles : manifeste et plateforme Composer, Pint, PHPStan, concordance des générés, types, lint, format, build et audits Composer/npm. Cette chaîne contrôle la base sans exécuter les suites de tests ; celles-ci ont leurs commandes explicites. Les profils décrits ci-dessous demandent leur propre implémentation et vérification ; ne pas attribuer leur portée au seul résultat de cette commande.

La chaîne courante contrôle la copie sélectionnée. Les workflows `base-pr` et `base-main` la reprennent sur une copie neuve isolée et associent SHA/run/tentative, runtime et locks au reçu, avec les contrôles d'image déclarés. Le profil de réception des contributions ajoute les suites et parcours nécessaires à leur contenu. **Les profils complets de réception des fonctionnalités et de CI sont des contrats à qualifier.** Vérifier leur état réel avant utilisation. Les suites métier locales utilisent l’installation existante du développeur, avec la base et les autres ressources réservées aux tests. Configurations, manifeste des contrôles requis, ordre, timeout et résultats attendus sont définis pour le profil concerné. Aucun filtrage silencieux selon les fichiers modifiés.

| Alias selon le profil         | Commande/responsabilité envisagée dans le runtime déclaré                                                              |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `composer contracts:generate` | Commande du générateur retenu, concordance de toutes les sorties TS et présence des fichiers ignorés nécessaires.      |
| `composer check:php`          | `vendor/bin/pint --test` puis `vendor/bin/phpstan analyse` ; préserver chaque échec.                                   |
| `composer test:php`           | Alias direct de `artisan test --compact` vers Pest ; même installation et base `testing` préparée, gardes SQL actives. |
| `npm run check:types`         | `vue-tsc --noEmit` sur le tsconfig qualifié ; pas seulement un projet de références vide.                              |
| `npm run check:lint`          | ESLint sur code Vue/TS et tests navigateur, `--max-warnings 0`.                                                        |
| `npm run check:format`        | Prettier `--check` sur sources et configurations concernées.                                                           |
| `npm run test:unit`           | `vitest run`, sans mode watch ni autorisation de suite vide.                                                           |
| `npm run build`               | Build Vite, manifeste et assets distribués contrôlés ; ne remplace pas le typage.                                      |
| `npm run test:browser`        | `vite build --mode browser` puis `playwright test --workers=1 --retries=0` dans le profil d’essai déclaré.             |

En développement, les commandes PHP/npm passent par Sail ; en CI elles utilisent les conteneurs déclarés dans [l'environnement](environnement.md). Exécutables locaux verrouillés, aucun téléchargement implicite par `npx`. Examiner dossier, index Git, sorties suivies/non suivies et générées ; `git diff` seul ne détecte pas un nouveau fichier oublié. Après génération/correction, reprendre les preuves invalidées. Prettier exclut explicitement les dossiers privés dès le parcours des fichiers ; Pint et ESLint les excluent également, sans retirer les sources ou tests partagés de leur portée. Les commandes transmettent les codes natifs ; les préconditions de la garde restent dans le [contrat commun](environnement.md#commandes).

<a id="profils"></a>

## EPR-Q-03 — Profils et contenu réellement vérifié

| Profil    | Entrée, preuves et limite                                                                                                                                                                                                   |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `local`   | Révision et état Git du contenu à soumettre ; génération, formats/analyses, PHP, Vue, build et parcours navigateur intégrés requis. Une modification postérieure invalide les garanties affectées.                          |
| `ci-pr`   | Révision de proposition et résultat de fusion d'essai contre la cible identifiés ; reprise indépendante de la suite commune avec services d'essai réels. Ni publication d'image admise ni accès aux secrets d'exploitation. |
| `ci-main` | SHA effectivement intégré à `main` ; suite commune, construction du livrable final, contrôles de l'image exacte et gate. Identités reliées dans [la livraison](../exploitation/collaboration-livraison.md#image).           |

Les raccordements réseau/SQL/job/fichiers/navigateur revendiqués utilisent leurs vrais services ; unités et simulations couvrent les variations moins coûteuses. L'installation technique ne demande pas à elle seule une suite `foundation`. Si ce stade existe, son manifeste déclare le périmètre réellement vérifié : `foundationchecks` réussi ne signifie ni noyau métier qualifié, ni 22 scénarios réussis. Les fonctionnalités apportent leurs preuves avant réception. Un contrôle essentiel indisponible ou vide bloque son profil ; une exclusion approuvée reste visible et borne le résultat.

<a id="isolation"></a>

## EPR-Q-04 — Données et isolation des essais

Les tests habituels utilisent l’installation existante du développeur : aucune deuxième copie ni nouvel ensemble de conteneurs n’est requis. La base PostgreSQL `testing` est réservée aux tests et préparée par les migrations du projet, sous l’identité migratrice selon l’[installation](installation.md#migrer-avec-lidentité-réservée). Les tests utilisent ensuite le rôle restreint `epr_app`, sans propriété du schéma ni droits DDL.

Depuis une installation démarrée et les migrations de `testing` à jour :

```sh
./vendor/bin/sail artisan test --compact
./vendor/bin/sail artisan test --compact tests/Feature/Auth/AuthenticationTest.php
./vendor/bin/sail composer test:contracts
./vendor/bin/sail npm run test:unit
```

La première commande exécute toutes les suites PHP ; la deuxième illustre une sélection pendant le travail. Les alias `composer test` et `composer test:php` transmettent leurs arguments à Artisan. Le succès exige une suite non vide, sans avertissement, test risqué, ignoré ou incomplet ; les mêmes exigences sont dans `phpunit.xml` et s’appliquent à Pest direct et à la CI. Un filtre sans test échoue.

`tests/TestCase.php` refuse un cache de configuration partagé ou hérité avant bootstrap, puis vérifie environnement `testing`, connexion déclarée et identité SQL effectivement connectée : serveur `pgsql`, base `testing`, session/rôle `epr_app`, schéma `epr`, absence de privilège propriétaire, superutilisateur ou contournement RLS. Un paramètre SQL hérité incorrect ne doit pas être forcé vers une réussite. La connexion applicative à `laravel` n’est pas une cible admissible pour ces tests.

Les tests transactionnels existants conservent `DatabaseTransactions` : les factories créent leurs propres données, puis leurs transactions sont annulées. Aucun `migrate:fresh`, reset ou semis global ne fait partie du lancement. Les fichiers, caches de vues et rendus PHP sont sous `storage/framework/testing/`. Cache, mail et sessions ordinaires sont en mémoire ; les jobs ordinaires sont synchrones. Les tests PHP n’utilisent ni Vite ni SSR ; build et navigateur vérifient leurs raccordements séparément.

Le test `QueueEffectTest` conserve une chaîne réelle Redis → Horizon → fichier privé. Il lance et arrête son processus Horizon dans le même runtime, avec bases Redis réservées `14` (file), `13` (cache), `12` (sessions), préfixe `epr_testing_` et files `testing-*`. Il observe le fichier écrit et son contenu déchiffré, puis retire uniquement sa sortie. Horizon local conserve ses ressources et ses files. Ces indices Redis sont réservés aux essais de cette installation ; aucun `FLUSHDB` ou `FLUSHALL` n’est requis.

`composer test:contracts` prépare les seules fixtures générées du test frontend de sérialisation ; il ne prépare aucun compte ni base. Les tests Vitest utilisent jsdom et une suite vide reste un échec. Reprendre cette génération après changement des fixtures PHP.

### Parcours navigateur

Installer une fois le navigateur correspondant au Playwright local verrouillé, puis lancer les scénarios :

```sh
./vendor/bin/sail npm exec --no -- playwright install chromium
./vendor/bin/sail npm run test:browser
```

La commande compile un build distinct `public/browser-build/`, incluant la page de fixture sous `tests/Fixtures/` sans la déplacer dans les pages applicatives. Playwright maintient lui-même les commandes officielles `artisan serve` et `reverb:start` dans le conteneur applicatif, sur `127.0.0.1:8000` et `127.0.0.1:8081`. Ces ports doivent être libres ; un serveur déjà présent est refusé. Aucun service Docker supplémentaire n’est nécessaire. Les scénarios vérifient connexion réelle, événement privé Reverb et sécurité du QR, sans retries.

Le profil de `tests/Browser/environment.ts` sélectionne `testing`, le rôle applicatif du clone, cookies et fichiers sous `storage/framework/browser/`, les seules ressources d’essai et des identifiants Reverb synthétiques. Le provider de fixture refuse une autre base ou un autre contexte. Chaque scénario crée un compte synthétique à adresse unique par factory, puis retire uniquement ce compte dans son nettoyage ; aucune préparation globale ni compte local existant n’est utilisé. Les serveurs sont arrêtés par Playwright même en échec. Les fichiers de résultats, builds et journaux d’essai restent ignorés et exclus du partage.

Les commandes sont séquentielles dans ce profil : ne pas ajouter `--parallel`, plusieurs workers ou plusieurs lancements simultanés sur cette même base et ces ports. La concurrence exige sa propre attribution de ressources. Les environnements supplémentaires gardent leur utilité pour une qualification d’installation ou d’image ; ils ne conditionnent pas les tests habituels.

Schéma préparé par le rôle de migration, application et preuves RLS sous `app_role`, non propriétaire/superutilisateur et sans `BYPASSRLS`. Tests SQL, HTTP, jobs, autorisation des canaux et fichiers gardent le contexte effectif prévu dans [données/sécurité](../../cahiers-des-charges/technique/donnees-securite.md). Vérifier refus sans contexte et passage atelier A → B après succès/erreur. SQLite, rôle d'initialisation ou policy désactivée ne prouvent pas ces accès.

Fixtures synthétiques minimales et discriminantes pour deux ateliers ; profils de volume séparés. Les courses utilisent connexions/processus indépendants, données committées visibles et barrières bornées. Une transaction englobant tout le test ne démontre ni concurrence ni callback après commit. Les doubles n'autorisent aucun appel externe non prévu.

<a id="raccordements"></a>

## EPR-Q-05 — Raccordements probants

Les preuves accompagnent les fonctionnalités ou mécanismes concernés ; elles sont examinées lors de la réception des contributions.

Observer état persistant et absence d'effet interdit, pas seulement HTTP 200 ou dispatch : dernière unité simultanée, réduction/archivage contre confirmation, versions, idempotence et réponse perdue. Pour les fichiers, provoquer interruption, nouvelle génération pendant traitement et accès croisé ; relire PDF/image effectivement publiés. Pour la chaîne durable, traverser commit, Redis, Horizon, reprise et résultat SQL. Pour Reverb, plusieurs sessions autorisées/refusées, coupure et rapprochement réel sans répétition de commande.

Les essais de l'image finale utilisent un pilote de test séparé : l'image de livraison reste sans Pest/Boost/outils de développement, ses services HTTP/Horizon/Reverb/ordonnanceur et ses assets proviennent du digest à vérifier. Données/services d'essai isolés, configuration publique fournie à l'exécution. Retrouver version active, résultat utile du job, fichier protégé et convergence navigateur. Les mesures/pertes de raccordement non exercées restent explicites.

La qualification technique du [profil d'image](../exploitation/collaboration-livraison.md#image) vérifie les raccordements installés. Reverb exige l'origine publique dans sa configuration effective, le message `pusher:connection_established` avec ses données attendues, le refus d'un autre host par `pusher:error` 4009 et la fermeture de la connexion acceptée à l'arrêt SIGTERM. HTTP 101 seul ne suffit pas. Les contrôles d'inventaire vérifient individuellement présence ou absence de `systemd-homed`, `tiffcrop`, bindings Python libxml2 et Perl `Archive::Tar`, avec commandes, résultats et identité exacte de l'image ; aucun résultat n'accorde d'exception de sécurité implicite. Réutiliser les contrôles existants concernés, sans ajouter une suite métier pour la seule installation technique.

Exercer aussi `/horizon` et les routes/API de logs, compteurs et jobs du package avec chacun des huit comptes produit et sans session, sous `APP_ENV=local` et `production` : 403/404 générique, aucune donnée de diagnostic. Le callback refuse indépendamment de l'environnement ; aucune exemption locale implicite. Vérifier `APP_DEBUG=false` et erreurs publiques maîtrisées. L'observation technique d'Horizon utilise CLI/journaux privés, pas via un rôle produit « admin » ajouté pour ce test.

<a id="refus"></a>

## EPR-Q-06 — Qualifier les contrôles et le circuit

À la réception du dispositif de contrôle concerné, dans son lot approuvé, injecter un défaut représentatif pour chaque responsabilité : format PHP, erreur PHPStan, accès TS erroné dans un template Vue, avertissement ESLint, dérive générée/nouveau fichier, test échoué, build manquant et fichier distribué absent. Vérifier l'échec jusqu'au lanceur/gate ; retirer les défauts et reprendre les preuves concernées. Ne pas construire un test miroir du script comme seule preuve de son refus. Ce protocole s'applique au dispositif concerné, sans imposer des sondes anticipées aux mécanismes non introduits.

Exercer aussi bootstrap sans `vendor/`, plateforme/configuration manquante, cible de reset incorrecte ; en livraison, contrôle sauté/annulé/manquant, preuve d'un autre SHA/run/digest, accord absent, image altérée, voie alternative et deux interventions incompatibles. Les scénarios de restauration utilisent une procédure réservée et une cible isolée autorisée. Les refus sont exercés dans un périmètre sûr ; aucune expérience destructive sur des données actives.

<a id="cout"></a>

## EPR-Q-07 — Coût et fiabilité

Objectifs proposés sur la machine de référence : contrôle court des frontières modifiées ≤ 30 s ; suite locale complète, hors bootstrap/téléchargement, ≤ 5 min ; `ci-pr` ≤ 10 min et construction/vérification `ci-main` ≤ 15 min. Déclarer durée de chaque phase, données, ressources et étendue. Ce sont des objectifs à mesurer, pas une permission de sauter un contrôle à l'échéance. Timeouts d'exécution proposés : 15 min pour `local`/`ci-pr`, 25 min pour `ci-main` ; tout dépassement échoue avec diagnostic.

Mesures contractuelles futures : `measure --profile volume --target qualified-test --run <id>` et même commande avec `--profile live` ; elles préparent les seuls jeux isolés décrits par qualification, contrôlent la cible et rapportent départs/erreurs/résultats/inachevés. Le profil `environment --instance c --run <id>` est décrit dans environnement. Aucun jeu de volume n'est injecté dans une instance active pour produire un benchmark.

Paralléliser seulement après isolation, avec agrégation bloquante. Clés de caches fondées sur locks/outils/configurations ; une absence de cache refait le travail. Aucune restauration d'un cache d'origine non fiable dans une phase de publication privilégiée. Diagnostiquer instabilité et tests lents ; pas de retry général, baseline ou quarantaine cachant la garantie manquante. Réutiliser les preuves encore valides selon leurs dépendances, sans réduire arbitrairement l'analyse aux seuls fichiers modifiés.

<a id="preuves"></a>

## EPR-Q-08 — Preuves et acceptation

Conserver dans les outils et artefacts de validation prévus : SHA/état soumis, profil, versions/services, données/graines utiles, contrôles requis et résultats, nombre de cas utiles, temps, écarts et limites. Compléter le suivi privé si nécessaire, sans journal dans la documentation partagée. Rapports JSON/JUnit et sorties CI sont bornés, sans mots de passe, sessions, payloads privés ou captures brutes ; les résultats publics n'exigent pas de données personnelles. Recette humaine et exploration complètent les régressions sans les remplacer.

Un seul résultat de recette peut relier plusieurs exigences lorsqu'il observe leurs garanties ; aucune fiche administrative par assertion ni quota universel de couverture. Distinguer relu, simulé, exécuté, réussi et accepté. Une recette décrite ne constitue pas une preuve applicative.

Procédures : [R18](../../../../.ai/rules/socle/18-tests-preuves-acceptation.md), [R19](../../../../.ai/rules/socle/19-performance-observabilite.md), [R20](../../../../.ai/rules/socle/20-environnement-dependances-qualite.md) et [LIV-CI](../../../../.ai/rules/projet/livraison/validation-ci-promotion-humaine.md), selon le profil du projet. Versions et configurations exactes se vérifient dans leur périmètre, sans réception implicite par la présence des fichiers.
