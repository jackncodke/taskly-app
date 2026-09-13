import type { ReactNode } from 'react';

/**
 * The frame every dashboard panel sits in.
 *
 * The dashboard is meant to collect several of these, so the heading, the
 * border and the padding live here instead of in each panel: a new panel only
 * has to bring its own content.
 */
export default function Panel({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-4 rounded-lg border border-[#e3e3e0] bg-white p-4 lg:p-5 dark:border-[#3E3E3A] dark:bg-[#161615]">
            <h2 className="text-[15px] font-medium">{title}</h2>

            {children}
        </section>
    );
}
