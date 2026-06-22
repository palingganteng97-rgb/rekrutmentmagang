<?php 
session_start(); 

// =========================================================================
// SINKRONISASI KONEKSI: MENGGUNAKAN SERVER ASLI YANG SUDAH ADA DATABASENYA
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Target folder penyimpanan gambar banner
$target_dir = "uploads/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// =========================================================================
// 2. [CRUD - CREATE / UPDATE] PROSES FORM & VALIDASI ANTI-DUPLIKAT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $lowongan_id   = intval($_POST['lowongan_id']);
    $tahapan_id    = intval($_POST['tahapan_id']); 
    $urutan        = intval($_POST['urutan']);
    $minimal_nilai = floatval($_POST['minimal_nilai']);
    $wajib_lulus   = intval($_POST['wajib_lulus']); 
    $waktu_sekarang = date('Y-m-d H:i:s');
    
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // VALIDASI A: Cek duplikasi Jenis Tahapan pada lowongan ini
    $sql_cek_tahapan = "SELECT id FROM lowongan_tahapan WHERE lowongan_id = $lowongan_id AND tahapan_id = $tahapan_id";
    if ($action == 'edit') { $sql_cek_tahapan .= " AND id != $id"; }
    $cek_tahapan = mysqli_query($koneksi, $sql_cek_tahapan);
    
    if (mysqli_num_rows($cek_tahapan) > 0) {
        $_SESSION['error_msg'] = 'Jenis tahapan seleksi tersebut sudah terdaftar untuk formasi lowongan ini.';
        header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
        exit;
    }

    // VALIDASI B: Cek duplikasi Nomor Urutan Alur pada lowongan ini
    $sql_cek_urutan = "SELECT id FROM lowongan_tahapan WHERE lowongan_id = $lowongan_id AND urutan = $urutan";
    if ($action == 'edit') { $sql_cek_urutan .= " AND id != $id"; }
    $cek_urutan = mysqli_query($koneksi, $sql_cek_urutan);
    
    if (mysqli_num_rows($cek_urutan) > 0) {
        $_SESSION['error_msg'] = 'Nomor urutan alur tersebut (Tahap Ke-' . $urutan . ') sudah digunakan oleh tahapan lain.';
        header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
        exit;
    }

    // PROSES EKSEKUSI DATA JIKA LOLOS VALIDASI
    if ($action == 'edit') {
        $query_update = "UPDATE lowongan_tahapan 
                         SET lowongan_id = $lowongan_id, tahapan_id = $tahapan_id, urutan = $urutan, minimal_nilai = $minimal_nilai, wajib_lulus = $wajib_lulus, updated_at = '$waktu_sekarang' 
                         WHERE id = $id";
        mysqli_query($koneksi, $query_update);
    } else {
        $query_insert = "INSERT INTO lowongan_tahapan (lowongan_id, tahapan_id, urutan, minimal_nilai, wajib_lulus, created_at, updated_at) 
                         VALUES ($lowongan_id, $tahapan_id, $urutan, $minimal_nilai, $wajib_lulus, '$waktu_sekarang', '$waktu_sekarang')";
        mysqli_query($koneksi, $query_insert);
    }
    
    header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
    exit;
}

