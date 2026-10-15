# Index des règles d’implémentation

Les vingt-deux règles du socle et les deux compléments ci-dessous constituent le référentiel d'implémentation du projet, selon les choix et le périmètre du [profil](../../project-management/docs/interne/profil-projet.md). Leur application au projet est autorisée ; les statuts sources encore à valider restent distincts de cette applicabilité. Une méthode liée à un outil non retenu ne s'applique pas.

Les en-têtes et le tableau donnent les versions courantes. Les dispositions documentaires de R01 v1.3/R20 v1.3, les précisions sur les tests locaux de R18 v1.2 et les commandes de R20 v1.3 sont validées ; l'applicabilité de R01/R18 et les extractions CI de R20/R21 restent à valider dans le corpus source. Aucune présence de texte ne prouve un contrôle opérationnel.

## Sélectionner les procédures

1. Lire [R01](socle/01-noyau-agent.md), les instructions de la racine et les références de conception pertinentes.
2. Retrouver les choix applicables dans le profil du projet ; sélectionner socle et compléments selon tâche, chemins, consommateurs et risques.
3. Lire les textes nécessaires et réviser la sélection si un nouvel impact apparaît. Les liens ne commandent pas un chargement récursif de tout le corpus.

Le **socle** explique les garanties et méthodes de la stack concernée ; il ne choisit pas les fonctionnalités ou outils du projet. Les branches d’outils s’appliquent seulement au profil correspondant. Les **compléments** donnent les méthodes d’un environnement ou circuit sélectionné. Un complément inapplicable n’est pas une dérogation ; un texte prévu n’est pas une règle existante. Les exemples n’adoptent aucun paramètre produit.

<a id="references-conception"></a>
## Sources de conception

- [Profil du projet](../../project-management/docs/interne/profil-projet.md) : orientation vers choix et procédures applicables.
- [CDC fonctionnel](../../project-management/docs/cahiers-des-charges/fonctionnel/README.md) : comportements et critères de recette.
- [CDC technique](../../project-management/docs/cahiers-des-charges/technique/README.md) et [documentation interne](../../project-management/docs/interne/README.md) : conception, contrats, paramètres et recettes selon leur statut.

<a id="regles"></a>
## Règles du socle et statuts

L’en-tête du fichier porte sa version et son statut ; ce tableau en donne la synthèse. Consulter la règle pour la procédure, pas uniquement cette fiche.

