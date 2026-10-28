# Installation et utilisation de la base

**Version 0.14.** Cette procédure possède l'installation d'un clone et le cycle de développement. L'[environnement](environnement.md) possède les paramètres ; la [qualité](qualite-et-recette.md) possède les responsabilités des contrôles et leurs critères.

Le projet comprend Laravel, Fortify, Inertia, Vue et les écrans d'authentification, avec les services retenus. Un clone reçoit les sources et leurs locks, sans nouvelle acquisition du kit. Les fonctionnalités et leurs contrats relèvent des [spécifications](../../cahiers-des-charges/fonctionnel/README.md) et des [contrats de modules](../architecture/contrats-et-lots.md).

## 1. Préparer le poste et choisir la copie

- Linux ou Ubuntu sous WSL, Bash, les utilitaires Linux usuels (`flock`, `sha256sum`, `realpath`), Git, Docker et Compose au moins 2.30 (`!reset` et arrêt `pre_stop`).
- Moteur Docker accessible sous le compte courant, contexte `default`. Vérifier avec `docker context show`, `docker info` et `docker compose version`.
- Sources sur le système de fichiers Linux ; terminal et éditeur ouverts sur la même racine. Le montage intérieur `/var/www/html` représente cette copie.
- Connexion réseau pour les images, paquets et fonts du build. PHP, Composer, Node et npm de l'hôte ne sont pas requis pour l'application.

Chaque instance de développement utilise une copie et des ressources distinctes. Attribuer son identité avant installation ; ne pas démarrer un deuxième clone avec une identité déjà utilisée. Pour une copie de contrôle supplémentaire, choisir un run isolé (§2). Le profil de livraison consomme une image et ne suit pas cette installation de développement.

Examiner les variables héritées avant toute commande : `COMPOSE_FILE`, `COMPOSE_PROJECT_NAME`, `DOCKER_HOST`, `DOCKER_CONTEXT`, `SAIL_FILES`, `APP_SERVICE`, `APP_USER`, `APP_ENV`, `APP_CONFIG_CACHE` et variables de connexion SQL peuvent changer la cible. L’amorçage et la garde Docker de Sail refusent les sélecteurs inattendus, une configuration d'une autre racine et un cache Laravel déjà présent. Diagnostiquer le refus avant de reprendre ; ne pas contourner les contrôles.

## 2. Obtenir un clone et l'amorcer

Le dépôt est `https://github.com/epr-chef-projet/epreuve-corpus-pret-materiel.git`. Cloner la branche ou révision communiquée dans une destination Linux neuve. Une vérification locale peut utiliser `git clone --no-hardlinks <racine-source-absolue> <nouvelle-racine-absolue>`, puis la révision à vérifier. Ne pas copier `.env`, secrets, vendor, node_modules ou état local d'un autre clone.

Configurer localement son nom et son adresse selon l’identité attribuée, en remplaçant les valeurs d’exemple :

```sh
git config user.name "Votre nom"
git config user.email "votre-adresse@example.org"
```

Cela ne configure pas l'authentification GitHub. Chaque intervenant utilise ses accès propres selon les [habilitations](../exploitation/collaboration-livraison.md#habilitations) ; aucun credential n'est partagé.

Depuis la racine du clone, préparer les paramètres privés de l’instance attribuée (`a`, `b` ou `c`) :

```sh
scripts/bootstrap --instance c
```

### Ce que fait `bootstrap`

Ce script prépare seulement les paramètres privés manquants d’une nouvelle copie : identité, ports, fichiers secrets PostgreSQL hors de la racine et clés locales. Il ne construit aucune image, n’installe aucun paquet, ne migre et ne démarre aucun service. Sur une copie déjà configurée, il vérifie la cible sans réécrire `.env`, clés ou secrets. Son intérêt est de créer ces valeurs ensemble, sous les droits prévus, sans diffuser de secrets dans les commandes.

Une copie supplémentaire de qualification peut ajouter `--run <nom>` et `--port-offset <nombre>` (0 à 1000). Cette variante est utile pour recevoir une installation depuis zéro ou pour la CI ; elle n’est pas nécessaire aux tests habituels. Chaque développeur utilise son installation existante et la base `testing` de son propre PostgreSQL.

### Runtime et dépendances verrouillées

Les commandes suivantes sont celles de Docker/Compose, puis de Sail dès que `vendor/` existe. S’arrêter au premier échec. Sur une installation neuve :

```sh
docker compose config --quiet
docker compose build laravel.test
docker compose pull pgsql redis mailpit
docker compose run --rm --no-deps --pull never --user "$(id -u):$(id -g)" --entrypoint composer laravel.test install --no-scripts --no-plugins --no-interaction
docker compose -f compose.yaml -f compose.bootstrap.yaml up -d --wait --wait-timeout 90 --no-build --pull never laravel.test pgsql redis mailpit
./vendor/bin/sail composer dump-autoload --no-scripts --no-interaction
./vendor/bin/sail npm ci --ignore-scripts --engine-strict
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail artisan package:discover --no-interaction
```

Attendus : runtime de `docker/8.5/Dockerfile`, dépendances des deux locks, plugin Pest autorisé, autoload et découverte Laravel disponibles. La surcharge `compose.bootstrap.yaml` maintient temporairement le service applicatif sans HTTP ; elle utilise les mêmes conteneurs, réseau et volumes que l’installation courante. Les hooks Composer sont différés jusqu’à disponibilité du contexte ; la découverte nommée est reprise explicitement, sans rejouer les installateurs du kit.

