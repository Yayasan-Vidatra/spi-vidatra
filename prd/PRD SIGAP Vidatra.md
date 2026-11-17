# PRD SIGAP Vidatra

Nov 16, 2026 · @Luthfianda Salsabila

Dokumen kebutuhan produk untuk **SIGAP Vidatra** (sigap.vidatra.sch.id). Acuan: Rancangan Sistem r1 dan TOR Pengembangan SIGAP Vidatra. Setiap kebutuhan diberi kode agar progres bisa dicocokkan dengan rancangan secara berkala.

## 1. Ringkasan Produk

**Masalah.** Keluhan orang tua, siswa, dan pegawai Yayasan Vidatra masuk lewat WhatsApp, lisan, dan media sosial. Laporan tidak tercatat, tidak ada batas waktu, dan pelapor pelanggaran serius tidak punya jalur yang aman dan rahasia.

**Solusi.** SIGAP Vidatra adalah website dengan dua kanal: **Pengaduan** untuk keluhan layanan, dan **Pelaporan Pelanggaran (WBS)** untuk dugaan pelanggaran serius. Keduanya menerima lampiran dokumen, foto, dan video, bisa dilacak dengan kode lacak, dan ditangani lewat panel petugas sesuai peran.

**Tujuan:**

1. Semua pengaduan unit (SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian) masuk lewat satu pintu dan tercatat.
2. Setiap laporan punya penanggung jawab, status, dan batas waktu yang terlihat oleh pelapor.
3. Pelapor WBS terlindungi: boleh anonim, identitas terenkripsi, dan laporan hanya sampai ke penerima yang tidak terlibat.
4. Laporan kekerasan dan etik dialihkan ke Kepala Sekolah atau Komite Etik secara tercatat.

**Bukan tujuan (di luar cakupan):**

- Aplikasi seluler native dan integrasi WhatsApp otomatis.
- Penjatuhan sanksi; sistem hanya mencatat rekomendasi, keputusan tetap di penerima hasil.
- Pengaduan anonim di kanal Pengaduan; anonim hanya berlaku untuk WBS.

## 2. Pengguna

| Pengguna | Kebutuhan utama | Halaman (TOR) |
| --- | --- | --- |
| Pelapor Pengaduan (orang tua, siswa via wali, alumni, masyarakat) | Mengirim keluhan dari HP dalam < 5 menit dan tahu perkembangannya | H-01, H-02, H-05–H-07 |
| Pelapor WBS (siapa pun, termasuk pegawai dan guru) | Melapor tanpa takut identitasnya bocor atau dibalas | H-03–H-07 |
| Admin Pengaduan | Memilah dan meneruskan pengaduan dengan cepat | H-10, H-11, H-18 |
| PIC Unit | Melihat pengaduan unitnya dan melaporkan penyelesaiannya | H-10, H-12 |
| Tim WBS (Ketua: SPI) | Menelaah dan menginvestigasi secara rahasia, mencatat laporan dari saluran lain | H-13–H-15, H-20 |
| Penerima Hasil WBS | Menerima rekomendasi yang menjadi bagiannya | H-16, H-18 |
| Kepala Sekolah dan Komite Etik | Menerima dan menindaklanjuti laporan yang dialihkan | H-17 |
| Pimpinan Yayasan | Melihat gambaran umum tanpa membuka identitas pelapor | H-10, H-18 |
| Super Admin | Mengelola pengguna, unit, data jabatan, dan konten | H-19, H-20 |

## 3. Ukuran Keberhasilan

Target berikut diukur dari data sistem sendiri, 3 bulan setelah peluncuran.

| Ukuran | Target |
| --- | --- |
| Pengaduan yang mendapat tanggapan awal ≤ 2 hari kerja | ≥ 90% |
| Pengaduan selesai ≤ 14 hari kerja | ≥ 80% |
| Telaah WBS selesai ≤ 5 hari kerja | 100% |
| Laporan kekerasan dialihkan ≤ 1 × 24 jam | 100% |
| Rata-rata penilaian kepuasan pelapor Pengaduan | ≥ 4 dari 5 |
| Formulir yang dimulai dan berhasil dikirim | ≥ 70% |
| Insiden kebocoran identitas pelapor WBS | 0 |

## 4. Kebutuhan Fungsional

