import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { toast } from 'vue-sonner';
import { initializeFlashToast } from '@/lib/flashToast';

vi.mock('vue-sonner', () => ({
    toast: {
        success: vi.fn(),
        info: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    },
}));

function flash(value: unknown): void {
    document.dispatchEvent(
        new CustomEvent('inertia:flash', {
            detail: { flash: { toast: value } },
        }),
    );
}

describe('typed Inertia flash boundary', () => {
    let dispose: () => void;
    beforeEach(() => {
        dispose = initializeFlashToast();
    });
    afterEach(() => {
        dispose();
    });

    it.each(['success', 'info', 'warning', 'error'] as const)(
        'dispatches a valid %s message',
        (type) => {
            flash({ type, message: 'Technical fixture' });
            expect(toast[type]).toHaveBeenCalledExactlyOnceWith(
                'Technical fixture',
            );
        },
    );

    it.each([
        undefined,
        null,
        'message',
        {},
        { type: 'unknown', message: 'text' },
        { type: 'success', message: 42 },
    ])('rejects malformed flash data (%j)', (value) => {
        flash(value);
        for (const method of [
            toast.success,
            toast.info,
            toast.warning,
            toast.error,
        ]) {
            expect(method).not.toHaveBeenCalled();
        }
    });

    it('releases its subscription when the entry is disposed', () => {
        dispose();
        flash({ type: 'success', message: 'Late fixture' });
        expect(toast.success).not.toHaveBeenCalled();
    });
});
