<?php
session_start();

// =========================================================================
// 1. PENGATURAN KONEKSI DATABASE
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil daftar pelamar untuk pilihan Dropdown di Form
$q_pelamar = mysqli_query($conn, "SELECT id, nama_lengkap AS nama FROM pelamar ORDER BY nama_lengkap ASC");

// Inisialisasi variabel untuk status notifikasi
$status_pesan = "";
$tipe_pesan = "";

// =========================================================================
// 2. PROSES LOGIKA KETIKA TOMBOL SIMPAN DIKLIK
// =========================================================================
if (isset($_POST['btn_simpan'])) {
    $pelamar_id      = $_POST['pelamar_id'];
    $nomor_sip       = mysqli_real_escape_string($conn, $_POST['nomor_sip']);
    $tanggal_terbit  = $_POST['tanggal_terbit'];
    $tanggal_expired = $_POST['tanggal_expired'];
    
    // Konfigurasi berkas berkas upload
    $file_nama = $_FILES['file_sip']['name'];
    $file_tmp  = $_FILES['file_sip']['tmp_name'];
    $file_size = $_FILES['file_sip']['size'];
    $file_ext  = strtolower(pathinfo($file_nama, PATHINFO_EXTENSION));
    
    // Aturan validasi file
    $ekstensi_diperbolehkan = array('pdf', 'jpg', 'jpeg', 'png');
    $maksimal_ukuran        = 2 * 1024 * 1024; // Maksimal 2 Megabytes (MB)
    
    if (empty($pelamar_id) || empty($nomor_sip) || empty($tanggal_terbit) || empty($tanggal_expired) || empty($file_nama)) {
        $status_pesan = "Semua kolom form wajib diisi!";
        $tipe_pesan   = "error";
    } elseif (!in_array($file_ext, $ekstensi_diperbolehkan)) {
        $status_pesan = "Format file salah! Hanya diperbolehkan dokumen berformat PDF, JPG, atau PNG.";
        $tipe_pesan   = "error";
    } elseif ($file_size > $maksimal_ukuran) {
        $status_pesan = "Ukuran file terlalu besar! Batas maksimal berkas adalah 2 Megabytes (MB).";
        $tipe_pesan   = "error";
    } else {
        // Buat nama berkas baru yang unik agar tidak menimpa berkas lama
        $file_baru_nama = "SIP_" . $pelamar_id . "_" . time() . "." . $file_ext;
        $folder_tujuan  = "uploads/sip/";
        
        // Buat folder otomatis jika belum ada di dalam server XAMPP
        if (!is_dir($folder_tujuan)) {
            mkdir($folder_tujuan, 0777, true);
        }
        
        // Pindahkan berkas fisik dari memori sementara ke folder tujuan rekrutmen
        if (move_uploaded_file($file_tmp, $folder_tujuan . $file_baru_nama)) {
            // Masukkan data transaksi ke dalam tabel pelamar_sip sesuai desain HeidiSQL
            $query_insert = "INSERT INTO pelamar_sip (pelamar_id, nomor_sip, tanggal_terbit, tanggal_expired, file_sip, created_at) 
                             VALUES ('$pelamar_id', '$nomor_sip', '$tanggal_terbit', '$tanggal_expired', '$file_baru_nama', NOW())";
            
            if (mysqli_query($conn, $query_insert)) {
                $status_pesan = "Data Surat Izin Praktik (SIP) pelamar berhasil disimpan!";
                $tipe_pesan   = "success";
            } else {
                $status_pesan = "Gagal menyimpan data ke database: " . mysqli_error($conn);
                $tipe_pesan   = "error";
            }
        } else {
            $status_pesan = "Gagal mengunggah berkas ke folder server. Periksa hak akses folder.";
            $tipe_pesan   = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload SIP Pelamar - Magang ID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        .form-container { width: 100%; max-width: 600px; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); padding: 40px; border: 1px solid #e2e8f0; }
        .form-header h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
        .form-header p { font-size: 14px; color: #6c757d; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-group label { font-size: 13px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input, select { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; color: #334155; outline: none; background: #ffffff; transition: all 0.2s; }
        .form-input:focus, select:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-submit { background: #4f46e5; color: white; border: none; width: 100%; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #4338ca; }
        .alert { padding: 14px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn-back:hover { color: #4f46e5; }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h2>Registrasi Dokumen SIP</h2>
        <p>Lengkapi formulir di bawah untuk memperbarui dokumen Surat Izin Praktik pelamar medis.</p>
    </div>

    <!-- Tampilkan Notifikasi Alert Jika Ada Logika Berjalan -->
    <?php if (!empty($status_pesan)): ?>
        <div class="alert alert-<?= $tipe_pesan; ?>">
            <?= $status_pesan; ?>
        </div>
    <?php endif; ?>

    <!-- Wajib Menggunakan enctype="multipart/form-data" agar file bisa terbaca oleh $_FILES -->
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nama Pelamar</label>
            
<!--  Kode yang benar: -->
<select name="pelamar_id" required>
    <option value="">-- Pilih Anggota Pelamar --</option>
    <?php while($row = mysqli_fetch_assoc($q_pelamar)): ?>
        <option value="<?= $row['id']; ?>"><?= $row['nama']; ?></option>
    <?php endwhile; ?>
</select>

        </div>

        <div class="form-group">
            <label>Nomor SIP</label>
            <input type="text" name="nomor_sip" class="form-input" placeholder="Contoh: 449/001/SIP-D/DPMPTSP/2026" required>
        </div>

        <div class="row-grid">
            <div class="form-group">
                <label>Tanggal Terbit</label>
                <input type="date" name="tanggal_terbit" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Tanggal Kedaluwarsa</label>
                <input type="date" name="tanggal_expired" class="form-input" required>
            </div>
        </div>

        <div class="form-group">
            <label>Unggah Berkas Dokumen SIP</label>
            <input type="file" name="file_sip" class="form-input" accept=".pdf, .jpg, .jpeg, .png" required>
            <small style="color: #94a3b8; font-size: 12px; margin-top: 2px;">Format yang didukung: PDF, JPG, PNG (Maks. 2MB)</small>
        </div>

        <button type="submit" name="btn_simpan" class="btn-submit">Simpan Dokumen</button>
        <a href="dashboard.php" class="btn-back">← Kembali ke Dashboard</a>
    </form>
</div>

</body>
</html>
