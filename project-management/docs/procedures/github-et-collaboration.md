# Préparer GitHub, contribuer et recevoir la version commune

**Version 1.3 - contenu validé ; parcours opérationnel à exercer.** Commandes et capacités examinées dans les sources officielles citées ; vérifier les outils, l'offre et les droits réellement disponibles avant application.

**Résultat :** un dépôt identifiable, des accès et contrôles reçus, des contributions revues selon les responsabilités, puis une révision intégrée dont la CI est vérifiée. Ce parcours concerne les projets retenant GitHub et le circuit validation locale préalable → CI → intégration contrôlée. Il s'arrête à la version commune vérifiée ; image, registre et déploiement ont leurs procédures propres.

Le responsable du dépôt prépare accès et protections. Les contributeurs développent et soumettent leurs branches. Le responsable de l'intégration reçoit les propositions et décide selon les habilitations. Une personne peut cumuler les responsabilités, avec ou sans agent. Les propres changements d'un responsable peuvent suivre le parcours sans seconde revue lorsqu'il est retenu ; leurs contrôles restent obligatoires.

Retrouver choix et paramètres dans le profil du projet. [R20](../../../.ai/rules/socle/20-environnement-dependances-qualite.md) possède environnement, commandes et méthode Git ; [LIV-CI](../../../.ai/rules/projet/livraison/validation-ci-promotion-humaine.md) possède les garanties du circuit. Consulter les règles applicables selon leur statut, sans charger tout le corpus.

## 1. Recevoir l'organisation et les droits

| Information à recevoir                                             | Utilisation                                                                                                                                                       |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Propriétaire, dépôt personnel ou organisation, visibilité et offre | Créer ou retrouver le dépôt ; vérifier les protections effectivement disponibles. Ne pas rendre public un projet privé pour obtenir une fonctionnalité manquante. |
| Branche commune, méthode de fusion, convention de contribution     | Référence de départ, PR et intégration ; les exemples ne les adoptent pas.                                                                                        |
| Comptes, authentification et habilitations                         | Distinguer publication de branches, revue, fusion, administration et futures publications de livrables.                                                           |
| Runtime local/CI, services, profils et commandes requis            | Installer depuis les locks, isoler les données et recevoir les contrôles avant soumission.                                                                        |
| Identités des checks, déclencheurs, résultats et conservation      | Configurer protections et preuves ; retrouver la révision réellement vérifiée.                                                                                    |
| Première publication et historique autorisé                        | Préparer le premier partage, sans importer automatiquement un ancien historique personnel.                                                                        |

