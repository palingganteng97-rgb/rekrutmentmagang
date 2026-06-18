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

// =========================================================================
// FITUR: LOGIKA PROSES HAPUS DATA LAMARAN
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'hapus_lamaran') {
    $lamaran_id_hapus = intval($_GET['lamaran_id']);
    
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");
    mysqli_query($koneksi, "DELETE FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id_hapus");
    mysqli_query($koneksi, "DELETE FROM rekrutmen_lamaran WHERE id = $lamaran_id_hapus");
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");
    
    header("Location: data_pelamar.php");
    exit();
}

// =========================================================================
// LOGIKA PROSES UPDATE STATUS SELEKSI OLEH ADMIN
// =========================================================================
if (isset($_POST['update_status_seleksi'])) {
    $lamaran_id = intval($_POST['lamaran_id']);
    $status_aksi = $_POST['status_aksi'];
    
    if ($status_aksi === 'Pending') {
        $q_status = "DELETE FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id";
    } else {
        $status_baru = mysqli_real_escape_string($koneksi, $status_aksi);
        $cek_tahapan = mysqli_query($koneksi, "SELECT id FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id");
        if (mysqli_num_rows($cek_tahapan) > 0) {
            $q_status = "UPDATE lamaran_tahapan SET status = '$status_baru', updated_at = NOW(), petugas_id = 1 WHERE lamaran_id = $lamaran_id";
        } else {
            $q_status = "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, petugas_id, created_at, updated_at) 
                         VALUES ($lamaran_id, 1, NOW(), '$status_baru', 1, NOW(), NOW())";
        }
    }
    
    mysqli_query($koneksi, $q_status);
    header("Location: data_pelamar.php");
    exit();
}

// 2. AMBIL DATA USER ADMIN SECARA DINAMIS BERDASARKAN SESSION LOGIN
$nama_tampilan = "Administrator";
if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    $query_user = "SELECT nama FROM users WHERE username = '$username_aktif'";
    $hasil_user = mysqli_query($koneksi, $query_user);
    if ($hasil_user && mysqli_num_rows($hasil_user) > 0) {
        $data_user = mysqli_fetch_assoc($hasil_user);
        $nama_tampilan = $data_user['nama'];
    }
}

