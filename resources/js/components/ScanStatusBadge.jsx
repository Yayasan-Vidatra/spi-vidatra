const styles = {
    menunggu: 'border-amber-200 bg-amber-50 text-amber-800',
    bersih: 'border-green-200 bg-green-50 text-green-800',
    terinfeksi: 'border-red-300 bg-red-100 text-red-900',
};

export default function ScanStatusBadge({ value, label }) {
    return (
        <span className={`inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-medium ${styles[value] ?? styles.menunggu}`}>
            {label}
        </span>
    );
}
