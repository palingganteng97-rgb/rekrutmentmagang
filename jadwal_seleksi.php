<?php
session_start(); 
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. KONEKSI DATABASE SERVER PUSAT (DISESUAIKAN)
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$lowongan_id = $_GET['lowongan_id'] ?? 0;
$tahapan_id  = $_GET['tahapan_id'] ?? 0; 

if (!$tahapan_id) {
    die("Akses ditolak: Parameter tahapan tidak valid.");
}

// Ambil info nama alur tahapan untuk sub-header aplikasi
$query_info = mysqli_query($conn, "SELECT lt.*, mts.nama_tahapan 
                                   FROM lowongan_tahapan lt
                                   JOIN mst_tahapan_seleksi mts ON lt.tahapan_id = mts.id
                                   WHERE lt.id = '$tahapan_id'");
$data_tahapan = mysqli_fetch_assoc($query_info);

$success_msg = "";
$error_msg   = "";

// PROSES SIMPAN / UPDATE JADWAL DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal     = $_POST['tanggal'];
    $jam_mulai   = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $lokasi      = mysqli_real_escape_string($conn, $_POST['lokasi']);
    $keterangan  = mysqli_real_escape_string($conn, $_POST['keterangan']);

    // KEAMANAN: Pastikan ID tahapan ini benar-benar ada di tabel lamaran_tahapan
    // Jika tabel asli Anda adalah lowongan_tahapan, ganti nama tabel di bawah ini menjadi lowongan_tahapan
    $nama_tabel_induk = "lamaran_tahapan"; 
    
    $cek_induk = mysqli_query($conn, "SELECT id FROM $nama_tabel_induk WHERE id = '$tahapan_id'");
    
    if (mysqli_num_rows($cek_induk) == 0) {
        // Jika tidak ada di lamaran_tahapan, coba cek ke tabel lowongan_tahapan
        $cek_lowongan_tahapan = mysqli_query($conn, "SELECT id FROM lowongan_tahapan WHERE id = '$tahapan_id'");
        if (mysqli_num_rows($cek_lowongan_tahapan) > 0) {
            $nama_tabel_induk = "lowongan_tahapan";
        }
    }

    if (mysqli_num_rows($cek_induk) == 0 && $nama_tabel_induk == "lamaran_tahapan") {
        $error_msg = "Gagal menyimpan: ID Tahapan Seleksi ($tahapan_id) tidak terdaftar di sistem database induk ($nama_tabel_induk).";
    } else {
        // Jalankan pengecekan jadwal existing
        $cek_jadwal = mysqli_query($conn, "SELECT id FROM jadwal_seleksi WHERE lamaran_tahapan_id = '$tahapan_id'");
        
        if (mysqli_num_rows($cek_jadwal) > 0) {
            $query_save = "UPDATE jadwal_seleksi SET 
                            tanggal = '$tanggal', 
                            jam_mulai = '$jam_mulai', 
                            jam_selesai = '$jam_selesai', 
                            lokasi = '$lokasi', 
                            keterangan = '$keterangan',
                            updated_at = NOW()
                           WHERE lamaran_tahapan_id = '$tahapan_id'";
        } else {
            $query_save = "INSERT INTO jadwal_seleksi (lamaran_tahapan_id, tanggal, jam_mulai, jam_selesai, lokasi, keterangan, created_at) 
                           VALUES ('$tahapan_id', '$tanggal', '$jam_mulai', '$jam_selesai', '$lokasi', '$keterangan', NOW())";
        }

        if (mysqli_query($conn, $query_save)) {
            $success_msg = "Jadwal seleksi berhasil diperbarui!";
        } else {
            $error_msg = "Gagal memproses data: " . mysqli_error($conn);
        }
    }
}

// AMBIL DATA JADWAL UNTUK DIKEMBALIKAN KE INPUT FORM
$query_view = mysqli_query($conn, "SELECT * FROM jadwal_seleksi WHERE lamaran_tahapan_id = '$tahapan_id'");
$jadwal     = mysqli_fetch_assoc($query_view);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Seleksi</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8fafc; padding: 40px; }
        .card { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin: 0 auto; }
        h2 { margin-top: 0; color: #0f172a; font-size: 20px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #334155; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .row { display: flex; gap: 12px; }
        .row .form-group { flex: 1; }
        .btn-primary { background-color: #4f46e5; color: white; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; font-size: 14px; }
        .btn-primary:hover { background-color: #4338ca; }
        .btn-link { display: inline-block; color: #64748b; font-size: 14px; text-decoration: none; margin-bottom: 15px; }
        .msg { padding: 10px; border-radius: 4px; font-size: 14px; margin-bottom: 15px; }
        .msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="card">
    <a href="lowongan_tahapan.php?lowongan_id=<?= $lowongan_id; ?>" class="btn-link">&larr; Kembali</a>
    <h2>Konfigurasi Jadwal Seleksi</h2>
    <p style="font-size: 14px; color: #64748b; margin-bottom: 20px;">Tahapan: <strong style="color: #4f46e5;"><?= htmlspecialchars($data_tahapan['nama_tahapan'] ?? ''); ?></strong></p>

    <?php if(!empty($success_msg)): ?> <div class="msg msg-success"><?= $success_msg; ?></div> <?php endif; ?>
    <?php if(!empty($error_msg)): ?> <div class="msg msg-error"><?= $error_msg; ?></div> <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label>Tanggal Pelaksanaan</label>
            <input type="date" name="tanggal" class="form-control" required value="<?= $jadwal['tanggal'] ?? ''; ?>">
        </div>
        <div class="row">
            <div class="form-group">
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" class="form-control" required value="<?= isset($jadwal['jam_mulai']) ? date('H:i', strtotime($jadwal['jam_mulai'])) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" class="form-control" required value="<?= isset($jadwal['jam_selesai']) ? date('H:i', strtotime($jadwal['jam_selesai'])) : ''; ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Lokasi / Ruangan / Link</label>
            <input type="text" name="lokasi" class="form-control" placeholder="Contoh: Ruang Rapat Lt.3 / Zoom" required value="<?= htmlspecialchars($jadwal['lokasi'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="3"><?= htmlspecialchars($jadwal['keterangan'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="btn-primary">Simpan Jadwal</button>
    </form>
</div>

</body>
</html>
