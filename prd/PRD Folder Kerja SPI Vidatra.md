# PRD Folder Kerja SPI Vidatra

Nov 23, 2026 · @Luthfianda Salsabila

Dokumen kebutuhan produk untuk **Folder Kerja SPI Vidatra** (spi.vidatra.sch.id). Acuan: Rancangan Website Folder Kerja SPI (r0, 20 Nov 2026) dan Daftar Revisi Rancangan (r1, 23 Nov 2026, disetujui Pak Randy). Setiap kebutuhan diberi kode agar progres bisa dicocokkan dengan rancangan secara berkala.

## 1. Ringkasan Produk

**Masalah.** Dokumen audit dan bukti pendukung SPI tersebar di surel, WhatsApp, dan flashdisk. Versinya sulit dilacak, tidak ada jejak siapa yang membuka atau mengunduh dokumen sensitif, dan SPI tidak punya satu tempat untuk meminta dokumen ke unit serta memantau mana yang sudah diserahkan.

**Solusi.** Folder Kerja SPI adalah ruang kerja dokumen tertutup untuk Satuan Pengawas Internal. Dokumen disimpan per tahun, unit, dan penugasan; dibagikan sesuai peran; diminta ke unit lewat slot unggah bertenggat; dan setiap aksi tercatat di log audit yang tidak bisa diubah. Login hanya dengan akun @vidatra.sch.id.

**Tujuan:**

1. Semua dokumen penugasan SPI tersimpan terpusat dengan struktur folder baku.
2. Dokumen hanya bisa diakses oleh yang berhak (akses default ditolak).
3. Permintaan dan penyerahan dokumen antara SPI dan unit (SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian) berjalan di sistem, bukan di WhatsApp.
4. Kewenangan Super Admin terkendali: perubahan peran disetujui Dewan Pengawas, dan setiap aksi admin terdeteksi.

**Bukan tujuan (di luar cakupan):**

- Kanal pengaduan dan whistleblowing (ditangani SIGAP Vidatra).
- Penyuntingan dokumen langsung di browser; dokumen disunting di aplikasi masing-masing lalu diunggah sebagai versi baru.
- Akses pihak di luar yayasan dan aplikasi seluler native.
- Penilaian atau penjatuhan sanksi; sistem hanya menyimpan dokumen dan jejaknya.

## 2. Pengguna

Peran mengikuti Daftar Revisi r1: Kepala SPI dirangkap Dewan Pengawas (R1), Super Admin dipegang Admin IT Yayasan dengan pengendalian (R2, R3).

| Pengguna | Kebutuhan utama | Halaman |
| --- | --- | --- |
| Dewan Pengawas (Kepala SPI) | Memiliki seluruh ruang kerja SPI: membuat penugasan, mengatur akses, menyetujui berbagi dokumen Rahasia dan perubahan peran, meninjau log | H-03–H-12 |
| Auditor SPI | Mengelola dokumen penugasan yang ditugaskan dan meminta dokumen ke unit | H-03–H-09, H-14 |
| PIC Unit (kepala sekolah / staf TU / Pelaksana Harian) | Melihat permintaan untuk unitnya, mengunggah bukti, membaca laporan untuk unitnya | H-03, H-08, H-10 |
| Pengurus Yayasan | Membaca laporan final dan ringkasan temuan yang dibagikan, tanpa unduh (R7) | H-03, H-10 |
| Peninjau Tamu | Akses baca sementara ke folder tertentu | H-03, H-05, H-06 |
| Super Admin (Admin IT Yayasan) | Mengelola akun, unit, dan pengaturan; tidak membuka isi folder Rahasia | H-12, H-13 |

Dewan Pengawas dan Pengurus Yayasan dibuatkan akun @vidatra.sch.id lewat formulir permintaan akun terpisah (R4).

## 3. Peta Halaman

