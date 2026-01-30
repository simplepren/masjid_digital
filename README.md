# Aplikasi Masjid Digital v1.0

Ini adalah aplikasi untuk menampilkan jadwal sholat secara digital melalui sebuah display atau TV. Aplikasi ini free/berbasis open source dan dapat digunakan oleh siapa saja. Anda bisa mengubah dan memodifikasi aplikasi ini sesuai kebutuhan Anda tanpa harus meminta izin maupun memberikan atribusi apapun. Silakan gunakan dengan bijak.

## Fitur
- Jadwal Sholat realtime, autosync dengan API https://api.myquran.com/
- Profil Masjid
- Bisa setup durasi adzan, iqomah dan sholat sesuai kebutuhan
- Pengaturan kalender hijri (menambah/mengurangi offset) menyesuaikan keputusan pemerintah
- Running text
- Koleksi wallpaper dan bisa diset durasi pergantiannya
- Pemilihan template display TV

## Panduan Instalasi
- Install docker
- Clone repository
```sh
git clone https://github.com/simplepren/masjid_digital
```
- Buka folder masjid_digital
```sh
cd masjid_digital
```
- Edit file .env.production
```sh
sudo nano .env.production
```
- Regitstrasi ke pusher.com, buat aplikasi dan ambil variabel di bawah ini dari pusher.com dan copas ke file .env.production
```sh
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
PUSHER_PORT=443
PUSHER_SCHEME=https
```
- Jalankan perintah ini
```sh
docker compose up -d --build
```
- Buka browser, akses http://localhost:9001 atau http://alamat_IP_server:9001.

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