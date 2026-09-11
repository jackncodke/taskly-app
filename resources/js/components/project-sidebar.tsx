import { useState } from 'react';
import { Form } from '@inertiajs/react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/ProjectController';
import { PencilIcon, TrashIcon } from '@/components/icons';
import Modal from '@/components/modal';
import TextField from '@/components/text-field';
import type { Project } from '@/types';

type Dialog =
    | { type: 'none' }
    | { type: 'create' }
    | { type: 'edit'; project: Project }
    | { type: 'delete'; project: Project };

const iconButtonClasses =
    'rounded p-1 text-[#706f6c] transition-colors hover:bg-[#e9e9e6] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:bg-[#2a2a28] dark:hover:text-[#EDEDEC]';

const secondaryButtonClasses =
    'rounded-md border border-[#e3e3e0] px-4 py-2 text-[14px] font-medium transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#0a0a0a]';

export default function ProjectSidebar({ projects }: { projects: Project[] }) {
    const [dialog, setDialog] = useState<Dialog>({ type: 'none' });

    const close = () => setDialog({ type: 'none' });
    const isEditing = dialog.type === 'edit';

    return (
        <aside className="flex w-full shrink-0 flex-col gap-4 border-b border-[#e3e3e0] p-4 lg:w-64 lg:border-r lg:border-b-0 dark:border-[#3E3E3A]">
            <button
                type="button"
                onClick={() => setDialog({ type: 'create' })}
                className="flex w-full items-center justify-center gap-2 rounded-md bg-[#1b1b18] px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 dark:bg-[#EDEDEC] dark:text-[#1b1b18]"
            >
                <span aria-hidden="true" className="text-[16px] leading-none">
                    +
                </span>
                Projeto
            </button>

            {projects.length === 0 ? (
                <p className="px-1 text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                    Nenhum projeto ainda. Crie o primeiro.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {projects.map((project) => (
                        <li
                            key={project.id}
                            className="flex items-center gap-1 rounded-md px-2 py-1.5 transition-colors hover:bg-[#f4f4f2] dark:hover:bg-[#161615]"
                        >
                            <span className="min-w-0 flex-1 text-[13px] break-words text-[#1b1b18] dark:text-[#EDEDEC]">
                                {project.description}
                            </span>

                            <button
                                type="button"
                                onClick={() =>
                                    setDialog({ type: 'edit', project })
                                }
                                aria-label={`Editar ${project.description}`}
                                title="Editar"
                                className={iconButtonClasses}
                            >
                                <PencilIcon className="size-4" />
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setDialog({ type: 'delete', project })
                                }
                                aria-label={`Excluir ${project.description}`}
                                title="Excluir"
                                className={iconButtonClasses}
                            >
                                <TrashIcon className="size-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <Modal
                open={dialog.type === 'create' || isEditing}
                onClose={close}
                title={isEditing ? 'Editar projeto' : 'Novo projeto'}
            >
                <Form
                    // Remounting on a changed target lets the field pick up the
                    // right default instead of keeping the previous value.
                    key={isEditing ? `edit-${dialog.project.id}` : 'create'}
                    {...(isEditing
                        ? update.form(dialog.project.id)
                        : store.form())}
                    resetOnSuccess
                    onSuccess={close}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <TextField
                                label="Descrição"
                                name="description"
                                type="text"
                                placeholder="Descreva o projeto"
                                defaultValue={
                                    isEditing ? dialog.project.description : ''
                                }
                                required
                                autoFocus
                                error={errors.description}
                            />

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
                                    className="rounded-md bg-[#1b1b18] px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60 dark:bg-[#EDEDEC] dark:text-[#1b1b18]"
                                >
                                    {processing ? 'Salvando…' : 'Salvar'}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </Modal>

            <Modal
                open={dialog.type === 'delete'}
                onClose={close}
                title="Excluir projeto"
            >
                {dialog.type === 'delete' && (
                    <>
                        <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                            Tem certeza que deseja excluir{' '}
                            <span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                                {dialog.project.description}
                            </span>
                            ? Esta ação não pode ser desfeita.
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
                                {...destroy.form(dialog.project.id)}
                                onSuccess={close}
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-md bg-red-600 px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60"
                                    >
                                        {processing ? 'Excluindo…' : 'Excluir'}
                                    </button>
                                )}
                            </Form>
                        </div>
                    </>
                )}
            </Modal>
        </aside>
    );
}
