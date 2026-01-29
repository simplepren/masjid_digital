# Aplikasi Masjid

Aplikasi ini free/berbasis open source dan dapat digunakan oleh siapa saja. Anda bisa mengubah dan memodifikasi aplikasi ini sesuai kebutuhan Anda tanpa harus meminta izin maupun memberikan atribusi apapun. Silakan gunakan dengan bijak.

## Fitur
- Jadwal Sholat realtime, autosync dengan API https://api.myquran.com/
- Profil Masjid
- Setup durasi adzan, iqomah dan sholat
- Pengaturan kalender hijri (menambah/mengurangi offset) menyesuaikan keputusan pemerintah
- Running text
- Koleksi wallpaper dan bisa diset durasi pergantiannya
- Pemilihan template display TV

## Panduan Instalasi
- git clone https://github.com/simplepren/masjid_digital
- cp .env.example cp
- docker compose up -d --build

## Tech Stack
- Laravel v.12
- LiveWire v.4
- Alpine JS