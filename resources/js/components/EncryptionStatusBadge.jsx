/**
 * Label berkas sudah berubah, tetapi isi yang tersimpan belum dienkripsi atau didekripsi ulang (US-12).
 */
export default function EncryptionStatusBadge({ value, label }) {
    if (!value) {
        return null;
    }

    return (
        <span
            title="Label berkas sudah berubah; isi yang tersimpan sedang disesuaikan di antrean."
            className="inline-flex items-center rounded border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-900"
        >
            {label}
        </span>
    );
}
