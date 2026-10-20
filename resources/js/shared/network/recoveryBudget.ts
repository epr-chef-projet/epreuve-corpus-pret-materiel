type Waiting = { start: () => void; abort: () => void };
const waiting = new Set<Waiting>();
let active = 0;

/** Shared only by authorized projection/context reads, never by mutation replay. */
export function withRecoverySlot<T>(
    work: () => Promise<T>,
    signal: AbortSignal,
): Promise<T> {
    return new Promise<T>((resolve, reject) => {
        let started = false;
        const entry: Waiting = {
            abort() {
                if (started) return;
                waiting.delete(entry);
                signal.removeEventListener('abort', entry.abort);
                reject(new DOMException('Recovery cancelled', 'AbortError'));
            },
            start() {
                if (signal.aborted) {
                    entry.abort();
                    return;
                }
                started = true;
                waiting.delete(entry);
                signal.removeEventListener('abort', entry.abort);
                active++;
                void Promise.resolve()
                    .then(work)
                    .then(resolve, reject)
                    .finally(() => {
                        active--;
                        for (const next of waiting) {
                            if (active >= 2) break;
                            next.start();
                        }
                    });
            },
        };
        if (signal.aborted) {
            entry.abort();
            return;
        }
        if (active < 2) entry.start();
        else {
            waiting.add(entry);
            signal.addEventListener('abort', entry.abort, { once: true });
        }
    });
}
