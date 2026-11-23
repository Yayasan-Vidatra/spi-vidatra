# Rancangan Website Folder Kerja SPI Yayasan Vidatra (r1)

Nov 23, 2026 · @Luthfianda Salsabila

Revisi r1 memuat seluruh masukan pimpinan atas r0: Kepala SPI dirangkap Dewan Pengawas, kewenangan Super Admin dikendalikan, Pengurus Yayasan hanya membaca laporan, log audit disimpan 10 tahun, dan aplikasi berjalan di VPS bersama SIGAP Vidatra.

| Versi | Tanggal | Perubahan |
| --- | --- | --- |
| r1 | 23 Nov 2026 | Memasukkan 8 revisi hasil tinjauan pimpinan (R1–R8); keputusan yang sebelumnya terbuka di Bagian 10 sudah diputuskan |
| r0 | 20 Nov 2026 | Rancangan awal |

Kode revisi R1–R8 dipakai juga di log deviasi PRD Folder Kerja SPI Vidatra.

## 1. Pendahuluan

Website Folder Kerja SPI adalah ruang kerja dokumen tertutup untuk Satuan Pengawas Internal (SPI) Yayasan Vidatra. Website ini dipakai untuk menyimpan, berbagi, dan meminta dokumen pengawasan secara aman antara SPI, unit sekolah, dan pengurus yayasan. Akses hanya lewat akun Google Workspace yayasan (@vidatra.sch.id).

### 1.1 Latar belakang

- Dokumen audit dan bukti pendukung tersebar di surel, WhatsApp, dan flashdisk, sehingga versinya sulit dilacak.
- Tidak ada jejak siapa yang membuka, mengunduh, atau mengubah dokumen sensitif.
- SPI perlu satu tempat untuk meminta dokumen ke unit dan memantau mana yang sudah atau belum diserahkan.

### 1.2 Tujuan

1. Menyediakan repositori dokumen SPI yang terpusat, terstruktur per tahun, unit, dan penugasan.
2. Membatasi akses dokumen sesuai peran dan kebutuhan (prinsip *least privilege*).
3. Mencatat setiap aktivitas dokumen dalam log audit yang tidak bisa diubah tanpa ketahuan.
4. Mempermudah permintaan dan penyerahan dokumen antara SPI dan unit.
5. Mengendalikan kewenangan Super Admin agar independensi SPI terjaga.

### 1.3 Ruang lingkup

- **Pengguna:** Dewan Pengawas (Kepala SPI), Auditor SPI, Pengurus Yayasan, PIC unit SD YPVDP, SMP YPVDP, SMA YPVDP, dan Pelaksana Harian, serta Admin IT Yayasan sebagai Super Admin.
- **Termasuk:** manajemen folder dan berkas, berbagi internal, permintaan dokumen, versi berkas, 2FA aplikasi, persetujuan perubahan peran, log audit, notifikasi surel.
- **Tidak termasuk:** kanal pengaduan dan whistleblowing (SIGAP Vidatra), penyuntingan dokumen langsung di browser, dan akses pihak luar yayasan.
- **Alamat:** spi.vidatra.sch.id. Alamat ini tidak dipublikasikan, tidak ditautkan dari website yayasan, dan tidak diindeks mesin pencari (R5).

## 2. Pengguna dan peran

Sistem memakai enam peran. Satu pengguna punya satu peran utama; akses tambahan diberikan per folder. Peran disimpan sebagai data, sehingga jika kelak ada Kepala SPI tersendiri, peran bisa dipisah tanpa mengubah kode.

