# Rencana Pengerjaan sampai Deploy

Nov 24, 2026 · @Luthfianda Salsabila

Urutan kerja setelah Demo 1 (US-01 sampai US-11, selesai) sampai aplikasi siap dipakai di VPS. Setiap langkah dikerjakan di branch sendiri, diuji manual di browser, lalu masuk lewat PR. Acuan isi: PRD Folder Kerja SPI Vidatra dan Rancangan r1. Dokumen ini hanya mengatur urutan, keputusan yang sudah diambil, dan hal yang perlu diputuskan sebelum mulai.

## Cara memakai dokumen ini

Untuk setiap langkah, minta Claude Code: "Baca prd/Rencana Pengerjaan sampai Deploy.md, kerjakan Langkah N. Mulai dengan rencana singkat dan daftar keputusan yang perlu saya ambil sebelum menulis kode."

Aturan yang berlaku untuk semua langkah:

- Ikuti CLAUDE.md, skills di `.claude/skills`, dan pola yang sudah ada (Policy, AuditLogger di transaksi yang sama, enum, factory, test di MySQL).
- Jangan tambah dependensi tanpa persetujuan.
- Feature test untuk setiap kriteria penerimaan, termasuk akses antar-peran dan URL langsung. Lalu `vendor/bin/pint --dirty --format agent` dan `npm run build`.
- Perbedaan dari PRD atau Rancangan: berhenti dan tanya dulu. Rincian implementasi tidak perlu log deviasi; perubahan perilaku atau aturan akses perlu.
- Setelah selesai: perbarui kolom Catatan di matriks PRD, commit dengan pesan berbahasa Indonesia yang menyebut kode US, push, lalu PR.

## Ringkasan urutan

| Langkah | Isi | User story | Rilis |
| --- | --- | --- | --- |
| 1 | Enkripsi Rahasia, tempat sampah, pencarian | US-12, US-13, US-14 | Demo 2 |
| 2 | Unit pengguna dan permintaan dokumen | US-15 sampai US-18 | Demo 2 |
| 3 | Berbagi, persetujuan Rahasia, laporan baca saja | US-19, US-20, US-21 | Demo 2 |
| 4 | Pengendalian Super Admin dan log audit | US-22 sampai US-27 | Serah terima |
| 5 | Dasbor, pengaturan, notifikasi, cabut sesi | US-28, US-29, US-30, US-04 | Serah terima |
| 6 | Kesiapan rilis: keamanan, cadangan, CI, skrip deploy | NF-01 sampai NF-09 | Serah terima |
| 7 | Deploy ke VPS (staging untuk UAT, lalu produksi) | Semua Tahap 1 | Peluncuran |

## Langkah 1: Enkripsi Rahasia, tempat sampah, pencarian (US-12 sampai US-14)

- **US-12:** isi berkas berlabel Rahasia, dan semua berkas di bawah folder Rahasia, dienkripsi saat disimpan (AES-256-GCM, kunci dari berkas terpisah dari `APP_KEY` dan tidak ikut cadangan). Mengubah label berkas atau folder ke atau dari Rahasia, atau memindah folder ke dalam atau keluar dari induk Rahasia, mengenkripsi atau mendekripsi ulang semua versi di job antrean, tercatat di log. Isi Rahasia dienkripsi langsung saat unggah; job pindai mendekripsi sambil mengalirkan isi asli ke clamd, sehingga clamd memindai isi asli tanpa salinan polos di disk. Super Admin tidak pernah bisa membuka isi folder atau berkas Rahasia.
- **US-13:** hapus berkas memindahkannya ke Tempat Sampah (H-14) selama 30 hari. Auditor hanya bisa menghapus berkas yang semua versinya ia unggah; hanya Dewan Pengawas yang memulihkan atau menghapus permanen. Penghapusan permanen otomatis setelah 30 hari lewat scheduler. Semua tercatat di log.
- **US-14:** pencarian global di header berdasarkan nama berkas, label, unit, tahun, penugasan; hasil hanya yang boleh diakses pencari (difilter di query, bukan setelah diambil).
- **Keputusan (24 Nov 2026):**
  1. Kunci di server: `/etc/spi-vidatra/berkas-rahasia.key`, milik pengguna `spi`, hak 0400, salinannya dipegang Dewan Pengawas. Lokal: `storage/berkas-rahasia.key`. Dibuat dengan `php artisan berkas:buat-kunci`.
  2. Yang dienkripsi: berkas berlabel Rahasia dan semua berkas di bawah folder Rahasia, termasuk lewat ubah label folder dan pindah folder. Ini memperketat PRD, jadi cukup dicatat di matriks US-12, tanpa log deviasi.
  3. Isi Rahasia dienkripsi langsung saat unggah; pemindaian mendekripsi sambil mengalirkan isi asli ke clamd.
  4. Auditor hanya bisa menghapus berkas jika semua versinya diunggah olehnya.
  5. Auditor melihat H-14 untuk penugasannya saja (aturan Rahasia sama), tanpa pulihkan atau hapus permanen. Sesuai Peta Halaman PRD.
  6. Unggahan baru dengan nama sama seperti berkas di Tempat Sampah menjadi berkas terpisah (nama unik hanya untuk berkas aktif). Saat pulihkan bentrok nama, berkas dipulihkan dengan akhiran "(dipulihkan YYYY-MM-DD)".
  - Tambahan: tanpa kunci, unggah dan buka berkas Rahasia, ubah label ke atau dari Rahasia, pindah folder ke dalam atau keluar dari induk Rahasia, serta pulihkan versi berkas Rahasia ditolak, agar job enkripsi ulang tidak gagal di tengah.

