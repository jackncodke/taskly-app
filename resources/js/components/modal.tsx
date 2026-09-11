import { useEffect, useRef, type ReactNode } from 'react';

type ModalProps = {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
};

export default function Modal({ open, onClose, title, children }: ModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="modal-title"
            onClose={onClose}
            onClick={(event) => {
                // A click landing on the dialog itself is a backdrop click:
                // the content sits inside the wrapper below.
                if (event.target === dialogRef.current) {
                    onClose();
                }
            }}
            className="m-auto w-[calc(100%-2rem)] max-w-[420px] rounded-lg bg-white p-0 text-[#1b1b18] shadow-lg backdrop:bg-black/40 dark:bg-[#161615] dark:text-[#EDEDEC]"
        >
            <div className="flex flex-col gap-5 p-6">
                <h2 id="modal-title" className="text-[16px] font-medium">
                    {title}
                </h2>

                {children}
            </div>
        </dialog>
    );
}