| Kode | Halaman | Isi pokok |
| --- | --- | --- |
| H-01 | Login | Tombol masuk dengan Google; tolak akun di luar domain atau tanpa peran |
| H-02 | Keamanan Akun | Aktivasi 2FA aplikasi, pernyataan kerahasiaan saat login pertama |
| H-03 | Dasbor | Ringkasan sesuai peran, pencarian global |
| H-04 | Penugasan | Daftar penugasan aktif dan arsip; buat penugasan baru |
| H-05 | Ruang Penugasan | Penjelajah folder baku, unggah, label klasifikasi, anggota tim |
| H-06 | Detail File | Pratinjau, versi, berbagi, riwayat akses |
| H-07 | Permintaan Dokumen (SPI) | Buat permintaan, pantau status slot, terima atau minta perbaikan |
| H-08 | Permintaan untuk Unit Saya | Daftar slot unggah untuk unit PIC |
| H-09 | Persetujuan | Antrean persetujuan Dewan Pengawas: berbagi Rahasia, perubahan peran, reset 2FA |
| H-10 | Laporan untuk Saya | Laporan final yang dibagikan, mode baca saja |
| H-11 | Log Audit | Penelusuran, verifikasi rantai hash, ekspor |
| H-12 | Pengguna dan Peran | Pengajuan perubahan peran, status persetujuan, cabut sesi |
| H-13 | Pengaturan | Unit, data pejabat, templat folder, jenis berkas yang diizinkan, batas ukuran |
| H-14 | Tempat Sampah | Berkas terhapus 30 hari terakhir |

## 4. Ukuran Keberhasilan

Target diukur dari data sistem sendiri, 3 bulan setelah peluncuran.

| Ukuran | Target |
| --- | --- |
| Penugasan baru yang dibuat dan dikelola di sistem | 100% |
| Permintaan dokumen ke unit yang lewat sistem (bukan WhatsApp/surel) | ≥ 90% |
| Slot permintaan yang diterima sebelum tenggat | ≥ 80% |
| Akses ke dokumen tanpa izin yang berhasil | 0 |
| Verifikasi rantai hash log audit yang gagal tanpa penjelasan | 0 |
| Uji pemulihan cadangan triwulanan yang berhasil | 100% |

## 5. Kebutuhan Fungsional

Kebutuhan ditulis sebagai user story dengan kriteria penerimaan yang bisa diuji. Kode US dipakai di matriks keterlacakan (Bagian 8). US-01 sampai US-30 masuk Tahap 1 (MVP); US-31 sampai US-34 masuk Tahap 2.

### M1 Akses dan akun

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-01 | Sebagai pengguna, saya ingin masuk dengan akun Google yayasan agar tidak perlu kata sandi baru | Hanya akun @vidatra.sch.id yang diterima; akun tanpa peran melihat pesan "belum diberi akses"; tidak ada login kata sandi lokal |
| US-02 | Sebagai Dewan Pengawas, Auditor SPI, atau Super Admin, saya wajib memakai 2FA aplikasi agar akun tetap aman walau admin Google mereset kata sandi | Aktivasi TOTP wajib saat login pertama sebelum halaman lain bisa dibuka; kunci TOTP disimpan terenkripsi; kode cadangan sekali pakai diberikan; reset 2FA hanya lewat persetujuan (US-22) |
| US-03 | Sebagai pengguna baru, saya diminta menyetujui pernyataan kerahasiaan sebelum memakai sistem | Pernyataan tampil sekali saat login pertama; persetujuan tercatat dengan waktu; teks pernyataan bisa diganti dari Pengaturan |
| US-04 | Sebagai pengguna, sesi saya berakhir otomatis jika tidak aktif | Sesi habis setelah 30 menit tidak aktif; Super Admin dan Dewan Pengawas bisa mencabut sesi aktif pengguna lain |

### M2 Penugasan dan folder

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-05 | Sebagai Dewan Pengawas, saya ingin membuat penugasan agar folder bakunya terbentuk otomatis | Nama mengikuti format `<tahun>-<nomor urut>-<unit>-<topik>`; tujuh subfolder baku terbentuk dari templat di Pengaturan; penugasan muncul di daftar aktif |
| US-06 | Sebagai Dewan Pengawas, saya ingin menunjuk auditor ke penugasan | Auditor yang ditunjuk langsung melihat penugasan; auditor lain tidak; penunjukan dan pencabutan tercatat di log |
| US-07 | Sebagai Dewan Pengawas atau Auditor, saya ingin mengelola folder dan label klasifikasinya | Buat, ubah nama, pindah, arsipkan subfolder; label Umum/Internal/Rahasia per folder dan file; file mewarisi label folder kecuali diubah |
| US-08 | Sebagai Dewan Pengawas, saya ingin menutup penugasan agar isinya terkunci | Penugasan tertutup menjadi baca saja, akses Peninjau Tamu dicabut, pindah ke Arsip; hanya Dewan Pengawas yang bisa membuka kembali, dan itu tercatat |

