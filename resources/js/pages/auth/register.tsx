import { Form, Link } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import TextField from '@/components/text-field';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';

export default function Register() {
    return (
        <AuthLayout
            title="Criar sua conta"
            description="Preencha os dados abaixo para começar a usar o Taskly."
        >
            <Form
                {...store.form()}
                resetOnError={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <TextField
                            label="Nome"
                            name="name"
                            type="text"
                            autoComplete="name"
                            placeholder="Seu nome completo"
                            required
                            autoFocus
                            error={errors.name}
                        />

                        <TextField
                            label="E-mail"
                            name="email"
                            type="email"
                            autoComplete="email"
                            placeholder="voce@exemplo.com"
                            required
                            error={errors.email}
                        />

                        <TextField
                            label="Senha"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder="Mínimo de 8 caracteres"
                            required
                            error={errors.password}
                        />

                        <TextField
                            label="Confirmar senha"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            placeholder="Repita a senha"
                            required
                            error={errors.password_confirmation}
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
                            {processing ? 'Criando conta…' : 'Criar conta'}
                        </button>
                    </>
                )}
            </Form>

            <p className="text-center text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                Já tem uma conta?{' '}
                <Link
                    href={login()}
                    className="font-medium text-[#1b1b18] underline underline-offset-4 dark:text-[#EDEDEC]"
                >
                    Entrar
                </Link>
            </p>
        </AuthLayout>
    );
}
