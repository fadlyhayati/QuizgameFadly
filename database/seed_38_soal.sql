USE client_serveradin;

SET @pembuat_id = COALESCE(
    (SELECT id
     FROM users
     WHERE role IN ('pembuat_soal', 'admin')
     ORDER BY (role = 'pembuat_soal') DESC, id ASC
     LIMIT 1),
    1
);

START TRANSACTION;

INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Bahasa apa yang digunakan untuk mengatur tampilan, warna, dan tata letak (layout) pada halaman website?', 'pilihan_ganda', 'SQL', 'CSS', 'Python', 'C++', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Bahasa apa yang digunakan untuk mengatur tampilan, warna, dan tata letak (layout) pada halaman website?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Manakah perintah SQL yang digunakan untuk menambahkan baris data baru ke dalam sebuah tabel?', 'pilihan_ganda', 'INSERT INTO', 'SELECT FROM', 'DROP TABLE', 'ALTER TABLE', 'A', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Manakah perintah SQL yang digunakan untuk menambahkan baris data baru ke dalam sebuah tabel?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Teknologi] Port default yang biasa digunakan oleh layanan database MySQL pada server lokal seperti XAMPP adalah?', 'pilihan_ganda', '80', '443', '3306', '8080', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Teknologi] Port default yang biasa digunakan oleh layanan database MySQL pada server lokal seperti XAMPP adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Dalam bahasa JavaScript, fungsi apa yang sering dipakai untuk mengirim permintaan data ke server tanpa me-reload halaman?', 'pilihan_ganda', 'alert()', 'fetch()', 'print()', 'prompt()', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Dalam bahasa JavaScript, fungsi apa yang sering dipakai untuk mengirim permintaan data ke server tanpa me-reload halaman?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Teknologi] Komponen komputer yang berfungsi menyimpan data secara sementara saat program sedang berjalan disebut?', 'pilihan_ganda', 'RAM', 'Harddisk', 'Monitor', 'Power Supply', 'A', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Teknologi] Komponen komputer yang berfungsi menyimpan data secara sementara saat program sedang berjalan disebut?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Pada protokol HTTP, kode status 404 memiliki arti bahwa?', 'pilihan_ganda', 'Permintaan berhasil diproses', 'Server mengalami kelebihan beban', 'Halaman atau file tidak ditemukan', 'Akses ditolak tanpa login', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Pada protokol HTTP, kode status 404 memiliki arti bahwa?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Matematika] Berapakah hasil dari 15% dikalikan dengan 200?', 'pilihan_ganda', '15', '20', '30', '45', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Matematika] Berapakah hasil dari 15% dikalikan dengan 200?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Logika] Jika 5 mesin membutuhkan waktu 5 menit untuk membuat 5 benda, berapa menit waktu yang dibutuhkan 100 mesin untuk membuat 100 benda?', 'pilihan_ganda', '1 menit', '5 menit', '20 menit', '100 menit', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Logika] Jika 5 mesin membutuhkan waktu 5 menit untuk membuat 5 benda, berapa menit waktu yang dibutuhkan 100 mesin untuk membuat 100 benda?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Matematika] Berapakah jumlah seluruh sudut dalam pada sebuah bangun datar segitiga?', 'pilihan_ganda', '90 derajat', '180 derajat', '270 derajat', '360 derajat', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Matematika] Berapakah jumlah seluruh sudut dalam pada sebuah bangun datar segitiga?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Logika] Berapakah angka berikutnya dalam pola deret berikut: 2, 4, 8, 16, ...?', 'pilihan_ganda', '24', '30', '32', '64', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Logika] Berapakah angka berikutnya dalam pola deret berikut: 2, 4, 8, 16, ...?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Matematika] Sebuah persegi memiliki panjang sisi 9 cm. Berapakah keliling persegi tersebut?', 'pilihan_ganda', '18 cm', '27 cm', '36 cm', '81 cm', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Matematika] Sebuah persegi memiliki panjang sisi 9 cm. Berapakah keliling persegi tersebut?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Kalimat manakah yang memiliki struktur grammar paling tepat?', 'pilihan_ganda', 'She don''t like coffee.', 'She doesn''t likes coffee.', 'She doesn''t like coffee.', 'She not like coffee.', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Kalimat manakah yang memiliki struktur grammar paling tepat?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Apa arti ungkapan "Break a leg" yang sering diucapkan sebelum seseorang tampil di panggung?', 'pilihan_ganda', 'Hati-hati agar tidak jatuh', 'Semoga berhasil / beruntung', 'Segera hentikan pertunjukan', 'Jangan lupa beristirahat', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Apa arti ungkapan "Break a leg" yang sering diucapkan sebelum seseorang tampil di panggung?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Bentuk jamak (plural) yang benar dari kata "Child" adalah?', 'pilihan_ganda', 'Childs', 'Childrens', 'Children', 'Childes', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Bentuk jamak (plural) yang benar dari kata "Child" adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Sinonim (persamaan kata) yang paling tepat untuk kata "Enormous" adalah?', 'pilihan_ganda', 'Tiny', 'Huge', 'Slow', 'Weak', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Sinonim (persamaan kata) yang paling tepat untuk kata "Enormous" adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Planet manakah di Tata Surya kita yang memiliki suhu permukaan paling panas?', 'pilihan_ganda', 'Merkurius', 'Venus', 'Mars', 'Jupiter', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Planet manakah di Tata Surya kita yang memiliki suhu permukaan paling panas?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Pada tabel periodik kimia, simbol unsur "Au" mewakili logam apa?', 'pilihan_ganda', 'Perak', 'Aluminium', 'Emas', 'Tembaga', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Pada tabel periodik kimia, simbol unsur "Au" mewakili logam apa?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Bagian sel tumbuhan yang berfungsi utama untuk melakukan proses fotosintesis adalah?', 'pilihan_ganda', 'Kloroplas', 'Ribosom', 'Vakuola', 'Membran inti', 'A', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Bagian sel tumbuhan yang berfungsi utama untuk melakukan proses fotosintesis adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Berapakah titik didih air murni pada tekanan udara normal 1 atmosfer?', 'pilihan_ganda', '50 derajat Celcius', '80 derajat Celcius', '100 derajat Celcius', '120 derajat Celcius', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Berapakah titik didih air murni pada tekanan udara normal 1 atmosfer?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Geografi] Negara manakah yang memiliki garis pantai terpanjang di dunia?', 'pilihan_ganda', 'Indonesia', 'Rusia', 'Kanada', 'Australia', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Geografi] Negara manakah yang memiliki garis pantai terpanjang di dunia?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sejarah] Siapakah ilmuwan yang terkenal merumuskan hukum gravitasi universal dan tiga hukum gerak?', 'pilihan_ganda', 'Albert Einstein', 'Nikola Tesla', 'Isaac Newton', 'Galileo Galilei', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sejarah] Siapakah ilmuwan yang terkenal merumuskan hukum gravitasi universal dan tiga hukum gerak?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Indonesia] Primata mungil bermata besar bernama Tarsius merupakan satwa endemik yang habitat aslinya terkenal berasal dari pulau mana?', 'pilihan_ganda', 'Sumatra', 'Sulawesi', 'Kalimantan', 'Jawa', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Indonesia] Primata mungil bermata besar bernama Tarsius merupakan satwa endemik yang habitat aslinya terkenal berasal dari pulau mana?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Geografi] Samudra terbesar dan terluas di permukaan Bumi adalah?', 'pilihan_ganda', 'Samudra Atlantik', 'Samudra Hindia', 'Samudra Pasifik', 'Samudra Arktik', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Geografi] Samudra terbesar dan terluas di permukaan Bumi adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Indonesia] Sungai terpanjang di Indonesia yang terletak di Pulau Kalimantan adalah?', 'pilihan_ganda', 'Sungai Musi', 'Sungai Bengawan Solo', 'Sungai Kapuas', 'Sungai Mahakam', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Indonesia] Sungai terpanjang di Indonesia yang terletak di Pulau Kalimantan adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sejarah] Pada tanggal berapakah Hari Sumpah Pemuda diperingati di Indonesia setiap tahunnya?', 'pilihan_ganda', '17 Agustus', '28 Oktober', '10 November', '20 Mei', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sejarah] Pada tanggal berapakah Hari Sumpah Pemuda diperingati di Indonesia setiap tahunnya?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Apa singkatan 3 huruf dari bahasa standar basis data yang dipakai untuk mengelola tabel di MySQL?', 'esai', '', '', '', '', 'A', 'SQL, sql', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Apa singkatan 3 huruf dari bahasa standar basis data yang dipakai untuk mengelola tabel di MySQL?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Teknologi] Apa singkatan 3 huruf dari otak utama komputer yang bertugas memproses instruksi program?', 'esai', '', '', '', '', 'A', 'CPU, cpu', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Teknologi] Apa singkatan 3 huruf dari otak utama komputer yang bertugas memproses instruksi program?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Programming] Perintah SQL satu kata apakah yang digunakan untuk menghapus baris data tertentu dari sebuah tabel?', 'esai', '', '', '', '', 'A', 'DELETE, delete', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Programming] Perintah SQL satu kata apakah yang digunakan untuk menghapus baris data tertentu dari sebuah tabel?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Teknologi] Apa singkatan 3 huruf dari alamat unik yang digunakan untuk mengakses sebuah halaman di internet?', 'esai', '', '', '', '', 'A', 'URL, url', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Teknologi] Apa singkatan 3 huruf dari alamat unik yang digunakan untuk mengakses sebuah halaman di internet?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Matematika] Berapakah hasil dari 2 pangkat 5? (tulis angkanya saja)', 'esai', '', '', '', '', 'A', '32, tiga puluh dua', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Matematika] Berapakah hasil dari 2 pangkat 5? (tulis angkanya saja)');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Matematika] Berapakah bilangan prima terkecil yang juga merupakan bilangan genap? (tulis angkanya saja)', 'esai', '', '', '', '', 'A', '2, dua', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Matematika] Berapakah bilangan prima terkecil yang juga merupakan bilangan genap? (tulis angkanya saja)');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Logika] Dalam satu tahun kalender Masehi, ada berapa bulan yang memiliki tepat 31 hari? (tulis angkanya saja)', 'esai', '', '', '', '', 'A', '7, tujuh', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Logika] Dalam satu tahun kalender Masehi, ada berapa bulan yang memiliki tepat 31 hari? (tulis angkanya saja)');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Apa bahasa Inggris dari kata "Pengetahuan"? (tulis satu kata)', 'esai', '', '', '', '', 'A', 'knowledge, Knowledge', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Apa bahasa Inggris dari kata "Pengetahuan"? (tulis satu kata)');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Bahasa Inggris] Apa bentuk kata kerja lampau (Verb 2) dari kata "eat"?', 'esai', '', '', '', '', 'A', 'ate, Ate', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Bahasa Inggris] Apa bentuk kata kerja lampau (Verb 2) dari kata "eat"?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Planet terbesar di Tata Surya kita adalah?', 'esai', '', '', '', '', 'A', 'Jupiter, jupiter, Yupiter, yupiter', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Planet terbesar di Tata Surya kita adalah?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Sains] Apa rumus kimia dari air murni?', 'esai', '', '', '', '', 'A', 'H2O, h2o', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Sains] Apa rumus kimia dari air murni?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Geografi] Benua terkecil di dunia sekaligus sebuah negara adalah benua apa?', 'esai', '', '', '', '', 'A', 'Australia, australia', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Geografi] Benua terkecil di dunia sekaligus sebuah negara adalah benua apa?');
INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Indonesia] Apa nama danau vulkanik terbesar di Indonesia yang terletak di Sumatra Utara?', 'esai', '', '', '', '', 'A', 'Toba, Danau Toba, toba, danau toba', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Indonesia] Apa nama danau vulkanik terbesar di Indonesia yang terletak di Sumatra Utara?');

COMMIT;

SELECT COUNT(*) AS soal_aktif
FROM soal
WHERE aktif = 1;