### M3 Berkas

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-09 | Sebagai Auditor, saya ingin mengunggah banyak berkas sekaligus | Drag and drop; hanya PDF, DOCX, XLSX, PPTX, JPG, PNG, ZIP; tipe dicek dari isi (MIME); maks. 50 MB per berkas (bisa diatur); berkas dipindai ClamAV dan baru bisa diunduh setelah bersih; berkas terinfeksi dikarantina |
| US-10 | Sebagai Auditor, saya ingin unggahan ulang tersimpan sebagai versi baru | Berkas dengan nama sama di folder yang sama menjadi versi berikutnya; versi lama bisa dilihat dan dipulihkan; tiap versi menyimpan hash SHA-256 dan pengunggah |
| US-11 | Sebagai pengguna berhak, saya ingin melihat pratinjau dan mengunduh berkas | Pratinjau PDF dan gambar di browser; unduhan lewat signed URL berumur 5 menit yang terikat sesi; tidak ada URL publik; setiap buka dan unduh tercatat |
| US-12 | Sebagai Yayasan, saya ingin berkas Rahasia terenkripsi dan tidak bisa dibuka Super Admin | Berkas berlabel Rahasia dienkripsi saat disimpan (AES-256) dengan kunci terpisah dari cadangan; nama simpan acak (UUID) di luar web root; Super Admin tidak bisa membuka isi folder Rahasia lewat aplikasi |
| US-13 | Sebagai Dewan Pengawas, saya ingin berkas terhapus bisa dipulihkan | Berkas terhapus masuk Tempat Sampah 30 hari; hanya Dewan Pengawas yang memulihkan atau menghapus permanen; Auditor hanya bisa menghapus berkas miliknya |
| US-14 | Sebagai pengguna, saya ingin mencari berkas | Cari berdasarkan nama, label, unit, tahun, penugasan; hasil hanya memuat yang boleh diakses pencari |

### M4 Permintaan dokumen

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-15 | Sebagai Auditor, saya ingin meminta dokumen ke unit dengan tenggat | Pilih unit, isi daftar dokumen (nama, keterangan, format), tenggat; slot unggah per dokumen terbentuk; PIC unit menerima surel |
| US-16 | Sebagai PIC Unit, saya ingin mengunggah dokumen yang diminta | PIC hanya melihat permintaan untuk unitnya; unggah langsung ke slot; status slot menjadi Diunggah; auditor diberi tahu |
| US-17 | Sebagai Auditor, saya ingin menerima atau meminta perbaikan | Tandai Diterima atau Perlu Perbaikan dengan catatan wajib; unggahan ulang menjadi versi baru; slot Diterima disalin ke folder 04 Bukti Unit dan terkunci untuk PIC |
| US-18 | Sebagai sistem, saya harus mengingatkan tenggat | Pengingat surel ke PIC pada H-3 dan saat tenggat; slot lewat tenggat berstatus Terlambat di dasbor SPI |

### M5 Berbagi dan laporan

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-19 | Sebagai Dewan Pengawas atau Auditor, saya ingin membagikan folder atau berkas dengan batas waktu | Pilih penerima (orang atau peran), hak baca/unggah/kelola, tanggal kedaluwarsa; Auditor hanya bisa berbagi ke sesama tim SPI; akses berakhir otomatis pada tanggal kedaluwarsa |
| US-20 | Sebagai Dewan Pengawas, saya ingin menyetujui setiap berbagi dokumen Rahasia ke luar tim SPI | Berbagi Rahasia ke Pengurus, PIC, atau Peninjau Tamu berstatus Menunggu persetujuan di H-09; aktif hanya setelah disetujui dengan kode 2FA |
| US-21 | Sebagai Pengurus Yayasan atau PIC Unit, saya ingin membaca laporan final yang dibagikan kepada saya | Mode baca saja di browser: tanpa tombol unduh atau cetak, rute unduh menolak peran ini; watermark nama, surel, dan waktu di setiap halaman |

