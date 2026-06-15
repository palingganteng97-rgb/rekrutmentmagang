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

// 2. PROSES TAMBAH DATA (CREATE)
if (isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $judul_lowongan   = mysqli_real_escape_string($koneksi, $_POST['judul_lowongan']);
    $jumlah_kebutuhan = intval($_POST['jumlah_kebutuhan']);
    $status           = mysqli_real_escape_string($koneksi, $_POST['status']);
    
    // Generate kode_lowongan otomatis (VARCHAR 50)
    $kode_lowongan    = "LWN-" . date("Ymd") . "-" . rand(100, 999); 

    // Insert sesuai nama kolom di HeidiSQL: kode_lowongan, judul_lowongan, jumlah_kebutuhan, status
    $query_insert = "INSERT INTO rekrutmen_lowongan (kode_lowongan, judul_lowongan, jumlah_kebutuhan, status) 
                     VALUES ('$kode_lowongan', '$judul_lowongan', '$jumlah_kebutuhan', '$status')";
    
    if (mysqli_query($koneksi, $query_insert)) {
        header("Location: master_lowongan.php");
        exit;
    }
}

// 3. PROSES UBAH DATA (UPDATE)
if (isset($_POST['action']) && $_POST['action'] == 'ubah') {
    $id               = intval($_POST['id']);
    $judul_lowongan   = mysqli_real_escape_string($koneksi, $_POST['judul_lowongan']);
    $jumlah_kebutuhan = intval($_POST['jumlah_kebutuhan']);
    $status           = mysqli_real_escape_string($koneksi, $_POST['status']);

    $query_update = "UPDATE rekrutmen_lowongan 
                     SET judul_lowongan = '$judul_lowongan', jumlah_kebutuhan = '$jumlah_kebutuhan', status = '$status' 
                     WHERE id = $id";
    
    if (mysqli_query($koneksi, $query_update)) {
        header("Location: master_lowongan.php");
        exit;
    }
}

// 4. PROSES HAPUS DATA (DELETE)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query_delete = "DELETE FROM rekrutmen_lowongan WHERE id = $id";
    
    if (mysqli_query($koneksi, $query_delete)) {
        header("Location: master_lowongan.php");
        exit;
    }
}

