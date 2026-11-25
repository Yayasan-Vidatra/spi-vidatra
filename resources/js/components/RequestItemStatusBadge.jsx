const styles = {
    menunggu: 'border-gray-200 bg-gray-50 text-gray-700',
    diunggah: 'border-blue-200 bg-blue-50 text-blue-800',
    diterima: 'border-green-200 bg-green-50 text-green-800',
    perlu_perbaikan: 'border-amber-200 bg-amber-50 text-amber-800',
    terlambat: 'border-red-300 bg-red-100 text-red-900',
};

/**
 * Status slot permintaan dokumen (KF-08). Terlambat ditampilkan menggantikan Menunggu atau Perlu Perbaikan
 * setelah tenggat lewat.
 */
export default function RequestItemStatusBadge({ status, label, isOverdue = false }) {
    const key = isOverdue ? 'terlambat' : status;

    return (
        <span className={`inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-medium ${styles[key] ?? styles.menunggu}`}>
            {isOverdue ? `Terlambat · ${label}` : label}
        </span>
    );
}
