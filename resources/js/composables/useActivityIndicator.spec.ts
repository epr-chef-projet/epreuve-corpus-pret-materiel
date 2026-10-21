import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { effectScope, ref, type EffectScope } from 'vue';
import { useActivityIndicator } from './useActivityIndicator';

let scope: EffectScope;
beforeEach(() => {
    vi.useFakeTimers();
    scope = effectScope();
});
afterEach(() => {
    scope.stop();
    vi.useRealTimers();
});
function fixture() {
    const active = ref(false);
    const indicator = scope.run(() => useActivityIndicator(active))!;
    return { active, ...indicator };
}
it('never shows a short activity and does not change its real completion', async () => {
    const state = fixture();
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(249);
    state.active.value = false;
    await vi.advanceTimersByTimeAsync(1000);
    expect(state.visible.value).toBe(false);
    expect(state.active.value).toBe(false);
});
it('keeps a just-visible indicator readable without postponing the real result', async () => {
    const state = fixture();
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(250);
    expect(state.visible.value).toBe(true);
    state.active.value = false;
    expect(state.active.value).toBe(false);
    await vi.advanceTimersByTimeAsync(299);
    expect(state.visible.value).toBe(true);
    await vi.advanceTimersByTimeAsync(1);
    expect(state.visible.value).toBe(false);
});
it('coalesces a second activity during the minimum display time', async () => {
    const state = fixture();
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(250);
    state.active.value = false;
    await vi.advanceTimersByTimeAsync(100);
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(500);
    expect(state.visible.value).toBe(true);
    state.active.value = false;
    await vi.advanceTimersByTimeAsync(0);
    expect(state.visible.value).toBe(false);
});
it('immediately clears visible activity and timers when its owner ends', async () => {
    const state = fixture();
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(250);
    scope.stop();
    expect(state.visible.value).toBe(false);
    state.active.value = false;
    state.active.value = true;
    await vi.advanceTimersByTimeAsync(1000);
    expect(state.visible.value).toBe(false);
});
