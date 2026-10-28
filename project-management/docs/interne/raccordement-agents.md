# Raccordement du corpus aux agents

Le raccordement utilise Boost depuis le lock. La source commune doit apparaître une seule fois dans `AGENTS.md` généré ; `CLAUDE.md` conserve son import. Les configurations MCP partagées appellent `./vendor/bin/sail artisan boost:mcp` dans le runtime et le service Sail de la copie concernée. La gestion concurrente des règles reste désactivée. Vérifier l'activation par chaque client, un appel MCP utile tel que `application-info` et la découverte des instructions dans sa session, selon [l'installation](developpement/installation.md).

Cette procédure possède l'installation et les contrôles du raccordement. Elle complète les [spécifications](../README.md), la [recette d'environnement](developpement/environnement.md#agents) et l'[installation](developpement/installation.md). Le CLI peut lire les instructions et préparer un plan avant que l'application fonctionne ; Boost/MCP exige ensuite le runtime Sail disponible. Ne pas créer de configuration dépendant d'un chemin personnel de préparation.

## 1. Sources et points d'entrée

- Source du court raccordement : [00-project-socle.md](../../../.ai/guidelines/00-project-socle.md). Corpus unique : [index](../../../.ai/rules/index.md), `socle/` et `projet/`.
- Codex : [AGENTS.md](../../../AGENTS.md) racine est son point d'entrée. Le dossier `.codex/` accueille les réglages utiles ; il n'est pas un import automatique de notre corpus Markdown.
- Claude Code : [CLAUDE.md](../../../CLAUDE.md) contient `@AGENTS.md`, import officiel du même point d'entrée. `.claude/` accueille ses réglages ; aucun corpus concurrent ni symlink n'est créé.

Ne pas modifier uniquement une sortie générée : après évolution approuvée de la source commune, utiliser Boost et examiner le diff en préservant le contenu étranger. Chaque point d'entrée doit atteindre une seule copie du fragment commun ; la présence de fichiers ne prouve ni leur consultation ni la connexion MCP.

Le point d'entrée contient la consigne de lire index, R01 et profil, puis les règles adaptées à la tâche. Il n'importe pas tous les textes. Pour Claude, l'import charge réellement AGENTS.md ; un chemin mentionné dans ce dernier demande ensuite une consultation par l'agent. Cette consultation et l'application des règles doivent être observées, pas présumées.

## 2. Avant l'installation des outils

Depuis la racine Linux concernée, inventorier les versions de CLI, entrées existantes/imbriquées, instructions globales applicables, configurations MCP et réglages de découverte. Vérifier le dossier utilisé et la racine Git. Une entrée prioritaire, un mode excluant les instructions projet ou une limite de taille peut modifier ce qui est chargé. Résoudre un conflit selon R01 ; ne pas effacer les préférences globales du poste pour forcer une conformité.

Après import des entrées, ouvrir une session neuve à la racine du projet. Vérifier les instructions actives, consulter index/R01/profil puis confronter le [profil](profil-projet.md) et la recette aux fichiers. Cette vérification initiale ne dépend pas de Boost et n'autorise pas les modifications applicatives ; leur plan suit R01.

Les fichiers présents ne fournissent ni credentials, ni serveur MCP, ni runtime PHP. Installer/raccorder ces éléments seulement dans leur étape approuvée, selon la recette déclarée. Les commandes PHP/Composer/npm/contrôles et le MCP Laravel doivent utiliser le code et le runtime Linux/Sail prévus ; l'éditeur et Git peuvent rester hors du conteneur.

## 3. Intégrer Boost sans gestion concurrente

Lorsque Boost est installé dans l'étape du starter, examiner ses versions, sélection d'agents, `boost.json`, sorties et scripts Composer. Choisir Codex et Claude Code parmi les intégrations réellement proposées. Ne pas répéter une installation déjà réalisée et ne pas supposer les noms de sorties à partir des seuls noms des agents.

Avant que Boost gère le corpus, désactiver son gestionnaire de règles par le mécanisme de la version retenue : la documentation examinée prévoit `BOOST_RULES_ENABLED=false`. Appliquer le réglage à l'environnement d'installation, de mise à jour et du MCP, vérifier la configuration effective et relancer les consommateurs concernés. Un ajout à `.env.example` seul n'actualise pas les configurations locales existantes. Conserver guidelines et MCP actifs ; vérifier l'absence de gestion concurrente de `.ai/rules/index.md` et de `record-rule`.

Inclure la source commune via les guidelines personnalisées de Boost. Si Boost manque, son installation documentée, une fois Sail disponible et sa plateforme vérifiée, est `./vendor/bin/sail composer require laravel/boost --dev`, puis `./vendor/bin/sail artisan boost:install`. Pour actualiser ses sorties : `./vendor/bin/sail artisan boost:update`. Exécuter ces commandes seulement dans la portée autorisée. Vérifier leur disponibilité sur la version installée. L'absence de Boost ne bloque pas la découverte préalable du corpus ni une première page qui n'en dépend pas.

Examiner le diff de génération. Préserver tout contenu étranger au bloc possédé par Boost. Si un fragment indépendant duplique celui inclus par Boost, retirer seulement la copie redondante après comparaison. Ne pas entretenir manuellement un fragment à l'intérieur du bloc régénéré. Si Boost utilise une entrée partagée, conserver l'import Claude utile ; s'il produit une entrée Claude complète, résoudre le parcours pour éviter les doublons, sans remplacement aveugle.

## 4. Réception des clients et actualisations

Vérifier la connexion et un appel utile depuis chaque nouvelle session cliente ; réutiliser les preuves encore pertinentes et reprendre les raccordements affectés après actualisation.

Si le client a démarré avant la disponibilité de Sail ou conserve un échec de connexion, vérifier d'abord les services, la commande MCP, la configuration cliente et la racine utilisée. Rétablir la connexion ou rouvrir la session cliente selon ses capacités, puis réussir un appel MCP natif depuis cette session. Un essai en ligne de commande ne prouve pas que la connexion cliente est rétablie. Ne pas réinstaller Boost sans avoir identifié un défaut d'installation.

| Contrôle                                                         | Preuve attendue                                                                                                            |
| ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Codex depuis la racine et un sous-dossier                        | Entrées effectivement chargées identifiées ; accès à l'index, à R01 et au profil ; aucun override involontaire.            |
| Claude dans les mêmes conditions                                 | CLAUDE.md/import visibles dans `/context` ou `/memory` selon version ; accès au même corpus ; réglages projet compatibles. |
| Petite tâche transverse sans nommer les règles à lire            | Traces des consultations pertinentes et plan cohérent ; un simple résumé déclaratif ne prouve pas l'application.           |
| Première génération Boost puis seconde sans changement           | Fragment unique, sources canoniques intactes, portions existantes préservées.                                              |
| `boost:update` et mise à jour des dépendances sur branche dédiée | Raccordement conservé, index/corpus non régénérés par le gestionnaire natif, diff relu.                                    |
| MCP et runtime                                                   | Appel utile depuis le bon clone et les bons services ; aucun chemin personnel requis par la configuration partagée.        |

Conserver versions, état vérifié, commandes/observations et limites dans les outils ou artefacts prévus, avec leurs références dans le suivi privé. Ne pas ajouter ces résultats à la documentation partagée. L'[index du corpus](../../../.ai/rules/index.md) porte les statuts et les conditions d'application, distincts de ces preuves. La présence d'une entrée ne décide pas l'installation des CLI, connexions, scripts ou permissions ; vérifier les garanties automatisables dans la chaîne de qualité appropriée.

**Documentation officielle :** [Codex AGENTS.md](https://learn.chatgpt.com/docs/agent-configuration/agents-md), [configuration Codex](https://learn.chatgpt.com/docs/config-file/config-basic), [imports Claude](https://code.claude.com/docs/en/memory#import-additional-files), [partage d'AGENTS.md](https://code.claude.com/docs/en/memory#share-one-file-with-other-coding-tools), [guidelines Boost](https://laravel.com/framework/docs/13.x/boost#adding-custom-ai-guidelines) et [désactivation de sa gestion de règles](https://laravel.com/framework/docs/13.x/boost#disabling-project-rules). Vérifier les versions réellement retenues avant application ; les fichiers d'instructions guident les agents, sans garantir à eux seuls le respect de chaque prescription.

## 5. Réglages des agents et organisation documentaire

Les CDC, spécifications et références actuelles se retrouvent par `project-management/README.md`. Le corpus conventionnel reste `.ai/rules/`, relié par les entrées racine ; cet emplacement n'est pas un chargement natif automatique.

Créer `.codex/` ou `.claude/` seulement lorsqu'un réglage projet réel le nécessite. Les configurations partagées présentes, `.codex/config.toml` et `.mcp.json`, sont versionnées ; les préserver lors des actualisations. Un fichier `.claude/settings.json` est ajouté seulement si un réglage projet le nécessite ; son absence ne remplace ni ne supprime le point d'entrée `CLAUDE.md`. Ne pas y copier configurations globales, identifiants ou chemins personnels. Les fichiers `.rules` de Codex autorisent des commandes ; ils n'ont pas la fonction de notre corpus d'implémentation. Ne pas créer de copie concurrente dans `.claude/rules/`.

## 6. Skills d'audit et de correction

Les entrées partagées [audit](../../../.agents/skills/audit/SKILL.md) et [audit-fix](../../../.agents/skills/audit-fix/SKILL.md) renvoient à une seule [procédure commune](../procedures/audit-and-remediation.md). Les rapports et suivis restent dans `project-management/local/audits/`, exclus de Git, des images et des archives partagées. Seuls les deux fichiers `SKILL.md` sont admis au versionnement dans `.agents/` ; les autres fichiers de ce dossier restent privés.

Codex découvre les entrées locales dans `.agents/skills/`. Les invocations sont `$audit <périmètre>` et `$audit-fix <dossier-audit>`. Si la session ne les présente pas, vérifier racine, fichiers et réglages de découverte, puis rouvrir le client si nécessaire. Recevoir leur découverte depuis la session réelle ; l'installation des fichiers ne prouve pas l'exécution de la méthode. Les autres outils peuvent consulter la même procédure ; vérifier leur mécanisme natif avant de promettre une commande identique. [Documentation officielle des skills Codex](https://learn.chatgpt.com/docs/build-skills).