Préparer ensuite les bases par la procédure de migration ci-dessous, puis générer et compiler :

```sh
./vendor/bin/sail composer contracts:generate
./vendor/bin/sail composer test:contracts
./vendor/bin/sail npm run build
./vendor/bin/sail up -d --wait --wait-timeout 90 --no-build --pull never
./vendor/bin/sail ps
```

Attendus : générés disponibles, manifeste Vite compilé, huit services démarrés et sondes réussies. Le dernier `up` applique le profil HTTP courant au service temporaire. Les sondes ne prouvent pas les parcours métier. Ne pas utiliser `composer setup`, `sail:install`, `sail:publish` ou l’acquisition du kit pour installer ce clone.

## 3. Configuration, secrets et données

L’amorçage crée `.env` en mode `0600`, lié à la racine et à l'identité. Il prépare `APP_KEY` seulement si elle manque. Les origines HTTP/Vite/Reverb, cookies et préfixes Redis sont propres à l'instance. Préserver ces fichiers et la clé après arrêt ou interruption.

Les cinq secrets PostgreSQL sont créés hors racine applicative, sous `../.secrets/<projet>/`, dans un dossier privé. Les montages individuels permettent leur lecture par les processus concernés. Ne pas déplacer ces fichiers ou modifier leurs droits indistinctement ; l'application reçoit ses secrets métier et de recherche d'identité et les secrets privilégiés ne sont pas montés dans le service PHP ordinaire.

| Rôle SQL          | Responsabilité                                                                        |
| ----------------- | ------------------------------------------------------------------------------------- |
| `epr_bootstrap`   | Initialisation du cluster ; jamais compte HTTP ou worker                              |
| `epr_owner`       | Propriété du schéma et des objets, sans connexion                                     |
| `epr_migrator`    | Migrations dédiées, prend le rôle propriétaire                                        |
| `epr_app`         | Accès applicatif restreint, sans contournement RLS                                    |
| `epr_auth_lookup` | Connexion préatelier et sessions par capacités étroites, sans accès direct aux tables |
| `epr_backup`      | Sauvegarde globale, sans usage métier                                                 |

Les sources sont `docker/pgsql/20-bootstrap.sh`, ses SQL et le service Compose `migrator`. Les scripts d'entrée PostgreSQL initialisent seulement un volume neuf. Les migrations vérifient base, schéma, session migrateur et propriétaire effectifs ; aucun cache privilégié n'est partagé avec HTTP ou Horizon. IdentityAccess fournit ses capacités SQL dédiées ; les politiques RLS des tables métier sont introduites avec leurs modules.

PostgreSQL, Redis, fichiers et clés persistent après arrêt. Un volume existant sans configuration correspondante est refusé pour inspection. Si `.epr-local/compose.volumes.yaml` adapte les labels de volumes existants, vérifier son contenu exact ; cette surcharge ne doit changer ni montage, ni donnée, ni secret et n'est pas requise par un clone neuf. Ne pas supprimer un volume pour forcer l'initialisation ; une réparation particulière PostgreSQL nécessite une proposition et un accord avant intervention.

### Ajouter le rôle de connexion à une installation existante

Avant les commandes Sail d'une copie ne possédant pas encore `AUTH_LOOKUP_PASSWORD`, ajouter uniquement ses nouveaux paramètres privés avec l'instance attribuée :

```sh
scripts/bootstrap --instance a --add-auth-lookup
./vendor/bin/sail up -d --no-build --pull never pgsql
./vendor/bin/sail exec -T pgsql bash /opt/epr-postgres-bootstrap/provision-auth-lookup.sh
```

Remplacer `a` par l'instance reçue. Le bootstrap vérifie l'ancienne configuration et conserve clé, secrets et données ; il crée le seul secret manquant, hors clone. La dernière commande utilise l'identité d'initialisation uniquement dans PostgreSQL : elle crée ou contrôle `epr_auth_lookup`, lui attribue son propre mot de passe et les seuls accès aux bases/schéma. Elle ne rejoue pas `roles.sql` ni `database.sql`, ne modifie aucun autre rôle et ne réinitialise pas le volume. Un rôle existant avec des privilèges inattendus provoque un refus. Une nouvelle installation reçoit directement les cinq secrets et le rôle.

### Préparer la recherche Catalog

Un volume neuf reçoit `pg_trgm` 1.6 dans le schéma `epr` pendant l’initialisation PostgreSQL, avant les migrations Catalog. Sur une installation existante, vérifier le projet Compose et les sauvegardes puis exécuter uniquement :

```sh
./vendor/bin/sail exec -T pgsql bash /opt/epr-postgres-bootstrap/provision-catalog-search.sh
```

Ce script utilise l’identité d’initialisation déjà réservée au conteneur PostgreSQL de l’installation. Il contrôle exclusivement `laravel` et `testing`, leur schéma propriétaire et l’absence de droit `CREATE` pour `epr_app`. Il installe seulement cette extension, ou vérifie une installation déjà conforme ; une version ou un schéma inattendu provoque un refus. Il ne rejoue pas les fichiers d’initialisation, ne change aucun rôle et ne reconstruit aucune donnée. Ne pas lui substituer le compte d’une autre installation. Préparer ensuite les migrations ordinaires avec l’identité migratrice ci-dessous.

### Migrer avec l’identité réservée

Le service ponctuel `migrator`, dans le profil Compose `tools`, exécute les commandes Artisan officielles dans le runtime Sail de cette installation. Seul ce service reçoit le fichier secret migrateur. `config/database.php` le lit dans ce contexte réservé ; aucun mot de passe privilégié ne passe dans les arguments ni dans le service HTTP. Le cache de configuration de ce processus est dans son `/tmp`, distinct du cache partagé.

