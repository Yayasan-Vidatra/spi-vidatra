import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ClassificationBadge from '../../components/ClassificationBadge';
import { formatBytes, formatDateTime } from '../../components/fileFormat';
import ScanStatusBadge from '../../components/ScanStatusBadge';
import AppLayout from '../../layouts/AppLayout';

const primaryButton = 'rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50';

function Preview({ preview, file, currentVersion }) {
    if (preview) {
        return preview.type === 'pdf' ? (
            <iframe src={preview.url} title={`Pratinjau ${file.name}`} className="h-[70vh] w-full rounded-md border" />
        ) : (
            <img src={preview.url} alt={`Pratinjau ${file.name}`} className="max-h-[70vh] w-auto rounded-md border" />
        );
    }

    let message = 'Pratinjau hanya tersedia untuk PDF dan gambar. Unduh berkas untuk membukanya.';

    if (currentVersion?.scan_status === 'menunggu') {
        message = 'Berkas sedang menunggu pemindaian antivirus. Pratinjau dan unduhan tersedia setelah dinyatakan bersih.';
    } else if (currentVersion?.scan_status === 'terinfeksi') {
        message = 'Versi terkini terdeteksi mengandung virus dan dikarantina. Berkas ini tidak bisa dibuka atau diunduh.';
    }

    return <p className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-gray-600">{message}</p>;
}

function LabelForm({ file, allowedLabels }) {
    return (
        <Form action={`/berkas/${file.id}`} method="patch" className="flex flex-wrap items-start gap-2">
            {({ errors, processing }) => (
                <>
                    <select name="classification" defaultValue={file.classification} className="rounded-md border px-2 py-1.5 text-sm" aria-label="Label">
                        {allowedLabels.map((label) => (
                            <option key={label.value} value={label.value}>
                                {label.label}
                            </option>
                        ))}
                    </select>
                    <button type="submit" disabled={processing} className={primaryButton}>
                        Simpan label
                    </button>
                    {errors.classification && <p className="w-full text-sm text-red-600">{errors.classification}</p>}
                </>
            )}
        </Form>
    );
}

function VersionRow({ file, version }) {
    const [isConfirmingRestore, setIsConfirmingRestore] = useState(false);

    return (
        <li className="py-3 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">Versi {version.number}</span>
                {version.is_current && <span className="rounded-full bg-gray-200 px-2 py-0.5 text-xs">Terkini</span>}
                <ScanStatusBadge value={version.scan_status} label={version.scan_status_label} />
            </div>
            <p className="mt-1 text-gray-600">
                {version.uploader} · {formatDateTime(version.uploaded_at)} · {formatBytes(version.size)}
                {version.restored_from && ` · dipulihkan dari versi ${version.restored_from}`}
            </p>
            <p className="mt-1 break-all font-mono text-xs text-gray-500">SHA-256 {version.sha256}</p>
            <div className="mt-2 flex flex-wrap items-center gap-3">
                {version.download_url && (
                    <a href={version.download_url} className="text-xs font-medium text-gray-700 underline">
                        Unduh
                    </a>
                )}
                {version.can.restore &&
                    (isConfirmingRestore ? (
                        <Form
                            action={`/berkas/${file.id}/versi/${version.id}/pulihkan`}
                            method="post"
                            className="inline-flex flex-wrap items-center gap-2"
                            onSuccess={() => setIsConfirmingRestore(false)}
                        >
                            {({ processing }) => (
                                <>
                                    <span className="text-xs text-gray-700">
                                        Isi versi {version.number} akan disalin menjadi versi terbaru. Riwayat versi tetap tersimpan.
                                    </span>
                                    <button type="submit" disabled={processing} className="text-xs font-medium text-gray-900 underline">
                                        Ya, pulihkan
                                    </button>
                                    <button type="button" onClick={() => setIsConfirmingRestore(false)} className="text-xs font-medium text-gray-600 underline">
                                        Batal
                                    </button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <button type="button" onClick={() => setIsConfirmingRestore(true)} className="text-xs font-medium text-gray-700 underline">
                            Pulihkan sebagai versi baru
                        </button>
                    ))}
            </div>
        </li>
    );
}

function AccessHistory({ entries }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <h2 className="font-semibold">Riwayat akses</h2>
            {entries.length === 0 ? (
                <p className="mt-2 text-sm text-gray-500">Belum ada catatan.</p>
            ) : (
                <ul className="mt-3 divide-y text-sm">
                    {entries.map((entry) => (
                        <li key={entry.id} className="py-2">
                            <p>
                                <span className="font-medium">{entry.user}</span> · {entry.action_label}
                                {entry.version && ` (versi ${entry.version})`}
                            </p>
                            <p className="text-xs text-gray-500">
                                {formatDateTime(entry.occurred_at)}
                                {entry.ip_address && ` · IP ${entry.ip_address}`}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Show({ file, preview, versions, allowedLabels, accessHistory, can }) {
    const currentVersion = versions.find((version) => version.is_current);

    return (
        <AppLayout>
            <Head title={file.name} />
            <Link href={file.folder_url} className="text-sm text-gray-600 underline">
                Kembali ke folder
            </Link>

            <div className="mt-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="break-all text-2xl font-semibold">{file.name}</h1>
                    <ClassificationBadge value={file.classification} label={file.classification_label} />
                </div>
                <p className="mt-1 text-sm text-gray-600">
                    {file.assignment_code && `${file.assignment_code} · `}
                    {file.folder_path}
                    {file.is_locked && ' · Terkunci, baca saja'}
                </p>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-semibold">Pratinjau</h2>
                            {currentVersion?.download_url && (
                                <a href={currentVersion.download_url} className={primaryButton}>
                                    Unduh versi terkini
                                </a>
                            )}
                        </div>
                        <div className="mt-3">
                            <Preview preview={preview} file={file} currentVersion={currentVersion} />
                        </div>
                    </section>

                    {accessHistory && <AccessHistory entries={accessHistory} />}
                </div>

                <div className="space-y-6">
                    {can.changeLabel && allowedLabels.length > 1 && (
                        <section className="rounded-lg border bg-white p-4">
                            <h2 className="font-semibold">Label</h2>
                            <div className="mt-3">
                                <LabelForm key={file.classification} file={file} allowedLabels={allowedLabels} />
                            </div>
                        </section>
                    )}

                    <section className="rounded-lg border bg-white p-4">
                        <h2 className="font-semibold">Riwayat versi</h2>
                        <ul className="mt-1 divide-y">
                            {versions.map((version) => (
                                <VersionRow key={version.id} file={file} version={version} />
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
