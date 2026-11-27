# Rancangan Website Folder Kerja SPI Yayasan Vidatra (r2)

Nov 25, 2026 · @Luthfianda Salsabila

Revisi r2 memisahkan Kepala SPI dari Dewan Pengawas sesuai Piagam SPI final: Kepala SPI dipegang Auditor Internal, sedangkan Dewan Pengawas menjadi Pengawas SPI yang menyetujui hal sensitif dan meninjau log. r2 juga memperkuat salinan log dan akses root, menetapkan masa simpan 10 tahun, menambah eskalasi tenggat Piagam, mempertegas pengaman VPS bersama SIGAP, dan memakai Shared Drive sementara sampai aplikasi diluncurkan.

| Versi | Tanggal | Perubahan |
| --- | --- | --- |
| r2 | 25 Nov 2026 | Memasukkan 7 usulan pimpinan atas r1 (P1–P7); hal terbuka di r1 diputuskan; jadwal dievaluasi ulang; progres pengembangan ditambahkan (Bagian 11) |
| r1 | 23 Nov 2026 | Memasukkan 8 revisi hasil tinjauan pimpinan (R1–R8) |
| r0 | 20 Nov 2026 | Rancangan awal |

Kode usulan r2, dipakai juga di log deviasi PRD:

| Kode | Perubahan | Bagian |
| --- | --- | --- |
| P1 | Kepala SPI dipegang Auditor Internal (Piagam Pasal 5, 18, 19); Dewan Pengawas menjadi Pengawas SPI | 2, 4, 5 |
| P2 | Hash ujung rantai log dikirim ke surel di luar domain; kata sandi root dan akun panel VPS disegel Dewan Pengawas; reset darurat oleh dua orang | 6.7, 6.8 |
| P3 | Perubahan atas akun Ketua Dewan Pengawas disetujui anggota Dewan Pengawas lain | 3, 5 |
| P4 | Masa simpan dokumen 10 tahun (Piagam Pasal 24); berkas penugasan yang ditutup tidak dapat dihapus; cadangan bulanan dan tahunan | 4, 6.3, 7 |
| P5 | Eskalasi tenggat Piagam ke Dewan Pengawas (F-21, KF-12) | 3, 5, 7 |
| P6 | Pengaman tambahan VPS bersama SIGAP; ZIP bersandi ditolak; DPIA sebelum peluncuran | 6.4, 6.6, 6.9 |
| P7 | Shared Drive sementara sampai peluncuran; jadwal dievaluasi ulang | 9.3, 10.1 |

## 1. Pendahuluan

Website Folder Kerja SPI adalah ruang kerja dokumen tertutup untuk Satuan Pengawas Internal (SPI) Yayasan Vidatra. Website ini dipakai untuk menyimpan, berbagi, dan meminta dokumen pengawasan secara aman antara SPI, unit sekolah, dan pengurus yayasan. Akses hanya lewat akun Google Workspace yayasan (@vidatra.sch.id).

### 1.1 Latar belakang

- Dokumen audit dan bukti pendukung tersebar di surel, WhatsApp, dan flashdisk, sehingga versinya sulit dilacak.
- Tidak ada jejak siapa yang membuka, mengunduh, atau mengubah dokumen sensitif.
- SPI perlu satu tempat untuk meminta dokumen ke unit dan memantau mana yang sudah atau belum diserahkan.

### 1.2 Tujuan

- Menyediakan repositori dokumen SPI yang terpusat, terstruktur per tahun, unit, dan penugasan.
- Membatasi akses dokumen sesuai peran dan kebutuhan (prinsip least privilege).
- Mencatat setiap aktivitas dokumen dalam log audit yang tidak bisa diubah tanpa ketahuan.
- Mempermudah permintaan dan penyerahan dokumen antara SPI dan unit, termasuk pemantauan tenggat sesuai Piagam SPI.
- Mengendalikan kewenangan Super Admin agar independensi SPI terjaga.

### 1.3 Ruang lingkup

- Pengguna: Dewan Pengawas (Pengawas SPI), Kepala SPI, Auditor SPI, Pengurus Yayasan, PIC unit SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian, dan Yayasan, serta Admin IT Yayasan sebagai Super Admin.
- Termasuk: manajemen folder dan berkas, berbagi internal, permintaan dokumen, versi berkas, 2FA aplikasi, persetujuan perubahan peran, log audit, notifikasi surel, eskalasi tenggat.
- Tidak termasuk: kanal pengaduan dan whistleblowing (SIGAP Vidatra), penyuntingan dokumen langsung di browser, dan akses pihak luar yayasan.
- Alamat: spi.vidatra.sch.id. Alamat ini tidak dipublikasikan, tidak ditautkan dari website yayasan, dan tidak diindeks mesin pencari (R5).
- Acuan: Piagam SPI final, Peraturan Sistem Pengaduan (WBS), dan UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.

## 2. Pengguna dan peran

Sistem memakai tujuh peran. Peran disimpan sebagai data, sehingga satu orang dapat memegang lebih dari satu peran; akses tambahan diberikan per folder. Untuk sementara, Auditor Internal memegang peran Kepala SPI dan Auditor SPI sekaligus, sampai struktur SPI difinalisasi (P1).

