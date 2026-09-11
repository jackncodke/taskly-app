/**
 * `localStorage` throws rather than returning null when it is unavailable —
 * private mode, or site data blocked — so every access goes through here
 * instead of being guarded again at each call site.
 */
export function readStored(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

/**
 * Returns whether the value could actually be persisted, so a caller can keep
 * the choice in memory for the session when it could not.
 */
export function writeStored(key: string, value: string): boolean {
    try {
        localStorage.setItem(key, value);

        return true;
    } catch {
        return false;
    }
}
