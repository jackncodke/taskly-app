import Panel from '@/components/panel';
import { styleFor } from '@/components/task-card';
import { cn } from '@/lib/utils';
import type { TimelineDay, TimelineTask } from '@/types';

const mutedClasses = 'text-[#706f6c] dark:text-[#A1A09A]';

/** The three columns, repeated by the axis and by every row so they line up. */
const labelColumnClasses = 'w-36 shrink-0 sm:w-44 lg:w-56';
const dateColumnClasses = 'w-28 shrink-0 whitespace-nowrap';

/**
 * The deadlines of the week ahead, drawn against a day axis.
 *
 * One row per task, a dot where its deadline falls, and a leader from the
 * start of the window to that dot so the eye can travel from the name to the
 * mark. The dot carries the status colour the rest of the interface uses, and
 * every row also spells its status out: the colour repeats what the text says
 * rather than being the only place it is said.
 *
 * Nothing here is computed from the browser's clock. The server sends each
 * offset already worked out against its own, which is the clock the deadlines
 * were written and are enforced against.
 */
export default function TimelinePanel({
    days,
    tasks,
}: {
    days: TimelineDay[];
    tasks: TimelineTask[];
}) {
    if (tasks.length === 0) {
        return (
            <Panel title="Timeline">
                <p className={cn('text-[13px]', mutedClasses)}>
                    Nenhuma tarefa a vencer nos próximos 7 dias.
                </p>
            </Panel>
        );
    }

    return (
        <Panel title="Timeline">
            <p className={cn('-mt-2 text-[13px]', mutedClasses)}>
                Tarefas a vencer nos próximos 7 dias.
            </p>

            <div className="overflow-x-auto">
                <div className="min-w-[40rem]">
                    <div className="flex items-end gap-3 border-b border-[#e3e3e0] pb-2 dark:border-[#3E3E3A]">
                        <div className={labelColumnClasses} />

                        <div className="relative h-7 flex-1">
                            {days.map((day) => (
                                <span
                                    key={day.offset}
                                    className={cn(
                                        'absolute bottom-0 flex flex-col pl-1.5 text-[11px] leading-tight',
                                        mutedClasses,
                                    )}
                                    style={{ left: `${day.offset}%` }}
                                >
                                    <span>{day.label}</span>
                                    <span className="tabular-nums">
                                        {day.date_label}
                                    </span>
                                </span>
                            ))}
                        </div>

                        <div className={dateColumnClasses} />
                    </div>

                    {/*
                        The rows touch, so the day rules drawn inside each plot
                        cell meet across the whole chart instead of breaking at
                        every gap. The breathing room is the padding inside the
                        columns either side.
                    */}
                    <ul className="max-h-80 overflow-y-auto">
                        {tasks.map((task) => {
                            const mark = styleFor(task.status).mark;

                            return (
                                <li
                                    key={task.id}
                                    className="flex items-stretch gap-3"
                                >
                                    <div
                                        className={cn(
                                            labelColumnClasses,
                                            'py-2.5',
                                        )}
                                    >
                                        <p
                                            className="truncate text-[13px]"
                                            title={
                                                task.short_description ??
                                                undefined
                                            }
                                        >
                                            {task.short_description}
                                        </p>
                                        <p
                                            className={cn(
                                                'text-[12px]',
                                                mutedClasses,
                                            )}
                                        >
                                            {task.status_label}
                                        </p>
                                    </div>

                                    <div className="relative flex-1 self-stretch">
                                        {days.map((day) => (
                                            <span
                                                key={day.offset}
                                                aria-hidden="true"
                                                className="absolute inset-y-0 w-px bg-[#e3e3e0] dark:bg-[#3E3E3A]"
                                                style={{
                                                    left: `${day.offset}%`,
                                                }}
                                            />
                                        ))}

                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'absolute top-1/2 left-0 h-0.5 -translate-y-1/2 rounded-full opacity-30',
                                                mark,
                                            )}
                                            style={{
                                                width: `${task.offset}%`,
                                            }}
                                        />

                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                // The ring is the panel's own
                                                // surface, so a dot sitting on
                                                // a day rule still reads as a
                                                // round mark.
                                                'absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white dark:ring-[#161615]',
                                                mark,
                                            )}
                                            style={{
                                                left: `${task.offset}%`,
                                            }}
                                        />
                                    </div>

                                    <div
                                        className={cn(
                                            dateColumnClasses,
                                            'py-2.5 text-right text-[12px] tabular-nums',
                                            mutedClasses,
                                        )}
                                    >
                                        {task.due_at_label}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </div>
        </Panel>
    );
}