| Peran | Siapa | Tanggung jawab di sistem |
| --- | --- | --- |
| Dewan Pengawas (Pengawas SPI) | Dewan Pengawas Yayasan (R1). Persetujuan oleh Ketua; anggota menjadi pengganti bila Ketua berhalangan atau terkait | Mengawasi seluruh ruang kerja SPI: melihat semua folder dan log, menyetujui berbagi dokumen Rahasia, buka kunci penugasan, hapus permanen, perubahan peran dan reset 2FA, serta menerima eskalasi tenggat |
| Kepala SPI | Auditor Internal (Piagam Pasal 5 ayat (1)); pemegang sementara sampai struktur SPI final | Pemilik ruang kerja SPI: membuat penugasan berdasarkan surat tugas, menunjuk auditor, mengatur akses folder, menutup penugasan (Dewan Pengawas diberi tahu otomatis), memulihkan berkas, mengajukan persetujuan kepada Dewan Pengawas |
| Auditor SPI | Anggota tim SPI | Mengunggah dan mengelola dokumen penugasan yang ditugaskan, membuat permintaan dokumen ke unit, memeriksa dokumen unit, memberi komentar |
| Pengurus Yayasan | Ketua/Pengurus Yayasan | Membaca laporan final dan ringkasan temuan yang dibagikan SPI, tanpa mengunduh (R7) |
| PIC Unit | Kepala sekolah / staf tata usaha SD, SMP, SMA YPVDP, Pelaksana Harian, Yayasan | Melihat permintaan dokumen untuk unitnya, mengunggah bukti, membaca laporan yang dibagikan ke unitnya |
| Peninjau Tamu | Pegawai lain yang ditunjuk sementara | Akses baca ke folder tertentu dengan batas waktu |
| Super Admin | Admin IT Yayasan, dengan pengendalian (R2, P2) | Mengelola akun, unit, dan pengaturan; mengajukan perubahan peran. Tidak bisa membuka isi folder Rahasia |

Pemisahan tugas. Karena Kepala SPI dan Auditor SPI saat ini dipegang satu orang, persetujuan kedua untuk hal sensitif tetap berada di Dewan Pengawas. Dengan begitu auditor tidak menyetujui pekerjaannya sendiri.

Akun. Login hanya dengan akun @vidatra.sch.id. Dewan Pengawas dan Pengurus Yayasan yang belum punya akun dibuatkan lewat formulir permintaan akun, yang disusun sebagai dokumen terpisah (R4).

Data pejabat. Nama dan surel petugas, termasuk siapa Ketua Dewan Pengawas, disimpan sebagai data yang bisa diubah, tidak ditanam di kode. Pergantian pejabat cukup diubah dari panel admin melalui alur persetujuan peran.

## 3. Fitur sistem

Fitur dibagi dua tahap. F-01 sampai F-16 berasal dari r0, F-17 sampai F-20 dari r1, dan F-21 ditambahkan di r2.

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
| F-10 | Notifikasi surel | Surel untuk permintaan baru, unggahan, hasil pemeriksaan, tenggat H-3, berbagi, persetujuan, dan eskalasi | MVP |
| F-11 | Pencarian | Cari berdasarkan nama berkas, label, unit, tahun, dan penugasan | MVP |
| F-12 | Tempat sampah | Berkas terhapus disimpan 30 hari, lalu dihapus permanen atas persetujuan Dewan Pengawas; berkas pada penugasan yang sudah ditutup tidak dapat dihapus (P4) | MVP |
| F-13 | Komentar dan catatan | Komentar pada berkas untuk klarifikasi antara auditor dan PIC unit | Lanjutan |
| F-14 | Watermark | Laporan yang dibaca Pengurus Yayasan diberi watermark nama, surel, dan waktu (R7); watermark pada unduhan PDF Rahasia lain menyusul | MVP sebagian |
| F-15 | Laporan aktivitas | Ekspor log dan status permintaan ke Excel/PDF untuk laporan SPI | Lanjutan |
| F-16 | Pencarian isi dokumen | Mencari teks di dalam PDF dan dokumen Office | Lanjutan |
| F-17 | 2FA aplikasi | Kode 6 digit dari aplikasi autentikator untuk Dewan Pengawas, Kepala SPI, Auditor SPI, dan Super Admin, di luar kendali admin Google (R3) | MVP |
| F-18 | Pernyataan kerahasiaan | Disetujui saat login pertama; persetujuan tercatat | MVP |
| F-19 | Persetujuan peran dan reset 2FA | Diajukan Super Admin dan berlaku setelah disetujui Ketua Dewan Pengawas (R3); pengajuan atas akun Ketua Dewan Pengawas disetujui anggota Dewan Pengawas lain (P3) | MVP |
| F-20 | Pengamanan log | Log berantai hash, salinan harian di luar server, hash ujung rantai dikirim harian ke surel Dewan Pengawas di luar domain vidatra.sch.id (P2), peringatan otomatis atas aksi sensitif, pengingat tinjauan log admin Google tiap 3 bulan | MVP |
| F-21 | Eskalasi tenggat Piagam | Diteruskan otomatis ke Dewan Pengawas: permintaan dokumen yang belum dipenuhi 10 hari kerja setelah tenggat (Pasal 17 ayat (2)), serta tindak lanjut yang melewati tenggat: tanggapan rancangan LHA 10 hari kerja, rencana aksi 30 hari kalender, temuan berisiko tinggi 10 hari kerja (Pasal 20 dan 22) (P5) | MVP |

Perhitungan hari kerja F-21 memakai hari Senin–Jumat dikurangi daftar libur nasional dan cuti bersama yang dikelola di Pengaturan.

## 4. Hak akses

Akses default ditolak; pengguna hanya melihat folder yang dibagikan kepadanya. Matriks berikut adalah hak maksimal tiap peran. Hak operasional (membuat dan menutup penugasan, memulihkan berkas) kini berada di Kepala SPI, sedangkan Dewan Pengawas mengawasi dan menyetujui (P1).