Kebutuhan ditulis sebagai user story dengan kriteria penerimaan yang bisa diuji. Kode US dipakai di matriks keterlacakan (Bagian 7). US-01 sampai US-27 masuk Tahap 1; US-28 sampai US-31 masuk Tahap 2.

### M1 Pelaporan publik

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-01 | Sebagai pelapor, saya ingin memilih antara Pengaduan dan WBS di Beranda agar laporan saya masuk ke kanal yang tepat | Beranda menampilkan dua tombol beserta penjelasan beda keduanya dan kontak darurat sekolah dari Pengaturan |
| US-02 | Sebagai orang tua, saya ingin mengirim pengaduan dari HP agar keluhan saya tercatat resmi | Formulir bisa diselesaikan < 5 menit di HP; isian wajib divalidasi tanpa menghapus isian lain; draf tersimpan otomatis |
| US-03 | Sebagai siswa, saya ingin mengadu dengan kontak orang tua/wali agar data saya diproses dengan persetujuan wali | Memilih "siswa" mengganti kolom kontak menjadi kontak orang tua/wali dengan kotak persetujuan wajib |
| US-04 | Sebagai pelapor, saya ingin melampirkan dokumen, foto, dan video agar laporan saya punya bukti | Maks. 5 berkas, total 200 MB, video ≤ 100 MB dengan unggah bertahap; format salah ditolak dengan alasan; berkas berbahaya dikarantina |
| US-05 | Sebagai pelapor WBS, saya ingin melapor secara anonim agar identitas saya tidak diketahui | Mode anonim menyembunyikan kolom identitas; tidak ada IP, cookie pelacak, atau analitik di halaman WBS; metadata lampiran dihapus |
| US-06 | Sebagai pelapor WBS, saya ingin membaca jaminan kerahasiaan sebelum melapor agar saya yakin aman | Halaman /wbs menampilkan dokumen dari file viewer; formulir hanya terbuka setelah menekan "Saya mengerti" |

### M2 Pelacakan dan komunikasi

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-07 | Sebagai pelapor, saya ingin mendapat kode lacak setelah mengirim agar bisa memantau laporan | Kode unik dan acak; PDF bukti lapor bisa diunduh; kode WBS hanya tampil sekali; surel konfirmasi untuk Pengaduan |
| US-08 | Sebagai pelapor, saya ingin melihat status dan riwayat laporan dengan kode lacak (+ PIN untuk WBS) | Status dan riwayat tampil; 5 percobaan salah memicu CAPTCHA dan jeda; sesi berakhir setelah 15 menit tidak aktif |
| US-09 | Sebagai pelapor, saya ingin berkirim pesan dan bukti tambahan dengan petugas tanpa membuka identitas saya | Pesan dua arah dengan lampiran; catatan internal petugas tidak terlihat oleh pelapor |

### M3 Penanganan Pengaduan

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-10 | Sebagai Admin, saya ingin memverifikasi dan mengkategorikan pengaduan agar yang tidak layak tersaring | Filter dan pencarian tersedia; penolakan wajib alasan; status berubah dan pelapor menerima notifikasi |
| US-11 | Sebagai Admin, saya ingin mendisposisikan pengaduan ke unit dan PIC agar ada penanggung jawab | Batas waktu dihitung dari SLA kategori; PIC menerima surel; pengingat dan eskalasi saat lewat batas |
| US-12 | Sebagai PIC Unit, saya ingin melihat pengaduan unit saya saja dan mencatat tindak lanjutnya | PIC tidak dapat membuka pengaduan unit lain; bukti penyelesaian bisa diunggah; status menjadi Menunggu Konfirmasi |
| US-13 | Sebagai pelapor, saya ingin mengonfirmasi atau menolak hasil dan memberi penilaian | Konfirmasi atau 7 hari tanpa respons → Selesai; tolak → kembali Diproses; penilaian 1–5 tersimpan |

