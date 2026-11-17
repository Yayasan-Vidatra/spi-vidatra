# TOR Pengembangan SIGAP Vidatra

Nov 16, 2026 · @Luthfianda Salsabila

Kerangka acuan kerja pembangunan **SIGAP Vidatra** (Sistem Informasi Pengaduan dan Pelaporan) di **sigap.vidatra.sch.id**, dikerjakan 3 minggu di VPS.

## 1. Latar Belakang dan Tujuan

Yayasan Vidatra membutuhkan satu pintu resmi untuk pengaduan layanan dan pelaporan pelanggaran (whistleblowing) di semua unit: SD YPVDP, SMP YPVDP, SMA YPVDP, dan Pelaksana Harian. Rancangan sistemnya sudah disusun dalam dokumen *Rancangan Sistem Pengaduan dan Pelaporan Pelanggaran Yayasan Vidatra* revisi r1 (16 November 2026). TOR ini mengatur pembangunannya.

**Tujuan pekerjaan:**

1. Membangun website SIGAP Vidatra dengan dua kanal terpisah: **Pengaduan** dan **Pelaporan Pelanggaran (WBS)**, masing-masing dapat menerima lampiran dokumen, foto, dan video.
2. Menyediakan panel petugas untuk Admin Pengaduan, PIC Unit, Tim WBS, Penerima Hasil, Kepala Sekolah/Komite Etik, dan Pimpinan Yayasan.
3. Memastikan setiap laporan punya kode lacak, status, penanggung jawab, dan batas waktu.
4. Menyiapkan sistem berjalan di VPS dengan subdomain **sigap.vidatra.sch.id** dalam waktu 3 minggu.

**Dasar:** Rancangan Sistem r1 dan Rancangan Peraturan Yayasan tentang Sistem Pengaduan dan Pelaporan Pelanggaran. Bila TOR ini berbeda dengan Peraturan, Peraturan yang berlaku.

## 2. Ruang Lingkup

Pekerjaan dibagi dua tahap. Tahap 1 (3 minggu, diatur TOR ini) menghasilkan kanal Pengaduan lengkap dan kanal WBS dasar. Tahap 2 melengkapi fitur keamanan lanjutan, karena rencana r1 utuh membutuhkan sekitar 11–13 minggu.

**Tahap 1 — dalam 3 minggu:**

- **Kanal Pengaduan:** formulir, lampiran, lacak status, pesan dua arah, verifikasi, disposisi ke unit, tindak lanjut PIC, konfirmasi pelapor, penilaian kepuasan.
- **Kanal WBS:** formulir bernama/anonim, kedudukan terlapor, kode lacak + PIN, enkripsi data dan lampiran, ruang kerja Tim WBS (telaah, investigasi, kesimpulan).
- **Perutean dan pengalihan:** matriks penerima (Rancangan r1 Bagian 3.1), pengalihan ke Kepala Sekolah/Komite Etik dengan konfirmasi terima, pindah Pengaduan ke WBS.
- **Saluran lain:** pencatatan laporan masuk dari surel, telepon/WhatsApp, dan surat.
- **Rekap:** dashboard per peran, rekap Pengaduan per unit, jumlah WBS per kategori (min. 3 laporan), ekspor Excel/PDF.
- **Keamanan dasar:** HTTPS, 2FA petugas, akun lokal untuk peran WBS, log audit, pemindaian virus, hapus metadata, tanpa log IP di aplikasi dan server web.

**Tahap 2 — setelah serah terima:**

- Laporan Khusus ke Ketua Pembina dengan penghitung 1 × 24 jam.
- Rekap triwulanan untuk Dewan Pengawas dan rekap tahunan untuk Pembina.
- Kunci enkripsi dengan kendali ganda, persetujuan pemberian peran oleh Ketua Dewan Pengawas, dan pencatatan hak terlapor.
- Uji keamanan oleh pihak independen, sebagai syarat kanal WBS dibuka ke publik.

**Asumsi:**

- Pengerjaan dimulai setelah Peraturan Yayasan cukup jelas untuk dipakai sebagai acuan; perubahan aturan di tengah jalan masuk tahap 2.
- Peluncuran kanal WBS ke publik menunggu uji keamanan independen di tahap 2 (Rancangan r1 Bagian 9).
- Pengembang satu orang penuh waktu; konten (teks halaman dan kategori) disediakan Yayasan. Nama dan surel Admin, PIC, Kepala Sekolah, serta kontak darurat tidak ditanam di kode, melainkan diinput dan disimpan di basis data lewat halaman Pengaturan, sehingga tidak terikat pada pejabat yang sedang menjabat.
- Platform akun: Google Workspace, untuk login SSO Admin dan PIC serta surel notifikasi. Sewa VPS sudah disetujui.

