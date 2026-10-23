export const catalogMessages = {
    fr: {
        catalog: {
            title: 'Gestion des matériels',
            create: 'Créer un matériel',
            details: 'Fiche du matériel',
            edit: 'Informations textuelles',
            name: 'Nom',
            category: 'Catégorie',
            description: 'Description (facultative)',
            total: 'Quantité totale',
            initialTotal: 'Quantité initiale',
            save: 'Enregistrer les textes',
            createSubmit: 'Créer le matériel',
            saving: 'Enregistrement en cours…',
            saved: 'Enregistrement confirmé.',
            replayed: 'Cet enregistrement avait déjà été effectué.',
            back: 'Retour à la liste',
            search: 'Fragment du nom',
            categoryFilter: 'Catégorie exacte',
            searchSubmit: 'Rechercher',
            clear: 'Effacer les critères',
            empty: 'Aucun matériel dans cet atelier.',
            noResults: 'Aucun matériel ne correspond à ces critères.',
            loading: 'Actualisation en cours…',
            readFailed:
                'La consultation a échoué. La dernière information connue est conservée.',
            retryRead: 'Actualiser',
            previous: 'Page précédente',
            next: 'Page suivante',
            page: 'Page {page}',
            open: 'Consulter',
            active: 'Actif',
            archived: 'Archivé',
            state: 'État',
            stateActions: 'Mise en service du matériel',
            archive: 'Archiver ce matériel',
            reactivate: 'Réactiver ce matériel',
            archiveTitle: 'Archiver ce matériel ?',
            archiveDescription:
                'Il ne sera plus proposé pour de nouveaux choix. Sa fiche et les demandes existantes seront conservées.',
            archiveConfirm: 'Confirmer l’archivage',
            archiveDone: 'Archivage confirmé.',
            reactivateDone: 'Réactivation confirmée.',
            stateSaving: 'Modification de l’état en cours…',
            resumeState: 'Reprendre la même modification d’état',
            cancel: 'Annuler',
            noImage: 'Aucune image',
            partial:
                'Disponibilité réelle, prêts, modification du total et images seront raccordés lors des prochaines étapes.',
            totalNotice:
                'Cette quantité est le total recensé. Elle ne représente pas la disponibilité au prêt.',
            stale: 'La mise à jour prend du retard. Les dernières informations connues restent affichées ; actualise pour les vérifier.',
            conflict:
                'La fiche a changé depuis le début de ta saisie. Ta saisie est conservée avec sa version de départ.',
            loadCurrent: 'Reprendre les valeurs actuelles',
            currentValues: 'Valeurs actuellement enregistrées',
            dirtyNotice:
                'Les nouvelles valeurs enregistrées sont consultables ci-dessus. Ta saisie reste inchangée.',
            pendingTitle: 'Enregistrements à vérifier',
            pending:
                'Le résultat de cet enregistrement est encore inconnu. Vérifie-le avant de le reprendre.',
            verify: 'Vérifier le résultat',
            checking: 'Vérification en cours…',
            notEstablished:
                'Aucun reçu trouvé pour le moment. Cela ne prouve pas que l’enregistrement a échoué.',
            resume: 'Reprendre exactement le même enregistrement',
            cannotResume:
                'La saisie précédente n’est plus en mémoire. Consulte la fiche après vérification ; aucun renvoi automatique n’est possible.',
            pendingLimit:
                'Vérifie les enregistrements déjà en attente avant un nouvel envoi.',
            storageLimit:
                'Le navigateur ne conserve pas les preuves de vérification. Elles resteront disponibles seulement tant que cet écran est ouvert.',
            applied: 'Cet enregistrement est confirmé.',
            verifyFailed:
                'Le résultat ne peut pas être vérifié pour le moment.',
            preparationFailed:
                'L’envoi n’a pas pu être préparé. Vérifie les enregistrements en attente puis réessaie.',
            required: 'Les champs marqués * sont obligatoires.',
        },
        issues: {
            catalog: {
                content_conflict:
                    'Cette fiche a été modifiée. Consulte son état actuel avant de reprendre.',
                version_exhausted:
                    'Cette fiche ne peut plus être modifiée. Signale le problème au responsable.',
            },
            operations: {
                intent_mismatch:
                    'Cette clé correspond à un autre enregistrement. Vérifie le résultat précédent avant de créer une nouvelle intention.',
            },
        },
    },
    en: {
        catalog: {
            title: 'Equipment management',
            create: 'Create equipment',
            details: 'Equipment details',
            edit: 'Text information',
            name: 'Name',
            category: 'Category',
            description: 'Description (optional)',
            total: 'Total quantity',
            initialTotal: 'Initial quantity',
            save: 'Save text information',
            createSubmit: 'Create equipment',
            saving: 'Saving…',
            saved: 'Save confirmed.',
            replayed: 'This save had already been completed.',
            back: 'Back to the list',
            search: 'Name fragment',
            categoryFilter: 'Exact category',
            searchSubmit: 'Search',
            clear: 'Clear filters',
            empty: 'There is no equipment in this workshop.',
            noResults: 'No equipment matches these filters.',
            loading: 'Refreshing…',
            readFailed:
                'Reading failed. The last known information is retained.',
            retryRead: 'Refresh',
            previous: 'Previous page',
            next: 'Next page',
            page: 'Page {page}',
            open: 'View',
            active: 'Active',
            archived: 'Archived',
            state: 'Status',
            stateActions: 'Equipment service status',
            archive: 'Archive this equipment',
            reactivate: 'Reactivate this equipment',
            archiveTitle: 'Archive this equipment?',
            archiveDescription:
                'It will no longer be offered for new selections. Its details and existing requests will be retained.',
            archiveConfirm: 'Confirm archiving',
            archiveDone: 'Archiving confirmed.',
            reactivateDone: 'Reactivation confirmed.',
            stateSaving: 'Changing status…',
            resumeState: 'Resume the same status change',
            cancel: 'Cancel',
            noImage: 'No image',
            partial:
                'Actual availability, lending, total changes and images will be connected in later steps.',
            totalNotice:
                'This is the recorded total. It does not represent availability for lending.',
            stale: 'Updates are delayed. The last known information is still displayed; refresh to check it.',
            conflict:
                'This equipment changed since you started editing. Your input and its original version are retained.',
            loadCurrent: 'Use the current values',
            currentValues: 'Currently saved values',
            dirtyNotice:
                'The newly saved values are shown above. Your input is unchanged.',
            pendingTitle: 'Saves to verify',
            pending:
                'The result of this save is still unknown. Check it before resuming.',
            verify: 'Check the result',
            checking: 'Checking…',
            notEstablished:
                'No receipt was found yet. This does not prove that the save failed.',
            resume: 'Resume exactly the same save',
            cannotResume:
                'The previous input is no longer in memory. View the equipment after checking; no automatic resubmission is possible.',
            pendingLimit:
                'Check the saves already pending before sending another one.',
            storageLimit:
                'The browser cannot retain verification proofs. They will be available only while this screen remains open.',
            applied: 'This save is confirmed.',
            verifyFailed: 'The result cannot be checked right now.',
            preparationFailed:
                'The save could not be prepared. Check pending saves and try again.',
            required: 'Fields marked * are required.',
        },
        issues: {
            catalog: {
                content_conflict:
                    'This equipment changed. Read its current state before resuming.',
                version_exhausted:
                    'This equipment cannot be changed again. Report the problem to your manager.',
            },
            operations: {
                intent_mismatch:
                    'This key describes another save. Check the previous result before starting a new intention.',
            },
        },
    },
};
