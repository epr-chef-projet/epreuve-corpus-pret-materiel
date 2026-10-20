export function initializeTheme(): () => void {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const update = () =>
        document.documentElement.classList.toggle('dark', media.matches);
    update();
    media.addEventListener('change', update);
    try {
        localStorage.removeItem('appearance');
    } catch {
        /* A denied preference store does not prevent rendering. */
    }
    document.cookie = 'appearance=;path=/;max-age=0;SameSite=Lax';
    return () => media.removeEventListener('change', update);
}