### M6 Pengendalian Super Admin dan audit

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-22 | Sebagai Ketua Dewan Pengawas, saya ingin setiap perubahan peran dan reset 2FA menunggu persetujuan saya | Super Admin hanya bisa mengajukan; status Menunggu persetujuan sampai disetujui dengan kode 2FA; ditolak atau lewat 7 hari → batal; sesuai Pasal 20 Peraturan WBS |
| US-23 | Sebagai Dewan Pengawas dan tim SPI, saya ingin diberi tahu otomatis atas aksi sensitif | Surel berisi siapa, apa, kapan, dan IP untuk: pengajuan/perubahan peran, reset 2FA, login Super Admin, pencabutan sesi; tetap tercatat di log walau surel gagal |
| US-24 | Sebagai Dewan Pengawas, saya ingin log audit tidak bisa diubah tanpa ketahuan | Log hanya bisa ditambah; tiap baris menyimpan hash baris sebelumnya; tidak ada fitur ubah atau hapus log di aplikasi, termasuk untuk Super Admin; verifikasi rantai bisa dijalankan dari H-11 |
| US-25 | Sebagai Dewan Pengawas, saya ingin salinan log tersimpan di luar server | Ekspor log harian otomatis ke folder Google Drive milik akun Dewan Pengawas; Super Admin tidak punya hak hapus di folder itu; kegagalan ekspor memicu surel peringatan |
| US-26 | Sebagai Dewan Pengawas, saya ingin menelusuri dan mengekspor log audit | Filter per pengguna, aksi, objek, rentang waktu; ekspor CSV/Excel; Auditor hanya melihat log penugasannya; Super Admin hanya melihat log sistem |
| US-27 | Sebagai Dewan Pengawas, saya ingin diingatkan meninjau log admin Google | Pengingat surel tiap 3 bulan; H-11 menyediakan kolom catatan hasil tinjauan yang tersimpan di log |

### M7 Dasbor, notifikasi, dan pengaturan

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-28 | Sebagai pengguna, saya ingin dasbor sesuai peran | Dewan Pengawas/Auditor: penugasan aktif, slot tertunda dan terlambat, persetujuan menunggu, berkas terbaru; PIC: permintaan untuk unitnya; Pengurus: laporan yang dibagikan |
| US-29 | Sebagai Super Admin, saya ingin mengelola unit, data pejabat, dan templat tanpa mengubah kode | Unit, nama dan surel pejabat, templat subfolder, jenis berkas, batas ukuran, dan teks pernyataan kerahasiaan tersimpan di basis data dan bisa diubah di H-13 |
| US-30 | Sebagai pengguna, saya ingin notifikasi surel yang relevan | Surel untuk permintaan baru, unggahan, tenggat, berbagi, dan persetujuan; dikirim lewat antrean dari akun notifikasi Google Workspace |

### Tahap 2

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-31 | Sebagai Auditor dan PIC, saya ingin berkomentar pada berkas untuk klarifikasi | Komentar per berkas, terlihat sesuai hak akses berkas, tercatat di log |
| US-32 | Sebagai Dewan Pengawas, saya ingin laporan aktivitas berkala | Ekspor Excel/PDF status permintaan dan aktivitas per penugasan dan per unit |
| US-33 | Sebagai pengguna, saya ingin mencari teks di dalam dokumen | Pencarian isi PDF dan dokumen Office, tetap dibatasi hak akses |
| US-34 | Sebagai Dewan Pengawas, saya ingin watermark pada unduhan PDF Rahasia | PDF Rahasia yang diunduh diberi watermark nama, surel, dan waktu pengunduh |

## 6. Kebutuhan Non-Fungsional

