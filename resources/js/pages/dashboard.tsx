import { Form, Head, usePage } from '@inertiajs/react';
import ProjectSidebar from '@/components/project-sidebar';
import TaskList from '@/components/task-list';
import ThemeToggle from '@/components/theme-toggle';
import ViewToggle from '@/components/view-toggle';
import { useTaskView } from '@/lib/task-view';
import { logout } from '@/routes';
import type { Project, StatusOption, Task } from '@/types';

export default function Dashboard({
    projects,
    selectedProject,
    tasks,
    statuses,
    now,
}: {
    projects: Project[];
    selectedProject: Project | null;
    tasks: Task[];
    statuses: StatusOption[];
    now: string;
}) {
    const { auth } = usePage().props;
    const [view, setView] = useTaskView();

    if (!auth.user) {
        return null;
    }

    return (
        <>
            <Head
                title={selectedProject ? selectedProject.description : 'Painel'}
            />

            <div className="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="flex items-center justify-between border-b border-[#e3e3e0] px-6 py-4 dark:border-[#3E3E3A]">
                    <span className="text-[18px] font-medium tracking-tight">
                        Taskly
                    </span>

                    <div className="flex items-center gap-2">
                        {selectedProject ? (
                            <ViewToggle view={view} onChange={setView} />
                        ) : null}

                        <ThemeToggle />

                        <Form {...logout.form()}>
                            <button
                                type="submit"
                                className="rounded-md border border-[#e3e3e0] px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#161615]"
                            >
                                Sair
                            </button>
                        </Form>
                    </div>
                </header>

                <div className="flex flex-1 flex-col lg:flex-row">
                    <ProjectSidebar
                        projects={projects}
                        selectedProjectId={selectedProject?.id ?? null}
                    />

                    <main className="flex min-w-0 flex-1 flex-col gap-2 p-6 lg:p-8">
                        {selectedProject ? (
                            <TaskList
                                project={selectedProject}
                                tasks={tasks}
                                statuses={statuses}
                                view={view}
                                now={now}
                            />
                        ) : (
                            <div className="flex flex-col gap-2">
                                <h1 className="text-[20px] font-medium">
                                    Olá, {auth.user.name}!
                                </h1>
                                <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                                    Selecione um projeto ao lado para ver e
                                    gerenciar suas tarefas.
                                </p>
                            </div>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
