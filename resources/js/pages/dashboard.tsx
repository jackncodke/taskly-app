import { Form, Head, Link, usePage } from '@inertiajs/react';
import AlertsPanel from '@/components/alerts-panel';
import { headerButtonClasses } from '@/components/buttons';
import { HomeIcon } from '@/components/icons';
import OverviewPanel from '@/components/overview-panel';
import ProgressPanel from '@/components/progress-panel';
import ProjectSidebar from '@/components/project-sidebar';
import TaskList from '@/components/task-list';
import TimelinePanel from '@/components/timeline-panel';
import ThemeToggle from '@/components/theme-toggle';
import ViewToggle from '@/components/view-toggle';
import { useTaskView } from '@/lib/task-view';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes';
import type {
    AlertTask,
    Progress,
    Project,
    ProjectOverview,
    StatusOption,
    Task,
    Timeline,
} from '@/types';

export default function Dashboard({
    projects,
    selectedProject,
    tasks,
    statuses,
    overview,
    alerts,
    timeline,
    progress,
    now,
}: {
    projects: Project[];
    selectedProject: Project | null;
    tasks: Task[];
    statuses: StatusOption[];
    overview: ProjectOverview[];
    alerts: AlertTask[];
    timeline: Timeline;
    progress: Progress | null;
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
                            <>
                                <Link
                                    href={dashboard()}
                                    aria-label="Ir para o painel"
                                    title="Ir para o painel"
                                    className={cn(
                                        headerButtonClasses,
                                        'size-[2.1rem]',
                                    )}
                                >
                                    <HomeIcon className="size-5" />
                                </Link>

                                <ViewToggle view={view} onChange={setView} />
                            </>
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
                            <>
                                <div className="flex flex-col gap-2">
                                    <h1 className="text-[20px] font-medium">
                                        Olá, {auth.user.name}!
                                    </h1>
                                    <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                                        Selecione um projeto ao lado para ver e
                                        gerenciar suas tarefas.
                                    </p>
                                </div>

                                <div className="mt-4 flex flex-col gap-6">
                                    {/*
                                        Progress runs the full width at the
                                        top: the bar and the badges want the
                                        room, and how the week has gone reads
                                        before what is overdue in it.

                                        The next two sit side by side from `lg`
                                        up, with the overview taking whatever
                                        the alerts column does not need, and
                                        stack on a narrow screen. The timeline
                                        runs the full width under both.
                                    */}
                                    {progress && (
                                        <ProgressPanel progress={progress} />
                                    )}

                                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,20rem)]">
                                        <OverviewPanel
                                            projects={overview}
                                            statuses={statuses}
                                        />

                                        <AlertsPanel tasks={alerts} now={now} />
                                    </div>

                                    <TimelinePanel
                                        days={timeline.days}
                                        tasks={timeline.tasks}
                                    />
                                </div>
                            </>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
