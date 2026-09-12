import type { ReactNode } from 'react';
import { iconButtonClasses } from '@/components/buttons';
import {
    ClockIcon,
    CloseIcon,
    PaperclipIcon,
    PencilIcon,
    TrashIcon,
} from '@/components/icons';
import TagChip from '@/components/tag-chip';
import { cn } from '@/lib/utils';
import type { StatusOption, Task, TaskAttachment } from '@/types';

export function formatBytes(bytes: number): string {
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

export function AttachmentChip({
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
 * Purely cosmetic tint per status, applied to the whole card, to its dropdown
 * and to the board column that collects them. Kept in one place so the three
 * never disagree about a colour.
 *
 * The tints are pale on purpose: the card still has to read as a task rather
 * than as a warning, and the text on top of it keeps its normal contrast.
 *
 * `column` is one step deeper than `card` in light mode and one step lighter in
 * dark mode, which in both cases leaves the cards sitting *on* the column
 * rather than dissolving into it — the same relationship the neutral pair had
 * before any status had a colour.
 */
type StatusStyle = { card: string; select: string; column: string };

const neutralStatusStyle: StatusStyle = {
    card: 'border-[#e3e3e0] dark:border-[#3E3E3A]',
    select: 'border-[#e3e3e0] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]',
    column: 'border-[#e3e3e0] bg-[#f7f7f5] dark:border-[#3E3E3A] dark:bg-[#141413]',
};

const statusStyles: Record<string, StatusStyle> = {
    in_progress: {
        card: 'border-blue-300 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40',
        select: 'border-blue-300 text-blue-800 dark:border-blue-800 dark:text-blue-300',
        column: 'border-blue-300 bg-blue-100/70 dark:border-blue-900 dark:bg-blue-950/25',
    },
    completed: {
        card: 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/40',
        select: 'border-green-300 text-green-800 dark:border-green-800 dark:text-green-300',
        column: 'border-green-300 bg-green-100/70 dark:border-green-900 dark:bg-green-950/25',
    },
    cancelled: {
        card: 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
        select: 'border-red-300 text-red-800 dark:border-red-800 dark:text-red-300',
        column: 'border-red-300 bg-red-100/70 dark:border-red-900 dark:bg-red-950/25',
    },
};

/**
 * An unknown status falls back to the neutral look, so a case added to the
 * enum renders sensibly before this map knows about it.
 */
export function styleFor(status: string): StatusStyle {
    return statusStyles[status] ?? neutralStatusStyle;
}

/**
 * Everything inside a task card, shared by the list and the board so the two
 * views cannot drift into showing different things about the same task.
 *
 * The wrapper element stays with each view, which owns its own drag wiring,
 * and passes its drag handle in through `handle`.
 *
 * The status dropdown is kept on the board even though the column already says
 * the status: dragging is the mouse-only way to move a card, and this is the
 * one that works from the keyboard.
 */
export default function TaskCard({
    task,
    statuses,
    handle,
    onEdit,
    onDelete,
    onStatusChange,
}: {
    task: Task;
    statuses: StatusOption[];
    handle: ReactNode;
    onEdit: () => void;
    onDelete: () => void;
    onStatusChange: (status: string) => void;
}) {
    return (
        <>
            <div className="flex items-start gap-2">
                {handle}

                <h2 className="min-w-0 flex-1 text-[15px] font-medium break-words">
                    {task.title}
                </h2>

                <button
                    type="button"
                    onClick={onEdit}
                    aria-label={`Editar ${task.title}`}
                    title="Editar"
                    className={iconButtonClasses}
                >
                    <PencilIcon className="size-4" />
                </button>

                <button
                    type="button"
                    onClick={onDelete}
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
                        <li key={attachment.id} className="min-w-0">
                            <AttachmentChip attachment={attachment} />
                        </li>
                    ))}
                </ul>
            ) : null}

            <div className="flex justify-end">
                <label htmlFor={`status-${task.id}`} className="sr-only">
                    Status de {task.title}
                </label>

                <select
                    id={`status-${task.id}`}
                    value={task.status}
                    onChange={(event) => onStatusChange(event.target.value)}
                    className={cn(
                        'rounded-md border bg-white px-2 py-1 text-[12px] font-medium outline-none',
                        'focus:border-[#1b1b18] focus:ring-2 focus:ring-[#1b1b18]/10',
                        'dark:bg-[#161615] dark:focus:border-[#EDEDEC]',
                        styleFor(task.status).select,
                    )}
                >
                    {statuses.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>
        </>
    );
}
