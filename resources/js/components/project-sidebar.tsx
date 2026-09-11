import { useState } from 'react';
import { Form, Link } from '@inertiajs/react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/ProjectController';
import {
    AddButton,
    dangerButtonClasses,
    iconButtonClasses,
    primaryButtonClasses,
    secondaryButtonClasses,
} from '@/components/buttons';
import { PencilIcon, TrashIcon } from '@/components/icons';
import Modal from '@/components/modal';
import TextField from '@/components/text-field';
import { cn } from '@/lib/utils';
import { show as showProject } from '@/routes/projects';
import type { Project } from '@/types';

type Dialog =
    | { type: 'none' }
    | { type: 'create' }
    | { type: 'edit'; project: Project }
    | { type: 'delete'; project: Project };

export default function ProjectSidebar({
    projects,
    selectedProjectId,
}: {
    projects: Project[];
    selectedProjectId: number | null;
}) {
    const [dialog, setDialog] = useState<Dialog>({ type: 'none' });

    const close = () => setDialog({ type: 'none' });
    const isEditing = dialog.type === 'edit';

    return (
        <aside className="flex w-full shrink-0 flex-col gap-4 border-b border-[#e3e3e0] p-4 lg:w-64 lg:border-r lg:border-b-0 dark:border-[#3E3E3A]">
            <AddButton
                label="Projeto"
                onClick={() => setDialog({ type: 'create' })}
            />

            {projects.length === 0 ? (
                <p className="px-1 text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                    Nenhum projeto ainda. Crie o primeiro.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {projects.map((project) => {
                        const selected = project.id === selectedProjectId;

                        return (
                            <li
                                key={project.id}
                                className={cn(
                                    'flex items-center gap-1 rounded-md pr-2 transition-colors',
                                    selected
                                        ? 'bg-[#f4f4f2] dark:bg-[#161615]'
                                        : 'hover:bg-[#f4f4f2] dark:hover:bg-[#161615]',
                                )}
                            >
                                <Link
                                    href={showProject(project.id)}
                                    aria-current={selected ? 'true' : undefined}
                                    className={cn(
                                        'min-w-0 flex-1 px-2 py-1.5 text-left text-[13px] break-words text-[#1b1b18] dark:text-[#EDEDEC]',
                                        selected && 'font-medium',
                                    )}
                                >
                                    {project.description}
                                </Link>

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
                        );
                    })}
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
                open={dialog.type === 'delete'}
                onClose={close}
                title="Excluir projeto"
            >
                {dialog.type === 'delete' && (
                    <>
                        <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                            Tem certeza que deseja excluir
                            <span className="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                                {` ${dialog.project.description}`}
                            </span>
                            ? As tarefas e os anexos do projeto também serão
                            excluídos. Esta ação não pode ser desfeita.
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
                                        className={dangerButtonClasses}
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
