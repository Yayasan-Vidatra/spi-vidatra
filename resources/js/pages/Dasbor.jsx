import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/**
 * Placeholder H-03. Isi dasbor sesuai peran dikerjakan di US-28.
 */
export default function Dasbor() {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <Head title="Dasbor" />
            <h1 className="text-2xl font-semibold">Dasbor</h1>
            <p className="mt-2 text-gray-600">
                Selamat datang, {auth.user.name}. Anda masuk sebagai {auth.user.role}.
            </p>
        </AppLayout>
    );
}
