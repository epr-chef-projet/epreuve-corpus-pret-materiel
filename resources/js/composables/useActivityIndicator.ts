import { onScopeDispose, readonly, ref, watch, type Ref } from 'vue';

/** Presentation timing only. The operation and its permissions stay independent. */
export function useActivityIndicator(active: Readonly<Ref<boolean>>) {
    const visible = ref(false);
    let shownAt = 0;
    let show: ReturnType<typeof setTimeout> | undefined;
    let hide: ReturnType<typeof setTimeout> | undefined;
    const stop = watch(
        active,
        (value) => {
            clearTimeout(show);
            clearTimeout(hide);
            if (value) {
                if (!visible.value)
                    show = setTimeout(() => {
                        shownAt = Date.now();
                        visible.value = true;
                    }, 250);
            } else if (visible.value) {
                hide = setTimeout(
                    () => {
                        visible.value = false;
                    },
                    Math.max(0, 300 - (Date.now() - shownAt)),
                );
            }
        },
        { immediate: true, flush: 'sync' },
    );
    function dispose() {
        stop();
        clearTimeout(show);
        clearTimeout(hide);
        visible.value = false;
    }
    onScopeDispose(dispose);
    return { visible: readonly(visible), dispose };
}
