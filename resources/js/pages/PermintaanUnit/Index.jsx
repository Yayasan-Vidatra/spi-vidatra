import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

/**
 * H-08 Permintaan untuk Unit Saya (US-16): hanya permintaan untuk unit PIC.
 */
export default function Index({ unit, documentRequests }) {
    return (
        <AppLayout>
            <Head title="Permintaan untuk unit" />
            <h1 className="text-2xl font-semibold">Permintaan untuk {unit}</h1>
            <p className="mt-1 text-sm text-gray-600">Dokumen yang diminta SPI dari unit Anda. Unggah berkas langsung ke setiap slot.</p>

            {documentRequests.length === 0 ? (
                <p className="mt-6 rounded-lg border bg-white p-6 text-sm text-gray-500">Belum ada permintaan dokumen untuk unit Anda.</p>
            ) : (
                <ul className="mt-6 divide-y rounded-lg border bg-white">
                    {documentRequests.map((documentRequest) => (
                        <li key={documentRequest.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div>
                                <Link href={`/permintaan-unit/${documentRequest.id}`} className="font-medium underline">
                                    Permintaan #{documentRequest.id}
                                </Link>
                                <p className="mt-1 text-sm text-gray-600">
                                    Dibuat {documentRequest.created_at} · Tenggat {documentRequest.due_date}
                                    {documentRequest.is_past_due && <span className="ml-1 font-medium text-red-700">(sudah lewat)</span>}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <span>
                                    {documentRequest.pending_count} dari {documentRequest.item_count} dokumen belum diunggah
                                </span>
                                {documentRequest.overdue_count > 0 && (
                                    <span className="rounded border border-red-300 bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-900">
                                        {documentRequest.overdue_count} terlambat
                                    </span>
                                )}
                                <span className="text-gray-500">{documentRequest.status_label}</span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