| Aksi | Super Admin | Dewan Pengawas (Pengawas SPI) | Kepala SPI / Auditor SPI | Pengurus Yayasan | PIC Unit | Peninjau Tamu |
| --- | --- | --- | --- | --- | --- | --- |
| Kelola akun dan unit | Ya | Tidak | Tidak | Tidak | Tidak | Tidak |
| Ubah peran / reset 2FA | Ajukan | Setujui (Ketua; anggota lain untuk akun Ketua) | Tidak | Tidak | Tidak | Tidak |
| Buat penugasan dan folder utama | Tidak | Tidak (diberi tahu) | Kepala SPI | Tidak | Tidak | Tidak |
| Buat subfolder | Tidak | Ya | Di penugasannya | Tidak | Tidak | Tidak |
| Lihat folder Internal SPI | Tidak | Ya | Di penugasannya | Tidak | Tidak | Tidak |
| Lihat folder Rahasia | Tidak | Ya | Jika ditunjuk | Tidak | Tidak | Tidak |
| Unggah berkas | Tidak | Ya | Di penugasannya | Tidak | Ke slot permintaan unitnya | Tidak |
| Unduh berkas | Tidak | Ya | Ya, kecuali dibatasi | Tidak (hanya lihat) | Berkas unitnya | Jika diizinkan |
| Hapus berkas | Tidak | Setujui hapus permanen | Berkas miliknya | Tidak | Unggahannya sebelum dikunci | Tidak |
| Bagikan folder/berkas | Tidak | Ya | Ke sesama SPI | Tidak | Tidak | Tidak |
| Buat permintaan dokumen | Tidak | Ya | Ya | Tidak | Tidak | Tidak |
| Lihat laporan final | Tidak | Ya | Ya | Yang dibagikan, baca saja | Untuk unitnya | Tidak |
| Lihat log audit | Log sistem | Semua | Penugasannya | Tidak | Tidak | Tidak |
| Pulihkan dari tempat sampah | Tidak | Ya | Kepala SPI | Tidak | Tidak | Tidak |
| Tutup / buka kunci penugasan | Tidak | Setujui buka kunci | Tutup: Kepala SPI; buka kunci: ajukan | Tidak | Tidak | Tidak |
| Terima eskalasi tenggat | Tidak | Ya | Diberi tahu | Tidak | Tidak | Tidak |

Aturan tambahan:

- Super Admin mengelola sistem, tetapi tidak membuka isi folder Rahasia; ini menjaga independensi SPI.
- Perubahan peran dan reset 2FA yang diajukan Super Admin baru berlaku setelah disetujui Ketua Dewan Pengawas (R3). Pengajuan atas akun Ketua Dewan Pengawas disetujui anggota Dewan Pengawas lain (P3).
- Berbagi dokumen berlabel Rahasia ke luar tim SPI wajib disetujui Dewan Pengawas.
- Pengurus Yayasan membaca laporan di pratinjau browser tanpa tombol unduh atau cetak, dengan watermark identitas pembaca (R7). Tangkapan layar tidak bisa dicegah sepenuhnya; watermark membuat sumbernya bisa ditelusuri.
- Setelah penugasan ditutup, folder menjadi baca saja dan hanya dapat dibuka kembali oleh Kepala SPI atas persetujuan Dewan Pengawas.
- Berkas pada penugasan yang sudah ditutup tidak dapat dihapus selama masa simpan 10 tahun (Piagam Pasal 24 ayat (1)).
- PIC Unit hanya melihat data unitnya sendiri.
- Akses Peninjau Tamu otomatis berakhir pada tanggal kedaluwarsa.

## 5. Use case

Ada 17 use case utama. UC-01 sampai UC-16 sama dengan r1, dengan aktor Kepala SPI kini Auditor Internal; UC-17 ditambahkan di r2.

| Kode | Use case | Aktor utama |
| --- | --- | --- |
| UC-01 | Login dengan akun Google yayasan | Semua pengguna |
| UC-02 | Melihat dasbor | Semua pengguna |
| UC-03 | Membuat penugasan audit beserta folder baku | Kepala SPI (Dewan Pengawas diberi tahu) |
| UC-04 | Menunjuk auditor ke penugasan | Kepala SPI |
| UC-05 | Mengunggah dan mengelola dokumen kerja | Auditor SPI |
| UC-06 | Membuat permintaan dokumen ke unit | Auditor SPI, Kepala SPI |
| UC-07 | Mengunggah dokumen yang diminta | PIC Unit |
| UC-08 | Memeriksa dan menerima/menolak dokumen unit | Auditor SPI, Kepala SPI |
| UC-09 | Membagikan folder/berkas dengan batas waktu | Kepala SPI, Auditor SPI |
| UC-10 | Menyetujui berbagi dokumen Rahasia | Dewan Pengawas |
| UC-11 | Membaca laporan final (baca saja) | Pengurus Yayasan, PIC Unit |
| UC-12 | Melihat dan mengekspor log audit | Dewan Pengawas |
| UC-13 | Menutup dan mengunci penugasan | Kepala SPI (Dewan Pengawas diberi tahu) |
| UC-14 | Mengelola akun, unit, dan pengaturan | Super Admin |
| UC-15 | Mengajukan dan menyetujui perubahan peran atau reset 2FA | Super Admin, Ketua Dewan Pengawas |
| UC-16 | Mengaktifkan 2FA aplikasi dan menyetujui pernyataan kerahasiaan | Pengguna baru |
| UC-17 | Menerima eskalasi tenggat Piagam | Dewan Pengawas (Kepala SPI diberi tahu) |

### UC-06 dan UC-07: Permintaan dan penyerahan dokumen

Prasyarat: penugasan sudah dibuat, auditor sudah ditunjuk, PIC unit sudah terdaftar dengan unitnya.

1. Auditor membuka penugasan dan memilih Buat Permintaan.
2. Auditor memilih unit tujuan, mengisi daftar dokumen (nama, keterangan, format yang diterima), dan tenggat.
3. Sistem membuat slot unggah per dokumen dan memberi tahu PIC unit.
4. PIC unit login, membuka permintaan, lalu mengunggah berkas ke tiap slot.
5. Sistem memindai berkas, mencatat unggahan di log, dan memberi tahu auditor pembuat permintaan.
6. Auditor memeriksa berkas, lalu menandai Diterima atau Perlu Perbaikan dengan catatan; PIC diberi tahu hasilnya.
7. Jika perlu perbaikan, PIC unit mengunggah versi baru; langkah 5 dan 6 berulang.

Alternatif: tenggat terlewat → sistem mengirim pengingat ke PIC unit dan menandai slot Terlambat di dasbor SPI. Bila belum dipenuhi 10 hari kerja setelah tenggat, sistem meneruskan daftar slot terlambat kepada Dewan Pengawas sebagai hambatan (Piagam Pasal 17 ayat (2)).

Hasil: semua slot berstatus Diterima, dan salinan berkasnya tersimpan di folder 04 Bukti Unit penugasan tersebut.

### UC-09 dan UC-10: Berbagi dokumen

