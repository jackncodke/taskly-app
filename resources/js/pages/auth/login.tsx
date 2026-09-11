import { Form, Link } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import TextField from '@/components/text-field';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';

export default function Login({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Entrar na sua conta"
            description="Informe seu e-mail e senha para continuar."
        >
            {status && (
                <div className="rounded-md bg-emerald-50 px-3 py-2 text-[13px] text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                disableWhileProcessing
                className="flex flex-col gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <TextField
                            label="E-mail"
                            name="email"
                            type="email"
                            autoComplete="email"
                            placeholder="voce@exemplo.com"
                            required
                            autoFocus
                            error={errors.email}
                        />

                        <TextField
                            label="Senha"
                            name="password"
                            type="password"
                            autoComplete="current-password"
                            placeholder="••••••••"
                            required
                            error={errors.password}
                        />

                        <label className="flex items-center gap-2 text-[13px] text-[#706f6c] select-none dark:text-[#A1A09A]">
                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                                defaultChecked
                                className="size-4 rounded border-[#e3e3e0] accent-[#1b1b18] dark:border-[#3E3E3A] dark:accent-[#EDEDEC]"
                            />
                            Manter-me conectado
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-1 w-full rounded-md bg-[#1b1b18] px-4 py-2.5 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60 dark:bg-[#EDEDEC] dark:text-[#1b1b18]"
                        >
                            {processing ? 'Entrando…' : 'Entrar'}
                        </button>
                    </>
                )}
            </Form>

            <p className="text-center text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                Não tem uma conta?{' '}
                <Link
                    href={register()}
                    className="font-medium text-[#1b1b18] underline underline-offset-4 dark:text-[#EDEDEC]"
                >
                    Cadastre-se
                </Link>
            </p>
        </AuthLayout>
    );
}