### M4 Penanganan WBS

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-14 | Sebagai Tim WBS, saya ingin melihat laporan WBS yang menjadi bagian saya menurut matriks | Laporan dengan terlapor SPI/Tim WBS, Pengawas, atau Pembina tidak terlihat oleh Ketua Tim WBS; Super Admin tidak dapat membuka isi WBS |
| US-15 | Sebagai Ketua Tim WBS, saya ingin menelaah kelayakan dan memeriksa kedudukan terlapor | Hasil telaah dan koreksi kedudukan tercatat; koreksi yang membuat Ketua harus mundur memindahkan akses ke penelaah pengganti |
| US-16 | Sebagai Ketua Tim WBS, saya ingin membuka identitas pelapor bila perlu, secara tercatat | Hanya Ketua (atau penelaah pengganti) yang bisa membuka; setiap pembukaan masuk log audit |
| US-17 | Sebagai investigator, saya ingin mencatat temuan dan menetapkan kesimpulan dengan rekomendasi | Catatan internal tersimpan terenkripsi; kesimpulan Terbukti/Tidak terbukti/Bukti tidak cukup; perpanjangan butuh persetujuan penerima hasil |
| US-18 | Sebagai Ketua Tim WBS, saya ingin mencatat laporan yang masuk lewat surel, telepon, atau surat | Formulir dengan kolom sumber; lampiran tangkapan layar/pindaian; kode lacak dan PIN sementara terbentuk |

### M5 Pengalihan dan perutean

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-19 | Sebagai Admin atau Tim WBS, saya ingin mengalihkan laporan kekerasan atau etik ke Kepala Sekolah atau Komite Etik | Ringkasan tanpa identitas pelapor; tujuan mengonfirmasi terima; status pelapor berubah menjadi Dialihkan |
| US-20 | Sebagai sistem, saya harus menolak pengalihan ke Kepala Sekolah yang menjadi terlapor | Tujuan ditolak; Ketua Tim WBS diberi opsi rujuk ke Pokja BSAN |
| US-21 | Sebagai Penerima Hasil, saya ingin menerima ringkasan dan rekomendasi WBS yang menjadi bagian saya | Hasil terkirim sesuai matriks, tanpa identitas pelapor; penerima bisa menyetujui perpanjangan dan menandai tindak lanjut |

### M6 Dashboard dan rekap

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-22 | Sebagai petugas, saya ingin dashboard sesuai peran agar tahu apa yang harus dikerjakan | Ringkasan laporan baru, lewat batas waktu, dan menunggu tindakan saya |
| US-23 | Sebagai Pimpinan, saya ingin rekap tanpa identitas pelapor | Pengaduan per unit; WBS hanya jumlah per kategori dan tampil bila ≥ 3 laporan; ekspor Excel/PDF |

### M7 Administrasi dan keamanan

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-24 | Sebagai Super Admin, saya ingin mengelola unit, data jabatan, dan kontak darurat agar pergantian pejabat cukup diubah di sistem | Nama dan surel Admin, PIC, Kepala Sekolah, Komite Etik, serta kontak darurat tersimpan di basis data dan bisa diubah tanpa mengubah kode |
| US-25 | Sebagai Super Admin, saya ingin mengganti berkas jaminan kerahasiaan tanpa bantuan pengembang | Unggah berkas baru langsung tampil di file viewer halaman /wbs |
| US-26 | Sebagai petugas, saya ingin login aman | SSO Google Workspace untuk Admin dan PIC; akun lokal untuk peran WBS; 2FA wajib; terkunci setelah 5 kali gagal |
| US-27 | Sebagai Ketua Tim WBS, saya ingin semua akses tercatat | Setiap pembukaan laporan dan lampiran tercatat; log tidak bisa diubah atau dihapus siapa pun |

### Tahap 2

| Kode | User story | Kriteria penerimaan |
| --- | --- | --- |
| US-28 | Sebagai Ketua Tim WBS, saya ingin mengirim Laporan Khusus ke Ketua Pembina saat ada indikasi kecurangan | Terkirim hanya ke Ketua Pembina (atau Pembina lain bila terlibat); penghitung tenggat 1 × 24 jam |
| US-29 | Sebagai Dewan Pengawas dan Pembina, saya ingin rekap berkala otomatis | Rekap triwulanan dan tahunan terbentuk otomatis tanpa identitas pelapor |
| US-30 | Sebagai Yayasan, saya ingin kunci enkripsi WBS dikendalikan dua orang | Pembukaan di luar aplikasi butuh Ketua Tim WBS dan Ketua Dewan Pengawas; kunci induk tidak ada di basis data maupun cadangan |
| US-31 | Sebagai terlapor, saya berhak diberi tahu dan menjelaskan sebelum kesimpulan | Tanggal pemberitahuan dan penjelasan, atau alasan penundaan, wajib terisi sebelum kesimpulan ditetapkan |

## 5. Kebutuhan Non-Fungsional

