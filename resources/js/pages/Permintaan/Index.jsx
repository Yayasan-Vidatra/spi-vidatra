import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

/**
 * H-07 Permintaan Dokumen (SPI) untuk satu penugasan (US-15, US-18).
 */
export default function Index({ assignment, documentRequests, can }) {
    return (
        <AppLayout>
            <Head title={`Permintaan dokumen · ${assignment.code}`} />
            <Link href={`/penugasan/${assignment.id}`} className="text-sm text-gray-600 underline">
                Kembali ke {assignment.code}
            </Link>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Permintaan dokumen</h1>
                {can.create && (
                    <Link
                        href={`/penugasan/${assignment.id}/permintaan/buat`}
                        className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Buat permintaan
                    </Link>
                )}
            </div>

            {documentRequests.length === 0 ? (
                <p className="mt-6 rounded-lg border bg-white p-6 text-sm text-gray-500">Belum ada permintaan dokumen di penugasan ini.</p>
            ) : (
                <div className="mt-6 overflow-x-auto rounded-lg border bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-2">No.</th>
                                <th className="px-4 py-2">Unit</th>
                                <th className="px-4 py-2">Tenggat</th>
                                <th className="px-4 py-2">Diterima</th>
                                <th className="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {documentRequests.map((documentRequest) => (
                                <tr key={documentRequest.id}>
                                    <td className="px-4 py-2">
                                        <Link
                                            href={`/penugasan/${assignment.id}/permintaan/${documentRequest.id}`}
                                            className="font-medium underline"
                                        >
                                            Permintaan {String(documentRequest.number).padStart(2, '0')}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2">{documentRequest.unit}</td>
                                    <td className="px-4 py-2">{documentRequest.due_date}</td>
                                    <td className="px-4 py-2">
                                        {documentRequest.accepted_count} dari {documentRequest.item_count}
                                    </td>
                                    <td className="px-4 py-2">
                                        {documentRequest.status_label}
                                        {documentRequest.overdue_count > 0 && (
                                            <span className="ml-2 rounded border border-red-300 bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-900">
                                                {documentRequest.overdue_count} terlambat
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