| Kode | Aspek | Kebutuhan |
| --- | --- | --- |
| NF-01 | Keamanan | HTTPS wajib (TLS 1.2+, HSTS); RBAC + izin folder dicek di server pada setiap permintaan; OWASP Top 10; rate limit login, unggah, unduh; CSP dan header keamanan |
| NF-02 | Privasi | Sesuai UU 27/2022; subdomain tidak ditautkan dari website yayasan; `noindex` dan `robots.txt` menolak mesin pencari (R5) |
| NF-03 | Kinerja | Halaman terbuka < 2 detik; unggah 10 MB < 30 detik di koneksi kantor |
| NF-04 | Ketersediaan | Uptime 99% pada jam kerja 07.00–17.00 WITA; cadangan harian terenkripsi di luar VPS, retensi 30 hari; RPO 24 jam, RTO < 4 jam; uji pemulihan tiap 3 bulan |
| NF-05 | Kegunaan | Responsif di laptop dan HP; bahasa Indonesia |
| NF-06 | Retensi | Log audit disimpan 10 tahun sesuai draf Piagam SPI (R6); masa simpan dokumen arsip menunggu keputusan |
| NF-07 | Audit | Log append-only dengan rantai hash dan salinan harian di luar server |
| NF-08 | Infrastruktur | VPS Rumahweb L (2 vCPU, 4 GB, 80 GB) bersama SIGAP pada srv1.vidatra.sch.id untuk uji coba (R8); pengguna sistem, database, dan pool PHP-FPM terpisah dari SIGAP; ClamAV dan Redis dipakai bersama; upgrade ke XL bila RAM rutin > 80%, disk > 70%, atau saat dipakai semua unit |
| NF-09 | Pemeliharaan | Laravel 13, Inertia v3 + React 19, Filament 5, MySQL 8, Laravel Socialite; kode di Yayasan-Vidatra/spi-vidatra dengan panduan instalasi |

## 7. Rilis dan Tahapan

Durasi mengikuti Rancangan Bagian 10 (pengembangan MVP 5 minggu). Minggu diganti tanggal kalender setelah tanggal mulai ditetapkan.

| Rilis | Isi | Kapan | Syarat lolos |
| --- | --- | --- | --- |
| Demo 1 | Fondasi, login SSO + 2FA, pernyataan kerahasiaan, penugasan dan folder baku, unggah dan versi berkas | Akhir minggu 2 | US-01–US-11 lolos |
| Demo 2 | Enkripsi Rahasia, tempat sampah, pencarian, permintaan dokumen, berbagi dan persetujuan, laporan baca saja | Akhir minggu 4 | US-12–US-21 lolos |
| Serah terima Tahap 1 | Pengendalian Super Admin, log audit berantai dan ekspor, dasbor, pengaturan, notifikasi; UAT bersama SPI dan satu unit | Akhir minggu 5, lalu UAT 2 minggu | US-01–US-30 lolos; berita acara UAT |
| Tahap 2 | Komentar, laporan aktivitas, pencarian isi, watermark unduhan | Setelah evaluasi 1 bulan | US-31–US-34 lolos |

## 8. Pemantauan Kesesuaian dengan Rancangan

Progres dicek pada tiga tingkat agar penyimpangan dari Rancangan r0 dan Daftar Revisi r1 ketahuan sebelum menumpuk. Status di matriks diperbarui langsung di dokumen ini.

### Jadwal pengecekan

| Kapan | Siapa | Yang dicek | Hasil |
| --- | --- | --- | --- |
| Setiap akhir hari kerja | Pengembang | Status US yang dikerjakan hari itu; hambatan | Matriks diperbarui |
| Demo 1, Demo 2, serah terima | Pengembang dan pimpinan | US target rilis dicoba langsung terhadap kriteria penerimaan dan daftar periksa di bawah | Status menjadi Sesuai rancangan atau Ada deviasi; catatan demo |
| Setiap ada perubahan dari rancangan | Pengembang, disetujui pimpinan | Alasan dan dampak perubahan | Baris baru di log deviasi sebelum dikerjakan |
| Sebelum dipakai semua unit | Pimpinan dan Dewan Pengawas | Semua US Tahap 1 Sesuai rancangan; uji akses antar-peran bersih | Berita acara serah terima |

### Daftar periksa setiap demo

- [ ] Setiap US target rilis lolos kriteria penerimaannya saat dicoba langsung, bukan hanya dilaporkan.
- [ ] Hak akses sesuai matriks (Rancangan Bagian 4 dengan revisi R1, R7): PIC tidak melihat unit lain; Super Admin tidak membuka isi Rahasia; Pengurus tidak bisa mengunduh, termasuk lewat URL langsung.
- [ ] Akses lewat URL langsung ke folder, berkas, dan rute unduh yang tidak dibagikan ditolak.
- [ ] Setiap aksi berkas, akses, dan peran tercatat di log; verifikasi rantai hash lolos.
- [ ] Struktur data sesuai Rancangan Bagian 9.2; perubahan tabel tercatat di log deviasi.
- [ ] Tidak ada fitur di luar cakupan yang ditambahkan tanpa log deviasi.

