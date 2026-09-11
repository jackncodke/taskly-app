import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';

type AuthLayoutProps = {
    title: string;
    description: string;
    children: ReactNode;
};

export default function AuthLayout({
    title,
    description,
    children,
}: AuthLayoutProps) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:p-8 dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="flex w-full max-w-[400px] flex-col gap-6">
                    <span className="self-center text-[18px] font-medium tracking-tight">
                        Taskly
                    </span>

                    <div className="flex flex-col gap-6 rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] sm:p-8 dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                        <div className="flex flex-col gap-1">
                            <h1 className="text-[18px] font-medium">{title}</h1>
                            <p className="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                                {description}
                            </p>
                        </div>

                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
