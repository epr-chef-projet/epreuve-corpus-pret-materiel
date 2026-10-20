import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(
    allowed: () => boolean = () => true,
): () => void {
    return router.on('flash', (event) => {
        const data = event.detail.flash.toast;

        if (!allowed() || !isFlashToast(data)) {
            return;
        }

        toast[data.type](data.message);
    });
}

function isFlashToast(value: unknown): value is FlashToast {
    return (
        typeof value === 'object' &&
        value !== null &&
        'message' in value &&
        typeof value.message === 'string' &&
        'type' in value &&
        (value.type === 'success' ||
            value.type === 'info' ||
            value.type === 'warning' ||
            value.type === 'error')
    );
}
