import { useEffect, useState } from 'react';
import { Form, router } from '@inertiajs/react';
import { destroy as destroyAttachment } from '@/actions/App/Http/Controllers/TaskAttachmentController';
import {
    destroy,
    reorder,
    store,
    update,
    updateStatus,
} from '@/actions/App/Http/Controllers/TaskController';
import {
    AddButton,
    dangerButtonClasses,
    iconButtonClasses,
    primaryButtonClasses,
    secondaryButtonClasses,
} from '@/components/buttons';
import {
    ClockIcon,
    CloseIcon,
    GripIcon,
    PaperclipIcon,
    PencilIcon,
    TrashIcon,
} from '@/components/icons';
import Modal from '@/components/modal';
import TagChip from '@/components/tag-chip';
import TextField from '@/components/text-field';
import TextareaField from '@/components/textarea-field';
import { cn } from '@/lib/utils';
import type { Project, StatusOption, Task, TaskAttachment } from '@/types';

type Dialog =
    | { type: 'none' }
    | { type: 'create' }
    | { type: 'edit'; taskId: number }
    | { type: 'delete'; taskId: number };

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(value < 10 ? 1 : 0)} ${units[unit]}`;
}

/**
 * Validation for a file list arrives keyed by position — `attachments.0`,
 * `attachments.2` — so there is no single key to read. The first message is
 * enough to tell the user what went wrong.
 */
function firstErrorFor(
    errors: Record<string, string>,
    field: string,
): string | undefined {
    return Object.entries(errors).find(
        ([key]) => key === field || key.startsWith(`${field}.`),
    )?.[1];
}

function AttachmentChip({
    attachment,
    onRemove,
}: {
    attachment: TaskAttachment;
    onRemove?: () => void;
}) {
    return (
        <span className="inline-flex max-w-full items-center gap-1.5 rounded-md border border-[#e3e3e0] bg-white py-1 pr-1 pl-2 text-[12px] dark:border-[#3E3E3A] dark:bg-[#161615]">
            {attachment.is_image ? (
                <img
                    src={attachment.url}
                    alt=""
                    className="size-6 shrink-0 rounded object-cover"
                />
            ) : (
                <PaperclipIcon className="size-3.5 shrink-0 text-[#706f6c] dark:text-[#A1A09A]" />
            )}

            <a
                href={attachment.url}
                target="_blank"
                rel="noopener noreferrer"
                className="min-w-0 truncate text-[#1b1b18] hover:underline dark:text-[#EDEDEC]"
                title={`${attachment.name} (${formatBytes(attachment.size)})`}
            >
                {attachment.name}
            </a>

            {onRemove ? (
                <button
                    type="button"
                    onClick={onRemove}
                    aria-label={`Remover anexo ${attachment.name}`}
                    title="Remover anexo"
                    className={iconButtonClasses}
                >
                    <CloseIcon className="size-3.5" />
                </button>
            ) : null}
        </span>
    );
}

/**
 * Purely cosmetic tint per status, applied to the whole card and to its
 * dropdown. Kept in one place so the two never disagree about a colour.
 *
 * The tints are pale on purpose: the card still has to read as a task rather
 * than as a warning, and the text on top of it keeps its normal contrast.
 */
type StatusStyle = { card: string; select: string };

const neutralStatusStyle: StatusStyle = {
    card: 'border-[#e3e3e0] dark:border-[#3E3E3A]',
    select: 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]',
};

const statusStyles: Record<string, StatusStyle> = {
    in_progress: {
        card: 'border-blue-300 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40',
        select: 'border-blue-300 text-blue-800 dark:border-blue-800 dark:text-blue-300',
    },
    completed: {
        card: 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/40',
        select: 'border-green-300 text-green-800 dark:border-green-800 dark:text-green-300',
    },
    cancelled: {
        card: 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
        select: 'border-red-300 text-red-800 dark:border-red-800 dark:text-red-300',
    },
};

/**
 * An unknown status falls back to the neutral look, so a case added to the
 * enum renders sensibly before this map knows about it.
 */
function styleFor(status: string): StatusStyle {
    return statusStyles[status] ?? neutralStatusStyle;
}

export default function TaskList({
    project,
    tasks,
    statuses,
}: {
    project: Project;
    tasks: Task[];
    statuses: StatusOption[];
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

    const editing = dialog.type === 'edit' ? findTask(dialog.taskId) : undefined;
    const deleting =
        dialog.type === 'delete' ? findTask(dialog.taskId) : undefined;
    const isEditing = editing !== undefined;

    const removeAttachment = (attachment: TaskAttachment) => {
        router.delete(destroyAttachment.url(attachment.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

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
                            <div className="flex items-start gap-2">
                                <button
                                    type="button"
                                    onMouseDown={() => setHandledId(task.id)}
                                    onMouseUp={() => setHandledId(null)}
                                    onKeyDown={(event) => {
                                        if (
                                            event.key !== 'ArrowUp' &&
                                            event.key !== 'ArrowDown'
                                        ) {
                                            return;
                                        }

                                        // Reordering without a mouse, which
                                        // dragging alone does not allow.
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

                                <h2 className="min-w-0 flex-1 text-[15px] font-medium break-words">
                                    {task.title}
                                </h2>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setDialog({
                                            type: 'edit',
                                            taskId: task.id,
                                        })
                                    }
                                    aria-label={`Editar ${task.title}`}
                                    title="Editar"
                                    className={iconButtonClasses}
                                >
                                    <PencilIcon className="size-4" />
                                </button>

                                <button
                                    type="button"
                                    onClick={() =>
                                        setDialog({
                                            type: 'delete',
                                            taskId: task.id,
                                        })
                                    }
                                    aria-label={`Excluir ${task.title}`}
                                    title="Excluir"
                                    className={iconButtonClasses}
                                >
                                    <TrashIcon className="size-4" />
                                </button>
                            </div>

                            {task.short_description ? (
                                <p className="text-[13px] break-words text-[#706f6c] dark:text-[#A1A09A]">
                                    {task.short_description}
                                </p>
                            ) : null}

                            {task.description ? (
                                <details className="text-[13px]">
                                    <summary className="cursor-pointer text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]">
                                        Descrição completa
                                    </summary>
                                    <p className="mt-2 whitespace-pre-wrap text-[#1b1b18] dark:text-[#EDEDEC]">
                                        {task.description}
                                    </p>
                                </details>
                            ) : null}

                            {task.due_at_label ? (
                                <p className="flex items-center gap-1.5 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">
                                    <ClockIcon className="size-3.5" />
                                    Prazo: {task.due_at_label}
                                </p>
                            ) : null}

                            {task.tags.length > 0 ? (
                                <ul className="flex flex-wrap gap-1.5">
                                    {task.tags.map((tag) => (
                                        <li key={tag}>
                                            <TagChip tag={tag} />
                                        </li>
                                    ))}
                                </ul>
                            ) : null}

                            {task.attachments.length > 0 ? (
                                <ul className="flex flex-wrap gap-2">
                                    {task.attachments.map((attachment) => (
                                        <li
                                            key={attachment.id}
                                            className="min-w-0"
                                        >
                                            <AttachmentChip
                                                attachment={attachment}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : null}

                            <div className="flex justify-end">
                                <label
                                    htmlFor={`status-${task.id}`}
                                    className="sr-only"
                                >
                                    Status de {task.title}
                                </label>

                                <select
                                    id={`status-${task.id}`}
                                    value={task.status}
                                    onChange={(event) =>
                                        changeStatus(task, event.target.value)
                                    }
                                    className={cn(
                                        'rounded-md border bg-white px-2 py-1 text-[12px] font-medium outline-none',
                                        'focus:border-[#1b1b18] focus:ring-2 focus:ring-[#1b1b18]/10',
                                        'dark:bg-[#161615] dark:focus:border-[#EDEDEC]',
                                        styleFor(task.status).select,
                                    )}
                                >
                                    {statuses.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Modal
                open={dialog.type === 'create' || isEditing}
                onClose={close}
                title={isEditing ? 'Editar tarefa' : 'Nova tarefa'}
                size="lg"
            >
                <Form
                    // Remounting on a changed target lets the fields pick up the
                    // right defaults instead of keeping the previous values.
                    key={isEditing ? `edit-${editing.id}` : 'create'}
                    {...(isEditing
                        ? update.form(editing.id)
                        : store.form(project.id))}
                    resetOnSuccess
                    onSuccess={close}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                label="Título"
                                name="title"
                                type="text"
                                placeholder="O que precisa ser feito"
                                defaultValue={editing?.title ?? ''}
                                required
                                autoFocus
                                error={errors.title}
                            />

                            <TextField
                                label="Descrição curta"
                                name="short_description"
                                type="text"
                                placeholder="Um resumo de uma linha"
                                defaultValue={editing?.short_description ?? ''}
                                error={errors.short_description}
                            />

                            <TextareaField
                                label="Descrição completa"
                                name="description"
                                rows={4}
                                placeholder="Detalhes, critérios de aceite, links…"
                                defaultValue={editing?.description ?? ''}
                                error={errors.description}
                            />

                            <TextField
                                label="Prazo"
                                name="due_at"
                                type="datetime-local"
                                defaultValue={editing?.due_at ?? ''}
                                error={errors.due_at}
                            />

                            <TextField
                                label="Tags"
                                name="tags"
                                type="text"
                                placeholder="urgente, backend, cliente"
                                defaultValue={editing?.tags.join(', ') ?? ''}
                                error={firstErrorFor(errors, 'tags')}
                            />

                            {isEditing && editing.attachments.length > 0 ? (
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-[13px] font-medium">
                                        Anexos atuais
                                    </span>
                                    <ul className="flex flex-wrap gap-2">
                                        {editing.attachments.map(
                                            (attachment) => (
                                                <li
                                                    key={attachment.id}
                                                    className="min-w-0"
                                                >
                                                    <AttachmentChip
                                                        attachment={attachment}
                                                        onRemove={() =>
                                                            removeAttachment(
                                                                attachment,
                                                            )
                                                        }
                                                    />
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}

                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="attachments"
                                    className="text-[13px] font-medium"
                                >
                                    {isEditing
                                        ? 'Adicionar anexos ou fotos'
                                        : 'Anexos e fotos'}
                                </label>

                                <input
                                    id="attachments"
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                                    className="w-full rounded-md border border-[#e3e3e0] bg-white p-2 text-[13px] file:mr-3 file:rounded file:border-0 file:bg-[#f4f4f2] file:px-3 file:py-1.5 file:text-[13px] file:font-medium dark:border-[#3E3E3A] dark:bg-[#161615] dark:file:bg-[#2a2a28] dark:file:text-[#EDEDEC]"
                                />

                                <span className="text-[12px] text-[#706f6c] dark:text-[#A1A09A]">
                                    Até 10 arquivos, 10 MB cada.
                                </span>

                                {firstErrorFor(errors, 'attachments') ? (
                                    <span className="text-[13px] text-red-600">
                                        {firstErrorFor(errors, 'attachments')}
                                    </span>
                                ) : null}
                            </div>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={close}
                                    className={secondaryButtonClasses}
                                >
                                    Cancelar
                                </button>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className={primaryButtonClasses}
                                >
                                    {processing ? 'Salvando…' : 'Salvar'}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </Modal>

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
