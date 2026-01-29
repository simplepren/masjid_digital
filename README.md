# Aplikasi Masjid Digital

Ini adalah aplikasi untuk menampilkan jadwal sholat secara digital melalui sebuah display atau TV. Aplikasi ini free/berbasis open source dan dapat digunakan oleh siapa saja. Anda bisa mengubah dan memodifikasi aplikasi ini sesuai kebutuhan Anda tanpa harus meminta izin maupun memberikan atribusi apapun. Silakan gunakan dengan bijak.

## Fitur
- Jadwal Sholat realtime, autosync dengan API https://api.myquran.com/
- Profil Masjid
- Setup durasi adzan, iqomah dan sholat
- Pengaturan kalender hijri (menambah/mengurangi offset) menyesuaikan keputusan pemerintah
- Running text
- Koleksi wallpaper dan bisa diset durasi pergantiannya
- Pemilihan template display TV

## Panduan Instalasi
- Install docker
```sh
git clone https://github.com/simplepren/masjid_digital
docker compose up -d --build
```
- Buka browser, akses http://localhost:81

## Panduan Penggunaan
- Login melalui route /login
- Lengkapi Profil Masjid
- Sinkronisasi Jadwal Sholat pada menu Jadwal Sholat (API sebaiknya jangan diubah/diganti)
- Atur Durasi Adzan, Iqomah, dan Sholat pada menu Pengaturan Sholat
- Pilih Template Display pada menu Template Display
- Silakan buka/akses tampilan display masjid (TV) melalui route /display

## Tech Stack
- Laravel v.12
- LiveWire v.4
- Alpine JS