1. Pengguna memilih folder atau berkas, lalu Bagikan.
2. Pengguna memilih penerima (orang atau peran), hak (baca/unggah/kelola), dan tanggal kedaluwarsa.
3. Jika berkas berlabel Rahasia dan penerima di luar tim SPI, sistem meminta persetujuan Dewan Pengawas.
4. Setelah disetujui, penerima mendapat surel dan akses aktif sampai tanggal kedaluwarsa.
5. Semua langkah tercatat di log audit.

### UC-15: Perubahan peran atau reset 2FA

1. Super Admin memilih pengguna, lalu mengajukan peran baru atau reset 2FA beserta alasannya.
2. Sistem menyimpan pengajuan berstatus Menunggu persetujuan dan mengirim surel ke Ketua Dewan Pengawas dan tim SPI.
3. Ketua Dewan Pengawas membuka antrean persetujuan, lalu menyetujui atau menolak dengan kode 2FA.
4. Jika disetujui, perubahan langsung berlaku; jika ditolak atau tidak diputuskan dalam 7 hari, pengajuan batal.
5. Pengajuan yang menyangkut akun Ketua Dewan Pengawas disetujui anggota Dewan Pengawas lain dengan cara yang sama (P3).
6. Semua langkah tercatat di log audit.

Pengecualian: saat sistem pertama dipasang, Super Admin dan Ketua Dewan Pengawas pertama ditetapkan lewat perintah server khusus yang hanya bisa dipakai sekali per peran. Reset darurat lewat server dilakukan dua orang (Admin IT dan anggota Dewan Pengawas), dicatat, dan dilaporkan otomatis (P2).

### UC-13: Menutup penugasan

1. Kepala SPI memastikan laporan final sudah diunggah ke folder Laporan.
2. Kepala SPI memilih Tutup Penugasan.
3. Sistem mengunci folder (baca saja), mencabut akses Peninjau Tamu, memindahkan penugasan ke Arsip, dan memberi tahu Dewan Pengawas.

### UC-17: Eskalasi tenggat Piagam

1. Setiap hari sistem memeriksa slot permintaan yang belum Diterima 10 hari kerja setelah tenggat, serta tenggat tindak lanjut yang terlewat.
2. Sistem mengirim daftar hambatan ke Dewan Pengawas dan memberi tahu Kepala SPI.
3. Eskalasi tampil di dasbor Dewan Pengawas sampai slot atau tindak lanjut selesai, dan tercatat di log audit.

## 6. Keamanan sistem

Keamanan dirancang berlapis: identitas, akses, data, unggahan, aplikasi, server, pemantauan, dan pengendalian Super Admin.

### 6.1 Identitas dan autentikasi

- Login hanya melalui Google OAuth 2.0. Server mengecek surel berakhiran @vidatra.sch.id, klaim hd dari Google, dan surel terverifikasi. Consent screen Google berstatus Internal.
- Tidak ada login kata sandi lokal, termasuk di panel admin.
- Akun baru yang login pertama kali dicatat tanpa peran dan diarahkan ke halaman "belum diberi akses" tanpa diberi sesi. PIC Unit yang belum diberi unit diperlakukan sama.
- Akun yang dinonaktifkan atau dicabut perannya dikeluarkan di permintaan berikutnya.
- Verifikasi 2 langkah (2SV) Google diwajibkan untuk akun SPI, Dewan Pengawas, dan Pengurus di Google Admin Console.
- 2FA aplikasi (TOTP) wajib untuk Dewan Pengawas, Kepala SPI, Auditor SPI, dan Super Admin di setiap login. Kuncinya terenkripsi di basis data aplikasi; kode cadangan disimpan sebagai hash dan sekali pakai (R3).
- Sesi habis setelah 30 menit tidak aktif; sesi aktif bisa dicabut oleh Super Admin atau Dewan Pengawas.

### 6.2 Kontrol akses

- Kontrol akses berbasis peran ditambah izin per folder, dicek di server pada setiap permintaan, bukan hanya disembunyikan di tampilan.
- Berkas tidak punya URL publik; unduhan memakai tautan bertanda tangan yang berlaku 5 menit dan terikat pengguna serta sesi.
- Pemisahan data per unit: query selalu disaring berdasarkan unit dan penugasan pengguna.

### 6.3 Perlindungan data

