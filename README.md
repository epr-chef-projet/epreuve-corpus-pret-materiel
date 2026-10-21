# Application de prêt gratuit de matériel

Application pour deux ateliers indépendants : catalogue de matériel, demandes multiarticles, réservation des quantités, prêts et bordereaux PDF privés. Le [cahier des charges fonctionnel](project-management/docs/cahiers-des-charges/fonctionnel/README.md) définit les comportements attendus.

La base Laravel, Vue/TypeScript et Inertia utilise Docker/Sail avec PostgreSQL, Redis, Horizon, Reverb et Mailpit. Les comptes précréés se connectent par identifiant, avec leur atelier, leur rôle et une session limitée à deux heures. Le cadre des écrans est disponible en français et anglais, avec une navigation adaptée au rôle et une protection des écrans lors des changements de session. Le responsable peut créer un matériel avec son total initial, rechercher et filtrer la liste paginée, consulter sa fiche et modifier ses textes, archiver et réactiver un matériel. Les enregistrements disposent d’un reçu et d’une vérification après réponse perdue ; les actualisations conservent la saisie en cours. Images et changement du total après création restent à raccorder. Le catalogue de prêt, les demandes et les quantités engagées ou disponibles restent explicitement indisponibles, sans valeur fictive.

## Installer

Prévoir Linux ou WSL, Bash, Git, les utilitaires de la recette, Docker et Compose au moins 2.30. Conserver les sources dans le système de fichiers Linux. PHP, Composer, Node et npm sont fournis par le runtime du projet.

Suivre l'[installation](project-management/docs/interne/developpement/installation.md) : clonage, initialisation des paramètres privés, commandes Docker/Sail d’installation, puis démarrage explicite. Une nouvelle copie utilise ses propres paramètres, ports, secrets et volumes.

## Utiliser la copie locale

Depuis la racine d'une copie déjà préparée :

```sh
./vendor/bin/sail up -d --wait --wait-timeout 90 --no-build --pull never
./vendor/bin/sail ps
./vendor/bin/sail composer ci:check
./vendor/bin/sail stop -t 90
```

`APP_URL` dans le `.env` privé donne l'adresse du navigateur. L'arrêt conserve la clé et les données. Exécuter PHP, Artisan, Composer et npm via `./vendor/bin/sail`.

Le service Vite fournit HMR en développement ; ne pas lancer un second serveur Vite sur le même port. Les assets compilés sont utilisés par l'image de livraison. La recette d'installation décrit le cycle quotidien et les diagnostics.

## Contribuer

Lire les spécifications de la tâche, le [profil technique](project-management/docs/interne/profil-projet.md), les [contrats](project-management/docs/interne/architecture/contrats-et-lots.md) et les [règles applicables](.ai/rules/index.md). Travailler sur une branche avec une identité Git propre, vérifier le changement, puis proposer une PR selon la [procédure de contribution](project-management/docs/interne/exploitation/collaboration-livraison.md#contribution).

Les contrôles locaux et la CI sont obligatoires. Les contributions suivent la revue et les habilitations du dépôt ; une dispense de revue ne dispense d'aucun contrôle. La CI du commit intégré est distincte de celle de la proposition. Une fusion ne déclenche pas le déploiement de l'application.

Actualiser les références affectées avec le code. Un journal personnel est encouragé dans un sous-dossier privé ignoré par Git et exclu des images et archives, selon la [procédure documentaire](project-management/docs/interne/developpement/contribution-documentation.md). Il ne fait pas partie des documents partagés.

## Références

| Besoin                         | Référence                                                                                                                                                                                                                                      |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Fonctionnalités et critères    | [CDC fonctionnel](project-management/docs/cahiers-des-charges/fonctionnel/README.md)                                                                                                                                                           |
| Architecture et contrats       | [CDC technique](project-management/docs/cahiers-des-charges/technique/README.md) et [contrats internes](project-management/docs/interne/architecture/contrats-et-lots.md)                                                                      |
| Environnement et vérifications | [Installation](project-management/docs/interne/developpement/installation.md), [environnement](project-management/docs/interne/developpement/environnement.md), [qualité](project-management/docs/interne/developpement/qualite-et-recette.md) |
| Documentation et contribution  | [Index documentaire](project-management/docs/README.md), [guides de procédure](project-management/docs/procedures/README.md)                                                                                                                   |
| Règles et outils d'assistance  | [Index du corpus](.ai/rules/index.md), [raccordement](project-management/docs/interne/raccordement-agents.md), [AGENTS.md](AGENTS.md), [CLAUDE.md](CLAUDE.md)                                                                                  |

Les tests habituels utilisent l’installation du développeur et sa base `testing` déjà migrée : `./vendor/bin/sail artisan test --compact`. Les commandes Vue et navigateur, leurs prérequis et les ressources réservées sont dans la [recette qualité](project-management/docs/interne/developpement/qualite-et-recette.md#isolation). La CI GitHub reste obligatoire.
