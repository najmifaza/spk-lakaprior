# Berkas mentah dari portal data terbuka

Disalin apa adanya pada 2026-11-24 dari Satu Data Kabupaten Sukoharjo (data.sukoharjokab.go.id),
supaya angka di `data/*.csv` bisa ditelusuri ulang dan tidak perlu percaya pada ringkasan.

| Berkas | Isi | URL unduhan |
|---|---|---|
| `laka2019.xlsx` | Banyaknya Kecelakaan Lalu Lintas dan Korbannya Menurut Kecamatan, 2019 | https://data.sukoharjokab.go.id/dataset/ffed0173-5f85-414e-aa3a-75e1836e578b/resource/47af9dec-66eb-4bab-befe-b97ef124d41e/download/9.17banyaknya-kecelakaan-lalu-lintas-dan-korbannya-menurut-kecamatan-2019.xlsx |
| `laka2020.xlsx` | idem, 2020 | https://data.sukoharjokab.go.id/dataset/ffed0173-5f85-414e-aa3a-75e1836e578b/resource/3277f751-3f5c-485c-86f1-8bd4568bdd5a/download/9.17banyaknya-kecelakaan-lalu-lintas-dan-korbannya-menurut-kecamatan-2020.xlsx |
| `laka2021.xlsx` | idem, 2020 (sheet 1) dan 2021 (sheet 2) | https://data.sukoharjokab.go.id/dataset/b9a45920-231a-4970-877a-8af9d9440d39/resource/9d02a738-a0c6-4d33-96c8-53934816e4b0/download/banyaknya-kecelakaan-lalu-lintas-dan-korbannya-menurut-kecamatan-2021.xlsx |
| `luas2022.xlsx` | Luas Wilayah dan Persentase Menurut Kecamatan, 2022 | https://data.sukoharjokab.go.id/dataset/6a9e588e-7588-4b64-943d-83fd0ae3baf7/resource/9cc6f683-5335-4964-94e6-d86045fef794/download/2022-luas-wilayah-dan-presentase-menurut-kecamatan-kabupaten-sukoharjo.xlsx |
| `jiwa2019.xls` | Jumlah Jiwa per Kecamatan menurut DKB, 2019 | https://data.sukoharjokab.go.id/dataset/fcfe36cd-51ad-4ddf-ae60-fe38666c9ee9/resource/b99cce24-8690-489f-85c2-aa03d6c50b38/download/01_jumlah_jiwa_kec-2019.xls |

Catatan per berkas:

- Ketiga berkas laka mencantumkan "Sumber: Satlantas Polres Sukoharjo" di baris terakhir tabel.
- `laka2021.xlsx` punya dua sheet: sheet 1 memuat data 2020 (sama dengan `laka2020.xlsx`),
  sheet 2 memuat data 2021. Angka 2021 diambil dari sheet 2.
- `laka2018.xlsx` tidak dipakai (di luar rentang studi kasus) tetapi tersedia di dataset yang sama
  bila periodenya mau diperpanjang.
- `jiwa2019.xls` sebenarnya berformat SpreadsheetML (XML gaya Excel 2003), bukan BIFF.
- Baris total kota di berkas-berkas itu tidak selalu konsisten dengan penjumlahan baris kecamatan
  (mis. luka berat 2020 tercatat 1 di satu berkas dan 65 di berkas lain). Perhitungan memakai
  **penjumlahan baris per kecamatan**, dan selisih ini ditulis di README.
