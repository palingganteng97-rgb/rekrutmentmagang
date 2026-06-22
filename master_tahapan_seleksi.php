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

// --- FITUR: PROSES FORM (TAMBAH & UBAH DATA) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_tahapan  = mysqli_real_escape_string($koneksi, $_POST['nama_tahapan']);
    $deskripsi     = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $status        = mysqli_real_escape_string($koneksi, $_POST['status']);
    $waktu_sekarang = date('Y-m-d H:i:s');

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // PROSES UBAH DATA (UPDATE)
        $id = intval($_POST['id']);
        
        $query_update = "UPDATE mst_tahapan_seleksi 
                         SET nama_tahapan = '$nama_tahapan', 
                             deskripsi = '$deskripsi', 
                             status = '$status', 
                             updated_at = '$waktu_sekarang' 
                         WHERE id = $id";
        
        if (!mysqli_query($koneksi, $query_update)) {
            die("Gagal mengubah data: " . mysqli_error($koneksi));
        }
    } else {
        // PROSES TAMBAH DATA (CREATE)
        $kode_tahapan = "THP-" . rand(100, 999); 

        $query_insert = "INSERT INTO mst_tahapan_seleksi (kode_tahapan, nama_tahapan, deskripsi, status, created_at) 
                         VALUES ('$kode_tahapan', '$nama_tahapan', '$deskripsi', '$status', '$waktu_sekarang')";
        
        if (!mysqli_query($koneksi, $query_insert)) {
            die("Gagal menambah data: " . mysqli_error($koneksi));
        }
    }
    header("Location: master_tahapan_seleksi.php");
    exit;
}

// --- FITUR: PROSES HAPUS DATA (DELETE) ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $query_delete = "DELETE FROM mst_tahapan_seleksi WHERE id = $id";
    if (mysqli_query($koneksi, $query_delete)) {
        header("Location: master_tahapan_seleksi.php");
        exit;
    } else {
        die("Gagal menghapus data: " . mysqli_error($koneksi));
    }
}

// 5. AMBIL DATA UNTUK TABEL
$query_tampil = "SELECT * FROM mst_tahapan_seleksi ORDER BY id DESC";
$ambil_data = mysqli_query($koneksi, $query_tampil);

$id_edit = isset($_GET['id_edit']) ? intval($_GET['id_edit']) : 0;
$query_edit = mysqli_query($koneksi, "SELECT * FROM mst_tahapan_seleksi WHERE id = $id_edit");
$data_tahapan_edit = mysqli_fetch_assoc($query_edit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tahapan Seleksi - Rekrutmen Magang</title>
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

        /* Tombol Logout Merah */
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
        table { width: 100%; min-width: 1200px; border-collapse: collapse; text-align: left; font-size: 13px; }
        th { color: #94a3b8; padding: 12px 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; white-space: nowrap; }
        td { padding: 16px 15px; color: #475569; border-bottom: 1px solid #f8fafc; white-space: nowrap; }
        .row-title { font-weight: 700; color: #1e293b; }
        
        /* Modal Popup Sederhana */
        .modal-popup { background: white; padding: 30px; border-radius: 20px; width: 550px; margin: 20px auto; border: 1px solid #e2e8f0; }
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
                <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                <a href="master_tahapan_seleksi.php" class="menu-item active">Master Tahapan Seleksi</a>
                <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
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
            <h1>Master Tahapan Seleksi</h1>
            <button class="btn-purple" onclick="window.location.href='master_tahapan_seleksi.php?tambah_baru=1'">Tambah Tahapan</button>
        </div>

        <!-- Panel Tabel Data -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>KODE TAHAPAN</th>
                        <th>NAMA TAHAPAN</th>
                        <th>DESKRIPSI</th>
                        <th>STATUS</th>
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
                        <td><span class="row-title"><?= $row['kode_tahapan']; ?></span></td>
                        <td><?= $row['nama_tahapan']; ?></td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;"><small><?= !empty($row['deskripsi']) ? $row['deskripsi'] : '-'; ?></small></td>
                        <td>
                            <span style="color: <?= $row['status'] == 'Aktif' ? '#10b981' : '#ef4444'; ?>; font-weight:700;">
                                <?= $row['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size:11px; color:#94a3b8; line-height: 1.4;">
                                Buat: <?= !empty($row['created_at']) ? date('d/m/y H:i', strtotime($row['created_at'])) : '-'; ?><br>
<!-- LANJUTAN DI BARIS 191 KE BAWAH -->
                                Ubah: <?= !empty($row['updated_at']) ? date('d/m/y H:i', strtotime($row['updated_at'])) : '-'; ?>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <a href="master_tahapan_seleksi.php?id_edit=<?= $row['id']; ?>" style="color: #4f46e5; text-decoration: none; font-weight: 700; margin-right: 12px;">Edit</a>
                            <a href="master_tahapan_seleksi.php?delete=<?= $row['id']; ?>" onclick="return confirm('Hapus data ini?')" style="color: #ef4444; text-decoration: none; font-weight: 700;">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- FORM POPUP MODAL TAHAPAN SELEKSI (TAMBAH / UBAH DATA) -->
        <?php if(isset($_GET['id_edit']) || isset($_GET['tambah_baru'])): ?>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999;">
            <div class="modal-popup" style="margin: 0; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-bottom: 20px; color:#1e293b;"><?= isset($data_tahapan_edit) ? 'Ubah Tahapan Seleksi' : 'Tambah Tahapan Baru'; ?></h3>
                
                <form action="master_tahapan_seleksi.php" method="POST">
                    <input type="hidden" name="id" value="<?= $data_tahapan_edit['id'] ?? ''; ?>">

                    <div class="form-group">
                        <label>NAMA TAHAPAN</label>
                        <input type="text" name="nama_tahapan" class="form-control" value="<?= $data_tahapan_edit['nama_tahapan'] ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>DESKRIPSI TAHAPAN</label>
                        <textarea name="deskripsi" class="form-control"><?= $data_tahapan_edit['deskripsi'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>STATUS</label>
                        <select name="status" class="form-control">
                            <option value="Aktif" <?= (isset($data_tahapan_edit) && $data_tahapan_edit['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="Nonaktif" <?= (isset($data_tahapan_edit) && $data_tahapan_edit['status'] == 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:10px; justify-content: flex-end; margin-top:20px;">
                        <a href="master_tahapan_seleksi.php" class="form-control" style="width:100px; text-align:center; text-decoration:none; color:#475569; background:#f1f5f9; display: flex; align-items: center; justify-content: center;">Batal</a>
                        <button type="submit" class="btn-purple" style="padding:10px 20px; border-radius:8px;">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
    // Menghubungkan tombol tambah agar memicu parameter modal terbuka
    const btnTambah = document.querySelector('.btn-purple');
    if (btnTambah) {
        btnTambah.addEventListener('click', function() {
            window.location.href = 'master_tahapan_seleksi.php?tambah_baru=1';
        });
    }
</script>

</body>
</html>
