# Guides de procédure

Ces guides décrivent les méthodes réutilisables pour travailler avec la stack et le circuit retenus. Leurs contenus sont validés ; l'exécution de chaque parcours se vérifie dans le contexte concerné. Les fonctionnalités, versions, paramètres et habilitations appartiennent au [profil](../interne/profil-projet.md), aux spécifications et aux recettes du projet.

| Besoin                                             | Guide                                                   | Version |
| -------------------------------------------------- | ------------------------------------------------------- | ------- |
| Créer une nouvelle base Laravel                    | [Initialisation](initialisation.md)                     | 1.4     |
| Préparer un poste et utiliser Docker/Sail          | [Environnement Docker](environnement-docker.md)         | 1.2     |
| Organiser, partager et entretenir les références   | [Documentation et partage](documentation-et-partage.md) | 1.3     |
| Préparer GitHub/CI, contribuer, revoir et intégrer | [GitHub et collaboration](github-et-collaboration.md)   | 1.4     |
| Construire, vérifier et publier une image          | [Image et registre](image-et-registre.md)               | 1.2     |

Pour installer un clone de ce projet, suivre la [recette d'installation](../interne/developpement/installation.md) ; ne pas recréer un squelette. Les guides expliquent les méthodes générales ; la recette du projet fixe les commandes et paramètres de sa copie existante. Choisir les sections utiles à la tâche, sans charger tous les guides.

Les méthodes communes de publication d'images et la [recette du workflow](../interne/exploitation/collaboration-livraison.md#publication-image) sont partagées sans secrets. Leur consultation ne donne aucun droit de publication. Les procédures d'intervention sur les serveurs et les accès réservés sont transmis séparément aux personnes habilitées. Le développement et les contributions ne dépendent pas de l'accès aux instructions d'exploitation privées. Les [règles](../../../.ai/rules/index.md) gardent les garanties d'implémentation ; les guides n'en modifient pas les statuts.