## 3. Peta Halaman

SIGAP terdiri dari 20 halaman: 8 halaman publik untuk pelapor dan 12 halaman panel untuk petugas.

&#91;embedded content: peta halaman · 8 publik, 12 internal\]

Pengaduan dan WBS berbagi Bukti Lapor dan Lacak Laporan, tetapi WBS selalu didahului halaman jaminan kerahasiaan. Rincian fitur tiap halaman ada di Bagian 4 dan 5.

## 4. Halaman Publik dan Fiturnya

Halaman publik dibuka tanpa login, dibangun dengan React + Vite, dan harus nyaman dipakai di HP.

### H-01 Beranda

*Alamat: sigap.vidatra.sch.id*

- Penjelasan singkat SIGAP Vidatra.
- Tombol "Buat Pengaduan" dan "Laporkan Pelanggaran", dengan penjelasan beda keduanya.
- Tombol "Lacak Laporan".
- Kontak darurat tiap sekolah untuk kasus kekerasan, diambil dari Pengaturan.
- Tautan FAQ dan Kebijakan Privasi.

### H-02 Formulir Pengaduan

*Alamat: /pengaduan*

- Pilihan unit, kategori, dan jenis pelapor (orang tua, siswa, alumni, masyarakat).
- Isian nama, kontak, judul, kronologi, waktu, lokasi, dan jabatan pihak yang dikeluhkan.
- Bila pelapor siswa, kontak berganti ke orang tua/wali dengan kotak persetujuan.
- Unggah maksimal 5 berkas (dokumen, foto, video) dengan progres dan pratinjau.
- Saran pindah ke kanal WBS bila isi menyangkut pelanggaran serius.
- CAPTCHA dan simpan draf otomatis agar isian tidak hilang.

### H-03 Jaminan Kerahasiaan WBS

*Alamat: /wbs*

- Dokumen jaminan kerahasiaan ditampilkan lewat file viewer; pembaruan cukup dengan mengganti berkas di Pengaturan. Isi dokumen menyusul.
- Informasi saluran alternatif: surel, telepon/WhatsApp SPI, dan surat tertutup, dengan catatan bahwa telepon memperlihatkan nomor pelapor.
- Tombol "Saya mengerti, lanjut melapor".

### H-04 Formulir WBS

*Alamat: /wbs/lapor*

- Pilihan mode bernama atau anonim.
- Isian jenis pelanggaran, unit, terlapor, kedudukan terlapor (wajib), kronologi 5W1H, dan saksi.
- Unggah dokumen, foto, video, dan audio; metadata dihapus otomatis.
- Pesan kontak darurat bila laporan menyangkut kekerasan.
- Pembuatan PIN 6 digit dan CAPTCHA.
- Tanpa pencatatan IP, cookie pelacak, atau analitik.

### H-05 Bukti Lapor

*Alamat: /selesai*

- Kode lacak ditampilkan; untuk WBS hanya sekali, dengan peringatan untuk disimpan.
- Unduh PDF bukti lapor dan tombol salin kode.
- Surel konfirmasi untuk Pengaduan.

### H-06 Lacak Laporan

*Alamat: /lacak*

- Isian kode lacak, ditambah PIN untuk WBS.
- Pembatasan percobaan dan CAPTCHA untuk mencegah tebak-tebakan kode.

### H-07 Detail Laporan

*Alamat: /lacak/{kode}*

- Status terkini dan riwayat status.
- Pesan dua arah dengan petugas, termasuk lampiran tambahan.
- Konfirmasi selesai atau tolak hasil, serta penilaian kepuasan 1–5 (Pengaduan).
- Keluar otomatis setelah 15 menit tidak aktif.

### H-08 FAQ dan Kebijakan Privasi

*Alamat: /faq dan /privasi*

- Tanya jawab umum dan target waktu penanganan.
- Data yang disimpan, lama penyimpanan (5 tahun), serta hak pelapor dan terlapor sesuai UU 27/2022.

## 5. Halaman Internal dan Fiturnya