- HTTPS wajib (TLS 1.2+, Let's Encrypt) dengan HSTS.
- Berkas disimpan di luar folder web publik dengan nama acak (UUID); nama asli hanya di basis data.
- Berkas berlabel Rahasia dienkripsi saat disimpan (AES-256-GCM) dengan kunci yang disimpan terpisah dari cadangan.
- Cadangan basis data dan berkas terenkripsi, disimpan di luar VPS: harian disimpan 30 hari, bulanan disimpan 12 bulan, tahunan disimpan 10 tahun (P4). Uji pemulihan tiap 3 bulan.

### 6.4 Keamanan unggahan

- Format yang diizinkan: PDF, DOCX, XLSX, PPTX, JPG, PNG, ZIP; maksimal 50 MB per berkas (bisa diatur).
- Tipe berkas divalidasi dari isinya, bukan hanya ekstensi.
- Pemindaian antivirus (ClamAV) sebelum berkas bisa diunduh; berkas terinfeksi dikarantina. ZIP bersandi ditolak karena isinya tidak dapat dipindai (P6).
- Berkas tidak pernah dieksekusi di server.

### 6.5 Keamanan aplikasi

- Mengikuti OWASP Top 10: proteksi CSRF, XSS, SQL injection, dan validasi input di server.
- Rate limit untuk login, verifikasi 2FA, unggah, dan unduh.
- Header keamanan: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options; halaman galat berbahasa Indonesia tanpa detail teknis.
- robots.txt menolak semua mesin pencari dan halaman memakai meta noindex (R5).
- Pemindaian kerentanan dependensi sebelum rilis.

### 6.6 Keamanan server

- Firewall hanya membuka port 80/443; SSH lewat kunci, bukan kata sandi, dan login root lewat SSH dimatikan.
- Fail2ban, pembaruan keamanan OS otomatis, dan basis data tidak bisa diakses dari internet.
- Folder Kerja SPI dan SIGAP Vidatra berbagi VPS, tetapi memakai pengguna sistem, basis data, dan pool PHP-FPM terpisah (R8).
- Pengaman tambahan (P6): Redis dengan kata sandi dan nomor basis data terpisah per aplikasi; folder berkas hanya dapat dibaca pengguna sistem aplikasi masing-masing; open\_basedir dan pembatasan fungsi PHP per pool; kuota disk per aplikasi; pemantauan RAM karena ClamAV, MySQL, dan dua aplikasi berbagi 4 GB.

Evaluasi berbagi VPS dengan SIGAP. SIGAP menerima unggahan dari publik, sehingga menjadi pintu masuk risiko terbesar di server ini. Ada dua pilihan:

| Pilihan | Kelebihan | Kekurangan |
| --- | --- | --- |
| A. Tetap berbagi VPS, dengan pengaman tambahan di atas | Tanpa biaya tambahan; satu server dirawat | Peretasan SIGAP yang berhasil naik ke hak root tetap dapat menjangkau data SPI |
| B. VPS terpisah untuk Folder Kerja SPI | Data SPI terpisah total dari aplikasi publik | Biaya VPS kedua dan perawatan dua server |

Usulan: pilihan A untuk masa uji coba dan tahun pertama, karena berkas Rahasia SPI terenkripsi dan unggahan SIGAP dipindai serta tidak pernah dieksekusi. Folder Kerja SPI dipindah ke VPS sendiri bila terjadi insiden keamanan di SIGAP, bila SIGAP dibuka lebih luas ke publik, atau saat paket perlu dinaikkan ke XL. Biaya pilihan B dihitung bila pimpinan memilihnya.

### 6.7 Log audit dan pemantauan

- Log audit hanya bisa ditambah. Setiap baris menyimpan hash baris sebelumnya, sehingga baris yang diubah atau dihapus membuat rantai putus dan terdeteksi saat verifikasi.
- Log diekspor harian ke folder Google Drive milik akun Dewan Pengawas, dan hash ujung rantai dikirim harian ke surel Dewan Pengawas di luar domain vidatra.sch.id (P2). Admin IT yang juga super admin Google Workspace dapat mengubah Drive Dewan Pengawas, tetapi tidak dapat mengubah surel di luar domain, sehingga perubahan log tetap dapat dibuktikan.
- Log audit disimpan 10 tahun (R6).
- Peringatan surel otomatis ke Dewan Pengawas dan tim SPI untuk perubahan peran, reset 2FA, login Super Admin, reset darurat lewat server, dan pola tidak wajar seperti lebih dari 50 unduhan dalam 10 menit oleh satu akun.

### 6.8 Pengendalian Super Admin

Super Admin dipegang Admin IT Yayasan dengan tujuh kontrol (R3, P2):

1. Perubahan peran dan reset 2FA wajib disetujui Ketua Dewan Pengawas, sejalan dengan Pasal 20 Peraturan WBS.
2. Akun SPI dan Dewan Pengawas memakai 2FA aplikasi, di luar kendali admin Google.
3. Perubahan peran, reset akun, dan login Super Admin memicu peringatan otomatis ke SPI dan Dewan Pengawas.
4. Log berantai hash dan salinannya dikirim ke luar server.
5. Dewan Pengawas diberi peran admin baca saja di Google Admin Console untuk meninjau log admin Google setiap 3 bulan; pengingatnya dikirim sistem.
6. Akun panel penyedia VPS (Rumahweb) didaftarkan atas nama Yayasan dengan 2FA; kata sandi root dan kredensial panel disimpan tersegel oleh Dewan Pengawas. Admin IT bekerja dengan akun sudo atas nama sendiri memakai kunci SSH, dan log sudo dikirim ke luar server.
7. Reset darurat akun atau 2FA lewat server dilakukan dua orang (Admin IT dan anggota Dewan Pengawas), dicatat, dan dilaporkan otomatis.

Prosedur akses server (butir 6):

1. Saat VPS diserahkan, Admin IT login sekali sebagai root untuk membuat akun sudo atas nama sendiri, memasang dua kunci SSH (perangkat utama dan cadangan), dan mematikan login root serta login kata sandi lewat SSH.
2. Kata sandi root diganti dengan kata sandi acak yang panjang, lalu diserahkan tersegel kepada Dewan Pengawas dalam amplop bertanda tangan atau brankas password manager milik Dewan Pengawas. Admin IT tidak menyimpan salinannya.
3. Pekerjaan harian dan deploy tidak memakai root: Admin IT memakai sudo, dan aplikasi di-deploy lewat akun aplikasi tanpa hak sudo.
4. Kode 2FA akun panel Rumahweb dipegang Dewan Pengawas. Upgrade paket, snapshot, konsol darurat, atau instal ulang dilakukan bersama Dewan Pengawas.
5. Bila segel dibuka, pembukaan dicatat, kata sandi root diganti, dan disegel ulang.

Batasan: Super Admin yang memegang akses sudo masih bisa mengubah data langsung di server. Kontrol di atas membuat perubahan semacam itu terdeteksi (rantai hash putus, salinan log di luar server tetap utuh, log sudo di luar server), bukan mustahil.

### 6.9 Kebijakan pendukung

- SOP penggunaan dan klasifikasi dokumen disahkan yayasan sebelum peluncuran.
- Pengguna menyetujui pernyataan kerahasiaan saat login pertama; teks finalnya disusun SPI berdasarkan perjanjian kerahasiaan dalam Dokumen Integritas Auditor Internal.
- Pemrosesan data pribadi mengikuti UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.
- Penilaian dampak pelindungan data pribadi (DPIA) dilakukan sebelum peluncuran, selaras dengan Peraturan Sistem Pengaduan (P6).

## 7. Kebutuhan sistem

### 7.1 Kebutuhan fungsional

| Kode | Kebutuhan |
| --- | --- |
| KF-01 | Sistem hanya menerima login dari akun Google berdomain vidatra.sch.id yang aktif dan sudah diberi peran. |
| KF-02 | Sistem membuat struktur folder baku otomatis saat penugasan dibuat. |
| KF-03 | Sistem menyimpan setiap unggahan ulang sebagai versi baru tanpa menimpa versi lama. |
| KF-04 | Sistem menolak akses ke folder dan berkas yang tidak dibagikan ke pengguna, termasuk lewat URL langsung. |
| KF-05 | Sistem mengirim surel notifikasi untuk permintaan baru, unggahan, hasil pemeriksaan, tenggat H-3, berbagi, persetujuan, dan eskalasi. |
| KF-06 | Sistem mencatat setiap aksi berkas, akses, dan peran ke log audit berantai hash. |
| KF-07 | Sistem mengunci folder penugasan yang sudah ditutup menjadi baca saja. |
| KF-08 | Sistem menampilkan status tiap permintaan dokumen: Menunggu, Diunggah, Diterima, Perlu Perbaikan, Terlambat. |
| KF-09 | Sistem mewajibkan 2FA aplikasi untuk peran yang ditetapkan dan meminta kodenya di setiap login. |
| KF-10 | Sistem menahan perubahan peran dan reset 2FA sampai disetujui Ketua Dewan Pengawas; perubahan atas akun Ketua Dewan Pengawas disetujui anggota Dewan Pengawas lain. |
| KF-11 | Sistem menolak unduhan dan cetak laporan oleh Pengurus Yayasan, dan menampilkan watermark identitas pembaca. |
| KF-12 | Sistem meneruskan otomatis kepada Dewan Pengawas permintaan dokumen yang belum dipenuhi 10 hari kerja setelah tenggat dan tindak lanjut yang melewati tenggat Piagam. |
| KF-13 | Sistem menolak penghapusan berkas pada penugasan yang sudah ditutup selama masa simpan 10 tahun. |

### 7.2 Kebutuhan non-fungsional

| Aspek | Target |
| --- | --- |
| Ketersediaan | 99% pada jam kerja (07.00–17.00 WITA) |
| Kinerja | Halaman terbuka < 2 detik; unggah 10 MB < 30 detik di koneksi kantor |
| Kapasitas | 50 pengguna aktif, 30 pengguna bersamaan |
| Tampilan | Responsif untuk laptop dan ponsel; bahasa Indonesia |
| Pemulihan | RPO 24 jam, RTO < 4 jam |
| Retensi | Log audit dan dokumen penugasan 10 tahun (R6; Piagam Pasal 24 ayat (1)); cadangan harian 30 hari, bulanan 12 bulan, tahunan 10 tahun |
| Pemeliharaan | Kode di repositori [Yayasan-Vidatra/spi-vidatra](https://github.com/Yayasan-Vidatra/spi-vidatra) dengan panduan instalasi; test otomatis berjalan di MySQL |

### 7.3 Teknologi

Mengikuti stack SIGAP Vidatra agar satu orang bisa merawat keduanya.

| Lapisan | Pilihan |
| --- | --- |
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | Inertia.js 3 + React 19 + Vite, Tailwind CSS 4 |
| Panel admin | Filament 5 |
| Basis data | MySQL 8 |
| Login | Laravel Socialite (Google OAuth), project Google Cloud milik organisasi vidatra.sch.id |
| 2FA | TOTP (pragmarx/google2fa) |
| Penyimpanan berkas | Disk VPS, di luar web root, berkas Rahasia terenkripsi AES-256-GCM |
| Antrean dan cache | Redis, nomor basis data dan kata sandi terpisah dari SIGAP |
| Surel | SMTP Google Workspace (akun notifikasi khusus) |
| Antivirus | ClamAV, dipakai bersama SIGAP |
| Web server | Nginx + PHP-FPM, Let's Encrypt |

### 7.4 Infrastruktur dan anggaran

| Komponen | Spesifikasi |
| --- | --- |
| VPS | Rumahweb paket L (2 vCPU, 4 GB RAM, 80 GB), Ubuntu 24.04, data center DCI; server srv1.vidatra.sch.id dipakai bersama SIGAP Vidatra (R8) |
| Upgrade | Ke paket XL (4 vCPU, 8 GB, 160 GB) bila RAM rutin > 80%, disk > 70%, atau saat dipakai semua unit; dibayar pro-rata tanpa migrasi data; turun paket tidak tersedia langsung |
| Domain | spi.vidatra.sch.id (DNS A record ke VPS; surel Google Workspace tidak terpengaruh) |
| Cadangan | Di luar VPS, kapasitas minimal 2× ukuran data ditambah cadangan bulanan dan tahunan |
| Pengguna | Browser terbaru dan akun Google Workspace yayasan |

Anggaran. Tahun pertama mengikuti anggaran VPS SIGAP, sekitar Rp2.988.675 termasuk PPN. Penyimpanan cadangan bulanan dan tahunan di luar VPS (P4) dan pilihan VPS terpisah (6.6) belum termasuk; biayanya dihitung setelah penyimpanan cadangan dipilih.

## 8. Arsitektur sistem

_Diagram: arsitektur sistem, 1 VPS bersama dan 5 layanan di luar server (lihat versi dokumen)._

Pengguna hanya berhubungan dengan Nginx; basis data, Redis, dan folder berkas tidak terjangkau dari internet. Salinan log, hash ujung rantai, dan cadangan dikirim ke luar server, sehingga perubahan di server tetap dapat dibuktikan.

## 9. Struktur folder dan data

### 9.1 Struktur folder baku

Saat Kepala SPI membuat penugasan, sistem otomatis membuat subfolder berikut dari templat di Pengaturan. Nama penugasan memakai format \<tahun>-\<nomor urut>-\<unit>-\<topik singkat>. Struktur ini dipakai sebagai templat final; SPI melengkapi templat dokumen sebelum UAT.

- SPI Yayasan Vidatra
  - 2026
    - 2026-01-SMP-Audit Keuangan Semester 1
      - 01 Perencanaan (surat tugas, program kerja audit)
      - 02 Permintaan Dokumen (subfolder per permintaan, slot unggah dari unit)
      - 03 Kertas Kerja (KKA, catatan auditor)
      - 04 Bukti Unit (salinan dokumen yang sudah diterima)
      - 05 Temuan dan Tanggapan
      - 06 Laporan (draf dan laporan final)
      - 07 Tindak Lanjut (pemantauan rekomendasi)
  - Regulasi dan Pedoman (peraturan yayasan, SOP, piagam audit)
  - Templat (format KKA, surat permintaan, laporan)
  - Arsip (penugasan yang sudah ditutup)

### 9.2 Entitas data utama

| Entitas | Isi pokok |
| --- | --- |
| users | nama, surel, google\_id, unit (untuk PIC), status aktif, login terakhir, kunci 2FA (terenkripsi), kode cadangan (hash) |
| roles, role\_user | peran dan pemegangnya; satu pengguna dapat memegang lebih dari satu peran; penanda Ketua Dewan Pengawas |
| role\_change\_requests | pengguna, perubahan peran atau reset 2FA, alasan, pengaju, penyetuju, status, batas waktu |
| confidentiality\_statements, confidentiality\_acceptances | teks pernyataan per versi; siapa menyetujui, kapan, IP, user agent |
| units | SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian, Yayasan |
| assignments | kode, judul, tahun, unit, status (aktif/ditutup), tanggal mulai dan selesai |
| folders, folder\_templates | nama, induk, penugasan, label klasifikasi, terkunci; templat folder baku dengan penanda folder 02 dan 04 |
| files, file\_versions | nama asli, nama simpan (UUID), ukuran, tipe MIME, hash SHA-256, label, pengunggah, status pindai, versi |
| shares | objek (folder/berkas), penerima (pengguna/peran), hak, kedaluwarsa, disetujui oleh |
| document\_requests, request\_items | penugasan, unit, tenggat, pembuat, status; slot per dokumen dengan format, berkas, status, catatan auditor dan catatan sistem |
| escalations | jenis (permintaan/tindak lanjut), objek, tenggat Piagam, waktu diteruskan, status |
| holidays | tanggal libur nasional dan cuti bersama untuk hitungan hari kerja |
| audit\_logs | waktu, pengguna, aksi, objek, IP, user agent, hash baris sebelumnya, hash baris ini (append-only) |

### 9.3 Shared Drive sementara (P7)

Folder SPI dengan akses terbatas sudah diperlukan untuk K-3 Tahap 1 (permintaan data akhir November 2026), sedangkan aplikasi baru siap setelah UAT. Karena itu:

1. Admin IT membuat Shared Drive khusus SPI di Google Workspace dengan struktur folder 9.1.
2. Anggota Shared Drive hanya Kepala SPI dan Auditor SPI sebagai Pengelola konten, serta Dewan Pengawas sebagai Pengamat. Admin IT tidak menjadi anggota.
3. Unit menyerahkan dokumen lewat folder permintaan yang dibagikan per unit dengan hak Kontributor, dan aksesnya dicabut setelah dokumen diterima.
4. Berbagi ke luar domain dan unduhan oleh Pengamat dimatikan di pengaturan Shared Drive.
5. Saat peluncuran, isi Shared Drive dimigrasikan ke aplikasi dengan alat impor yang mencatat hash SHA-256 setiap berkas sebelum dan sesudah pindah. Setelah verifikasi, Shared Drive dijadikan baca saja selama 3 bulan, lalu diarsipkan.

## 10. Tahapan, risiko, dan keputusan

### 10.1 Tahapan dan jadwal

Setelah dievaluasi ulang, peluncuran ditargetkan awal Februari 2027, bukan pertengahan Januari. Tambahan cakupan r2 (pemisahan peran, eskalasi, cadangan berlapis, alat migrasi) dan libur akhir tahun membuat jadwal 5 minggu tidak realistis untuk satu pengembang. Selama itu SPI memakai Shared Drive sementara (9.3).

| Tahap | Isi | Target |
| --- | --- | --- |
| Demo 1 | Login Google, 2FA, pernyataan kerahasiaan, sesi; penugasan dan folder baku; unggah dan versi berkas | Selesai 24 Nov 2026 |
| Langkah 1 | Enkripsi berkas Rahasia, tempat sampah, pencarian | Selesai 25 Nov 2026 |
| Langkah 2 | Permintaan dokumen ke unit, unggah PIC, pemeriksaan, pengingat tenggat, eskalasi permintaan ke Dewan Pengawas | 2 Des 2026 |
| Langkah 2b | Pemisahan Kepala SPI dan Pengawas SPI, peran ganda, penanda Ketua Dewan Pengawas, penolakan hapus pada penugasan ditutup | 5 Des 2026 |
| Langkah 3 → Demo 2 | Berbagi dan persetujuan Rahasia, laporan baca saja dengan watermark | 12 Des 2026 |
| Langkah 4 | Pengendalian Super Admin: persetujuan peran dan reset 2FA, log berantai dan ekspor, hash ujung rantai ke surel luar domain | 19 Des 2026 |
| Langkah 5 | Dasbor, pengaturan, notifikasi surel, pencabutan sesi, eskalasi tenggat tindak lanjut | 9 Jan 2027 |
| Langkah 6 | Pengerasan keamanan, cadangan berlapis, CI, skrip deploy VPS bersama, alat migrasi Shared Drive | 16 Jan 2027 |
| Langkah 7 | Pembelian VPS, staging, UAT 2 minggu bersama SPI dan satu unit | 18–29 Jan 2027 |
| Peluncuran | Server produksi, SOP, DPIA, akun Dewan Pengawas dan Pengurus, aktivasi 2SV, pelatihan, migrasi Shared Drive dengan verifikasi hash | Awal Feb 2027 |
| Tahap 2 | Komentar, laporan aktivitas, pencarian isi dokumen, watermark unduhan Rahasia | Setelah evaluasi 1 bulan |

Rincian per user story ada di PRD Folder Kerja SPI Vidatra dan Rencana Pengerjaan sampai Deploy di repositori.

### 10.2 Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Dokumen rahasia bocor karena salah atur akses | Tinggi | Akses default ditolak, persetujuan Dewan Pengawas untuk berbagi Rahasia, test akses antar-peran otomatis dan uji manual sebelum rilis |
| Penyalahgunaan kewenangan Super Admin | Tinggi | Persetujuan Ketua Dewan Pengawas, peringatan otomatis, log berantai hash dengan salinan dan hash ujung di luar server, root dan panel VPS disegel Dewan Pengawas |
| Akun Google pegawai diretas | Tinggi | 2SV Google, 2FA aplikasi, sesi 30 menit, peringatan unduhan massal |
| Data hilang karena kerusakan VPS | Tinggi | Cadangan terenkripsi di luar VPS (harian, bulanan, tahunan), uji pemulihan triwulanan |
| Peretasan SIGAP menjangkau data SPI di VPS yang sama | Sedang | Pemisahan pengguna sistem, basis data, PHP-FPM, Redis; enkripsi berkas Rahasia; pemicu pindah ke VPS sendiri (6.6) |
| Penugasan tertunda karena menunggu Dewan Pengawas | Sedang | Kepala SPI dipegang Auditor Internal; Dewan Pengawas hanya menyetujui hal sensitif, dengan anggota sebagai pengganti Ketua |
| Ketua Dewan Pengawas kehilangan perangkat 2FA | Sedang | Persetujuan oleh anggota Dewan Pengawas lain (P3); kode cadangan; reset darurat oleh dua orang |
| Jadwal mundur karena satu pengembang | Sedang | Shared Drive sementara, jadwal bertahap dengan demo, test otomatis di setiap langkah |
| RAM atau disk paket L tidak cukup | Sedang | Pantau pemakaian; upgrade ke XL pro-rata tanpa migrasi data |
| Pengguna tetap berbagi lewat WhatsApp/surel | Sedang | SOP resmi yayasan, pelatihan, kemudahan permintaan dokumen di sistem |
| Ketergantungan pada satu pengembang | Sedang | Kode di GitHub organisasi, PRD dan panduan instalasi di repositori |

### 10.3 Keputusan r2

Sudah diputuskan:

- Kepala SPI dipegang Auditor Internal untuk sementara; Dewan Pengawas menjadi Pengawas SPI dengan persetujuan oleh Ketua dan anggota sebagai pengganti; semua anggota dapat melihat seluruh folder dan log.
- Masa simpan dokumen dan log 10 tahun (Piagam Pasal 24 ayat (1)).
- Kata sandi root dan akun panel Rumahweb disimpan tersegel oleh Dewan Pengawas; Admin IT memakai akun sudo atas nama sendiri (6.8).
- Teks pernyataan kerahasiaan disusun SPI berdasarkan Dokumen Integritas Auditor Internal.
- Struktur folder 9.1 dipakai; SPI melengkapi templat dokumen sebelum UAT.

Masih perlu diputuskan:

- Hak operasional Dewan Pengawas. Matriks masih memberi Dewan Pengawas hak membuat subfolder, mengunggah, dan membuat permintaan dokumen. Usulan: Dewan Pengawas cukup melihat, menyetujui, dan menerima eskalasi, agar pengawas tidak ikut menjalankan audit.
- VPS tetap bersama SIGAP atau terpisah (6.6). Usulan: tetap bersama selama tahun pertama dengan pemicu pindah.
- Sumber tenggat tindak lanjut F-21. Usulan: Kepala SPI mencatat tanggal penyampaian rancangan LHA, LHA final, dan temuan berisiko tinggi di penugasan, lalu sistem menghitung tenggatnya.
- Tempat cadangan bulanan dan tahunan di luar VPS beserta biayanya.
- Alamat surel Dewan Pengawas di luar domain vidatra.sch.id untuk menerima hash ujung rantai log.
- Penanggung jawab dan tanggal pembuatan Shared Drive sementara, sebelum permintaan data K-3 akhir November.

## 11. Progres pengembangan

Per 25 November 2026, 17 dari 34 user story PRD sudah dikerjakan, dan 16 di antaranya sudah lolos uji di browser. Semua 557 test otomatis lulus. Aplikasi masih berjalan di komputer pengembang; server belum dibeli.

Repositori kode: [github.com/Yayasan-Vidatra/spi-vidatra](https://github.com/Yayasan-Vidatra/spi-vidatra) (organisasi Yayasan-Vidatra). PRD, Rancangan, dan Rencana Pengerjaan sampai Deploy tersimpan di folder prd/ repositori ini.

| Bagian | User story | Status |
| --- | --- | --- |
| Login Google, 2FA aplikasi, pernyataan kerahasiaan, sesi 30 menit | US-01–US-04 | Selesai, diuji, digabung ke repositori |
| Penugasan, folder baku, label klasifikasi, unggah, versi, pindai antivirus | US-05–US-11 | Selesai, diuji, digabung ke repositori (Demo 1) |
| Enkripsi berkas Rahasia, tempat sampah, pencarian | US-12–US-14 | Selesai, diuji, digabung ke repositori |
| Permintaan dokumen ke unit dan unggah oleh PIC | US-15–US-16 | Selesai, lolos uji browser |
| Pemeriksaan auditor dan salinan ke 04 Bukti Unit | US-17 | Selesai, uji browser sedang berjalan |
| Pengingat dan eskalasi tenggat permintaan | US-18 | Berikutnya |
| Berbagi, persetujuan, laporan baca saja | US-19–US-21 | Belum |
| Pengendalian Super Admin dan log | US-22–US-27 | Belum |
| Dasbor, pengaturan, notifikasi | US-28–US-30 | Belum |
| Keamanan, cadangan, deploy | Langkah 6–7 | Belum |

Penyesuaian kode akibat r2:

- Pemisahan Kepala SPI dan Pengawas SPI, termasuk peran ganda untuk Auditor Internal, dikerjakan sebagai Langkah 2b sebelum fitur berbagi dan persetujuan.
- Aturan pemeriksa dan ubah tenggat permintaan dokumen, yang semula menyebut Dewan Pengawas, dialihkan ke Kepala SPI.
- Eskalasi permintaan dokumen setelah 10 hari kerja ditambahkan ke US-18; eskalasi tindak lanjut menyusul di Langkah 5.
- ZIP bersandi ditolak, dan penghapusan berkas di penugasan yang ditutup diblokir.

Hal yang sudah teruji di browser antara lain: akun di luar domain dan akun tanpa peran ditolak; PIC unit hanya melihat permintaan unitnya; berkas dengan isi palsu dan format yang tidak diminta ditolak; unggah ulang menjadi versi baru dengan hash SHA-256 yang cocok; auditor yang tidak ditunjuk ditolak termasuk lewat URL langsung.
