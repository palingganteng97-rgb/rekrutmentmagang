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
// 3. QUERY UTAMA ADMIN (DIPERBAIKI: TANPA JOIN PENDIDIKAN & PENGALAMAN)
// =========================================================================
try {
    // Query ini dijamin bersih dan hanya menghasilkan 1 baris per lamaran pelamar
$query_pelamar = "
SELECT
    rl.id AS lamaran_id,
    p.id AS pelamar_id,

    p.nama_lengkap AS nama_pendaftar,
    p.nik AS nik_pendaftar,
    p.tempat_lahir,
    p.tanggal_lahir,
    p.jenis_kelamin,
    p.agama,
    p.alamat,
    p.kota,
    p.provinsi,
    p.no_telepon,
    p.email,
    p.status_sosial,
    p.foto AS foto_pelamar,

    low.judul_lowongan AS nama_lowongan,
    rl.created_at AS tanggal_daftar,

    lt.status AS status_tahap,

    pp.institusi,
    pp.jurusan,
    pp.ipk,

    pk.perusahaan,
    pk.jabatan,
    pk.mulai_kerja,
    pk.selesai_kerja,
    pk.alasan_keluar

FROM rekrutmen_lamaran rl

INNER JOIN pelamar p
    ON rl.pelamar_id = p.id

INNER JOIN rekrutmen_lowongan low
    ON rl.lowongan_id = low.id

LEFT JOIN lamaran_tahapan lt
    ON lt.lamaran_id = rl.id

LEFT JOIN pelamar_pendidikan pp
    ON pp.pelamar_id = p.id

LEFT JOIN pelamar_pengalaman pk
    ON pk.pelamar_id = p.id

ORDER BY rl.id DESC
";

    $result_pelamar = mysqli_query($koneksi, $query_pelamar);
    if (!$result_pelamar) {
        throw new Exception("Query utama gagal.");
    }
} catch (Exception $e) {
    $query_backup = "SELECT 
        rl.id AS lamaran_id, p.id AS pelamar_id, p.nama_lengkap AS nama_pendaftar, p.nik AS nik_pendaftar, 
        p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, 
        p.no_telepon, p.email, p.status_sosial, p.foto AS foto_pelamar, 
        'DOKTER UMUM' AS nama_lowongan, rl.created_at AS tanggal_daftar, 
        lt.status AS status_tahap
    FROM rekrutmen_lamaran rl
    INNER JOIN pelamar p ON rl.pelamar_id = p.id
    LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
    GROUP BY rl.id
    ORDER BY rl.id DESC";

    
    $result_pelamar = mysqli_query($koneksi, $query_backup);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <!-- PERBAIKAN TAUTAN IKON GOOGLE MATERIAL SYMBOLS -->
    <link rel="stylesheet" href="https://googleapis.com" />

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
        
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 30px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        .content-header p { font-size: 14px; color: #94a3b8; margin-top: 4px; }
        
        .table-wrapper {
            width: 100%;             
            overflow-x: auto;        
            -webkit-overflow-scrolling: touch; 
        }

        table {
            width: 100%;             
            min-width: 1050px;        /* Dinaikkan agar tabel memiliki ruang horizontal yang lega */
            border-collapse: collapse;
        }
        
        /* ATUR LEBAR PROPORSIONAL UNTUK KOLOM 1 SAMPAI 4 */
        table th:nth-child(1), table td:nth-child(1) { width: 28%; } 
        table th:nth-child(2), table td:nth-child(2) { width: 20%; } 
        table th:nth-child(3), table td:nth-child(3) { width: 18%; } 
        table th:nth-child(4), table td:nth-child(4) { width: 16%; } 
        
        /* PERBAIKAN UTAMA: MENGUNCI LEBAR KOLOM KE-5 (AKSI) AGAR TIDAK LEPAS */
        table th:nth-child(5), table td:nth-child(5) { 
            width: 180px !important; 
            text-align: center; 
        }

        th { color: #94a3b8; padding: 0 10px 16px 10px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        
        /* MODIFIKASI TD: Menghilangkan word-wrap pada kolom aksi agar teks tombol tidak pecah ke bawah */
        td { padding: 20px 10px; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        table td:not(:last-child) { word-wrap: break-word; }
        
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* MEMAKSA TOMBOL DI KOLOM AKSI BERJEJER SEJAJAR KE SAMPING */
        table td:nth-child(5) {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 16px;           /* Jarak ideal antar tombol */
            white-space: nowrap; /* Tombol Hapus dilarang turun ke bawah */
        }
        
        table td:nth-child(5) a {
            margin-left: 0 !important; 
            text-decoration: none;
        }

        /* ========================================================================= */
        /* WARNA STATUS PILL KONTROL */
        /* ========================================================================= */
        .status-pill { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;   
            font-weight: 700; 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 11px; 
            text-transform: uppercase; 
            width: 130px;              
            box-sizing: border-box;    
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-lulus { background: #dcfce7; color: #15803d; }
        .status-tolak { background: #fee2e2; color: #b91c1c; }
        .status-proses { background: #e0f2fe !important; color: #0369a1 !important; }
        .status-skip { background: #1e293b !important; color: #ffffff !important; }

        /* ========================================================================= */
        /* TOMBOL & MODAL DETAIL */
        /* ========================================================================= */
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
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
                    <a href="talent_pool.php" class="menu-item">Talent Pool</a>
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
        <!-- Tambahkan inline style layout fixed agar persentase kolom dipatuhi -->
        <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
            <thead>
                <tr>
                    <!-- Lebar diubah menjadi persentase agar pas dengan layar -->
                    <th style="width: 30%; text-align: left;">Nama Pelamar</th>
                    <th style="width: 25%; text-align: left;">Formasi Lowongan</th>
                    <th style="width: 15%; text-align: left;">Tanggal Masuk</th>
                    <th style="width: 15%; text-align: center;">Tahap Seleksi</th>
                    <th style="width: 15%; text-align: center;">Aksi Kontrol</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result_pelamar && mysqli_num_rows($result_pelamar) > 0) : ?>
                    <?php while ($row = mysqli_fetch_assoc($result_pelamar)) : ?>
                        
                        <?php 
                            $status_badge = !empty($row['status_tahap']) ? $row['status_tahap'] : 'Pending'; 
                            
                            $class_badge = 'status-pending'; 
                            if ($status_badge == 'Lulus') $class_badge = 'status-lulus';       
                            if ($status_badge == 'Tidak Lulus') $class_badge = 'status-tolak';  
                            if ($status_badge == 'Proses') $class_badge = 'status-proses';     
                            if ($status_badge == 'Skip') $class_badge = 'status-skip';         
                        ?>

                        <tr>
                            <td style="text-align: left; vertical-align: middle;">
                                <div class="candidate-name" style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nama_pendaftar']); ?></div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">NIK: <?php echo htmlspecialchars($row['nik_pendaftar']); ?></div>
                            </td>
                            <td style="text-align: left; vertical-align: middle;">
                                <span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nama_lowongan']); ?></span>
                            </td>
                            <td style="text-align: left; vertical-align: middle; color: #475569;">
                                <?php echo date('d M Y', strtotime($row['tanggal_daftar'])); ?>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <span class="status-pill <?php echo $class_badge; ?>">• <?php echo htmlspecialchars($status_badge); ?></span>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <!-- PERBAIKAN: Karakter bocor '<' sebelum tombol sudah dihapus -->
                                <button type="button" class="btn-detail" onclick="bukaDetailModal(
                                    '<?php echo $row['lamaran_id']; ?>', 
                                    '<?php echo $row['pelamar_id']; ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['nama_pendaftar'] ?? $row['nama_lengkap'] ?? '')); ?>', 
                                    '<?php echo $row['nik_pendaftar'] ?? $row['nik'] ?? ''; ?>', 
                                    '<?php echo $row['foto_pelamar'] ?? $row['foto'] ?? ''; ?>', 
                                    '<?php echo ($row['tempat_lahir'] ?? '') . ', ' . (!empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : ''); ?>', 
                                    '<?php echo $row['jenis_kelamin'] ?? ''; ?>', 
                                    '<?php echo $row['agama'] ?? ''; ?>', 
                                    '<?php echo $row['status_sosial'] ?? ''; ?>', 
                                    '<?php echo $row['email'] ?? ''; ?>', 
                                    '<?php echo $row['no_telepon'] ?? ''; ?>', 
                                    '<?php echo ($row['kota'] ?? '') . ', ' . ($row['provinsi'] ?? ''); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['alamat'] ?? '')); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['institusi'] ?? $row['nama_institusi'] ?? '')); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['jurusan'] ?? '')); ?>', 
                                    '<?php echo $row['ipk'] ?? $row['nilai'] ?? ''; ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['status_tahap'] ?? 'Pending')); ?>', 
                                    '<?php echo addslashes(htmlspecialchars($row['perusahaan'] ?? '')); ?>',
                                    '<?php echo addslashes(htmlspecialchars($row['jabatan'] ?? '')); ?>',
                                    '<?php echo $row['mulai_kerja'] ?? ''; ?>',
                                    '<?php echo $row['selesai_kerja'] ?? ''; ?>',
                                    '<?php echo addslashes(htmlspecialchars($row['alasan_keluar'] ?? '')); ?>'
                                )">Lihat Detail</button>

                                <a href="?action=hapus_lamaran&lamaran_id=<?php echo $row['lamaran_id']; ?>" class="text-danger" style="margin-left: 12px; font-size: 14px; text-decoration: none;" onclick="return confirm('Apakah Anda yakin ingin menghapus data lamaran pendaftar ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8; font-style: italic; padding: 40px 0;">Belum ada berkas pendaftaran pelamar yang masuk.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== MODAL OVERLAY POP-UP DETAIL PELAMAR ==================== -->
<div id="detailModal" style="
    display: none;
    position: fixed;
    inset: 0;
    background-color: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    font-family: system-ui, -apple-system, sans-serif;
    box-sizing: border-box;
">
    <div style="
        background: #ffffff; 
        border-radius: 20px; 
        max-width: 640px; 
        width: 100%; 
        margin: 16px; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
        display: flex; 
        flex-direction: column; 
        max-height: 85vh;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-sizing: border-box;
    ">
        
        <!-- HEADER MODAL -->
<div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #ffffff; box-sizing: border-box;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <!-- Wadah ikon assignment_ind telah dihapus dari sini -->
        <div>
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; text-align: left;">Informasi Berkas Pelamar</h3>
            <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b; text-align: left;">Detail resume dan dokumen administrasi</p>
        </div>
    </div>
    <button onclick="tutupDetailPelamar()" type="button" style="background: #f1f5f9; border: none; padding: 6px 12px; border-radius: 20px; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center;">
        close
    </button>
</div>

        <!-- ISI KONTEN MODAL (SCROLLABLE AREA) -->
        <div style="padding: 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; text-align: left; background: #f8fafc; box-sizing: border-box;">
            
            <!-- BAGIAN A: BIODATA PROFIL -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-sizing: border-box;">
                <h5 style="margin: 0 0 16px 0; color: #1e3a8a; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                    A. Biodata Profil Pelamar
                </h5>
                <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="flex-shrink: 0;">
                        <img id="modalFoto" src="" alt="Foto Profil" style="width: 100px; height: 130px; object-fit: cover; border-radius: 10px; border: 1px solid #cbd5e1; display: none;">
                        <div id="modalNoFoto" style="display: flex; width: 100px; height: 130px; background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 10px; align-items: center; justify-content: center; font-size: 12px; color: #64748b;">
                            No Photo
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 250px; font-size: 13px; color: #334155; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px;"><span style="width: 130px; font-weight: 600; color: #64748b;">Nama Lengkap</span> <span>: <span id="md_nama" style="font-weight: 700; color: #0f172a;">-</span></span></div>
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px;"><span style="width: 130px; font-weight: 600; color: #64748b;">NIK</span> <span>: <span id="md_nik" style="font-family: monospace;">-</span></span></div>
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px;"><span style="width: 130px; font-weight: 600; color: #64748b;">Tempat, Tgl Lahir</span> <span>: <span id="md_ttl">-</span></span></div>
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px;"><span style="width: 130px; font-weight: 600; color: #64748b;">Jenis Kelamin</span> <span>: <span id="md_jk">-</span></span></div>
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px;"><span style="width: 130px; font-weight: 600; color: #64748b;">No. Telepon / WA</span> <span>: <span id="md_telepon" style="color: #16a34a; font-weight: 700;">-</span></span></div>
                        <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px; align-items: flex-start;">
                            <span style="width: 130px; font-weight: 600; color: #64748b; flex-shrink: 0;">Alamat Rumah</span> 
                            <span style="display: flex; flex: 1; gap: 4px;">: 
                                <span id="md_alamat" style="color: #475569; line-height: 1.4; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #f1f5f9; width: 100%; box-sizing: border-box; display: inline-block;">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BAGIAN B: RIWAYAT PENDIDIKAN -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-sizing: border-box;">
                <h5 style="margin: 0 0 14px 0; color: #1e3a8a; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                    B. Riwayat Pendidikan
                </h5>
                <div style="font-size: 13px; color: #334155; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <span id="md_kampus" style="font-weight: 700; color: #0f172a; font-size: 14px;">-</span>
                            <span id="md_prodi" style="color: #64748b;">-</span>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #eff6ff; padding: 10px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
                        <span style="font-weight: 600; color: #1e40af;">IPK / Nilai Akhir</span>
                        <span id="md_nilai" style="font-weight: 800; color: #2563eb; font-size: 15px;">-</span>
                    </div>
                </div>
            </div>

            <!-- BAGIAN C: RIWAYAT PENGALAMAN KERJA -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-sizing: border-box;">
                <h5 style="margin: 0 0 14px 0; color: #1e3a8a; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                    C. Riwayat Pengalaman Kerja
                </h5>
                <div style="font-size: 13px; color: #334155; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <div style="display: flex; flex-direction: column; gap: 2px; width: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 5px;">
                                <span id="md_perusahaan" style="font-weight: 700; color: #0f172a;">-</span>
                                <span id="md_periode" style="font-size: 12px; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 20px;">-</span>
                            </div>
                            <span id="md_jabatan" style="color: #475569; font-weight: 500;">-</span>
                        </div>
                    </div>
                                <!-- Perbaikan Struktur Bagian C: Alasan Keluar -->
            <div style="display: flex; flex-direction: column; gap: 4px; padding: 4px;">
                <span style="font-weight: 600; color: #64748b;">Alasan Keluar:</span>
                <span id="md_alasan_keluar" style="font-style: italic; color: #475569; background: #fff7ed; padding: 8px; border-radius: 6px; border: 1px solid #ffedd5;">-</span>
            </div>
        </div> <!-- Penutup inner box Bagian C Pengalaman Kerja -->

        <!-- BAGIAN D: LAMPIRAN BERKAS DOKUMEN -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-sizing: border-box;">
            <h5 style="margin: 0 0 14px 0; color: #1e3a8a; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                D. Lampiran Berkas Dokumen (Ijazah)
            </h5>
            <div id="admin-wadah-berkas" style="font-size: 13px; display: flex; flex-direction: column; gap: 10px; width: 100%; box-sizing: border-box;">
                <!-- Diisi otomatis oleh JavaScript -->
            </div>
        </div>
        <!-- BAGIAN E: SURAT TANDA REGISTRASI (STR) -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-sizing: border-box;">
            <h5 style="margin: 0 0 14px 0; color: #1e3a8a; font-size: 13px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                E. Surat Tanda Registrasi (STR)
            </h5>
            <div id="admin-wadah-str" style="font-size: 13px; display: flex; flex-direction: column; gap: 10px; width: 100%; box-sizing: border-box;">
                <!-- Diisi otomatis oleh JavaScript -->
            </div>
        </div>

    </div> <!-- /ISI KONTEN MODAL -->

<!-- FOOTER MODAL (FORM UPDATE STATUS SELEKSI PELAMAR) -->
<div style="padding: 18px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-end; background: #ffffff; box-sizing: border-box; flex-wrap: wrap; gap: 15px; width: 100%;">
    <!-- Bagian Kiri: Dropdown Pilihan Tahap Seleksi -->
    <div style="display: flex; flex-direction: column; gap: 6px; text-align: left; width: 260px; max-width: 100%;">
        <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Tahap Seleksi Pelamar:</label>
        <input type="hidden" id="formLamaranId" value="">
        <select id="md_status_seleksi" style="width: 100%; padding: 11px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 13px; color: #0f172a; background-color: #ffffff; font-weight: 600; cursor: pointer; outline: none; height: 42px; box-sizing: border-box;">
            <option value="Pending">🟡 Pending</option>
            <option value="Lulus">🟢 Lulus</option>
            <option value="Tidak Lulus">🔴 Tidak Lulus</option>
            <option value="Proses">🔵 Proses</option>
            <option value="Skip">⚫ Skip</option>
        </select>
    </div>

    <!-- Bagian Kanan: Tombol Aksi Batal & Simpan -->
    <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
        <button onclick="tutupDetailPelamar()" type="button" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; color: #475569; font-weight: 600; cursor: pointer; font-size: 13px; height: 42px; padding: 0 20px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center;">
            Batal
        </button>
        <button onclick="simpanPerubahanStatus()" type="button" style="background: #2563eb; border: none; border-radius: 10px; color: #ffffff; font-weight: 600; cursor: pointer; font-size: 13px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); height: 42px; padding: 0 22px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center;">
            Simpan Perubahan
        </button>
    </div>
</div>

</div> <!-- /BOX INNER MODAL -->
</div> <!-- /OVERLAY MODAL -->

<!-- ==================== LOGIKA JAVASCRIPT SUNTIK MODAL ==================== -->
<script>
function bukaDetailModal(
    lamaranId, pelamarId, nama, nik, foto, ttl, 
    jk, agama, status, email, telepon, lokasi, 
    alamat, kampus, prodi, nilai, statusAksi,
    perusahaan, jabatan, mulai, selesai, alasan
) {
    try {
        if(document.getElementById('md_nama')) document.getElementById('md_nama').innerText = nama || '-';
        if(document.getElementById('md_nik')) document.getElementById('md_nik').innerText = nik || '-';
        if(document.getElementById('md_ttl')) document.getElementById('md_ttl').innerText = ttl || '-';
        if(document.getElementById('md_jk')) document.getElementById('md_jk').innerText = jk || '-';
        if(document.getElementById('md_agama')) document.getElementById('md_agama').innerText = agama || '-';
        if(document.getElementById('md_status')) document.getElementById('md_status').innerText = status || '-';
        if(document.getElementById('md_telepon')) document.getElementById('md_telepon').innerText = telepon || '-';
        if(document.getElementById('md_email')) document.getElementById('md_email').innerText = email || '-';
        if(document.getElementById('md_lokasi')) document.getElementById('md_lokasi').innerText = lokasi || '-';
        if(document.getElementById('md_alamat')) document.getElementById('md_alamat').innerText = alamat || '-';
        
        if(document.getElementById('md_kampus')) document.getElementById('md_kampus').innerText = kampus || '-';
        if(document.getElementById('md_prodi')) document.getElementById('md_prodi').innerText = prodi || '-';
        if(document.getElementById('md_nilai')) document.getElementById('md_nilai').innerText = nilai || '-';

        if(document.getElementById('md_perusahaan')) document.getElementById('md_perusahaan').innerText = perusahaan || 'Tidak Ada Pengalaman';
        if(document.getElementById('md_jabatan')) document.getElementById('md_jabatan').innerText = jabatan || '-';
        
        if (mulai) {
            let tgl_selesai = selesai ? selesai.split('-').reverse().join('/') : 'Sekarang';
            let tgl_mulai = mulai.split('-').reverse().join('/');
            if(document.getElementById('md_periode')) document.getElementById('md_periode').innerText = tgl_mulai + ' s/d ' + tgl_selesai;
        } else { 
            if(document.getElementById('md_periode')) document.getElementById('md_periode').innerText = '-'; 
        }
        if(document.getElementById('md_alasan_keluar')) document.getElementById('md_alasan_keluar').innerText = alasan || '-';

        const imgObj = document.getElementById('modalFoto');
        const noImgObj = document.getElementById('modalNoFoto');
        if (imgObj) {
            if (foto && foto !== '') {
                imgObj.src = 'uploads/' + foto;
                imgObj.style.display = 'inline-block';
                if(noImgObj) noImgObj.style.display = 'none';
            } else {
                imgObj.style.display = 'none';
                if(noImgObj) noImgObj.style.display = 'flex';
            }
        }

        if(document.getElementById('formLamaranId')) document.getElementById('formLamaranId').value = lamaranId;
        
        const selectStatus = document.getElementById('md_status_seleksi');
        if (selectStatus && statusAksi) {
            selectStatus.value = statusAksi;
        }
    } catch (e) {
        console.warn("Ada elemen HTML biodata yang tidak ditemukan:", e);
    }

    // === TAMBAHKAN KODE INI DI SINI ===
    const modalObj = document.getElementById('detailModal');
    if (modalObj) {
        modalObj.style.display = 'flex'; 
    }
    // =================================

    const wadahBerkas = document.getElementById('admin-wadah-berkas');
    const wadahSTR    = document.getElementById('admin-wadah-str');


    if(wadahBerkas) wadahBerkas.innerHTML = '<span style="color: #64748b; font-size: 13px; font-style: italic;">Memuat berkas dokumen...</span>';
    if(wadahSTR) wadahSTR.innerHTML    = '<span style="color: #64748b; font-size: 13px; font-style: italic;">Memuat data STR...</span>';

    fetch('get_berkas_str_admin.php?pelamar_id=' + pelamarId)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                
                // A. Tampilkan Paket Berkas Dokumen Pelamar (Ijazah)
                if (wadahBerkas) {
                    if (res.berkas && res.berkas.length > 0) {
                        let htmlBerkas = '';
                        res.berkas.forEach(bk => {
                            htmlBerkas += `
                                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; gap: 15px; width: 100%; box-sizing: border-box; margin-bottom: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; width: 70%;">
                                        <span style="font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            ${bk.jenis_berkas}
                                        </span>
                                    </div>
                                    ${bk.nama_file ? `
                                        <a href="uploads/${bk.nama_file}" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 6px; flex-shrink: 0; background: #eff6ff; padding: 8px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
                                            Lihat Berkas
                                        </a>
                                    ` : '<span style="color: #94a3b8; font-style: italic; font-size: 12px;">Belum diunggah</span>'}
                                </div>`;
                        });
                        wadahBerkas.innerHTML = htmlBerkas;
                    } else {
                        wadahBerkas.innerHTML = '<span style="color: #64748b; font-size: 13px; font-style: italic;">Pelamar belum melampirkan berkas dokumen.</span>';
                    }
                }

                // B. Tampilkan Paket Data Surat Tanda Registrasi (STR)
                if (wadahSTR) {
                    if (res.str && res.str.length > 0) {
                        let htmlSTR = '';
                        res.str.forEach(s => {
                            htmlSTR += `
                                <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 16px; border-radius: 12px; font-size: 13px; line-height: 1.6; box-shadow: 0 1px 3px rgba(0,0,0,0.02); box-sizing: border-box; margin-bottom: 10px;">
                                    <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 6px; margin-bottom: 6px;"><span style="width: 120px; color: #64748b; font-weight: 600;">Nomor STR</span><span style="flex: 1; color: #0f172a; font-family: monospace;">: ${s.nomor_str}</span></div>
                                    <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 6px; margin-bottom: 6px;"><span style="width: 120px; color: #64748b; font-weight: 600;">Tanggal Terbit</span><span style="flex: 1; color: #0f172a;">: ${s.tanggal_terbit || '-'}</span></div>
                                    <div style="display: flex; border-bottom: 1px dashed #f1f5f9; padding-bottom: 6px; margin-bottom: 12px;"><span style="width: 120px; color: #64748b; font-weight: 600;">Tanggal Expired</span><span style="flex: 1; color: #b91c1c; font-weight: 700;">: ${s.tanggal_expired || '-'}</span></div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; gap: 15px; width: 100%; box-sizing: border-box;">
                                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; width: 70%;">
                                            <span style="font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                ${s.nama_file || 'File_STR.pdf'}
                                            </span>
                                        </div>
                                        ${s.nama_file ? `
                                            <a href="uploads/${s.nama_file}" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 6px; flex-shrink: 0; background: #eff6ff; padding: 8px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
                                                Lihat Berkas
                                            </a>
                                        ` : '<span style="color: #94a3b8; font-style: italic; font-size: 12px;">Belum diunggah</span>'}
                                    </div>
                                </div>`;
                        });
                                                wadahSTR.innerHTML = htmlSTR;
                    } else {
                        wadahSTR.innerHTML = '<span style="color: #64748b; font-size: 13px; font-style: italic;">Pelamar belum melampirkan berkas STR.</span>';
                    }
                }

            } else {
                if(wadahBerkas) wadahBerkas.innerHTML = '<span style="color: #ef4444; font-size: 13px;">Gagal memuat data berkas.</span>';
                if(wadahSTR) wadahSTR.innerHTML = '<span style="color: #ef4444; font-size: 13px;">Gagal memuat data STR.</span>';
            }
        })
        .catch(err => {
            console.error("Terjadi error fetch data admin:", err);
            if(wadahBerkas) wadahBerkas.innerHTML = '<span style="color: #ef4444; font-size: 13px;">Error koneksi data berkas.</span>';
            if(wadahSTR) wadahSTR.innerHTML = '<span style="color: #ef4444; font-size: 13px;">Error koneksi data STR.</span>';
        });
}

// 1. FUNGSI UNTUK MENUTUP MODAL (Aksi tombol Close & Batal)
function tutupDetailPelamar() {
    const modalObj = document.getElementById('detailModal');
    if (modalObj) {
        modalObj.style.display = 'none';
    }
}

// 2. FUNGSI UNTUK SIMPAN STATUS SELEKSI (Aksi tombol Simpan Perubahan)
function simpanPerubahanStatus() {
    const lamaranId = document.getElementById('formLamaranId').value;
    const statusBaru = document.getElementById('md_status_seleksi').value;

    if (!lamaranId) {
        alert("ID Lamaran tidak ditemukan!");
        return;
    }

    // Mengirim data ke lamaran_tahapan.php di latar belakang menggunakan Fetch
    fetch('lamaran_tahapan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&id_lamaran=${lamaranId}&status_tahap=${statusBaru}&id_tahapan=`
    })
    .then(response => {
        // Karena file PHP melakukan redirect (Location:), response.url akan mendeteksi halaman tujuan.
        // Jika response aman, kita anggap database berhasil diperbarui.
        if (response.ok) {
            alert("Status seleksi berhasil diperbarui!");
            tutupDetailPelamar(); // Menutup modal pop-up
            location.reload();    // Menyegarkan halaman data_pelamar.php agar tabel terupdate
        } else {
            alert("Gagal memperbarui status pada sistem.");
        }
    })
    .catch(err => {
        console.error("Error saat menyimpan status:", err);
        alert("Terjadi kesalahan koneksi saat menyimpan data!");
    });
}
</script>
    </body>
</html>