-- =====================================================
-- DATABASE SCHEMA FOR VILLAGE ADMINISTRATION SYSTEM
-- =====================================================
-- Created: 2025
-- Description: Complete database schema for village administration
-- with sample data for testing

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS desa_admin 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE desa_admin;

-- =====================================================
-- TABLE: villages (Data Desa)
-- =====================================================
CREATE TABLE IF NOT EXISTS villages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_desa VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    kode_pos VARCHAR(10) NOT NULL,
    kepala_desa VARCHAR(100) NOT NULL,
    nip_kepala_desa VARCHAR(20) NOT NULL,
    kecamatan VARCHAR(50) NOT NULL,
    kabupaten VARCHAR(50) NOT NULL,
    provinsi VARCHAR(50) NOT NULL,
    telepon VARCHAR(15),
    email VARCHAR(100),
    website VARCHAR(100),
    logo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: users (Sistem User/Admin)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'operator', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    remember_token VARCHAR(100),
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT,
    updated_by INT,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: penduduk (Data Penduduk/Warga)
-- =====================================================
CREATE TABLE IF NOT EXISTS penduduk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nik VARCHAR(16) UNIQUE NOT NULL,
    kk VARCHAR(16) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    tempat_lahir VARCHAR(50) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    alamat TEXT NOT NULL,
    rt VARCHAR(3) NOT NULL,
    rw VARCHAR(3) NOT NULL,
    agama ENUM('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu') NOT NULL,
    pekerjaan VARCHAR(50) NOT NULL,
    status_kawin ENUM('Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati') NOT NULL,
    pendidikan VARCHAR(30),
    nama_ayah VARCHAR(100),
    nama_ibu VARCHAR(100),
    telepon VARCHAR(15),
    email VARCHAR(100),
    foto_path VARCHAR(255),
    status_penduduk ENUM('Tetap', 'Tidak Tetap', 'Pendatang') DEFAULT 'Tetap',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT,
    updated_by INT,
    
    INDEX idx_nik (nik),
    INDEX idx_kk (kk),
    INDEX idx_nama (nama),
    INDEX idx_rt_rw (rt, rw),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: surat_types (Jenis Surat)
-- =====================================================
CREATE TABLE IF NOT EXISTS surat_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_surat VARCHAR(10) UNIQUE NOT NULL,
    nama_surat VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    template_file VARCHAR(255),
    persyaratan TEXT,
    biaya DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT,
    updated_by INT,
    
    INDEX idx_kode_surat (kode_surat),
    INDEX idx_is_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: surat_requests (Permintaan Surat)