## Langkah 2: Unit pengguna dan permintaan dokumen (US-15 sampai US-18)

- Tambah `users.unit_id` (wajib untuk PIC Unit) dan cara menetapkannya. Sampai US-22/US-29 selesai, unit ditetapkan lewat DemoAccountSeeder (format `email:peran:kode-unit`) di non-produksi.
- **US-15:** auditor membuat permintaan ke satu unit: daftar dokumen (nama, keterangan, format yang diterima), tenggat. Slot unggah per dokumen terbentuk di folder 02 Permintaan Dokumen.
- **US-16:** PIC hanya melihat permintaan unitnya (H-08), mengunggah ke slot memakai alur unggah M3 (MIME, ukuran, antivirus, versi). Status slot menjadi Diunggah.
- **US-17:** auditor menandai Diterima atau Perlu Perbaikan (catatan wajib). Unggahan ulang menjadi versi baru. Slot Diterima disalin ke 04 Bukti Unit dan terkunci untuk PIC.
- **US-18:** status Terlambat dihitung dari tenggat; pengingat H-3 (3 hari kerja) dan hari-H lewat scheduler. Surel dikirim di Langkah 5; di sini cukup event/notifikasi yang nanti dipakai surel, plus tampilan di dasbor sementara.
  - Tambahan r2 (P5, F-21, KF-12, UC-17): slot yang belum Diterima 10 hari kerja setelah tenggat diteruskan ke Dewan Pengawas, tercatat di tabel escalations dan log, tampil di dasbor Dewan Pengawas sampai Diterima. Hari kerja = Senin–Jumat dikurangi tabel holidays (diisi HolidaySeeder sampai Pengaturan di US-29).
  - Ubah tenggat dengan alasan wajib, untuk sementara oleh Auditor penugasan dan Dewan Pengawas. Kepala SPI (pemberitahuan eskalasi dan ubah tenggat) menyusul di Langkah 2b.
- **Perlu diputuskan:** apakah satu unit boleh punya lebih dari satu PIC (rekomendasi: boleh).

## Langkah 3: Berbagi, persetujuan Rahasia, laporan baca saja (US-19 sampai US-21)

