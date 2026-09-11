import { Form, Head, usePage } from '@inertiajs/react';
import ProjectSidebar from '@/components/project-sidebar';
import { logout } from '@/routes';
import type { Project } from '@/types';

export default function Dashboard({ projects }: { projects: Project[] }) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <>
            <Head title="Painel" />

            <div className="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="flex items-center justify-between border-b border-[#e3e3e0] px-6 py-4 dark:border-[#3E3E3A]">
                    <span className="text-[18px] font-medium tracking-tight">
                        Taskly
                    </span>

                    <Form {...logout.form()}>
                        <button
                            type="submit"
                            className="rounded-md border border-[#e3e3e0] px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#161615]"
                        >
                            Sair
                        </button>
                    </Form>
                </header>

                <div className="flex flex-1 flex-col lg:flex-row">
                    <ProjectSidebar projects={projects} />

                    <main className="flex flex-1 flex-col gap-2 p-6 lg:p-8">
                        <h1 className="text-[20px] font-medium">
                            Olá, {auth.user.name}!
                        </h1>
                        <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                            Você está autenticado como {auth.user.email}.
                        </p>
                    </main>
                </div>
            </div>
        </>
    );
}
