# QuizgameFadly

Aplikasi kuis PHP dan MySQL untuk lingkungan lokal XAMPP.

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
