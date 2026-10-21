# Catalogue et disponibilité

**Version 1.2 - spécification fonctionnelle.** Ce chapitre définit les références proposées et les quantités disponibles. Le [cadre et les accès](cadre-et-acces.md) possède les acteurs et droits ; le [cycle des demandes](demandes-et-prets.md) possède les transitions. Les [scénarios de recette](recette.md) vérifient leurs résultats conjoints.

## 1. Références et consultation

**EPR-F-020 - Fiche d'une référence.** Une référence appartient à un atelier et comporte un nom, une description facultative, une catégorie, une image facultative, une quantité totale et un état actif ou archivé. Nom et catégorie doivent être renseignés. La catégorie est un libellé de classement porté par la référence ; le noyau ne prévoit pas de gestion séparée des catégories. Les quantités sont des nombres entiers supérieurs ou égaux à zéro. Une référence représente plusieurs unités interchangeables, sans numéro de série ni disponibilité à date future.

**EPR-F-021 - Catalogue de l'atelier.** Chaque membre consulte uniquement les références de son atelier. Le catalogue de prêt propose les références actives, y compris celles dont la quantité disponible est nulle. Une référence épuisée porte une indication visible et ne peut pas être ajoutée à une demande ; une référence archivée n'est plus proposée pour un nouveau choix. Le responsable retrouve les références archivées dans la gestion du catalogue.

**EPR-F-022 - Recherche et navigation bornée.** Le catalogue permet de rechercher un fragment du nom sans distinction de majuscules et minuscules, et de filtrer par catégorie. Les deux critères peuvent être combinés. Les résultats sont présentés par pages de taille limitée ; la navigation conserve les critères et une nouvelle recherche repart de la première page. La liste est ordonnée par nom, puis départagée de manière stable lorsque plusieurs noms sont identiques. L'écran distingue chargement, absence de référence, absence de résultat et échec de consultation. La taille des pages et les seuils de performance sont définis dans les [contrats](../../interne/architecture/contrats-et-lots.md#vocabulaire) et la [qualification technique](../technique/qualification.md).

**EPR-F-023 - Écrans de consultation.** Le catalogue présente, pour chaque référence, nom, catégorie, image ou visuel de remplacement, quantité disponible et accès au détail. Le détail présente aussi la description et permet de choisir une quantité pour une demande lorsqu'il en reste. La quantité totale et la répartition des engagements sont visibles dans la gestion du responsable ; leur consultation ne donne pas accès aux demandes personnelles des autres emprunteurs.

## 2. Quantités et engagements

**EPR-F-024 - Définition de la disponibilité.** Pour une référence, la quantité disponible est sa quantité totale moins les quantités réservées par les demandes **À valider** ou **Acceptée**, et moins les quantités des demandes **Remise** non encore retournées. Les brouillons et les demandes **Refusée**, **Annulée** ou **Retournée** ne consomment aucune quantité. Chaque ligne ne contribue qu'une seule fois au calcul. Le résultat ne peut pas être négatif.

La disponibilité appartient au suivi des engagements des demandes, dont [le cycle](demandes-et-prets.md) définit les actions. Les écrans du catalogue présentent ce résultat ; modifier une fiche ou une image ne crée, ne réduit et ne supprime aucun engagement.

**EPR-F-025 - Effet des actions.** La confirmation réserve les quantités de toutes les lignes ensemble. L'acceptation maintient cette réservation. La remise remplace la réservation par du matériel en possession de l'emprunteur, sans changement de disponibilité. Refus et annulation avant remise libèrent l'engagement ; le retour libère le matériel remis. Acceptation, refus, annulation, remise et retour concernent la demande entière. Une modification avant acceptation remplace ensemble les engagements selon le cycle documenté ; elle peut libérer la différence lorsqu'une ligne est diminuée ou retirée.

**EPR-F-026 - Disponibilité affichée et décision actuelle.** Une quantité affichée informe sur l'état connu ; elle ne garantit pas que les unités resteront disponibles jusqu'à la confirmation. Les changements d'engagement actualisent les vues concernées. Si la quantité a changé avant une confirmation ou une modification, l'opération est évaluée sur l'état courant : elle ne peut pas provoquer de surengagement. En cas de refus, la saisie et les engagements antérieurs restent conformes au [cycle des demandes](demandes-et-prets.md), et l'utilisateur reçoit une explication compréhensible.

**EPR-F-027 - Ajustement de la quantité totale.** Seul le responsable change la quantité totale. La nouvelle valeur ne peut pas être inférieure à la somme des quantités réservées et remises non retournées au moment du changement. Une réduction égale à ces engagements est possible et porte la disponibilité à zéro. Une augmentation rend les unités supplémentaires disponibles lorsque la référence est active. Un refus conserve la valeur précédente et la saisie à corriger ; il indique le minimum actuellement requis sans révéler les demandes d'un autre atelier.

_Exemple de calcul._ Dix perceuses sont recensées : deux sont dans une demande à valider, une dans une demande acceptée et trois ont été remises. Il en reste quatre disponibles. Accepter la première demande, puis la remettre, laisse quatre unités disponibles. Retourner les trois perceuses déjà remises porte la disponibilité à sept. Tant que six unités sont engagées, réduire la quantité totale à cinq est refusé ; la réduire à six est possible.

