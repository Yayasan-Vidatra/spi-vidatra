const styles = {
    umum: 'border-gray-200 bg-gray-50 text-gray-700',
    internal: 'border-blue-200 bg-blue-50 text-blue-800',
    rahasia: 'border-red-200 bg-red-50 text-red-800',
};

export default function ClassificationBadge({ value, label }) {
    return (
        <span className={`inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-medium ${styles[value] ?? styles.umum}`}>
            {label}
        </span>
    );
}
