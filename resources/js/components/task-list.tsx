import { useEffect, useState } from 'react';
import { Form, router } from '@inertiajs/react';
import {
    destroy,
    move,
    reorder,
    updateStatus,
} from '@/actions/App/Http/Controllers/TaskController';
import {
    AddButton,
    dangerButtonClasses,
    iconButtonClasses,
    secondaryButtonClasses,
} from '@/components/buttons';
import { GripIcon } from '@/components/icons';
import Modal from '@/components/modal';
import TaskBoard from '@/components/task-board';
import TaskCard, { styleFor } from '@/components/task-card';
import TaskFormModal from '@/components/task-form-modal';
import type { TaskView } from '@/lib/task-view';
import { cn } from '@/lib/utils';
import type { Project, StatusOption, Task } from '@/types';

type Dialog =
    | { type: 'none' }
    | { type: 'create' }
    | { type: 'edit'; taskId: number }
    | { type: 'delete'; taskId: number };

export default function TaskList({
    project,
    tasks,
    statuses,
    view,
    now,
}: {
    project: Project;
    tasks: Task[];
    statuses: StatusOption[];
    view: TaskView;
    /** Earliest deadline the picker may offer, from the server's clock. */
    now: string;
}) {
    const [dialog, setDialog] = useState<Dialog>({ type: 'none' });

    // The list is held locally so a drop can reorder it immediately, before the
    // server has answered. Whatever comes back from the server then wins.
    const [items, setItems] = useState(tasks);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const [overId, setOverId] = useState<number | null>(null);

    // Only the row whose handle is held may be dragged, so selecting text and
    // clicking links inside a card keep working.
    const [handledId, setHandledId] = useState<number | null>(null);

    useEffect(() => setItems(tasks), [tasks]);

    const close = () => setDialog({ type: 'none' });

    // The task is looked up from props rather than copied into state, so
    // removing an attachment updates the open form straight away.
    const findTask = (id: number) => tasks.find((task) => task.id === id);

    const editing =
        dialog.type === 'edit' ? findTask(dialog.taskId) : undefined;
    const deleting =
        dialog.type === 'delete' ? findTask(dialog.taskId) : undefined;

    const changeStatus = (task: Task, status: string) => {
        // Applied locally first so the dropdown does not snap back to the old
        // value while the request is in flight.
        setItems((current) =>
            current.map((item) =>
                item.id === task.id ? { ...item, status } : item,
            ),
        );

        router.patch(
            updateStatus.url(task.id),
            { status },
            { preserveScroll: true, preserveState: true },
        );
    };

    const clearDragState = () => {
        setDraggingId(null);
        setOverId(null);
        setHandledId(null);
    };

    const moveTask = (from: number, to: number) => {
        if (from < 0 || to < 0 || to >= items.length || from === to) {
            return;
        }

        const reordered = [...items];
        const [moved] = reordered.splice(from, 1);
        reordered.splice(to, 0, moved);

        setItems(reordered);

        router.patch(
            reorder.url(project.id),
            { tasks: reordered.map((task) => task.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const indexOf = (id: number) => items.findIndex((task) => task.id === id);

    /**
     * Move a card on the board: a status change and a reordering at once.
     *
     * The board's columns are this same list filtered by status, so a card is
     * placed by splicing it into the one global order — in front of the card it
     * was dropped on, or after the last card of the target column when it was
     * dropped on empty space. Ordering the whole list by status instead would
     * have the board silently rearrange the list view.
     */
    const moveToStatus = (
        task: Task,
        status: string,
        beforeId: number | null,
    ) => {
        const rest = items.filter((item) => item.id !== task.id);

        const at = () => {
            if (beforeId !== null) {
                const before = rest.findIndex((item) => item.id === beforeId);

                return before < 0 ? rest.length : before;
            }

            // Dropped on empty space: one past the column's last card, or the
            // end of the list when that column has none.
            const last = rest.findLastIndex((item) => item.status === status);

            return last < 0 ? rest.length : last + 1;
        };

        const reordered = [...rest];
        reordered.splice(at(), 0, { ...task, status });

        // Nothing actually moved, so there is nothing to save.
        if (
            task.status === status &&
            reordered.every((item, index) => item.id === items[index].id)
        ) {
            return;
        }

        setItems(reordered);

        router.patch(
            move.url({ project: project.id, task: task.id }),
            { status, tasks: reordered.map((item) => item.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <section className="flex min-w-0 flex-1 flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <h1 className="truncate text-[20px] font-medium">
                        {project.description}
                    </h1>
                    <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                        {items.length === 0
                            ? 'Nenhuma tarefa neste projeto.'
                            : `${items.length} ${items.length === 1 ? 'tarefa' : 'tarefas'}`}
                    </p>
                </div>

                <AddButton
                    label="Tarefa"
                    onClick={() => setDialog({ type: 'create' })}
                    className="sm:w-auto"
                />
            </div>

            {items.length === 0 ? (
                <p className="rounded-md border border-dashed border-[#e3e3e0] p-6 text-center text-[13px] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                    Adicione a primeira tarefa deste projeto.
                </p>
            ) : view === 'board' ? (
                <TaskBoard
                    items={items}
                    statuses={statuses}
                    onEdit={(task) =>
                        setDialog({ type: 'edit', taskId: task.id })
                    }
                    onDelete={(task) =>
                        setDialog({ type: 'delete', taskId: task.id })
                    }
                    onStatusChange={changeStatus}
                    onMove={moveToStatus}
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {items.map((task, index) => (
                        <li
                            key={task.id}
                            draggable={handledId === task.id}
                            onDragStart={(event) => {
                                setDraggingId(task.id);
                                event.dataTransfer.effectAllowed = 'move';
                                // Firefox starts no drag at all without data.
                                event.dataTransfer.setData(
                                    'text/plain',
                                    String(task.id),
                                );
                            }}
                            onDragOver={(event) => {
                                if (
                                    draggingId === null ||
                                    draggingId === task.id
                                ) {
                                    return;
                                }

                                // Without this the drop event never fires.
                                event.preventDefault();
                                event.dataTransfer.dropEffect = 'move';
                                setOverId(task.id);
                            }}
                            onDrop={(event) => {
                                event.preventDefault();

                                if (draggingId !== null) {
                                    moveTask(indexOf(draggingId), index);
                                }

                                clearDragState();
                            }}
                            onDragEnd={clearDragState}
                            className={cn(
                                'flex flex-col gap-2 rounded-lg border p-4 transition-colors',
                                styleFor(task.status).card,
                                draggingId === task.id && 'opacity-50',
                                // Last, so the drop target outline still wins
                                // over whatever colour the status painted.
                                overId === task.id &&
                                    'border-[#1b1b18] dark:border-[#EDEDEC]',
                            )}
                        >
                            <TaskCard
                                task={task}
                                statuses={statuses}
                                onEdit={() =>
                                    setDialog({ type: 'edit', taskId: task.id })
                                }
                                onDelete={() =>
                                    setDialog({
                                        type: 'delete',
                                        taskId: task.id,
                                    })
                                }
                                onStatusChange={(status) =>
                                    changeStatus(task, status)
                                }
                                handle={
                                    <button
                                        type="button"
                                        onMouseDown={() =>
                                            setHandledId(task.id)
                                        }
                                        onMouseUp={() => setHandledId(null)}
                                        onKeyDown={(event) => {
                                            if (
                                                event.key !== 'ArrowUp' &&
                                                event.key !== 'ArrowDown'
                                            ) {
                                                return;
                                            }

                                            // Reordering without a mouse,
                                            // which dragging alone does not
                                            // allow.
                                            event.preventDefault();
                                            moveTask(
                                                index,
                                                event.key === 'ArrowDown'
                                                    ? index + 1
                                                    : index - 1,
                                            );
                                        }}
                                        aria-label={`Reordenar ${task.title}`}
                                        title="Arraste para reordenar, ou use as setas do teclado"
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
                </ul>
            )}

            <TaskFormModal
                open={dialog.type === 'create' || editing !== undefined}
                onClose={close}
                task={editing}
                projectId={project.id}
                now={now}
            />

            <Modal
                open={deleting !== undefined}
                onClose={close}
                title="Excluir tarefa"
            >
                {deleting ? (
                    <>
                        <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                            Tem certeza que deseja excluir
                            <span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                                {` ${deleting.title}`}
                            </span>
                            ? Os anexos da tarefa também serão excluídos. Esta
                            ação não pode ser desfeita.
                        </p>

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={close}
                                className={secondaryButtonClasses}
                            >
                                Cancelar
                            </button>

                            <Form
                                {...destroy.form(deleting.id)}
                                onSuccess={close}
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className={dangerButtonClasses}
                                    >
                                        {processing ? 'Excluindo…' : 'Excluir'}
                                    </button>
                                )}
                            </Form>
                        </div>
                    </>
                ) : null}
            </Modal>
        </section>
    );
}
