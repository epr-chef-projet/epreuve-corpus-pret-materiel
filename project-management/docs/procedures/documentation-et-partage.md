# Organiser, partager et entretenir la documentation

**Version 1.1 — contenu validé ; parcours de remise à exercer.** Commandes examinées dans les sources officielles citées ; vérifier leur compatibilité avec les outils du projet.

**Résultat :** un dépôt dont le README oriente vers des références actuelles, une documentation collective suffisante pour travailler et des procédures réservées transmises seulement aux personnes concernées. Le parcours s'applique à l'initialisation, aux contributions et à la remise d'un projet existant, avec ou sans agent.

La personne chargée de l'initialisation prépare l'accueil et les références. Les contributeurs entretiennent les informations affectées par leur travail. Le responsable de l'intégration reçoit l'ensemble ; les responsables habilités décident des accès et de la diffusion. Une responsabilité peut avoir plusieurs titulaires, et une personne peut cumuler les rôles.

Les [règles](../../../.ai/rules/index.md), notamment [R01](../../../.ai/rules/socle/01-noyau-agent.md) et [R20, section 9](../../../.ai/rules/socle/20-environnement-dependances-qualite.md#9-préparer-et-maintenir-la-documentation-collective), possèdent les garanties. Le présent guide donne le parcours portable ; le profil et les procédures internes d'un projet possèdent son organisation, ses destinataires et ses paramètres. Il ne remplace ni le CDC ni les recettes d'installation, de collaboration ou de déploiement.

## 1. Identifier les destinataires et les sources

Avant de ranger ou partager, recevoir : les personnes concernées, les documents nécessaires à leur tâche, l'organisation retenue, les emplacements privés et le périmètre de diffusion. Une information indispensable au développement doit être accessible aux contributeurs autorisés ; leur installation ne dépend pas du journal ou des secrets d'un autre poste.

| Information                                                                                       | Source propriétaire et usage                                                                                                         |
| ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Fonctionnalités, parcours et acceptation                                                          | CDC fonctionnel : ce que le produit doit permettre et comment recevoir le résultat.                                                  |
| Architecture et contraintes retenues                                                              | CDC technique : conception adoptée et décisions encore ouvertes.                                                                     |
| Versions, paramètres, contrats et recettes du projet                                              | Documentation interne et configurations versionnées : valeurs applicables et commandes adaptées. Le profil oriente vers ces sources. |
| Garanties et méthodes d'implémentation                                                            | Corpus de règles : textes applicables selon leur sujet, choix et statut.                                                             |
| Marches à suivre réutilisables                                                                    | Guides : étapes pour appliquer les outils/processus sélectionnés, avec paramètres reçus du projet.                                   |
| Résultats d'exécution                                                                             | Tests, intégration continue, artefacts autorisés ; supports privés si nécessaire, sans compte rendu Markdown partagé.                |
| Plans, cartographies, conversations, instructions et mandats privés, accords de travail, reprises | Espace privé ignoré, exclu du partage même sans secret technique.                                                                    |

La documentation partagée décrit fonctionnalités, architecture, contrats, recettes, contrôles, contribution, règles et limites actuels. Les choix applicables et justifications techniques utiles y figurent sans attribution, chronologie ni citation d'échanges. Une procédure ne prouve pas son exécution. Versions et dates nécessaires à l'usage restent indiquées.

## 2. Organiser les références et les points d'entrée

Organiser par thème explicite. Exemple à adapter :

```text
README.md
docs/
  README.md
  specifications/
  procedures/
rules/
  index.md
```

Définir les chemins selon le projet. Les index orientent vers les références actuelles ; aucune arborescence de plans, suivis, décisions chronologiques ou validations d'intervention n'est ajoutée.

Employer des liens relatifs et ancres stables, sans dépendance à un chemin ou document privé.

Transférer seulement les références actuelles nécessaires, sans plans, formation personnelle, essais ou journaux. Un enseignement devient une instruction générale, sans récit d'origine. Les [guides d'initialisation](initialisation.md) et [d'environnement Docker](environnement-docker.md) conservent leurs recettes.

## 3. Préparer le README racine

Rédiger l'accueil pour une personne qui vient de récupérer le dépôt. Elle doit comprendre le produit, son état et comment commencer sans rechercher dans les anciennes discussions. Trame à adapter, sans la transformer en second CDC :

```text
# Nom du produit
Usage principal et personnes concernées.

## État actuel
Ce qui fonctionne, ce qui reste prévu et les limites connues.

## Démarrer
Prérequis du poste ; lien vers l'installation et son résultat attendu.

## Travailler et vérifier
Points d'entrée des commandes usuelles, contrôles et procédure de contribution.

## Retrouver les références
Liens vers spécifications, profil, procédures et règles applicables.
```

Renseigner des faits actuels : une base technique installée n'est pas une fonctionnalité métier terminée. Pour les commandes usuelles, fournir le point d'entrée vers leur recette ; une commande courte peut figurer dans l'accueil si son maintien reste coordonné avec cette source. Les options, prérequis et variantes détaillés restent dans la recette propriétaire.

La recette d'installation doit être disponible dans le contenu collectif, avec versions/références nécessaires, configuration d'exemple sans secrets, contexte des commandes, paramètres à créer localement et réception attendue. Elle distingue installation, démarrage, arrêt/reprise et mise à jour. Si elle manque, l'initialisateur la prépare avant la remise ; le README ne compense pas ce manque par des promesses.

Vérifier le parcours de lecture : accueil → installation/profil → spécification de la tâche → règles pertinentes → commandes de validation → contribution. Un intervenant sélectionne ses références ; aucun point d'entrée n'exige de charger tout le corpus. Les réglages GitHub et le circuit de fusion gardent leur procédure propre.

## 4. Sélectionner ce qui sera partagé

### Répartir selon les besoins et les droits

| Contenu                                                                                                   | Destination                                                                      |
| --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| Références actuelles nécessaires au travail                                                               | Documentation partagée, accessible depuis le README.                             |
| Plans, cartographies, conversations, instructions et mandats privés, accords de travail, reprises, essais | Espace privé ignoré, exclu des images et archives partagées.                     |
| Résultats et preuves d'exécution                                                                          | Outils et artefacts de validation autorisés ; conservation privée si nécessaire. |
| Procédures réservées et secrets                                                                           | Stockage protégé ; transmission limitée aux personnes habilitées.                |

Un dossier ignoré n'est pas un contrôle d'accès aux fichiers du poste. Pour les procédures privées, recevoir emplacement, droits, sauvegarde privée et moyen de transmission autorisé. Le clone collectif reste utilisable sans ces fichiers ; une personne appelée à intervenir sur le déploiement reçoit séparément les accès et instructions nécessaires.

### Examiner les fichiers, les exclusions et l'historique Git

Les exemples suivants sont des **consultations**, dans un terminal Bash à la racine du dépôt concerné, hors du conteneur applicatif. Les paramètres entre `<…>` sont à remplacer. Vérifier d'abord la copie :

```bash
pwd -P
git rev-parse --show-toplevel
git status --short --untracked-files=all
git ls-files
git diff --cached --name-status
```

Attendus : racine prévue, fichiers suivis connus et changements préparés examinés. La liste des fichiers suivis et celle des changements préparés sont distinctes ; une vue Git propre ne prouve pas la confidentialité de l'ancien historique.

Si le projet retient, **par exemple**, `project-management/local/` pour le contenu local privé, une règle commune dans le `.gitignore` racine peut être :

```gitignore
/project-management/local/
```

Adapter ce chemin au profil, préserver les exclusions existantes et les fichiers collectifs attendus. Les exclusions personnelles ou globales d'un poste ne deviennent pas automatiquement celles d'un clone. Pour contrôler un **fichier privé précis**, pas seulement son dossier :

```bash
DOC_PRIVATE_PATH='<chemin-relatif-du-fichier-prive>'
git ls-files -- "$DOC_PRIVATE_PATH"
git check-ignore -v --no-index -- "$DOC_PRIVATE_PATH"
```

La première sortie doit être vide si le fichier ne doit pas être suivi. La deuxième explique le motif et sa source, même pour un fichier déjà suivi. Un motif commençant par `!` est une réinclusion : la présence d'une sortie verbose n'est pas une preuve d'exclusion. `--no-index` ne retire rien de l'index Git.

Pour recevoir l'état d'exclusion seul, sans confondre diagnostic verbose et résultat :

```bash
if git check-ignore --no-index --quiet -- "$DOC_PRIVATE_PATH"; then
    DOC_IGNORE_STATUS=0
else
    DOC_IGNORE_STATUS=$?
fi
printf 'Code de retour : %s\n' "$DOC_IGNORE_STATUS"
```

Le bloc affiche le code reçu sans confondre un résultat non ignoré et une erreur : **0** signifie ignoré, **1** non ignoré, **128** une erreur à corriger avant de poursuivre. Le résultat attendu pour ce fichier privé est 0, **avec absence de fichier suivi vérifiée séparément**. Ne pas transformer cette consultation en un contrôle automatique qui masque une erreur ou interprète tout retour non nul comme « aucun problème ». Examiner aussi les exceptions de réinclusion et les règles effectivement distribuées. [Exclusions Git](https://git-scm.com/docs/gitignore), [fichiers suivis](https://git-scm.com/docs/git-ls-files) et [check-ignore](https://git-scm.com/docs/git-check-ignore).

Si le fichier a déjà été suivi, sa règle d'exclusion ne le retire ni de l'index ni des anciens commits. Préserver sa copie privée, reporter les informations collectives utiles, puis préparer son retrait courant dans la contribution autorisée. Vérifier le changement avant intégration ; ne pas supprimer les fichiers de travail d'un autre intervenant.

Pour un aperçu des commits locaux touchant ce chemin :

```bash
git log --all --oneline -- "$DOC_PRIVATE_PATH"
git rev-parse --is-shallow-repository
```

L'aperçu concerne les références et l'historique disponibles localement ; un clone superficiel, un autre nom de fichier ou une référence non récupérée limite la preuve. Recevoir le **périmètre d'historique destiné à diffusion** avant publication. Si des éléments réservés y figurent, remettre le constat au responsable : une réécriture ou une autre stratégie de publication exige son propre accord. Si un secret a été diffusé, prévenir le responsable habilité et le faire révoquer/remplacer ; retirer le fichier ne rend pas ce secret sûr. Ne pas recopier sa valeur dans le rapport. [Historique Git](https://git-scm.com/docs/git-log) et [traitement d'informations sensibles](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository).

### Vérifier aussi les canaux hors Git

Pour Docker, identifier **chaque contexte de construction** et le fichier d'exclusion réellement utilisé. Un `.dockerignore` se lit depuis la racine de son contexte ; lorsqu'un fichier d'exclusion spécifique au Dockerfile existe, il prend priorité sur celui du contexte. Pour un contexte situé à la racine du dépôt, le même exemple privé peut être exclu ainsi :

```dockerignore
project-management/local/
```

Adapter les exclusions au contenu nécessaire à la construction ; ne pas exclure indistinctement toutes les sources de génération. Examiner Dockerfile, `COPY` et contextes supplémentaires afin que documents privés et secrets ne soient pas ajoutés à une couche intermédiaire ou finale. `.gitignore` n'est pas une exclusion Docker. [Contextes et fichiers d'exclusion](https://docs.docker.com/build/concepts/context/#dockerignore-files).

Les exclusions de construction ne protègent pas les montages du runtime ni tous les fichiers joints à un artefact, cache, journal CI ou archive. Examiner aussi les chemins effectivement collectés et transmis. Cette vérification documentaire prépare la sélection ; la qualification d'une image et le déploiement gardent leurs procédures propres. Les sauvegardes privées suivent les accès et protections reçus ; elles ne sont pas versées dans une archive collective pour simplifier la remise.

## 5. Entretenir les références avec les contributions

Avant une modification, identifier les consommateurs documentaires : référence propriétaire, exemples, liens entrants et points d'entrée. Après le changement, les actualiser dans la même contribution que le code ou la configuration concernés. Relire le résultat obtenu, pas uniquement les phrases ajoutées.

| Changement                               | Mise à jour nécessaire                                                                                                                                                      |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Commande ou prérequis d'installation     | Recette propriétaire, exemple/configuration affectés et renvois ; reprendre les étapes dont la preuve est invalidée.                                                        |
| Fonctionnalité ou contrat                | Spécification concernée, comportement documenté et critères de réception ; distinguer réalisation et proposition.                                                           |
| Version, service ou configuration        | Source de la valeur, recette et diagnostics affectés ; ne pas maintenir une autre valeur dans le README.                                                                    |
| Déplacement ou renommage d'un document   | Liens entrants/sortants, index, ancres et copies transmises concernées. Un changement de chemin seul ne rend pas le contenu privé.                                          |
| Enseignement ou limite affectant l'usage | Actualiser la référence propriétaire avec une instruction générale ou la limite actuelle ; conserver les notes et résultats détaillés dans leurs supports privés ou outils. |

Tenir un journal de bord privé est encouragé pour suivre l'avancement, les essais, les décisions de travail et les points à reprendre. Le conserver dans un sous-dossier privé de `project-management/` ou de l'espace de gestion retenu, explicitement ignoré par Git et exclu des images et archives partagées. Il reste un outil personnel, sans obligation de journal pour chaque petite tâche.

Le suivi nécessaire à la continuité reste privé et ignoré. Aucune synthèse de ses actions, aucun journal de commandes ni historique personnalisé n'est ajouté à la documentation partagée.

Lors d'une divergence entre deux copies, retrouver la source propriétaire, comparer statut/date/portée et corriger le renvoi ou le transfert concerné. Ne pas choisir automatiquement le fichier le plus récent. Une décision manquante ou contradictoire demande clarification avant l'étape dépendante.

## 6. Recevoir la remise documentaire

Le responsable de l'intégration vérifie quatre aspects :

- **Navigation et actualité :** les liens et sections existent, l'état décrit correspond au résultat livré, les informations importantes ont une source maintenue.
- **Destinataires :** fichiers suivis, changements préparés et historique destiné à diffusion correspondent aux accès reçus ; les autres canaux de partage sont examinés.
- **Autonomie :** un nouveau contributeur retrouve les références et peut suivre l'installation/validation sans chemin, clé, fichier privé ou contexte personnel d'un autre poste.
- **Preuves :** les vérifications nécessaires sont exécutées et retrouvables dans les outils ou artefacts prévus, avec portée et limites ; aucune duplication en récit documentaire.

Une correction de titre demande relecture et vérification des renvois affectés. Un changement de bootstrap demande la reprise du parcours d'installation invalidé. Utiliser les contrôles déjà déclarés, y compris le format des documents collectifs si retenu ; ne pas ajouter une suite spécialisée ni conditionner les validations du code à l'immuabilité des anciens comptes rendus.

Conserver les résultats de remise dans les supports autorisés. Reporter seulement les limites actuelles nécessaires dans leurs références. Une information indispensable inaccessible laisse la remise à compléter.

### Quatre situations concrètes

| Situation                                  | Déroulement et résultat attendu                                                                                                                                                                                                                |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Un développeur récupère le seul dépôt      | Il lit l'accueil, ouvre installation/profil, prépare sa copie, puis retrouve la spécification et les règles de sa tâche. Aucun journal personnel du responsable n'est nécessaire.                                                              |
| La commande de démarrage change            | Le contributeur modifie la recette, les renvois affectés et les exemples utiles dans la même contribution ; l'intégrateur reçoit le nouveau parcours. Le README reste cohérent.                                                                |
| Un enseignement devient réutilisable       | Formuler la méthode générale dans la recette propriétaire ; conserver séparément les notes, essais et résultats, sans importer leur chronologie.                                                                                               |
| La procédure de déploiement reste réservée | Le responsable conserve sa notice dans le support privé choisi, exclu de Git et des images, avec sauvegarde privée. Les contributeurs reçoivent l'installation locale ; un futur opérateur reçoit séparément la notice et les accès autorisés. |

Les fonctionnalités, la stack et les moyens de diffusion appartiennent au projet. Les interventions administratives GitHub, la publication d'images, le traitement complet d'un incident et les gestes serveur gardent leurs procédures dédiées. Ce guide organise les références et leur partage ; sa relecture seule ne qualifie pas une remise réelle.
