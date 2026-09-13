import { Link } from '@inertiajs/react';
import Panel from '@/components/panel';
import { styleFor } from '@/components/task-card';
import { cn } from '@/lib/utils';
import { show as showProject } from '@/routes/projects';
import type { ProjectOverview, StatusOption } from '@/types';

const mutedClasses = 'text-[#706f6c] dark:text-[#A1A09A]';
const rowClasses = 'border-b border-[#e3e3e0] dark:border-[#3E3E3A]';

/**
 * A column only as wide as what it holds: `w-px` loses to the content's own
 * width, which leaves every spare pixel to the project names — the one column
 * that gets unreadable when it is squeezed.
 */
const countColumnClasses = 'w-px px-2 py-2.5 whitespace-nowrap';

/**
 * How many tasks each project holds at each status.
 *
 * One row per project and one column per status, so a project reads across as
 * a single line and a status reads down as a single column. The columns come
 * from the statuses the server sends, which is the enum itself, so a status
 * added there shows up here without this file knowing about it.
 *
 * The colours are the ones `styleFor` already gives the cards and the board
 * columns, so a status looks the same wherever it is shown.
 */
export default function OverviewPanel({
    projects,
    statuses,
}: {
    projects: ProjectOverview[];
    statuses: StatusOption[];
}) {
    if (projects.length === 0) {
        return (
            <Panel title="Visão Geral">
                <p className={cn('text-[13px]', mutedClasses)}>
                    Nenhum projeto ainda. Crie o primeiro para acompanhar suas
                    tarefas aqui.
                </p>
            </Panel>
        );
    }

    return (
        <Panel title="Visão Geral">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[30rem] border-collapse text-[13px]">
                    <thead>
                        <tr className={rowClasses}>
                            <th
                                scope="col"
                                className={cn(
                                    'px-2 pb-3 text-left font-medium',
                                    mutedClasses,
                                )}
                            >
                                Projeto
                            </th>

                            {statuses.map((status) => (
                                <th
                                    key={status.value}
                                    scope="col"
                                    className={cn(
                                        countColumnClasses,
                                        'pt-0 pb-3 text-center font-medium',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'inline-block rounded-md border px-2 py-1 text-[12px]',
                                            styleFor(status.value).card,
                                        )}
                                    >
                                        {status.label}
                                    </span>
                                </th>
                            ))}

                            <th
                                scope="col"
                                className={cn(
                                    countColumnClasses,
                                    'pt-0 pb-3 text-right font-medium',
                                    mutedClasses,
                                )}
                            >
                                Total
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {projects.map((project) => (
                            <tr
                                key={project.id}
                                className={cn(rowClasses, 'last:border-b-0')}
                            >
                                <th
                                    scope="row"
                                    className="max-w-[18rem] px-2 py-2.5 text-left font-normal break-words"
                                >
                                    <Link
                                        href={showProject(project.id)}
                                        className="hover:underline"
                                    >
                                        {project.description}
                                    </Link>
                                </th>

                                {statuses.map((status) => {
                                    const count =
                                        project.counts[status.value] ?? 0;

                                    return (
                                        <td
                                            key={status.value}
                                            className={cn(
                                                countColumnClasses,
                                                'text-center tabular-nums',
                                                count === 0 && mutedClasses,
                                            )}
                                        >
                                            {count}
                                        </td>
                                    );
                                })}

                                <td
                                    className={cn(
                                        countColumnClasses,
                                        'text-right font-medium tabular-nums',
                                    )}
                                >
                                    {project.total}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Panel>
    );
}
