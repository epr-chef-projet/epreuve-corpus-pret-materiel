# CDC technique - prêt de matériel

**Version 1.2 - spécification technique.** Le [CDC fonctionnel](../fonctionnel/README.md) possède les comportements attendus ; les chapitres ci-dessous définissent les choix de conception, contrats et critères techniques.

| Chapitre                                             | Responsabilité et exigences                                                                                     |
| ---------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| [Architecture et contrats](architecture-contrats.md) | Modules, propriétaires, graphe sans cycle et composition ; EPR-T-001 à 009.                                     |
| [Données et sécurité](donnees-securite.md)           | Structure initiale et migrations, contraintes/index, identité/RLS, droits et confidentialité ; EPR-T-010 à 029. |
| [Transactions et états](transactions-etats.md)       | Engagements, protections, transitions, versions, idempotence et résultat incertain ; EPR-T-030 à 039.           |
| [Traitements et fichiers](traitements-fichiers.md)   | Obligation durable, relais/résultat, PDF, images privées et reprises ; EPR-T-040 à 059.                         |
| [Frontend et temps réel](frontend-temps-reel.md)     | Inertia/JSON, état local, Reverb, convergence, langues et nettoyage ; EPR-T-060 à 069.                          |
| [Qualification](qualification.md)                    | Jeux, budgets, observation et correspondance des 52 exigences/22 scénarios ; EPR-T-080 à 089.                   |

Les identifiants sont stables ; la plage 070-079 est réservée, sans chapitre manquant ni exigence implicite. Les 79 exigences techniques sont adoptées pour le projet ; leur rédaction n'est pas une preuve d'exécution.

Les paramètres adoptés et sources propriétaires se retrouvent par le [profil](../../interne/profil-projet.md). Les [contrats publics et dépendances](../../interne/architecture/contrats-et-lots.md), les versions et les recettes sont dans la [documentation interne](../../interne/README.md). Sélectionner les méthodes depuis l'[index des règles](../../../../.ai/rules/index.md), en respectant le périmètre applicable et les statuts individuels.

Chaque capacité doit être vérifiée sur son comportement réel et sur son intégration. La réception d'une installation ou d'un raccordement ne qualifie pas les fonctionnalités qui le consomment. Les [critères de qualification](qualification.md) et les [recettes de développement](../../interne/developpement/qualite-et-recette.md) définissent les vérifications à sélectionner ; les résultats restent dans les outils et artefacts prévus.