// 5. AMBIL DATA DARI TABEL REKRUTMEN LOWONGAN (READ)
$query_tampil = "SELECT id, kode_lowongan, judul_lowongan, jumlah_kebutuhan, status FROM rekrutmen_lowongan ORDER BY id DESC";
$ambil_data = mysqli_query($koneksi, $query_tampil);
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
        
        /* Navigasi Sidebar Kiri */
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }

        /* Area Konten Utama */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header { display: flex; justify-content: space-between; align-items: center; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        .btn-purple { background: #4f46e5; color: white; border-radius: 14px; font-weight: 700; padding: 14px 28px; border: none; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .btn-purple:hover { background: #3b33c7; }

        /* Tabel */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 20px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .row-title { font-weight: 700; color: #1e293b; font-size: 14px; }
        .badge-status { border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-aktif { background-color: #e0f2fe; color: #0369a1; }
        .status-draft { background-color: #f1f5f9; color: #475569; }
        
        .action-link { text-decoration: none; font-size: 13px; font-weight: 700; margin-left: 15px; }
        .edit-lnk { color: #4f46e5; }
        .del-lnk { color: #ef4444; }

        /* Modal */
        .modal-mask { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal-body { background: #ffffff; padding: 35px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .form-input-group { margin-bottom: 18px; }
        .form-input-group label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-style, .select-style { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; outline: none; background: #fff; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR LEFT -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item active">Master Lowongan</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
                </nav>
            </div>
            
            <div style="background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center;">
                <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 13px; font-weight: 700;">Keluar Sistem</a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1>Master Lowongan</h1>
                </div>
                <button class="btn-purple" onclick="openModal()">Tambah Lowongan</button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>KODE LOWONGAN</th>
                            <th>JUDUL LOWONGAN</th>
                            <th>KUOTA KEBUTUHAN</th>
                            <th>STATUS</th>
                            <th style="text-align: right; padding-right: 15px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($ambil_data) > 0) {
                            while ($row = mysqli_fetch_assoc($ambil_data)) {
                                $statusClass = ($row['status'] == 'Aktif') ? 'status-aktif' : 'status-draft';
                        ?>
                        <tr>
                            <td><?= $row['id']; ?></td>
                            <td><code><?= htmlspecialchars($row['kode_lowongan']); ?></code></td>
                            <td class="row-title"><?= htmlspecialchars($row['judul_lowongan']); ?></td>
                            <td><?= htmlspecialchars($row['jumlah_kebutuhan']); ?> Orang</td>
                            <td><span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                            <td style="text-align: right;">
                                <a href="#" class="action-link edit-lnk" onclick="openEditModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['judul_lowongan']); ?>', <?= $row['jumlah_kebutuhan']; ?>, '<?= $row['status']; ?>')">Edit</a>
                                <a href="master_lowongan.php?delete=<?= $row['id']; ?>" class="action-link del-lnk" onclick="return confirm('Apakah Anda yakin ingin menghapus data?')">Hapus</a>
                        </tr>
                        <?php 
                            }
                        } else {
                        ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px 0;">Belum ada data lowongan pekerjaan.</td>
                            </tr>
                        <?php 
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- DIALOG POP-UP FORM INPUT MODAL (TAMBAH & EDIT) -->
    <div class="modal-mask" id="formModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); display: none; align-items: center; justify-content: center; z-index: 9999;">
        <div class="modal-body" style="background: #ffffff; padding: 35px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <h3 id="modalTitle" style="font-weight: 800; font-size: 20px; margin-bottom: 22px; color: #1e293b;">Buat Lowongan Baru</h3>
            
            <form action="" method="POST" id="mainForm">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="jobId" value="">

                <div class="form-input-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">NAMA LOWONGAN</label>
                    <input type="text" class="input-style" id="formJudul" name="judul_lowongan" placeholder="misal: Manajer Pemasaran" required style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; outline: none;">
                </div>
                
                <div class="form-input-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">KUOTA KEBUTUHAN</label>
                    <input type="number" class="input-style" id="formKuota" name="jumlah_kebutuhan" placeholder="misal: 2" required style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; outline: none;">
                </div>

                <div class="form-input-group" style="margin-bottom: 25px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">STATUS</label>
                    <select class="input-style" id="formStatus" name="status" style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; outline: none;">
                        <option value="Aktif">Aktif</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeModal()" style="background: #edf2f7; border: none; padding: 12px 22px; border-radius: 12px; cursor: pointer; font-weight: 600; color: #718096; font-size: 14px;">Batal</button>
                    <button type="submit" class="btn-purple" style="background: #4f46e5; color: white; border-radius: 12px; font-weight: 700; padding: 12px 24px; border: none; cursor: pointer; font-size: 14px;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC INTERAKSI MODAL -->
    <script>
        function openModal() {
            document.getElementById('modalTitle').innerText = 'Buat Lowongan Baru';
            document.getElementById('formAction').value = 'tambah';
            document.getElementById('jobId').value = '';
            document.getElementById('formJudul').value = '';
            document.getElementById('formKuota').value = '';
            document.getElementById('formStatus').value = 'Aktif';
            document.getElementById('formModal').style.display = 'flex';
        }

        function openEditModal(id, judul, kuota, status) {
            document.getElementById('modalTitle').innerText = 'Ubah Data Lowongan';
            document.getElementById('formAction').value = 'ubah';
            document.getElementById('jobId').value = id;
            document.getElementById('formJudul').value = judul;
            document.getElementById('formKuota').value = kuota;
            document.getElementById('formStatus').value = status;
            document.getElementById('formModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('formModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('formModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