Avant la première commande Laravel, examiner hors bootstrap tout cache partagé ou `APP_CONFIG_CACHE` hérité ; la garde refuse le contexte inattendu. Les initialisateurs PostgreSQL ne s’appliquent qu’à un volume neuf. Pour un volume existant, ne pas recréer les rôles ni supprimer le volume.

Choisir explicitement `laravel` pour l’application ou `testing` pour les tests, puis vérifier la cible :

```sh
migration_database=testing
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator tinker --execute '
$identity = DB::selectOne("select session_user, current_user, current_database() as database, current_schema() as schema, host(inet_server_addr()) as address");
if (!in_array(getenv("DB_DATABASE"), ["laravel", "testing"], true) || app()->configurationIsCached() || app()->environment() !== "local" || $identity->session_user !== "epr_migrator" || $identity->current_user !== "epr_owner" || $identity->database !== getenv("DB_DATABASE") || $identity->schema !== "epr" || !in_array($identity->address, gethostbynamel("pgsql") ?: [], true)) { throw new RuntimeException("Migration target refused"); }
dump($identity);'
```

Attendus : session `epr_migrator`, rôle effectif `epr_owner`, base choisie, schéma `epr` et adresse du service PostgreSQL de cette installation. Une divergence ou un code non nul arrête la procédure. Avec la même cible et la même configuration :

```sh
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator migrate --no-interaction
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator migrate:status --no-interaction
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator tinker --execute '
if (DB::select("select c.relname from pg_class c join pg_namespace n on n.oid=c.relnamespace join pg_roles r on r.oid=c.relowner where n.nspname=? and c.relkind in (?,?,?,?,?) and r.rolname <> ?", ["epr", "r", "p", "S", "v", "m", "epr_owner"])) { throw new RuntimeException("Unexpected SQL object owner"); }'
```

Attendus : migrations appliquées ou aucune migration en attente, objets possédés par `epr_owner`. Exécuter cette séquence pour chacune des deux bases à l’installation, puis seulement lorsque les migrations changent. Elle ne fait ni reset ni semis. Les tests emploient ensuite `epr_app`, sans droits DDL ; les tests transactionnels ne relancent pas les migrations. La migration additive de routage des sessions crée uniquement un index et une fonction étroite IdentityAccess ; elle ne reconstruit ni table ni compte. Une copie déjà préparée reprend les migrations ordinaires sur chacune de ses deux bases autorisées, sans migrate:fresh ni semis implicite.

Le processus `horizon` de Compose reçoit explicitement `IDENTITY_SERVICE_ID=notifications` et `IDENTITY_SERVICE_CAPABILITIES=identity_access.publish` pour le routage des notifications. Conserver ces paramètres dans l'environnement de ce seul processus ; ne pas les ajouter au `.env` commun ni à l'image. Un autre hébergement doit appliquer la même séparation. Après changement de ce bloc ou du code des workers, renouveler le service existant avec la recette de mise à jour et vérifier `horizon:status` ; un worker déjà démarré ne charge pas automatiquement le nouveau code.

### Structure créée par les migrations

IdentityAccess crée directement les trois tables `identity_access_workshops`, `identity_access_users` et `identity_access_auth_sessions`, chacune dans sa migration de `app/Modules/IdentityAccess/Database/Migrations/`. Une migration complémentaire installe leurs capacités PostgreSQL réservées. Les comptes portent directement UUID, login, atelier et rôle ; aucun compte du starter n'est conservé dans une table parallèle. Les droits applicatifs directs sur les trois tables sont révoqués dans leur transaction de création.

`database/migrations/` conserve la création technique de `failed_jobs` : les tâches utilisent Redis mais leur fournisseur d'échecs écrit encore dans cette table. La table `migrations` est gérée par Laravel. `Catalog/Database/Migrations/` crée `catalog_equipment` : textes, total initial, état, version, dates, FK d’atelier, contraintes, index de tri/recherche et RLS forcée. Le runtime peut lire/insérer/modifier dans son contexte autorisé, sans suppression. `database/migrations/` crée aussi `operation_receipts`, propriété technique Operations : unicité de portée, empreinte, résultat minimal et FK composites d’acteur/référence. Le runtime peut lire et ajouter son reçu, sans modification ni suppression. Ces créations sont additives : une installation ne possédant pas encore ces tables les reçoit par `migrate` sur ses deux bases, après la préparation de recherche, sans reconstruction ni nouveau semis. Les tables déjà créées suivent les alignements ciblés ci-dessous lorsque leur définition correspond à une version antérieure prise en charge. Les tables SQL de sessions, cache, file et lots Laravel ne sont pas créées ; ces pilotes ne sont pas configurés par le projet. Les entrées applicatives inutilisées sont explicitement nulles quand le chargement Laravel fusionnerait sinon les configurations natives. Fortify reste limité à connexion/déconnexion ; les dépendances PHP qu'il requiert restent installées même lorsqu'une fonctionnalité optionnelle n'est pas exposée.

Une installation neuve suit `migrate`, puis la préparation explicite des comptes ci-dessous. Une mise à jour ordinaire utilise également `migrate` et préserve les données. Après consolidation des migrations initiales selon R05-017, une base existante ne s'aligne pas par le seul changement des fichiers : appliquer uniquement après autorisation la procédure de reconstruction suivante. Une conservation sans reconstruction exige une compatibilité vérifiée de sa structure et de son historique `migrations`.

