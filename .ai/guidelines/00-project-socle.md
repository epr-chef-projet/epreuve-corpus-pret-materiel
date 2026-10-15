# Corpus commun — méthode de travail

Les chemins sont relatifs à la racine du dépôt.

- Avant une modification, lire `.ai/rules/index.md`, `.ai/rules/socle/01-noyau-agent.md` et `project-management/docs/interne/profil-projet.md`, puis sélectionner les règles selon la tâche, les chemins, les consommateurs et les risques. Ne pas charger tout le corpus par défaut.
- L'index définit l'applicabilité du corpus au projet et conserve les statuts sources. Une disposition conditionnelle s'applique seulement aux choix du profil concerné ; l'application au projet ne valide pas les révisions sources en attente.
- Appliquer R01 pour les plans, accords, contradictions, limites, preuves et délégation. Faire approuver un plan proportionné avant de modifier code, configuration ou références ; ne pas inventer de choix si une exigence essentielle manque.
- Les références partagées décrivent les fonctionnalités, contrats, procédures et limites actuels. Un journal personnel est encouragé dans `project-management/local/`, ignoré par Git et exclu des images et archives ; y conserver plans de travail, cartographies, échanges, accords et notes de reprise. Ne pas les ajouter à la documentation partagée, même sans secret technique.
- Après perte de contexte, retrouver le suivi privé de l'intervention et le confronter aux sources actuelles. Ce suivi n'est pas requis pour installer ou développer depuis un autre clone. L'installation courante est décrite dans `project-management/docs/interne/developpement/installation.md`.
- Transmettre aux sous-agents les sources et limites pertinentes, sans présumer un héritage complet du contexte.
- Le corpus canonique est `.ai/rules/`. Ne pas le dupliquer dans `.codex/` ou `.claude/`, ni laisser `record-rule` ou `infer-conventions` le réécrire. Suivre `project-management/docs/interne/raccordement-agents.md` pour entretenir cette source et les entrées existantes.
- La présence d'une entrée ou d'une configuration ne prouve ni sa consultation ni une connexion MCP. Vérifier les raccordements utiles dans l'environnement du clone.
