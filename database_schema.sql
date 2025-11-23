-- ============================================
-- Database Schema: BD_LAB_TEKNODATA
-- Laboratorium Teknologi Data JTI Polinema
-- ============================================

-- Create ENUM types
CREATE TYPE jenis_berita AS ENUM ('berita','agenda','pengumuman');
CREATE TYPE status_publish AS ENUM ('draft','diajukan','disetujui','ditolak','arsip');
CREATE TYPE peran_user AS ENUM ('admin','operator');

-- ============================================
-- TABLE: pengguna (Admin & Operator CMS)
-- ============================================
CREATE TABLE pengguna (
    id_pengguna BIGSERIAL PRIMARY KEY,
    nama_lengkap VARCHAR(120) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    peran peran_user NOT NULL DEFAULT 'operator',
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data: Admin user (password: admin123)
INSERT INTO pengguna (nama_lengkap, email, password_hash, peran) VALUES
('Administrator Lab', 'admin@polinema.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Operator Lab', 'operator@polinema.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operator');

-- ============================================
-- TABLE: media (Gambar/File)
-- ============================================
CREATE TABLE media (
    id_media BIGSERIAL PRIMARY KEY,
    lokasi_file TEXT NOT NULL,
    tipe_file VARCHAR(100) NOT NULL,
    keterangan_alt VARCHAR(200),
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna) ON DELETE SET NULL,
    dibuat_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO media (lokasi_file, tipe_file, keterangan_alt, dibuat_oleh) VALUES
('news-default.jpg', 'image/jpeg', 'Default news image', 1),
('lab-photo-1.jpg', 'image/jpeg', 'Laboratorium Teknologi Data', 1),
('facility-server.jpg', 'image/jpeg', 'Server dan Infrastruktur', 1);

-- ============================================
-- TABLE: berita (Berita/Agenda/Pengumuman)
-- ============================================
CREATE TABLE berita (
    id_berita BIGSERIAL PRIMARY KEY,
    jenis jenis_berita NOT NULL,
    judul VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE NOT NULL,
    ringkasan TEXT,
    isi_html TEXT,
    id_cover BIGINT REFERENCES media(id_media) ON DELETE SET NULL,
    tanggal_mulai TIMESTAMPTZ,
    tanggal_selesai TIMESTAMPTZ,
    status status_publish DEFAULT 'draft',
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    disetujui_oleh BIGINT REFERENCES pengguna(id_pengguna),
    disetujui_pada TIMESTAMPTZ,
    catatan_review TEXT,
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO berita (jenis, judul, slug, ringkasan, isi_html, id_cover, tanggal_mulai, status, dibuat_oleh, disetujui_oleh, disetujui_pada) VALUES
('berita', 'Kolaborasi Lab Data dengan PT Tech Innovator', 'kolaborasi-lab-data-dengan-pt-tech-innovator', 
 'Penandatanganan MoU untuk pengembangan riset dan pelatihan mahasiswa di bidang data science.', 
 '<p>Laboratorium Teknologi Data JTI Polinema telah menandatangani Memorandum of Understanding (MoU) dengan PT Tech Innovator untuk kolaborasi dalam bidang data science dan artificial intelligence.</p><p>Kerjasama ini mencakup program magang mahasiswa, penelitian bersama, dan workshop teknologi terkini.</p>', 
 1, NOW(), 'disetujui', 1, 1, NOW()),

('agenda', 'Workshop Python untuk Data Analysis', 'workshop-python-untuk-data-analysis', 
 'Pelatihan intensif menggunakan Python, Pandas, dan Matplotlib untuk analisis data praktis.', 
 '<p>Workshop ini akan membahas penggunaan Python untuk analisis data, termasuk library Pandas, Matplotlib, dan Seaborn.</p><p>Peserta akan belajar dari data preprocessing hingga visualisasi data yang efektif.</p>', 
 1, NOW() + INTERVAL '15 days', 'disetujui', 1, 1, NOW()),

('pengumuman', 'Hasil Seleksi Penelitian Mahasiswa 2024', 'hasil-seleksi-penelitian-mahasiswa-2024', 
 'Pengumuman hasil seleksi proposal penelitian mahasiswa yang akan didanai oleh laboratorium.', 
 '<p>Berikut adalah daftar mahasiswa yang lolos seleksi proposal penelitian semester ganjil 2024/2025.</p><ul><li>Proposal 1: Sistem Prediksi...</li><li>Proposal 2: Analisis Sentimen...</li></ul>', 
 1, NOW(), 'disetujui', 1, 1, NOW());

-- ============================================
-- TABLE: galeri_album
-- ============================================
CREATE TABLE galeri_album (
    id_album BIGSERIAL PRIMARY KEY,
    judul VARCHAR(160) NOT NULL,
    slug VARCHAR(180) UNIQUE NOT NULL,
    deskripsi TEXT,
    id_cover BIGINT REFERENCES media(id_media) ON DELETE SET NULL,
    status status_publish DEFAULT 'disetujui',
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO galeri_album (judul, slug, deskripsi, id_cover, status, dibuat_oleh) VALUES
('Workshop Machine Learning 2024', 'workshop-machine-learning-2024', 
 'Dokumentasi kegiatan workshop machine learning untuk mahasiswa semester 5', 
 2, 'disetujui', 1),
('Kunjungan Industri ke Startup AI', 'kunjungan-industri-ke-startup-ai', 
 'Foto-foto kunjungan mahasiswa ke perusahaan startup AI di Malang', 
 2, 'disetujui', 1);

-- ============================================
-- TABLE: galeri_item
-- ============================================
CREATE TABLE galeri_item (
    id_item BIGSERIAL PRIMARY KEY,
    id_album BIGINT REFERENCES galeri_album(id_album) ON DELETE CASCADE,
    id_media BIGINT REFERENCES media(id_media) ON DELETE CASCADE,
    caption VARCHAR(200),
    urutan INT DEFAULT 0,
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    dibuat_pada TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- TABLE: aktivitas (Kegiatan Lab)
-- ============================================
CREATE TABLE aktivitas (
    id_aktivitas BIGSERIAL PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE NOT NULL,
    deskripsi TEXT,
    tanggal_mulai TIMESTAMPTZ,
    tanggal_selesai TIMESTAMPTZ,
    lokasi VARCHAR(200),
    id_cover BIGINT REFERENCES media(id_media),
    status status_publish DEFAULT 'draft',
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    disetujui_oleh BIGINT REFERENCES pengguna(id_pengguna),
    disetujui_pada TIMESTAMPTZ,
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================
-- TABLE: anggota_lab
-- ============================================
CREATE TABLE anggota_lab (
    id_anggota BIGSERIAL PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    slug VARCHAR(150) UNIQUE NOT NULL,
    email VARCHAR(150),
    peran_lab VARCHAR(100),
    bio_html TEXT,
    id_foto BIGINT REFERENCES media(id_media) ON DELETE SET NULL,
    aktif BOOLEAN DEFAULT TRUE,
    urutan INT DEFAULT 0,
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO anggota_lab (nama, slug, email, peran_lab, bio_html, urutan, aktif) VALUES
('Dr. Ahmad Saufi, M.Kom', 'dr-ahmad-saufi-m-kom', 'ahmad.saufi@polinema.ac.id', 'Kepala Laboratorium', 
 '<p>Kepala Laboratorium Teknologi Data dengan fokus riset pada Machine Learning dan Big Data Analytics.</p>', 1, TRUE),
('Rina Fitriana, S.Kom., M.T', 'rina-fitriana-s-kom-m-t', 'rina.fitriana@polinema.ac.id', 'Dosen Peneliti', 
 '<p>Peneliti di bidang Data Mining dan Visualization.</p>', 2, TRUE),
('Budi Harijanto, S.T., M.Kom', 'budi-harijanto-s-t-m-kom', 'budi.harijanto@polinema.ac.id', 'Dosen Peneliti', 
 '<p>Spesialisasi dalam Database Systems dan Cloud Computing.</p>', 3, TRUE);

-- ============================================
-- TABLE: fasilitas
-- ============================================
CREATE TABLE fasilitas (
    id_fasilitas BIGSERIAL PRIMARY KEY,
    nama VARCHAR(160) NOT NULL,
    slug VARCHAR(180) UNIQUE NOT NULL,
    kategori VARCHAR(80),
    deskripsi TEXT,
    id_foto BIGINT REFERENCES media(id_media),
    status status_publish DEFAULT 'disetujui',
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO fasilitas (nama, slug, kategori, deskripsi, id_foto, status, dibuat_oleh) VALUES
('Server High Performance Computing', 'server-high-performance-computing', 'Hardware', 
 'Server dengan spesifikasi tinggi untuk komputasi data besar dan machine learning. Dilengkapi dengan GPU NVIDIA untuk deep learning.', 
 3, 'disetujui', 1),
('Workstation Analisis Data', 'workstation-analisis-data', 'Hardware', 
 'Workstation khusus untuk analisis data dengan software lengkap seperti Python, R, dan Tableau.', 
 3, 'disetujui', 1),
('Lisensi Software Enterprise', 'lisensi-software-enterprise', 'Software', 
 'Lisensi berbagai software enterprise untuk keperluan riset dan praktikum mahasiswa.', 
 NULL, 'disetujui', 1);

-- ============================================
-- TABLE: publikasi
-- ============================================
CREATE TABLE publikasi (
    id_publikasi BIGSERIAL PRIMARY KEY,
    judul VARCHAR(300) NOT NULL,
    slug VARCHAR(320) UNIQUE NOT NULL,
    abstrak TEXT,
    jenis VARCHAR(60),
    tempat VARCHAR(200),
    tahun INT,
    doi VARCHAR(120),
    id_cover BIGINT REFERENCES media(id_media),
    status status_publish DEFAULT 'disetujui',
    dibuat_oleh BIGINT REFERENCES pengguna(id_pengguna),
    dibuat_pada TIMESTAMPTZ DEFAULT NOW(),
    diperbarui_pada TIMESTAMPTZ DEFAULT NOW()
);

-- Sample data
INSERT INTO publikasi (judul, slug, abstrak, jenis, tempat, tahun, doi, status, dibuat_oleh) VALUES
('Sistem Prediksi Penjualan dengan Metode Monte Carlo', 'sistem-prediksi-penjualan-dengan-metode-monte-carlo', 
 'Penelitian ini mengembangkan sistem prediksi penjualan menggunakan metode Monte Carlo dengan studi kasus pada industri frozen food.', 
 'Jurnal Internasional', 'International Journal of Data Science', 2023, '10.xxxx/ijds.2023.001', 'disetujui', 1),
('Implementasi Machine Learning untuk Deteksi Anomali Jaringan', 'implementasi-machine-learning-untuk-deteksi-anomali-jaringan', 
 'Studi implementasi algoritma K-Means, DBSCAN, dan Mean Shift untuk mendeteksi anomali pada traffic jaringan komputer.', 
 'Prosiding Konferensi', 'International Conference on Computer Science 2023', 2023, '10.xxxx/iccs.2023.045', 'disetujui', 1);

-- ============================================
-- TABLE: publikasi_penulis (Many-to-Many)
-- ============================================
CREATE TABLE publikasi_penulis (
    id_publikasi BIGINT REFERENCES publikasi(id_publikasi) ON DELETE CASCADE,
    id_anggota BIGINT REFERENCES anggota_lab(id_anggota) ON DELETE CASCADE,
    urutan INT DEFAULT 1,
    PRIMARY KEY (id_publikasi, id_anggota)
);

-- Sample data
INSERT INTO publikasi_penulis (id_publikasi, id_anggota, urutan) VALUES
(1, 1, 1),
(1, 2, 2),
(2, 1, 1),
(2, 3, 2);

-- ============================================
-- TABLE: pesan_kontak
-- ============================================
CREATE TABLE pesan_kontak (
    id_pesan BIGSERIAL PRIMARY KEY,
    nama_pengirim VARCHAR(120) NOT NULL,
    email_pengirim VARCHAR(150) NOT NULL,
    subjek VARCHAR(200),
    isi TEXT NOT NULL,
    tujuan VARCHAR(100),
    status VARCHAR(30) DEFAULT 'baru',
    diterima_pada TIMESTAMPTZ DEFAULT NOW(),
    ditangani_oleh BIGINT REFERENCES pengguna(id_pengguna),
    catatan_balasan TEXT
);

-- ============================================
-- INDEXES for Performance
-- ============================================
CREATE INDEX idx_berita_status ON berita(status);
CREATE INDEX idx_berita_jenis ON berita(jenis);
CREATE INDEX idx_berita_dibuat_pada ON berita(dibuat_pada);
CREATE INDEX idx_publikasi_tahun ON publikasi(tahun);
CREATE INDEX idx_publikasi_status ON publikasi(status);
CREATE INDEX idx_anggota_aktif ON anggota_lab(aktif);
CREATE INDEX idx_fasilitas_kategori ON fasilitas(kategori);
CREATE INDEX idx_pesan_status ON pesan_kontak(status);

-- ============================================
-- Functions & Triggers
-- ============================================

-- Function to update diperbarui_pada timestamp
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.diperbarui_pada = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers for auto-update timestamp
CREATE TRIGGER update_pengguna_timestamp BEFORE UPDATE ON pengguna
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER update_berita_timestamp BEFORE UPDATE ON berita
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER update_publikasi_timestamp BEFORE UPDATE ON publikasi
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER update_anggota_timestamp BEFORE UPDATE ON anggota_lab
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

CREATE TRIGGER update_fasilitas_timestamp BEFORE UPDATE ON fasilitas
    FOR EACH ROW EXECUTE FUNCTION update_timestamp();

-- ============================================
-- END OF SCHEMA
-- ============================================
