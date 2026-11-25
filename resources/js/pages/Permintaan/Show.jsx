import { Head, Link } from '@inertiajs/react';
import { formatDateTime } from '../../components/fileFormat';
import RequestItemStatusBadge from '../../components/RequestItemStatusBadge';
import ScanStatusBadge from '../../components/ScanStatusBadge';
import AppLayout from '../../layouts/AppLayout';

/**
 * H-07 detail permintaan: status tiap slot (KF-08).
 */
export default function Show({ assignment, documentRequest, items }) {
    return (
        <AppLayout>
            <Head title={`Permintaan ${documentRequest.number} · ${assignment.code}`} />
            <Link href={`/penugasan/${assignment.id}/permintaan`} className="text-sm text-gray-600 underline">
                Kembali ke daftar permintaan
            </Link>

            <h1 className="mt-4 text-2xl font-semibold">
                Permintaan {String(documentRequest.number).padStart(2, '0')} · {documentRequest.unit}
            </h1>
            <p className="mt-1 text-sm text-gray-600">
                {assignment.code} · Tenggat {documentRequest.due_date}
                {documentRequest.is_past_due && <span className="ml-1 font-medium text-red-700">(sudah lewat)</span>} · Dibuat oleh{' '}
                {documentRequest.creator} · {documentRequest.status_label}
            </p>

            <ul className="mt-6 divide-y rounded-lg border bg-white">
                {items.map((item) => (
                    <li key={item.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                        <div>
                            <p className="font-medium">{item.name}</p>
                            {item.description && <p className="mt-1 text-sm text-gray-600">{item.description}</p>}
                            <p className="mt-1 text-xs text-gray-500">Format: {item.formats.join(', ')}</p>
                            {item.file && (
                                <div className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                                    <Link href={`/berkas/${item.file.id}`} className="break-all font-medium underline">
                                        {item.file.name}
                                    </Link>
                                    <span className="text-gray-600">
                                        v{item.file.version_number} · {item.file.uploader} · {formatDateTime(item.submitted_at)}
                                    </span>
                                    <ScanStatusBadge value={item.file.scan_status} label={item.file.scan_status_label} />
                                    {item.is_submitted_late && <span className="text-xs text-red-700">Diunggah terlambat</span>}
                                </div>
                            )}
                            {item.system_note && (
                                <p className="mt-2 text-sm text-red-800">
                                    <span className="font-medium">Catatan sistem:</span> {item.system_note}
                                </p>
                            )}
                            {item.reviewer_note && (
                                <p className="mt-2 text-sm text-amber-900">
                                    <span className="font-medium">Catatan auditor:</span> {item.reviewer_note}
                                </p>
                            )}
                        </div>
                        <RequestItemStatusBadge status={item.status} label={item.status_label} isOverdue={item.is_overdue} />
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
