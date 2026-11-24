import { Link } from '@inertiajs/react';
import ClassificationBadge from './ClassificationBadge';
import EncryptionStatusBadge from './EncryptionStatusBadge';
import FileDropzone from './FileDropzone';
import { formatBytes, formatDateTime } from './fileFormat';
import ScanStatusBadge from './ScanStatusBadge';

/**
 * Daftar berkas satu folder beserta area unggah (US-09), dipakai di H-05 dan halaman folder umum.
 */
export default function FolderFiles({ folder, uploadRules }) {
    if (!folder) {
        return <p className="text-sm text-gray-500">Pilih folder untuk melihat berkasnya.</p>;
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-medium">{folder.name}</h3>
                {folder.is_locked && <span className="text-xs text-gray-500">Terkunci, baca saja</span>}
            </div>

            {folder.can.upload && <FileDropzone uploadUrl={`/folder/${folder.id}/berkas`} rules={uploadRules} />}

            {folder.files.length === 0 ? (
                <p className="text-sm text-gray-500">Belum ada berkas di folder ini.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b text-xs text-gray-500">
                            <tr>
                                <th className="py-2 pr-3 font-medium">Nama</th>
                                <th className="py-2 pr-3 font-medium">Versi</th>
                                <th className="py-2 pr-3 font-medium">Ukuran</th>
                                <th className="py-2 pr-3 font-medium">Label</th>
                                <th className="py-2 pr-3 font-medium">Status</th>
                                <th className="py-2 pr-3 font-medium">Pengunggah</th>
                                <th className="py-2 font-medium">Waktu</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {folder.files.map((file) => (
                                <tr key={file.id}>
                                    <td className="py-2 pr-3">
                                        <Link href={`/berkas/${file.id}`} className="break-all font-medium underline">
                                            {file.name}
                                        </Link>
                                    </td>
                                    <td className="py-2 pr-3">v{file.version_number}</td>
                                    <td className="py-2 pr-3 whitespace-nowrap">{formatBytes(file.size)}</td>
                                    <td className="py-2 pr-3">
                                        <ClassificationBadge value={file.classification} label={file.classification_label} />
                                    </td>
                                    <td className="py-2 pr-3">
                                        <div className="flex flex-wrap gap-1">
                                            <ScanStatusBadge value={file.scan_status} label={file.scan_status_label} />
                                            <EncryptionStatusBadge value={file.encryption_status} label={file.encryption_status_label} />
                                        </div>
                                    </td>
                                    <td className="py-2 pr-3">{file.uploader}</td>
                                    <td className="py-2 whitespace-nowrap text-gray-600">{formatDateTime(file.uploaded_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
