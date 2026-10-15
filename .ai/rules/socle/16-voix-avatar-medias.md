# R16 — Voix, avatar et médias

**Version 1.0 — contenu validé.** [Index des règles](../index.md#r16).

**Lire pour :** créer, auditer ou corriger capture vocale, flux audio, coordination des tours, lecteur, avatar, interruption, reprise ou ressources médias. **Règle spécialisée :** appliquer les prescriptions correspondant aux capacités présentes ; la voix n'impose pas un avatar. **Procédure :** exigence → propriétaire/contrats → activation vérifiée → capture/tours → restitution valide → interruption/reprise → libération → preuves.

Les prescriptions `R16-001` à `R16-028` sont obligatoires, sauf mention **Revue** ou **Variante** selon [R01](01-noyau-agent.md). Les [références de conception](../index.md#references-conception) possèdent comportements, modes, appareils autorisés, langues, budgets, données et garanties de bascule. Clarifier une exigence nécessaire manquante ; aucune valeur ou politique produit ne découle des illustrations.

**Frontières :** [R02](02-architecture-modules.md)/[R12](12-vue-typescript-inertia.md) possèdent organisation et cycle frontend ; [R04](04-contrats-dto-validation.md) les contrats ; [R08](08-securite-identites-isolation.md)/[R09](09-confidentialite-cycle-donnees.md) accès et données ; [R11](11-temps-reel-synchronisation.md) convergence et invalidations d'autorité ; [R13](13-interface-accessibilite-internationalisation.md) restitution utilisable ; [R14](14-assets-fichiers-stockage.md) assets ; [R15](15-ia-outils-fournisseurs.md) interprétation, outils, confirmations et résultats ; [R17](17-integrations-effets-externes.md) protocole fournisseur. Consulter selon impact [R07](07-transactions-concurrence-etats.md), [R18](18-tests-preuves-acceptation.md), [R19](19-performance-observabilite.md), [R20](20-environnement-dependances-qualite.md) et [R22](../index.md#r22) pour opérations, preuves, mesures, dépendances et erreurs. Respecter leurs statuts ; un renvoi ne qualifie pas un mécanisme.

## 1. Posséder et raccorder la chaîne

**R16-001 — Partir des capacités et exigences.** Retrouver usage, propriétaire, entrées, sorties et transitions concernées : activer, écouter, transmettre, répondre, interrompre, suspendre, reprendre, terminer. Définir les effets attendus de chacune, conditions d'autorité et alternatives. Appliquer le même examen à une correction et aux consommateurs existants. **Contrôle :** le comportement vient d'une exigence identifiable, pas d'un réglage de démonstration.

**R16-002 — Segmenter la coordination.** Placer coordinateur de session et adaptateurs près du propriétaire R02/R12. Séparer capture, transcription, lecture, rendu et échanges ; les composants affichent l'état et émettent des intentions. Instancier selon leur durée de vie réelle ; plusieurs consommateurs ne créent pas automatiquement plusieurs microphones, lecteurs ou boucles. Extraire seulement le partage justifié, sans gestionnaire universel contenant tout le dialogue.

**R16-003 — Définir les raccordements.** Déclarer formats, unités, ordre, identités, révisions, horloges et états utiles des adaptateurs. Distinguer événement de contrôle, fragment de texte et octets médias ; la synchronisation R11 ne choisit pas leur transport. Mapper les objets fournisseur à ces contrats avant usage R04/R17. Garder handles navigateur, nœuds audio et objets moteur hors des DTO de transport et de la réactivité profonde ; leur propriétaire commande leurs effets.

**R16-004 — Protéger les frontières des flux.** Résoudre autorité et capacités depuis le contexte applicatif contrôlé ; revalider côté serveur les opérations reçues. Appliquer R08/R09/R17 aux connexions, contenus et éventuelles autorisations temporaires : périmètre, expiration, renouvellement et révocation qualifiés. Aucun secret fournisseur durable dans le bundle. Examiner captures, copies, buffers, diagnostics et prestataires ; un traitement local ou un flux sans fichier n'exclut pas ses autres représentations.

## 2. Activer les capacités du navigateur

**R16-005 — Vérifier les possibilités effectives.** Examiner environnement sécurisé, API, périphérique, contraintes et format réellement utilisés ; exécuter les accès navigateur dans leur contexte client R12. Classer absence, refus, indisponibilité et demande en attente selon R22/R13. Une permission annoncée ne prouve pas une capture active ; vérifier aussi les pistes et interruptions du périphérique. Les réglages demandés ne prouvent pas ceux obtenus. [Microphone](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)

**R16-006 — Activer la sortie indépendamment.** Initier les appels exigeant une activation utilisateur dans le geste prévu ; ne pas supposer que cette faculté subsiste après l'attente d'une permission ou du réseau. Observer le résultat de `play()` ou les signaux du lecteur retenu : un `AudioContext` en état `running` ne prouve pas qu'une source déterminée joue. Traiter restriction, suspension et échec avec l'activation ou le repli prescrit ; permission microphone et lecture automatique restent distinctes. **Contrôle :** ouverture et reprise sur les navigateurs visés. [Autoplay](https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay), [états audio](https://developer.mozilla.org/en-US/docs/Web/API/BaseAudioContext/state)

**R16-007 — Protéger l'acquisition asynchrone.** Identifier chaque demande et borner celles encore pendantes auprès d'un propriétaire stable du parcours ; recréer une vue ne réinitialise pas cette borne. `getUserMedia()` peut rester sans réponse et n'accepte pas directement un `AbortSignal` : une limite d'attente applicative ne clôt pas la demande native. Après abandon ou renouvellement, invalider l'activation ; si la promesse aboutit, arrêter immédiatement les pistes devenues étrangères, sans branchement ni transmission. Vérifier l'essai avant appel natif et adoption ; rattacher succès, erreur et finalisation au travail concerné, sans effacer une nouvelle tentative. L'attente sans réponse garde une alternative selon R13.

**R16-008 — Afficher des états fidèles.** Distinguer autorisation, acquisition pendante, capture, transmission, sortie muette, suspension et arrêt. Appliquer la sémantique de pause choisie : cesser l'envoi ne signifie pas arrêter la capture. Les signaux effectifs pilotent l'état visible R13, pas seulement le bouton pressé. Relier `mute`/`unmute`, `ended` et changement de périphérique aux transitions prévues ; une réactivation suit les contrôles utiles, sans reprise implicite d'une ancienne écoute. [Indisponibilité d'une piste](https://developer.mozilla.org/en-US/docs/Web/API/MediaStreamTrack/mute_event)

## 3. Capturer et coordonner les tours

**R16-009 — Identifier les fragments révisables.** Associer fragments et corrections au contexte, tour et séquence utiles ; borner conservation et traitements R09. Distinguer provisoire, remplacé et exploitable ; un indicateur fournisseur « final » ne prouve pas qu'une demande complète est terminée. Valider et normaliser par R04/R17 avant transmission à R15 ; les reprises ne fusionnent pas des contextes ou interlocutions incompatibles.

**R16-010 — Qualifier la fin de tour.** Raccorder signaux de parole, segmentation et contexte utiles à l'interprétation R15. Examiner pauses, hésitations, reprises, chevauchements et fins réellement attendues ; présence sonore et silence seuls ne décident pas l'intention. Appliquer attente et clarification prescrites sans inventer seuil, bouton ou modèle supplémentaire. La fin de tour fournit une entrée exploitable ; elle ne vaut pas accord métier.

**R16-011 — Maîtriser écho et interruption.** Qualifier détection de reprise et protections contre la réinjection de la sortie sonore : appareils, haut-parleurs/casque, bruit et parole simultanée. Un réglage `echoCancellation` ne garantit ni élimination de la voix locale ni absence de transcription. Écarter ou clarifier une entrée d'origine incertaine selon R15 ; elle ne fournit pas une confirmation attestée. Ne pas couper systématiquement l'écoute si le parcours exige une interruption orale. **Contrôle :** faux déclenchements, voix réinjectée et reprises réellement attendues. [Contraintes d'écho](https://developer.mozilla.org/en-US/docs/Web/API/MediaTrackConstraints/echoCancellation)

**R16-012 — Garder la préparation invalidable.** Lorsque la conception retient une préparation sur entrée partielle, transmettre identités et révisions permettant à R15 de la remplacer ou de l'abandonner. Borner travail et coût, puis vérifier validité avant restitution. Révision de transcription et reprise de parole neutralisent les segments devenus inadaptés ; ni préparation ni détection média ne déclenche un effet engageant ou ne se confirme elle-même.

## 4. Interrompre et renouveler l'autorité

**R16-013 — Séparer les identités.** Distinguer périmètre/génération serveur, autorité média, génération locale, tour, réponse et segment selon les responsabilités. Renouveler la génération locale aux transitions invalidantes, y compris A → B → A. Vérifier le contexte avant adoption, branchement et lecture, pas uniquement avant requête. L'identifiant courant d'un appareil ou une connexion ouverte ne prouve pas son autorité.

**R16-014 — Invalider avant l'arrêt.** Neutraliser l'ancienne réponse, arrêter sons actifs et programmés, vider ses segments et annuler les traitements qui le permettent. Détacher les callbacks inutiles et protéger ceux qui peuvent encore arriver. Suspension d'un `AudioContext`, arrêt du fetch et fermeture d'une vue sont des opérations différentes ; aucun ne prouve seul l'annulation distante. Une reprise ne doit pas relancer les sources anciennes restées programmées. [Abort](https://developer.mozilla.org/en-US/docs/Web/API/AbortController/abort), [suspension audio](https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/suspend)

**R16-015 — Appliquer la bascule contrôlée.** Consommer le contexte d'autorité vérifié R08/R11 ; préciser point de bascule, cessation des anciens flux, activation des nouveaux et conduite en cas de perte de contact. Raccorder les vérifications serveur aux entrées encore possibles, avec R07 si nécessaire. Qualifier la garantie physique : invalider l'ancien propriétaire côté serveur ne démontre pas l'arrêt instantané du son d'un appareil injoignable. Si l'exigence n'est pas satisfaite, clarifier avant adoption.

**R16-016 — Préserver les faits métier.** Distinguer arrêt média, abandon du tour et annulation demandée d'une opération. R15/R07/R17 conservent identité d'origine, résultat acquis ou incertain et suite permise. Écarter une restitution périmée sans effacer son fait métier ; aucun repli vocal ni besoin de produire une nouvelle phrase ne rejoue une mutation. Les callbacks média ne libèrent pas implicitement réservations ou commandes.

## 5. Restituer son et animation ensemble

**R16-017 — Ordonner une sortie vérifiée.** N'envoyer à la synthèse que les segments autorisés par leur contrat de restitution, selon R15 lorsqu'ils viennent de l'IA. Valider format, réponse, séquence et dépendances ; traiter doublon, trou ou segment remplacé selon la reprise déclarée. Qualifier décodage progressif : `decodeAudioData()` attend les données complètes d'un fichier, pas des fragments arbitraires. Identifier les sources actives ou programmées pour permettre leur arrêt ; distinguer réception, tampon et lecture. [Décodage](https://developer.mozilla.org/en-US/docs/Web/API/BaseAudioContext/decodeAudioData), [sources audio](https://developer.mozilla.org/en-US/docs/Web/API/AudioBufferSourceNode)

**R16-018 — Raccorder horloge et mouvements labiaux.** Définir position, unité, origine et décalages du lecteur ; normaliser les timings avant synchronisation. Utiliser lecture réelle ou estimation de sortie qualifiée, jamais arrivée réseau ni nombre fixe d'images. Si la synchronisation labiale est requise, qualifier sa source : visèmes (poses de bouche)/phonèmes horodatés ou analyse audio retenue, puis mapper aux contrôles du personnage. L'amplitude seule ne prouve pas une articulation phonétique précise ; prévoir le traitement des indices absents et le niveau de restitution admis. Traiter pauses, vitesse et redémarrage sans décaler lèvres/gestes. Une estimation audio ne prouve pas que le destinataire a entendu. [Repère de sortie](https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/getOutputTimestamp), [visèmes — exemple de format, sans fournisseur adopté](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/how-to-speech-synthesis-viseme), [analyse du signal](https://developer.mozilla.org/en-US/docs/Web/API/AnalyserNode)

**R16-019 — Mapper les intentions aux capacités.** Le rendu consomme états applicatifs et intentions structurées admises R04/R15 selon leur origine. Vérifier compatibilité du personnage : squelette, contrôles faciaux, clips, amplitudes et cibles disponibles. Définir composition/priorités lorsque lèvres, expressions et clips partagent des contrôles, sans écritures concurrentes indépendantes. Garder transitions, variation et mouvements secondaires dans le contrôleur, sans appel modèle par image. Aucun code, chemin d'asset ou mouvement arbitraire généré n'est exécuté. R14 possède ressources et licences, la conception le vocabulaire et le rendu attendu.

**R16-020 — Interrompre aussi l'animation.** Associer gestes et mouvements labiaux à la réponse/segment et à son contexte ; arrêter ou remplacer ceux invalidés. Rétablir les contrôles détenus dans un état valide ou appliquer la transition prévue : couper une boucle peut laisser la pose précédente figée. Traiter aussi silence/fin, pause et reprise sans rattrapage brutal des gestes manqués ni influences périmées. Les marqueurs techniques restent hors du texte prononcé. Voix, informations visuelles et adaptations R13 gardent le sens du résultat ; une animation de succès ne précède pas sa preuve applicative.

## 6. Borner, libérer et dégrader

**R16-021 — Maîtriser production et consommation.** Définir limites de données en attente, durée audio, calculs, concurrence et ressources graphiques d'après les budgets. Examiner réception, décodage et programmation ; réguler, arrêter ou reprendre explicitement si le consommateur ne suit plus. Un WebSocket classique ne régule pas automatiquement les messages entrants ; aucun débordement transféré dans une file non bornée ni segment utile perdu silencieusement. Borner dimensions internes et densité de pixels du rendu, distinctes de sa taille CSS ; adapter viewport/projection au redimensionnement. Mesurer les blocages du thread principal ; qualifier `AudioWorklet` pour un traitement audio personnalisé nécessitant une exécution dédiée. [WebSocket](https://developer.mozilla.org/en-US/docs/Web/API/WebSocket), [AudioWorklet](https://developer.mozilla.org/en-US/docs/Web/API/AudioWorklet)

**R16-022 — Traiter suspension et réveil.** Raccorder visibilité, événements utiles du document et cycle Vue R12 aux modes prescrits ; page masquée, composant désactivé et démontage diffèrent. Les timers et boucles peuvent être ralentis ou suspendus : aucun contrôle local ne remplace l'autorité serveur. Au retour, vérifier contexte, ressources, flux et position avant toute réactivation. Reprendre l'état pertinent, pas une accumulation de médias anciens. [Visibilité](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API), [boucle de rendu](https://developer.mozilla.org/en-US/docs/Web/API/Window/requestAnimationFrame)

**R16-023 — Libérer selon la propriété réelle.** Arrêter pistes, sources, connexions, écouteurs, temporisations et boucles détenus ; fermer le `AudioContext` à sa fin de vie réelle avec `close()`. Retirer buffers/références inutiles et libérer ressources CPU, caches et GPU selon les API du moteur, sans attendre uniquement le ramasse-miettes. Une piste arrêtée par `stop()` ne déclenche pas elle-même `ended` : nettoyer directement. Respecter ressources partagées et dernier propriétaire R12/R14 ; une pause n'impose pas une fermeture définitive. **Contrôle :** visites/activations/sorties répétées sans accumulation ni destruction du travail voisin. [Arrêt des pistes](https://developer.mozilla.org/en-US/docs/Web/API/MediaStreamTrack/stop), [fermeture audio](https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/close), [WebGL](https://developer.mozilla.org/en-US/docs/Web/API/WebGL_API/WebGL_best_practices)

**R16-024 — Dégrader sans fausse réussite.** Détecter rupture réseau, absence de progression, défaut de lecture ou perte du rendu ; interrompre le chemin invalide et restituer limitation/suite sûre R13/R22. À la reconnexion, revalider contexte/état selon R11 avant réactivation. Pour WebGL, raccorder perte/restauration au propriétaire natif ou moteur : neutraliser les chargements périmés, permettre la restauration et reconstruire les ressources invalidées. Reprendre une seule boucle après disponibilité vérifiée, depuis la position audio pertinente. Ne pas ajouter de handlers concurrents à ceux du moteur. Adaptation et repli respectent qualité, données et autorité ; ils ne clôturent ni ne répètent le métier. [Perte du contexte](https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/webglcontextlost_event), [restauration](https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/webglcontextrestored_event)

## 7. Qualifier et faire évoluer

**R16-025 — Vérifier les références utiles.** Relever versions, formats, capacités et limites réellement utilisés : navigateur/OS, bibliothèques, transport et fournisseurs. Dater les extraits et consulter documentation/code correspondants lors d'une intégration, évolution pertinente ou écart. Référence consultée et compatibilité annoncée restent distinctes d'une qualification exécutée. R20 possède dépendances, R17 fournisseur, R15 intégration IA ; proposer une révision du socle selon R01, sans mise à jour silencieuse.

**R16-026 — Séparer les preuves.** Tester transitions, ordonnancement et invalidation avec des scénarios contrôlés ; vérifier ensuite capture, restrictions audio, réseau, décodage et rendu sur la chaîne réelle pertinente. Une simulation de permission ou un callback de lecture ne prouve pas le comportement d'un téléphone. Corpus et diagnostics suivent R09, appels externes R17 ; prévenir effets/coûts accidentels et distinguer résultats exécutés, prévus et manquants R18/R01.

**R16-027 — Mesurer les délais utiles et le coût.** Partir des objectifs applicables ; distinguer fin de parole, transcription exploitable, première sortie sonore, première réponse utile, résultat métier et fin de restitution. Mesurer arrêt effectif, décalage audiovisuel, erreurs de reprise, ressources et coût des préparations abandonnées avec R19. Comparer à conditions identifiables, langues/appareils/réseaux/charge pertinents ; une moyenne ou une formule d'attente rapide ne prouve pas la fluidité.

**R16-028 — Faire évoluer les raccordements.** Vérifier contrats, profils médias, personnages, consommateurs et sessions en cours avant changement. Rejouer les scénarios affectés et actualiser références/procédures selon R01/R20. Conserver compatibilités nécessaires et comportement explicite pour une ancienne session ; ne pas adopter un autre format ou moteur par simple remplacement d'import. Les décisions et seuils restent dans la conception, leurs modalités de livraison chez R21.

## Trois illustrations sur un même support

**Portée :** exemples de responsabilités, pas application autonome ni choix final de modules, moteur ou prestataire. Références navigateur/Vue consultées le 2 octobre 2026 ; qualification sur appareils cibles et compilation restent à effectuer.

```text
resources/js/modules/dining/conversation/voice-session/
├── components/VoicePanel.vue             # État et intentions, sans micro propre
├── composables/
│   ├── useMediaSession.ts                # Coordinateur des trois parcours
│   └── useMicrophone.ts                  # Acquisition protégée, illustration A
├── adapters/
│   ├── microphone.ts                    # requestMicrophone, API native
│   ├── playback.ts                      # Contrat de lecture ci-dessous
│   └── avatar.ts                        # Contrat de rendu ci-dessous
└── types/media-session.ts               # Types locaux, distincts du transport
```

Le coordinateur reçoit le contexte autorisé et ses invalidations depuis l'intégration R11 ; il n'en fabrique pas l'autorité. Les contrats de transport suivent R04 et ne sont pas redéfinis ici. `MediaContext`, projection locale illustrative, porte `scopeKey: string` et `authorityVersion: string` ; l'entrée renouvelle son objet ou le met à `null` à tout changement pertinent d'acteur, périmètre, génération, autorité ou validité. Ce type ne prouve pas seul les droits.

| Adaptateur illustratif | Contrat nécessaire à ces parcours |
| --- | --- |
| `microphone.ts` | `requestMicrophone(): Promise<MediaStream>` appelle `navigator.mediaDevices.getUserMedia({ audio: true })` sur le client vérifié ; chaque flux acquis appartient à cet appel. |
| `playback.ts` | Consomme des segments validés/ordonnés ; expose position de lecture et signaux utiles. L'arrêt d'une réponse supprime sons actifs **et programmés** ainsi que ses buffers. API concrète et format restent à qualifier. |
| `avatar.ts` | Consomme états/intentions et indices labiaux admis, avec position de lecture normalisée ; mappe aux contrôles disponibles, invalide animations/poses périmées et libère ses ressources détenues. Les capacités viennent du personnage qualifié. |

### A — Activation et choix collectif

**Hypothèse :** le propriétaire de session installe une seule fois `useMicrophone` dans son scope Vue actif, indépendamment des panneaux recréés. Le verrou de l'extrait est local à cette instance : si ce propriétaire est lui-même remplacé pendant une demande pendante, l'intégration conserve son suivi/sa borne au niveau stable R16-007. Le contexte nullable vient du raccordement autorisé ; le callback d'échec est un adaptateur privé R22, pas l'affichage direct d'un diagnostic. Dans `useMicrophone.ts` :

```ts
import { onScopeDispose, readonly, ref, shallowRef, watch, type Ref } from 'vue';
import { requestMicrophone } from '../adapters/microphone';
import type { MediaContext } from '../types/media-session';

export function useMicrophone(
    context: Readonly<Ref<MediaContext | null>>,
    onCaptureFailure: (error: unknown) => void,
) {
    const stream = shallowRef<MediaStream | null>(null);
    const acquiring = ref(false);
    let generation = 0;
    let disposed = false;

    function release(value: MediaStream) {
        value.getTracks().forEach((track) => track.stop());
    }

    function stop() {
        ++generation;
        const previous = stream.value;
        stream.value = null;
        if (previous !== null) release(previous);
    }

    async function start() {
        const authorized = context.value;
        if (disposed || authorized === null || acquiring.value || stream.value !== null) return;
        const attempt = ++generation;
        acquiring.value = true;
        const current = () => !disposed && attempt === generation && context.value === authorized;

        try {
            if (!current()) return;
            const acquired = await requestMicrophone();
            if (!current()) {
                release(acquired);
                return;
            }
            stream.value = acquired;
        } catch (error: unknown) {
            if (current()) onCaptureFailure(error);
        } finally {
            acquiring.value = false;
        }
    }

    watch(context, stop, { flush: 'sync' });
    onScopeDispose(() => { disposed = true; stop(); });
    return { stream: readonly(stream), acquiring: readonly(acquiring), start, stop };
}
```

`acquiring` décrit l'unique tentative d'acquisition de cette instance, pas l'écoute du contexte courant. `stop()` l'invalide sans fermer la demande native. Tant qu'elle reste pendante, aucune nouvelle acquisition locale ne démarre : le `finally` ne termine donc pas une nouvelle tentative. Le contrôle avant appel couvre aussi une invalidation déclenchée par un watcher synchrone après publication de cet état. Changement de contexte et A → B → A écartent l'ancienne erreur ; un flux périmé est arrêté avant branchement. Le propriétaire applique par ailleurs signaux de piste et états R16-008 ; l'extrait illustre seulement l'acquisition. [Nettoyage Vue](https://vuejs.org/api/reactivity-advanced.html#onscopedispose)

Le flux est réservé au coordinateur et à ses adaptateurs ; le panneau reçoit uniquement les états. `readonly` protège le ref, pas les effets des méthodes du `MediaStream` : la propriété des opérations reste contrôlée par l'architecture. [Readonly Vue](https://vuejs.org/api/reactivity-core.html#readonly)

**Trajet :** activation prévue → contexte/capacités → acquisition et sortie sonore vérifiées séparément → capture/transcription → « un coca, et toi ? » → poursuite collective selon la conception → entrée exploitable pour R15 → réponse vérifiée → segments/lecture → avatar. Le panneau lit l'état et appelle le coordinateur ; il n'installe pas un second microphone.

| Transition | Observation attendue |
| --- | --- |
| Permission refusée | Échec actuel adapté R22/R13 ; aucune capture annoncée. |
| Permission encore pendante puis abandon | Activation invalidée, pas de flux disponible ni nouvelle demande concurrente dans ce propriétaire. |
| Vue recréée pendant l'attente | Réutiliser le propriétaire stable ou conserver le suivi des demandes natives ; ne pas remettre la borne à zéro. |
| Permission accordée après bascule | Pistes reçues arrêtées ; ni branchement ni transmission. |
| Phrase reprise après un fragment | Révision et préparation concernées remplacées ; aucune confirmation déduite du silence. |

### B — Interruption et transfert

**Hypothèse :** la conception prévoit interruption orale et un seul propriétaire vocal ; le raccordement serveur et ses garanties ont été qualifiés avec R08/R11. `useMediaSession` possède génération locale, tour/réponse, acquisition, lecteur et rendu ; une nouvelle identité locale n'est pas une nouvelle commande.

| Étape | Action du propriétaire |
| --- | --- |
| Reprise de parole pendant la réponse | Invalider la restitution ; arrêter sons actifs/programmés, retirer gestes périmés et rétablir un état facial valide ; transmettre l'entrée utile à R15. Le bruit seul n'est pas une reprise. |
| Transfert reçu et vérifié | Renouveler le contexte, invalider l'ancien, retirer ses ressources et respecter le point de bascule prévu ; le nouveau propriétaire vérifie ses propres capacités. |
| Ancien segment, décodage ou callback reçu | Vérifier génération/autorité/réponse avant tout effet ; ne pas relancer lecture, geste ou état de réussite/échec périmé. |
| Résultat métier de l'ancien tour | Garder son opération d'origine ; R15 rapproche et restitue seulement dans le contexte autorisé encore concerné. |

L'ancien téléphone peut devenir injoignable pendant le transfert : l'autorité serveur empêche les opérations non autorisées, mais l'arrêt physique du son dépend du protocole qualifié. Une animation qui cesse localement ou un message WebSocket envoyé ne démontre pas à lui seul cette garantie. Pas de protocole de bail, intervalle ni délai choisi par cette illustration.

### C — Panne et reprise synchronisée

**Hypothèse :** le lecteur fournit une position normalisée, les indices labiaux sont mappés aux contrôles compatibles du personnage. Pour un segment valide : **réception → tampon borné → décodage/programmation → lecture → position audio → lèvres/gestes**. Son retard réseau décale la lecture ; le rendu suit sa position, pas l'arrivée des données. La boucle suit le temps écoulé, sans nombre fixe d'images.

| Incident | Traitement et reprise |
| --- | --- |
| Production plus rapide que lecture | Réguler ou interrompre selon le contrat ; aucune croissance illimitée ni perte silencieuse de segments utiles. |
| Audio interrompu/réseau perdu | Stopper les sorties invalides ; à la reconnexion, vérifier contexte/état via R11 avant réactivation et traiter les segments selon le contrat de reprise, sans rejouer une mutation. |
| Page masquée puis réveillée | Appliquer la politique média ; au retour, revalider autorité et état via R11 avant réactivation, sans rejouer les anciens sons/gestes. |
| Contexte graphique perdu | Arrêter le rendu et neutraliser les chargements périmés ; reconstruire les ressources via leur propriétaire, puis reprendre une seule boucle après disponibilité vérifiée, ou appliquer le repli. L'opération métier reste distincte. |
| Panneau retiré, session encore partagée | Retirer ses propres effets ; le propriétaire de session conserve ou termine les ressources communes selon leur durée de vie R12. |

Une alternative textuelle suit les faits autorisés et R13/R15 ; elle ne recrée pas un historique interdit et n'envoie pas à nouveau une mutation pour produire une réponse. Tester plusieurs entrées/sorties et reprises pour mesurer ressources conservées, synchronisation et absence de sons orphelins.

## Recette proportionnée au changement

| Risque | Preuve attendue |
| --- | --- |
| Raccordement | Un propriétaire par durée de vie pertinente, composants consommateurs, contrats/adaptateurs identifiables. |
| Activation | Refus, absence, attente, acquisition tardive et invalidation avant appel ; vues recréées sans demandes cumulées, capture/lecture réellement distinctes. |
| Tours | Fragment corrigé, hésitation, reprise, écho/bruit et choix collectif ; aucune action engageante issue d'un fragment. |
| Autorité/concurrence | Transfert, droits retirés, A → B → A, anciens succès/erreurs/finalisations et perte réseau. |
| Restitution | Ordre/décodage, horloge, indices labiaux et contrôles compatibles ; sons programmés arrêtés, poses/segments périmés exclus. |
| Ressources/replis | Débordement, densité de rendu, veille, reconnexion et reconstruction graphique ; fermeture audio/partage et visites répétées sans accumulation ni faux succès. |
| Qualification | Simulations distinctes des essais réels ; délais/coûts/ressources comparables et contenus selon R09. |

Cette rédaction n'exécute ni compilation, test applicatif ni benchmark ; elle ne qualifie aucun moteur, navigateur, personnage ou fournisseur.