| ID | Règle | Statut courant | Lire pour |
|---|---|---|---|
| <a id="r01"></a>R01 | [Noyau de travail des agents](socle/01-noyau-agent.md) | v1.3 — suivi privé validé ; applicabilité à valider | Plans approuvés, consultation ciblée, méthode de travail, preuves, exceptions et mémoire privée. |
| <a id="r02"></a>R02 | [Architecture et modules](socle/02-architecture-modules.md) | v1.1 — révision à valider | Frontières métier, dépendances, structure des fichiers, règles d'extension et propriété des données. |
| <a id="r03"></a>R03 | [Implémentation du code métier en PHP/Laravel](socle/03-php-laravel-metier.md) | v1.1 — révision à valider | Implémentation des cas d'usage, invariants, dépendances, calculs et qualité du code PHP/Laravel. |
| <a id="r04"></a>R04 | [Contrats, DTO et validation](socle/04-contrats-dto-validation.md) | v1.1 — révision à valider | DTO, validation de forme, conversions, sérialisation et évolution ; sémantique des erreurs possédée par R22. |
| <a id="r05"></a>R05 | [Schéma, migrations et historique](socle/05-schema-migrations-historique.md) | v1.0 — validée | PostgreSQL, types, clés, contraintes, index physiques, migrations et données historiques. |
| <a id="r06"></a>R06 | [Requêtes, lectures et cache](socle/06-requetes-lectures-cache.md) | v1.0 — validée | Lectures par usage, coût du parcours collectif, projections et lots bornés, plans SQL, contexte effectif et cache. |
| <a id="r07"></a>R07 | [Transactions, concurrence et états](socle/07-transactions-concurrence-etats.md) | v1.0 — validée | Transactions, concurrence, relecture Eloquent, idempotence, reprises et preuves. |
| <a id="r08"></a>R08 | [Sécurité, identités et isolation](socle/08-securite-identites-isolation.md) | v1.0 — validée | Comptes, autorisations, appareils, sessions, isolation des données et secrets. |
| <a id="r09"></a>R09 | [Confidentialité et cycle de vie des données](socle/09-confidentialite-cycle-donnees.md) | v1.0 — validée | Application des exigences de traitement et de cycle de vie ; représentations, copies, conservation et suppression. |
| <a id="r10"></a>R10 | [Jobs, événements et fiabilité](socle/10-jobs-evenements-fiabilite.md) | v1.1 — révision à valider | Jobs, événements, files, Horizon, publication durable, résultats par consommateur et reprise bornée. |
| <a id="r11"></a>R11 | [Temps réel et synchronisation](socle/11-temps-reel-synchronisation.md) | v1.1 — révision à valider | Reverb, branche hybride, canaux autorisés, rapprochement et récupération des vues. |
| <a id="r12"></a>R12 | [Vue, TypeScript et Inertia](socle/12-vue-typescript-inertia.md) | v1.1 — révision à valider | Pages, composants, composables, état, contrats et cycle de vie du frontend. |
| <a id="r13"></a>R13 | [Interface, accessibilité et internationalisation](socle/13-interface-accessibilite-internationalisation.md) | v0.2 — à valider | Restitution des états/erreurs, interactions et focus, adaptations évaluées, catalogues/langues/formats et replis sûrs. |
| <a id="r14"></a>R14 | [Assets, fichiers et stockage](socle/14-assets-fichiers-stockage.md) | v1.1 — révision à valider | Vite/Tailwind, styles et chargement, préparation des fichiers, publication/remplacement, accès, documents et licences. |
| <a id="r15"></a>R15 | [IA, outils et fournisseurs](socle/15-ia-outils-fournisseurs.md) | v1.1 — révision à valider | Capacités/version du SDK, contexte et connaissances, outils/validation, confirmations, résultats, performance et évaluations. |
| <a id="r16"></a>R16 | [Voix, avatar et médias](socle/16-voix-avatar-medias.md) | v1.0 — validée | Coordination des médias selon les capacités : activation, capture/tours, interruptions/autorité, restitution, ressources et qualification. |
| <a id="r17"></a>R17 | [Intégrations et effets externes](socle/17-integrations-effets-externes.md) | v0.1 — à valider | Fournisseurs, appels réseau, webhooks, délais, erreurs et effets externes. |
| <a id="r18"></a>R18 | [Tests, preuves et acceptation](socle/18-tests-preuves-acceptation.md) | v1.2 — tests locaux validés ; applicabilité à valider | Politique de preuves, niveaux de test, simulations/intégrations, efficacité, recette réelle et acceptation. |
| <a id="r19"></a>R19 | [Performance et observabilité](socle/19-performance-observabilite.md) | v1.0 — validée | Contrats de mesure, instrumentation, journaux/métriques/traces, charge, diagnostic, comparaison et alertes. |
| <a id="r20"></a>R20 | [Environnement, dépendances et qualité](socle/20-environnement-dependances-qualite.md) | v1.3 — prescriptions documentaires et commandes validées ; extraction CI à valider | Environnement déclaré, configuration, dépendances, Git, contrôles reproductibles et documentation partagée ; méthodes Sail/CI dans leurs compléments. |
| <a id="r21"></a>R21 | [Livraison, exploitation et reprise](socle/21-livraison-exploitation-reprise.md) | v1.0 — extraction CI/promotion à valider | Livraison selon le circuit retenu, déploiement, compatibilité, supervision, sauvegardes et reprise. |
| <a id="r22"></a>R22 | [Gestion des erreurs et exceptions](socle/22-erreurs-exceptions.md) | v1.0 — validée | Issues, exceptions/codes, propagation, diagnostic responsable, restitution et catalogue. |

## Compléments conditionnels

Consulter l’[index des compléments](projet/index.md). [ENV-SAIL](projet/environnements/laravel-sail.md) reste validé en v1.0 ; son extraction Sail de R20 a été validée. [LIV-CI](projet/livraison/validation-ci-promotion-humaine.md), v1.0 validé, porte les méthodes du circuit local → CI → promotion humaine, extraites de R20/R21 selon son plan approuvé. Ces extractions restent à valider distinctement. Aucun lien vers un texte présent ne vaut validation ou qualification.


## Raccordement

La [procédure de raccordement](../../project-management/docs/interne/raccordement-agents.md) décrit les entrées vers le corpus unique de ce dépôt. Le suivi personnel reste dans un sous-dossier privé de l'espace de gestion, ignoré par Git et exclu des images et archives partagées. Les références partagées sont utilisables sans ces notes.