### Matriks keterlacakan

Acuan: F, UC, dan nomor bagian dari Rancangan r0; R dari Daftar Revisi r1.

| Kode | Acuan rancangan | Halaman | Target | Status | Catatan |
| --- | --- | --- | --- | --- | --- |
| US-01 | F-01, UC-01, R4 | H-01 | Demo 1 | Sesuai rancangan |  |
| US-02 | Bagian 6.1, R3 | H-02 | Demo 1 | Sesuai rancangan |  |
| US-03 | Bagian 6.8 | H-02 | Demo 1 | Sesuai rancangan | Penyuntingan teks via H-13 menyusul di US-29 |
| US-04 | Bagian 6.1 | H-12 | Demo 1 | Sebagian | Cabut sesi dipindah ke Serah terima (H-12) |
| US-05 | F-03, UC-03, Bagian 9.1 | H-04, H-05 | Demo 1 | Sesuai rancangan |  |
| US-06 | UC-04 | H-05 | Demo 1 | Sesuai rancangan |  |
| US-07 | F-03, F-07 | H-05 | Demo 1 | Sesuai rancangan |  |
| US-08 | UC-13 | H-04 | Demo 1 | Sesuai rancangan | Cabut akses Peninjau Tamu menyusul fitur berbagi (US-19) |
| US-09 | F-04, Bagian 6.4 | H-05 | Demo 1 | Sesuai rancangan | Batas ukuran dan jenis berkas dari config (BERKAS_MAX_SIZE_MB) sampai US-29; syarat clamd.conf dan queue worker di README bagian Deploy |
| US-10 | F-05 | H-06 | Demo 1 | Sesuai rancangan |  |
| US-11 | F-04, Bagian 6.2 | H-06 | Demo 1 | Sesuai rancangan | "Buka" dicatat saat isi berkas ditampilkan di pratinjau |
| US-12 | Bagian 6.3, R2 | H-05, H-06 | Demo 2 | Belum mulai |  |
| US-13 | F-12 | H-14 | Demo 2 | Belum mulai |  |
| US-14 | F-11 | H-03 | Demo 2 | Belum mulai |  |
| US-15 | F-08, UC-06 | H-07 | Demo 2 | Belum mulai |  |
| US-16 | F-08, UC-07 | H-08 | Demo 2 | Belum mulai |  |
| US-17 | UC-08 | H-07 | Demo 2 | Belum mulai |  |
| US-18 | F-10, KF-08 | H-03, H-07 | Demo 2 | Belum mulai |  |
| US-19 | F-06, UC-09 | H-06 | Demo 2 | Belum mulai |  |
| US-20 | UC-10, R1 | H-09 | Demo 2 | Belum mulai |  |
| US-21 | UC-11, R7 | H-10 | Demo 2 | Belum mulai |  |
| US-22 | R3 | H-09, H-12 | Serah terima | Belum mulai |  |
| US-23 | R3 | — | Serah terima | Belum mulai |  |
| US-24 | F-09, Bagian 6.7, R3 | H-11 | Serah terima | Belum mulai | Tabel audit_logs dan pencatatan dasar berantai hash sudah dibuat di M2; UI log dan verifikasi rantai menyusul |
| US-25 | Bagian 6.7, R3 | — | Serah terima | Belum mulai |  |
| US-26 | UC-12 | H-11 | Serah terima | Belum mulai |  |
| US-27 | R3 | H-11 | Serah terima | Belum mulai |  |
| US-28 | F-02 | H-03 | Serah terima | Belum mulai |  |
| US-29 | UC-14 | H-13 | Serah terima | Belum mulai |  |
| US-30 | F-10 | — | Serah terima | Belum mulai |  |
| US-31 | F-13 | H-06 | Tahap 2 | Belum mulai |  |
| US-32 | F-15 | H-11 | Tahap 2 | Belum mulai |  |
| US-33 | F-16 | H-03 | Tahap 2 | Belum mulai |  |
| US-34 | F-14 | H-06 | Tahap 2 | Belum mulai |  |