| Kode | Aspek | Kebutuhan |
| --- | --- | --- |
| NF-01 | Keamanan | HTTPS wajib; 2FA untuk semua petugas; data dan lampiran WBS terenkripsi saat disimpan |
| NF-02 | Privasi | Sesuai UU 27/2022; data pelapor hanya untuk penanganan laporan; tanpa log IP di jalur /wbs, baik di aplikasi maupun Nginx |
| NF-03 | Kinerja | Halaman publik terbuka < 3 detik di 4G; unggah video 100 MB tetap jalan di koneksi tidak stabil |
| NF-04 | Ketersediaan | Uptime ≥ 99%; cadangan harian terenkripsi di luar VPS, disimpan ≥ 30 hari |
| NF-05 | Kegunaan | Responsif di HP; bahasa Indonesia sederhana; formulir selesai < 5 menit |
| NF-06 | Retensi | Laporan disimpan 5 tahun setelah ditutup, lalu dimusnahkan dengan berita acara |
| NF-07 | Audit | Log audit hanya bisa ditambah, tidak bisa diubah atau dihapus |
| NF-08 | Pemeliharaan | Stack Laravel 11, React + Vite (Inertia.js), Filament, MySQL; kode di repositori GitHub organisasi Yayasan dengan panduan instalasi |

## 6. Rilis dan Tahapan

| Rilis | Isi | Kapan | Syarat lolos |
| --- | --- | --- | --- |
| Demo 1 | Fondasi, login, Beranda, Formulir Pengaduan, lampiran, Bukti Lapor | Hari 5 | US-01–US-04, US-07, US-26 lolos |
| Demo 2 | Panel Pengaduan, lacak dan pesan, kanal WBS, Ruang Kerja WBS | Hari 10 | US-05, US-08–US-18 lolos |
| Serah terima Tahap 1 | Pengalihan, Hasil WBS, dashboard, rekap, Pengaturan, log audit | Hari 15 | US-01–US-27 lolos; kanal Pengaduan dibuka ke publik |
| Tahap 2 | Laporan Khusus, rekap berkala, kunci kendali ganda, hak terlapor, uji keamanan independen | Setelah serah terima | US-28–US-31 lolos dan uji keamanan bersih; kanal WBS dibuka ke publik |

## 7. Pemantauan Kesesuaian dengan Rancangan

Progres dicek pada tiga tingkat agar penyimpangan dari Rancangan r1 dan TOR ketahuan sebelum menumpuk. Matriks di bawah ini adalah alat kerjanya: statusnya diperbarui langsung di dokumen ini.

### Jadwal pengecekan

| Kapan | Siapa | Yang dicek | Hasil |
| --- | --- | --- | --- |
| Setiap akhir hari kerja | Pengembang | Status US yang dikerjakan hari itu di matriks; hambatan | Matriks diperbarui |
| Hari 5, 10, 15 (demo) | Pengembang dan pimpinan | US target rilis itu dicoba langsung terhadap kriteria penerimaan dan daftar periksa di bawah | Status menjadi Sesuai rancangan atau Ada deviasi; catatan demo |
| Setiap ada perubahan dari rancangan | Pengembang, disetujui pimpinan | Alasan dan dampak perubahan | Baris baru di log deviasi sebelum dikerjakan |
| Sebelum serah terima dan sebelum WBS dibuka ke publik | Pimpinan; uji keamanan oleh pihak independen (Tahap 2) | Semua US tahap itu berstatus Sesuai rancangan | Berita acara serah terima |

### Daftar periksa setiap demo

- [ ] Setiap US target rilis lolos kriteria penerimaannya saat dicoba langsung, bukan hanya dilaporkan.
- [ ] Hak akses sesuai peran: PIC tidak melihat unit lain; Super Admin tidak membuka isi WBS; matriks penerima berlaku (Rancangan r1 Bagian 3.1).
- [ ] Status laporan yang terlihat pelapor sesuai Rancangan r1 Bagian 6.
- [ ] Struktur data sesuai Rancangan r1 Bagian 10; perubahan tabel tercatat di log deviasi.
- [ ] Halaman WBS tidak mencatat IP dan tidak memuat analitik (periksa log Nginx dan sumber halaman).
- [ ] Tidak ada fitur di luar cakupan yang ditambahkan tanpa log deviasi.

### Matriks keterlacakan