## 3. Gestion et archivage

**EPR-F-028 - Création et modification.** Le responsable crée une référence et modifie les champs de sa fiche, sa quantité et son état. Le formulaire indique les champs obligatoires, conserve les valeurs saisies en cas de refus et rattache les erreurs aux champs concernés lorsque cela est possible. Une modification des informations textuelles ou de la quantité ne dépend pas de la réussite du traitement d'une image facultative. Les textes enregistrés dans le catalogue restent dans la langue dans laquelle ils ont été saisis.

**EPR-F-029 - Archivage sans perte historique.** L'archivage retire une référence des nouveaux choix et n'annule aucune demande confirmée. Il ne change ni leurs engagements, ni leurs informations historiques, ni leurs documents. Les demandes déjà confirmées restent traitables. Les quantités engagées demeurent suivies, même si la référence n'est plus proposée au prêt. La gestion du catalogue utilise l'archivage ; elle ne propose pas de suppression des références dans le noyau. Avant l'envoi, une confirmation annulable explique le retrait des nouveaux choix et la conservation des demandes existantes. Une demande d'état déjà courant, fondée sur la bonne version, confirme ce résultat sans modifier à nouveau la fiche.

**EPR-F-030 - Ligne devenue archivée.** Un matériel archivé ne peut plus entrer dans une nouvelle demande ni être confirmé depuis un brouillon qui le contenait. Pour une ligne déjà confirmée et encore modifiable, la quantité peut rester identique, diminuer ou être retirée ; elle ne peut pas augmenter. Les autres lignes suivent leurs conditions habituelles. Après acceptation, les possibilités de modification restent celles définies par le cycle des demandes.

**EPR-F-031 - Retour à l'état actif.** Le responsable peut rendre active une référence archivée. Elle réapparaît alors dans le catalogue de prêt avec sa disponibilité courante, qui tient toujours compte des engagements existants. Cette action ne rouvre pas les demandes terminées et ne réécrit pas leur historique.

**EPR-F-032 - Écrans de gestion.** La liste du responsable distingue les références actives et archivées, permet la recherche et le filtre par catégorie, puis donne accès à la création et à la modification. Une fiche de gestion montre quantité totale, quantités réservées, quantités remises non retournées et quantité disponible. Avant d'archiver une référence, l'interface annonce son retrait des nouveaux choix et le maintien des demandes existantes. Les actions de gestion ne sont pas proposées aux autres rôles, conformément au cadre des accès.

_Exemple d'archivage._ Une demande confirmée multiarticles contient notamment deux perceuses. Le responsable archive leur référence : elle disparaît des nouveaux choix mais la ligne confirmée demeure. Avant acceptation, l'emprunteur peut conserver deux unités, passer à une ou retirer la ligne tant que la demande n'est pas vide ; passer à trois est refusé. L'équipe peut encore accepter et remettre la demande. Réactiver ensuite la référence conserve la quantité effectivement engagée par cette demande.

## 4. Images et états visibles

**EPR-F-033 - Image facultative et préparation.** Une référence sans image utilisable présente un visuel de remplacement. Le responsable peut envoyer une image et voit les états **En préparation**, **Disponible** ou **Échec**. La référence reste consultable et utilisable selon ses autres conditions pendant cette préparation. Le catalogue destiné aux emprunteurs n'affiche que l'image disponible ou le visuel de remplacement.

**EPR-F-034 - Remplacement et reprise.** Lorsqu'une image disponible est remplacée, l'ancienne reste affichée tant que la nouvelle n'est pas prête. Un échec conserve cette ancienne image ; en l'absence d'image précédente, le visuel de remplacement demeure. Le responsable voit l'échec et peut relancer la préparation ou sélectionner une autre image. Seule une préparation réussie publie le remplacement. Un échec ne supprime pas les informations textuelles enregistrées et ne bloque ni le catalogue ni les demandes.

Le responsable peut aussi retirer l'image : le visuel de remplacement réapparaît, sans changement des engagements. Une préparation ancienne terminée après ce retrait ou un remplacement plus récent ne republie pas un visuel devenu périmé.

**EPR-F-035 - Refus d'une image.** Un fichier non reconnu comme image ou ne respectant pas les conditions de réception est refusé avec une explication attachée au champ. Aucune image disponible n'est remplacée par ce fichier. Les formats, dimensions, tailles et autres conditions exactes sont définis dans les [traitements de fichiers](../technique/traitements-fichiers.md) et annoncés à l'utilisateur dans le formulaire ; leur qualification ne doit pas être laissée à une découverte après l'envoi.

## Références de recette

La [recette fonctionnelle](recette.md) couvre la recherche sur un catalogue volumineux, les engagements concurrents, les effets des transitions, les limites de réduction, l'archivage et la préservation de l'historique, ainsi que les images refusées ou dont le remplacement échoue. Ce chapitre ne constitue pas une preuve d'exécution de ces scénarios.
