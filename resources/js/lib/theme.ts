import { useEffect, useSyncExternalStore } from 'react';
import { readStored, writeStored } from '@/lib/storage';

export type Theme = 'light' | 'dark';

/**
 * Shared with the inline script in `resources/views/app.blade.php`, which
 * applies the theme before first paint to avoid a flash of the wrong one.
 */
const STORAGE_KEY = 'theme';

const listeners = new Set<() => void>();

/**
 * Holds the choice for this session when it could not be persisted, so the
 * toggle still works where storage is unavailable. Stays null otherwise, which
 * leaves storage authoritative and lets other tabs drive this one.
 */
let unpersistedTheme: Theme | null = null;

function systemTheme(): Theme {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function readStoredTheme(): Theme | null {
    const stored = readStored(STORAGE_KEY);

    return stored === 'dark' || stored === 'light' ? stored : null;
}

/**
 * The theme in effect: the user's stored choice when there is one, otherwise
 * whatever the operating system asks for.
 */
function resolveTheme(): Theme {
    return readStoredTheme() ?? unpersistedTheme ?? systemTheme();
}

function applyTheme(theme: Theme): void {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

function subscribe(onStoreChange: () => void): () => void {
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    // `storage` covers other tabs, `media` covers the OS switching on us, and
    // `listeners` covers this tab — `storage` does not fire where it was set.
    media.addEventListener('change', onStoreChange);
    window.addEventListener('storage', onStoreChange);
    listeners.add(onStoreChange);

    return () => {
        media.removeEventListener('change', onStoreChange);
        window.removeEventListener('storage', onStoreChange);
        listeners.delete(onStoreChange);
    };
}

export function setTheme(theme: Theme): void {
    // A failed write only costs persistence across reloads: the theme still
    // applies for this session through `unpersistedTheme`.
    unpersistedTheme = writeStored(STORAGE_KEY, theme) ? null : theme;

    applyTheme(theme);
    listeners.forEach((listener) => listener());
}

/**
 * Reads the active theme and lets the caller change it.
 *
 * `useSyncExternalStore` rather than `useState` because the theme lives in the
 * DOM and `localStorage`, which the SSR pass cannot see: it lets the server
 * render a defined value and the client correct it on hydration without a
 * mismatch warning.
 */
export function useTheme(): [Theme, (theme: Theme) => void] {
    const theme = useSyncExternalStore(
        subscribe,
        resolveTheme,
        () => 'light' as const,
    );

    // Keeps the <html> class in step when the OS preference changes and the
    // user has not made an explicit choice.
    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

    return [theme, setTheme];
}