Un dépôt personnel a un propriétaire et des collaborateurs aux droits étendus, incluant la fusion ; une organisation offre d'autres rôles. CODEOWNERS ne fournit pas un contrôle de lecture ni un pouvoir exclusif de fusion. Si l'intégration doit être réservée à certains acteurs, recevoir une restriction compatible et en éprouver le refus. Les fonctions disponibles dépendent aussi de la visibilité et de l'offre. [Permissions personnelles](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/permission-levels-for-a-personal-account-repository), [rôles d'organisation](https://docs.github.com/en/organizations/managing-user-access-to-your-organizations-repositories/managing-repository-roles/repository-roles-for-an-organization), [rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets).

Les blocs `bash` s'exécutent dans un terminal Linux de la copie, **hors du runtime applicatif**, sauf les commandes Sail explicitement nommées. Remplacer les valeurs entre `<…>` ; ne pas poursuivre une étape dépendante après une erreur. Paramètres de travail non secrets :

```bash
GH_REPO='<proprietaire>/<depot>'
GH_MAIN='<branche-commune>'
GH_REMOTE='<nom-du-remote-officiel>'
GH_URL='<url-git-sans-identifiant-ni-jeton>'
```

Les exemples CLI visent GitHub.com et des branches dans le dépôt commun. Un profil Enterprise ou utilisant des forks reçoit ses hôtes/remotes et droits correspondants ; ne pas mélanger référence de publication et référence officielle. Un contributeur disposant du droit de publier sa branche n'a pas besoin de créer un autre dépôt personnel.

## 2. Préparer les identités et l'authentification

| Identité                          | Ce qu'elle démontre                                                                                                    |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `user.name`/`user.email` Git      | Attribution des commits ; ces valeurs ne connectent pas à GitHub.                                                      |
| Compte navigateur ou CLI `gh`     | Identité utilisant cette interface ; vérifier séparément chaque interface concernée.                                   |
| Credential ou clé utilisé par Git | Identité qui lit/publie réellement le code ; elle n'est pas prouvée par le nom d'auteur ni par la connexion CLI seule. |

Vérifier `git --version` et `gh --version` sur le poste. Installer le CLI par sa [procédure officielle](https://github.com/cli/cli#installation) s'il manque. Dans une copie Git existante, examiner la configuration locale avant adaptation ; dans une base sans Git, l'appliquer après `git init` à l'étape 3 :

```bash
git config --local user.name '<nom-auteur-recu>'
git config --local user.email '<adresse-auteur-recue>'
git config --local --get user.name
git config --local --get user.email
```

Utiliser l'adresse réelle ou l'adresse de confidentialité retenue pour ce compte, sans modifier celles des autres projets. Les accès GitHub restent ceux du compte, indépendamment de cette attribution. [Auteur Git](https://docs.github.com/en/get-started/git-basics/setting-your-username-in-git).

Les worktrees d'un même dépôt partagent normalement sa configuration locale. Pour des identités distinctes, utiliser des clones indépendants ou une configuration par worktree explicitement reçue ; ne pas supposer que `--local` isole deux worktrees. Un worktree de recette du responsable peut garder son identité commune. [Configuration des worktrees](https://git-scm.com/docs/git-worktree#_configuration_file).

### Exemple HTTPS avec contexte CLI distinct

Cette variante convient lorsque le profil sélectionne le CLI pour HTTPS, notamment avec plusieurs comptes sur un poste. Recevoir un dossier privé **hors du dépôt, des contextes de construction et des montages applicatifs**, et le binaire CLI Linux qualifié. Les chemins de cet exemple sont absolus et ordinaires, sans caractères de contrôle. Dans un terminal dédié :

```bash
AUTH_DIR='<dossier-prive-absolu-du-contexte-gh>'
AUTH_GH_BIN='<chemin-absolu-du-binaire-gh>'
AUTH_LOGIN='<compte-github-attendu>'
mkdir -p -- "$AUTH_DIR"
chmod 700 "$AUTH_DIR"
export GH_CONFIG_DIR="$AUTH_DIR"
unset GH_TOKEN GITHUB_TOKEN
"$AUTH_GH_BIN" auth login --hostname github.com --git-protocol https --web
"$AUTH_GH_BIN" auth status --active --hostname github.com
test "$("$AUTH_GH_BIN" api --hostname github.com user --jq .login)" = "$AUTH_LOGIN"
```

Exécuter ce bloc dans l'ordre et s'arrêter à la première erreur ; le contrôle final du compte doit réussir avant la suite. Dans le parcours navigateur, vérifier le compte attendu. Répondre **No** à la configuration Git automatique proposée : `gh auth setup-git` modifie la configuration globale. Recevoir le stockage des credentials ; un repli en fichier texte doit être protégé selon le profil. Ne pas afficher de token. `GH_TOKEN`/`GITHUB_TOKEN` ont priorité sur les credentials sauvegardés ; leur retrait ici concerne seulement ce terminal dédié. Pour Enterprise, adapter aussi leurs équivalents et l'hôte. [Connexion](https://cli.github.com/manual/gh_auth_login), [variables CLI](https://cli.github.com/manual/gh_help_environment).

Pour les commandes `gh` ultérieures, utiliser ce même contexte, en le rétablissant dans un nouveau terminal. Pour que **Git depuis l'IDE** le retrouve également, épingler le contexte et le binaire dans son helper local. Appliquer les commandes `git config --local` ci-dessous seulement après disponibilité du dépôt : après le clone ou, pour une base neuve, après `git init` à l'étape 3. `printf -v` échappe les chemins et prépare seulement la chaîne du helper natif ; aucun script de projet n'est créé :

```bash
printf -v AUTH_HELPER \
    '!env -u GH_TOKEN -u GITHUB_TOKEN GH_CONFIG_DIR=%q %q auth git-credential' \
    "$AUTH_DIR" "$AUTH_GH_BIN"
git config --local credential.https://github.com.username "$AUTH_LOGIN"
git config --local --replace-all credential.https://github.com.helper ''
git config --local --add credential.https://github.com.helper "$AUTH_HELPER"
```

Le helper vide neutralise la chaîne héritée pour cet hôte dans cette copie ; examiner une configuration préexistante avant de la remplacer. Vérifier aussi les éventuels en-têtes HTTP d'authentification ou réécritures d'URL hérités, sans publier leurs valeurs sensibles. Le contexte CLI seul n'isole pas ces mécanismes Git. [Helpers Git](https://git-scm.com/docs/gitcredentials) et [raccordement officiel du CLI](https://github.com/cli/cli/blob/trunk/pkg/cmd/auth/shared/gitcredentials/helper_config.go).

Pour un clone privé avant disponibilité de `.git/config`, appliquer ce raccordement temporairement à **la commande de clone** prévue par le [guide Docker](environnement-docker.md) :

```bash
GH_CLONE_FOLDER='<nouvelle-destination-du-clone>'
git -c credential.https://github.com.helper= \
    -c "credential.https://github.com.helper=$AUTH_HELPER" \
    -c "credential.https://github.com.username=$AUTH_LOGIN" \
    clone -- "$GH_URL" "$GH_CLONE_FOLDER"
```

Après succès, ouvrir cette copie et appliquer son auteur et son helper local. Pour une base sans Git, préparer le helper après l'initialisation à l'étape suivante. Un `git ls-remote` réussi sur dépôt public peut être anonyme : recevoir le compte/droit de publication lors du passage autorisé, avec les traces GitHub pertinentes. Le droit OAuth `workflow` peut être nécessaire pour modifier les workflows ; l'accorder seulement dans ce parcours reçu.

**Si SSH est retenu :** recevoir clé et configuration privées, vérifier l'identité avec `ssh -F <configuration-privee> -T <alias-recu>`, puis épingler cette configuration dans `core.sshCommand` **local**. Vérifier les empreintes du serveur selon la recette. `IdentitiesOnly=yes` n'annule pas tous les autres `IdentityFile` configurés ; la sélection doit être explicite. GitHub renvoie normalement 1 pour un test SSH réussi sans accès shell : lire aussi le compte annoncé. Les commandes `gh` gardent leur authentification distincte. [Test SSH GitHub](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/testing-your-ssh-connection), [configuration OpenSSH](https://man.openbsd.org/ssh_config).

## 3. Préparer la première publication

### Dépôt vide ou dépôt déjà existant

Sélectionner contenu collectif et historique selon [Documentation et partage](documentation-et-partage.md). Examiner les fichiers hérités de `.github/workflows/` avant le push : leurs versions, scripts, déclencheurs et permissions ne constituent pas automatiquement la CI prévue. Préparer la recette locale/CI avant leur activation.

Pour une base **sans dépôt Git**, après vérification qu'aucun dépôt parent ne la gouverne :

```bash
test ! -e .git && git init --initial-branch "$GH_MAIN"
```

Appliquer ensuite auteur et authentification reçus à l'étape 2. Pour un dépôt existant, examiner branche, remotes, historique et modifications ; ne pas réinitialiser Git ni réécrire cet historique pour simplifier le partage. Sélectionner les chemins autorisés, examiner l'index et exécuter les validations locales du contenu soumis avant le commit. Exemple pour un **ensemble explicitement sélectionné**, à adapter sans ajouter les fichiers privés :

```bash
git status --short --untracked-files=all
git add -- '<chemin-autorise-1>' '<chemin-autorise-2>'
git diff --cached --name-status
git diff --cached --check
```

Relire le contenu préparé dans l'éditeur. Après contrôles réussis et concordance du contenu :

```bash
git commit -m '<message-du-changement>'
git rev-parse HEAD
```

La première référence distante doit être publiée avant sa CI distante. Ce passage est **borné** : contenu propre et contrôles locaux → décision de première publication → CI réelle → protections actives et essais → ouverture aux contributions. Il ne devient pas une exception permanente permettant de pousser directement dans la branche commune.

Si le dépôt distant doit être créé, le responsable choisit dans GitHub propriétaire et visibilité reçus ; pour importer cette base, ne pas y générer un README ou des commits concurrents. Si le dépôt existe, examiner son contenu et ses accès avant raccordement. Dans un dépôt local où ce remote n'existe pas encore :

```bash
git remote add "$GH_REMOTE" "$GH_URL"
git remote get-url "$GH_REMOTE"
```

Si le remote existe, vérifier son URL sans le remplacer automatiquement. Après décision portant sur le dépôt et le contenu préparé :

```bash
git push --set-upstream "$GH_REMOTE" "$GH_MAIN"
```

Recevoir le SHA publié et l'identité effective du compte ayant publié. Les anciens workflows ne doivent pas lancer une migration non prévue ou une livraison implicite. [Première importation GitHub](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github).

## 4. Raccorder la CI et les protections

### Installer une validation indépendante du poste

La recette CI versionnée définit déclencheurs, runner, versions et services, profils d'essai, commandes, résultats requis et conservation. Elle reprend les garanties locales avec les différences de profil explicites ; ne pas copier les scripts d'un starter ni importer un nombre fixe de contrôles.

Pour chaque workflow utilisable, la notice indique son nom affiché, son déclenchement automatique ou son point de lancement manuel, les entrées à renseigner et où trouver leurs valeurs, puis le résultat attendu et les points d'arrêt. Les noms permettent de distinguer vérification d'une proposition, vérification après intégration et publication ; ils ne remplacent pas cette notice.

| Phase CI               | Préparation et réception                                                                                                                                                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Résoudre les sources   | Action de checkout identifiée par SHA qualifié ; relever le commit effectivement contrôlé. Pour une PR, le checkout par défaut vise la fusion synthétique ; sur `push` de la branche commune, recevoir le commit intégré.                                   |
| Préparer le runtime    | Amorçage déclaré avant `vendor/`, images/services et outils du runner compatibles, configuration et secrets d'essai, droits et stockages isolés. Réutiliser les [recettes d'installation](initialisation.md) et [Docker](environnement-docker.md) adaptées. |
| Installer et vérifier  | Locks, plateforme effective, hooks après leurs prérequis, migrations sous le profil dédié, générations et concordance ; aucun accès de production requis.                                                                                                   |
| Exécuter les contrôles | Commandes communes dans Sail : tests, analyses, format, frontend et autres contrôles requis. Garder les échecs natifs ; pas de `continue-on-error` pour neutraliser une obligation.                                                                         |
| Arrêter et recevoir    | Arrêt prévu, résultat des étapes et preuves utiles sans secrets ; échec/manque/saut/annulation d'une étape indispensable refusé.                                                                                                                            |

Les scripts communs existants peuvent exposer les contrôles ; leur runtime et leur portée viennent des manifestes et de la recette, pas d'un nom `check` inventé. Employer les commandes conventionnelles et ne pas ajouter un orchestrateur spécifique sans valeur justifiée. Délais et capacité de l'offre sont reçus ; les caches gardent des entrées et droits maîtrisés et ne remplacent pas l'exécution requise.

Les PR non fiables ne reçoivent pas les credentials de publication ou de production. Protéger les évolutions des workflows et de leurs sources ; identifier actions tierces et permissions. Ne pas exécuter leur code dans un contexte privilégié tel que `pull_request_target` avec des secrets. Un runner persistant ou son socket Docker peut partager un état : recevoir son isolation avant usage. [Sécurité Actions](https://docs.github.com/en/actions/reference/security/secure-use).

### Qualifier les opérations conditionnelles et les caches

Identifier les opérations et droits qui diffèrent entre une PR, une intégration et un lancement manuel. Exercer les opérations propres à chaque parcours dans un périmètre de qualification hors production, avec leurs conditions et permissions effectives. Une PR réussie ne prouve pas le fonctionnement d'une étape exécutée uniquement après intégration. Ne pas accorder à une PR l'écriture dans un cache partagé ou l'accès à des credentials réservés pour contourner cette différence.

Pour chaque cache retenu, vérifier sa première création, sa restauration et le repli après absence ou indisponibilité. Les clés, le rafraîchissement et les droits doivent empêcher la réutilisation d'un contenu incompatible ou non autorisé ; secrets, configurations privées, données et résultats de contrôle restent exclus. Un cache inutilisable entraîne la préparation normale, sans neutraliser un échec de construction, de test ou de contrôle. Ne pas considérer un résultat de contrôle conservé comme une nouvelle exécution.

Éprouver aussi les transitions susceptibles d’invalider les caches : changement de jour si la recette prévoit un rafraîchissement, et modification des fichiers de verrouillage. Vérifier que les parties compatibles restent réutilisées, que les mises à jour requises sont exécutées et que les dépendances sont installées conformément aux nouveaux verrous. Une restauration compatible peut provenir d’une autre clé ; vérifier son contenu sans la confondre avec une correspondance exacte.

Distinguer la preuve de restauration et de fonctionnement de la mesure du gain. Une différence de versions peut empêcher une comparaison chronométrique contrôlée sans interdire de vérifier la réutilisation effective et la réussite des contrôles. Déclarer séparément les scénarios réellement éprouvés et ceux restant à qualifier.

Comparer une exécution sans restauration de cache et une exécution avec cache à révision identique, avec paramètres, outils, fraîcheur des sources et conditions d'exécution comparables. Inclure restauration, validation des contenus, construction, enregistrement et transferts dans la durée totale, ainsi que le stockage consommé. Conserver les caches dont le gain net justifie le coût et la maintenance ; aucun cache d'outil ni rythme de rafraîchissement n'est imposé par principe. Conserver les mesures et leurs limites dans les outils et artefacts de validation prévus, ou dans le suivi privé ; la recette partagée décrit le fonctionnement retenu.

### Refuser aussi les contrôles sautés

GitHub peut admettre des conclusions `skipped` ou `neutral` pour un check requis. L'agrégation doit donc recevoir les **contrôles indispensables réellement exécutés**. Dans le job `validation`, donner des identifiants aux étapes, puis ajouter une réception finale. **Fragment à raccorder**, pas workflow complet : les étapes `bootstrap`, `checks`, `stop` doivent exister et couvrir les obligations du profil.

```yaml
- name: Recevoir les étapes indispensables
  if: ${{ always() }}
  shell: bash
  env:
      BOOTSTRAP_RESULT: ${{ steps.bootstrap.outcome }}
      CHECKS_RESULT: ${{ steps.checks.outcome }}
      STOP_RESULT: ${{ steps.stop.outcome }}
  run: |
      set -euo pipefail
      test "$BOOTSTRAP_RESULT" = success
      test "$CHECKS_RESULT" = success
      test "$STOP_RESULT" = success
```

Ajouter chaque étape indispensable à cette réception ; un job vert ne prouve pas qu'une étape conditionnelle a été exécutée. L'arrêt utilise sa commande native, une condition appropriée et un délai borné. Après annulation ou panne du runner, constater l'état des ressources/opérations avant une relance incompatible ; `always()` ne prouve pas leur arrêt.

Exemple complémentaire de déclencheurs et check final, à intégrer au workflow dont le job `validation` est déjà défini :

```yaml
on:
    pull_request:
        branches: ['<branche-commune>']
    push:
        branches: ['<branche-commune>']
permissions:
    contents: read
jobs:
    required:
        name: ci-required
        needs: [validation]
        if: ${{ always() }}
        runs-on: '<runner-linux-retenu>'
        steps:
            - name: Recevoir la validation
              shell: bash
              env:
                  VALIDATION_RESULT: ${{ needs.validation.result }}
              run: test "$VALIDATION_RESULT" = success
```

Remplacer les paramètres et compléter le job réel avant dépôt. L'agrégat exige `success` ; résultat absent, sauté, annulé ou échoué reste bloquant. Si plusieurs jobs portent des obligations, les ajouter à `needs` et contrôler chacun. Garder un nom de check stable et unique dans son contexte. Des filtres de chemins ou un déclencheur manuel seul ne doivent pas laisser une PR sans contrôle indispensable. [Conditions/jobs](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax#jobsjob_idneeds), [checks requis](https://docs.github.com/en/pull-requests/how-tos/merge-and-close-pull-requests/troubleshooting-required-status-checks), [annulation](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-cancellation).

### Activer les protections selon les rôles

Après une première CI reçue, le responsable retrouve **Settings → Rules → Rulesets** dans le dépôt et configure les ensembles applicables. Exemple lorsqu'une dispense de revue pour le responsable est retenue :

| Ensemble        | Règles et exceptions                                                                                                                                  |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| CI et intégrité | PR, checks requis et source attendue, branche à jour selon le profil, refus de force-push/suppression ; aucune exception liée à la dispense de revue. |
| Revue           | Approbations et CODEOWNERS selon les rôles ; seul l'acteur habilité reçoit la dispense prévue, limitée aux PR.                                        |

« PR uniquement » borne le mode de contournement, **pas les règles contournées dans le même ensemble**. Séparer les garanties à conserver. Si le profil exige des intégrateurs exclusifs, configurer aussi la restriction de mise à jour compatible ; une approbation CODEOWNERS seule ne réserve pas la fusion. Prévoir qui peut administrer ces réglages : les protections ordinaires ne retirent pas ce pouvoir administratif.

Dans CODEOWNERS, désigner les personnes/équipes reçues, notamment pour les fichiers du circuit ; ne pas imposer un acteur supérieur au chef. Configurer la revue des nouvelles modifications et l'actualisation de branche selon le profil. Recevoir le nom/source des checks, leur présence récente et leur résultat sur la bonne révision. Si l'offre ou les rôles ne permettent pas la séparation requise, présenter le manque avant de poursuivre cette phase. [Création des rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/creating-rulesets-for-a-repository), [règles disponibles](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/available-rules-for-rulesets), [CODEOWNERS](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners).

Avant ouverture aux contributeurs, éprouver sur des références de qualification identifiées : parcours permis, revue manquante pour un contributeur, check manquant/échoué/sauté/annulé, mauvaise source de statut et changements directs interdits par le profil. Vérifier aussi les propres contributions du responsable : dispense de revue reçue, CI/intégrité conservées. Qualifier les refus dans le mécanisme réel, en protégeant les références actives, sans plateforme de tests supplémentaire ni purge globale.

## 5. Contribuer sur une branche et faire revoir

Recevoir le mandat et ses dépendances. Un lot dépendant d'une contribution commune attend sa référence intégrée reçue. Coordonner contrats, migrations, locks et générés partagés ; les [ressources Docker](environnement-docker.md#3-raccorder-et-isoler-les-services) sont isolées indépendamment des branches Git.

Dans une copie dont les modifications existantes sont préservées, à partir de la référence de départ reçue :

```bash
git status --short --untracked-files=all
git fetch "$GH_REMOTE"
GH_START='<reference-de-depart-recue>'
GH_BRANCH='<branche-du-changement>'
git switch --create "$GH_BRANCH" "$GH_START"
git rev-parse HEAD
```

Si le changement de branche est refusé ou déplace des travaux, résoudre cette situation avant de poursuivre ; aucun reset/nettoyage automatique. Implémenter le périmètre et mettre à jour les références affectées. Dans Sail, exécuter les générations, contrôles et tests requis par la recette. Sélectionner, relire et commiter les changements avec le parcours de l'étape 3, en faisant correspondre contenu vérifié et contenu commité, puis publier :

```bash
git push --set-upstream "$GH_REMOTE" "$GH_BRANCH"
```

Ouvrir la PR vers la branche commune, par l'interface ou le CLI. Exemple dans le parcours de branches communes, avec une description préparée dans un fichier local :

```bash
GH_PR_BODY='<fichier-de-description>'
gh pr create --repo "$GH_REPO" --base "$GH_MAIN" --head "$GH_BRANCH" \
    --title '<probleme-et-resultat>' --body-file "$GH_PR_BODY"
```

Décrire résultat, périmètre, contrôles réellement exécutés et limites utiles au relecteur. Recevoir numéro, source et cible de la PR. La publication suit le mandat du contributeur ; la fusion suit la décision habilitée. Une PR proposée n'est pas une autorisation de livraison. [Créer une PR](https://cli.github.com/manual/gh_pr_create).

Pour des corrections ou une base devenue ancienne, revenir à la branche de contribution et actualiser ses preuves. Exemple **si la recette retient la mise à jour par fusion** :

```bash
git fetch "$GH_REMOTE"
git merge "$GH_REMOTE/$GH_MAIN"
```

En conflit, examiner les sources propriétaires et consommateurs, résoudre volontairement puis régénérer les sorties concernées. Reprendre les preuves invalidées avant commit et publication. Un rebase ou force-push d'une branche partagée n'est pas un repli automatique ; il suit sa recette et sa coordination propres. Toute nouvelle révision peut invalider revue ou CI précédentes.

## 6. Intégrer et recevoir la version commune

### Examiner la proposition actuelle

Depuis le contexte du responsable, pour la PR reçue :

```bash
GH_PR='<numero-de-la-pr>'
gh pr view "$GH_PR" --repo "$GH_REPO" \
    --json number,url,headRefName,headRefOid,baseRefName,baseRefOid,reviewDecision
gh pr diff "$GH_PR" --repo "$GH_REPO"
gh pr checks "$GH_PR" --repo "$GH_REPO"
```

`gh pr checks` renvoie notamment 8 si des contrôles sont en attente : attendre leur résultat, sans confondre ce diagnostic avec un échec ni avec une CI reçue. [Diagnostic CLI](https://cli.github.com/manual/gh_pr_checks).

Relire changements, consommateurs, workflow/configuration, preuves et conditions d'accès ; vérifier l'identité des auteurs et relecteurs selon les rôles. Le responsable peut récupérer la proposition dans une copie propre ou un worktree pour sa recette, avec ressources locales isolées. Cette vérification ne modifie pas la production et ne remplace pas celle du résultat intégré.

La décision identifie PR et références actuelles source/cible. Si elles changent de manière pertinente, revoir les garanties et la décision avant fusion. Pour une contribution nécessitant sa revue, le responsable peut ensuite enregistrer l'avis :

```bash
gh pr review "$GH_PR" --repo "$GH_REPO" --approve
```

Pour **son propre changement**, le responsable suit la branche/PR et les contrôles prévus ; il ne tente pas de s'autoapprouver ni de créer un relecteur supérieur. Un avis indépendant reste possible. La dispense retenue s'utilise seulement par son mécanisme qualifié, sans enlever les gardes CI/intégrité.

Après décision et recontrôle, exemple de fusion **si squash est la méthode retenue** :

```bash
GH_APPROVED_HEAD='<sha-source-recontrole-et-autorise>'
gh pr merge "$GH_PR" --repo "$GH_REPO" --squash \
    --match-head-commit "$GH_APPROVED_HEAD"
```

`--match-head-commit` protège la source attendue, pas une base figée. Recevoir les exigences de branche à jour et la coordination des intégrations ; ne pas fusionner une proposition devenue ancienne sur la seule foi de son ancien vert. Si l'interface exige une dispense de revue, vérifier l'acteur et l'ensemble concernés et utiliser uniquement le chemin reçu. Ne pas ajouter `--admin` pour débloquer un contrôle requis ; un refus se diagnostique. Une file de fusion éventuelle garde son profil et ses événements de validation propres. [Fusion CLI](https://cli.github.com/manual/gh_pr_merge).

### Recevoir le commit réellement intégré

Après fusion effective, relever son identité et retrouver **sa** CI :

```bash
gh pr view "$GH_PR" --repo "$GH_REPO" --json state,mergedAt,mergeCommit
GH_INTEGRATED_SHA='<sha-reellement-integre-recu>'
gh run list --repo "$GH_REPO" --event push --branch "$GH_MAIN" \
    --commit "$GH_INTEGRATED_SHA" \
    --json databaseId,attempt,headSha,event,status,conclusion,url
```

Sélectionner la bonne exécution, tentative et workflows requis, puis examiner leurs étapes et résultats :

```bash
GH_RUN='<identifiant-execution-recue>'
GH_RUN_ATTEMPT='<numero-tentative-recue>'
gh run view "$GH_RUN" --repo "$GH_REPO" --attempt "$GH_RUN_ATTEMPT"
```

Le résultat `pull_request` porte habituellement sur une fusion synthétique ; il ne remplace pas le résultat `push` du commit intégré. Une exécution ancienne, sans résultat final ou portant sur un autre SHA ne reçoit pas ce nouveau contenu. Retrouver références, liens, résultats et limites dans les PR, exécutions et artefacts concernés ; compléter le suivi privé si nécessaire, sans recopier leurs réceptions successives dans la documentation du projet. [Événements Actions](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#pull_request), [exécutions](https://cli.github.com/manual/gh_run_list).

GitHub ne met pas automatiquement à jour les clones. Dans une copie propre réservée à la branche commune, sans commits locaux divergents :

```bash
git fetch "$GH_REMOTE"
git switch "$GH_MAIN"
git merge --ff-only "$GH_REMOTE/$GH_MAIN"
git rev-parse HEAD
```

Rapprocher le SHA obtenu de celui examiné. Si la branche commune a déjà avancé et qu'il faut recevoir une révision précise, utiliser une copie/worktree dédiée à cette référence ; ne pas attribuer l'observation à un ancien SHA. Appliquer ensuite la [mise à jour Docker locale](environnement-docker.md#5-appliquer-le-bon-changement-en-conservant-les-données) : dépendances, schéma sous identité dédiée, assets et processus concernés. Un `fetch`, une fusion ou une CI verte ne change pas la version active sur le serveur.

### Diagnostics et situations décisives

| Situation                                     | Vérification et reprise                                                                                                                                                                   |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Premier dépôt vide                            | Contrôles locaux et contenu reçus, publication décidée, puis CI distante/protections qualifiées. Aucun préalable exige une CI distante avant le premier push.                             |
| Contribution refusée puis corrigée            | Lire les étapes et règles qui refusent, corriger sur la branche, reprendre les contrôles locaux affectés, publier puis revoir la nouvelle révision.                                       |
| Propre changement du responsable              | PR et contrôles requis conservés ; dispense de seconde revue reçue, sans compte supplémentaire ni autoapprobation.                                                                        |
| Deux branches séparément valides              | Après intégration de la première, recevoir la base actuelle et l'assemblage de la seconde ; traiter conflits Git et incohérences fonctionnelles, puis vérifier le SHA finalement intégré. |
| CI manquante, sautée, annulée ou échouée      | Pas d'intégration admise par un ancien vert. Retrouver run/tentative/SHA et résultat natif ; constater les ressources après interruption avant reprise incompatible.                      |
| Mauvais compte ou droit absent                | Comparer auteur, CLI/navigateur et credential Git ; recevoir les accès ou le scope manquant sans modifier les autres comptes ni afficher de token.                                        |
| Branche ancienne ou tête modifiée après revue | Actualiser les références, preuves et décision concernées ; ne pas forcer la fusion pour fermer la PR.                                                                                    |
| Refus d'arrêt pendant annulation              | Conserver l'incident et examiner ce qui fonctionne encore ; un arrêt réussi lors d'une autre exécution ne résout pas cet incident.                                                        |

Transmettre une recette actuelle avec commandes, accès à recevoir, protections, références communes et preuves exploitables. Reprendre les vérifications invalidées lorsqu'un runtime, workflow ou droit change, sans répéter les preuves encore valables. Les succès/refus d'un compte ne qualifient pas tous les rôles de l'équipe.

Ce guide ne construit ni ne publie une image et n'intervient pas sur une cible. La version intégrée reçue devient une entrée pour le guide image/registre ; la décision et l'exécution du déploiement restent séparées. Le contenu relu et le parcours réellement exercé sont deux états distincts.