// =========================================================================
// 3. QUERY UTAMA ADMIN SINKRON DATA PELAMAR, PENDIDIKAN, & PENGALAMAN
// =========================================================================
try {
    $query_pelamar = "SELECT 
                rl.id AS lamaran_id, p.id AS pelamar_id,
                p.nama_lengkap AS nama_pendaftar, p.nik AS nik_pendaftar, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto AS foto_pelamar,
                low.nama_lowongan AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap,
                pd.jenjang, pd.institusi, pd.jurusan, pd.ipk,
                px.perusahaan, px.jabatan, px.mulai_kerja, px.selesai_kerja, px.alasan_keluar
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              INNER JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              LEFT JOIN pelamar_pendidikan pd ON pd.pelamar_id = p.id
              LEFT JOIN pelamar_pengalaman px ON px.pelamar_id = p.id
              ORDER BY rl.id DESC";

    $result_pelamar = mysqli_query($koneksi, $query_pelamar);
    if (!$result_pelamar) { throw new Exception("Query utama gagal."); }
} catch (Exception $e) {
    $query_backup = "SELECT 
                rl.id AS lamaran_id, p.id AS pelamar_id,
                p.nama_lengkap AS nama_pendaftar, p.nik AS nik_pendaftar, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto AS foto_pelamar,
                'DOKTER UMUM' AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap,
                pd.jenjang, pd.institusi, pd.jurusan, pd.ipk,
                px.perusahaan, px.jabatan, px.mulai_kerja, px.selesai_kerja, px.alasan_keluar
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              LEFT JOIN pelamar_pendidikan pd ON pd.pelamar_id = p.id
              LEFT JOIN pelamar_pengalaman px ON px.pelamar_id = p.id
              ORDER BY rl.id DESC";
    $result_pelamar = mysqli_query($koneksi, $query_backup);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelamar Kerja - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        .content-header p { font-size: 14px; color: #94a3b8; margin-top: 4px; }
        
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 12px; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-terima { background: #dcfce7; color: #15803d; }
        .status-tolak { background: #fee2e2; color: #b91c1c; }
        
        .btn-detail { background-color: #f1f5f9; color: #475569; border: none; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-detail:hover { background-color: #e2e8f0; color: #1e293b; }
        .text-danger { color: #ef4444; font-weight: 600; text-decoration: none; font-size: 13px; }
        .text-danger:hover { text-decoration: underline; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
        .modal-box { background: white; border-radius: 24px; padding: 35px; max-width: 750px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); position: relative; }
        .modal-title { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
        .detail-section-title { font-size: 13px; font-weight: 700; color: #4f46e5; margin-bottom: 12px; margin-top: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .profile-layout { display: flex; gap: 24px; margin-bottom: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
        .photo-wrapper { width: 120px; text-align: center; flex-shrink: 0; }
        .photo-wrapper img { width: 100%; height: auto; border-radius: 12px; border: 2px solid #cbd5e1; object-fit: cover; }
        
        .info-table-modal { width: 100%; font-size: 14px; line-height: 1.6; border-collapse: collapse; }
        .info-table-modal td { padding: 4px 0; color: #475569; vertical-align: top; }
        .btn-modal-close { background-color: #cbd5e1; color: #334155; border: none; padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; }
        .btn-modal-save { background-color: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- SIDEBAR MENU KIRI DENGAN CELAH & TOMBOL LOG OUT MERAH PRESISI -->
        <aside class="sidebar-left" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100vh; padding: 35px; background: #ffffff; border-right: 1px solid #f1f5f9; flex-shrink: 0; width: 280px;">
            
            <!-- GRUP ATAS: Navigasi Utama sampai Lowongan Tahapan -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div class="brand-logo" style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px;"><span style="width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block;"></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>                    
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>

                </nav>
            </div>

            <!-- GRUP BAWAH: Menyisakan Celah Kosong di Tengah, Memuat Profil & Tombol Log Out Merah -->
            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 20px; padding-top: 40px;">
                <nav class="menu-list">
                </nav>               
                <!-- TOMBOL LOG OUT DENGAN STYLE KOTAK MERAH ABSOLUT -->
                <a href="logout.php" style="display: block; width: 100%; padding: 14px; background: #ef4444; color: #ffffff !important; text-align: center; border-radius: 16px; font-weight: 700; font-size: 14px; text-decoration: none; border: none; transition: background 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?')">Log Out</a>
            </div>            
        </aside>

    <!-- MAIN KONTEN KANAN -->
    <div class="main-content">
        <div class="content-header">
            <h1>Data Pelamar Kerja</h1>
            <p>Halo, <?php echo htmlspecialchars($nama_tampilan); ?> • Kelola berkas pelamar baru masuk</p>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 250px;">Nama Pelamar</th>
                        <th>Formasi Lowongan</th>
                        <th>Tanggal Masuk</th>
                        <th>Tahap Seleksi</th>
                        <th style="text-align: center; width: 200px;">Aksi Kontrol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_pelamar && mysqli_num_rows($result_pelamar) > 0) : ?>
                        <?php while ($row = mysqli_fetch_assoc($result_pelamar)) : ?>
                            <?php 
                                $status_badge = !empty($row['status_tahap']) ? $row['status_tahap'] : 'Pending'; 
                                $class_badge = 'status-pending';
                                if ($status_badge == 'Terima') $class_badge = 'status-terima';
                                if ($status_badge == 'Tolak') $class_badge = 'status-tolak';
                            ?>
                            <tr>
                                <td>
                                    <div class="candidate-name"><?php echo htmlspecialchars($row['nama_pendaftar']); ?></div>
                                    <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">NIK: <?php echo htmlspecialchars($row['nik_pendaftar']); ?></div>
                                </td>
                                <td><span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nama_lowongan']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($row['tanggal_daftar'])); ?></td>
                                <td><span class="status-pill <?php echo $class_badge; ?>">• <?php echo htmlspecialchars($status_badge); ?></span></td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-detail" onclick="bukaDetailModal(
                                        '<?php echo $row['lamaran_id']; ?>', 
                                        '<?php echo $row['nama_pendaftar']; ?>', 
                                        '<?php echo $row['nik_pendaftar']; ?>', 
                                        '<?php echo $row['foto_pelamar']; ?>', 
                                        '<?php echo $row['tempat_lahir'].', '.date('d/m/Y', strtotime($row['tanggal_lahir'])); ?>', 
                                        '<?php echo $row['jenis_kelamin']; ?>', 
                                        '<?php echo $row['agama']; ?>', 
                                        '<?php echo $row['status_sosial']; ?>', 
                                        '<?php echo $row['email']; ?>', 
                                        '<?php echo $row['telepon']; ?>', 
                                        '<?php echo $row['kota'].', '.$row['provinsi']; ?>', 
                                        '<?php echo $row['alamat']; ?>', 
                                        '<?php echo $row['institusi']; ?>', 
                                        '<?php echo $row['jurusan']; ?>', 
                                        '<?php echo $row['ipk']; ?>', 
                                        '<?php echo $status_badge; ?>',
                                        '<?php echo isset($row['perusahaan']) ? htmlspecialchars($row['perusahaan']) : ''; ?>',
                                        '<?php echo isset($row['jabatan']) ? htmlspecialchars($row['jabatan']) : ''; ?>',
                                        '<?php echo isset($row['mulai_kerja']) ? $row['mulai_kerja'] : ''; ?>',
                                        '<?php echo isset($row['selesai_kerja']) ? $row['selesai_kerja'] : ''; ?>',
                                        '<?php echo isset($row['alasan_keluar']) ? htmlspecialchars($row['alasan_keluar']) : ''; ?>'
                                    )">Lihat Detail</button>
                                    <a href="?action=hapus_lamaran&lamaran_id=<?php echo $row['lamaran_id']; ?>" class="text-danger" style="margin-left: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus data lamaran pendaftar ini?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; font-style: italic; padding: 40px 0;">Belum ada berkas pendaftaran pelamar yang masuk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODAL OVERLAY POP-UP DETAIL PELAMAR ==================== -->
<div id="detailModal" class="modal-overlay">
    <div class="modal-box">
        <h3 class="modal-title">Informasi Lengkap Berkas Pelamar</h3>

        <h5 class="detail-section-title">A. Biodata Profil Pelamar</h5>
        <div class="profile-layout">
            <div class="photo-wrapper">
                <img id="modalFoto" src="" alt="Foto Profil">
                <div id="modalNoFoto" style="display:none; width:100%; height:140px; background:#cbd5e1; border-radius:8px; align-items:center; justify-content:center; font-size:12px; color:#475569;">No Photo</div>
            </div>
            <div style="flex-grow: 1;">
                <table class="info-table-modal">
                    <tr><td style="width: 150px; color: #94a3b8;">Nama Lengkap</td><td style="width: 15px;">:</td><td><strong id="md_nama" style="color:#1e293b;">-</strong></td></tr>
                    <tr><td style="color: #94a3b8;">NIK</td><td>:</td><td id="md_nik">-</td></tr>
                    <tr><td style="color: #94a3b8;">Tempat, Tgl Lahir</td><td>:</td><td id="md_ttl">-</td></tr>
                    <tr><td style="color: #94a3b8;">Jenis Kelamin</td><td>:</td><td id="md_jk">-</td></tr>
                    <tr><td style="color: #94a3b8;">Agama</td><td>:</td><td id="md_agama">-</td></tr>
                    <tr><td style="color: #94a3b8;">Status Hubungan</td><td>:</td><td id="md_status">-</td></tr>
                    <tr><td style="color: #94a3b8;">No. Telepon / WA</td><td>:</td><td id="md_telepon">-</td></tr>
                    <tr><td style="color: #94a3b8;">Email</td><td>:</td><td id="md_email">-</td></tr>
                    <tr><td style="color: #94a3b8;">Kota / Provinsi</td><td>:</td><td id="md_lokasi">-</td></tr>
                    <tr><td style="color: #94a3b8;">Alamat Rumah</td><td>:</td><td id="md_alamat">-</td></tr>
                </table>
            </div>
        </div>

        <h5 class="detail-section-title">B. Riwayat Pendidikan</h5>
        <div class="data-box-modal" style="margin-bottom: 15px;">
            <strong>Kampus / Sekolah :</strong> <span id="md_kampus">-</span><br>
            <strong>Jurusan / Prodi :</strong> <span id="md_prodi">-</span><br>
            <strong>IPK / Nilai Akhir :</strong> <span id="md_nilai">-</span>
        </div>

        <h5 class="detail-section-title" style="color: #4f46e5;">C. Riwayat Pengalaman Kerja</h5>
        <div class="data-box-modal" style="margin-bottom: 20px;">
            <table class="info-table-modal">
                <tr><td style="width: 140px; color: #94a3b8;">Nama Perusahaan</td><td style="width: 15px;">:</td><td><strong id="md_perusahaan" style="color: #1e293b;">-</strong></td></tr>
                <tr><td style="color: #94a3b8;">Jabatan / Posisi</td><td>:</td><td id="md_jabatan">-</td></tr>
                <tr><td style="color: #94a3b8;">Periode Kerja</td><td>:</td><td id="md_periode">-</td></tr>
                <tr><td style="color: #94a3b8;">Alasan Keluar</td><td>:</td><td id="md_alasan_keluar">-</td></tr>
            </table>
        </div>

                <!-- FORM UPDATE STATUS SELEKSI ADMIN (PERBAIKAN VISUAL DROPDOWN) -->
        <form action="" method="POST">
            <input type="hidden" name="lamaran_id" id="formLamaranId">
            <div style="margin-top: 15px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#94a3b8; margin-bottom:8px; text-transform:uppercase;">UBAH TAHAP SELEKSI PELAMAR:</label>
                <select name="status_aksi" id="formStatusAksi" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:12px; font-size:14px; font-weight:600; color:#475569; background-color: #fff;">
                    <option value="Pending">🟡 Pending / Seleksi Berkas</option>
                    <option value="Terima">🟢 Terima / Lolos Kualifikasi</option>
                    <option value="Tolak">🔴 Tolak Lamaran</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal-close" onclick="tutupDetailPelamar()">Batal</button>
                <button type="submit" name="update_status_seleksi" class="btn-modal-save">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== LOGIKA JAVASCRIPT SUNTIK MODAL ==================== -->
<script>
function bukaDetailModal(id, nama, nik, foto, ttl, jk, agama, status, email, telepon, lokasi, alamat, kampus, prodi, nilai, statusAksi, perusahaan, jabatan, mulai, selesai, alasan) {
    document.getElementById('md_nama').innerText = nama ? nama : '-';
    document.getElementById('md_nik').innerText = nik ? nik : '-';
    document.getElementById('md_ttl').innerText = ttl ? ttl : '-';
    document.getElementById('md_jk').innerText = jk ? jk : '-';
    document.getElementById('md_agama').innerText = agama ? agama : '-';
    document.getElementById('md_status').innerText = status ? status : '-';
    document.getElementById('md_telepon').innerText = telepon ? telepon : '-';
    document.getElementById('md_email').innerText = email ? email : '-';
    document.getElementById('md_lokasi').innerText = lokasi ? lokasi : '-';
    document.getElementById('md_alamat').innerText = alamat ? alamat : '-';
    
    document.getElementById('md_kampus').innerText = kampus ? kampus : '-';
    document.getElementById('md_prodi').innerText = prodi ? prodi : '-';
    document.getElementById('md_nilai').innerText = nilai ? nilai : '-';

    // SUNTIK DATA RIWAYAT PENGALAMAN KERJA ASLI PELAMAR
    document.getElementById('md_perusahaan').innerText = perusahaan ? perusahaan : 'Tidak Ada Pengalaman';
    document.getElementById('md_jabatan').innerText = jabatan ? jabatan : '-';
    
    if (mulai) {
        let tgl_selesai = selesai ? selesai.split('-').reverse().join('/') : 'Sekarang';
        let tgl_mulai = mulai.split('-').reverse().join('/');
        document.getElementById('md_periode').innerText = tgl_mulai + ' s/d ' + tgl_selesai;
    } else { 
        document.getElementById('md_periode').innerText = '-'; 
    }
    document.getElementById('md_alasan_keluar').innerText = alasan ? alasan : '-';

    const imgObj = document.getElementById('modalFoto');
    const noImgObj = document.getElementById('modalNoFoto');
    if (foto && foto !== '') {
        imgObj.src = 'uploads/' + foto;
        imgObj.style.display = 'inline-block';
        noImgObj.style.display = 'none';
    } else {
        imgObj.style.display = 'none';
        noImgObj.style.display = 'flex';
    }

    document.getElementById('formLamaranId').value = id;
    document.getElementById('formStatusAksi').value = statusAksi;
    document.getElementById('detailModal').style.display = 'flex';
}

function tutupDetailPelamar() {
    document.getElementById('detailModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('detailModal');
    if (event.target === modal) { modal.style.display = 'none'; }
}
</script>
</body>
</html>
