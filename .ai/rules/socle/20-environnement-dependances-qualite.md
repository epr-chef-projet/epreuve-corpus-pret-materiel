# R20 — Environnement, dépendances et qualité

**Version 1.3 — commandes et prescriptions documentaires validées ; extraction CI à valider.** [Index des règles](../index.md#r20).

**Lire pour :** installer, auditer ou corriger environnement, configuration, dépendance, généré, commande de contrôle, intégration collaborative ou documentation du dépôt. **Procédure :** sources → environnement vérifié → installation/configuration → changement maîtrisé → contrôles → intégration/documentation → preuves.

Les prescriptions `R20-001` à `R20-031` sont obligatoires, sauf **Revue** ou **Variante** selon [R01](01-noyau-agent.md). Les [références propriétaires](../index.md#references-conception) et sources versionnées du projet possèdent versions, profils, paramètres et critères ; clarifier une exigence nécessaire introuvable. Les exemples ne les fixent pas.

**Applicabilité :** retrouver runtime, gestionnaires, outils de contrôle et circuit de validation dans le profil du projet, par les références propriétaires. Les branches npm et chaîne de contrôles s’appliquent lorsque leurs choix sont retenus ; elles ne les adoptent pas. Les méthodes Sail/WSL sont dans le [complément ENV-SAIL](../projet/environnements/laravel-sail.md), validé en v1.0. Les méthodes du circuit local → CI → promotion humaine sont dans [LIV-CI](../projet/livraison/validation-ci-promotion-humaine.md), validé en v1.0 ; leur extraction de cette règle reste soumise à validation distincte. Aucun texte ne constitue une recette installée ; versions, réglages et efficacité restent à qualifier.

**Frontières :** [R01](01-noyau-agent.md) accords/délégation ; [R02](02-architecture-modules.md) architecture/emplacements ; [R04](04-contrats-dto-validation.md)/[R14](14-assets-fichiers-stockage.md) contrats/assets ; [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès/données ; [R18](18-tests-preuves-acceptation.md) preuves ; [R19](19-performance-observabilite.md) mesures ; [R21](21-livraison-exploitation-reprise.md) pipeline/livraison/exploitation. Consulter les propriétaires affectés selon leur statut ; aucun renvoi ne déclare un mécanisme installé.

## 1. Retrouver et vérifier l'environnement

**R20-001 — Partir des sources effectives.** Identifier tâche, racine, instructions, état initial et environnement visé. Retrouver versions, profils et commandes dans manifestes, locks, configurations et procédure d'installation versionnés, avec liens entre ces sources ; ne pas recopier une matrice concurrente dans les règles. Distinguer retenu, installé et qualifié. Une documentation courante ou une commande réussie ailleurs ne prouve pas la compatibilité.

**R20-002 — Qualifier le runtime complet.** Relever PHP/extensions, Composer, Node/gestionnaire de packages, services, images et outils nécessaires, avec contraintes/options pertinentes. Identifier versions ou références d'images reproductibles plutôt qu'un `latest` mouvant comme preuve. Vérifier le runtime réel : `composer check-platform-reqs` ne remplace pas les autres qualifications, mais contrôle PHP/extensions sans se fier à `config.platform`. Les locks ne fixent pas tout l'environnement.

**R20-003 — Travailler dans la copie et le runtime déclarés.** Vérifier chemins/montages, casse, permissions et fins de ligne utiles ; éviter deux copies éditables supposées synchronisées. Exécuter commandes applicatives, dépendances, générations et contrôles dans le runtime déclaré/qualifié, hors amorçage R20-004 ; ne pas substituer silencieusement des outils globaux du poste. Git et éditeur peuvent fonctionner hors runtime applicatif ; validation et production utilisent leurs profils qualifiés R21. Un environnement alternatif retenu doit conserver versions, services et garanties, puis être qualifié. Aucun chemin personnel ni lanceur temporaire du laboratoire exigé par l'application. Si Sail est retenu, appliquer les méthodes du [complément ENV-SAIL](../projet/environnements/laravel-sail.md), dont la variante WSL selon le profil.

## 2. Installer et gérer le cycle local

**R20-004 — Prévoir l'amorçage sans dépendances.** Documenter l'obtention du runtime PHP/Composer avant `vendor/`, sans dépendre d'un lanceur encore absent. Vérifier montage/répertoire, extensions, droits et configuration héritée des outils avant installation. Aucun hook préalable ne suppose les dépendances déjà présentes. Le premier démarrage doit fonctionner depuis une copie propre, sans outils globaux non déclarés ni caches personnels cachés.

**R20-005 — Installer depuis les verrouillages.** Utiliser `composer install` et l'installation verrouillée du gestionnaire frontend retenu, avec versions/options déclarées et dépendances de développement requises par les contrôles. **Si npm est retenu :** utiliser `npm ci` avec son verrouillage cohérent. Vérifier cohérence manifestes/locks avant de poursuivre ; leur absence hors première résolution autorisée n'est pas une installation reproductible. Examiner scripts/plugins et autorisations, préparer/vérifier leurs prérequis avant la première exécution qui les utilise. Avec Composer, `--no-scripts` ne désactive pas les plugins ; tout différé exige des étapes de reprise qualifiées. Aucun contournement permanent des exigences de plateforme.

**R20-006 — Préparer puis vérifier les raccordements.** Dans le profil isolé prévu, configurer services, rôles/données nécessaires R05/R08/R18 et initialisation locale ; préserver une clé existante plutôt que la régénérer à chaque installation. Vérifier HTTP/frontend, SQL, Redis, workers, Reverb ou ordonnanceur selon le parcours. Un conteneur démarré ne prouve ni connexion, ni consommation, ni réception. Activer les consommateurs après leur préparation ; aucun accès de production nécessaire au bootstrap.

**R20-007 — Séparer les opérations du cycle.** Fournir commandes distinctes et effets annoncés pour démarrage, arrêt, reconstruction, mise à jour et remise à zéro. Identifier ce qui persiste : données, fichiers, caches et volumes. Qualifier disponibilité/reprise après reconstruction ; une image reconstruite ne réinitialise pas nécessairement les données.

**R20-008 — Vérifier la cible avant préparation destructive.** Résoudre environnement, base/rôle, volumes et chemins effectivement concernés ; examiner données et utilisateurs concurrents avant migration, semis ou reset. Utiliser les garanties et autorisations applicables R01/R05/R08/R18, arrêter/coordonner les consommateurs affectés et prévoir conservation/reprise requises. Un nom contenant `test` ou une branche différente ne constitue pas cette vérification.

## 3. Maîtriser la configuration effective

**R20-009 — Maintenir le contrat de configuration.** Déclarer entrées, sens/types, valeurs obligatoires et valeurs locales admissibles dans les sources/configurations et exemples versionnés. Ne pas remplacer un secret absent par une valeur fictive en exploitation. Garder secrets/données privés hors dépôt et sorties publiques selon R08/R09 ; sélectionner les valeurs exposées au frontend, notamment `VITE_*`. Un fichier ignoré par Git ne protège pas ses copies, impressions ou accès.

**R20-010 — Utiliser la configuration Laravel.** Lire les variables via `env()` dans les fichiers de configuration, puis leurs valeurs via `config()` dans l'application. Valider/convertir les types et qualifier cache/clear. Avant le premier bootstrap du contexte visé, initial ou modifié, examiner hors Laravel sources héritées, environnement du processus et provenance du cache effectif : `artisan config:clear` peut amorcer les providers avec l'ancien contexte avant suppression. Modifier `.env.example` ne change pas une installation ; le `.env` n'est pas chargé lorsque la configuration est cachée. Vérifier les valeurs utiles sans publier les secrets.

**R20-011 — Vérifier chaque processus concerné.** Après changement, identifier ce qu'utilisent HTTP, console, workers, frontend et MCP ; appliquer cache/rechargement/redémarrage ou génération requis, puis observer la prise en compte. Une commande lancée dans le mauvais PHP/dossier ou un worker ancien peut lire un autre état. Distinguer configuration serveur, valeurs intégrées au build et paramètres publics dynamiques ; leur changement ne suit pas automatiquement le même cycle.

## 4. Qualifier et faire évoluer les dépendances

**R20-012 — Justifier puis qualifier la capacité.** Examiner besoin et mécanismes déjà disponibles avant ajout. Vérifier ensemble compatible, API effectivement utilisée, contraintes/licences pertinentes, modes de démarrage et consommateurs ; utiliser documentation des versions retenues et code/tests si nécessaire. Pour nouvelle capacité, montée de version ou écart, consigner conditions/résultats/limites. Une compatibilité déclarée ou un package installé ne prouve pas son comportement ni sa performance.

**R20-013 — Maîtriser la mise à jour.** Dans le changement autorisé, borner packages et effets avant lancement. Si du nouveau code/effet reste à examiner, séparer résolution, revue et installation avec options qualifiées ; `--no-install` seul ne désactive pas scripts/plugins. Examiner diff complet manifestes/locks, dépendances indirectes, scripts, générés et configurations ; vérifier API/raccordements puis reprendre les preuves invalidées R18 et mesures pertinentes R19. Ne pas mettre à jour indistinctement toutes les dépendances pour un écart ciblé ni actualiser silencieusement une règle validée. Reporter décision et sources affectées R01.

**R20-014 — Examiner scripts, plugins et audits.** Qualifier configuration effective des gestionnaires utilisés, dont Composer et npm lorsque retenu : projet, utilisateur/global et environnement ; isoler les sources externes non prévues, examiner aussi plugins globaux et leurs autorisations. Définir permissions nécessaires plutôt qu'une autorisation générale ; l'installation exécute du code. Un plugin modifiant téléchargements/chemins ne se reporte pas comme un hook. Distinguer audit du lock, installés et runtime ; interpréter périmètre/date/sévérité et sorties. Traiter constats R08/R01 ; audit indisponible/ignoré ne prouve pas l'absence de vulnérabilité. Aucune correction automatique de dépendances sans examen.

## 5. Exécuter les contrôles communs

**R20-015 — Donner un propriétaire à chaque contrôle.** Raccorder la chaîne déclarée par le projet et conserver sa configuration versionnée. **Lorsque les outils suivants sont retenus**, respecter leurs responsabilités distinctes :

| Responsabilité | Outil |
| --- | --- |
| Format PHP | Pint |
| Analyse PHP/Laravel | PHPStan avec Larastan |
| Typage Vue/TypeScript | `vue-tsc` |
| Conventions frontend | ESLint avec intégrations Vue/TypeScript adaptées |
| Format frontend | Prettier, harmonisé avec le linter |

Compilation R14, architecture R02 et tests R18 complètent ces contrôles. Vite ne vérifie pas seul les types ; formatage, analyse, compilation et comportement restent des preuves distinctes.

**R20-016 — Qualifier configuration et portée.** Déclarer chemins réellement couverts, intégrations/parsers/extensions, presets/niveaux, sévérités et exclusions ; inclure entrées, modules, infrastructure et consommateurs utiles selon le contrôle. Qualifier projets TypeScript, templates Vue et types importés/générés ; un `tsconfig` racine de références ne suffit pas à prouver leur analyse. Corriger code/typage avant suppression d'un diagnostic. Toute exclusion ou dette admise reste ciblée et suivie R01 ; aucune baseline régénérée pour masquer les nouveaux défauts.

**R20-017 — Définir le contrat des commandes.** Documenter les commandes communes avec leur environnement d’exécution, leurs prérequis, leurs paramètres, leurs résultats attendus et leurs conditions d’échec. Privilégier les commandes fournies par les outils retenus, exécutables directement en suivant la procédure. Un script supplémentaire propre au projet nécessite un besoin précis et une valeur ajoutée justifiée avant son introduction ; sa procédure explique son rôle, ses effets et ses conditions d’utilisation. L’automatisation ne remplace pas la documentation. Les vérifications automatiques obligatoires restent conservées. Identifier points d'entrée, composition et profils de la suite requise aux étapes déclarées du circuit de validation selon R18/R21 ; ne pas laisser chaque contributeur choisir sa propre combinaison. Utiliser les exécutables locaux verrouillés ; vérifier leur résolution, sans téléchargement implicite pour compenser un outil absent. Séparer vérification et correction automatique ; préserver codes d'échec, timeout et interruption jusqu'à l'appelant. Contrôle sans fichiers, avertissements non examinés, exclusion ou outil indisponible ne vaut pas validation complète.

**R20-018 — Exécuter dans un contexte sûr.** Installer les dépendances nécessaires puis vérifier profil, services, droits et données avant analyse/tests/génération. **Avec Larastan :** son analyse démarre le conteneur applicatif Laravel ; examiner providers et bootstrap afin d'éviter secrets/effets de production. HTTP, scripts, tests et MCP restent soumis à R08/R09. Une simulation ou un profil d'analyse simplifié limite sa preuve ; il ne qualifie pas le comportement réel des raccordements omis.

**R20-019 — Valider puis optimiser sans changer la preuve.** Exécuter tests et suite requise sur le contenu concerné aux étapes déclarées par le projet ; vérifications ciblées et hooks aident pendant le travail sans remplacer les preuves requises. Conserver sens des contrôles et différences de profils/services explicites et justifiées. **Si le circuit local → CI → promotion humaine est retenu :** consulter [LIV-CI, parties 2–3](../projet/livraison/validation-ci-promotion-humaine.md#2-valider-localement-le-contenu-soumis) pour son application. Employer caches du moteur avec leurs dépendances/versionnement, pas une réutilisation aveugle d'un résultat vert. Ne pas réduire arbitrairement l'analyse aux fichiers modifiés : imports, contrats et générés affectent leurs consommateurs. Qualifier délais/ressources avec R19 sans retirer les garanties R18.

## 6. Maintenir sources et fichiers générés

**R20-020 — Identifier le contrat de génération.** Déclarer source/propriétaire, outil/version, commande, entrées/sorties, configuration et politique de versionnement près du mécanisme R02/R04/R14. Modifier la source puis régénérer ; ne pas entretenir une correction concurrente dans la sortie. Un généré non versionné doit être reconstructible au moment requis R21, avec entrées disponibles et chemins utiles ; aucun artefact local inexpliqué indispensable au projet.

**R20-021 — Vérifier présence et concordance.** Contrôler sorties attendues, créations, modifications et suppressions après génération, puis leurs consommateurs. Pour sorties versionnées, examiner aussi les nouveaux fichiers non suivis ; `git diff --exit-code` seul ne les détecte pas. Une commande Git réussie avec un état non vide n'est pas un contrôle de propreté. Les sorties ignorées demandent une vérification de présence distincte. Génération, formatage ou résolution de conflit peuvent invalider les preuves antérieures.

**R20-022 — Raccorder les agents sans réécrire leurs sources.** Maintenir corpus unique, source de raccordement commune et entrées préexistantes conservées ; empêcher une gestion concurrente des mêmes règles. Après installation/mise à jour, examiner scripts et sorties du mécanisme retenu, puis vérifier accès aux règles/MCP dans le bon runtime. **Si Boost est retenu :** suivre la [procédure de raccordement](../../../project-management/docs/interne/raccordement-agents.md) et ses vérifications de gestion concurrente. Ne pas éditer un bloc régénéré comme source ni supposer une connexion depuis un fichier présent ; aucune nouveauté fournisseur ne réécrit automatiquement le socle.

## 7. Collaborer et intégrer avec Git

**R20-023 — Borner la contribution.** Examiner état Git, changements/commits et référence de départ ; utiliser une branche courte par changement cohérent depuis la référence prévue, principale par défaut. Identifier les défauts préexistants : une principale défectueuse peut servir de base à sa correction, avec remise en validité vérifiée dans la portée autorisée. Relier différences/preuves à la tâche, sélectionner les fichiers intégrés et préserver contributions étrangères. Changement de branche, reset, nettoyage ou réécriture d'historique partagé exigent traitement de leurs effets R01. Aucun fournisseur ni durée universelle de branche imposés.

**R20-024 — Coordonner aussi les ressources.** Pour travaux indépendants, utiliser espaces de travail isolés ; `git worktree` peut fournir plusieurs branches/dossiers. Déclarer intégrateur et chevauchements ; coordonner ports, volumes, bases, caches, files et processus. Une autre copie de code ne les isole pas automatiquement. **Variante :** petites interventions dans une copie partagée avec répartition explicite et absence de changements concurrents dangereux ; délégation/reprise selon R01.

**R20-025 — Vérifier le résultat intégré.** Relire la différence à intégrer, résoudre conflits selon sources/propriétaires, puis régénérer et reprendre les preuves invalidées. Ne pas conserver arbitrairement une version ou sortie pour fermer le conflit. Faire correspondre contenu vérifié et contenu soumis : distinguer dossier, index et révision ; après différence pertinente, assurer concordance et reprendre les contrôles requis selon le circuit déclaré. **Si le circuit local → CI → promotion humaine est retenu :** appliquer [LIV-CI, partie 2](../projet/livraison/validation-ci-promotion-humaine.md#2-valider-localement-le-contenu-soumis). Une fusion produite exclusivement côté serveur n'est pas réputée testée sur le poste. Contributions séparément valides, hooks, résultat local ou CI d'une ancienne révision ne prouvent pas l'assemblage final. Déclarer état/révision vérifié selon R01/R18 ; livraison R21.

## 8. Qualifier et transmettre

**R20-026 — Éprouver la reproductibilité.** À la qualification initiale, vérifier depuis une copie propre amorçage, installation, configuration, permissions, raccordements et commandes ; reprendre les vérifications invalidées par un changement. Exercer échecs pertinents : plateforme incompatible, configuration absente/périmée, outil manquant, portée vide, sortie divergente. Pour un contrôle ou sa configuration, vérifier le rejet d'un défaut représentatif de sa responsabilité/portée jusqu’à la commande d’entrée documentée ; réutiliser les preuves encore valables. Examiner profils, copies parallèles et reprise concernés. Documentation, simulation ou preuve extérieure ne valent pas exécution réelle.

**R20-027 — Qualifier l'efficacité au bon moment.** Mesurer installation initiale séparément de réactivité quotidienne et contrôles : édition/actualisation, tests/analyse, ressources et coexistence des outils utiles. Appliquer R19 selon les critères pertinents ; qualifier les réserves d'outillage R18 à preuves comparables, puis reporter décision/révision selon leurs propriétaires. Diagnostiquer fichiers, runtime, débogage et ressources avant de déclarer une lenteur normale ou changer de stack.

**R20-028 — Garder les preuves retrouvables.** Maintenir dans les références partagées les procédures, commandes, versions, configurations, critères de vérification et limites actuelles nécessaires au travail. Conserver les résultats d'exécution dans les outils et artefacts de validation prévus, avec la révision vérifiée, le résultat et ses limites ; utiliser le suivi privé lorsque ces supports ne suffisent pas. Ne pas recopier les sorties, reçus de commande ou qualifications successives dans un journal documentaire versionné. Distinguer prévu, simulé, exécuté, réussi et accepté ; aucune preuve essentielle manquante ne devient un succès. Actualiser les exemples de configuration, générateurs, consommateurs et raccordements R21 affectés, sans dossier administratif par commande.

## 9. Préparer et maintenir la documentation collective

**R20-029 — Préparer la documentation initiale.** Fournir un README d'accueil présentant produit, état fonctionnel actuel, installation, commandes usuelles, contribution et navigation. La documentation partagée décrit ce que l'application doit faire, son architecture, ses contrats, ses choix applicables, son installation et usage local, ses tests, sa contribution et les règles d'implémentation nécessaires. Conserver chaque information dans sa source propriétaire et les commandes exactes dans leur recette. Exclure les plans d'exécution personnels, journaux, comptes rendus chronologiques, conversations et échanges d'approbation avec un agent, instructions ou mandats privés et historiques d'interventions. L'absence de mot de passe ou de secret technique ne rend pas ces contenus partageables. Rédiger les procédures sous une forme générale et impersonnelle, sans noms de personnes, désignations de postes propres à une organisation ou contexte de préparation. L'installation depuis un clone propre R20-004/026 ne dépend pas d'un chemin, d'une identité ou d'un fichier privé d'un autre poste. Les informations réservées suivent leurs habilitations et supports dédiés ; une exclusion Git ne retire ni un fichier déjà suivi ni son historique.

**R20-030 — Maintenir les références avec la contribution.** Actualiser les documents affectés dans le même changement que le code ou la configuration : exigences, contrats, recettes, exemples, état fonctionnel ou limites actuelles. Corriger les liens après déplacement et distinguer fonctionnement disponible et capacité prévue. Intégrer un enseignement utile sous forme de contrainte, prérequis ou procédure de diagnostic applicable, sans raconter qui a rencontré le problème, quand ou comment l'intervention s'est déroulée. Une justification nécessaire explique le choix en vigueur et ses conséquences, sans citer la conversation ou l'accord dont il provient. Les plans, notes de travail et comptes rendus restent dans leur support privé ; leur utilité pour une reprise n'autorise pas leur publication dans la documentation. Avant archivage, reporter seulement les informations actuelles nécessaires dans leurs sources et préserver les contributions en cours selon R01-016.

**R20-031 — Vérifier la remise collective.** Relire cohérence, liens, sources propriétaires et contenu réellement suivi, ainsi que l'historique destiné à diffusion. Vérifier que la documentation contient des références actuelles utiles au travail, sans journal d'intervenant, récit de préparation ou détail réservé. Examiner confidentialité R08/R09, exclusions et autres canaux de diffusion ; ne pas déduire l'absence d'un fichier de sa seule règle d'exclusion. Préserver les informations nécessaires et les travaux actifs avant retrait. Vérifier les procédures essentielles depuis un clone propre à la remise initiale et après changement invalidant leur preuve R20-026 ; réutiliser les preuves valables. Effectuer des vérifications proportionnées, sans suite spécialisée de tests documentaires ni validation applicative dépendant de l'immuabilité de notes historiques.

*Illustration — commande modifiée :* le contributeur change le lanceur et sa recette propriétaire, corrige les renvois du README, puis vérifie la commande. L'intégrateur examine ces changements et le contenu partagé ; il reprend l'essai depuis un clone propre si la preuve d'installation est invalidée, sans rejouer un contrôle d'empreinte des anciens comptes rendus.

## Illustrations — Séquences et extraits décisifs

Hypothèses locales fictives, pas application installée. A renvoie au complément Sail ; B suppose npm et les outils de contrôle nommés, avec exécution dans le runtime déclaré ; B et C suivent le circuit déclaré ; [LIV-CI](../projet/livraison/validation-ci-promotion-humaine.md) en précise les étapes lorsque son circuit est retenu. Ces conditions ne choisissent pas les outils ou le circuit d’un autre projet. Versions, configurations et raccordements sont à qualifier ; les commandes ci-dessous ne sont pas exécutées par cette rédaction.

### A — Arriver sur une copie propre

Appliquer les garanties R20-001 à R20-008. Si le profil utilise Sail, le [parcours complet d’amorçage et d’installation](../projet/environnements/laravel-sail.md#a--arriver-sur-une-copie-propre) est possédé par ENV-SAIL ; consulter son statut avant application. Les autres profils gardent leur recette qualifiée, sans dépendre de cet exemple.

### B — Changer une dépendance ou un type généré

Hypothèse : installation dev et configurations déjà qualifiées. Extraits à fusionner avec les scripts existants, sans remplacer hooks ou déclarations. Arbre des seuls éléments utilisés :

```text
composer.json                 # check:php
pint.json + phpstan.neon      # Portée/configuration PHP, Larastan inclus
package.json                  # check:types, check:lint, check:format
tsconfig.json                 # Projet unique ici, Vue/TS et imports générés
eslint.config.js              # Intégrations et portée Vue/TS
.prettierignore               # Exclusions de formatage justifiées
resources/js/ + resources/css/
tests/Browser/
```

Dans `composer.json` :

```json
{
  "scripts": {
    "check:php": [
      "@php vendor/bin/pint --test",
      "@php vendor/bin/phpstan analyse"
    ]
  }
}
```

Dans `package.json`, hypothèse locale d'aucun avertissement ESLint accepté :

```json
{
  "scripts": {
    "check:types": "vue-tsc --noEmit -p tsconfig.json",
    "check:lint": "eslint resources/js tests/Browser --max-warnings 0",
    "check:format": "prettier --check resources/js resources/css tests/Browser"
  }
}
```

Exécuter dans le runtime déclaré `composer check:php` puis `npm run check:types`, `npm run check:lint` et `npm run check:format`, en conservant chaque résultat/échec. `@php` utilise le PHP exécutant Composer ; `tsconfig.json` contient ici le projet à analyser, pas seulement des références vides. Les dépendances locales, sévérités, délais et exclusions sont des prérequis, pas définis par ces fragments. Larastan démarre Laravel : profil/effets maîtrisés R20-018. Ces quatre contrôles ne constituent pas la suite complète R18 ; suivre le circuit déclaré pour le contenu soumis, avec [LIV-CI, parties 2–3](../projet/livraison/validation-ci-promotion-humaine.md#2-valider-localement-le-contenu-soumis) lorsqu’il est retenu.

Reprendre le [renommage illustratif R04](04-contrats-dto-validation.md) `cooking` → `cookingLevel` : modifier le contrat source, régénérer par sa commande qualifiée, vérifier toutes les sorties puis adapter les accès effectifs dans Vue. Déclarer seulement la prop ne démontre pas que `item.cooking` a disparu. Typage, compilation et tests de contrat/consommateurs sont à exercer selon leurs garanties ; le build seul ne suffit pas.

Pour le dossier de sorties TS de cet usage, après génération :

```sh
git diff --exit-code -- resources/js/modules/dining/orders/show-order-item/contracts/
git diff --cached --exit-code -- resources/js/modules/dining/orders/show-order-item/contracts/
git status --porcelain --untracked-files=all -- resources/js/modules/dining/orders/show-order-item/contracts/
```

Examiner séparément dossier/index (`diff`), index/HEAD (`diff --cached`) **et sortie du status**, non vide possible malgré un code de succès. Une version indexée peut différer de celle testée sur disque. Pendant un changement autorisé, différences attendues à relire : un code 1 signalant une différence ne dispense pas des autres inspections ; toute erreur de commande reste à traiter. Sur une révision propre intégrée, régénérer ne doit produire aucune dérive. Vérifier liste/présence et versionnement ; sorties ignorées contrôlées séparément. Intégrer les sorties autorisées, sans supprimer un nouveau fichier pour obtenir un succès ; reprendre preuves/caches invalidés. Aucun générateur fictif ni bibliothèque adoptée ici.

### C — Intégrer deux contributions valides

Hypothèse : principale valide, deux branches courtes et ressources locales coordonnées. A renomme le champ R04 et adapte ses consommateurs ; B ajoute un affichage basé sur l'ancien champ. Chaque branche peut passer ses contrôles face à sa référence initiale.

| Étape | Procédure et preuve attendue |
| --- | --- |
| Départ/parallèle | Référence identifiée, propriétaire/intégrateur et périmètres connus ; ports/données/files isolés ou partagés explicitement. |
| Intégration | Examiner les différences, conserver la source autorisée et résoudre l'accès ajouté par B selon le contrat courant. |
| Génération | Régénérer les sorties, vérifier présence/dérive ; ne pas conserver arbitrairement un généré ancien. |
| Vérification | Exécuter les contrôles requis sur le résultat final selon les profils et le circuit déclarés, y compris typage/compilation et affichage ajouté ; [LIV-CI, partie 2](../projet/livraison/validation-ci-promotion-humaine.md#2-valider-localement-le-contenu-soumis) si ce circuit est retenu. Les deux anciens résultats ne prouvent pas cet assemblage. |

Un conflit Git absent ne prouve pas la compatibilité ; une prop typée sans accès effectif peut aussi laisser un test insuffisant. Toute différence de comportement non résolue retourne à sa source R01/R04, sans inventer une nouvelle API pour faciliter la fusion. Un worktree distinct ne suffit pas à protéger les données d'essai ; contrôler la cible avant leur reset.

**Repères consultés le 3 octobre 2026 :** [Sail](https://laravel.com/framework/docs/13.x/sail), [scripts Composer](https://getcomposer.org/doc/articles/scripts.md), [plateforme/audit Composer](https://getcomposer.org/doc/03-cli.md), [plugins](https://getcomposer.org/doc/articles/plugins.md), [npm ci](https://docs.npmjs.com/cli/v11/commands/npm-ci/)/[configuration npm](https://docs.npmjs.com/cli/v11/configuring-npm/npmrc/), [configuration Laravel](https://laravel.com/framework/docs/13.x/configuration), [bootstrap console](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Console/Kernel.php)/[config:clear](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Console/ConfigClearCommand.php), [Larastan](https://github.com/larastan/larastan), [Vue/TypeScript](https://vuejs.org/guide/typescript/overview.html), [cache PHPStan](https://phpstan.org/user-guide/result-cache), [Git diff](https://git-scm.com/docs/git-diff)/[status](https://git-scm.com/docs/git-status). Vérifier les versions utilisées ; aucun outil, script, installation, compilation, benchmark ou contrôle applicatif qualifiés par ces illustrations.