Halaman internal ada di **sigap.vidatra.sch.id/panel**, hanya untuk petugas yang sudah login dengan 2FA. Menu yang tampil menyesuaikan peran. Semua halaman dikerjakan di Tahap 1, kecuali yang ditandai Tahap 2.

### H-09 Login Petugas

*Pengguna: semua petugas*

- Login SSO Google Workspace (akun @vidatra.sch.id) untuk Admin dan PIC.
- Akun lokal untuk peran WBS, Penerima Hasil, dan Kepala Sekolah/Komite Etik.
- 2FA wajib dengan aplikasi autentikator.
- Akun terkunci setelah 5 kali gagal login.

### H-10 Dashboard

*Pengguna: semua petugas*

- Ringkasan sesuai peran: laporan baru, lewat batas waktu, dan menunggu tindakan.
- Grafik Pengaduan per unit dan per bulan.
- Jumlah WBS per kategori, tampil hanya bila sekurang-kurangnya 3 laporan.

### H-11 Kotak Masuk Pengaduan

*Pengguna: Admin Pengaduan*

- Daftar laporan dengan filter (unit, kategori, status, prioritas, tanggal) dan pencarian.
- Verifikasi, kategorisasi, dan penolakan dengan alasan.
- Disposisi ke unit, PIC, dan prioritas.
- Pindah ke WBS atau alihkan ke Kepala Sekolah/Komite Etik.
- Pesan ke pelapor dan penutupan laporan.

### H-12 Pengaduan Unit Saya

*Pengguna: PIC Unit*

- Hanya pengaduan milik unitnya.
- Isian tindak lanjut dan unggah bukti penyelesaian.
- Permintaan konfirmasi ke pelapor.
- Pengingat batas waktu.

### H-13 Ruang Kerja WBS

*Pengguna: Tim WBS*

- Daftar laporan WBS yang boleh dibuka menurut matriks penerima.
- Telaah: layak atau tidak, dan pemeriksaan kedudukan terlapor.
- Pembukaan identitas pelapor hanya oleh Ketua, dan tercatat.
- Penugasan investigator, catatan internal, dan pesan ke pelapor.
- Perpanjangan investigasi dengan persetujuan penerima hasil.
- Kesimpulan dan rekomendasi; pengalihan kasus kekerasan/etik.

### H-14 Catat Laporan Masuk

*Pengguna: Ketua Tim WBS (SPI)*

- Formulir laporan dari surel, telepon/WhatsApp, atau surat.
- Unggah tangkapan layar atau pindaian surat.
- Kode lacak dan PIN sementara untuk disampaikan ke pelapor.

### H-15 Laporan Khusus (Tahap 2)

*Pengguna: Ketua Tim WBS*

- Pembuatan Laporan Khusus ke Ketua Pembina.
- Penghitung tenggat 1 × 24 jam dan pencatatan waktu kirim.

### H-16 Hasil WBS untuk Saya

*Pengguna: Penerima Hasil (Dewan Pengawas, Ketua Pembina, Pembina)*

- Ringkasan dan rekomendasi laporan yang menjadi bagiannya, tanpa identitas pelapor.
- Persetujuan perpanjangan investigasi.
- Penandaan laporan yang sudah ditindaklanjuti.

### H-17 Laporan Dialihkan

*Pengguna: Kepala Sekolah, Komite Etik*

- Daftar laporan yang dialihkan ke unit atau komitenya.
- Konfirmasi terima dan catatan tindak lanjut.
- Kepala Sekolah tidak dapat melihat laporan yang menyangkut dirinya.

### H-18 Rekap dan Ekspor

*Pengguna: Admin, Pimpinan Yayasan, Penerima Hasil*

- Rekap per periode, unit, dan kategori; rata-rata waktu penyelesaian; laporan lewat SLA.
- Ekspor Excel dan PDF.
- Rekap triwulanan dan tahunan otomatis (Tahap 2).

### H-19 Pengaturan

*Pengguna: Super Admin*

- Pengguna dan peran.
- Unit: SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian.
- Data jabatan per unit (nama dan surel Admin, PIC, Kepala Sekolah, Komite Etik) dan kontak darurat. Data disimpan di basis data, sehingga pergantian pejabat cukup diubah di sini.
- Kategori dan SLA.
- Teks halaman publik dan unggah berkas jaminan kerahasiaan untuk file viewer.
- Super Admin tidak dapat membuka isi WBS.

