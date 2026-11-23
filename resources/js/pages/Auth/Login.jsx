import { Head, usePage } from '@inertiajs/react';
import PublicLayout from '../../layouts/PublicLayout';

export default function Login() {
    const { flash } = usePage();

    return (
        <PublicLayout>
            <Head title="Masuk" />
            <div className="mx-auto max-w-sm rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Masuk</h1>
                <p className="mt-2 text-sm text-gray-600">Gunakan akun Google @vidatra.sch.id Anda.</p>

                {flash?.error && (
                    <div role="alert" className="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* Tautan biasa, bukan <Link>: alur OAuth harus berpindah halaman penuh ke Google. */}
                <a
                    href="/auth/google/redirect"
                    className="mt-6 flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    Masuk dengan Google
                </a>
            </div>
        </PublicLayout>
    );
}
