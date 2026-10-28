---
name: audit-fix
description: Resolve findings from an existing project audit through approved small correction batches, regression checks and tracked verification. Use when an audit report or audit directory identifies the findings to address, rather than to start a new audit or perform an unrelated refactor.
---

# Audit Fix

Lire la [procédure commune](../../../project-management/docs/procedures/audit-and-remediation.md), sections [corrections](../../../project-management/docs/procedures/audit-and-remediation.md#remediation), [vérification](../../../project-management/docs/procedures/audit-and-remediation.md#verification) et [reprise](../../../project-management/docs/procedures/audit-and-remediation.md#resumption). Retrouver les définitions de [constats](../../../project-management/docs/procedures/audit-and-remediation.md#findings) si leur catégorie ou leur preuve nécessite clarification.

- Identifier sans ambiguïté le dossier d'audit et les constats demandés. Un commit seul ne remplace pas un rapport ; retrouver sa correspondance ou demander la référence manquante.
- Lire la cartographie, le rapport, l'avancement et les parties utiles. Examiner le code actuel et les règles applicables avant de reprendre un constat ancien.
- Conserver les plans et le suivi dans `progress.md`, en Markdown, sans état machine parallèle ni fichier annexe non demandé. Respecter les livrables `.md` de la procédure.
- Proposer les lots cohérents de correction et attendre l'approbation du plan de chaque lot. La demande d'utiliser ce skill n'approuve pas à elle seule une modification encore indéfinie.
- Réaliser la portée approuvée, éprouver la résolution et examiner sa conformité avant le lot suivant. Conserver le constat initial et distinguer correction réalisée et résultat vérifié dans `progress.md`.
- Avant de clore un lot, appliquer le [contrôle de clôture](../../../project-management/docs/procedures/audit-and-remediation.md#closure) aux obligations affectées, avec leurs preuves, écarts ou limites dans les supports existants.
- Soumettre les ambiguïtés ou changements d'exigence à arbitrage ; ne pas modifier les règles ou affaiblir les tests pour masquer un écart.
- Remettre l'état des constats et les preuves ou limites restantes. Respecter les accords distincts du circuit de contribution et de livraison ; aucune fusion, publication ou mise en service implicite.

La procédure doit être disponible avec le skill. Si elle manque, la rechercher dans les références du projet et signaler le manque avant les modifications dépendantes.