### H-20 Log Audit

*Pengguna: Super Admin (lihat saja), Ketua Tim WBS (khusus WBS)*

- Riwayat akses dan perubahan per laporan dan per pengguna.
- Tidak dapat diubah atau dihapus.
- Filter dan ekspor.

## 6. Kebutuhan Teknis dan Infrastruktur

SIGAP berjalan di VPS tersendiri, terpisah dari shared hosting website Yayasan, agar log server, pemindaian berkas, dan unggah video bisa dikendalikan sendiri.

| Komponen | Spesifikasi |
| --- | --- |
| Server | VPS min. 2 vCPU, 4 GB RAM, 80 GB SSD, Ubuntu LTS, lokasi data center di Indonesia |
| Domain | Subdomain sigap.vidatra.sch.id: record A di DNS vidatra.sch.id diarahkan ke IP VPS |
| SSL | Let's Encrypt, perpanjangan otomatis; HTTPS wajib, HTTP dialihkan |
| Aplikasi | Laravel 11 sebagai backend; React + Vite lewat Inertia.js untuk halaman publik (H-01 sampai H-08); Filament untuk panel petugas; MySQL/MariaDB; antrean (queue) dan cron untuk notifikasi dan pengingat |
| Berkas | Penyimpanan privat di luar folder publik, nama acak, unduhan lewat tautan sementara 10 menit; lampiran WBS terenkripsi |
| Pemindaian | ClamAV untuk virus; ExifTool dan FFmpeg untuk menghapus metadata foto/video; konversi HEIC ke JPG |
| Unggah | Unggah bertahap (chunked) untuk video hingga 100 MB; batas total 200 MB per laporan |
| Surel | SMTP Google Workspace dengan akun pengirim khusus (mis. sigap@vidatra.sch.id) |
| Log | Log akses Nginx untuk jalur /wbs dimatikan atau tanpa IP; firewall hanya membuka port 80, 443, dan SSH dengan kunci |
| Cadangan | Basis data dan berkas dicadangkan harian, terenkripsi, disimpan di luar VPS minimal 30 hari |
| Pemantauan | Pemantau uptime dan peringatan disk penuh ke surel pengelola TI |

## 7. Timeline Pengerjaan (3 Minggu)

Pekerjaan berlangsung 15 hari kerja. Setiap akhir minggu ada demo kepada pimpinan sebagai titik periksa; tanggal mulai ditetapkan saat TOR disetujui.

&#91;embedded content: jadwal 3 minggu · 3 demo\]

Kanal Pengaduan sudah bisa didemokan di akhir Minggu 1; kanal WBS menyusul di Minggu 2 agar Minggu 3 cukup untuk uji dan perbaikan. Rincian per hari:

| Hari | Pekerjaan | Halaman |
| --- | --- | --- |
| 1 | Siapkan VPS, firewall, DNS subdomain, SSL; pasang Laravel, React + Vite (Inertia.js), Filament, MySQL, ClamAV, ExifTool, FFmpeg | — |
| 2 | Desain basis data (Rancangan r1 Bagian 10), migrasi, data induk unit dan kategori | — |
| 3 | Login, peran, 2FA, akun lokal vs SSO, log audit dasar | H-09, H-20 |
| 4 | Beranda dan Formulir Pengaduan | H-01, H-02 |
| 5 | Unggah lampiran bertahap, pemindaian virus, hapus metadata; Bukti Lapor; **demo Minggu 1** | H-05 |
| 6 | Kotak Masuk Pengaduan: verifikasi, kategori, tolak, disposisi | H-11 |
| 7 | Pengaduan Unit Saya, SLA, pengingat, notifikasi surel | H-12 |
| 8 | Lacak Laporan dan Detail Laporan: status, pesan dua arah, konfirmasi, penilaian | H-06, H-07 |
| 9 | Jaminan Kerahasiaan dan Formulir WBS: anonim, kedudukan terlapor, PIN, enkripsi | H-03, H-04 |
| 10 | Ruang Kerja WBS, matriks penerima, Catat Laporan Masuk; **demo Minggu 2** | H-13, H-14 |
| 11 | Pengalihan ke Kepala Sekolah/Komite Etik, Hasil WBS untuk Saya | H-16, H-17 |
| 12 | Dashboard, Rekap dan Ekspor | H-10, H-18 |
| 13 | Pengaturan: unit, data jabatan, kontak darurat, berkas jaminan kerahasiaan dan file viewer; FAQ dan Kebijakan Privasi | H-08, H-19 |
| 14 | Cadangan otomatis, pemantauan, pengerasan server; pemeriksaan kriteria penerimaan | — |
| 15 | Pelatihan petugas, panduan pengguna, serah terima; **demo Minggu 3** | — |