- **US-19:** bagikan folder atau berkas ke pengguna atau peran, hak baca/unggah/kelola, tanggal kedaluwarsa wajib. Auditor hanya ke sesama tim SPI. Akses berakhir otomatis. Penutupan penugasan mencabut akses Peninjau Tamu (melengkapi US-08).
- **US-20:** berbagi berkas/folder Rahasia ke luar tim SPI menunggu persetujuan Dewan Pengawas di H-09, disetujui dengan kode 2FA (memakai TwoFactorAuthenticator yang ada).
- **US-21:** Pengurus Yayasan dan PIC membaca laporan yang dibagikan di H-10 dalam mode baca saja: tanpa tombol unduh atau cetak, rute unduh menolak peran ini, watermark nama, surel, dan waktu di setiap halaman (R7).
- **Perlu diputuskan:** cara watermark tanpa dependensi baru. Pilihan: (a) render halaman PDF ke gambar di server dengan Imagick/Ghostscript lalu tumpuk teks, (b) pdf.js di browser dengan lapisan watermark (lebih ringan tetapi isi PDF asli tetap terkirim ke browser). Rekomendasi: (a) bila Imagick tersedia di VPS, karena PDF asli tidak pernah sampai ke browser Pengurus. Keduanya butuh persetujuan dependensi atau ekstensi.

Setelah Langkah 3: **Demo 2** ke pimpinan (lokal atau staging).

## Langkah 4: Pengendalian Super Admin dan log audit (US-22 sampai US-27)

- **US-22:** pengajuan perubahan peran dan reset 2FA oleh Super Admin (H-12), persetujuan Ketua Dewan Pengawas dengan kode 2FA (H-09), batal setelah 7 hari. Setelah ini ada, DemoAccountSeeder tidak dibutuhkan lagi untuk produksi.
- **US-23:** surel peringatan untuk pengajuan/perubahan peran, reset 2FA, login Super Admin, pencabutan sesi. Tetap tercatat walau surel gagal.
- **US-24:** perintah dan halaman verifikasi rantai hash (H-11). Tutup celah dua baris pertama tanpa `previous_hash` saat tabel masih kosong (misalnya baris genesis saat migrasi).
- **US-25:** ekspor log harian ke folder Google Drive milik akun Dewan Pengawas; kegagalan memicu surel.
- **US-26:** H-11 Log Audit: filter, ekspor CSV/Excel; Auditor hanya log penugasannya, Super Admin hanya log sistem.
- **US-27:** pengingat tinjauan log admin Google tiap 3 bulan dan catatan hasil tinjauan.
- **Perlu diputuskan:** cara ekspor ke Google Drive. Pilihan: (a) service account Google Cloud di project Vidatra Internal Apps, folder Drive milik Dewan Pengawas dibagikan ke service account dengan hak Editor (bukan pemilik), upload lewat REST API dengan JWT yang ditandatangani sendiri (tanpa paket), (b) paket `google/apiclient` (besar). Rekomendasi: (a). Catatan: dengan hak Editor, service account bisa menghapus berkas yang ia unggah; untuk benar-benar mencegah penghapusan oleh server, aktifkan versi berkas di Drive atau gunakan Shared Drive dengan peran Contributor.

## Langkah 5: Dasbor, pengaturan, notifikasi, cabut sesi (US-28 sampai US-30, US-04)

- **US-28:** dasbor per peran (Dewan Pengawas/Auditor, PIC, Pengurus) menggantikan placeholder.
- **US-29:** H-13 Pengaturan di panel Filament (Super Admin): unit, data pejabat, templat subfolder, jenis berkas dan batas ukuran (pindahkan dari config ke basis data dengan nilai default dari config), teks pernyataan kerahasiaan (versi baru setiap diubah). `roles.requires_two_factor` tetap tidak bisa diubah lewat UI.
- **US-30:** surel lewat antrean untuk permintaan baru, unggahan, tenggat (H-3 dan hari-H), berbagi, dan persetujuan, dari akun notifikasi Google Workspace.
- **US-04 (sisa):** H-12 daftar sesi aktif dan cabut sesi oleh Super Admin dan Dewan Pengawas, tercatat di log dan memicu surel US-23.
- **Perlu diputuskan:** akun pengirim surel (rekomendasi: buat `noreply-spi@vidatra.sch.id` di Google Workspace; kirim lewat SMTP relay Google Workspace yang dibatasi IP VPS, atau sandi aplikasi bila relay tidak bisa diatur).

## Langkah 6: Kesiapan rilis

