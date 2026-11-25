CREATE DATABASE IF NOT EXISTS client_serveradin
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE client_serveradin;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_lengkap VARCHAR(120) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pembuat_soal', 'user') NOT NULL DEFAULT 'user',
    asal_daerah VARCHAR(150) NOT NULL,
    jenis_kelamin VARCHAR(20) NULL,
    negara VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
    provinsi VARCHAR(100) NULL,
    kabupaten_kota VARCHAR(100) NULL,
    kecamatan VARCHAR(100) NULL,
    kelurahan_desa VARCHAR(100) NULL,
    nama_panggilan VARCHAR(30) NULL,
    avatar_config TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_asal_daerah (asal_daerah)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelompok (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_kelompok VARCHAR(120) NOT NULL,
    kode_gabung VARCHAR(32) NOT NULL,
    dibuat_oleh BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kelompok_kode_gabung (kode_gabung),
    KEY idx_kelompok_dibuat_oleh (dibuat_oleh),
    CONSTRAINT fk_kelompok_pembuat
        FOREIGN KEY (dibuat_oleh) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kelompok_anggota (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kelompok_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    bergabung_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kelompok_anggota_user (user_id),
    KEY idx_kelompok_anggota_kelompok (kelompok_id),
    CONSTRAINT fk_anggota_kelompok
        FOREIGN KEY (kelompok_id) REFERENCES kelompok (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_anggota_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS soal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pembuat_id BIGINT UNSIGNED NOT NULL,
    pertanyaan TEXT NOT NULL,
    opsi_a VARCHAR(500) NOT NULL,
    opsi_b VARCHAR(500) NOT NULL,
    opsi_c VARCHAR(500) NOT NULL,
    opsi_d VARCHAR(500) NOT NULL,
    kunci_jawaban ENUM('A', 'B', 'C', 'D') NOT NULL,
    tipe_soal ENUM('pilihan_ganda', 'esai') NOT NULL DEFAULT 'pilihan_ganda',
    jawaban_esai VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_soal_pembuat_aktif (pembuat_id, aktif),
    CONSTRAINT fk_soal_pembuat
        FOREIGN KEY (pembuat_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesi_kuis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kelompok_id BIGINT UNSIGNED NULL,
    dimulai_oleh BIGINT UNSIGNED NOT NULL,
    status ENUM('berlangsung', 'selesai') NOT NULL DEFAULT 'berlangsung',
    dimulai_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    selesai_pada TIMESTAMP NULL,
    durasi_detik INT UNSIGNED NOT NULL DEFAULT 600,
    batas_waktu DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sesi_kelompok_status (kelompok_id, status),
    KEY idx_sesi_dimulai_oleh (dimulai_oleh),
    CONSTRAINT fk_sesi_kelompok
        FOREIGN KEY (kelompok_id) REFERENCES kelompok (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sesi_pemain
        FOREIGN KEY (dimulai_oleh) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posisi_pemain (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesi_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    pos_x INT NOT NULL DEFAULT 200,
    pos_y INT NOT NULL DEFAULT 200,
    arah VARCHAR(10) NOT NULL DEFAULT 'down',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_posisi_pemain_sesi_user (sesi_id, user_id),
    KEY idx_posisi_pemain_user (user_id),
    KEY idx_posisi_pemain_updated_at (updated_at),
    CONSTRAINT fk_posisi_pemain_sesi
        FOREIGN KEY (sesi_id) REFERENCES sesi_kuis (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_posisi_pemain_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesi_kuis_peserta (
    sesi_kuis_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('berlangsung', 'selesai') NOT NULL DEFAULT 'berlangsung',
    bergabung_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    selesai_pada TIMESTAMP NULL,
    PRIMARY KEY (sesi_kuis_id, user_id),
    KEY idx_peserta_user_status (user_id, status),
    CONSTRAINT fk_peserta_sesi
        FOREIGN KEY (sesi_kuis_id) REFERENCES sesi_kuis (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_peserta_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesi_kuis_soal (
    sesi_kuis_id BIGINT UNSIGNED NOT NULL,
    soal_id BIGINT UNSIGNED NOT NULL,
    urutan INT UNSIGNED NOT NULL,
    pertanyaan_snapshot TEXT NOT NULL,
    opsi_a_snapshot VARCHAR(500) NOT NULL,
    opsi_b_snapshot VARCHAR(500) NOT NULL,
    opsi_c_snapshot VARCHAR(500) NOT NULL,
    opsi_d_snapshot VARCHAR(500) NOT NULL,
    kunci_snapshot ENUM('A', 'B', 'C', 'D') NOT NULL,
    tipe_soal_snapshot ENUM('pilihan_ganda', 'esai') NOT NULL DEFAULT 'pilihan_ganda',
    jawaban_esai_snapshot VARCHAR(255) NULL,
    PRIMARY KEY (sesi_kuis_id, soal_id),
    UNIQUE KEY uq_sesi_urutan_soal (sesi_kuis_id, urutan),
    CONSTRAINT fk_sesi_soal_sesi
        FOREIGN KEY (sesi_kuis_id) REFERENCES sesi_kuis (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sesi_soal_bank
        FOREIGN KEY (soal_id) REFERENCES soal (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jawaban_user (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sesi_kuis_id BIGINT UNSIGNED NOT NULL,
    soal_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    jawaban_dipilih ENUM('A', 'B', 'C', 'D') NOT NULL,
    jawaban_teks VARCHAR(255) NULL,
    benar TINYINT(1) NOT NULL,
    skor INT UNSIGNED NOT NULL DEFAULT 0,
    dijawab_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jawaban_user_soal (sesi_kuis_id, user_id, soal_id),
    KEY idx_jawaban_user (user_id),
    KEY idx_jawaban_sesi (sesi_kuis_id),
    CONSTRAINT fk_jawaban_sesi_soal
        FOREIGN KEY (sesi_kuis_id, soal_id)
        REFERENCES sesi_kuis_soal (sesi_kuis_id, soal_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_jawaban_pemain
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timer_soal (
    sesi_kuis_id BIGINT UNSIGNED NOT NULL,
    soal_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    dibuka_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_kuis_id, soal_id, user_id),
    CONSTRAINT fk_timer_soal_sesi
        FOREIGN KEY (sesi_kuis_id, soal_id)
        REFERENCES sesi_kuis_soal (sesi_kuis_id, soal_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_timer_soal_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_aktivitas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    aktivitas VARCHAR(100) NOT NULL,
    detail TEXT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user_waktu (user_id, dibuat_pada),
    KEY idx_log_aktivitas_waktu (aktivitas, dibuat_pada),
    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun layanan tanpa akses login, hanya untuk kepemilikan bank soal.
INSERT IGNORE INTO users
    (nama_lengkap, email, password_hash, role, asal_daerah)
VALUES
    ('Pemilik Bank Soal', 'quiz-seed@invalid.local', '!disabled!',
     'pembuat_soal', 'Indonesia');

-- Bank Soal aktif untuk instalasi baru: 12 soal awal + 38 Revisi D + 5 soal Agama (55).
SET @pembuat_id = COALESCE(
    (SELECT id
     FROM users
     WHERE role IN ('pembuat_soal', 'admin')
     ORDER BY (role = 'pembuat_soal') DESC, id ASC
     LIMIT 1),
    1
);

INSERT INTO soal (
    pembuat_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d,
    kunci_jawaban, aktif, tipe_soal, jawaban_esai
) VALUES
    (@pembuat_id, '[Programming] Dalam arsitektur Client-Server pada website, kode PHP dijalankan di sisi mana?', 'Browser pengguna (Client)', 'Web server (Server)', 'Kartu grafis komputer (GPU)', 'Modem jaringan', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Perintah SQL manakah yang digunakan untuk mengambil atau menampilkan data dari tabel database?', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Apa kepanjangan dari HTTP yang biasa muncul di awal alamat website?', 'HyperText Transfer Protocol', 'High Tech Transmission Program', 'Hyperlink Text Tracking Process', 'Home Tool Transfer Protocol', 'A', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Matematika] Berapakah hasil yang benar dari operasi hitung 6 + 4 x 2 - 2?', '18', '12', '16', '10', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Logika] Kamu sedang ikut lomba lari dan berhasil menyalip pelari yang berada di posisi ke-2. Sekarang kamu berada di posisi ke berapa hayo?', 'Posisi ke-1', 'Posisi ke-2', 'Posisi ke-3', 'Posisi terakhir', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Simbol karakter apakah yang wajib ditulis di awal setiap nama variabel pada bahasa PHP?', '', '', '', '', 'A', 1, 'esai', '$, dollar, tanda dollar'),
    (@pembuat_id, '[Programming] Perintah SQL apakah yang digunakan untuk mengambil atau menampilkan data dari tabel database?', '', '', '', '', 'A', 1, 'esai', 'SELECT, select'),
    (@pembuat_id, '[Matematika] Berapakah hasil dari akar kuadrat 144? (tulis angkanya saja)', '', '', '', '', 'A', 1, 'esai', '12, dua belas'),
    (@pembuat_id, '[Matematika] Sebuah segitiga memiliki alas 10 cm dan tinggi 6 cm. Berapakah luas segitiga tersebut?', '', '', '', '', 'A', 1, 'esai', '30, 30 cm, 30 cm2'),
    (@pembuat_id, '[Bahasa Inggris] Apa bentuk kata kerja lampau (Verb 2) dari kata "go"?', '', '', '', '', 'A', 1, 'esai', 'went, Went'),
    (@pembuat_id, '[Sains] Gas apakah yang paling dibutuhkan manusia saat bernapas untuk bertahan hidup?', '', '', '', '', 'A', 1, 'esai', 'Oksigen, oksigen, O2, o2'),
    (@pembuat_id, '[Indonesia] Siapakah nama presiden pertama Republik Indonesia?', '', '', '', '', 'A', 1, 'esai', 'Soekarno, Sukarno, Ir. Soekarno, Ir Soekarno'),
    (@pembuat_id, '[Programming] Bahasa apa yang digunakan untuk mengatur tampilan, warna, dan tata letak (layout) pada halaman website?', 'SQL', 'CSS', 'Python', 'C++', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Manakah perintah SQL yang digunakan untuk menambahkan baris data baru ke dalam sebuah tabel?', 'INSERT INTO', 'SELECT FROM', 'DROP TABLE', 'ALTER TABLE', 'A', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Teknologi] Port default yang biasa digunakan oleh layanan database MySQL pada server lokal seperti XAMPP adalah?', '80', '443', '3306', '8080', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Dalam bahasa JavaScript, fungsi apa yang sering dipakai untuk mengirim permintaan data ke server tanpa me-reload halaman?', 'alert()', 'fetch()', 'print()', 'prompt()', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Teknologi] Komponen komputer yang berfungsi menyimpan data secara sementara saat program sedang berjalan disebut?', 'RAM', 'Harddisk', 'Monitor', 'Power Supply', 'A', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Pada protokol HTTP, kode status 404 memiliki arti bahwa?', 'Permintaan berhasil diproses', 'Server mengalami kelebihan beban', 'Halaman atau file tidak ditemukan', 'Akses ditolak tanpa login', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Matematika] Berapakah hasil dari 15% dikalikan dengan 200?', '15', '20', '30', '45', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Logika] Jika 5 mesin membutuhkan waktu 5 menit untuk membuat 5 benda, berapa menit waktu yang dibutuhkan 100 mesin untuk membuat 100 benda?', '1 menit', '5 menit', '20 menit', '100 menit', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Matematika] Berapakah jumlah seluruh sudut dalam pada sebuah bangun datar segitiga?', '90 derajat', '180 derajat', '270 derajat', '360 derajat', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Logika] Berapakah angka berikutnya dalam pola deret berikut: 2, 4, 8, 16, ...?', '24', '30', '32', '64', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Matematika] Sebuah persegi memiliki panjang sisi 9 cm. Berapakah keliling persegi tersebut?', '18 cm', '27 cm', '36 cm', '81 cm', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Bahasa Inggris] Kalimat manakah yang memiliki struktur grammar paling tepat?', 'She don''t like coffee.', 'She doesn''t likes coffee.', 'She doesn''t like coffee.', 'She not like coffee.', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Bahasa Inggris] Apa arti ungkapan "Break a leg" yang sering diucapkan sebelum seseorang tampil di panggung?', 'Hati-hati agar tidak jatuh', 'Semoga berhasil / beruntung', 'Segera hentikan pertunjukan', 'Jangan lupa beristirahat', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Bahasa Inggris] Bentuk jamak (plural) yang benar dari kata "Child" adalah?', 'Childs', 'Childrens', 'Children', 'Childes', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Bahasa Inggris] Sinonim (persamaan kata) yang paling tepat untuk kata "Enormous" adalah?', 'Tiny', 'Huge', 'Slow', 'Weak', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sains] Planet manakah di Tata Surya kita yang memiliki suhu permukaan paling panas?', 'Merkurius', 'Venus', 'Mars', 'Jupiter', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sains] Pada tabel periodik kimia, simbol unsur "Au" mewakili logam apa?', 'Perak', 'Aluminium', 'Emas', 'Tembaga', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sains] Bagian sel tumbuhan yang berfungsi utama untuk melakukan proses fotosintesis adalah?', 'Kloroplas', 'Ribosom', 'Vakuola', 'Membran inti', 'A', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sains] Berapakah titik didih air murni pada tekanan udara normal 1 atmosfer?', '50 derajat Celcius', '80 derajat Celcius', '100 derajat Celcius', '120 derajat Celcius', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Geografi] Negara manakah yang memiliki garis pantai terpanjang di dunia?', 'Indonesia', 'Rusia', 'Kanada', 'Australia', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sejarah] Siapakah ilmuwan yang terkenal merumuskan hukum gravitasi universal dan tiga hukum gerak?', 'Albert Einstein', 'Nikola Tesla', 'Isaac Newton', 'Galileo Galilei', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Indonesia] Primata mungil bermata besar bernama Tarsius merupakan satwa endemik yang habitat aslinya terkenal berasal dari pulau mana?', 'Sumatra', 'Sulawesi', 'Kalimantan', 'Jawa', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Geografi] Samudra terbesar dan terluas di permukaan Bumi adalah?', 'Samudra Atlantik', 'Samudra Hindia', 'Samudra Pasifik', 'Samudra Arktik', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Indonesia] Sungai terpanjang di Indonesia yang terletak di Pulau Kalimantan adalah?', 'Sungai Musi', 'Sungai Bengawan Solo', 'Sungai Kapuas', 'Sungai Mahakam', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Sejarah] Pada tanggal berapakah Hari Sumpah Pemuda diperingati di Indonesia setiap tahunnya?', '17 Agustus', '28 Oktober', '10 November', '20 Mei', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Programming] Apa singkatan 3 huruf dari bahasa standar basis data yang dipakai untuk mengelola tabel di MySQL?', '', '', '', '', 'A', 1, 'esai', 'SQL, sql'),
    (@pembuat_id, '[Teknologi] Apa singkatan 3 huruf dari otak utama komputer yang bertugas memproses instruksi program?', '', '', '', '', 'A', 1, 'esai', 'CPU, cpu'),
    (@pembuat_id, '[Programming] Perintah SQL satu kata apakah yang digunakan untuk menghapus baris data tertentu dari sebuah tabel?', '', '', '', '', 'A', 1, 'esai', 'DELETE, delete'),
    (@pembuat_id, '[Teknologi] Apa singkatan 3 huruf dari alamat unik yang digunakan untuk mengakses sebuah halaman di internet?', '', '', '', '', 'A', 1, 'esai', 'URL, url'),
    (@pembuat_id, '[Matematika] Berapakah hasil dari 2 pangkat 5? (tulis angkanya saja)', '', '', '', '', 'A', 1, 'esai', '32, tiga puluh dua'),
    (@pembuat_id, '[Matematika] Berapakah bilangan prima terkecil yang juga merupakan bilangan genap? (tulis angkanya saja)', '', '', '', '', 'A', 1, 'esai', '2, dua'),
    (@pembuat_id, '[Logika] Dalam satu tahun kalender Masehi, ada berapa bulan yang memiliki tepat 31 hari? (tulis angkanya saja)', '', '', '', '', 'A', 1, 'esai', '7, tujuh'),
    (@pembuat_id, '[Bahasa Inggris] Apa bahasa Inggris dari kata "Pengetahuan"? (tulis satu kata)', '', '', '', '', 'A', 1, 'esai', 'knowledge, Knowledge'),
    (@pembuat_id, '[Bahasa Inggris] Apa bentuk kata kerja lampau (Verb 2) dari kata "eat"?', '', '', '', '', 'A', 1, 'esai', 'ate, Ate'),
    (@pembuat_id, '[Sains] Planet terbesar di Tata Surya kita adalah?', '', '', '', '', 'A', 1, 'esai', 'Jupiter, jupiter, Yupiter, yupiter'),
    (@pembuat_id, '[Sains] Apa rumus kimia dari air murni?', '', '', '', '', 'A', 1, 'esai', 'H2O, h2o'),
    (@pembuat_id, '[Geografi] Benua terkecil di dunia sekaligus sebuah negara adalah benua apa?', '', '', '', '', 'A', 1, 'esai', 'Australia, australia'),
    (@pembuat_id, '[Indonesia] Apa nama danau vulkanik terbesar di Indonesia yang terletak di Sumatra Utara?', '', '', '', '', 'A', 1, 'esai', 'Toba, Danau Toba, toba, danau toba'),
    (@pembuat_id, '[Agama] Berapakah jumlah seluruh surah yang terdapat di dalam kitab suci Al-Qur''an?', '110 surah', '112 surah', '114 surah', '120 surah', 'C', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Agama] Surah apakah yang pertama kali diturunkan kepada Nabi Muhammad SAW di Gua Hira?', 'Surah Al-Fatihah', 'Surah Al-Alaq', 'Surah Al-Baqarah', 'Surah An-Nas', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Agama] Dalam hadits shahih riwayat Bukhari dan Muslim yang sangat masyhur, diterangkan bahwa sah atau tidaknya setiap amal perbuatan sangat bergantung pada apa?', 'Kekayaan harta', 'Niatnya', 'Banyaknya teman', 'Jabatan seseorang', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Agama] Sebelum kiblat umat Islam dialihkan ke Ka''bah di Masjidil Haram (Makkah), ke arah manakah kiblat pertama umat Islam saat melaksanakan shalat?', 'Masjid Nabawi di Madinah', 'Masjidil Aqsa (Baitul Maqdis)', 'Masjid Quba', 'Gua Tsur', 'B', 1, 'pilihan_ganda', NULL),
    (@pembuat_id, '[Agama] Kota apakah yang menjadi tujuan hijrah Nabi Muhammad SAW dari Makkah yang menandai awal penanggalan kalender Hijriah?', '', '', '', '', 'A', 1, 'esai', 'Madinah, madinah, Yatsrib, yatsrib, Kota Madinah, Madinah Al-Munawwarah');

-- Migrasi opsional dari tabel akun lama yang sudah ada sebelumnya:
-- Jalankan hanya jika tabel akun masih ada dan masih berisi data proyek lama.
/*
INSERT IGNORE INTO users (
    nama_lengkap, email, password_hash, role, asal_daerah,
    jenis_kelamin, negara, provinsi, kabupaten_kota,
    kecamatan, kelurahan_desa, created_at
)
SELECT
    nama_lengkap,
    email,
    password_hash,
    CASE WHEN role = 'admin' THEN 'admin' ELSE 'user' END,
    COALESCE(NULLIF(kabupaten_kota, ''), NULLIF(provinsi, ''), 'Tidak diketahui'),
    jenis_kelamin,
    COALESCE(negara, 'Indonesia'),
    provinsi,
    kabupaten_kota,
    kecamatan,
    kelurahan_desa,
    created_at
FROM akun
WHERE email <> 'admin@movie.local';
*/
