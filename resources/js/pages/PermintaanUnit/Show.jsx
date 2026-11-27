import { Form, Head, Link } from '@inertiajs/react';
import { formatDateTime } from '../../components/fileFormat';
import RequestItemStatusBadge from '../../components/RequestItemStatusBadge';
import ScanStatusBadge from '../../components/ScanStatusBadge';
import { requestLabel } from '../../components/requestNumber';
import AppLayout from '../../layouts/AppLayout';

function SlotUpload({ documentRequestId, item, maxSizeMb }) {
    return (
        <Form
            action={`/permintaan-unit/${documentRequestId}/slot/${item.id}`}
            method="post"
            resetOnSuccess
            className="mt-3 flex flex-wrap items-center gap-2"
        >
            {({ errors, processing, progress }) => (
                <>
                    <input
                        type="file"
                        name="file"
                        accept={item.accept}
                        aria-label={`Berkas untuk ${item.name}`}
                        className="text-sm file:mr-2 file:rounded-md file:border file:bg-white file:px-2 file:py-1 file:text-sm"
                    />
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                    >
                        {item.versions.length > 0 ? 'Unggah versi baru' : 'Unggah'}
                    </button>
                    {progress && <span className="text-xs text-gray-500">{progress.percentage}%</span>}
                    <p className="w-full text-xs text-gray-500">
                        Format: {item.formats.join(', ')} · Maksimal {maxSizeMb} MB
                    </p>
                    {errors.file && <p className="w-full text-sm text-red-600">{errors.file}</p>}
                </>
            )}
        </Form>
    );
}

/**
 * H-08 detail permintaan (US-16): slot unggah, status, catatan, dan versi yang sudah diunggah unit.
 */
export default function Show({ documentRequest, items, maxSizeMb }) {
    return (
        <AppLayout>
            <Head title={requestLabel(documentRequest.number)} />
            <Link href="/permintaan-unit" className="text-sm text-gray-600 underline">
                Kembali ke daftar permintaan
            </Link>

            <h1 className="mt-4 text-2xl font-semibold">{requestLabel(documentRequest.number)}</h1>
            <p className="mt-1 text-sm text-gray-600">
                {documentRequest.unit} · Dari {documentRequest.creator} · Dibuat {documentRequest.created_at} · Tenggat {documentRequest.due_date}{' '}
                pukul 23.59 WITA
                {documentRequest.is_past_due && <>{' '}<span className="font-medium text-red-700">(sudah lewat)</span></>}
            </p>

            <ul className="mt-6 space-y-4">
                {items.map((item) => (
                    <li key={item.id} className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="font-medium">{item.name}</p>
                                {item.description && <p className="mt-1 text-sm text-gray-600">{item.description}</p>}
                            </div>
                            <div className="flex flex-wrap items-center gap-1">
                                <RequestItemStatusBadge status={item.status} label={item.status_label} isOverdue={item.is_overdue} />
                                {item.is_submitted_late && <span className="text-xs text-red-700">Diunggah terlambat</span>}
                            </div>
                        </div>

                        {item.system_note && (
                            <p className="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                <span className="font-medium">Catatan sistem:</span> {item.system_note}
                            </p>
                        )}
                        {item.reviewer_note && (
                            <p className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                <span className="font-medium">Catatan auditor:</span> {item.reviewer_note}
                            </p>
                        )}

                        {item.versions.length > 0 && (
                            <ul className="mt-3 space-y-1 text-sm">
                                {item.versions.map((version) => (
                                    <li key={version.id} className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">v{version.number}</span>
                                        <span className="text-gray-600">
                                            {version.uploader} · {formatDateTime(version.uploaded_at)}
                                        </span>
                                        <ScanStatusBadge value={version.scan_status} label={version.scan_status_label} />
                                        {version.download_url && (
                                            <a href={version.download_url} className="text-xs font-medium underline">
                                                Unduh
                                            </a>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {item.is_locked && <p className="mt-3 text-sm text-gray-600">Dokumen sudah diterima SPI dan tidak bisa diunggah ulang.</p>}
                        {item.can_upload && <SlotUpload documentRequestId={documentRequest.id} item={item} maxSizeMb={maxSizeMb} />}
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