| Peran | Siapa | Tanggung jawab di sistem |
| --- | --- | --- |
| Dewan Pengawas (Kepala SPI) | Dewan Pengawas Yayasan (R1) | Pemilik seluruh ruang kerja SPI: buat penugasan, atur akses folder, setujui berbagi dokumen Rahasia, setujui perubahan peran dan reset 2FA, lihat log audit, tutup penugasan |
| Auditor SPI | Anggota tim SPI | Unggah dan kelola dokumen penugasan yang ditugaskan, buat permintaan dokumen ke unit, beri komentar |
| Pengurus Yayasan | Ketua/Pengurus Yayasan | Membaca laporan final dan ringkasan temuan yang dibagikan SPI, tanpa mengunduh (R7) |
| PIC Unit | Kepala sekolah / staf tata usaha SD, SMP, SMA YPVDP, Pelaksana Harian | Melihat permintaan dokumen untuk unitnya, mengunggah bukti, membaca laporan yang dibagikan ke unitnya |
| Peninjau Tamu | Pegawai lain yang ditunjuk sementara | Akses baca ke folder tertentu dengan batas waktu |
| Super Admin | Admin IT Yayasan, dengan pengendalian (R2) | Kelola akun, unit, dan pengaturan; mengajukan perubahan peran. Tidak bisa membuka isi folder Rahasia |

**Akun.** Login hanya dengan akun @vidatra.sch.id. Dewan Pengawas dan Pengurus Yayasan yang belum punya akun dibuatkan lewat formulir permintaan akun, yang disusun sebagai dokumen terpisah (R4).

**Data pejabat.** Nama dan surel petugas disimpan sebagai data yang bisa diubah, tidak ditanam di kode, sehingga pergantian pejabat cukup diubah dari panel admin.

## 3. Fitur sistem

Fitur dibagi dua tahap. Kode F-01 sampai F-16 sama dengan r0; F-17 sampai F-20 ditambahkan di r1.

| Kode | Fitur | Deskripsi | Tahap |
| --- | --- | --- | --- |
| F-01 | Login SSO Google | Masuk hanya dengan akun @vidatra.sch.id; akun di luar domain ditolak; tidak ada login kata sandi lokal | MVP |
| F-02 | Dasbor | Ringkasan penugasan aktif, permintaan tertunda, persetujuan menunggu, berkas terbaru, tenggat terdekat | MVP |
| F-03 | Manajemen folder | Buat, ubah nama, pindah, arsipkan folder; struktur baku per tahun, unit, penugasan | MVP |
| F-04 | Unggah dan unduh berkas | Unggah banyak berkas (drag and drop), pratinjau PDF dan gambar di browser | MVP |
| F-05 | Versi berkas | Berkas dengan nama sama disimpan sebagai versi baru; versi lama bisa dilihat dan dipulihkan | MVP |
| F-06 | Berbagi internal | Bagikan folder atau berkas ke pengguna/peran dengan hak baca, unggah, atau kelola, plus tanggal kedaluwarsa | MVP |
| F-07 | Klasifikasi dokumen | Label Umum, Internal, Rahasia; label menentukan siapa yang boleh mengakses dan apakah boleh diunduh | MVP |
| F-08 | Permintaan dokumen | SPI membuat daftar dokumen yang diminta ke unit dengan tenggat; unit mengunggah ke slot permintaan | MVP |
| F-09 | Log audit | Mencatat login, buka, unduh, unggah, hapus, ubah akses, dan perubahan peran, dengan waktu dan alamat IP | MVP |
| F-10 | Notifikasi surel | Surel untuk permintaan baru, unggahan, tenggat H-3, berbagi, dan persetujuan | MVP |
| F-11 | Pencarian | Cari berdasarkan nama berkas, label, unit, tahun, dan penugasan | MVP |
| F-12 | Tempat sampah | Berkas terhapus disimpan 30 hari sebelum dihapus permanen oleh Dewan Pengawas | MVP |
| F-13 | Komentar dan catatan | Komentar pada berkas untuk klarifikasi antara auditor dan PIC unit | Lanjutan |
| F-14 | Watermark | Laporan yang dibaca Pengurus Yayasan diberi watermark nama, surel, dan waktu (MVP, R7); watermark pada unduhan PDF Rahasia lain menyusul | MVP sebagian |
| F-15 | Laporan aktivitas | Ekspor log dan status permintaan ke Excel/PDF untuk laporan SPI | Lanjutan |
| F-16 | Pencarian isi dokumen | Mencari teks di dalam PDF dan dokumen Office | Lanjutan |
| F-17 | 2FA aplikasi | Kode 6 digit dari aplikasi autentikator untuk Dewan Pengawas, Auditor SPI, dan Super Admin, di luar kendali admin Google (R3) | MVP |
| F-18 | Pernyataan kerahasiaan | Disetujui saat login pertama; persetujuan tercatat | MVP |
| F-19 | Persetujuan peran dan reset 2FA | Perubahan peran dan reset 2FA diajukan Super Admin dan baru berlaku setelah disetujui Ketua Dewan Pengawas (R3) | MVP |
| F-20 | Pengamanan log | Log berantai hash, salinan harian di luar server, peringatan otomatis atas aksi sensitif, pengingat tinjauan log admin Google tiap 3 bulan (R3) | MVP |

