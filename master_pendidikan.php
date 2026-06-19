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

// 2. FITUR PENCARIAN DATA PENDIDIKAN
$keyword = "";
if (isset($_POST['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_POST['keyword']);
    $query = "SELECT id, nama_pendidikan FROM mst_pendidikan 
              WHERE nama_pendidikan LIKE '%$keyword%' 
              ORDER BY id DESC";
} else {
    $query = "SELECT id, nama_pendidikan FROM mst_pendidikan ORDER BY id DESC";
}
$hasil = mysqli_query($koneksi, $query);

// 3. FITUR HAPUS DATA PENDIDIKAN
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $query_hapus = "DELETE FROM mst_pendidikan WHERE id = '$id_hapus'";
    if (mysqli_query($koneksi, $query_hapus)) {
        echo "<script>alert('Data kualifikasi pendidikan berhasil dihapus!'); window.location='master_pendidikan.php';</script>";
    }
}

// 4. FITUR UPDATE / EDIT DATA PENDIDIKAN (MODAL SIMPAN)
if (isset($_POST['update_pendidikan'])) {
    $id_edit   = mysqli_real_escape_string($koneksi, $_POST['id_pendidikan']);
    $nama_edit = mysqli_real_escape_string($koneksi, $_POST['nama_pendidikan']);
    
    $query_update = "UPDATE mst_pendidikan SET nama_pendidikan='$nama_edit' WHERE id='$id_edit'";
    if (mysqli_query($koneksi, $query_update)) {
        echo "<script>alert('Data kualifikasi pendidikan berhasil diperbarui!'); window.location='master_pendidikan.php';</script>";
    }
}

// 5. FITUR TAMBAH DATA PENDIDIKAN BARU (MODAL TAMBAH)
if (isset($_POST['simpan_pendidikan_baru'])) {
    $nama_baru  = mysqli_real_escape_string($koneksi, $_POST['nama_baru']);
    
    $query_tambah = "INSERT INTO mst_pendidikan (nama_pendidikan) VALUES ('$nama_baru')";
    if (mysqli_query($koneksi, $query_tambah)) {
        echo "<script>alert('Kualifikasi pendidikan baru berhasil ditambahkan!'); window.location='master_pendidikan.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Pendidikan - Magang ID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        .support-card { background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center; margin-top: 20px; }
        .support-card a { display: block; width: 100%; background: #dc2626; color: white; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); }
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 24px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #212529; }
        .content-header p { font-size: 14px; color: #6c757d; margin-top: 4px; }
        .control-bar { display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-top: 10px; }
        .search-box { display: flex; gap: 8px; flex: 1; max-width: 450px; }
        .input-search { width: 100%; padding: 10px 16px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; color: #495057; outline: none; background: #ffffff; }
        .btn-search { background: #3182ce; color: white; border: none; padding: 0 24px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .action-right { display: flex; gap: 10px; }
        .btn-add { background: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .table-wrapper { background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; color: #212529; padding: 16px 12px; font-weight: 700; border-bottom: 2px solid #dee2e6; text-align: center; }
        td { padding: 16px 12px; color: #495057; border-bottom: 1px solid #dee2e6; vertical-align: middle; text-align: center; }
        .action-container { display: flex; gap: 6px; justify-content: center; }
        .btn-action { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background: #00a896; }   
        .btn-delete { background: #e74c3c; } 
        .table-input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; color: #1e293b; background: #ffffff; outline: none; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- SIDEBAR MENU KIRI -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item" style="text-decoration: none;">Dashboard</a>
                    <a href="master_user.php" class="menu-item" style="text-decoration: none;">Master User</a>
                    <a href="master_unit.php" class="menu-item" style="text-decoration: none;">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item" style="text-decoration: none;">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item active" style="text-decoration: none;">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
                    <a href="user.php" class="menu-item" style="text-decoration: none;">Profil Pengguna</a>
                </nav>
            </div>
            <div class="support-card">
<a href="logout_admin.php" class="btn-logout" style="display: block; width: 100%; background: #dc2626; color: white; text-decoration: none; text-align: center; font-weight: 700; font-size: 14px; padding: 14px 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); transition: background 0.2s; margin-top: auto;">Sign Out / Logout</a>
        </aside>

        <!-- AREA KONTEN UTAMA -->
        <main class="main-content">
            <div class="content-header">
                <h1>DATA PENDIDIKAN</h1>
                <p>Pengelolaan Kualifikasi Nama Pendidikan Pelamar Magang</p>
            </div>

            <!-- BARIS PENCARIAN & TOMBOL UTAMA -->
            <div class="control-bar">
                <form action="" method="POST" class="search-box">
                    <input type="text" name="keyword" class="input-search" placeholder="Cari Nama pendidikan..." value="<?php echo htmlspecialchars($keyword); ?>">
                    <button type="submit" name="cari" class="btn-search">Cari</button>
                </form>
                
                <div class="action-right">
                    <button type="button" class="btn-add" onclick="bukaModalTambah()">+ Tambah Pendidikan</button>
                </div>
            </div>

            <!-- TABEL DATA MASTER PENDIDIKAN -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">No</th>
                            <th style="width: 65%;">Nama Pendidikan</th>
                            <th style="width: 35%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($hasil) > 0) {
                            $no = 1;
                            while($row = mysqli_fetch_assoc($hasil)) {
                                echo "<tr>";
                                echo "<td style='font-weight: 600; color: #64748b;'>".$no++."</td>";
                                echo "<td style='text-align: center; font-weight: 700; color: #1e293b;'>".$row['nama_pendidikan']."</td>";
                                echo "<td>
                                        <div class='action-container'>
                                            <button type='button' class='btn-action btn-edit' onclick=\"bukaModalEdit('".$row['id']."', '".addslashes($row['nama_pendidikan'])."')\" title='Edit Data'>✏️</button>
                                            <a href='master_pendidikan.php?hapus=".$row['id']."' class='btn-action btn-delete' onclick=\"return confirm('Apakah Anda yakin ingin menghapus Nama ".$row['nama_pendidikan']."?')\" title='Hapus'>🗑️</a>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='padding: 30px; color: #94a3b8;'>Tidak ada data kualifikasi pendidikan yang ditemukan. silakan tambah data baru.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ================= MODAL BOX: TAMBAH PENDIDIKAN ================= -->
    <div id="modalTambah" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 999;">
        <div style="background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 450px; display: flex; flex-direction: column; gap: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <h3 style="color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:10px; font-weight:700;">Tambah Nama Pendidikan</h3>
            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8;"> NAMA PENDIDIKAN</label>
                    <input type="text" name="nama_baru" placeholder="Contoh: S1 Keperawatan / D3 Kebidanan" class="table-input" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px;" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button type="button" onclick="tutupModalTambah()" style="background:#cbd5e1; color:#475569; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Batal</button>
                    <button type="submit" name="simpan_pendidikan_baru" style="background:#2ecc71; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:700;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL BOX: EDIT PENDIDIKAN ================= -->
    <div id="modalEdit" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 999;">
        <div style="background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; gap: 15px;">
            <h3 style="color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:10px; font-weight:700;">Ubah Data Pendidikan</h3>
            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" id="edit_id" name="id_pendidikan">
                
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8;">NAMA PENDIDIKAN</label>
                    <input type="text" id="edit_nama" name="nama_pendidikan" placeholder=" NAMA Pendidikan" class="table-input" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px;" required>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button type="button" onclick="tutupModalEdit()" style="background:#cbd5e1; color:#475569; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Batal</button>
                    <button type="submit" name="update_pendidikan" style="background:#3182ce; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:700;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPT CONTROL MODAL POP-UP -->
    <script>
        function bukaModalTambah() { document.getElementById('modalTambah').style.display = 'flex'; }
        function tutupModalTambah() { document.getElementById('modalTambah').style.display = 'none'; }
        
        function bukaModalEdit(id, nama) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('modalEdit').style.display = 'flex';
        }
        function tutupModalEdit() { document.getElementById('modalEdit').style.display = 'none'; }
    </script>
</body>
</html>