### Log deviasi

Setiap perbedaan dari Rancangan r0 dicatat di sini dan disetujui sebelum dikerjakan. Baris di bawah berasal dari Daftar Revisi r1.

| Tanggal | Kode terkait | Perubahan dari rancangan | Alasan | Disetujui oleh |
| --- | --- | --- | --- | --- |
| 2026-11-23 | US-20, US-22, semua peran | Peran Kepala SPI dirangkap Dewan Pengawas (R1) | Yayasan belum punya Kepala SPI | Pak Randy |
| 2026-11-23 | US-12, US-22 | Super Admin = Admin IT Yayasan dengan pengendalian (R2) | Penegasan pemegang peran | Pak Randy |
| 2026-11-23 | US-02, US-22–US-25, US-27 | Ditambah 2FA aplikasi, persetujuan peran oleh Ketua Dewan Pengawas, peringatan otomatis, log berantai hash dan salinan luar server, tinjauan log Google 3 bulanan (R3) | Mengendalikan kewenangan Super Admin; Pasal 20 Peraturan WBS | Pak Randy |
| 2026-11-23 | US-01 | Dewan Pengawas dan Pengurus dibuatkan akun @vidatra.sch.id lewat formulir terpisah (R4) | Login hanya untuk akun domain | Pak Randy |
| 2026-11-23 | NF-02 | Subdomain tidak dipublikasikan; noindex (R5) | Mengurangi paparan sistem internal | Pak Randy |
| 2026-11-23 | NF-06 | Log audit disimpan 10 tahun, bukan 5 tahun (R6) | Mengikuti draf Piagam SPI | Pak Randy |
| 2026-11-23 | US-21 | Pengurus Yayasan hanya melihat laporan final, tanpa unduh (R7) | Keputusan pimpinan | Pak Randy |
| 2026-11-23 | NF-08 | VPS dipakai bersama SIGAP, paket L untuk uji coba, upgrade ke XL bila perlu (R8) | Biaya awal lebih rendah; upgrade Rumahweb pro-rata tanpa migrasi data | Pak Randy |
| 2026-11-23 | US-22 | Perintah bootstrap akun:tetapkan-peran untuk Super Admin dan Dewan Pengawas pertama, di luar alur persetujuan | Belum ada Dewan Pengawas untuk menyetujui saat sistem pertama dipasang; hanya bisa dijalankan sekali per peran lewat SSH | (menunggu persetujuan) |
| 2026-11-23 | US-22 | DemoAccountSeeder menetapkan peran akun uji dari DEMO_ACCOUNTS di luar alur persetujuan, kecuali menetapkan atau mengubah peran super-admin; jalur sementara khusus non-produksi (hanya APP_ENV local/staging, menolak di production), setiap penetapan tercatat di audit_logs | Uji coba M2 butuh akun Auditor SPI sebelum alur persetujuan peran (US-22) selesai; dihapus setelah US-22 selesai | (menunggu persetujuan) |

## 9. Asumsi dan Pertanyaan Terbuka

**Asumsi:**

- Pengembang satu orang, mengerjakan MVP dalam 5 minggu di atas starter Laravel + Inertia + Filament yang sudah ada di repo.
- VPS SIGAP (paket L, srv1.vidatra.sch.id) tersedia sebelum Demo 1; selama belum ada, pengembangan berjalan lokal di Laragon.
- Nama pejabat, unit, dan anggota tim SPI diinput lewat Pengaturan, bukan ditanam di kode.
- Akun Google Workspace untuk Dewan Pengawas dan Pengurus sudah dibuat sebelum UAT.

**Pertanyaan terbuka:**

- [ ] Peran Kepala SPI dipegang Ketua Dewan Pengawas saja, atau semua anggota Dewan Pengawas.
- [ ] Masa simpan dokumen arsip penugasan (log audit sudah 10 tahun).
- [ ] Pemegang kata sandi root VPS: Admin IT, atau disimpan tersegel oleh Dewan Pengawas.
- [ ] Templat subfolder dan jenis dokumen final dari SPI.
- [ ] Tanggal mulai pengerjaan, untuk mengganti minggu dengan tanggal kalender.
