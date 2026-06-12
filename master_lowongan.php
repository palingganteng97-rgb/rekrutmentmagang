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

// 2. FITUR PENCARIAN DATA LOWONGAN MAGANG
$keyword = "";
if (isset($_POST['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_POST['keyword']);
    $query = "SELECT * FROM lowongan_tahapan 
              WHERE kode_lowongan LIKE '%$keyword%' OR judul_lowongan LIKE '%$keyword%' 
              ORDER BY id DESC";
} else {
    $query = "SELECT * FROM lowongan_tahapan ORDER BY id DESC";
}
$hasil = mysqli_query($koneksi, $query);

// 3. FITUR HAPUS LOWONGAN MAGANG
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $query_hapus = "DELETE FROM lowongan_tahapan WHERE id = '$id_hapus'";
    if (mysqli_query($koneksi, $query_hapus)) {
        echo "<script>alert('Data lowongan magang berhasil dihapus!'); window.location='master_lowongan.php';</script>";
    }
}

// 4. FITUR TAMBAH DATA LOWONGAN MAGANG BARU
if (isset($_POST['simpan_lowongan'])) {
    $kode_lowongan   = mysqli_real_escape_string($koneksi, $_POST['kode_lowongan']);
    $jabatan_id      = mysqli_real_escape_string($koneksi, $_POST['jabatan_id']);
    $unit_id         = mysqli_real_escape_string($koneksi, $_POST['unit_id']);
    $judul_lowongan  = mysqli_real_escape_string($koneksi, $_POST['judul_lowongan']);
    $jumlah_kebutuhan= mysqli_real_escape_string($koneksi, $_POST['jumlah_kebutuhan']);
    $deskripsi       = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $kualifikasi     = mysqli_real_escape_string($koneksi, $_POST['kualifikasi']);
    $persyaratan     = mysqli_real_escape_string($koneksi, $_POST['persyaratan']);
    $tanggal_mulai   = mysqli_real_escape_string($koneksi, $_POST['tanggal_mulai']);
    $tanggal_selesai = mysqli_real_escape_string($koneksi, $_POST['tanggal_selesai']);
    $status          = mysqli_real_escape_string($koneksi, $_POST['status']);
    
    $gambar = "default.png"; 
    $created_by = "1";

    $query_tambah = "INSERT INTO lowongan_tahapan (kode_lowongan, jabatan_id, unit_id, judul_lowongan, jumlah_kebutuhan, deskripsi, kualifikasi, persyaratan, tanggal_mulai, tanggal_selesai, status, gambar, created_by, created_at) 
                     VALUES ('$kode_lowongan', '$jabatan_id', '$unit_id', '$judul_lowongan', '$jumlah_kebutuhan', '$deskripsi', '$kualifikasi', '$persyaratan', '$tanggal_mulai', '$tanggal_selesai', '$status', '$gambar', '$created_by', NOW())";
    
    if (mysqli_query($koneksi, $query_tambah)) {
        echo "<script>alert('Lowongan magang baru berhasil diterbitkan!'); window.location='master_lowongan.php';</script>";
    }
}

// 5. FITUR UPDATE / EDIT LOWONGAN MAGANG
if (isset($_POST['update_lowongan'])) {
    $id_edit         = mysqli_real_escape_string($koneksi, $_POST['id_lowongan']);
    $kode_lowongan   = mysqli_real_escape_string($koneksi, $_POST['kode_lowongan']);
    $jabatan_id      = mysqli_real_escape_string($koneksi, $_POST['jabatan_id']);
    $unit_id         = mysqli_real_escape_string($koneksi, $_POST['unit_id']);
    $judul_lowongan  = mysqli_real_escape_string($koneksi, $_POST['judul_lowongan']);
    $jumlah_kebutuhan= mysqli_real_escape_string($koneksi, $_POST['jumlah_kebutuhan']);
    $deskripsi       = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $kualifikasi     = mysqli_real_escape_string($koneksi, $_POST['kualifikasi']);
    $persyaratan     = mysqli_real_escape_string($koneksi, $_POST['persyaratan']);
    $tanggal_mulai   = mysqli_real_escape_string($koneksi, $_POST['tanggal_mulai']);
    $tanggal_selesai = mysqli_real_escape_string($koneksi, $_POST['tanggal_selesai']);
    $status          = mysqli_real_escape_string($koneksi, $_POST['status']);

    $query_update = "UPDATE lowongan_tahapan SET kode_lowongan='$kode_lowongan', jabatan_id='$jabatan_id', unit_id='$unit_id', judul_lowongan='$judul_lowongan', jumlah_kebutuhan='$jumlah_kebutuhan', deskripsi='$deskripsi', kualifikasi='$kualifikasi', persyaratan='$persyaratan', tanggal_mulai='$tanggal_mulai', tanggal_selesai='$tanggal_selesai', status='$status', updated_at=NOW() WHERE id='$id_edit'";
    
    if (mysqli_query($koneksi, $query_update)) {
        echo "<script>alert('Data lowongan magang berhasil diperbarui!'); window.location='master_lowongan.php';</script>";
    }
}

$opt_unit = mysqli_query($koneksi, "SELECT id, nama_unit FROM mst_unit ORDER BY nama_unit ASC");
$opt_jbt  = mysqli_query($koneksi, "SELECT id, nama_jabatan FROM mst_jabatan ORDER BY nama_jabatan ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Lowongan Magang - Magang ID</title>
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
        .control-bar { display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-top: 10px; }
        .search-box { display: flex; gap: 8px; flex: 1; max-width: 450px; }
        .input-search { width: 100%; padding: 10px 16px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; outline: none; }
        .btn-search { background: #3182ce; color: white; border: none; padding: 0 24px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .btn-add { background: #2ecc71; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .table-wrapper { background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; color: #212529; padding: 16px 12px; font-weight: 700; border-bottom: 2px solid #dee2e6; text-align: center; }
        td { padding: 16px 12px; color: #495057; border-bottom: 1px solid #dee2e6; text-align: center; vertical-align: middle; }
        .action-container { display: flex; gap: 6px; justify-content: center; }
        .btn-action { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: white; }
        .btn-edit { background: #00a896; }   
        .btn-delete { background: #e74c3c; } 
        .table-input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; color: #1e293b; background: #ffffff; outline: none; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 999; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; text-align: left; }
        .form-group-full { grid-column: span 2; display: flex; flex-direction: column; gap: 4px; }
        .form-group-half { display: flex; flex-direction: column; gap: 4px; }
        label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
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
                    <a href="master_pendidikan.php" class="menu-item" style="text-decoration: none;">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item active" style="text-decoration: none;">Master Lowongan</a>
                    <a href="user.php" class="menu-item" style="text-decoration: none;">Profil Pengguna</a>
                </nav>
            </div>
            <div class="support-card" style="background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center; margin-top: 20px;">
                <a href="logout.php" style="display: block; width: 100%; background: #dc2626; color: white; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);">Log Out</a>
            </div>
        </aside>

        <!-- AREA KONTEN UTAMA -->
        <main class="main-content">
            <div class="content-header">
                <h1>DATA LOWONGAN MAGANG</h1>
            </div>

            <div class="control-bar">
                <form action="" method="POST" class="search-box">
                    <input type="text" name="keyword" class="input-search" placeholder="Cari kode atau judul lowongan magang..." value="<?php echo htmlspecialchars($keyword); ?>">
                    <button type="submit" name="cari" class="btn-search">Cari</button>
                </form>
                <button type="button" class="btn-add" onclick="bukaModalTambah()">+ Terbitkan Lowongan</button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Kode</th>
                            <th>Judul Lowongan Magang</th>
                            <th>ID Unit</th>
                            <th>ID Jabatan</th>
                            <th>Kebutuhan</th>
                            <th>Batas Waktu</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($hasil && mysqli_num_rows($hasil) > 0) {
                            $no = 1;
                            while($row = mysqli_fetch_assoc($hasil)) {
                                $tgl_selesai = !empty($row['tanggal_selesai']) ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-';
                                echo "<tr>";
                                echo "<td style='font-weight: 600; color: #64748b;'>".$no++."</td>";
                                echo "<td style='font-weight: 700; color: #4f46e5;'>".$row['kode_lowongan']."</td>";
                                echo "<td style='text-align: center; font-weight: 700; color: #1e293b;'>".$row['judul_lowongan']."</td>";
                                echo "<td>Unit ID: ".$row['unit_id']."</td>";
                                echo "<td>Jabatan ID: ".$row['jabatan_id']."</td>";
                                echo "<td style='font-weight: 700;'>".$row['jumlah_kebutuhan']." Orang</td>";
                                echo "<td style='color: #64748b;'>".$tgl_selesai."</td>";
                                echo "<td><span style='color: " . ($row['status'] == 'Aktif' ? '#059669' : '#e74c3c') . "; font-weight: 700;'>".$row['status']."</span></td>";
                                echo "<td>
                                        <div class='action-container'>
                                            <button type='button' class='btn-action btn-edit' onclick=\"bukaModalEdit('".$row['id']."', '".$row['kode_lowongan']."', '".$row['jabatan_id']."', '".$row['unit_id']."', '".addslashes($row['judul_lowongan'])."', '".$row['jumlah_kebutuhan']."', '".addslashes($row['deskripsi'])."', '".addslashes($row['kualifikasi'])."', '".addslashes($row['persyaratan'])."', '".$row['tanggal_mulai']."', '".$row['tanggal_selesai']."', '".$row['status']."')\">✏️</button>
                                            <a href='master_lowongan.php?hapus=".$row['id']."' class='btn-action btn-delete' onclick=\"return confirm('Hapus lowongan magang ".$row['judul_lowongan']."?')\" style='text-decoration:none;'>🗑️</a>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='padding: 30px; color: #94a3b8;'>Belum ada data lowongan magang aktif.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- MODAL BOX: FORM TAMBAH -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <h3 style="color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:10px; font-weight:700;">Form Terbitkan Lowongan Magang</h3>
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group-half"><label>Kode Lowongan</label><input type="text" name="kode_lowongan" class="table-input" placeholder="Contoh: LWG-001" required></div>
                    <div class="form-group-half"><label>Judul Lowongan Magang</label><input type="text" name="judul_lowongan" class="table-input" placeholder="Contoh: Magang Perawat" required></div>
                    <div class="form-group-half"><label>Unit Kerja</label><select name="unit_id" class="table-input" required><option value="">-- Pilih Unit --</option><?php if($opt_unit){ mysqli_data_seek($opt_unit,0); while($u=mysqli_fetch_assoc($opt_unit)){ echo "<option value='".$u['id']."'>".$u['nama_unit']."</option>"; } } ?></select></div>
                    <div class="form-group-half"><label>Formasi Jabatan</label><select name="jabatan_id" class="table-input" required><option value="">-- Pilih Jabatan --</option><?php if($opt_jbt){ mysqli_data_seek($opt_jbt,0); while($j=mysqli_fetch_assoc($opt_jbt)){ echo "<option value='".$j['id']."'>".$j['nama_jabatan']."</option>"; } } ?></select></div>
                    <div class="form-group-half"><label>Jumlah Kebutuhan (Orang)</label><input type="number" name="jumlah_kebutuhan" class="table-input" value="1" min="1" required></div>
                    <div class="form-group-half"><label>Status Publikasi</label><select name="status" class="table-input"><option value="Draft">Draft</option><option value="Aktif">Aktif</option></select></div>
                    <div class="form-group-half"><label>Tanggal Mulai</label><input type="date" name="tanggal_mulai" class="table-input" required></div>
                    <div class="form-group-half"><label>Tanggal Selesai / Batas</label><input type="date" name="tanggal_selesai" class="table-input" required></div>
                    <div class="form-group-full"><label>Deskripsi Lowongan Magang</label><textarea name="deskripsi" class="table-input" style="height:80px; resize:vertical;"></textarea></div>
                    <div class="form-group-full"><label>Kualifikasi Utama</label><textarea name="kualifikasi" class="table-input" style="height:80px; resize:vertical;"></textarea></div>
                    <div class="form-group-full"><label>Berkas Persyaratan</label><textarea name="persyaratan" class="table-input" style="height:80px; resize:vertical;"></textarea></div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="tutupModalTambah()" style="background:#cbd5e1; color:#475569; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Batal</button>
                    <button type="submit" name="simpan_lowongan" style="background:#2ecc71; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:700;">Terbitkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL BOX: FORM EDIT -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <h3 style="color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:10px; font-weight:700;">Ubah Detail Lowongan Magang</h3>
            <form action="" method="POST">
                <input type="hidden" id="edit_id" name="id_lowongan">
                <div class="form-grid">
                    <div class="form-group-half"><label>Kode Lowongan</label><input type="text" id="edit_kode" name="kode_lowongan" class="table-input" required></div>
                    <div class="form-group-half"><label>Judul Lowongan Magang</label><input type="text" id="edit_judul" name="judul_lowongan" class="table-input" required></div>
                    <div class="form-group-half"><label>Unit Kerja</label><select id="edit_unit" name="unit_id" class="table-input" required><option value="">-- Pilih Unit --</option><?php if($opt_unit){ mysqli_data_seek($opt_unit,0); while($u=mysqli_fetch_assoc($opt_unit)){ echo "<option value='".$u['id']."'>".$u['nama_unit']."</option>"; } } ?></select></div>
                        <div class="form-group-half">
                            <label>Formasi Jabatan</label>
                            <select id="edit_jbt" name="jabatan_id" class="table-input" required>
                                <option value="">-- Pilih Jabatan --</option>
                                <?php if($opt_jbt){ mysqli_data_seek($opt_jbt,0); while($j=mysqli_fetch_assoc($opt_jbt)){ echo "<option value='".$j['id']."'>".$j['nama_jabatan']."</option>"; } } ?>
                            </select>
                        </div>
                        <div class="form-group-half">
                            <label>Jumlah Kebutuhan</label>
                            <input type="number" id="edit_qty" name="jumlah_kebutuhan" class="table-input" required>
                        </div>
                        <div class="form-group-half">
                            <label>Status Publikasi</label>
                            <select id="edit_status" name="status" class="table-input">
                                <option value="Draft">Draft</option>
                                <option value="Aktif">Aktif</option>
                            </select>
                        </div>
                        <div class="form-group-half">
                            <label>Tanggal Mulai</label>
                            <input type="date" id="edit_tgl_m" name="tanggal_mulai" class="table-input" required>
                        </div>
                        <div class="form-group-half">
                            <label>Tanggal Selesai</label>
                            <input type="date" id="edit_tgl_s" name="tanggal_selesai" class="table-input" required>
                        </div>
                        <div class="form-group-full">
                            <label>Deskripsi Lowongan Magang</label>
                            <textarea id="edit_desk" name="deskripsi" class="table-input" style="height:80px; resize:vertical;"></textarea>
                        </div>
                        <div class="form-group-full">
                            <label>Kualifikasi Utama</label>
                            <textarea id="edit_kual" name="kualifikasi" class="table-input" style="height:80px; resize:vertical;"></textarea>
                        </div>
                        <div class="form-group-full">
                            <label>Berkas Persyaratan</label>
                            <textarea id="edit_pers" name="persyaratan" class="table-input" style="height:80px; resize:vertical;"></textarea>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="tutupModalEdit()" style="background:#cbd5e1; color:#475569; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Batal</button>
                        <button type="submit" name="update_lowongan" style="background:#3182ce; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:700;">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SCRIPT JAVASCRIPT POP-UP CONTROL -->
        <script>
            function bukaModalTambah() { document.getElementById('modalTambah').style.display = 'flex'; }
            function tutupModalTambah() { document.getElementById('modalTambah').style.display = 'none'; }
            function bukaModalEdit(id, kode, jbt, unit, judul, qty, desk, kual, pers, tgl_m, tgl_s, status) {
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_kode').value = kode;
                document.getElementById('edit_jbt').value = jbt;
                document.getElementById('edit_unit').value = unit;
                document.getElementById('edit_judul').value = judul;
                document.getElementById('edit_qty').value = qty;
                document.getElementById('edit_desk').value = desk;
                document.getElementById('edit_kual').value = kual;
                document.getElementById('edit_pers').value = pers;
                document.getElementById('edit_tgl_m').value = tgl_m;
                document.getElementById('edit_tgl_s').value = tgl_s;
                document.getElementById('edit_status').value = status;
                document.getElementById('modalEdit').style.display = 'flex';
            }
            function tutupModalEdit() { document.getElementById('modalEdit').style.display = 'none'; }
        </script>
    </body>
</html>