-- =====================================================
CREATE TABLE IF NOT EXISTS surat_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    nomor_surat VARCHAR(50) UNIQUE,
    user_id INT NOT NULL,
    penduduk_nik VARCHAR(16) NOT NULL,
    surat_type_id INT NOT NULL,
    keperluan TEXT NOT NULL,
    keterangan_tambahan TEXT,
    status ENUM('pending', 'diproses', 'selesai', 'ditolak') DEFAULT 'pending',
    alasan_penolakan TEXT,
    file_path VARCHAR(255),
    tanggal_selesai DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_by INT,
    updated_by INT,
    processed_by INT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (penduduk_nik) REFERENCES penduduk(nik) ON DELETE CASCADE,
    FOREIGN KEY (surat_type_id) REFERENCES surat_types(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_uuid (uuid),
    INDEX idx_nomor_surat (nomor_surat),
    INDEX idx_user_id (user_id),
    INDEX idx_penduduk_nik (penduduk_nik),
    INDEX idx_surat_type_id (surat_type_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: surat_templates (Template Surat)
-- =====================================================
CREATE TABLE IF NOT EXISTS surat_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    surat_type_id INT NOT NULL,
    nama_template VARCHAR(100) NOT NULL,
    template_content TEXT NOT NULL,
    variables JSON,
    kop_surat TEXT,
    footer_surat TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    created_by INT,
    updated_by INT,
    
    FOREIGN KEY (surat_type_id) REFERENCES surat_types(id) ON DELETE CASCADE,
    
    INDEX idx_surat_type_id (surat_type_id),
    INDEX idx_is_default (is_default),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: settings (Pengaturan Sistem)
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (`key`),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: surat_logs (Log Aktivitas Surat)
-- =====================================================
CREATE TABLE IF NOT EXISTS surat_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    surat_request_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    old_status VARCHAR(20),
    new_status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (surat_request_id) REFERENCES surat_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_surat_request_id (surat_request_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert sample village data
INSERT INTO villages (nama_desa, alamat, kode_pos, kepala_desa, nip_kepala_desa, kecamatan, kabupaten, provinsi, telepon, email) VALUES
('Desa Maju Jaya', 'Jl. Raya Desa No. 123, RT 01/RW 01', '12345', 'Budi Santoso', '196501011990031001', 'Kecamatan Tengah', 'Kabupaten Contoh', 'Jawa Barat', '021-12345678', 'desa@majujaya.go.id');

-- Insert sample users (password: 'admin123' hashed)
INSERT INTO users (uuid, username, email, password, full_name, role, status, email_verified_at) VALUES
(UUID(), 'admin', 'admin@desa.go.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator Desa', 'super_admin', 'active', NOW()),
(UUID(), 'operator1', 'operator1@desa.go.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operator Satu', 'operator', 'active', NOW()),
(UUID(), 'operator2', 'operator2@desa.go.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operator Dua', 'operator', 'active', NOW()),
(UUID(), 'user1', 'user1@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Warga Satu', 'user', 'active', NOW()),
(UUID(), 'user2', 'user2@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Warga Dua', 'user', 'active', NOW());

-- Insert sample penduduk data
INSERT INTO penduduk (nik, kk, nama, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, rt, rw, agama, pekerjaan, status_kawin, pendidikan, nama_ayah, nama_ibu, telepon) VALUES
('3201010101850001', '3201010101000001', 'Ahmad Wijaya', 'Jakarta', '1985-01-01', 'L', 'Jl. Mawar No. 10', '001', '001', 'Islam', 'Wiraswasta', 'Kawin', 'SMA', 'Suharto Wijaya', 'Siti Aminah', '081234567890'),
('3201010202860002', '3201010101000001', 'Siti Nurhaliza', 'Bandung', '1986-02-02', 'P', 'Jl. Mawar No. 10', '001', '001', 'Islam', 'Ibu Rumah Tangga', 'Kawin', 'SMA', 'Abdul Rahman', 'Fatimah', '081234567891'),
('3201010303870003', '3201010202000002', 'Budi Setiawan', 'Surabaya', '1987-03-03', 'L', 'Jl. Melati No. 15', '002', '001', 'Kristen', 'Pegawai Swasta', 'Belum Kawin', 'S1', 'Setiawan', 'Maria', '081234567892'),
('3201010404880004', '3201010303000003', 'Dewi Sartika', 'Medan', '1988-04-04', 'P', 'Jl. Anggrek No. 20', '003', '002', 'Islam', 'Guru', 'Kawin', 'S1', 'Sartika', 'Aminah', '081234567893'),
('3201010505890005', '3201010404000004', 'Eko Prasetyo', 'Yogyakarta', '1989-05-05', 'L', 'Jl. Dahlia No. 25', '004', '002', 'Islam', 'Petani', 'Kawin', 'SMP', 'Prasetyo', 'Sari', '081234567894'),
('3201010606900006', '3201010505000005', 'Fitri Handayani', 'Semarang', '1990-06-06', 'P', 'Jl. Kenanga No. 30', '005', '003', 'Islam', 'Pedagang', 'Cerai Hidup', 'SMA', 'Handayani', 'Ningsih', '081234567895'),
('3201010707910007', '3201010606000006', 'Gunawan Susilo', 'Malang', '1991-07-07', 'L', 'Jl. Cempaka No. 35', '006', '003', 'Katolik', 'Buruh', 'Belum Kawin', 'SMA', 'Susilo', 'Maria', '081234567896'),
('3201010808920008', '3201010707000007', 'Hani Rahmawati', 'Palembang', '1992-08-08', 'P', 'Jl. Flamboyan No. 40', '007', '004', 'Islam', 'Bidan', 'Kawin', 'D3', 'Rahmawati', 'Siti', '081234567897'),
('3201010909930009', '3201010808000008', 'Indra Gunawan', 'Makassar', '1993-09-09', 'L', 'Jl. Bougenville No. 45', '008', '004', 'Islam', 'Sopir', 'Kawin', 'SMA', 'Gunawan', 'Ratna', '081234567898'),
('3201011010940010', '3201010909000009', 'Joko Widodo', 'Solo', '1994-10-10', 'L', 'Jl. Teratai No. 50', '009', '005', 'Islam', 'PNS', 'Kawin', 'S1', 'Widodo', 'Suharni', '081234567899');

-- Insert sample surat types
INSERT INTO surat_types (kode_surat, nama_surat, deskripsi, persyaratan, biaya, urutan) VALUES
('SKD', 'Surat Keterangan Domisili', 'Surat keterangan tempat tinggal warga', 'KTP, KK, Surat Pengantar RT/RW', 5000.00, 1),
('SKU', 'Surat Keterangan Usaha', 'Surat keterangan untuk keperluan usaha', 'KTP, KK, Surat Pengantar RT/RW, Foto Usaha', 10000.00, 2),
('SKTM', 'Surat Keterangan Tidak Mampu', 'Surat keterangan ekonomi tidak mampu', 'KTP, KK, Surat Pengantar RT/RW, Surat Keterangan Penghasilan', 0.00, 3),
('SPN', 'Surat Pengantar Nikah', 'Surat pengantar untuk keperluan nikah', 'KTP, KK, Akta Kelahiran, Surat Pengantar RT/RW', 15000.00, 4),
('SKK', 'Surat Keterangan Kematian', 'Surat keterangan kematian warga', 'KTP Almarhum, KK, Surat Keterangan Dokter/RS', 0.00, 5),
('SKL', 'Surat Keterangan Kelahiran', 'Surat keterangan kelahiran bayi', 'KTP Orang Tua, KK, Surat Keterangan Dokter/Bidan', 0.00, 6),
('SKP', 'Surat Keterangan Pindah', 'Surat keterangan pindah domisili', 'KTP, KK, Surat Pengantar RT/RW', 5000.00, 7),
('SKCK', 'Surat Keterangan Catatan Kepolisian', 'Surat pengantar untuk SKCK', 'KTP, KK, Pas Foto 4x6', 5000.00, 8);

-- Insert sample surat templates
INSERT INTO surat_templates (surat_type_id, nama_template, template_content, variables, kop_surat, is_default) VALUES
(1, 'Template SKD Default', 
'Yang bertanda tangan di bawah ini, Kepala Desa {{nama_desa}}, Kecamatan {{kecamatan}}, Kabupaten {{kabupaten}}, dengan ini menerangkan bahwa:

Nama                : {{nama}}
NIK                 : {{nik}}
Tempat/Tgl Lahir    : {{tempat_lahir}}, {{tanggal_lahir}}
Jenis Kelamin       : {{jenis_kelamin}}
Alamat              : {{alamat}}, RT {{rt}}/RW {{rw}}

Adalah benar warga Desa {{nama_desa}} dan berdomisili di alamat tersebut di atas.

Surat keterangan ini dibuat untuk keperluan {{keperluan}}.

Demikian surat keterangan ini dibuat dengan sebenarnya.', 
'["nama", "nik", "tempat_lahir", "tanggal_lahir", "jenis_kelamin", "alamat", "rt", "rw", "nama_desa", "kecamatan", "kabupaten", "keperluan"]',
'PEMERINTAH KABUPATEN {{kabupaten}}
KECAMATAN {{kecamatan}}
DESA {{nama_desa}}', 
TRUE);

-- Insert sample settings
INSERT INTO settings (`key`, `value`, description, type, is_public) VALUES
('app_name', 'Sistem Administrasi Desa', 'Nama aplikasi', 'string', TRUE),
('app_version', '1.0.0', 'Versi aplikasi', 'string', TRUE),
('max_file_upload', '5242880', 'Maksimal ukuran file upload (bytes)', 'number', FALSE),
('allowed_file_types', '["pdf", "jpg", "jpeg", "png", "doc", "docx"]', 'Tipe file yang diizinkan', 'json', FALSE),
('auto_approve_surat', 'false', 'Otomatis approve surat', 'boolean', FALSE),
('email_notifications', 'true', 'Aktifkan notifikasi email', 'boolean', FALSE),
('maintenance_mode', 'false', 'Mode maintenance', 'boolean', FALSE),
('session_timeout', '3600', 'Timeout session (detik)', 'number', FALSE);

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for better performance
CREATE INDEX idx_surat_requests_status_created ON surat_requests(status, created_at);
CREATE INDEX idx_penduduk_rt_rw_nama ON penduduk(rt, rw, nama);
CREATE INDEX idx_users_role_status ON users(role, status);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for surat requests with related data
CREATE VIEW v_surat_requests AS
SELECT 
    sr.id,
    sr.uuid,
    sr.nomor_surat,
    sr.keperluan,
    sr.status,
    sr.created_at,
    sr.processed_at,
    p.nama as nama_pemohon,
    p.nik,
    st.nama_surat,
    st.kode_surat,
    u.full_name as processed_by_name
FROM surat_requests sr
LEFT JOIN penduduk p ON sr.penduduk_nik = p.nik
LEFT JOIN surat_types st ON sr.surat_type_id = st.id
LEFT JOIN users u ON sr.processed_by = u.id
WHERE sr.deleted_at IS NULL;

-- View for active users
CREATE VIEW v_active_users AS
SELECT 
    id,
    uuid,
    username,
    email,
    full_name,
    role,
    last_login,
    created_at
FROM users 
WHERE status = 'active' AND deleted_at IS NULL;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to generate nomor surat
CREATE PROCEDURE GenerateNomorSurat(
    IN p_surat_type_id INT,
    OUT p_nomor_surat VARCHAR(50)
)
BEGIN
    DECLARE v_kode_surat VARCHAR(10);
    DECLARE v_counter INT DEFAULT 1;
    DECLARE v_year YEAR DEFAULT YEAR(NOW());
    DECLARE v_month INT DEFAULT MONTH(NOW());
    
    -- Get kode surat
    SELECT kode_surat INTO v_kode_surat 
    FROM surat_types 
    WHERE id = p_surat_type_id;
    
    -- Get counter for this month and year
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(nomor_surat, '/', 1), '/', -1) AS UNSIGNED)), 0) + 1
    INTO v_counter
    FROM surat_requests 
    WHERE surat_type_id = p_surat_type_id 
    AND YEAR(created_at) = v_year 
    AND MONTH(created_at) = v_month
    AND nomor_surat IS NOT NULL;
    
    -- Generate nomor surat: 001/SKD/XII/2025
    SET p_nomor_surat = CONCAT(
        LPAD(v_counter, 3, '0'), '/',
        v_kode_surat, '/',
        CASE v_month
            WHEN 1 THEN 'I' WHEN 2 THEN 'II' WHEN 3 THEN 'III'
            WHEN 4 THEN 'IV' WHEN 5 THEN 'V' WHEN 6 THEN 'VI'
            WHEN 7 THEN 'VII' WHEN 8 THEN 'VIII' WHEN 9 THEN 'IX'
            WHEN 10 THEN 'X' WHEN 11 THEN 'XI' WHEN 12 THEN 'XII'
        END, '/',
        v_year
    );
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger to generate UUID for users
CREATE TRIGGER before_insert_users
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
END //

-- Trigger to generate UUID for surat_requests
CREATE TRIGGER before_insert_surat_requests
BEFORE INSERT ON surat_requests
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
END //

-- Trigger to log surat status changes
CREATE TRIGGER after_update_surat_requests
AFTER UPDATE ON surat_requests
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO surat_logs (surat_request_id, user_id, action, description, old_status, new_status)
        VALUES (NEW.id, NEW.updated_by, 'status_change', 
                CONCAT('Status changed from ', OLD.status, ' to ', NEW.status),
                OLD.status, NEW.status);
    END IF;
END //

DELIMITER ;

-- =====================================================
-- GRANT PERMISSIONS (Optional - for production)
-- =====================================================

-- Create application user (uncomment for production)
-- CREATE USER IF NOT EXISTS 'desa_app'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON desa_admin.* TO 'desa_app'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================

SELECT 'Database schema created successfully!' as message,
       (SELECT COUNT(*) FROM users) as total_users,
       (SELECT COUNT(*) FROM penduduk) as total_penduduk,
       (SELECT COUNT(*) FROM surat_types) as total_surat_types;
