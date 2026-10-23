import { flushPromises } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { withRecoverySlot } from './recoveryBudget';

describe('recovery read budget', () => {
    it('keeps at most two reads active and starts waiting work when a slot is released', async () => {
        const releases: (() => void)[] = [];
        let running = 0;
        let maximum = 0;
        const reads = Array.from({ length: 5 }, () =>
            withRecoverySlot(async () => {
                running++;
                maximum = Math.max(maximum, running);
                await new Promise<void>((resolve) => releases.push(resolve));
                running--;
            }, new AbortController().signal),
        );
        await flushPromises();
        expect(releases).toHaveLength(2);
        releases[0]();
        releases[1]();
        await flushPromises();
        expect(releases).toHaveLength(4);
        releases[2]();
        releases[3]();
        await flushPromises();
        expect(releases).toHaveLength(5);
        releases[4]();
        await Promise.all(reads);
        expect(maximum).toBe(2);
    });
    it('removes a cancelled waiting read without starting it or taking another slot', async () => {
        const releases: (() => void)[] = [];
        const first = [0, 1].map(() =>
            withRecoverySlot(
                () => new Promise<void>((resolve) => releases.push(resolve)),
                new AbortController().signal,
            ),
        );
        const abort = new AbortController();
        let started = false;
        const waiting = withRecoverySlot(() => {
            started = true;
            return Promise.resolve();
        }, abort.signal);
        abort.abort();
        await expect(waiting).rejects.toMatchObject({ name: 'AbortError' });
        await flushPromises();
        releases.forEach((release) => release());
        await Promise.all(first);
        expect(started).toBe(false);
    });
    it('releases a slot after a rejected read instead of blocking the next recovery', async () => {
        await expect(
            withRecoverySlot(
                () => Promise.reject(new Error('Read failed')),
                new AbortController().signal,
            ),
        ).rejects.toThrow('Read failed');
        await expect(
            withRecoverySlot(
                () => Promise.resolve('recovered'),
                new AbortController().signal,
            ),
        ).resolves.toBe('recovered');
    });
});