Acuan: UC dan F dari Rancangan r1; H dari TOR.

| Kode | Acuan rancangan | Halaman | Target | Status | Catatan |
| --- | --- | --- | --- | --- | --- |
| US-01 | F-01 | H-01 | Demo 1 | Belum mulai |  |
| US-02 | UC-01, F-02 | H-02 | Demo 1 | Belum mulai |  |
| US-03 | UC-01, F-02 | H-02 | Demo 1 | Belum mulai |  |
| US-04 | UC-03, UC-17, F-04 | H-02, H-04 | Demo 1 | Belum mulai |  |
| US-05 | UC-02, F-03 | H-04 | Demo 2 | Belum mulai |  |
| US-06 | F-01, Bagian 9 | H-03 | Demo 2 | Belum mulai |  |
| US-07 | F-05 | H-05 | Demo 1 | Belum mulai |  |
| US-08 | UC-04, F-06 | H-06, H-07 | Demo 2 | Belum mulai |  |
| US-09 | UC-05, F-06 | H-07 | Demo 2 | Belum mulai |  |
| US-10 | UC-08, UC-11, F-08 | H-11 | Demo 2 | Belum mulai |  |
| US-11 | UC-09, F-11, F-12 | H-11 | Demo 2 | Belum mulai |  |
| US-12 | UC-10, F-09 | H-12 | Demo 2 | Belum mulai |  |
| US-13 | UC-06, F-06 | H-07 | Demo 2 | Belum mulai |  |
| US-14 | F-19, Bagian 3.1 | H-13 | Demo 2 | Belum mulai |  |
| US-15 | UC-12 | H-13 | Demo 2 | Belum mulai |  |
| US-16 | UC-12, Bagian 9 | H-13 | Demo 2 | Belum mulai |  |
| US-17 | UC-13, F-10 | H-13 | Demo 2 | Belum mulai |  |
| US-18 | UC-18, F-17 | H-14 | Demo 2 | Belum mulai |  |
| US-19 | UC-19, F-18 | H-11, H-13, H-17 | Serah terima | Belum mulai |  |
| US-20 | UC-19, F-18 | H-17 | Serah terima | Belum mulai |  |
| US-21 | UC-13, F-19 | H-16 | Serah terima | Belum mulai |  |
| US-22 | F-13 | H-10 | Serah terima | Belum mulai |  |
| US-23 | UC-14, F-13, F-14 | H-18 | Serah terima | Belum mulai |  |
| US-24 | UC-15, F-15 | H-19 | Serah terima | Belum mulai |  |
| US-25 | F-15 | H-19 | Serah terima | Belum mulai |  |
| US-26 | UC-07 | H-09 | Demo 1 | Belum mulai |  |
| US-27 | F-16 | H-20 | Serah terima | Belum mulai |  |
| US-28 | UC-20, F-20 | H-15 | Tahap 2 | Belum mulai |  |
| US-29 | F-22 | H-18 | Tahap 2 | Belum mulai |  |
| US-30 | Bagian 9 | — | Tahap 2 | Belum mulai |  |
| US-31 | F-21 | H-13 | Tahap 2 | Belum mulai |  |

### Log deviasi

Setiap perbedaan dari Rancangan r1 atau TOR dicatat di sini dan disetujui sebelum dikerjakan.

| Tanggal | Kode terkait | Perubahan dari rancangan | Alasan | Disetujui oleh |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## 8. Asumsi dan Pertanyaan Terbuka

**Asumsi:**

- Pengembang satu orang penuh waktu selama 15 hari kerja, dengan VPS dan akses DNS tersedia di Hari 1.
- Platform akun Google Workspace; nama petugas, data jabatan, dan kontak darurat diinput lewat Pengaturan, bukan ditanam di kode.
- Susunan Tim WBS dan penerima hasil bersifat internal dan tidak dicantumkan di dokumen ini; sistem cukup menyediakan perannya.

**Pertanyaan terbuka:**

- [ ] Kategori pengaduan dan jenis pelanggaran final (sebagian sudah ada di Peraturan Pasal 3–5).
- [ ] Isi dokumen jaminan kerahasiaan, yang akan diunggah lewat file viewer (menyusul).
- [ ] Pihak dan jadwal uji keamanan independen di Tahap 2.
- [ ] Tanggal mulai pengerjaan, untuk mengganti Hari 1–15 dengan tanggal kalender.
