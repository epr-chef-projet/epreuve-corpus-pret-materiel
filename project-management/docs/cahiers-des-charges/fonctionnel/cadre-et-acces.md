# Cadre et accès

**Version 1.1 — spécification fonctionnelle.** [Index du CDC](README.md). Ce chapitre possède le périmètre, les identités, les droits et les comportements communs de l'interface.

## 1. Produit et limites

<a id="perimetre"></a>
**EPR-F-001 — Objet du projet.** L'application permet le prêt gratuit de matériel disponible immédiatement dans deux ateliers indépendants. Les matériels sont gérés par références et quantités interchangeables, sans numéro de série. Une demande porte sur un seul atelier et peut comporter plusieurs références ; acceptation, remise et retour concernent la demande entière.

**EPR-F-002 — Périmètre court.** Le noyau comprend connexion/déconnexion, catalogue illustré, demandes, traitement par l'équipe, suivi partagé, historique et bordereau PDF privé. Il exclut inscription publique, gestion des comptes ou membres dans l'interface, prêt anonyme, paiement, emails, réservation à date future, expiration automatique, échéance/retard, remise ou retour partiels, matériel endommagé/perdu, transfert entre ateliers et administration globale. IA, voix, avatar, intégrations externes et transformation de données déjà déployées restent des extensions distinctes. Ces exclusions ne dispensent pas des droits et de la cohérence exigés pour le noyau.

**EPR-F-003 — Données de démonstration.** Les données sont exclusivement synthétiques. Le jeu minimal comporte deux ateliers, chacun avec un responsable, un agent de prêt et deux emprunteurs : huit comptes distincts. Chaque atelier comporte des références similaires à celles de l'autre, dont une disponible en une seule unité et des références permettant une demande multiarticles. Comptes, documents et images n'utilisent pas d'identités ni de données personnelles réelles. Les quantités exactes et jeux de charge sont définis par la [qualification technique](../technique/qualification.md) ; les propriétés ci-dessus sont requises.

## 2. Comptes et droits

**EPR-F-004 — Identité.** Chaque compte précréé appartient à un seul atelier et possède un seul rôle : emprunteur, agent de prêt ou responsable. L'atelier est visible dans l'interface ; aucun sélecteur ni appartenance multiple n'est proposé. Connexion par identifiant de compte et mot de passe, déconnexion explicite et demande de reconnexion après expiration de session. Aucun identifiant ou mot de passe réel n'est fixé par ce document ; les accès de démonstration seront fournis avec leur recette. Le traitement d'un défaut de connexion ne doit pas révéler de contenu privé.

<a id="droits"></a>
**EPR-F-005 — Matrice des autorisations.** Les rôles sont distincts : le responsable bénéficie des opérations de l'agent, mais aucun rôle de l'équipe ne reçoit implicitement la possibilité d'emprunter. Les droits ci-dessous s'appliquent à l'atelier du compte.

| Opération                                                               | Emprunteur     | Agent de prêt                    | Responsable                      |
| ----------------------------------------------------------------------- | -------------- | -------------------------------- | -------------------------------- |
| Consulter le catalogue actif et les disponibilités                      | Oui            | Oui                              | Oui                              |
| Créer, reprendre, supprimer un brouillon                                | Ses brouillons | Non                              | Non                              |
| Confirmer une demande                                                   | Ses brouillons | Non                              | Non                              |
| Consulter demandes, détails et historique                               | Ses demandes   | Demandes confirmées de l'atelier | Demandes confirmées de l'atelier |
| Modifier/annuler une demande à valider                                  | Ses demandes   | Annuler seulement                | Annuler seulement                |
| Accepter/refuser une demande à valider                                  | Non            | Oui                              | Oui                              |
| Annuler une demande acceptée avant remise                               | Non            | Oui                              | Oui                              |
| Enregistrer remise puis retour                                          | Non            | Oui                              | Oui                              |
| Télécharger ou relancer le bordereau prévu                              | Ses demandes   | Demandes confirmées de l'atelier | Demandes confirmées de l'atelier |
| Créer/modifier les références, images et quantités ; archiver/réactiver | Non            | Non                              | Oui                              |
| Consulter la gestion des références archivées                           | Non            | Non                              | Oui                              |