// =========================================================================
// 3. [CRUD - CREATE / UPDATE] PROSES SIMPAN DATA FORM POP-UP MODAL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul_lowongan   = mysqli_real_escape_string($koneksi, $_POST['judul_lowongan']);
    $jumlah_kebutuhan = intval($_POST['jumlah_kebutuhan']);
    $status           = mysqli_real_escape_string($koneksi, $_POST['status']);
    $jabatan_id       = intval($_POST['jabatan_id']);
    $unit_id          = intval($_POST['unit_id']);
    
    $deskripsi        = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $kualifikasi      = mysqli_real_escape_string($koneksi, $_POST['kualifikasi']);
    $persyaratan      = mysqli_real_escape_string($koneksi, $_POST['persyaratan']);
    $tanggal_mulai    = !empty($_POST['tanggal_mulai']) ? "'".$_POST['tanggal_mulai']."'" : "NULL";
    $tanggal_selesai  = !empty($_POST['tanggal_selesai']) ? "'".$_POST['tanggal_selesai']."'" : "NULL";
    
    $waktu_sekarang   = date('Y-m-d H:i:s');

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // PROSES UBAH DATA (UPDATE)
        $id = intval($_POST['id']);
        $query_lama = mysqli_query($koneksi, "SELECT gambar FROM rekrutmen_lowongan WHERE id = $id");
        $data_lama  = mysqli_fetch_assoc($query_lama);
        $nama_gambar = $data_lama['gambar'];

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            if (!empty($nama_gambar) && file_exists($target_dir . $nama_gambar)) { 
                unlink($target_dir . $nama_gambar); 
            }
            $nama_file = $_FILES['gambar']['name'];
            $tmp_file  = $_FILES['gambar']['tmp_name'];
            $ekstensi  = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
            $nama_gambar = "lwn_" . time() . "_" . rand(10, 99) . "." . $ekstensi;
            move_uploaded_file($tmp_file, $target_dir . $nama_gambar);
        }

        $query_update = "UPDATE rekrutmen_lowongan 
                         SET jabatan_id = $jabatan_id, 
                             unit_id = $unit_id, 
                             judul_lowongan = '$judul_lowongan', 
                             jumlah_kebutuhan = $jumlah_kebutuhan, 
                             deskripsi = '$deskripsi', 
                             kualifikasi = '$kualifikasi', 
                             persyaratan = '$persyaratan', 
                             tanggal_mulai = $tanggal_mulai, 
                             tanggal_selesai = $tanggal_selesai, 
                             status = '$status', 
                             gambar = '$nama_gambar', 
                             updated_at = '$waktu_sekarang' 
                         WHERE id = $id";
        
        if (!mysqli_query($koneksi, $query_update)) {
            die("Gagal mengubah data: " . mysqli_error($koneksi));
        }

    } else {
        // PROSES TAMBAH DATA (CREATE)
        $kode_lowongan = "LWN-" . rand(100, 999); 
        $created_by    = (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ? intval($_SESSION['user_id']) : "NULL";
        $nama_gambar   = "";

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            $nama_file = $_FILES['gambar']['name'];
            $tmp_file  = $_FILES['gambar']['tmp_name'];
            $ekstensi  = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
            $nama_gambar = "lwn_" . time() . "_" . rand(10, 99) . "." . $ekstensi;
            move_uploaded_file($tmp_file, $target_dir . $nama_gambar);
        }

        $query_insert = "INSERT INTO rekrutmen_lowongan (kode_lowongan, jabatan_id, unit_id, judul_lowongan, jumlah_kebutuhan, deskripsi, kualifikasi, persyaratan, tanggal_mulai, tanggal_selesai, status, gambar, created_by, created_at) 
                         VALUES ('$kode_lowongan', $jabatan_id, $unit_id, '$judul_lowongan', $jumlah_kebutuhan, '$deskripsi', '$kualifikasi', '$persyaratan', $tanggal_mulai, $tanggal_selesai, '$status', '$nama_gambar', $created_by, '$waktu_sekarang')";
        
        if (!mysqli_query($koneksi, $query_insert)) {
            die("Gagal menambah data: " . mysqli_error($koneksi));
        }
    }
    header("Location: master_lowongan.php");
    exit;
}