## 4. Hak akses

Akses default ditolak; pengguna hanya melihat folder yang dibagikan kepadanya. Matriks berikut adalah hak maksimal tiap peran.

| Aksi | Super Admin | Dewan Pengawas (Kepala SPI) | Auditor SPI | Pengurus Yayasan | PIC Unit | Peninjau Tamu |
| --- | --- | --- | --- | --- | --- | --- |
| Kelola akun dan unit | Ya | Tidak | Tidak | Tidak | Tidak | Tidak |
| Ubah peran / reset 2FA | Ajukan | Setujui (Ketua) | Tidak | Tidak | Tidak | Tidak |
| Buat penugasan dan folder utama | Tidak | Ya | Tidak | Tidak | Tidak | Tidak |
| Buat subfolder | Tidak | Ya | Di penugasannya | Tidak | Tidak | Tidak |
| Lihat folder Internal SPI | Tidak | Ya | Di penugasannya | Tidak | Tidak | Tidak |
| Lihat folder Rahasia | Tidak | Ya | Jika ditunjuk | Tidak | Tidak | Tidak |
| Unggah berkas | Tidak | Ya | Di penugasannya | Tidak | Ke slot permintaan unitnya | Tidak |
| Unduh berkas | Tidak | Ya | Ya, kecuali dibatasi | Tidak (hanya lihat) | Berkas unitnya | Jika diizinkan |
| Hapus berkas | Tidak | Ya | Berkas miliknya | Tidak | Unggahannya sebelum dikunci | Tidak |
| Bagikan folder/berkas | Tidak | Ya | Ke sesama SPI | Tidak | Tidak | Tidak |
| Buat permintaan dokumen | Tidak | Ya | Ya | Tidak | Tidak | Tidak |
| Lihat laporan final | Tidak | Ya | Ya | Yang dibagikan, baca saja | Untuk unitnya | Tidak |
| Lihat log audit | Log sistem | Semua | Penugasannya | Tidak | Tidak | Tidak |
| Pulihkan dari tempat sampah | Tidak | Ya | Tidak | Tidak | Tidak | Tidak |

**Aturan tambahan:**

- Super Admin mengelola sistem, tetapi tidak membuka isi folder Rahasia; ini menjaga independensi SPI.
- Perubahan peran dan reset 2FA yang diajukan Super Admin baru berlaku setelah disetujui Ketua Dewan Pengawas (R3).
- Berbagi dokumen berlabel Rahasia ke luar tim SPI wajib disetujui Dewan Pengawas.
- Pengurus Yayasan membaca laporan di pratinjau browser tanpa tombol unduh atau cetak, dengan watermark identitas pembaca (R7). Tangkapan layar tidak bisa dicegah sepenuhnya; watermark membuat sumbernya bisa ditelusuri.
- Setelah penugasan ditutup, folder menjadi baca saja dan hanya Dewan Pengawas yang bisa membukanya kembali.
- PIC Unit hanya melihat data unitnya sendiri.
- Akses Peninjau Tamu otomatis berakhir pada tanggal kedaluwarsa.

## 5. Use case

Ada 16 use case utama. UC-01 sampai UC-14 sama dengan r0 (aktor Kepala SPI kini Dewan Pengawas); UC-15 dan UC-16 ditambahkan di r1.

