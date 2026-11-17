import { Head } from '@inertiajs/react';
import PublicLayout from '../layouts/PublicLayout';

export default function Beranda() {
    return (
        <PublicLayout>
            <Head title="Beranda" />
            <h1 className="text-2xl font-semibold">SIGAP Vidatra</h1>
            <p className="mt-2 text-gray-600">Sistem Informasi Pengaduan dan Pelaporan Yayasan Vidatra.</p>
        </PublicLayout>
    );
}