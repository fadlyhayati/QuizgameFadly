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
SELECT @pembuat_id, '[Agama] Berapakah jumlah seluruh surah yang terdapat di dalam kitab suci Al-Qur''an?', 'pilihan_ganda', '110 surah', '112 surah', '114 surah', '120 surah', 'C', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Agama] Berapakah jumlah seluruh surah yang terdapat di dalam kitab suci Al-Qur''an?');

INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Agama] Surah apakah yang pertama kali diturunkan kepada Nabi Muhammad SAW di Gua Hira?', 'pilihan_ganda', 'Surah Al-Fatihah', 'Surah Al-Alaq', 'Surah Al-Baqarah', 'Surah An-Nas', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Agama] Surah apakah yang pertama kali diturunkan kepada Nabi Muhammad SAW di Gua Hira?');

INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Agama] Dalam hadits shahih riwayat Bukhari dan Muslim yang sangat masyhur, diterangkan bahwa sah atau tidaknya setiap amal perbuatan sangat bergantung pada apa?', 'pilihan_ganda', 'Kekayaan harta', 'Niatnya', 'Banyaknya teman', 'Jabatan seseorang', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Agama] Dalam hadits shahih riwayat Bukhari dan Muslim yang sangat masyhur, diterangkan bahwa sah atau tidaknya setiap amal perbuatan sangat bergantung pada apa?');

INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Agama] Sebelum kiblat umat Islam dialihkan ke Ka''bah di Masjidil Haram (Makkah), ke arah manakah kiblat pertama umat Islam saat melaksanakan shalat?', 'pilihan_ganda', 'Masjid Nabawi di Madinah', 'Masjidil Aqsa (Baitul Maqdis)', 'Masjid Quba', 'Gua Tsur', 'B', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Agama] Sebelum kiblat umat Islam dialihkan ke Ka''bah di Masjidil Haram (Makkah), ke arah manakah kiblat pertama umat Islam saat melaksanakan shalat?');

INSERT INTO soal (pembuat_id, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, jawaban_esai, aktif)
SELECT @pembuat_id, '[Agama] Kota apakah yang menjadi tujuan hijrah Nabi Muhammad SAW dari Makkah yang menandai awal penanggalan kalender Hijriah?', 'esai', '', '', '', '', 'A', 'Madinah, madinah, Yatsrib, yatsrib, Kota Madinah, Madinah Al-Munawwarah', 1
WHERE NOT EXISTS (SELECT 1 FROM soal WHERE pertanyaan = '[Agama] Kota apakah yang menjadi tujuan hijrah Nabi Muhammad SAW dari Makkah yang menandai awal penanggalan kalender Hijriah?');

COMMIT;

SELECT COUNT(*) AS soal_aktif
FROM soal
WHERE aktif = 1;