### Recevoir la clôture des créations incertaines

La migration `2026_10_26_174346_add_closed_outcomes_to_operation_receipts` ajoute le statut obligatoire `applied` ou `closed`. Les anciens reçus et les insertions antérieures restent `applied` avec leurs valeurs et relations. Une clôture de création conserve seulement la portée et la date ; les contraintes refusent tout résultat fictif. Unicité, propriétaire, RLS forcée et droits applicatifs de lecture/insertion restent inchangés.

Prérequis : sauvegardes vérifiées, structure et historique attendus, accès migrateur existant et créneau permettant le verrou bref. Suivre [Migrer avec l'identité réservée](#migrer-avec-lidentité-réservée), explicitement pour `testing` puis `laravel`, avant d'activer les nouveaux lecteurs. `migrate --no-interaction` applique cette seule migration en attente, ou annonce l'absence de travail si elle est déjà reçue ; `migrate:status --no-interaction` la montre appliquée. Elle borne le verrou à deux secondes et l'instruction à cinq secondes ; un échec n'autorise ni forçage ni reset. Vérifier les anciennes valeurs, les nouvelles contraintes, les droits et les tests concernés après évolution. Aucun semis, nouvelle clé ou effacement n'est nécessaire.

Après activation, le [contrat de résolution](../architecture/contrats-et-lots.md#resolution-creation) permet de retrouver un succès ou de fermer une intention de création sans matériel. Dès qu'une clôture existe, un ancien lecteur ignorant `closed` ne constitue plus un retour compatible. Le `down()` refuse le retrait de ce schéma ; conserver les traces et leurs gardes, puis préparer une correction compatible en avant. Une restauration des bases actives reste une opération distincte à autoriser.

### Aligner les familles de reçus Catalog sans reconstruire

La création initiale de `operation_receipts` admet directement création, édition textuelle, archivage et réactivation. `migrate` ne réexécute pas une migration déjà enregistrée. Pour une installation conservant la version initiale à deux familles, vérifier la copie et son service PostgreSQL, les sauvegardes lisibles selon la procédure ci-dessous, puis l'historique complet des migrations et le schéma réel. Les deux migrations Catalog/reçus doivent déjà être enregistrées ensemble ; leurs noms et lots restent inchangés. Toute autre dérive demande inspection avant adaptation.

Choisir explicitement `migration_database=laravel` ou `migration_database=testing`, puis utiliser le seul rôle migrateur de cette installation :

```sh
./vendor/bin/sail exec -T pgsql sh -ec '
PGPASSWORD="$(cat /run/secrets/postgres_migrator_password)"
export PGPASSWORD
exec psql -X --no-password --host=127.0.0.1 --username=epr_migrator --dbname="$1" --file=/opt/epr-postgres-bootstrap/align-catalog-receipts.sql
' sh "$migration_database"
```

Le script refuse toute autre base, identité, schéma, contrainte inattendue ou histoire Catalog/reçus absente. Il remplace uniquement `operation_receipts_type_check`, dans une transaction bornée ; une contrainte déjà conforme est conservée. Aucun compte, matériel, reçu, droit ou enregistrement de migration n'est réécrit. Relever après exécution contrainte validée, propriétaires, RLS et permissions, historique inchangé et données conservées, puis effectuer les tests ordinaires sur `testing`. Chaque cible demande ses propres contrôles et sauvegardes. Une installation neuve utilise les migrations initiales et n'a pas besoin de cet alignement.

### Aligner le contrôle de l’auteur Catalog sans reconstruire

La création initiale de `catalog_equipment` exige un atelier, le rôle responsable et un `actor_id` non nul et non vide dans `WITH CHECK`, comme dans `USING`. Pour une base déjà migrée avec le contrôle d’insertion précédent, `migrate` ne rejoue pas cette définition. Vérifier la copie, son service PostgreSQL, les sauvegardes lisibles et la politique réelle avant l’alignement ciblé. Choisir explicitement `migration_database=laravel` ou `migration_database=testing` pour cette seule installation :

```sh
./vendor/bin/sail exec -T pgsql sh -ec '
PGPASSWORD="$(cat /run/secrets/postgres_migrator_password)"
export PGPASSWORD
exec psql -X --no-password --host=127.0.0.1 --username=epr_migrator --dbname="$1" --file=/opt/epr-postgres-bootstrap/align-catalog-actor.sql
' sh "$migration_database"
```

Le script exige `epr_migrator`/`epr_owner`, le schéma `epr`, la migration Catalog enregistrée et la politique antérieure exacte ou sa forme finale. Toute autre dérive est refusée. Il vérifie propriétaire, rôle applicatif, RLS forcée et politique unique, puis modifie seulement `WITH CHECK` dans une transaction bornée ; une cible conforme reste inchangée. Aucun compte, matériel, reçu, privilège ou historique de migration n’est réécrit. Contrôler ensuite la politique, les données et permissions conservées, puis les tests ordinaires sur `testing`, avec atelier/rôle valides et acteur seul absent ou vide. Une installation neuve reçoit directement la politique finale par la migration initiale.

<a id="reconstruire-une-base-locale-après-consolidation"></a>

### Reconstruire une base locale après consolidation

Cette opération supprime toutes les tables et données de la base choisie. Elle nécessite un accord explicite désignant l'installation et chacune des bases à reconstruire ; un accord sur les migrations n'autorise pas les autres installations. Sur une base à conserver, arrêter et faire définir une procédure compatible. Ne supprimer ni volume, schéma ou rôle, ni clé applicative ; ne pas rejouer les scripts PostgreSQL d'initialisation.

1. Vérifier la racine, l'identité Compose, les montages et les volumes de l'installation. Arrêter ses consommateurs et les tests concurrents avec `./vendor/bin/sail stop laravel.test horizon reverb scheduler vite`, en conservant PostgreSQL et Redis. Les commandes ponctuelles passent par `./vendor/bin/sail --profile tools run ...` ; `sail run` seul désigne une autre commande du lanceur.
2. Choisir explicitement `migration_database=laravel` ou `migration_database=testing`. Reprendre le contrôle de cible de la section migrateur : environnement local non caché, service `pgsql` de cette installation, session `epr_migrator`, rôle `epr_owner`, schéma `epr` et base choisie. Inventorier tables, données, historique, fonctions et permissions locales à rétablir. Une dépendance ou donnée inattendue modifiant les hypothèses de l'accord arrête la reconstruction concernée.
3. Sauvegarder et vérifier chaque cible avant destruction. Conserver la révision correspondante, l'inventaire, les permissions locales et le reçu `.epr-local/identity-<base>.json` s'il existe dans un dossier privé protégé, exclu de Git, des images et des archives partagées. Comparer les identités du reçu aux comptes réellement présents ; une divergence ou un reçu incomplet doit être examiné, sans suppression automatique.

Exemple de sauvegarde d'une cible déjà vérifiée, dans un dossier neuf et privé :

```sh
umask 077
backup_dir=$(mktemp -d project-management/local/database-backup.XXXXXX)
backup_file="$backup_dir/$migration_database.dump"
./vendor/bin/sail exec -T pgsql sh -ec '
PGPASSWORD="$(cat /run/secrets/postgres_backup_password)"
export PGPASSWORD
exec pg_dump --host=127.0.0.1 --username=epr_backup --no-password --format=custom --dbname="$1"
' sh "$migration_database" > "$backup_file"
test -s "$backup_file"
./vendor/bin/sail exec -T pgsql pg_restore --list < "$backup_file" > "$backup_dir/toc.txt"
./vendor/bin/sail exec -T pgsql pg_restore --file=/dev/null < "$backup_file"
sha256sum "$backup_file" > "$backup_dir/sha256.txt"
```

Arrêter au premier code non nul. L'inventaire et le décodage vérifient la lisibilité complète de l'archive, sans restaurer ni modifier une autre base ; ils ne remplacent pas un exercice de restauration. Conserver aussi une copie privée du reçu avant la suite.

4. Dans ce schéma, `migrate:fresh` ne retire pas les huit fonctions IdentityAccess. Après recontrôle de la cible migratrice, inventorier leurs signatures, définitions, propriétaires et permissions, puis les objets qui en dépendent. Les lectures suivantes ne modifient pas la base :

```sh
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator tinker --execute '
dump(DB::select("
SELECT n.nspname AS schema, p.proname AS name, oidvectortypes(p.proargtypes) AS arguments,
       p.prokind AS kind, pg_get_userbyid(p.proowner) AS owner, l.lanname AS language,
       p.prosecdef AS security_definer, p.proconfig AS settings, p.proacl AS direct_acl,
       has_function_privilege(?,p.oid,?) AS public_execute,
       has_function_privilege(?,p.oid,?) AS app_execute,
       has_function_privilege(?,p.oid,?) AS lookup_execute,
       pg_get_functiondef(p.oid) AS definition
FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace JOIN pg_language l ON l.oid=p.prolang
WHERE n.nspname=? AND left(p.proname,16)=?
ORDER BY p.proname,p.proargtypes",
["public", "EXECUTE", "epr_app", "EXECUTE", "epr_auth_lookup", "EXECUTE", "epr", "identity_access_"]));
dump(DB::select("SELECT p.proname AS name, oidvectortypes(p.proargtypes) AS arguments, pg_describe_object(d.classid,d.objid,d.objsubid) AS dependent FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace JOIN pg_depend d ON d.refclassid=p.tableoid AND d.refobjid=p.oid WHERE n.nspname=? AND left(p.proname,16)=? ORDER BY p.proname,dependent", ["epr", "identity_access_"]));'
```

Attendus sur une base existante conforme : exactement les huit signatures du bloc de retrait ci-dessous, sans surcharge supplémentaire, toutes de nature fonction (`kind=f`) et possédées par `epr_owner`. Définitions et permissions correspondent aux [capacités IdentityAccess](../../../../app/Modules/IdentityAccess/Database/Migrations/2026_10_19_090917_create_identity_access_capabilities.php) et au [routage des sessions](../../../../app/Modules/IdentityAccess/Database/Migrations/2026_10_20_144632_add_identity_access_session_routing.php), avec les seuls droits locaux déjà autorisés et inventoriés. Les colonnes `public_execute`, `app_execute` et `lookup_execute` vérifient les droits effectifs, y compris hérités, à confronter aux `GRANT`/`REVOKE` de ces migrations. `direct_acl` expose seulement les ACL directes ; compléter le contrôle pour chaque rôle local inventorié. La seconde lecture ne renvoie aucun objet dépendant dans ce schéma livré.

Les huit corps sont définis en texte `AS $$...$$` : PostgreSQL ne suit pas toutes leurs références internes dans `pg_depend`. Relire aussi les corps et leurs consommateurs, même si la seconde lecture est vide ; voir les [dépendances PostgreSQL](https://www.postgresql.org/docs/18/ddl-depend.html). Un inventaire partiel, une surcharge, une définition, un propriétaire, un droit ou une dépendance inattendus impose une inspection avant tout retrait ou `migrate:fresh`. Sur une base réellement neuve, sans tables ni historique ni ces fonctions, reprendre l'installation initiale avec `migrate` : aucun retrait n'est nécessaire.

Pour la seule cible existante conforme dont la reconstruction est autorisée, consommateurs arrêtés et sauvegarde vérifiée, retirer d'abord la fonction de routage, puis les sept capacités dans l'ordre inverse de leur création. Avec la même cible migratrice recontrôlée :

```sh
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator tinker --execute '
DB::transaction(function (): void {
    DB::unprepared("DROP FUNCTION epr.identity_access_session_candidates(jsonb,uuid,uuid,integer);
DROP FUNCTION epr.identity_access_admit_session(text,uuid,text);
DROP FUNCTION epr.identity_access_assert_actor(uuid,uuid,text);
DROP FUNCTION epr.identity_access_revoke_session(text);
DROP FUNCTION epr.identity_access_start_session(uuid,uuid,text,text,text);
DROP FUNCTION epr.identity_access_rehash_password(uuid,text,text);
DROP FUNCTION epr.identity_access_resolve_identity(uuid,text);
DROP FUNCTION epr.identity_access_find_identity(text);");
});'
```

Attendu après commit : aucune des huit fonctions ne subsiste, ce que la première lecture d'inventaire permet de constater. Tout échec de retrait annule cette transaction et interdit de poursuivre vers la suppression des tables. Ne pas ajouter `CASCADE`, `IF EXISTS` ou masquer la dérive pour continuer ; le retrait conserve le [refus des dépendances](https://www.postgresql.org/docs/18/sql-dropfunction.html) par défaut. Après interruption ou perte de réponse, constater les objets réellement présents avant reprise, sans supposer le rollback ni rejouer aveuglément le bloc.

5. Reconstruire les tables, sans semis global et sans dépendre des anciens `down()` :

```sh
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator migrate:fresh --no-interaction
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE="$migration_database" migrator migrate:status --no-interaction
```

Vérifier ensuite structure finale, propriétaires `epr_owner`, fonctions, contraintes et permissions effectives. Les créations et leurs révocations sont transactionnelles ; aucun rôle applicatif ne devient propriétaire. Les `down()` des migrations retirent leurs objets et ne restaurent aucune donnée supprimée. Une reprise depuis sauvegarde nécessite le code et la procédure de restauration correspondant à cette sauvegarde.

6. Après reconstruction vérifiée de la cible, archiver son seul ancien reçu `.epr-local/identity-<base>.json` dans le dossier privé de l'opération, sans écraser de fichier existant. Ne pas nettoyer globalement `.epr-local/`. Puis exécuter la préparation explicite ci-dessous avec `DB_DATABASE` et `--target` désignant cette même base. Vérifier deux ateliers et huit comptes, les identifiants et rôles attendus et la concordance du nouveau reçu avec SQL. Les nouveaux UUID rendent les anciennes connexions caduques ; les logins et le mot de passe synthétique restent ceux de la recette. Si la préparation est interrompue, comparer reçu et base avant de reprendre, sans effacer aveuglément le reçu.
7. Vérifier les permissions communes rétablies par les migrations, puis réappliquer les seuls droits locaux de consultation déjà autorisés et inventoriés : la recréation des tables et fonctions retire leurs grants particuliers. Aucun droit `EXECUTE` supplémentaire n'est implicite. Ne pas inscrire de rôle propre à un poste dans les migrations communes, ni ouvrir les métadonnées de sessions. Conserver les secrets et les rôles existants.
8. Reprendre les services selon le démarrage quotidien, vérifier HTTP, connexion/déconnexion, SQL/Redis et accès de consultation, puis les tests sur `testing`. Ne purger ni Redis ni les volumes. Traiter chaque autre cible uniquement dans son autorisation et après ses propres contrôles/sauvegardes.

### Préparer explicitement les comptes synthétiques

Après les migrations, sur une cible locale autorisée vide uniquement, initialiser séparément les huit comptes de `laravel` et ceux de `testing` :

```sh
./vendor/bin/sail --profile tools run --rm --no-deps -T migrator identity-access:prepare-accounts --target=laravel --no-interaction
./vendor/bin/sail --profile tools run --rm --no-deps -T -e DB_DATABASE=testing migrator identity-access:prepare-accounts --target=testing --no-interaction
```

Chaque cible reçoit deux ateliers avec responsable, agent et deux emprunteurs. Ces huit comptes de démonstration utilisent tous le mot de passe `password`, uniquement pour ces cibles locales. Leurs identifiants et le mot de passe sont également fournis dans `.epr-local/identity-laravel.json` ou `identity-testing.json`, en lecture privée locale. Les mots de passe sont hachés en base par Laravel. La préparation exige l'environnement `local`, la session migratrice et le rôle propriétaire ; HTTP et les tests n'acquièrent aucun droit de création de comptes. Aucun semis global n'est exécuté au lancement des tests.

Une répétition sur une cible peuplée ou un reçu déjà présent est refusée, sans changement de compte ou de mot de passe. Le reçu privé est écrit et synchronisé avant la transaction SQL : après interruption, conserver le reçu et comparer la cible avant toute reprise ; sa présence seule ne prouve pas le commit. Après une reconstruction explicitement autorisée et vérifiée, l'ancien reçu de cette seule cible est archivé selon la procédure précédente, puis un nouveau reçu est créé avec les comptes réellement préparés. Hors de ce cas, conserver comptes et reçu ; toute divergence nécessite une inspection.

### Préparer explicitement le Catalogue de démonstration

`Catalog/Database/Seeders/CatalogDemoSeeder.php` est appelé uniquement par `catalog:prepare-demo`, jamais au démarrage ni par le semis global. Sur une cible locale expressément choisie, avec ses comptes synthétiques préparés, il fournit les six références par atelier du profil `demo` : noms identiques pour le tri stable, total nul, dernière unité, archive et plusieurs actives. Il ne crée aucune demande, aucun engagement ou image.

Depuis le runtime ordinaire `epr_app`, pour le seul Catalogue vide de la cible choisie :

```sh
./vendor/bin/sail artisan catalog:prepare-demo --target=laravel --no-interaction
./vendor/bin/sail exec -T --user sail -e DB_DATABASE=testing laravel.test php artisan catalog:prepare-demo --target=testing --no-interaction
```

Choisir une seule commande selon la cible autorisée ; ne pas peupler `laravel` déjà utilisé pour les essais manuels. Le contrôle vérifie la base et l'identité SQL effectives, l'absence de transaction extérieure et le reçu privé IdentityAccess de cette base. Les deux responsables y sont revalidés par les capacités publiques IdentityAccess ; le seeder ne consulte pas les tables de son voisin. Tout Catalogue déjà peuplé dans l'un des ateliers est refusé avant la préparation. Chaque atelier est rempli dans une unité propre, avec un nouveau contrôle de vacuité ; aucune atomicité entre ateliers n'est annoncée.

Le manifeste privé `.epr-local/catalog-demo-<base>-<UUID>.json` est synchronisé avant les écritures et identifie exactement les douze UUID possédés par cette préparation. Après interruption, sa présence seule ne prouve pas que les deux ateliers sont préparés : comparer manifeste et SQL avant reprise, sans ressemer ni effacer aveuglément. Sur une cible d'essai autorisée, le retrait des seules fixtures identifiées utilise l'identité d'initialisation hors tests, après contrôle des dépendances ; il ne donne aucun droit de suppression au runtime. Les identifiants et mots de passe des comptes restent ceux du reçu IdentityAccess.

### SMTP de développement

Le SMTP de développement utilise Mailpit : `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, sans identifiant externe. Les messages restent capturés localement. Aucun parcours métier du noyau ne déclenche d'email ; les essais gardent leurs transports isolés.

## 4. Travailler au quotidien

Depuis la racine de l’installation déjà préparée :

```sh
./vendor/bin/sail up -d --wait --wait-timeout 90 --no-build --pull never
./vendor/bin/sail ps
./vendor/bin/sail artisan test --compact
./vendor/bin/sail stop -t 90
```

`up` démarre HTTP, Vite, PostgreSQL, Redis, Mailpit, Horizon, Reverb et l’ordonnanceur. Il n’installe, ne migre ni ne compile. `stop` conserve volumes, configuration et clés. Aucun marqueur de préparation ni nouvelle construction d’image n’est requis pour reprendre une installation inchangée.

Les tests habituels utilisent cette même installation, la base `testing` déjà migrée et le rôle applicatif restreint. Leur configuration sélectionne des ressources réservées ; la [recette qualité](qualite-et-recette.md#isolation) décrit aussi les commandes Vue et navigateur. `composer test` et `composer test:php` sont des alias directs de la commande Artisan, avec transmission des arguments et du code natif.

La garde `scripts/docker-guard`, raccordée par `SAIL_DOCKER_BINARY`, vérifie la racine, la configuration privée et les ressources Docker ciblées, et sérialise les opérations de cycle incompatibles. Elle préserve le raccordement des installations existantes sans orchestrer leurs tests. `environment.sh` partage ces vérifications avec l’initialisation. Les opérations de suppression ne font pas partie du cycle courant ; utiliser `stop`. Le hook Compose de Vite retire seulement le fichier régulier `public/hot` de cette racine à l’arrêt ; après arrêt brutal, vérifier ce fichier et le processus concerné avant intervention.

Le service `vite` maintient déjà `npm run dev` : ne pas démarrer un second Vite. Le build sert aux contrôles et à la livraison. En cas d’erreur de manifeste ou d’adresse HMR, vérifier processus, racine et origine avant de reconstruire.

### Générés et contrôles de base

```sh
./vendor/bin/sail composer contracts:generate
./vendor/bin/sail composer test:contracts
./vendor/bin/sail composer ci:check
```

`contracts:generate` coordonne Wayfinder et les types PHP attribués sous `app/`, puis vérifie présence et concordance des sorties ignorées ; cette vérification justifie `scripts/contracts.php` en complément des générateurs officiels. Modifier leurs sources, pas leurs sorties.

`test:contracts` prépare uniquement les types et le JSON de fixtures utilisés par `tests/Frontend/contractProbe.test.ts`. `scripts/contracts-generate.php` applique les mêmes transformateurs et sérialise le véritable DTO ; un exemple JSON écrit à la main ne prouverait pas ce raccordement. Cette préparation ne touche plus les routes applicatives et n’est pas nécessaire aux tests PHP habituels.

`ci:check` conserve onze contrôles : manifeste Composer, plateforme, Pint, PHPStan niveau 8, concordance des générés, types Vue/Node stricts, ESLint sans avertissement, Prettier, build et audits Composer/npm. Les tests ont leurs commandes propres et restent requis selon la recette qualité. Les sondes `epr-probe.php` contrôlent la véritable identité SQL et Redis pour les services ; elles ne remplacent pas les tests.

## 5. Mettre à jour, reconstruire et reprendre

Préserver les modifications locales et sélectionner la révision selon la procédure de contribution. Distinguer changement de code, dépendances, runtime et données ; une reprise ordinaire utilise seulement `sail up`. Pour une mise à jour des dépendances ou du runtime, coordonner l’arrêt des consommateurs :

```sh
./vendor/bin/sail stop -t 90
docker compose build --build-arg EPR_CACHE_REFRESH="$(date -u +%F)" laravel.test
docker compose -f compose.yaml -f compose.bootstrap.yaml up -d --wait --wait-timeout 90 --force-recreate --no-build --pull never laravel.test pgsql redis mailpit
./vendor/bin/sail composer install --no-scripts --no-plugins --no-interaction
./vendor/bin/sail composer dump-autoload --no-scripts --no-interaction
./vendor/bin/sail npm ci --ignore-scripts --engine-strict
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail artisan package:discover --no-interaction
```

Le paramètre `EPR_CACHE_REFRESH` renouvelle les index et paquets système pour la journée UTC lors de cette reconstruction. La construction refuse PHP antérieur à 8.5.12 ou hors de la branche 8.5. Après reprise, contrôler `./vendor/bin/sail php --version` et le PHP réellement utilisé dans `horizon`, `reverb` et `scheduler` avec `./vendor/bin/sail exec -T <service> php --version`. Aucun processus PHP persistant ne doit conserver l’ancien runtime. Cette mise à jour technique ne demande ni réinitialisation SQL ni nouvelle clé applicative.

La construction est nécessaire si sa recette change ; `composer install` et `npm ci` suivent leurs locks. Préparer les migrations modifiées avec la procédure réservée ci-dessus, puis reprendre générations, build, démarrage et contrôles affectés. La configuration Compose transmet les budgets ImageMagick aux processus PHP dès leur création ; une modification de ces paramètres demande leur recréation, sans assouplir les tests de limites.

Une interruption laisse une étape à diagnostiquer avec `sail ps` et les sorties natives. Corriger la cause et reprendre la commande concernée ; ne pas relancer systématiquement toute l’installation. Les anciens fichiers privés de préparation peuvent être conservés : ils ne commandent plus le démarrage. Aucun `.env`, secret, volume ou clé ne doit être effacé pour reprendre. `down -v`, `migrate:fresh`, purge Redis globale et restauration de données demandent leur procédure et autorisation propres.

## 6. Résolution locale et agents

Le navigateur du poste utilise les noms `*.localhost`. Dans WSL, vérifier le nom de `APP_URL` avec `getent hosts <nom>`. Si nécessaire, faire ajouter l'entrée de boucle locale par un administrateur, puis vérifier sa persistance après redémarrage complet. L'amorçage ne modifie ni sudoers ni réglages globaux WSL.

Pour un diagnostic ponctuel sans modifier le poste, `curl --resolve <nom>:<port>:127.0.0.1 http://<nom>:<port>/` vérifie HTTP avec le bon Host ; cela ne prouve pas la résolution DNS ordinaire. Compose contrôle les services par leurs sondes ; la recette HTTP utilise également l’origine déclarée avec le bon Host.

[AGENTS.md](../../../../AGENTS.md) et [CLAUDE.md](../../../../CLAUDE.md) orientent vers le corpus unique. Les configurations MCP partagées appellent `./vendor/bin/sail artisan boost:mcp` dans le runtime de ce clone ; aucun compte ou jeton n'est livré. Chaque client doit accepter sa configuration de projet et recevoir la découverte des instructions à son arrivée, selon le [raccordement](../raccordement-agents.md). La disponibilité du serveur MCP ne prouve pas sa connexion par une nouvelle session cliente.

## 7. Exécution de la base en CI

Les workflows de [proposition](../../../../.github/workflows/base-pr.yml) et de [révision intégrée](../../../../.github/workflows/base-main.yml) appellent le [workflow commun](../../../../.github/workflows/base-common.yml). Le runner `ubuntu-24.04` neuf reprend les commandes d’installation ci-dessus, avec paramètres et ressources privés de CI, puis les onze contrôles, tests PHP/Vue/navigateur et contrôles de l’image. La CI reste obligatoire et indépendante du poste.

Le reçu associe révision, run/tentative, locks, runtime et résultats natifs. Chaque étape indispensable doit réussir ; une étape échouée, sautée ou annulée ne valide pas le résultat agrégé. Les paramètres de runtime enregistrés pour ce reçu ne sont pas un préalable aux commandes quotidiennes. Les fichiers `.env`, secrets et journaux privés ne sont pas publiés. Les [statuts bloquants](qualite-et-recette.md#ci-de-base) gardent leurs responsabilités ; l’installation locale ne publie ni image ni code.

## 8. Limites et étapes ultérieures

Les versions applicatives sont verrouillées. Les paquets système sont résolus dans leurs branches ; une reconstruction n'est pas garantie identique au bit près. Le build dépend du réseau et des fonts.

Les workflows disponibles définissent la portée effective de la CI. Les protections GitHub et les contrôles requis restent applicables ; un arrêt réussi ne prouve pas le traitement de toutes les formes d'annulation. Diagnostiquer séparément l'état des services après une interruption.

La construction et la publication d'une image ainsi que son déploiement ne sont pas des effets de cette installation. Le provisionnement des ateliers et comptes est une opération distincte. Les politiques RLS métier, outbox, canaux privés, traitements de fichiers, scénarios fonctionnels et leurs tests sont vérifiés avec les fonctionnalités qui les utilisent.