| Kode | Use case | Aktor utama |
| --- | --- | --- |
| UC-01 | Login dengan akun Google yayasan | Semua pengguna |
| UC-02 | Melihat dasbor | Semua pengguna |
| UC-03 | Membuat penugasan audit beserta folder baku | Dewan Pengawas |
| UC-04 | Menunjuk auditor ke penugasan | Dewan Pengawas |
| UC-05 | Mengunggah dan mengelola dokumen kerja | Auditor SPI |
| UC-06 | Membuat permintaan dokumen ke unit | Auditor SPI, Dewan Pengawas |
| UC-07 | Mengunggah dokumen yang diminta | PIC Unit |
| UC-08 | Memeriksa dan menerima/menolak dokumen unit | Auditor SPI |
| UC-09 | Membagikan folder/berkas dengan batas waktu | Dewan Pengawas, Auditor SPI |
| UC-10 | Menyetujui berbagi dokumen Rahasia | Dewan Pengawas |
| UC-11 | Membaca laporan final (baca saja) | Pengurus Yayasan, PIC Unit |
| UC-12 | Melihat dan mengekspor log audit | Dewan Pengawas |
| UC-13 | Menutup dan mengunci penugasan | Dewan Pengawas |
| UC-14 | Mengelola akun, unit, dan pengaturan | Super Admin |
| UC-15 | Mengajukan dan menyetujui perubahan peran atau reset 2FA | Super Admin, Ketua Dewan Pengawas |
| UC-16 | Mengaktifkan 2FA aplikasi dan menyetujui pernyataan kerahasiaan | Pengguna baru |

### UC-06 dan UC-07: Permintaan dan penyerahan dokumen

**Prasyarat:** penugasan sudah dibuat, auditor sudah ditunjuk, PIC unit sudah terdaftar.

1. Auditor membuka penugasan dan memilih **Buat Permintaan**.
2. Auditor memilih unit tujuan, mengisi daftar dokumen (nama, keterangan, format yang diterima), dan tenggat.
3. Sistem membuat slot unggah per dokumen dan mengirim surel ke PIC unit.
4. PIC unit login, membuka permintaan, lalu mengunggah berkas ke tiap slot.
5. Sistem mencatat unggahan di log dan memberi tahu auditor.
6. Auditor memeriksa berkas, lalu menandai **Diterima** atau **Perlu Perbaikan** dengan catatan.
7. Jika perlu perbaikan, PIC unit mengunggah versi baru; langkah 5 dan 6 berulang.

**Alternatif:** tenggat terlewat → sistem mengirim pengingat ke PIC unit dan menandai slot **Terlambat** di dasbor SPI.

**Hasil:** semua slot berstatus Diterima, dan berkas tersimpan di folder Bukti Unit penugasan tersebut.

### UC-09 dan UC-10: Berbagi dokumen

1. Pengguna memilih folder atau berkas, lalu **Bagikan**.
2. Pengguna memilih penerima (orang atau peran), hak (baca/unggah/kelola), dan tanggal kedaluwarsa.
3. Jika berkas berlabel Rahasia dan penerima di luar tim SPI, sistem meminta persetujuan Dewan Pengawas.
4. Setelah disetujui, penerima mendapat surel dan akses aktif sampai tanggal kedaluwarsa.
5. Semua langkah tercatat di log audit.

### UC-15: Perubahan peran atau reset 2FA

1. Super Admin memilih pengguna, lalu mengajukan peran baru atau reset 2FA beserta alasannya.
2. Sistem menyimpan pengajuan berstatus **Menunggu persetujuan** dan mengirim surel ke Ketua Dewan Pengawas dan tim SPI.
3. Ketua Dewan Pengawas membuka antrean persetujuan, lalu menyetujui atau menolak dengan kode 2FA.
4. Jika disetujui, perubahan langsung berlaku; jika ditolak atau tidak diputuskan dalam 7 hari, pengajuan batal.
5. Semua langkah tercatat di log audit.

**Pengecualian:** saat sistem pertama dipasang, Super Admin dan Dewan Pengawas pertama ditetapkan lewat perintah server khusus yang hanya bisa dipakai sekali per peran (dicatat di log deviasi PRD).

### UC-13: Menutup penugasan

1. Dewan Pengawas memastikan laporan final sudah diunggah ke folder Laporan.
2. Dewan Pengawas memilih **Tutup Penugasan**.
3. Sistem mengunci folder (baca saja), mencabut akses Peninjau Tamu, dan memindahkan penugasan ke Arsip.