// =========================================================================
// 4. [CRUD - DELETE] PROSES HAPUS REKORD DATA
// =========================================================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $query_gambar = mysqli_query($koneksi, "SELECT gambar FROM rekrutmen_lowongan WHERE id = $id");
    $data_gambar  = mysqli_fetch_assoc($query_gambar);
    if (!empty($data_gambar['gambar']) && file_exists($target_dir . $data_gambar['gambar'])) {
        unlink($target_dir . $data_gambar['gambar']);
    }

    $query_delete = "DELETE FROM rekrutmen_lowongan WHERE id = $id";
    if (mysqli_query($koneksi, $query_delete)) {
        header("Location: master_lowongan.php");
        exit;
    } else {
        die("Gagal menghapus data: " . mysqli_error($koneksi));
    }
}

// =========================================================================
// 5. [CRUD - READ] AMBIL DATA UTAMA UNTUK TABEL & DROPDOWN RELASI
// =========================================================================
$query_tampil = "SELECT rl.*, rj.nama_jabatan, ru.nama_unit
                 FROM rekrutmen_lowongan rl
                 LEFT JOIN mst_jabatan rj ON rl.jabatan_id = rj.id
                 LEFT JOIN mst_unit ru ON rl.unit_id = ru.id
                 ORDER BY rl.id DESC";
$ambil_data = mysqli_query($koneksi, $query_tampil);

$list_jabatan = mysqli_query($koneksi, "SELECT id, nama_jabatan FROM mst_jabatan ORDER BY nama_jabatan ASC");
$list_unit    = mysqli_query($koneksi, "SELECT id, nama_unit FROM mst_unit ORDER BY nama_unit ASC");

