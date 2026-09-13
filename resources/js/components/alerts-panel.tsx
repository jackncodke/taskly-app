import { useState } from 'react';
import { ClockIcon } from '@/components/icons';
import Panel from '@/components/panel';
import TaskFormModal from '@/components/task-form-modal';
import type { AlertTask } from '@/types';

/**
 * The tasks whose deadline has already passed and that nobody has closed.
 *
 * Only the short description and the deadline are listed: the panel is a
 * nudge, and the whole task is one click away in the same form the task list
 * edits with.
 *
 * The red is the one the cancelled status already uses, which is the only red
 * the interface has — here it means late rather than cancelled, and nothing on
 * this panel is a status, so the two cannot be read as the same label.
 */
export default function AlertsPanel({
    tasks,
    now,
}: {
    tasks: AlertTask[];
    /** Earliest deadline the picker may offer, from the server's clock. */
    now: string;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    // Looked up from props rather than copied into state, so a task edited
    // into the future — and therefore out of this list — closes the form
    // instead of leaving it open on something that is no longer an alert.
    const editing = tasks.find((task) => task.id === editingId);

    return (
        <Panel title="Alertas">
            {tasks.length === 0 ? (
                <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                    Nenhuma tarefa vencida.
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {tasks.map((task) => (
                        <li key={task.id}>
                            <button
                                type="button"
                                onClick={() => setEditingId(task.id)}
                                className="flex w-full flex-col gap-1 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-left transition-colors hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:hover:bg-red-950/70"
                            >
                                <span className="text-[13px] font-medium break-words text-red-900 dark:text-red-200">
                                    {task.short_description}
                                </span>

                                <span className="text-[12px] text-red-700 italic dark:text-red-300">
                                    {task.status_label}
                                </span>

                                <span className="flex items-center gap-1.5 text-[12px] text-red-700 dark:text-red-300">
                                    <ClockIcon className="size-3.5 shrink-0" />
                                    {task.due_at_label}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {/*
                Mounted only with a task in hand: this panel never creates one,
                and the form needs the project of whichever task was clicked
                rather than a project the page has selected.
            */}
            {editing ? (
                <TaskFormModal
                    open
                    onClose={() => setEditingId(null)}
                    task={editing}
                    projectId={editing.project_id}
                    now={now}
                />
            ) : null}
        </Panel>
    );
}
