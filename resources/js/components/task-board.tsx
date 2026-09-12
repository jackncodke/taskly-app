import { useState } from 'react';
import { iconButtonClasses } from '@/components/buttons';
import { GripIcon } from '@/components/icons';
import TaskCard, { styleFor } from '@/components/task-card';
import { cn } from '@/lib/utils';
import type { StatusOption, Task } from '@/types';

/**
 * The tasks as one column per status, with cards dragged between them.
 *
 * The board does not keep its own copy of the order: the columns are the same
 * single list the list view shows, filtered by status, so the two views always
 * agree. A drop reports where the card landed and the parent works out the
 * resulting order.
 */
export default function TaskBoard({
    items,
    statuses,
    onEdit,
    onDelete,
    onStatusChange,
    onMove,
}: {
    items: Task[];
    statuses: StatusOption[];
    onEdit: (task: Task) => void;
    onDelete: (task: Task) => void;
    onStatusChange: (task: Task, status: string) => void;
    /** `beforeId` is the card to land in front of, or null for the end. */
    onMove: (task: Task, status: string, beforeId: number | null) => void;
}) {
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const [overColumn, setOverColumn] = useState<string | null>(null);
    const [overCardId, setOverCardId] = useState<number | null>(null);

    // Only the card whose handle is held may be dragged, so selecting text and
    // clicking attachment links keep working.
    const [handledId, setHandledId] = useState<number | null>(null);

    const dragging = items.find((task) => task.id === draggingId);

    const clearDragState = () => {
        setDraggingId(null);
        setOverColumn(null);
        setOverCardId(null);
        setHandledId(null);
    };

    const tasksOf = (status: string) =>
        items.filter((task) => task.status === status);

    const drop = (status: string, beforeId: number | null) => {
        // Dropping a card on itself: the column behind it preventDefaults the
        // dragover this card ignored, so the drop still lands here. Placing a
        // card in front of itself means nothing, so treat it as a cancel.
        if (dragging && beforeId !== dragging.id) {
            onMove(dragging, status, beforeId);
        }

        clearDragState();
    };

    /** Reordering within a column without a mouse. */
    const moveByKey = (task: Task, offset: number) => {
        const column = tasksOf(task.status);
        const index = column.findIndex((item) => item.id === task.id);
        const target = index + offset;

        if (target < 0 || target >= column.length) {
            return;
        }

        // Moving down means landing in front of the card *after* the one being
        // passed, which is the end of the column when there is none.
        onMove(
            task,
            task.status,
            offset > 0
                ? (column[target + 1]?.id ?? null)
                : (column[target]?.id ?? null),
        );
    };

    return (
        <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2">
            {statuses.map((status) => {
                const column = tasksOf(status.value);

                return (
                    <section
                        key={status.value}
                        onDragOver={(event) => {
                            if (!dragging) {
                                return;
                            }

                            // Without this the drop event never fires.
                            event.preventDefault();
                            event.dataTransfer.dropEffect = 'move';
                            setOverColumn(status.value);
                        }}
                        onDrop={(event) => {
                            event.preventDefault();
                            drop(status.value, null);
                        }}
                        className={cn(
                            'flex w-[17rem] shrink-0 flex-col gap-3 rounded-lg border p-3 transition-colors',
                            styleFor(status.value).column,
                            // Last, so the drop outline still wins over
                            // whatever colour the status painted.
                            overColumn === status.value &&
                                'border-[#1b1b18] dark:border-[#EDEDEC]',
                        )}
                    >
                        <header className="flex items-center justify-between gap-2">
                            <h2 className="truncate text-[13px] font-medium">
                                {status.label}
                            </h2>

                            {/* Translucent rather than a fixed grey, so it
                                sits on any of the status tints. */}
                            <span className="shrink-0 rounded-full bg-black/5 px-2 py-0.5 text-[12px] text-[#706f6c] dark:bg-white/10 dark:text-[#A1A09A]">
                                {column.length}
                            </span>
                        </header>

                        <ul className="flex min-h-16 flex-col gap-3">
                            {column.map((task) => (
                                <li
                                    key={task.id}
                                    draggable={handledId === task.id}
                                    onDragStart={(event) => {
                                        setDraggingId(task.id);
                                        event.dataTransfer.effectAllowed =
                                            'move';
                                        // Firefox starts no drag without data.
                                        event.dataTransfer.setData(
                                            'text/plain',
                                            String(task.id),
                                        );
                                    }}
                                    onDragOver={(event) => {
                                        if (
                                            !dragging ||
                                            draggingId === task.id
                                        ) {
                                            return;
                                        }

                                        event.preventDefault();
                                        event.dataTransfer.dropEffect = 'move';
                                        setOverColumn(status.value);
                                        setOverCardId(task.id);
                                    }}
                                    onDragLeave={() => setOverCardId(null)}
                                    onDrop={(event) => {
                                        event.preventDefault();
                                        // The column behind this card would
                                        // otherwise drop it at the end.
                                        event.stopPropagation();
                                        drop(status.value, task.id);
                                    }}
                                    onDragEnd={clearDragState}
                                    className={cn(
                                        'flex flex-col gap-2 rounded-lg border bg-white p-3 transition-colors dark:bg-[#0a0a0a]',
                                        styleFor(task.status).card,
                                        draggingId === task.id && 'opacity-50',
                                        // Last, so the drop marker wins over
                                        // whatever colour the status painted.
                                        overCardId === task.id &&
                                            'border-t-4 border-t-[#1b1b18] dark:border-t-[#EDEDEC]',
                                    )}
                                >
                                    <TaskCard
                                        task={task}
                                        statuses={statuses}
                                        onEdit={() => onEdit(task)}
                                        onDelete={() => onDelete(task)}
                                        onStatusChange={(value) =>
                                            onStatusChange(task, value)
                                        }
                                        handle={
                                            <button
                                                type="button"
                                                onMouseDown={() =>
                                                    setHandledId(task.id)
                                                }
                                                onMouseUp={() =>
                                                    setHandledId(null)
                                                }
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key !==
                                                            'ArrowUp' &&
                                                        event.key !==
                                                            'ArrowDown'
                                                    ) {
                                                        return;
                                                    }

                                                    event.preventDefault();
                                                    moveByKey(
                                                        task,
                                                        event.key ===
                                                            'ArrowDown'
                                                            ? 1
                                                            : -1,
                                                    );
                                                }}
                                                aria-label={`Reordenar ${task.title}`}
                                                title="Arraste para mover entre colunas, ou use as setas para reordenar"
                                                className={cn(
                                                    iconButtonClasses,
                                                    'cursor-grab touch-none active:cursor-grabbing',
                                                )}
                                            >
                                                <GripIcon className="size-4" />
                                            </button>
                                        }
                                    />
                                </li>
                            ))}

                            {column.length === 0 ? (
                                <li className="rounded-md border border-dashed border-black/15 p-4 text-center text-[12px] text-[#706f6c] dark:border-white/15 dark:text-[#A1A09A]">
                                    Arraste uma tarefa para cá.
                                </li>
                            ) : null}
                        </ul>
                    </section>
                );
            })}
        </div>
    );
}
