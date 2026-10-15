# Contribution et entretien de la documentation

**Version 1.2 — contenu validé.**

Les [règles R01](../../../../.ai/rules/socle/01-noyau-agent.md) et [R20](../../../../.ai/rules/socle/20-environnement-dependances-qualite.md) définissent les garanties ; le [guide Documentation et partage](../../procedures/documentation-et-partage.md) décrit la méthode. L'organisation et les références retenues sont accessibles depuis le [README du projet](../../../../README.md) et l'[index documentaire](../../README.md).

## 1. Retrouver les références

Dans les dépôts applicatifs, retrouver les références partagées dans `project-management/docs/` et conserver les documents personnels ou réservés dans `project-management/local/`. Le [guide Documentation et partage](../../procedures/documentation-et-partage.md#2-organiser-les-références-et-les-points-dentrée) définit cette organisation ; le README de `project-management/` en présente les points d'entrée.

Lire l'accueil, puis les spécifications de la tâche, l'installation et le profil technique avant de sélectionner les règles applicables. Les références doivent suffire pour comprendre le résultat attendu et travailler sans accès aux notes privées d'un autre intervenant. Rechercher une information essentielle manquante ou contradictoire et demander sa clarification avant l'action qui en dépend.

## 2. Préparer les documents partagés

Décrire le produit, les fonctionnalités disponibles ou prévues, l'architecture, les contrats, les choix en vigueur et les procédures nécessaires à l'installation, au développement, aux tests et à la contribution. Donner accès aux règles d'implémentation. Organiser les documents par thème, avec une source maintenue pour chaque information et des liens depuis les points d'entrée.

Le README oriente et présente l'état actuel ; les exigences et commandes détaillées restent dans leurs références propriétaires. Éviter les pages vides, doublons et références à un chemin ou une configuration personnelle.

Les procédures exposent les prérequis, actions, résultats attendus et conduites à tenir en cas d'échec. Elles sont rédigées de manière générale et impersonnelle. Une limite connue utile est formulée comme une contrainte actuelle ou une instruction de diagnostic ; elle ne devient pas un récit d'incident.

## 3. Séparer références, suivi et résultats

Les plans d'exécution, cartographies de mission, notes de reprise, comptes rendus, journaux d'essais, archives préparatoires, conversations avec un agent et instructions ou mandats privés restent privés, même s'ils ne contiennent aucun mot de passe. Lorsqu'ils sont conservés dans la copie locale du projet, vérifier leur exclusion effective du versionnement, des images et des autres contenus distribués. Ne pas les publier dans la documentation, même sous forme de synthèse historique. Reporter seulement les choix et contraintes applicables, avec leur justification nécessaire, sans citer les échanges ni raconter les accords reçus.

Tenir un journal de bord privé est encouragé pour suivre l'avancement, les essais, les décisions de travail et les points à reprendre. Le conserver dans `project-management/local/`, ignoré par Git et exclu des images et archives partagées. Il reste un outil personnel, sans obligation de journal pour chaque petite tâche.

Les tests, critères et recettes nécessaires au projet restent partagés. Leurs résultats d'exécution sont conservés dans les outils et artefacts de validation prévus ; les références et compléments de reprise restent dans le suivi privé. Une recette écrite ne constitue pas une preuve de son exécution.

Les documents et informations réservés sont conservés séparément suivant les habilitations applicables. Les références de développement restent utilisables sans ces fichiers. Le partage du dépôt ne confère pas l'accès à des procédures, données ou configurations réservées.

## 4. Entretenir les références

Identifier les documents affectés par un changement, puis actualiser leurs exigences, contrats, recettes, exemples et liens dans la même contribution. Décrire le fonctionnement obtenu et ses limites, sans ajouter l'historique des travaux. Une petite correction ne demande ni dossier de suivi partagé ni nouveau formulaire.

Avant retrait ou archivage, reporter les informations actuelles encore nécessaires dans leurs sources et préserver les contributions actives. Une exclusion ne retire pas les fichiers déjà suivis ni les anciens commits ; traiter séparément le contenu courant et l'historique destiné à diffusion.

## 5. Vérifier la remise

Examiner la navigation, l'actualité, les sources concurrentes éventuelles et les fichiers réellement partagés. Vérifier que l'installation et les usages essentiels sont réalisables depuis un clone propre, sans fichier privé d'autrui. Après changement, reprendre seulement les vérifications dont les preuves sont invalidées.

La vérification documentaire repose sur une relecture, les liens et les procédures affectées, sans suite spécialisée de tests documentaires ni protection des anciens comptes rendus par empreinte. Les contrôles du code et des fichiers générés conservent leur responsabilité. Les résultats et limites de la remise restent dans leurs supports de validation ou de suivi privé, sans nouveau journal dans la documentation.