$id_edit = isset($_GET['id_edit']) ? intval($_GET['id_edit']) : 0;
$query_edit = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan WHERE id = $id_edit");
$data_lowongan_edit = mysqli_fetch_assoc($query_edit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Lowongan - Rekrutmen Magang</title>
    <style>

        /* =========================================================================
   GAYA TAMPILAN ALERT MODAL KUSTOM MODERN
   ========================================================================= */
.alert-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; justify-content: center; align-items: center; z-index: 9999999;
}
.alert-box {
    background: #ffffff; width: 90%; max-width: 400px; padding: 30px;
    border-radius: 24px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: alertBounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes alertBounce {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.alert-icon {
    width: 60px; height: 60px; background: #fee2e2; color: #ef4444;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 30px; margin: 0 auto 16px auto; font-weight: bold;
}
.alert-title { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
.alert-text { font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }
.btn-alert-close {
    width: 100%; padding: 12px; background: #4f46e5; color: #ffffff;
    border: none; border-radius: 12px; font-weight: 700; font-size: 14px;
    cursor: pointer; transition: background 0.2s;
}
.btn-alert-close:hover { background: #3b33c7; }

        /* =========================================================================
           1. RESET GLOBAL & CORES STYLING
           ========================================================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        
        /* Sidebar Menu Layout */
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5 !important; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }

        /* Tombol Logout Merah di Dasar Sidebar */
        .btn-logout { display: block; width: 100%; background: #dc2626; color: white; text-decoration: none; text-align: center; font-weight: 700; font-size: 14px; padding: 14px 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); transition: background 0.2s; margin-top: auto; }
        .btn-logout:hover { background: #b91c1c; }

        /* Area Konten Utama */
        .main-content { 
            flex: 1; 
            background: #fbfbfd; 
            padding: 40px 50px; 
            display: flex; 
            flex-direction: column; 
            gap: 32px; 
            overflow-y: auto; 
            overflow-x: hidden !important; /* Mengunci konten luar agar tidak ikut bergeser miring */
            min-width: 0; /* Solusi Flexbox Bug: Memaksa area mengalah terhadap scrollbar tabel di dalamnya */
        }
        .content-header { display: flex; justify-content: space-between; align-items: center; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        .btn-purple { background: #4f46e5; color: white; border-radius: 14px; font-weight: 700; padding: 14px 28px; border: none; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .btn-purple:hover { background: #3b33c7; }

        /* =========================================================================
           2. PERBAIKAN TOTAL: MEMAKSA TABEL BISA DIGESER SECARA HORIZONTAL
           ========================================================================= */
        .table-wrapper { 
            background: #ffffff; 
            border: 1px solid #f1f5f9; 
            border-radius: 24px; 
            padding: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.01); 
            
            /* MEMAKSA PEMBUNGKUS MEMUNCUKKAN SCROLLBAR HORIZONTAL */
            overflow-x: auto !important; 
            width: 100% !important;
            display: block !important;
        }

        table { 
            width: 100% !important; 
            border-collapse: collapse; 
            text-align: left; 
            font-size: 14px; 
            table-layout: auto !important; /* Menyetel pembagian lebar kolom adaptif mengikuti isi teks */
            
            /* MEMAKSA TABEL MELEBAR KE KANAN AGAR SELURUH DATA TERSEMBUNYI MUNCUL */
            min-width: 1800px !important; 
        }

        /* MEMPERTEBAL TAMPILAN BATANG SCROLLBAR AGAR MUDAH DIGESER ADMIN */
        .table-wrapper::-webkit-scrollbar {
            height: 10px !important; /* Ketebalan batang scrollbar bawah */
        }
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9 !important;
            border-radius: 10px !important;
        }
        .table-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1 !important;
            border-radius: 10px !important;
        }
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8 !important;
        }

        th { color: #94a3b8; padding: 12px 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; white-space: nowrap; }
        td { padding: 16px 15px; color: #475569; border-bottom: 1px solid #f8fafc; white-space: nowrap; vertical-align: middle; }
        .row-title { font-weight: 700; color: #1e293b; }
        
        /* Modal Popup Sederhana */
        .modal-popup { background: white; padding: 30px; border-radius: 20px; width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; text-align: left; }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        .form-control { padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; width: 100%; font-size: 14px; font-weight: 600; color: #1e293b; background-color: #f8fafc; outline: none; }
        .form-control:focus { border-color: #4f46e5; }
        textarea.form-control { resize: vertical; min-height: 80px; }
    </style>
</head>
<body>


<div class="dashboard-container">
    <!-- Sidebar -->

        <!-- SIDEBAR MENU KIRI DENGAN JAMINAN TOMBOL LOGOUT DIPOSISI BAWAH -->
        <aside class="sidebar-left">
            <!-- Wadah Atas: Untuk Logo dan Menu Navigasi Utama -->
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item active">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
                    <a href="user.php" class="menu-item" style="margin-bottom: 8px;">Profil Pengguna</a>
                </nav>
            </div>
            <!-- Wadah Bawah: Otomatis Terdorong ke Bawah -->
            <div>
                <nav class="menu-list">
                    <a href="logout.php" class="menu-item btn-sidebar-logout" style="background: #ef4444; color: white !important; text-align: center; border-radius: 12px; padding: 12px; font-weight: bold;" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?')">Log Out</a>
                </nav>
            </div>
        </aside>

        <!-- PERBAIKAN UTAMA: WAJIB DIBUNGKUS KEDALAM MAIN-CONTENT AGAR BISA SCROLL -->
        <div class="main-content">
            <div class="content-header">
                <h1>Master Lowongan</h1>
                <button class="btn-purple" onclick="window.location.href='master_lowongan.php?tambah_baru=1'">Tambah Lowongan</button>
            </div>

            <!-- Panel Tabel Data -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>KODE</th>
                            <th>NAMA LOWONGAN</th>
                            <th>JABATAN</th>
                            <th>UNIT</th>
                            <th>DESKRIPSI</th>
                            <th>KUALIFIKASI</th>
                            <th>PERSYARATAN</th>
                            <th>MULAI</th>
                            <th>SELESAI</th>
                            <th>KUOTA</th>
                            <th>STATUS</th>
                            <th>GAMBAR</th>
                            <th>TIMESTAMPS</th>
                            <th style="text-align: center; width: 110px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while($row = mysqli_fetch_assoc($ambil_data)) { 
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><span class="row-title"><?= $row['kode_lowongan']; ?></span></td>
                            <td style="font-weight: 700; color: #4f46e5;"><?= htmlspecialchars($row['judul_lowongan'] ?? 'Lowongan Tanpa Nama'); ?></td>
                            <td><?= $row['nama_jabatan'] ?? '-'; ?></td>
                            <td><?= $row['nama_unit'] ?? '-'; ?></td>
                            
                            <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis;"><small><?= !empty($row['deskripsi']) ? $row['deskripsi'] : '-'; ?></small></td>
                            <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis;"><small><?= !empty($row['kualifikasi']) ? $row['kualifikasi'] : '-'; ?></small></td>
                            <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis;"><small><?= !empty($row['persyaratan']) ? $row['persyaratan'] : '-'; ?></small></td>
                            
                            <td><?= !empty($row['tanggal_mulai']) ? date('d/m/Y', strtotime($row['tanggal_mulai'])) : '-'; ?></td>
                            <td><?= !empty($row['tanggal_selesai']) ? date('d/m/Y', strtotime($row['tanggal_selesai'])) : '-'; ?></td>
                            <td><?= $row['jumlah_kebutuhan']; ?> Org</td>
                            <td><span style="color: <?= $row['status'] == 'Aktif' ? '#10b981' : '#ef4444'; ?>; font-weight:700;"><?= $row['status']; ?></span></td>
                            <td>
                                <?php if (!empty($row['gambar']) && file_exists("uploads/" . $row['gambar'])): ?>
                                    <img src="uploads/<?= $row['gambar']; ?>" width="40" height="40" style="border-radius:6px; object-fit:cover;">
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size:11px; font-style:italic;">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:11px; color:#94a3b8; line-height: 1.5;">
                                    By ID: <?= !empty($row['created_by']) ? $row['created_by'] : '<span style="color:#cbd5e1;">System</span>'; ?><br>
                                    Buat: <?= !empty($row['created_at']) ? date('d/m/y H:i', strtotime($row['created_at'])) : '-'; ?><br>
                                    Ubah: <?= !empty($row['updated_at']) ? date('d/m/y H:i', strtotime($row['updated_at'])) : '-'; ?>
                                </div>
                            </td>
<td style="text-align: center; white-space: nowrap; vertical-align: middle;">
    <!-- Tombol 1: Edit Data (Kotak Ikon Biru) -->
    <a href="master_lowongan.php?id_edit=<?= $row['id']; ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 10px; font-size: 14px; margin-right: 4px;" title="Ubah Data Lowongan">✏️</a>
    
    <!-- Tombol 2: Hapus Data (Kotak Ikon Merah) -->
    <a href="master_lowongan.php?delete=<?= $row['id']; ?>" onclick="return confirm('Hapus data ini?')" style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #ef4444; color: white; text-decoration: none; border-radius: 10px; font-size: 14px; margin-right: 8px;" title="Hapus Data">🗑️</a>
    
    <!-- PERBAIKAN: Tombol Teks Panjang Kapsul Elegan -->
    <a href="lowongan_tahapan.php?lowongan_id=<?= $row['id']; ?>" style="display: inline-inline-flex; align-items: center; padding: 8px 16px; background: #f5f3ff; color: #4f46e5; text-decoration: none; border-radius: 10px; font-size: 12px; font-weight: 700; border: 1px solid #e0e7ff; transition: all 0.2s;" onmouseover="this.style.background='#4f46e5'; this.style.color='#ffffff';" onmouseout="this.style.background='#f5f3ff'; this.style.color='#4f46e5';">
        Lowongan Tahapan
    </a>
</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div> <!-- PENUTUP MAIN-CONTENT -->

        <!-- FORM POPUP MODAL (TAMBAH / UBAH DATA) -->
        <?php if(isset($_GET['id_edit']) || isset($_GET['tambah_baru'])): ?>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999;">
            <div class="modal-popup" style="margin: 0; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-bottom: 20px; color:#1e293b;"><?= isset($data_lowongan_edit) ? 'Ubah Data Lowongan' : 'Tambah Lowongan Baru'; ?></h3>
                
                <form action="master_lowongan.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $data_lowongan_edit['id'] ?? ''; ?>">

                    <div class="form-group">
                        <label>NAMA LOWONGAN / JUDUL</label>
                        <input type="text" name="judul_lowongan" class="form-control" value="<?= $data_lowongan_edit['judul_lowongan'] ?? ''; ?>" required>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1;">
                            <label>JABATAN</label>
                            <select name="jabatan_id" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php 
                                mysqli_data_seek($list_jabatan, 0); 
                                while($jwb = mysqli_fetch_assoc($list_jabatan)) { 
                                    $selected = (isset($data_lowongan_edit) && $jwb['id'] == $data_lowongan_edit['jabatan_id']) ? 'selected' : '';
                                    echo "<option value='".$jwb['id']."' $selected>".$jwb['nama_jabatan']."</option>";
                                } 
                                ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>UNIT KERJA</label>
                            <select name="unit_id" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php 
                                mysqli_data_seek($list_unit, 0); 
                                while($ut = mysqli_fetch_assoc($list_unit)) { 
                                    $selected = (isset($data_lowongan_edit) && $ut['id'] == $data_lowongan_edit['unit_id']) ? 'selected' : '';
                                    echo "<option value='".$ut['id']."' $selected>".$ut['nama_unit']."</option>";
                                } 
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>DESKRIPSI LOWONGAN</label>
                        <textarea name="deskripsi" class="form-control"><?= $data_lowongan_edit['deskripsi'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>KUALIFIKASI</label>
                        <textarea name="kualifikasi" class="form-control"><?= $data_lowongan_edit['kualifikasi'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>PERSYARATAN</label>
                        <textarea name="persyaratan" class="form-control"><?= $data_lowongan_edit['persyaratan'] ?? ''; ?></textarea>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1;">
                            <label>TANGGAL MULAI</label>
<!-- LANJUTAN DI BARIS 357 KE BAWAH -->
                            <input type="date" name="tanggal_mulai" class="form-control" value="<?= $data_lowongan_edit['tanggal_mulai'] ?? ''; ?>">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>TANGGAL SELESAI</label>
                            <input type="date" name="tanggal_selesai" class="form-control" value="<?= $data_lowongan_edit['tanggal_selesai'] ?? ''; ?>">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1;">
                            <label>KUOTA KEBUTUHAN</label>
                            <input type="number" name="jumlah_kebutuhan" class="form-control" value="<?= $data_lowongan_edit['jumlah_kebutuhan'] ?? '1'; ?>" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>STATUS</label>
                            <select name="status" class="form-control">
                                <option value="Aktif" <?= (isset($data_lowongan_edit) && $data_lowongan_edit['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Tidak Aktif" <?= (isset($data_lowongan_edit) && $data_lowongan_edit['status'] == 'Tidak Aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>GAMBAR LOWONGAN</label>
                        <input type="file" name="gambar" class="form-control">
                        <?php if(!empty($data_lowongan_edit['gambar'])): ?>
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <img src="uploads/<?= $data_lowongan_edit['gambar']; ?>" width="60" style="border-radius: 6px;">
                                <a href="master_lowongan.php?delete_image=<?= $data_lowongan_edit['id']; ?>" style="color: #ef4444; font-size: 12px; text-decoration: none; font-weight: bold;">Hapus Gambar</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:10px; justify-content: flex-end; margin-top:20px;">
                        <a href="master_lowongan.php" class="form-control" style="width:100px; text-align:center; text-decoration:none; color:#475569; background:#f1f5f9; display: flex; align-items: center; justify-content: center;">Batal</a>
                        <button type="submit" class="btn-purple" style="padding:10px 20px; border-radius:8px;">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
