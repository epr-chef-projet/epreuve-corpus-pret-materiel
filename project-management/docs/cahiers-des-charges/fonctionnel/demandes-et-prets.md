# Demandes et prêts

**Version 1.1 — spécification fonctionnelle.** [Index du CDC](README.md). Ce chapitre possède composition, états, actions, historique, bordereaux et écrans du prêt. Les [droits](cadre-et-acces.md#droits) et le [catalogue](catalogue-et-disponibilite.md) restent leurs sources propriétaires.

## 1. Préparer et confirmer

**EPR-F-040 — Identité de la demande.** Une demande a une référence propre, un atelier, un emprunteur auteur, un état et des lignes. Chaque ligne désigne une référence de matériel de ce même atelier et une quantité entière strictement positive. Une référence apparaît au plus une fois ; la modifier change la quantité de sa ligne. Aucune demande ne mélange ateliers ou emprunteurs. L'auteur ne change pas après création.

**EPR-F-041 — Brouillon.** L'emprunteur peut créer plusieurs brouillons indépendants, les enregistrer, les reprendre dans ses demandes et les supprimer. Un brouillon enregistré est retrouvable après reconnexion ; la saisie non enregistrée n'est pas présentée comme conservée. Le brouillon peut être vide, ne réserve aucune quantité, ne produit pas de bordereau et n'est pas visible par l'équipe. Le quitter avec des changements non enregistrés avertit du risque de perdre la saisie. Supprimer un brouillon ne modifie aucune disponibilité.

**EPR-F-042 — Composer.** Depuis le catalogue, « Ajouter » crée une ligne ou permet de modifier sa quantité si elle existe déjà. L'emprunteur ajoute, retire ou modifie les lignes de son brouillon. Les quantités nulles, négatives, fractionnaires ou non numériques sont invalides ; retirer une ligne est une action explicite. Une référence épuisée ou archivée n'est pas nouvellement ajoutable. Un brouillon antérieur peut néanmoins contenir une quantité devenue indisponible ou une référence désormais archivée : la situation est indiquée sans perdre les autres lignes.

Le catalogue ouvert depuis une composition indique la demande ciblée ; « Ajouter » vise uniquement cette composition explicite. Depuis le catalogue sans composition ouverte, le premier ajout démarre un nouveau brouillon, jamais un ancien choisi silencieusement. Le détail d'une demande À valider ouvre explicitement sa modification ; les ajouts depuis ce parcours appartiennent à la proposition de remplacement et ne sont enregistrés qu'avec celle-ci.

**EPR-F-043 — Confirmer ensemble.** Avant envoi, un récapitulatif présente matériels et quantités, avec une action explicite « Confirmer la demande ». Une demande vide n'est pas confirmable. La confirmation réussit seulement si toutes les lignes sont admissibles et toutes leurs quantités disponibles au moment où elle est enregistrée. Elle fait passer le brouillon à « À valider » et réserve toutes ses quantités. Si une ligne échoue, le brouillon reste un brouillon et aucune quantité n'est réservée. Le refus désigne les lignes concernées avec un message compréhensible ; l'emprunteur corrige ou retire des lignes puis confirme à nouveau.

**EPR-F-044 — Description historique.** La première confirmation capture les noms, descriptions et catégories alors présentés pour les lignes. Les modifications ultérieures du catalogue ne réécrivent pas ces informations. Lors d'une modification acceptée de la demande, une ligne existante conserve son descriptif capturé ; une nouvelle référence prend son descriptif courant. L'historique conserve les états précédents de la demande et le détail des changements. L'image courante du catalogue n'est pas une preuve historique et ne doit pas être présentée comme une photographie garantie du matériel à la confirmation.

## 2. États et actions

<a id="etats"></a>
**EPR-F-045 — Sens des états.** « Confirmée » désigne la réussite de l'envoi par l'emprunteur, distincte de l'acceptation par l'équipe.

| État affiché | Signification                                                      | Engagement des lignes                                               |
| ------------ | ------------------------------------------------------------------ | ------------------------------------------------------------------- |
| Brouillon    | Choix enregistrés pour préparation, non envoyés.                   | Aucun.                                                              |
| À valider    | Demande confirmée, en attente du choix de l'équipe.                | Toutes les quantités sont réservées.                                |
| Acceptée     | L'équipe a accepté le contenu courant ; matériel pas encore remis. | Les mêmes quantités restent réservées.                              |
| Remise       | L'équipe atteste avoir remis toute la demande.                     | Quantités sorties, toujours indisponibles pour les autres demandes. |
| Retournée    | L'équipe atteste avoir récupéré toute la demande.                  | Aucun engagement restant.                                           |
| Refusée      | L'équipe a refusé avant acceptation.                               | Aucun engagement restant.                                           |
| Annulée      | L'auteur ou l'équipe a annulé dans les conditions autorisées.      | Aucun engagement restant.                                           |

<a id="transitions"></a>
**EPR-F-046 — Transitions autorisées.** Les quantités sont traitées ensemble. Toutes les autres transitions sont interdites, sans effets sur état ou disponibilité.

| Action                | Départ → arrivée      | Auteur autorisé                          | Conditions particulières                                          |
| --------------------- | --------------------- | ---------------------------------------- | ----------------------------------------------------------------- |
| Confirmer             | Brouillon → À valider | Emprunteur auteur                        | EPR-F-043.                                                        |
| Modifier les lignes   | À valider → À valider | Emprunteur auteur                        | Nouveau contenu admissible, non vide ; EPR-F-048/049.             |
| Annuler               | À valider → Annulée   | Emprunteur auteur ou équipe de l'atelier | Avant acceptation.                                                |
| Accepter              | À valider → Acceptée  | Équipe de l'atelier                      | Contenu courant relu ; aucun besoin de réserver une seconde fois. |
| Refuser               | À valider → Refusée   | Équipe de l'atelier                      | Avant acceptation ; motif EPR-F-050.                              |
| Annuler avant remise  | Acceptée → Annulée    | Équipe de l'atelier                      | Matériel non remis ; l'emprunteur ne dispose pas de cette action. |
| Remettre              | Acceptée → Remise     | Équipe de l'atelier                      | Confirmation explicite de la remise physique de toute la demande. |
| Enregistrer le retour | Remise → Retournée    | Équipe de l'atelier                      | Confirmation explicite de la récupération de toute la demande.    |

L'équipe désigne agent de prêt ou responsable selon [EPR-F-005](cadre-et-acces.md#droits). Les états Retournée, Refusée et Annulée sont terminaux. Une nouvelle demande est nécessaire pour recommencer un prêt. La suppression d'un brouillon n'est pas une transition de prêt ; aucune suppression de demande confirmée n'est proposée.

<a id="engagements"></a>
**EPR-F-047 — Effets sur la disponibilité.** Le [calcul du catalogue](catalogue-et-disponibilite.md) distingue réservations et matériel remis non retourné. L'acceptation conserve les réservations. La remise les remplace par des sorties de même quantité, sans libération ni double décompte. Refus ou annulation avant remise libèrent tout l'engagement ; retour complet libère les sorties. Un document en préparation ou en échec ne change aucun de ces effets. Aucun compte à rebours ne libère une demande en attente.

<a id="modification"></a>
**EPR-F-048 — Remplacer son engagement.** L'emprunteur peut modifier les lignes uniquement à l'état À valider. Le nouveau contenu et les nouvelles réservations remplacent ensemble les précédents ; si une ligne ne convient pas, l'ancien contenu et toutes les réservations précédentes restent intacts. Les unités déjà réservées par cette demande restent utilisables pour son remplacement : elles ne sont pas considérées comme celles d'un autre emprunteur. Le formulaire compare saisie proposée et dernier contenu enregistré. Retirer toutes les lignes est refusé ; l'utilisateur est invité à annuler la demande.

_Exemple :_ un atelier possède cinq perceuses. Ma demande en réserve trois et une autre en réserve deux : disponibilité affichée zéro. Garder mes trois unités ou réduire à deux reste possible ; passer à quatre ne l'est pas. Si une augmentation échoue, mes trois unités restent réservées. Une modification de deux références dont une est indisponible ne modifie aucune des deux.

**EPR-F-049 — Référence archivée après confirmation.** Une ligne confirmée peut conserver ou diminuer sa quantité, ou être retirée, même si le responsable a archivé sa référence. Elle ne peut pas être augmentée et aucune nouvelle ligne archivée n'est admise. L'archivage n'empêche pas l'équipe d'accepter, remettre ou récupérer un engagement déjà confirmé. Une référence archivée seulement après son ajout à un brouillon bloque sa confirmation ; ce brouillon n'avait encore acquis aucun engagement.

**EPR-F-050 — Motifs et choix physiques.** Refus et annulation par l'équipe demandent un court motif visible à l'emprunteur et conservé dans l'historique ; l'annulation par l'emprunteur n'en demande pas. Un motif vide lors d'une action de l'équipe laisse l'état inchangé. Pour remise et retour, un récapitulatif des quantités et une confirmation explicite évitent de déclarer l'action accomplie par simple ouverture de l'écran. Le logiciel ne déduit pas une remise ou un retour physique du temps écoulé.

**EPR-F-051 — Actions concurrentes.** Deux demandes ne peuvent pas engager la même dernière unité. Modification, acceptation, annulation et remise sont aussi confrontées au contenu et à l'état courants de la demande. Si une modification réussit avant qu'un agent accepte un ancien récapitulatif, cette acceptation ne s'applique pas silencieusement au nouveau contenu : l'agent doit le relire. Si l'acceptation réussit d'abord, la modification ne s'applique pas à une demande déjà verrouillée. Une annulation et une remise concurrentes ne peuvent pas réussir toutes les deux. Le participant dont l'action n'est pas appliquée reçoit un conflit ou un refus expliquant comment retrouver l'état courant ; la répétition d'une action déjà enregistrée respecte [EPR-F-013](cadre-et-acces.md#synchronisation).

**EPR-F-052 — Historique du prêt.** Toute confirmation, modification enregistrée, acceptation, refus, annulation, remise et retour comporte auteur, date, nature de l'action et contenu utile pour comprendre le changement. Les modifications permettent de retrouver lignes et quantités avant/après. Les motifs prévus sont consultables avec l'action. Un refus technique ou une tentative non appliquée n'apparaît pas comme une transition réussie. Cet historique de prêt n'est pas le journal de diagnostic technique ; l'organisation des journaux appartient à la conception et aux règles.

## 3. Bordereau privé

<a id="documents"></a>
**EPR-F-053 — Création à l'acceptation.** Une acceptation enregistrée déclenche la préparation d'un bordereau PDF, sans attendre son achèvement pour afficher la demande acceptée. Le document appartient à cette demande et à l'acceptation datée. Aucune confirmation en attente, demande refusée ou brouillon ne crée de bordereau. Aucune action ultérieure du prêt ne crée un nouveau bon d'acceptation.

**EPR-F-054 — Contenu et langue.** Le bordereau indique référence de demande, atelier, nom de démonstration de l'emprunteur, date d'acceptation, nom de chaque matériel et quantité acceptée. Il est établi dans la langue fixée selon [EPR-F-008](cadre-et-acces.md#langues), avec les descriptifs historiques du prêt. Aucun prix, donnée d'un autre membre, mot de passe ou diagnostic n'y figure. Il porte une mention indiquant qu'il décrit l'acceptation à cette date et que l'état courant doit être consulté dans l'application.

**EPR-F-055 — États du document.** Le détail présente « En préparation », « Disponible » ou « Échec ». Un document disponible peut être téléchargé ; un travail en préparation n'est pas proposé comme téléchargement achevé. En cas d'échec, une personne autorisée peut demander une reprise, avec résultat visible. Plusieurs reprises ou clics ne produisent pas plusieurs bons concurrents. L'acceptation et les quantités demeurent enregistrées lorsque la préparation est retardée, interrompue ou échoue.

**EPR-F-056 — Document historique et droits.** Le PDF est accessible à l'emprunteur auteur et à l'équipe du même atelier, selon les mêmes droits que la demande. Le lien direct ne donne pas d'accès public. Si la demande est annulée après acceptation, le document reste une trace datée, y compris quand sa préparation finit après l'annulation ; le détail montre clairement l'état Annulée à côté du document. Le document ne prétend jamais attester d'une remise ou d'un état courant. Il reste consultable après retour. Ni archivage, renommage ni reprise de préparation ne réécrivent son contenu accepté.

## 4. Écrans et résultats attendus

<a id="ecrans"></a>
**EPR-F-057 — Composition et récapitulatif.** L'écran de composition montre chaque nom, quantité éditable, disponibilité connue et action de retrait ; il distingue brouillon et modification d'une demande déjà confirmée. Les contrôles « Enregistrer le brouillon » et « Confirmer la demande » ont des effets distincts. Pour À valider, « Enregistrer les modifications » rappelle les anciennes et nouvelles quantités. Le refus désigne les lignes à corriger ; les anciennes réservations restent identifiables. L'auteur peut revenir au catalogue sans perdre un brouillon enregistré.

**EPR-F-058 — Demandes personnelles.** L'emprunteur retrouve ses brouillons et demandes confirmées avec référence, date, état et aperçu des matériels. Il ouvre le détail et accède seulement aux actions autorisées dans cet état. Les états actifs et terminaux sont distingués ; la liste est paginée, avec filtre d'état et ordre de création décroissant départagé de façon stable. Aucune liste des prêts d'un autre emprunteur n'est proposée.

**EPR-F-059 — Tableau de l'équipe.** Agent et responsable voient les demandes confirmées de leur atelier, avec auteur, référence, heure de confirmation, état et quantités. Les demandes À valider sont présentées par confirmation la plus ancienne d'abord, avec départage stable ; l'équipe peut filtrer les autres états et ouvrir un détail. Les listes restent paginées. Accepter, refuser, annuler, remettre et récupérer passent par un détail/récapitulatif approprié : voir un ticket ne le prend pas en charge et n'accomplit aucune transition. Les demandes nouvelles ou modifiées sont signalées, sans perturber une saisie déjà ouverte.

**EPR-F-060 — Détail commun et actualisation.** Le détail autorisé montre état courant, lignes historiques, quantités, auteur, dates, historique, motif éventuel, actions permises et état du bordereau. Les actions indisponibles ne sont pas présentées comme utilisables ; un lien ou écran ancien ne les autorise pas. Les vues concernées appliquent [EPR-F-011/012](cadre-et-acces.md#synchronisation) : changement reçu, reprise après coupure et résultat incertain n'écrasent pas une saisie locale ou ne présentent pas un ancien état comme courant.

**EPR-F-061 — Issues à expliquer.** Les parcours distinguent saisie invalide, ligne épuisée/quantité insuffisante, référence archivée, accès non autorisé ou ressource indisponible, état interdisant l'action, contenu modifié entre-temps, document en préparation/échec et erreur imprévue. La restitution indique ce que l'utilisateur peut faire sans révéler de détail technique. Une demande ayant réservé ses quantités n'est pas rétroactivement refusée parce que la disponibilité publique a ensuite atteint zéro. Les formats techniques et codes internes de ces issues seront possédés par le CDC technique, sans devenir des règles produit concurrentes.
