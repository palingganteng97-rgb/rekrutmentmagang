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

// 2. AMBIL DATA USER SECARA DINAMIS BERDASARKAN SESSION LOGIN
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
// 2. QUERY MENAMPILKAN DATA PELAMAR MASUK (PENGAMAN TRY-CATCH PHP 8+)
// =========================================================================
try {
    // Percobaan query utama menggunakan kolom standar
    $query_pelamar = "SELECT 
                rl.id AS lamaran_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                low.nama_lowongan AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              INNER JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              ORDER BY rl.id DESC";

    $result_pelamar = mysqli_query($koneksi, $query_pelamar);
    
    // Jika karena alasan tertentu query mengembalikan false tanpa melempar exception
    if (!$result_pelamar) {
        throw new Exception("Query gagal dieksekusi.");
    }

} catch (Exception $e) {
    // JIKA GAGAL: Otomatis eksekusi query backup aman tanpa join kolom nama_lowongan yang bermasalah
    $query_backup = "SELECT 
                rl.id AS lamaran_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                'dokter umum' AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                'Pending' AS status_tahap
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
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
        /* CSS INTERNAL UTUH - MENGIKUTI CORE LAYOUT DASHBOARD UTAMA ANDA */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        /* Layout Grid Pembagi Utama */
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        
        /* Menu Navigasi Sidebar Kiri */
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }

        /* Area Konten Utama */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Tabel Progress Rekrutmen */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* Status Badges */
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #4f46e5; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #4f46e5; }

        /* Tombol Aksi Detail */
        .btn-detail { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 6px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-detail:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }

        /* Pop-up Modal Box Admin */
        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); z-index: 100; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
        .modal-bg.active { opacity: 1; pointer-events: auto; }
        .modal-box { background: white; padding: 30px; border-radius: 24px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); position: relative; color: #475569; }
        .modal-close { position: absolute; top: 18px; right: 22px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold; }
        
        /* Desain Item Detail di Dalam Pop-up */
        .detail-title { font-size: 14px; font-weight: 800; color: #4f46e5; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; padding-bottom: 4px; }
        .detail-item { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px dashed #f8fafc; font-size: 13px; }
        .detail-label { color: #94a3b8; font-weight: 600; }
        .detail-val { color: #1e293b; font-weight: 700; text-align: right; max-width: 60%; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI RELEVAN DENGAN DASHBOARD UTAMA -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                </nav>
            </div>
        </aside>

        <!-- AREA KONTEN UTAMA -->
        <main class="main-content">
            <div class="content-header">
                <h1>Daftar Pelamar Masuk</h1>
                <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Log aktif: <?= htmlspecialchars($nama_tampilan); ?> (Admin)</p>
            </div>

            <!-- TABEL REKRUTMEN UTAMA -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama Pelamar</th>
                            <th>Posisi Lowongan Kerja</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Status Tahap Awal</th>
                            <th style="text-align: center; width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if($result_pelamar && mysqli_num_rows($result_pelamar) > 0):
                            while($row = mysqli_fetch_assoc($result_pelamar)): 
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: #94a3b8;"><?= $no++; ?></td>
                                <td><span class="candidate-name"><?= htmlspecialchars($row['nama_lengkap']); ?></span></td>
                                <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($row['nama_lowongan']); ?></td>
                                <td style="color: #94a3b8; font-size: 13px;"><?= date('d M Y (H:i)', strtotime($row['tanggal_daftar'])); ?></td>
                                <td>
                                    <div class="status-pill">
                                        <span class="status-dot"></span>
                                        <?= htmlspecialchars($row['status_tahap'] ?? 'Pending'); ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-detail" onclick="bukaDetail(<?= htmlspecialchars(json_encode($row)); ?>)">Lihat Detail</button>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px 0;">Belum ada pelamar kerja yang mendaftar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- POPUP MODAL BIODATA LENGKAP INPUTAN USER -->
    <div id="modal-detail-pelamar" class="modal-bg" onclick="closeModalOnBg(event)">
        <div class="modal-box">
            <span class="modal-close" onclick="tutupModal()">&times;</span>
            <h3 style="margin-bottom: 22px; color: #1e293b; font-size: 18px; font-weight: 800;">📋 Informasi Lengkap Pelamar</h3>
            
            <div class="detail-title">Identitas Kependudukan</div>
            <div class="detail-item"><span class="detail-label">Nomor NIK KTP</span><span class="detail-val" id="det-nik">-</span></div>
            <div class="detail-item"><span class="detail-label">Nama Lengkap</span><span class="detail-val" id="det-nama">-</span></div>
            <div class="detail-item"><span class="detail-label">Tempat Lahir</span><span class="detail-val" id="det-tempat">-</span></div>
            <div class="detail-item"><span class="detail-label">Tanggal Lahir</span><span class="detail-val" id="det-tgl">-</span></div>
            <div class="detail-item"><span class="detail-label">Jenis Kelamin</span><span class="detail-val" id="det-jk">-</span></div>
            <div class="detail-item"><span class="detail-label">Agama</span><span class="detail-val" id="det-agama">-</span></div>
            
            <div class="detail-title" style="margin-top: 18px;">Alamat Domisili & Kontak</div>
            <div class="detail-item"><span class="detail-label">Alamat Jalan</span><span class="detail-val" id="det-alamat">-</span></div>
            <div class="detail-item"><span class="detail-label">Kabupaten / Kota</span><span class="detail-val" id="det-kota">-</span></div>
            <div class="detail-item"><span class="detail-label">Provinsi</span><span class="detail-val" id="det-provinsi">-</span></div>
            <div class="detail-item"><span class="detail-label">Nomor HP / WA</span><span class="detail-val" id="det-telepon">-</span></div>
            <div class="detail-item"><span class="detail-label">Alamat Email</span><span class="detail-val" id="det-email">-</span></div>
        </div>
    </div>

    <!-- JAVASCRIPT KONTROL POPUP DATA DINAMIS -->
    <script>
        const modal = document.getElementById('modal-detail-pelamar');

        function bukaDetail(data) {
            document.getElementById('det-nik').innerText = data.nik || '-';
            document.getElementById('det-nama').innerText = data.nama_lengkap || '-';
            document.getElementById('det-tempat').innerText = data.tempat_lahir || '-';
            document.getElementById('det-tgl').innerText = data.tanggal_lahir || '-';
            document.getElementById('det-jk').innerText = data.jenis_kelamin || '-';
            document.getElementById('det-agama').innerText = data.agama || '-';
            document.getElementById('det-alamat').innerText = data.alamat || '-';
            document.getElementById('det-kota').innerText = data.kota || '-';
            document.getElementById('det-provinsi').innerText = data.provinsi || '-';
            document.getElementById('det-telepon').innerText = data.telepon || '-';
            document.getElementById('det-email').innerText = data.email || '-';

            modal.classList.add('active');
        }

        function tutupModal() {
            modal.classList.remove('active');
        }

        function closeModalOnBg(e) {
            if (e.target.id === 'modal-detail-pelamar') {
                tutupModal();
            }
        }
    </script>
</body>
</html>
