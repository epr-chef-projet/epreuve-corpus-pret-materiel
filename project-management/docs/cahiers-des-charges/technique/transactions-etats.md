# Transactions, états et répétitions

**Version 1.3 - spécification technique.** [Index technique](README.md). Références produit : [engagements](../fonctionnel/catalogue-et-disponibilite.md), [transitions et historique](../fonctionnel/demandes-et-prets.md), [conflits et résultat incertain](../fonctionnel/cadre-et-acces.md#synchronisation). Le [schéma](donnees-securite.md) et les [contrats](../../interne/architecture/contrats-et-lots.md) possèdent leurs détails.

La clôture explicite d'une création Catalogue incertaine dispose d'une entrée HTTP et d'une migration d'évolution. Chaque installation doit recevoir cette migration et le code compatible ; la présence de cette spécification ne prouve pas leur application sur un poste ou serveur.

<a id="epr-t-030"></a>

## EPR-T-030 - Connexion et unité propriétaire

Les mutations utilisent la connexion PostgreSQL primaire du rôle applicatif, en `READ COMMITTED`. L'action propriétaire ouvre l'unité, installe le contexte d'accès fiable avant tout SQL protégé, puis appelle services et repositories. Les participants Catalog/Lending utilisent cette même connexion ; un retour de méthode ou savepoint n'est pas un commit extérieur. Une mauvaise connexion, l'absence de transaction ou un contexte imbriqué d'un autre atelier provoque un échec avant l'accès.

Les compositions de lecture qui exigent un instantané commun choisissent explicitement `READ ONLY REPEATABLE READ`, avec durée maximale de cinq secondes et lots bornés. Elles n'héritent pas d'une transaction de mutation et n'en modifient pas l'isolation. Les projections sont terminées sous ce contexte ; aucun générateur SQL paresseux n'en sort.

<a id="epr-t-031"></a>

## EPR-T-031 - Ordre commun de protection

Après coordination de l'identité d'opération, une commande visant une demande verrouille d'abord cette demande, puis les références nécessaires, triées par UUID croissant. Le remplacement prend l'union des anciennes et nouvelles références : jusqu'à 200 identifiants pour deux ensembles de 100. Aucun parcours ne verrouille un équipement puis une demande. Changement du total, archivage et traitements d'images ne verrouillent pas de demande.

Les lignes `equipment` existantes sont la ressource commune protégée avant les décisions d'engagement, libération, total ou archivage. Tous les producteurs suivent ce protocole, y compris travaux et recettes autorisés. Verrouiller une somme, les seuls prêts trouvés ou Redis ne protège pas l'insertion concurrente d'un engagement. Les lectures sont refaites après acquisition ; les services reçoivent cet état, sans SQL implicite.

<a id="epr-t-032"></a>

## EPR-T-032 - Atomicité et état acquis

État de demande, lignes, historique prévu par le cycle confirmé, preuve d'opération et messages/obligations durables nécessaires sont validés dans une seule unité SQL. Un refus après écriture partielle annule l'ensemble. L'erreur sort de la fermeture transactionnelle avant adaptation ; un résultat d'échec capturé dedans ne doit pas permettre un commit partiel.

Les engagements sont dérivés des lignes et états acquis, sans compteur autonome : réservés pour `pending`/`accepted`, remis pour `handed_over`, nuls sinon. La disponibilité vaut total moins ces deux sommes. Une image, un PDF ou une notification ne décide jamais cette quantité. Aucun réseau, rendu PDF ou attente humaine n'a lieu sous cette transaction ; PostgreSQL et Redis ne sont pas annoncés atomiques.

<a id="epr-t-033"></a>

## EPR-T-033 - Brouillon et première confirmation

Un brouillon appartient à son auteur ; l'équipe n'y accède pas. Enregistrer, reprendre et supprimer ne créent ni engagement ni historique de prêt confirmé. Les lignes déjà enregistrées peuvent devenir indisponibles sans être perdues ; une nouvelle référence épuisée ou archivée n'est pas ajoutable. Un brouillon vide est enregistrable.

Confirmer exige une demande non vide, version attendue et lignes admissibles de l'atelier. Sous les protections communes, Lending obtient descriptions/total/actif via Catalog et tous les engagements via sa projection autorisée. Toutes les quantités doivent convenir ensemble. Le succès capture noms/descriptions/catégories et langue de première confirmation, passe à `pending`, historise et enregistre la preuve. Un échec conserve brouillon et saisie utile sans engagement partiel.

<a id="epr-t-034"></a>

## EPR-T-034 - Remplacement complet

Seul l'auteur remplace les lignes à l'état `pending`, avec sa version attendue. Pour chaque référence, le minimum disponible pour sa proposition est calculé en excluant son ancien engagement : quantité proposée ≤ total − engagements des autres demandes. Cette exclusion vient de la demande protégée, jamais d'une quantité annoncée par le client.

Les lignes existantes gardent leur descriptif historique ; une référence nouvellement introduite capture le descriptif courant. Une ligne archivée peut rester, diminuer ou disparaître, mais pas augmenter ; aucune nouvelle ligne archivée n'entre. La proposition ne peut être vide. Si une ligne échoue, anciennes lignes, réservations et version restent intactes. Un succès remplace tout ensemble, augmente la version une fois et historise avant/après.

<a id="epr-t-035"></a>

## EPR-T-035 - Décisions de l'équipe et actions physiques

Toutes les commandes portent la version relue du récapitulatif ; une modification concurrente impose une nouvelle lecture avant acceptation. Acceptation garde l'engagement et capture l'instantané immuable du bon, avec obligation durable de préparation. Refus et annulation autorisés libèrent l'ensemble ; le motif d'équipe est obligatoire.

Remise transforme réservé en remis sans libération ni double décompte. Retour complet libère l'ensemble. Annulation/remise concurrentes ne réussissent pas toutes deux ; les états terminaux ne sont pas rouverts. Le tableau fonctionnel possède transitions et rôles, sans seconde matrice concurrente ici. Un commit atteste l'action enregistrée par l'équipe, pas l'accomplissement physique observé par le logiciel. État, acteur, date, motif et historique sont acquis ensemble.

<a id="epr-t-036"></a>

## EPR-T-036 - Catalogue et quantité totale

WorkshopOperations possède le changement du total : autoriser le responsable, protéger la référence Catalog et sa version, lire les engagements Lending, puis décider total proposé ≥ réservés + remis. Catalog enregistre le total par son contrat participant, avec le minimum protégé fourni par l'orchestrateur. Aucun module ne modifie les tables du voisin ; Catalog ne consulte pas Lending.

Archivage/réactivation utilisent la même protection de référence. Une confirmation concurrente voit donc l'un des deux états acquis, sans engagement d'une référence déjà archivée lors de sa décision. Archiver n'annule aucun prêt et n'empêche pas les transitions sur ses lignes confirmées. Une nouvelle intention demandant un état déjà atteint vérifie d'abord la version attendue, puis confirme par reçu sans mutation, incrément ni invalidation. Une version périmée reste un conflit. Tous les changements pertinents d'une référence font évoluer sa version ; les engagements évoluent sous la version de demande, pas sous une prétendue version de disponibilité Catalog.

<a id="epr-t-037"></a>

## EPR-T-037 - Identité d'opération durable

La portée est atelier, acteur, type de commande et `operationKey` UUID. Une empreinte du contenu canonique comprend ressource, valeurs normalisées, lignes triées sémantiquement et version attendue. Une même charge utile avec une nouvelle clé est une nouvelle intention. Acquérir la clé, appliquer la mutation et conserver son résultat minimal réussissent ensemble ; une unicité effective arbitre les appels concurrents.

Même clé/contenu : restituer la preuve acquise après droits courants, sans refaire l'effet ni exiger que l'état actuel égale l'ancien résultat. Même clé/contenu différent : conflit. Un refus annulant l'unité ne conserve pas une réussite. La relecture après conflit utilise un nouvel instantané d'instruction sur le primaire ; une preuve absente/incomplète impose rapprochement, jamais mutation aveugle. Aucune expiration automatique : preuves conservées jusqu'à remise à zéro explicite. Une suppression de brouillon conserve la preuve nécessaire à ses répétitions tardives.

Pour `catalog.equipment.create`, lorsque le contenu d'un envoi incertain a été perdu, le responsable courant peut demander explicitement sa résolution. Cette commande ne reconstruit pas le contenu et ne crée pas de matériel. Dans une unité inscriptible `READ COMMITTED`, elle prend le même verrou d'intention que la création, puis relit l'issue sur le primaire dans une instruction distincte. Si un reçu existe, elle rend le succès initial sans l'annuler. Si la clôture existe, elle la rend. Sinon elle enregistre une clôture immuable, confirmée seulement après commit. Une seule issue terminale, `applied` ou `closed`, occupe cette portée.

Toute création tardive ou tentative de reprise contrôle la clôture avant comparaison d'empreinte et avant insertion. Une clé clôturée ne peut plus produire de matériel ; elle est refusée selon le [contrat d'erreur](../../interne/architecture/contrats-et-lots.md#resolution-creation). La clôture ne produit ni version Equipment, ni invalidation Catalogue, ni faux reçu de succès. Les autres familles conservent leur protocole actuel.

Un timeout, une erreur, `notEstablished` ou une réponse perdue ne prouve pas que la clé est clôturée. La vérification autorisée peut retrouver le succès ou la clôture après perte de réponse ; seule une preuve terminale valide autorise le retrait de la preuve navigateur. Après clôture confirmée, un nouveau clic explicite peut créer une nouvelle intention ; une réponse réseau ne la soumet jamais automatiquement. La clôture serveur reste conservée sans TTL afin de refuser un ancien appel tardif.

Les formes de résultat et les protections HTTP appartiennent aux [contrats](../../interne/architecture/contrats-et-lots.md#resolution-creation) ; la présentation contextuelle et la conservation d'une nouvelle saisie relèvent d'[EPR-T-063](frontend-temps-reel.md). La résolution explicite ne constitue pas une autorisation de suppression d'un matériel ou d'une donnée métier.

<a id="epr-t-038"></a>

## EPR-T-038 - Échec, limites et suites

Configurer localement `lock_timeout` à 2 s et `statement_timeout` à 5 s pour les unités concernées. Au maximum trois tentatives complètes sont admises après deadlock ou échec de sérialisation reconnu avec rollback certain ; attente cumulée entre tentatives ≤250 ms et durée totale ≤15 s. La dernière tentative respecte le temps restant. Ces objectifs sont à qualifier, sans présumer la reconnaissance exacte des erreurs par Laravel.

Ne pas rejouer automatiquement conflit métier/version, attente expirée ou commit incertain. Reconstruire l'état PHP après rollback. Une réponse perdue exige lookup de preuve puis récupération de l'état courant avec la même identité d'intention. Si le commit est acquis mais la publication échoue, le prêt reste acquis : l'obligation durable permet reprise du PDF/image ; une notification perdue est compensée par la récupération HTTP. Fichiers et objets PHP ne sont pas annulés par SQL ; leur préparation/publication relève de [traitements et fichiers](traitements-fichiers.md).

<a id="epr-t-039"></a>

## EPR-T-039 - Preuves à produire

Exercer PostgreSQL avec deux connexions indépendantes, rôle applicatif et contextes réels. Des barrières observables et délais bornés contrôlent les deux ordres des courses : dernière unité, réduction du total/confirmation, archivage/confirmation, modification/acceptation, annulation/remise. Vérifier persistance, absence d'effet partiel et issue présentée.

Ajouter rollback après première écriture, mauvaise connexion/contexte, version périmée, double clé simultanée, contenu divergent, nouvelle intention, suppression de brouillon répétée, commit/réponse perdue et crash avant relais. Le succès d'une transaction interne suivi d'un rollback extérieur ne publie rien. Ni SQLite, rôle contournant RLS, mock ou transaction englobante du test ne prouve ces garanties. Les [profils de qualification](qualification.md) relient ces preuves aux EPR-S.

Pour la clôture de création, exercer les deux ordres création/clôture, le rollback du premier détenteur, deux clôtures simultanées, l'appel initial tardif et la réponse de clôture perdue puis relue. Constater une seule issue persistée et aucun effet de création après clôture. Compléter par création non reçue, rechargement et perte du contenu dans le navigateur, avec action de résolution et nouvelle saisie explicite. Les régressions appartiennent aux suites Catalogue PHP, frontend et Browser existantes.
