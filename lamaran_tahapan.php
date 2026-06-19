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

// 3. QUERY MENGAMBIL DATA LOWONGAN SINKRON TABEL MASTER LOWONGAN ANDA
$lowongan_kerja = [];
$q_lwn = mysqli_query($koneksi, "SELECT judul_lowongan AS posisi, deskripsi FROM rekrutmen_lowongan ORDER BY id DESC LIMIT 2");
if ($q_lwn) {
    while ($r_lwn = mysqli_fetch_assoc($q_lwn)) {
        $lowongan_kerja[] = $r_lwn;
    }
}

// =========================================================================
// 4. PERBAIKAN: QUERY PROGRESS REKRUTMEN TERBARU UNTUK ISI TABEL ADMIN
// =========================================================================
$query_progress = mysqli_query($koneksi, "SELECT 
                    p.nama_lengkap AS nama_pendaftar, 
                    p.nik, 
                    low.judul_lowongan AS nama_lowongan, -- PERBAIKAN: Mengubah low.nama_lowongan menjadi low.judul_lowongan
                    lt.status AS status_tahap, 
                    lt.tanggal_mulai AS tanggal_update
                 FROM lamaran_tahapan lt
                 INNER JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
                 INNER JOIN pelamar p ON rl.pelamar_id = p.id
                 INNER JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
                 ORDER BY lt.id DESC LIMIT 5");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lamaran Tahapan Seleksi</title>
    <style>
        /* CSS INTERNAL UTUH - DASHBOARD STYLING */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        
        /* Sidebar Kiri */
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        
        /* Area Konten */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Cards Grid Lowongan */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: 800; color: #1e293b; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .job-card { background: #ffffff; border: 1px solid #f1f5f9; padding: 22px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .job-card .qty { font-size: 32px; font-weight: 900; color: #4f46e5; line-height: 1; }
        .job-card .title { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 6px; }
        .job-card .desc { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .percentage-ring { width: 48px; height: 48px; border-radius: 50%; border: 4px solid #f1f5f9; border-top-color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #4f46e5; }

        /* Tabel Kembar */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* Bulatan Status Otomatis Dinamis CSS */
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #334155; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .status-pending { background: #d97706; } /* Warna Kuning Oranye */
        .status-terima { background: #15803d; }  /* Warna Hijau */
        .status-tolak { background: #b91c1c; }   /* Warna Merah */
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI -->
        <aside class="sidebar-left">
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item active">Lamaran Tahapan</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
                </nav>
            </div>

            <div style="margin-top: auto; padding-top: 40px;">
                <a href="logout.php" style="display: block; width: 100%; padding: 14px; background: #ef4444; color: #ffffff; text-align: center; border-radius: 16px; font-weight: 700; font-size: 14px; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?')">Log Out</a>
            </div>            
        </aside>

        <!-- AREA KONTEN UTAMA MID -->
        <main class="main-content">
            <div class="content-header">
                <h1>Lamaran Tahapan</h1>
            </div>

            <!-- Bagian Lowongan Kerja (Kotak Kuota Master) -->
            <section>
                <div class="section-header">
                    <div class="section-title">Kuota Data Master Lowongan</div>
                </div>
                <div class="cards-grid">
                    <?php if (!empty($lowongan_kerja)) : ?>
                        <?php foreach ($lowongan_kerja as $lk) : ?>
                            <div class="job-card">
                                <div>
                                    <div class="qty">NEW</div>
                                    <div class="title">Posisi: <?php echo htmlspecialchars($lk['posisi'] ?? 'Lowongan'); ?></div>
                                    <div class="desc"><?php echo htmlspecialchars($lk['deskripsi'] ?? '-'); ?></div>
                                </div>
                                <div class="percentage-ring">NEW</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-style:italic; font-size: 14px; width: 100%;">Belum ada kuota data master lowongan aktif di database.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- TABEL PROGRESS SELEKSI -->
            <section>
                <div class="section-header">
                    <div class="section-title">Progress Rekrutmen Terbaru</div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Pelamar</th>
                                <th>Formasi Lowongan</th>
                                <th>Tanggal Update</th>
                                <th>Status Tahap</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($query_progress && mysqli_num_rows($query_progress) > 0) : ?>
                    <?php while ($row = mysqli_fetch_assoc($query_progress)) : ?>
                        <?php 
                            $status_badge = !empty($row['status_tahap']) ? $row['status_tahap'] : 'Pending'; 
                            $class_badge = 'status-pending';
                            if ($status_badge == 'Terima') $class_badge = 'status-terima';
                            if ($status_badge == 'Tolak') $class_badge = 'status-tolak';
                        ?>
                        <tr>
                            <td>
                                <div class="candidate-name"><?php echo htmlspecialchars($row['nama_pendaftar']); ?></div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">NIK: <?php echo htmlspecialchars($row['nik'] ?? '-'); ?></div>
                            </td>
                            <td><span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nama_lowongan']); ?></span></td>
                            <td><?php echo date('d M Y - H:i', strtotime($row['tanggal_update'])); ?> WIB</td>
                            <td>
                                <div class="status-pill">
                                    <span class="status-dot <?php echo $class_badge; ?>"></span>
                                    <strong><?php echo htmlspecialchars($status_badge); ?></strong>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr><td colspan="4" style="text-align: center; color: #94a3b8; font-style: italic; padding: 30px 0;">Belum ada progress tahapan rekrutmen terbaru saat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</main>
</div> <!-- Penutup dashboard-container -->

<!-- CSS INTERNAL UNTUK BULATAN WARNA STATUS AUTOMATIC -->
<style>
    .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .status-pending { background-color: #d97706; }
    .status-terima { background-color: #15803d; }
    .status-tolak { background-color: #b91c1c; }
</style>

</body>
</html>
