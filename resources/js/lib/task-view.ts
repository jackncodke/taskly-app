import { useSyncExternalStore } from 'react';
import { readStored, writeStored } from '@/lib/storage';

export type TaskView = 'list' | 'board';

const STORAGE_KEY = 'task-view';

const listeners = new Set<() => void>();

/**
 * Holds the choice for this session when it could not be persisted, so the
 * toggle still works where storage is unavailable.
 */
let unpersistedView: TaskView | null = null;

function readView(): TaskView {
    const stored = readStored(STORAGE_KEY);

    if (stored === 'list' || stored === 'board') {
        return stored;
    }

    return unpersistedView ?? 'list';
}

function subscribe(onStoreChange: () => void): () => void {
    // `storage` covers other tabs; `listeners` covers this one, where it does
    // not fire.
    window.addEventListener('storage', onStoreChange);
    listeners.add(onStoreChange);

    return () => {
        window.removeEventListener('storage', onStoreChange);
        listeners.delete(onStoreChange);
    };
}

function setView(view: TaskView): void {
    unpersistedView = writeStored(STORAGE_KEY, view) ? null : view;

    listeners.forEach((listener) => listener());
}

/**
 * Whether tasks are shown as a list or as a board, and how to switch.
 *
 * Persisted because navigating between projects is a full Inertia visit: a
 * preference kept only in component state would snap back to the list every
 * time the user picked another project.
 *
 * `useSyncExternalStore` for the same reason as the theme: the value lives in
 * `localStorage`, which the SSR pass cannot read, so the server renders a
 * defined value and the client corrects it on hydration without a mismatch.
 */
export function useTaskView(): [TaskView, (view: TaskView) => void] {
    const view = useSyncExternalStore(
        subscribe,
        readView,
        () => 'list' as const,
    );

    return [view, setView];
}
