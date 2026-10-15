# CDC fonctionnel — prêt gratuit de matériel

**Version 1.1 — spécification fonctionnelle.** Cette référence définit les comportements attendus et leurs critères de recette.

## Lire selon le besoin

| Chapitre                                                    | Responsabilité                                                                            | Exigences             |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------- | --------------------- |
| [Cadre et accès](cadre-et-acces.md)                         | Périmètre, acteurs, droits, langues et comportements communs de l'interface.              | EPR-F-001 à EPR-F-014 |
| [Catalogue et disponibilité](catalogue-et-disponibilite.md) | Références, images, recherche, quantités, archivage et écrans du catalogue.               | EPR-F-020 à EPR-F-035 |
| [Demandes et prêts](demandes-et-prets.md)                   | Composition, engagements, états, historique, documents et écrans des prêts.               | EPR-F-040 à EPR-F-061 |
| [Recette](recette.md)                                       | Situations et résultats observables reliés aux exigences, sans solution d'implémentation. | EPR-S-001 à EPR-S-022 |

Les identifiants `EPR-F-xxx` appartiennent aux exigences et restent stables. Les plages comportent volontairement des réserves ; elles ne représentent pas des exigences manquantes. Les identifiants `EPR-S-xxx` appartiennent aux scénarios. Une référence croisée évite une seconde définition du même comportement.

## Résultat attendu

Un emprunteur consulte les matériels disponibles dans son atelier, prépare une demande de plusieurs références, puis la confirme. Toutes les quantités sont réservées ensemble. L'équipe accepte ou refuse, remet le matériel puis enregistre son retour complet. Les personnes autorisées suivent le même état sur leurs écrans et consultent un bordereau privé daté de l'acceptation.

Le noyau réserve les quantités dès confirmation, utilise des comptes précréés rattachés chacun à un seul atelier et traite entièrement les demandes multiarticles.

## Frontières documentaires

Ce CDC possède les comportements, droits, données visibles, écrans et critères fonctionnels. Le [CDC technique](../technique/README.md) possède conception et contrats ; la [documentation interne](../../interne/README.md) possède les recettes et paramètres opérationnels. Le [profil](../../interne/profil-projet.md) oriente vers ces sources. Les [règles](../../../../.ai/rules/index.md) expliquent les méthodes d'implémentation et ne remplacent aucune exigence produit.

Les résultats des scénarios sont conservés dans les outils et artefacts de vérification. Leur rédaction ne prouve ni isolation réelle, concurrence, performance, outils, environnement ou conformité du code. Le noyau n'inclut pas d'IA, de voix, d'avatar ni d'intégration externe. Ses références sont autonomes dans le dépôt ; un ajout de périmètre ou une migration de données conservées demande sa propre conception et qualification.
