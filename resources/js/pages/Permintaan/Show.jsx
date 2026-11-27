import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import DueDateWarning from '../../components/DueDateWarning';
import { formatDateTime } from '../../components/fileFormat';
import RequestItemStatusBadge from '../../components/RequestItemStatusBadge';
import ScanStatusBadge from '../../components/ScanStatusBadge';
import { requestLabel } from '../../components/requestNumber';
import AppLayout from '../../layouts/AppLayout';

/**
 * Hasil periksa satu slot (US-17): Diterima atau Perlu Perbaikan dengan catatan wajib. Nonaktif selama
 * berkas masih menunggu pindai; server menolaknya juga.
 */
function ReviewForm({ assignmentId, documentRequestId, item, maxNoteLength }) {
    const isDisabled = item.review_waits_for_scan;

    return (
        <Form
            action={`/penugasan/${assignmentId}/permintaan/${documentRequestId}/slot/${item.id}/periksa`}
            method="post"
            resetOnSuccess
            className="mt-3 space-y-2 rounded-md border bg-gray-50 p-3"
        >
            {({ errors, processing }) => (
                <fieldset disabled={isDisabled || processing} className="space-y-2 disabled:opacity-60">
                    <legend className="text-sm font-medium">Hasil periksa</legend>
                    <input type="hidden" name="version_id" value={item.version_id ?? ''} />
                    <div className="flex flex-wrap gap-4 text-sm">
                        <label className="flex items-center gap-1.5">
                            <input type="radio" name="result" value="diterima" />
                            Diterima
                        </label>
                        <label className="flex items-center gap-1.5">
                            <input type="radio" name="result" value="perlu_perbaikan" />
                            Perlu Perbaikan
                        </label>
                    </div>
                    {errors.result && <p className="text-sm text-red-600">{errors.result}</p>}
                    <label className="block text-sm">
                        <span className="sr-only">Catatan untuk {item.name}</span>
                        <textarea
                            name="note"
                            rows={2}
                            maxLength={maxNoteLength}
                            placeholder="Catatan (wajib)"
                            className="w-full rounded-md border px-2 py-1.5 text-sm"
                        />
                    </label>
                    {errors.note && <p className="text-sm text-red-600">{errors.note}</p>}
                    {errors.version_id && <p className="text-sm text-red-600">{errors.version_id}</p>}
                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                        >
                            Simpan hasil periksa
                        </button>
                        <span className="text-xs text-gray-500">
                            {isDisabled
                                ? 'Menunggu pindai: berkas belum bisa diperiksa sampai dinyatakan bersih.'
                                : 'Diterima bersifat final: slot terkunci untuk unit dan disalin ke 04 Bukti Unit.'}
                        </span>
                    </div>
                </fieldset>
            )}
        </Form>
    );
}

/**
 * Ubah tenggat (US-18): tanggal baru dan alasan wajib, yang dicatat di log dan dikirim ke PIC. Pengingat untuk
 * tenggat baru dikirim ulang; eskalasi yang sudah terkirim tetap.
 */
