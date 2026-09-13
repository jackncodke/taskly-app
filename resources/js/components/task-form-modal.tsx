import { Form, router } from '@inertiajs/react';
import { destroy as destroyAttachment } from '@/actions/App/Http/Controllers/TaskAttachmentController';
import { store, update } from '@/actions/App/Http/Controllers/TaskController';
import {
    primaryButtonClasses,
    secondaryButtonClasses,
} from '@/components/buttons';
import Modal from '@/components/modal';
import { AttachmentChip } from '@/components/task-card';
import TextField from '@/components/text-field';
import TextareaField from '@/components/textarea-field';
import type { Task, TaskAttachment } from '@/types';

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

/**
 * The one form a task is written with, whether it is being created or edited.
 *
 * It lives on its own because more than the task list opens it: the dashboard
 * panels do too, and a second copy of these fields would be a second place for
 * the form to drift away from what the server accepts.
 *
 * `task` is read from props on every render rather than copied into state, so
 * removing an attachment updates the open form straight away.
 */
export default function TaskFormModal({
    open,
    onClose,
    task,
    projectId,
    now,
}: {
    open: boolean;
    onClose: () => void;
    /** The task being edited, or nothing at all when creating one. */
    task?: Task;
    /** The project the task belongs to, or will be created in. */
    projectId: number;
    /** Earliest deadline the picker may offer, from the server's clock. */
    now: string;
}) {
    const isEditing = task !== undefined;

    const removeAttachment = (attachment: TaskAttachment) => {
        router.delete(destroyAttachment.url(attachment.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEditing ? 'Editar tarefa' : 'Nova tarefa'}
            size="lg"
        >
            <Form
                // Remounting on a changed target lets the fields pick up the
                // right defaults instead of keeping the previous values.
                key={isEditing ? `edit-${task.id}` : 'create'}
                {...(isEditing ? update.form(task.id) : store.form(projectId))}
                resetOnSuccess
                onSuccess={onClose}
                className="flex flex-col gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <TextField
                            label="Título"
                            name="title"
                            type="text"
                            placeholder="O que precisa ser feito"
                            defaultValue={task?.title ?? ''}
                            required
                            autoFocus
                            error={errors.title}
                        />

                        <TextField
                            label="Descrição curta"
                            name="short_description"
                            type="text"
                            placeholder="Um resumo de uma linha"
                            defaultValue={task?.short_description ?? ''}
                            required
                            error={errors.short_description}
                        />

                        <TextareaField
                            label="Descrição completa"
                            name="description"
                            rows={4}
                            placeholder="Detalhes, critérios de aceite, links…"
                            defaultValue={task?.description ?? ''}
                            required
                            error={errors.description}
                        />

                        <TextField
                            label="Prazo"
                            name="due_at"
                            type="datetime-local"
                            // A deadline a task already has may be older than
                            // `now`, and keeping it is allowed, so the picker
                            // must not refuse to show it back.
                            min={
                                task?.due_at && task.due_at < now
                                    ? task.due_at
                                    : now
                            }
                            defaultValue={task?.due_at ?? ''}
                            required
                            error={errors.due_at}
                        />

                        <TextField
                            label="Tags"
                            name="tags"
                            type="text"
                            placeholder="urgente, backend, cliente"
                            defaultValue={task?.tags.join(', ') ?? ''}
                            error={firstErrorFor(errors, 'tags')}
                        />

                        {isEditing && task.attachments.length > 0 ? (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-[13px] font-medium">
                                    Anexos atuais
                                </span>
                                <ul className="flex flex-wrap gap-2">
                                    {task.attachments.map((attachment) => (
                                        <li
                                            key={attachment.id}
                                            className="min-w-0"
                                        >
                                            <AttachmentChip
                                                attachment={attachment}
                                                onRemove={() =>
                                                    removeAttachment(attachment)
                                                }
                                            />
                                        </li>
                                    ))}
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
                                onClick={onClose}
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
    );
}
