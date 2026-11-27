const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/**
 * Peringatan bila tenggat jatuh di akhir pekan atau hari libur (US-18). Hanya peringatan: tenggat tetap boleh
 * dipakai. `holidays` berisi tanggal (Y-m-d) → keterangan libur.
 */
export default function DueDateWarning({ date, holidays }) {
    if (!date) {
        return null;
    }

    const holiday = holidays[date];
    const day = new Date(`${date}T00:00:00`).getDay();
    const isWeekend = day === 0 || day === 6;

    if (!holiday && !isWeekend) {
        return null;
    }

    return (
        <p role="status" className="mt-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-900">
            Tenggat jatuh pada {holiday ? `hari libur (${holiday})` : `hari ${dayNames[day]}`}. Tenggat tetap bisa dipakai, tetapi unit
            mungkin tidak bekerja hari itu.
        </p>
    );
}
