# QuizgameFadly

Aplikasi kuis PHP Native dan MySQL untuk lingkungan lokal XAMPP maupun Railway.

## Konfigurasi

Atur variabel lingkungan berikut untuk Apache/PHP sebelum menjalankan aplikasi:

- `DB_HOST`
- `DB_USER`
- `DB_PASSWORD` (boleh kosong jika konfigurasi MySQL lokal memang tanpa password)
- `DB_NAME`
- `DB_PORT` (opsional; default `3306`)

Untuk membuat akun admin atau pembuat soal secara otomatis saat aplikasi menyiapkan tabel, atur pasangan variabel berikut. Kata sandi harus minimal 12 karakter.

- `QUIZ_ADMIN_EMAIL` dan `QUIZ_ADMIN_PASSWORD`
- `QUIZ_CREATOR_EMAIL` dan `QUIZ_CREATOR_PASSWORD`

Jangan menyimpan nilai rahasia atau konfigurasi lokal di repository. Jika variabel akun awal tidak diatur, akun tidak dibuat otomatis.

Impor `database/quiz_game.sql` ke MySQL untuk membuat skema dan bank soal. Akun `quiz-seed@invalid.local` yang disisipkan oleh skrip SQL tidak dapat digunakan untuk login; akun itu hanya menjadi pemilik data soal awal.

## Login dengan Google

Atur `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` sebagai environment variables di Railway. `GOOGLE_REDIRECT_URI` opsional; jika tidak diatur, aplikasi membentuk URI callback dari host aktif, jalur instalasi, dan protokol HTTPS dari reverse proxy. Daftarkan URI callback yang sama di Google Cloud Console, misalnya `https://quizgamefadly-production.up.railway.app/Koneksi/google-callback.php`.

Untuk XAMPP, kredensial yang sama dapat diatur di file `.env` lokal (file ini diabaikan Git). Callback otomatis mengikuti jalur instalasi lokal; tambahkan URI hasil konfigurasi tersebut sebagai authorized redirect URI di Google Cloud Console. Autentikasi Google memerlukan ekstensi PHP `curl` dan `openssl`. Bila kredensial belum tersedia, login dan pendaftaran email/password tetap dapat digunakan.

Kolom Google ditambahkan otomatis saat backend menyiapkan tabel akun. Skrip migrasi manual idempoten tersedia di `database/google_auth_migration.sql`.