## 8. Keluaran dan Kriteria Penerimaan

**Keluaran pada akhir Minggu 3:**

1. Website SIGAP Vidatra berjalan di sigap.vidatra.sch.id dengan HTTPS, mencakup halaman H-01 sampai H-20 kecuali H-15.
2. Kode sumber di repositori GitHub organisasi Yayasan, beserta panduan instalasi.
3. Akun Super Admin awal; akun dan data petugas lain diinput Yayasan lewat Pengaturan.
4. Panduan pengguna singkat untuk pelapor dan untuk tiap peran petugas.
5. Berita acara serah terima.

**Kriteria penerimaan:**

- [ ] Pengaduan dapat dikirim dari HP dengan lampiran foto dan video 100 MB, lalu dilacak dengan kode lacak.
- [ ] Pengaduan bergerak dari Diterima sampai Selesai melalui Admin dan PIC Unit, dengan notifikasi surel di setiap perpindahan status.
- [ ] Laporan WBS anonim tersimpan terenkripsi, tanpa alamat IP di log aplikasi maupun log server web, dan hanya dapat dibuka Tim WBS.
- [ ] Laporan dengan kedudukan terlapor SPI/Tim WBS, Pengawas, atau Pembina tidak terlihat oleh Ketua Tim WBS dan sampai ke penerima sesuai matriks.
- [ ] Pengalihan ke Kepala Sekolah ditolak sistem bila Kepala Sekolah unit itu yang dilaporkan.
- [ ] PIC Unit tidak dapat melihat pengaduan unit lain; Super Admin tidak dapat membaca isi WBS.
- [ ] Berkas berbahaya terkarantina dan metadata lokasi foto terhapus.
- [ ] Setiap pembukaan laporan dan lampiran tercatat di log audit.

## 9. Peran, Kebutuhan dari Yayasan, dan Risiko

### Peran dan tanggung jawab

| Pihak | Tanggung jawab |
| --- | --- |
| Pengembang (penyusun TOR) | Membangun, memasang di VPS, panduan, pelatihan, serah terima |
| Pimpinan Yayasan | Menyetujui TOR; hadir di demo akhir minggu; menyetujui serah terima |

### Yang dibutuhkan dari Yayasan

| Kebutuhan | Paling lambat |
| --- | --- |
| Persetujuan TOR (sewa VPS sudah disetujui) | Sebelum Hari 1 |
| Akses pengelolaan DNS vidatra.sch.id untuk subdomain sigap | Hari 1 |
| Kategori pengaduan dan jenis pelanggaran final | Hari 2 |
| Akun Google Workspace pengirim notifikasi (mis. sigap@vidatra.sch.id) dan izin SSO di konsol admin | Hari 3 |
| Persetujuan draf FAQ dan kebijakan privasi (disiapkan pengembang) | Hari 13 |
| Berkas jaminan kerahasiaan | Menyusul; diunggah sendiri lewat Pengaturan |

### Risiko

| Risiko | Dampak | Penanganan |
| --- | --- | --- |
| Data kategori dan konten dari Yayasan terlambat | Uji dan serah terima mundur | Pakai data contoh dulu; tenggat per kebutuhan dicantumkan di atas |
| Peraturan Yayasan berubah saat pengerjaan | Matriks atau alur harus diubah | Perubahan setelah Hari 5 dicatat dan dikerjakan di tahap 2 |
| Jadwal 3 minggu padat untuk satu pengembang | Fitur tahap 1 tidak tuntas | Prioritas kanal Pengaduan dan WBS inti; rekap dan pengaturan lanjutan digeser bila perlu |
| WBS dipakai publik sebelum uji keamanan independen | Kerahasiaan pelapor belum teruji | Kanal WBS belum dibuka ke publik sampai uji keamanan tahap 2 selesai; sementara itu laporan WBS lewat surel/telepon SPI |
| Kapasitas VPS penuh oleh video | Unggah gagal | Batas 100 MB per video, pemantauan disk, opsi penyimpanan tambahan |
