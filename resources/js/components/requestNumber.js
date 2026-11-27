/**
 * Nomor permintaan dokumen seperti di notifikasi dan nama subfolder: "Permintaan 01".
 */
export function requestLabel(number) {
    return `Permintaan ${String(number).padStart(2, '0')}`;
}
