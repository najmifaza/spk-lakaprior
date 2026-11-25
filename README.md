# LAKAPRIOR — SPK Penentuan Prioritas Kecamatan Rawan Kecelakaan Lalu Lintas Kabupaten Sukoharjo

Tugas project mata kuliah Sistem Pendukung Keputusan (SPK).

Judul: **LAKAPRIOR: Sistem Pendukung Keputusan Penentuan Prioritas Kecamatan Rawan Kecelakaan
Lalu Lintas di Kabupaten Sukoharjo Menggunakan TOPSIS dan SAW (Studi Kasus Data Kecelakaan 2019–2021)**

- Metode utama: TOPSIS. Pembanding: SAW (Simple Additive Weighting).
- Alternatif: 12 kecamatan Kabupaten Sukoharjo.
- Kriteria: 7 (6 benefit, 1 cost).
- Stack: PHP native + MySQL (XAMPP). Tanpa framework.
- Semua angka berasal dari data terbuka (Satu Data Kabupaten Sukoharjo). Tidak ada data simulasi.

## Struktur

    spk-lakaprior/
    ├── data/
    │   ├── alternatif_kecamatan.csv        luas, penduduk, kepadatan per kecamatan (data terbuka)
    │   ├── laka_sukoharjo_2019-2021.csv    kecelakaan + korban per kecamatan (data terbuka)
    │   ├── hasil_referensi.csv             baseline hitung TOPSIS + SAW (patokan validasi)
    │   └── sumber-asli/                    berkas mentah unduhan + daftar URL sumbernya
    ├── db/
    │   ├── schema.sql                      tabel: users, kecamatan, kriteria, penilaian, hasil
    │   └── seed.sql                        12 kecamatan, 7 kriteria, 84 nilai, 1 user admin
    ├── docs/
    │   └── referensi-jurnal.md             rujukan jurnal ber-SINTA + status verifikasi
    ├── scripts/
    │   └── build_seed.py                   hitung baseline + tulis ulang data/*.csv dan db/seed.sql
    └── src/                                aplikasi web (PHP native + MySQL)
        ├── config.php                      koneksi PDO, session, CSRF, helper format
        ├── layout.php                      kerangka halaman + menu tab
        ├── login.php                       masuk/keluar
        ├── index.php                       tiga tab: Hasil, Cara hitung, Data dan bobot
        ├── hitung.php                      redirect 302 -> index.php?tab=perhitungan (alamat lama)
        ├── data.php                        redirect 302 -> index.php?tab=data (alamat lama)
        ├── style.css                       tampilan
        ├── lib/mcdm.php                    mesin TOPSIS/SAW (fungsi murni, tanpa DB)
        ├── lib/data.php                    ambil/simpan data dari MySQL
        └── scripts/
            ├── selfcheck.php               uji mesin hitung vs baseline CSV (tanpa MySQL)
            └── smoke.sh                    uji HTTP: login, proteksi halaman, CSRF, CRUD

Antarmuka berupa satu halaman bertab; tab aktif dibaca dari query `?tab=` (label di menu pakai
bahasa Indonesia, kuncinya tetap bahasa Inggris).

| Alamat | Label menu | Isi |
|---|---|---|
| `index.php` atau `index.php?tab=keputusan` | Hasil | kecamatan peringkat 1 + peringkat lengkap 12 kecamatan (TOPSIS), jumlah peringkat TOPSIS ≠ SAW |
| `index.php?tab=data` | Data dan bobot | nilai alternatif, bobot + tipe kriteria, tambah/hapus kecamatan dan kriteria |
| `index.php?tab=perhitungan` | Cara hitung | 6 tabel langkah TOPSIS + tabel SAW + perbandingan peringkat |

`hitung.php` dan `data.php` disimpan sebagai alamat lama (bookmark, tautan di modul) dan hanya
mengalihkan ke tab yang sesuai. Setiap aksi POST menyimpan lalu kembali ke tab asal.

## Kriteria

Acuan penilaian: **tingkat kerawanan**. Kriteria *benefit* menaikkan skor kerawanan (semakin besar
nilainya, semakin tinggi prioritas penanganan); kriteria *cost* menurunkannya — di sini luas wilayah,
karena kecamatan berarea luas dengan jumlah kecelakaan setara punya kerapatan risiko lebih rendah.

| Kode | Kriteria | Satuan | Tipe | Bobot | Data |
|------|----------|--------|------|-------|------|
| C1 | Jumlah kejadian kecelakaan lalu lintas 2019–2021 | kejadian | benefit | 0,20 | terbuka (Polres Sukoharjo) |
| C2 | Korban meninggal dunia 2019–2021 | jiwa | benefit | 0,20 | terbuka (Polres Sukoharjo) |
| C3 | Korban luka berat 2019–2021 | jiwa | benefit | 0,10 | terbuka (Polres Sukoharjo) |
| C4 | Korban luka ringan 2019–2021 | jiwa | benefit | 0,10 | terbuka (Polres Sukoharjo) |
| C5 | Jumlah penduduk 2019 | jiwa | benefit | 0,15 | terbuka (DKB Dukcapil) |
| C6 | Kepadatan penduduk | jiwa/km2 | benefit | 0,15 | turunan C5 / luas |
| C7 | Luas wilayah | km2 | cost | 0,10 | terbuka (Satu Data Sukoharjo) |

Catatan pembobotan: bobot ditetapkan langsung (rating langsung) dengan urutan kepentingan
kejadian = korban meninggal > jumlah korban lain > paparan penduduk > luas. Untuk klaim
"pembobotan primer", ganti angka bobot dengan hasil wawancara Satlantas/Dishub atau AHP pairwise,
lalu tulis metode dan respondennya di laporan. C5 dan C6 memakai bahan yang sama (penduduk, luas),
jadi jangan diklaim sebagai dua sumber paparan yang benar-benar bebas.

## Sumber data

Semua dari portal terbuka Satu Data Kabupaten Sukoharjo (data.sukoharjokab.go.id). Berkas mentahnya
disimpan di `data/sumber-asli/` beserta URL dan tanggal unduh, supaya bisa dicek ulang.

- Kecelakaan dan korban per kecamatan 2019, 2020, 2021 — Satlantas Polres Sukoharjo, tabel
  "Banyaknya Kecelakaan Lalu Lintas dan Korbannya Menurut Kecamatan". Total 2019 = 1.279,
  2020 = 994, 2021 = 1.135 kejadian; jumlah tiga tahun 3.408 kejadian.
- Jumlah penduduk per kecamatan 2019 — Dinas Kependudukan dan Pencatatan Sipil, Data Konsolidasi
  Bersih (DKB) per 31 Desember 2019. Total kabupaten 906.403 jiwa.
- Luas wilayah per kecamatan 2022 — Satu Data Sukoharjo. Total 466,66 km2.

Yang **tidak** dipakai, plus alasannya:

- Kerugian material per kecamatan: tidak tersedia per kecamatan di sumber terbuka (hanya total
  kabupaten), jadi kriteria kerugian dikeluarkan dari model.
- Panjang jalan / kondisi jalan per kecamatan: data jalan yang terbuka hanya per status dan kelas
  jalan tingkat kabupaten, bukan per kecamatan. Karena itu kriteria jalan dikeluarkan, bukan dikira-kira.
- Data kecelakaan 2022 per kecamatan belum terbit; periode studi kasus memakai 2019–2021.

Perbedaan kecil di sumber: baris total kabupaten pada berkas 2020 tidak selalu sama dengan
penjumlahan baris kecamatan (mis. luka berat tercatat 1 di satu berkas dan 65 di berkas lain).
Perhitungan sistem memakai **penjumlahan baris per kecamatan**; nilai luka berat memang kecil
(2 jiwa pada 2019, 1 pada 2020, 3 pada 2021).

## Menjalankan

1. Import database (XAMPP sudah jalan, MariaDB 10.4):

       /c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE spk_lakaprior CHARACTER SET utf8mb4"
       /c/xampp/mysql/bin/mysql.exe -u root spk_lakaprior < db/schema.sql
       /c/xampp/mysql/bin/mysql.exe -u root spk_lakaprior < db/seed.sql

   Bisa juga lewat phpMyAdmin: buat database `spk_lakaprior`, lalu Import `db/schema.sql` dan `db/seed.sql`.

2. Jalankan aplikasi (dari folder `spk-lakaprior`):

       php -S 127.0.0.1:8123 -t src

   Buka http://127.0.0.1:8123/login.php — akun awal `admin` / `admin123` (ganti sebelum demo).
   Kalau pakai Apache XAMPP, copy folder `src/` ke `htdocs/spk-lakaprior` dan sesuaikan `DB_*` di `config.php`.

3. Uji:

       php src/scripts/selfcheck.php     # mesin hitung vs baseline CSV, tanpa MySQL
       bash src/scripts/smoke.sh         # HTTP: login, proteksi halaman, CSRF, simpan bobot

   `selfcheck` mencocokkan 12 alternatif dengan `data/hasil_referensi.csv` (Ci, skor SAW, kedua
   peringkat) dan memeriksa total kejadian 3.408. `smoke.sh` butuh server `php -S` masih hidup.

4. Ubah data lalu hitung ulang tanpa menyentuh database secara manual:

       python scripts/build_seed.py                  # tulis ulang data/*.csv + db/seed.sql
       /c/xampp/mysql/bin/mysql.exe -u root spk_lakaprior < db/schema.sql
       /c/xampp/mysql/bin/mysql.exe -u root spk_lakaprior < db/seed.sql

Catatan keamanan: `db/seed.sql` menyimpan password sebagai SHA2-256 MySQL (kolom `password_hash CHAR(64)`).
`login.php` sudah menerima hash `password_hash()` PHP juga, tapi untuk produksi ganti hash seed
dengan `password_hash()` dan hapus fallback SHA2.

## Catatan format

CSV dan SQL memakai titik sebagai desimal (3.13, bukan 3,13). Di laporan docx, ubah ke koma.

## Baseline perhitungan (patokan validasi)

`data/hasil_referensi.csv` = hasil hitung 12 alternatif x 7 kriteria dengan bobot di atas
(dihitung independen di `scripts/build_seed.py`, untuk pembanding saat uji). Hasil TOPSIS:

| Peringkat | Kecamatan | Ci | Peringkat SAW |
|---|---|---|---|
| 1 | Sukoharjo | 0,7099 | 2 |
| 2 | Kartasura | 0,6881 | 1 |
| 3 | Grogol | 0,5766 | 3 |
| 4 | Mojolaban | 0,3612 | 4 |
| 5 | Baki | 0,2968 | 5 |
| 10 | Tawangsari | 0,2243 | 10 |
| 11 | Nguter | 0,1933 | 11 |
| 12 | Bulu | 0,0659 | 12 |

Selisih peringkat TOPSIS vs SAW: **5 dari 12 alternatif** — bahan bab analisis komparatif.
Nilai D+, D−, Ci, dan skor SAW lengkap ada di CSV-nya.

Contoh nilai untuk uji manual: Sukoharjo → D+ = 0,062795 ; D− = 0,153679 ; Ci = 0,709921.

## Langkah berikut

1. Laporan + slide (Bab 1–5) memakai angka di README ini.
2. Kalau periode mau diperpanjang ke 2022–2023, ambil berkas kecelakaan 2022/2023 dari portal
   yang sama dan tambahkan ke `scripts/build_seed.py`.
3. Kalau mau klaim pembobotan primer, tambahkan bab metode pembobotan (AHP/rating) beserta responden.