- **Keamanan:** Content-Security-Policy, HSTS, X-Frame-Options, Referrer-Policy lewat middleware; halaman galat 403/404/419/429/500 berbahasa Indonesia tanpa detail teknis, mengikuti tampilan aplikasi (saat ini masih halaman bawaan Laravel, misalnya "This action is unauthorized.", temuan uji browser US-15); cek `APP_DEBUG=false` di produksi; `composer audit` dan `npm audit` bersih.
- **Cadangan (NF-04):** cadangan harian basis data dan berkas, terenkripsi, ke luar VPS, retensi 30 hari; perintah uji pulihkan; kunci enkripsi berkas Rahasia tidak ikut cadangan. **Perlu diputuskan:** tujuan cadangan (rekomendasi: Google Drive Shared Drive yayasan lewat mekanisme yang sama dengan Langkah 4, atau object storage) dan apakah memakai paket (`spatie/laravel-backup`) atau skrip sendiri.
- **CI:** GitHub Actions dengan service MySQL 8 menjalankan seluruh test, Pint (`--test`), dan `npm run build` di setiap PR.
- **Skrip deploy:** folder `deploy/` berisi skrip pemasangan untuk Ubuntu 24.04 yang **berbagi server dengan SIGAP** (srv1.vidatra.sch.id): pengguna sistem `spi`, basis data dan pengguna MySQL sendiri, pool PHP-FPM sendiri, vhost Nginx `spi.vidatra.sch.id` dengan `client_max_body_size 64M`, Let's Encrypt, Supervisor untuk queue worker, cron untuk scheduler, `robots.txt` dan header noindex. Skrip tidak boleh mengubah konfigurasi SIGAP; bagian bersama (PHP, MySQL, Redis, ClamAV dengan StreamMaxLength 105M) hanya diperiksa, dan dipasang bila belum ada. Skrip pembaruan (pull, composer install --no-dev, migrate --force, build, `php artisan queue:restart` wajib di setiap deploy agar worker tidak menjalankan kode lama) terpisah dari skrip pemasangan.
- **Dokumen operasional:** README bagian Deploy dilengkapi langkah lengkap, termasuk bootstrap Super Admin dan Dewan Pengawas pertama dan tempat menyimpan kunci enkripsi.

## Langkah 7: Deploy ke VPS

1. **Beli VPS** Rumahweb paket L, 1 tahun, Ubuntu 24.04, lokasi DCI, hostname `srv1.vidatra.sch.id` (dipakai bersama SIGAP). Pembayaran oleh yayasan.
2. **DNS:** A record `spi.vidatra.sch.id` ke IP VPS. Surel Google Workspace tidak terpengaruh.
3. **Google Cloud:** di OAuth client, tambahkan redirect URI `https://spi.vidatra.sch.id/auth/google/callback` (atau buat client baru "SPI Vidatra (produksi)").
4. **Pasang** dengan skrip Langkah 6, `APP_ENV=staging` untuk UAT. Isi `.env` server (bukan dari repo), kunci enkripsi berkas, `BERKAS_SCANNER=clamav`.
5. **Bootstrap peran:** `akun:tetapkan-peran` untuk Super Admin dan Dewan Pengawas yang sebenarnya; akun uji lewat DemoAccountSeeder (staging saja).
6. **UAT 2 minggu** bersama SPI dan satu unit memakai daftar periksa demo di PRD Bagian 8; hasilnya berita acara UAT.
7. **Peluncuran:** `APP_ENV=production`, `APP_DEBUG=false`; hapus atau tangguhkan akun uji dan data uji; aktifkan cadangan dan uji pulihkan sekali; pelatihan pengguna; SOP disahkan.

## Keputusan yang bisa diambil sekarang

Supaya pengerjaan tidak tertahan, keputusan berikut sebaiknya dijawab sebelum Langkah 1:

- [x] Penyimpanan kunci enkripsi berkas Rahasia di server (Langkah 1).
- [ ] Satu unit boleh punya lebih dari satu PIC (Langkah 2).
- [ ] Cara watermark laporan untuk Pengurus (Langkah 3).
- [ ] Cara ekspor log ke Google Drive (Langkah 4).
- [ ] Akun pengirim surel dan cara kirimnya (Langkah 5).
- [ ] Tujuan dan cara cadangan (Langkah 6).
- [ ] Pertanyaan terbuka PRD Bagian 9: Kepala SPI = Ketua Dewan Pengawas saja atau semua anggota; masa simpan dokumen arsip; pemegang kata sandi root VPS; teks final pernyataan kerahasiaan; templat subfolder final.
