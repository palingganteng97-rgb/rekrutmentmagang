<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Target folder penyimpanan gambar
$target_dir = "uploads/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// --- FITUR: PROSES HAPUS GAMBAR SAJA ---
if (isset($_GET['delete_image'])) {
    $id = intval($_GET['delete_image']);
    
    $query_gambar = mysqli_query($koneksi, "SELECT gambar FROM rekrutmen_lowongan WHERE id = $id");
    $data_gambar  = mysqli_fetch_assoc($query_gambar);
    
    if (!empty($data_gambar['gambar'])) {
        $file_path = "uploads/" . $data_gambar['gambar'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        mysqli_query($koneksi, "UPDATE rekrutmen_lowongan SET gambar = NULL WHERE id = $id");
    }
    
    header("Location: master_lowongan.php");
    exit;
}

// --- FITUR: PROSES FORM (TAMBAH & UBAH DATA) ---
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

        // QUERY SESUAI STRUKTUR DATABASE KAMU
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

        // QUERY DIURUTKAN SESUAI STRUKTUR DATABASE GAMBAR KAMU
        $query_insert = "INSERT INTO rekrutmen_lowongan (kode_lowongan, jabatan_id, unit_id, judul_lowongan, jumlah_kebutuhan, deskripsi, kualifikasi, persyaratan, tanggal_mulai, tanggal_selesai, status, gambar, created_by, created_at) 
                         VALUES ('$kode_lowongan', $jabatan_id, $unit_id, '$judul_lowongan', $jumlah_kebutuhan, '$deskripsi', '$kualifikasi', '$persyaratan', $tanggal_mulai, $tanggal_selesai, '$status', '$nama_gambar', $created_by, '$waktu_sekarang')";
        
        if (!mysqli_query($koneksi, $query_insert)) {
            die("Gagal menambah data: " . mysqli_error($koneksi));
        }
    }
    header("Location: master_lowongan.php");
    exit;
}

// --- FITUR: PROSES HAPUS DATA (DELETE) ---
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

// 5. AMBIL DATA UNTUK TABEL & DROPDOWN RELASI
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
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header { display: flex; justify-content: space-between; align-items: center; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        .btn-purple { background: #4f46e5; color: white; border-radius: 14px; font-weight: 700; padding: 14px 28px; border: none; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .btn-purple:hover { background: #3b33c7; }

        /* CSS Tabel Horizontal Scrollbar Panjang */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); overflow-x: auto; width: 100%; }
        table { width: 100%; min-width: 1600px; border-collapse: collapse; text-align: left; font-size: 13px; }
/* LANJUTAN DI BARIS 192 KE BAWAH */
        th { color: #94a3b8; padding: 12px 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; white-space: nowrap; }
        td { padding: 16px 15px; color: #475569; border-bottom: 1px solid #f8fafc; white-space: nowrap; }
        .row-title { font-weight: 700; color: #1e293b; }
        
        /* Modal Popup Sederhana */
        .modal-popup { background: white; padding: 30px; border-radius: 20px; width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; }
        .form-control { padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; width: 100%; font-size: 14px; }
        textarea.form-control { resize: vertical; min-height: 80px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar-left">
        <div>
            <div class="brand-logo"><span></span>impozitions</div>
            <div class="menu-list">
                <a href="dashboard.php" class="menu-item">Dashboard</a>
                <a href="master_user.php" class="menu-item">Master User</a>
                <a href="master_unit.php" class="menu-item">Master Unit</a>
                <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                <a href="master_lowongan.php" class="menu-item active">Master Lowongan</a>
                <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                <a href="user.php" class="menu-item">Profil Pengguna</a>
            </div>
        </div>
        <div>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin keluar?')">Log Out</a>
        </div>
    </div>

    <!-- Main Content -->
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
                        <th style="text-align: right;">AKSI</th>
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
        <!-- Menampilkan ID User Pembuat -->
        By ID: <?= !empty($row['created_by']) ? $row['created_by'] : '<span style="color:#cbd5e1;">System</span>'; ?><br>
        
        <!-- Menampilkan Tanggal Dibuat -->
        Buat: <?= !empty($row['created_at']) ? date('d/m/y H:i', strtotime($row['created_at'])) : '-'; ?><br>
        
        <!-- Menampilkan Tanggal Diubah -->
        Ubah: <?= !empty($row['updated_at']) ? date('d/m/y H:i', strtotime($row['updated_at'])) : '-'; ?>
    </div>
</td>

                        <td style="text-align: right;">
                            <a href="master_lowongan.php?id_edit=<?= $row['id']; ?>" style="color: #4f46e5; text-decoration: none; font-weight: 700; margin-right: 12px;">Edit</a>
                            <a href="master_lowongan.php?delete=<?= $row['id']; ?>" onclick="return confirm('Hapus data ini?')" style="color: #ef4444; text-decoration: none; font-weight: 700;">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

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
</div>

</body>
</html>