function DueDateForm({ assignmentId, documentRequest, today, holidays, maxReasonLength }) {
    const [isOpen, setIsOpen] = useState(false);
    const [dueDate, setDueDate] = useState(documentRequest.due_date);

    if (!isOpen) {
        return (
            <button type="button" onClick={() => setIsOpen(true)} className="text-sm font-medium underline">
                Ubah tenggat
            </button>
        );
    }

    return (
        <Form
            action={`/penugasan/${assignmentId}/permintaan/${documentRequest.id}/tenggat`}
            method="patch"
            onSuccess={() => setIsOpen(false)}
            className="mt-3 max-w-lg space-y-2 rounded-md border bg-gray-50 p-3"
        >
            {({ errors, processing }) => (
                <fieldset disabled={processing} className="space-y-2">
                    <legend className="text-sm font-medium">Ubah tenggat</legend>
                    <label className="block text-sm">
                        Tenggat baru
                        <input
                            name="due_date"
                            type="date"
                            min={today}
                            defaultValue={documentRequest.due_date}
                            onChange={(event) => setDueDate(event.target.value)}
                            className="mt-1 block rounded-md border px-2 py-1.5 text-sm"
                        />
                    </label>
                    <DueDateWarning date={dueDate} holidays={holidays} />
                    {errors.due_date && <p className="text-sm text-red-600">{errors.due_date}</p>}
                    <label className="block text-sm">
                        Alasan (wajib, dikirim ke PIC unit)
                        <textarea name="reason" rows={2} maxLength={maxReasonLength} className="mt-1 w-full rounded-md border px-2 py-1.5 text-sm" />
                    </label>
                    {errors.reason && <p className="text-sm text-red-600">{errors.reason}</p>}
                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                        >
                            Simpan tenggat
                        </button>
                        <button type="button" onClick={() => setIsOpen(false)} className="text-sm underline">
                            Batal
                        </button>
                    </div>
                </fieldset>
            )}
        </Form>
    );
}

/**
 * H-07 detail permintaan: status tiap slot (KF-08), pemeriksaan auditor (US-17), dan ubah tenggat (US-18).
 */
export default function Show({ assignment, documentRequest, items, maxNoteLength, maxReasonLength, today, holidays }) {
    return (
        <AppLayout>
            <Head title={`${requestLabel(documentRequest.number)} · ${assignment.code}`} />
            <Link href={`/penugasan/${assignment.id}/permintaan`} className="text-sm text-gray-600 underline">
                Kembali ke daftar permintaan
            </Link>

            <h1 className="mt-4 text-2xl font-semibold">
                {requestLabel(documentRequest.number)} · {documentRequest.unit}
            </h1>
            <p className="mt-1 text-sm text-gray-600">
                {assignment.code} · Tenggat {documentRequest.due_date}
                {documentRequest.is_past_due && <>{' '}<span className="font-medium text-red-700">(sudah lewat)</span></>} · Dibuat oleh{' '}
                {documentRequest.creator} · {documentRequest.status_label}
                {documentRequest.due_date_changed_at && ` · Tenggat diubah ${formatDateTime(documentRequest.due_date_changed_at)}`}
            </p>
            {documentRequest.can.updateDueDate && (
                <div className="mt-2">
                    <DueDateForm
                        assignmentId={assignment.id}
                        documentRequest={documentRequest}
                        today={today}
                        holidays={holidays}
                        maxReasonLength={maxReasonLength}
                    />
                </div>
            )}

            <ul className="mt-6 space-y-4">
                {items.map((item) => (
                    <li key={item.id} className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="font-medium">{item.name}</p>
                                {item.description && <p className="mt-1 text-sm text-gray-600">{item.description}</p>}
                                <p className="mt-1 text-xs text-gray-500">Format: {item.formats.join(', ')}</p>
                            </div>
                            <RequestItemStatusBadge status={item.status} label={item.status_label} isOverdue={item.is_overdue} />
                        </div>

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
                                {item.reviewer && (
                                    <span className="text-xs text-gray-600">
                                        {' '}
                                        · {item.reviewer}, {formatDateTime(item.reviewed_at)}
                                    </span>
                                )}
                            </p>
                        )}
                        {item.evidence_file && (
                            <p className="mt-2 text-sm text-gray-700">
                                Disalin ke 04 Bukti Unit:{' '}
                                <Link href={`/berkas/${item.evidence_file.id}`} className="break-all font-medium underline">
                                    {item.evidence_file.name}
                                </Link>
                            </p>
                        )}
                        {item.evidence_copy_pending && (
                            <p className="mt-2 text-sm text-amber-800">Salinan ke 04 tertunda. Sistem mencoba ulang secara otomatis.</p>
                        )}

                        {item.can_review && (
                            <ReviewForm assignmentId={assignment.id} documentRequestId={documentRequest.id} item={item} maxNoteLength={maxNoteLength} />
                        )}
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
