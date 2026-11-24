import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ClassificationBadge from '../../components/ClassificationBadge';
import { formatDateTime } from '../../components/fileFormat';
import Pagination from '../../components/Pagination';
import AppLayout from '../../layouts/AppLayout';

function TrashActions({ file }) {
    const [isConfirmingPurge, setIsConfirmingPurge] = useState(false);

    if (!file.can.restore && !file.can.forceDelete) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-3">
            {file.can.restore && (
                <Form action={`/tempat-sampah/${file.id}/pulihkan`} method="post">
                    {({ processing }) => (
                        <button type="submit" disabled={processing} className="text-xs font-medium text-gray-700 underline">
                            Pulihkan
                        </button>
                    )}
                </Form>
            )}
            {file.can.forceDelete &&
                (isConfirmingPurge ? (
                    <Form action={`/tempat-sampah/${file.id}`} method="delete" className="inline-flex flex-wrap items-center gap-2">
                        {({ processing }) => (
                            <>
                                <span className="text-xs text-red-800">Hapus permanen? Isi dan semua versinya tidak bisa dikembalikan.</span>
                                <button type="submit" disabled={processing} className="text-xs font-medium text-red-700 underline">
                                    Ya, hapus permanen
                                </button>
                                <button type="button" onClick={() => setIsConfirmingPurge(false)} className="text-xs font-medium text-gray-600 underline">
                                    Batal
                                </button>
                            </>
                        )}
                    </Form>
                ) : (
                    <button type="button" onClick={() => setIsConfirmingPurge(true)} className="text-xs font-medium text-red-700 underline">
                        Hapus permanen
                    </button>
                ))}
        </div>
    );
}

export default function Index({ files, retentionDays }) {
    return (
        <AppLayout>
            <Head title="Tempat Sampah" />
            <h1 className="text-2xl font-semibold">Tempat Sampah</h1>
            <p className="mt-1 text-sm text-gray-600">
                Berkas terhapus disimpan {retentionDays} hari, lalu dihapus permanen otomatis. Hanya Dewan Pengawas yang bisa memulihkan atau menghapus
                permanen.
            </p>

            <section className="mt-6 rounded-lg border bg-white p-4">
                {files.data.length === 0 ? (
                    <p className="text-sm text-gray-500">Tempat Sampah kosong.</p>
                ) : (
                    <ul className="divide-y">
                        {files.data.map((file) => (
                            <li key={file.id} className="py-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="break-all font-medium">{file.name}</span>
                                    <ClassificationBadge value={file.classification} label={file.classification_label} />
                                    {file.version_number && <span className="text-xs text-gray-500">v{file.version_number}</span>}
                                </div>
                                <p className="mt-1 text-gray-600">
                                    {file.assignment_code ? `${file.assignment_code} · ` : ''}
                                    {file.folder}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    Dihapus {file.deleted_by ? `oleh ${file.deleted_by} ` : ''}
                                    {formatDateTime(file.deleted_at)} · dihapus permanen {formatDateTime(file.purge_at)}
                                </p>
                                <div className="mt-2">
                                    <TrashActions file={file} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination paginator={files} />
            </section>
        </AppLayout>
    );
}