## 6. Keamanan sistem

Keamanan dirancang berlapis: identitas, akses, data, aplikasi, server, pemantauan, dan pengendalian Super Admin.

### 6.1 Identitas dan autentikasi

- Login hanya melalui Google OAuth 2.0. Di server dicek tiga hal: surel berakhiran @vidatra.sch.id, klaim `hd` dari Google cocok, dan surel terverifikasi. Consent screen Google berstatus Internal, jadi akun di luar organisasi sudah ditolak Google.
- Tidak ada login kata sandi lokal, termasuk di panel admin.
- Akun baru yang login pertama kali dicatat tanpa peran dan diarahkan ke halaman "belum diberi akses" tanpa diberi sesi.
- Akun yang dinonaktifkan atau dicabut perannya dikeluarkan di permintaan berikutnya.
- Verifikasi 2 langkah (2SV) Google diwajibkan untuk akun SPI, Dewan Pengawas, dan Pengurus di Google Admin Console.
- 2FA aplikasi (TOTP) wajib untuk Dewan Pengawas, Auditor SPI, dan Super Admin, diminta di setiap login. Kuncinya disimpan terenkripsi di basis data aplikasi, sehingga admin Google yang mereset kata sandi tetap tidak bisa melewatinya. Kode cadangan disimpan sebagai hash dan sekali pakai (R3).
- Sesi habis setelah 30 menit tidak aktif; sesi aktif bisa dicabut oleh Super Admin atau Dewan Pengawas.

### 6.2 Kontrol akses

- Kontrol akses berbasis peran (RBAC) ditambah izin per folder, dicek di server pada setiap permintaan, bukan hanya disembunyikan di tampilan.
- Berkas tidak punya URL publik; unduhan memakai tautan bertanda tangan yang berlaku 5 menit dan terikat sesi.
- Pemisahan data per unit: query selalu disaring berdasarkan unit dan penugasan pengguna.

### 6.3 Perlindungan data