Chaque opération reste soumise à son état et aux conditions du [cycle de prêt](demandes-et-prets.md#transitions). Le tableau ne permet pas de passer outre une transition interdite. L'équipe ne consulte pas les brouillons des emprunteurs et ne modifie pas leurs lignes pour eux.

**EPR-F-006 — Isolation.** Un compte ne consulte ni ne modifie les données de l'autre atelier, y compris par lien direct, identifiant changé dans une demande ou ouverture d'un document. Un emprunteur ne consulte pas les demandes d'un autre emprunteur de son atelier. L'accès refusé à une ressource privée ne révèle ni contenu, propriétaire, quantités ni existence par un message différent du cas introuvable. Les opérations et actualisations en direct respectent les mêmes droits que les écrans ordinaires.

**EPR-F-007 — Droits courants et sortie du compte.** Les droits sont vérifiés pour l'opération effectuée, pas déduits d'un écran ouvert antérieurement. Après déconnexion ou session devenue invalide, les données privées de l'écran ne restent pas proposées à la consultation et les actions exigent une connexion valide. Un autre compte connecté sur le même navigateur ne retrouve pas les demandes, documents ou formulaires privés du compte précédent. Le contenu d'une page déjà vue ne donne aucun droit supplémentaire.

## 3. Interface commune

<a id="langues"></a>
**EPR-F-008 — Langues.** L'interface propose français et anglais, avec français par défaut et choix conservé lors de la navigation/reconnexion sur le même navigateur. Navigation, boutons, états, validations, refus et erreurs s'affichent dans la langue choisie ; dates et nombres suivent cette présentation. Les noms, descriptions et catégories saisis dans le catalogue ne sont pas traduits automatiquement. Changer de langue ne change ni quantités, état ou droits. Le bordereau est établi dans la langue sélectionnée par l'emprunteur lors de la première confirmation ; cette langue reste fixée lors des modifications suivantes avant acceptation et le bon n'est pas régénéré à chaque changement de langue d'écran.

**EPR-F-009 — Parcours et accessibilité.** Les écrans sont utilisables sur téléphone et ordinateur, au clavier et sans dépendre uniquement d'une couleur ou du survol. Les champs ont un libellé, les actions principales restent accessibles et les erreurs sont associées à leur champ ou au résultat global concerné. Les confirmations, refus, chargements et changements d'état sont perceptibles. Après une action ou un refus, l'utilisateur sait ce qui a changé et comment continuer.

**EPR-F-010 — Saisie et validation.** Une saisie invalide indique ce qu'il faut corriger et conserve les autres champs. Un refus métier conserve la saisie lorsque sa reprise est possible, sans présenter les modifications refusées comme enregistrées. Une erreur imprévue affiche un message général compréhensible, sans diagnostic technique. L'emprunteur est invité à réessayer après vérification de l'état ou à solliciter l'équipe de son atelier ; l'équipe peut réessayer ou signaler le problème au responsable. Aucun message n'invite l'emprunteur à administrer le système.

<a id="synchronisation"></a>
**EPR-F-011 — Suivi partagé.** Lorsqu'une opération est enregistrée, les autres écrans ouverts et autorisés concernés actualisent disponibilités, demandes et états de documents sans rechargement manuel. Un formulaire en cours de saisie n'est pas écrasé silencieusement par ces changements. Une donnée affichée antérieurement n'est pas une garantie de disponibilité : l'utilisateur connaît le résultat de sa confirmation ou modification. Les vues distinguent information confirmée, saisie locale et traitement en attente. Les objectifs chiffrés de délai seront fixés au CDC technique.

**EPR-F-012 — Coupure et résultat incertain.** Distinguer perte du réseau et sortie du compte. Pendant une coupure détectée, l'interface signale qu'elle n'est plus à jour et ne présente pas une nouvelle action comme réussie. Les données déjà connues ne sont pas étiquetées comme actuelles. Après reconnexion, elle récupère état et droits courants ; elle ne renvoie pas aveuglément une opération dont le résultat est inconnu. Une réponse perdue conduit à vérifier si l'opération a été enregistrée avant toute reprise. La déconnexion du compte applique aussi EPR-F-007.

**EPR-F-013 — Conflit et répétition.** Une opération fondée sur un état ou contenu qui a changé entre-temps est refusée et expliquée, avec possibilité de consulter l'état courant. Aucune donnée d'un autre intervenant n'est écrasée silencieusement. Un double clic, une réponse répétée ou la reprise d'une même opération déjà enregistrée ne produit pas plusieurs demandes, engagements, transitions ou bordereaux. Créer délibérément une autre demande demeure une opération différente ; ses quantités doivent être disponibles.

**EPR-F-014 — Historique et données affichées.** Les demandes confirmées et actions associées restent consultables sans expiration automatique, y compris après clôture. Le catalogue courant ne réécrit pas cet historique. Les textes saisis sont des données du catalogue ou de la demande ; leur consultation ne déclenche pas d'action à leur place. Toute remise à zéro du jeu de démonstration est une opération explicite de la recette d'environnement, distincte des écrans de prêt. Aucun parcours de suppression de compte ou de purge générale n'est ajouté au noyau.

## 4. Navigation attendue

La connexion mène au catalogue pour l'emprunteur et au tableau des demandes pour l'équipe. Chaque écran indique atelier, compte et rôle. L'emprunteur retrouve catalogue et demandes personnelles ; l'équipe retrouve tableau des demandes et catalogue ; le responsable dispose en plus de la gestion du catalogue. Choix de langue et déconnexion restent accessibles. Les détails des écrans sont possédés par les chapitres [catalogue](catalogue-et-disponibilite.md) et [prêts](demandes-et-prets.md#ecrans).

La [recette](recette.md) vérifie ces comportements communs. Cette référence ne constitue pas un résultat de test exécuté.