- HTTPS wajib (TLS 1.2+, Let's Encrypt) dengan HSTS.
- Berkas disimpan di luar folder web publik dengan nama acak (UUID); nama asli hanya di basis data.
- Berkas berlabel Rahasia dienkripsi saat disimpan (AES-256) dengan kunci yang disimpan terpisah dari cadangan.
- Cadangan harian basis data dan berkas, terenkripsi, disimpan di luar VPS, retensi 30 hari; uji pemulihan tiap 3 bulan.

### 6.4 Keamanan unggahan

- Format yang diizinkan: PDF, DOCX, XLSX, PPTX, JPG, PNG, ZIP; maks. 50 MB per berkas (bisa diatur).
- Tipe berkas divalidasi dari isinya (MIME), bukan hanya ekstensi.
- Pemindaian antivirus (ClamAV) sebelum berkas bisa diunduh; berkas terinfeksi dikarantina.
- Berkas tidak pernah dieksekusi di server.

### 6.5 Keamanan aplikasi

- Mengikuti OWASP Top 10: proteksi CSRF, XSS, SQL injection, dan validasi input di server.
- Rate limit untuk login, verifikasi 2FA, unggah, dan unduh.
- Header keamanan: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options.
- `robots.txt` menolak semua mesin pencari dan halaman memakai meta `noindex` (R5).
- Pemindaian kerentanan dependensi sebelum rilis.

### 6.6 Keamanan server

- Firewall hanya membuka port 80/443; SSH lewat kunci, bukan kata sandi, dan dibatasi IP.
- Fail2ban, pembaruan keamanan OS otomatis, dan basis data tidak bisa diakses dari internet.
- Folder Kerja SPI dan SIGAP Vidatra berbagi VPS, tetapi memakai pengguna sistem, basis data, dan pool PHP-FPM terpisah (R8).

### 6.7 Log audit dan pemantauan

- Log audit hanya bisa ditambah. Setiap baris menyimpan hash baris sebelumnya, sehingga baris yang diubah atau dihapus membuat rantai putus dan terdeteksi saat verifikasi.
- Log diekspor harian ke folder Google Drive milik akun Dewan Pengawas, yang tidak bisa dihapus Super Admin.
- Log audit disimpan 10 tahun, sesuai draf Piagam SPI (R6).
- Peringatan surel otomatis ke Dewan Pengawas dan tim SPI untuk perubahan peran, reset 2FA, login Super Admin, dan pola tidak wajar seperti lebih dari 50 unduhan dalam 10 menit oleh satu akun.

### 6.8 Pengendalian Super Admin

Super Admin dipegang Admin IT Yayasan dengan lima kontrol (R3):

1. Perubahan peran dan reset 2FA wajib disetujui Ketua Dewan Pengawas, sejalan dengan Pasal 20 Peraturan WBS.
2. Akun SPI dan Dewan Pengawas memakai 2FA aplikasi, di luar kendali admin Google.
3. Perubahan peran, reset akun, dan login Super Admin memicu peringatan otomatis ke SPI dan Dewan Pengawas.
4. Log berantai hash dan salinannya dikirim ke luar server.
5. Dewan Pengawas diberi peran admin baca saja di Google Admin Console untuk meninjau log admin Google setiap 3 bulan; pengingatnya dikirim sistem.

**Batasan:** Super Admin yang memegang akses root server masih bisa mengubah data langsung di server. Kontrol di atas membuat perubahan semacam itu terdeteksi (rantai hash putus, salinan log di luar server tetap utuh), bukan mustahil.

### 6.9 Kebijakan pendukung

- SOP penggunaan dan klasifikasi dokumen disahkan yayasan sebelum peluncuran.
- Pengguna menyetujui pernyataan kerahasiaan saat login pertama; teks finalnya disusun SPI.
- Pemrosesan data pribadi mengikuti UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.

## 7. Kebutuhan sistem

### 7.1 Kebutuhan fungsional

| Kode | Kebutuhan |
| --- | --- |
| KF-01 | Sistem hanya menerima login dari akun Google berdomain vidatra.sch.id yang aktif dan sudah diberi peran. |
| KF-02 | Sistem membuat struktur folder baku otomatis saat penugasan dibuat. |
| KF-03 | Sistem menyimpan setiap unggahan ulang sebagai versi baru tanpa menimpa versi lama. |
| KF-04 | Sistem menolak akses ke folder dan berkas yang tidak dibagikan ke pengguna, termasuk lewat URL langsung. |
| KF-05 | Sistem mengirim surel notifikasi untuk permintaan baru, unggahan, tenggat H-3, berbagi, dan persetujuan. |
| KF-06 | Sistem mencatat setiap aksi berkas, akses, dan peran ke log audit berantai hash. |
| KF-07 | Sistem mengunci folder penugasan yang sudah ditutup menjadi baca saja. |
| KF-08 | Sistem menampilkan status tiap permintaan dokumen: Menunggu, Diunggah, Diterima, Perlu Perbaikan, Terlambat. |
| KF-09 | Sistem mewajibkan 2FA aplikasi untuk peran yang ditetapkan dan meminta kodenya di setiap login. |
| KF-10 | Sistem menahan perubahan peran dan reset 2FA sampai disetujui Ketua Dewan Pengawas. |
| KF-11 | Sistem menolak unduhan dan cetak laporan oleh Pengurus Yayasan, dan menampilkan watermark identitas pembaca. |

### 7.2 Kebutuhan non-fungsional

| Aspek | Target |
| --- | --- |
| Ketersediaan | 99% pada jam kerja (07.00–17.00 WITA) |
| Kinerja | Halaman terbuka < 2 detik; unggah 10 MB < 30 detik di koneksi kantor |
| Kapasitas | 50 pengguna aktif, 30 pengguna bersamaan |
| Tampilan | Responsif untuk laptop dan ponsel; bahasa Indonesia |
| Pemulihan | RPO 24 jam, RTO < 4 jam |
| Retensi | Log audit 10 tahun (R6); masa simpan dokumen arsip menunggu keputusan |
| Pemeliharaan | Kode di repositori GitHub organisasi Yayasan-Vidatra (spi-vidatra), dengan panduan instalasi; test berjalan di MySQL |

### 7.3 Teknologi

Mengikuti stack SIGAP Vidatra agar satu tim bisa merawat keduanya.

| Lapisan | Pilihan |
| --- | --- |
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | Inertia.js 3 + React 19 + Vite, Tailwind CSS 4 |
| Panel admin | Filament 5 |
| Basis data | MySQL 8 |
| Login | Laravel Socialite (Google OAuth), project Google Cloud "Vidatra Internal Apps" milik organisasi vidatra.sch.id |
| 2FA | TOTP (pragmarx/google2fa) |
| Penyimpanan berkas | Disk VPS, di luar web root, berkas Rahasia terenkripsi |
| Antrean dan cache | Redis, dipakai bersama SIGAP |
| Surel | SMTP Google Workspace (akun notifikasi khusus) |
| Antivirus | ClamAV, dipakai bersama SIGAP |
| Web server | Nginx + PHP-FPM, Let's Encrypt |

### 7.4 Infrastruktur

| Komponen | Spesifikasi |
| --- | --- |
| VPS | Rumahweb paket L (2 vCPU, 4 GB RAM, 80 GB), Ubuntu 24.04, data center DCI Bekasi; server srv1.vidatra.sch.id dipakai bersama SIGAP Vidatra (R8) |
| Upgrade | Ke paket XL (4 vCPU, 8 GB, 160 GB) bila RAM rutin > 80%, disk > 70%, atau saat dipakai semua unit. Upgrade dibayar pro-rata tanpa migrasi data; turun paket tidak tersedia langsung |
| Domain | spi.vidatra.sch.id (DNS A record ke VPS; surel Google Workspace tidak terpengaruh) |
| Cadangan | Di luar VPS, minimal 2× ukuran data |
| Pengguna | Browser terbaru dan akun Google Workspace yayasan |

**Anggaran.** Tahun pertama mengikuti anggaran VPS SIGAP, sekitar Rp2.988.675 termasuk PPN, tanpa biaya tambahan sampai upgrade diperlukan.

## 8. Arsitektur sistem

&#91;embedded content: arsitektur sistem · 8 komponen\]

Pengguna hanya berhubungan dengan Nginx dan aplikasi; basis data dan folder berkas tidak bisa dijangkau dari internet. Setiap unduhan melewati pengecekan izin dan tercatat di log berantai hash, yang salinannya diekspor harian ke Drive Dewan Pengawas di luar server.

## 9. Struktur folder dan data

### 9.1 Struktur folder baku

Saat Dewan Pengawas membuat penugasan, sistem otomatis membuat subfolder berikut dari templat di Pengaturan. Nama penugasan memakai format `<tahun>-<nomor urut>-<unit>-<topik singkat>`.

- **SPI Yayasan Vidatra**
  - **2026**
    - **2026-01-SMP-Audit Keuangan Semester 1**
      - 01 Perencanaan (surat tugas, program kerja audit)
      - 02 Permintaan Dokumen (slot unggah dari unit)
      - 03 Kertas Kerja (KKA, catatan auditor) — Internal
      - 04 Bukti Unit (dokumen yang sudah diterima)
      - 05 Temuan dan Tanggapan
      - 06 Laporan (draf dan laporan final)
      - 07 Tindak Lanjut (pemantauan rekomendasi)
  - **Regulasi dan Pedoman** (peraturan yayasan, SOP, piagam audit)
  - **Templat** (format KKA, surat permintaan, laporan)
  - **Arsip** (penugasan yang sudah ditutup)

### 9.2 Entitas data utama

| Entitas | Isi pokok |
| --- | --- |
| users | nama, surel, google\_id, peran, unit, status aktif, login terakhir, kunci 2FA (terenkripsi), kode cadangan (hash), waktu konfirmasi 2FA |
| roles | slug, nama, wajib 2FA (diisi seeder, tidak bisa diubah lewat UI) |
| role\_change\_requests | pengguna, peran lama dan baru atau reset 2FA, alasan, pengaju, penyetuju, status, batas waktu |
| confidentiality\_statements | teks pernyataan, versi |
| confidentiality\_acceptances | pengguna, versi pernyataan, waktu, IP, user agent |
| units | SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian, Yayasan |
| assignments | kode, judul, tahun, unit, ketua tim, status (aktif/ditutup), tanggal mulai dan selesai |
| folders | nama, induk, penugasan, label klasifikasi, terkunci |
| files | nama asli, nama simpan (UUID), ukuran, tipe MIME, hash SHA-256, label, pengunggah |
| file\_versions | berkas, nomor versi, lokasi simpan, pengunggah, waktu |
| shares | objek (folder/berkas), penerima (pengguna/peran), hak, kedaluwarsa, disetujui oleh |
| document\_requests | penugasan, unit, tenggat, pembuat, status |
| request\_items | permintaan, nama dokumen, keterangan, berkas terunggah, status, catatan auditor |
| audit\_logs | waktu, pengguna, aksi, objek, IP, user agent, hash baris sebelumnya, hash baris ini (append-only) |

## 10. Tahapan, risiko, dan hal terbuka

### 10.1 Tahapan

Pengembangan MVP 5 minggu, lalu uji coba pengguna (UAT) 2 minggu. Rinciannya per user story ada di PRD Folder Kerja SPI Vidatra.

| Rilis | Isi | Kapan |
| --- | --- | --- |
| Demo 1 | Login Google, 2FA, pernyataan kerahasiaan, sesi; penugasan dan folder baku; unggah dan versi berkas | Akhir minggu 2 |
| Demo 2 | Enkripsi Rahasia, tempat sampah, pencarian, permintaan dokumen, berbagi dan persetujuan, laporan baca saja | Akhir minggu 4 |
| Serah terima Tahap 1 | Pengendalian Super Admin, log berantai dan ekspor, dasbor, pengaturan, notifikasi, pencabutan sesi; UAT bersama SPI dan satu unit | Akhir minggu 5, lalu UAT 2 minggu |
| Peluncuran | Server produksi, SOP, pembuatan akun Dewan Pengawas dan Pengurus, aktivasi 2SV, pelatihan pengguna, migrasi dokumen awal | Setelah UAT |
| Tahap 2 | Komentar, laporan aktivitas, pencarian isi dokumen, watermark unduhan Rahasia | Setelah evaluasi 1 bulan |

### 10.2 Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Dokumen rahasia bocor karena salah atur akses | Tinggi | Akses default ditolak, persetujuan Dewan Pengawas untuk berbagi Rahasia, uji akses antar-peran sebelum rilis |
| Penyalahgunaan kewenangan Super Admin | Tinggi | Persetujuan Ketua Dewan Pengawas untuk peran dan reset 2FA, peringatan otomatis, log berantai hash dengan salinan di luar server |
| Akun Google pegawai diretas | Tinggi | 2SV Google, 2FA aplikasi, sesi 30 menit, peringatan unduhan massal |
| Data hilang karena kerusakan VPS | Tinggi | Cadangan harian terenkripsi di luar VPS, uji pemulihan triwulanan |
| Gangguan atau peretasan aplikasi lain di VPS yang sama | Sedang | Pengguna sistem, basis data, dan PHP-FPM terpisah dari SIGAP; pembaruan rutin |
| RAM atau disk paket L tidak cukup | Sedang | Pantau pemakaian; upgrade ke XL pro-rata tanpa migrasi data |
| Admin terkunci karena kehilangan perangkat 2FA sebelum fitur reset selesai | Sedang | Simpan kode cadangan di password manager; reset darurat lewat server dicatat dan dilaporkan ke Dewan Pengawas |
| Pengguna tetap berbagi lewat WhatsApp/surel | Sedang | SOP resmi yayasan, pelatihan, kemudahan permintaan dokumen di sistem |
| Ketergantungan pada satu pengembang | Sedang | Kode di GitHub organisasi, PRD dan panduan instalasi di repositori |

### 10.3 Hal yang masih perlu diputuskan

- [ ] Peran Kepala SPI dipegang Ketua Dewan Pengawas saja, atau semua anggota Dewan Pengawas.
- [ ] Masa simpan dokumen arsip penugasan.
- [ ] Pemegang kata sandi root VPS: Admin IT, atau disimpan tersegel oleh Dewan Pengawas.
- [ ] Teks final pernyataan kerahasiaan dari SPI.
- [ ] Templat subfolder dan jenis dokumen final dari SPI.
