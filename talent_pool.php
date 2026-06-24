<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

// Menggunakan variabel $koneksi sesuai konfigurasi sistem Anda
$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. QUERY UTAMA: AMBIL DATA TALENT POOL & TRACKING ID TAHAPAN LAMARAN
// =========================================================================
$query_pool = mysqli_query($koneksi, "
    SELECT
        tp.*,
        p.*,
        p.nama_lengkap AS nama_pendaftar,
        (
            SELECT MAX(lt.id)
            FROM rekrutmen_lamaran rl
            JOIN lamaran_tahapan lt
                ON rl.id = lt.lamaran_id
            WHERE rl.pelamar_id = p.id
        ) AS id_lamaran_tahapan
    FROM talent_pool tp
    JOIN pelamar p
        ON tp.pelamar_id = p.id
    ORDER BY tp.id DESC
");

if (!$query_pool) {
    die("Query Gagal: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Rekrutmen Magang</title>
    <style>
        /* CSS INTERNAL UTUH - BERFUNGSI OFFLINE TANPA INTERNET */
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
        .btn-logout { display: block; width: 100%; background: #dc2626; color: white; text-decoration: none; text-align: center; font-weight: 700; font-size: 14px; padding: 14px 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); transition: background 0.2s; margin-top: auto; }
        .btn-logout:hover { background: #b91c1c; }
        /* Area Konten Utama */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Banner Sambutan Ungu */
        .welcome-banner { background: #4f46e5; border-radius: 24px; padding: 35px 40px; color: #ffffff; position: relative; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15); margin-top: 10px; }
        .welcome-banner h2 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .welcome-banner p { font-size: 14px; opacity: 0.9; line-height: 1.6; max-width: 500px; }
        
        /* Grid Lowongan Pekerjaan */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: 800; color: #1e293b; }
        .see-all-link { font-size: 12px; color: #94a3b8; text-decoration: none; font-weight: 700; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .job-card { background: #ffffff; border: 1px solid #f1f5f9; padding: 22px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .job-card .qty { font-size: 32px; font-weight: 900; color: #1e293b; line-height: 1; }
        .job-card .title { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 6px; }
        .job-card .desc { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .percentage-ring { width: 48px; height: 48px; border-radius: 50%; border: 4px solid #f1f5f9; border-top-color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #4f46e5; }

        /* Tabel Progress Rekrutmen */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #334155; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #4f46e5; }
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
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item">Lamaran Tahapan</a>
                    <a href="talent_pool.php" class="menu-item active">Talent Pool</a>
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

<!-- ==================== AREA UTAMA KANAN ==================== -->
<div class="main-content">
    <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #0f172a;">✨ Database Talent Pool Kandidat</h2>
    <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Menampilkan daftar seluruh kandidat potensial hasil evaluasi penilai rekrutmen Rumah Sakit.</p>
    
    <div class="table-container">
        <table class="table-pool">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">NO</th>
                    <th style="width: 25%;">DATA PELAMAR</th>
                    <th style="width: 20%;">KONTAK</th>
                    <th style="width: 25%;">CATATAN REKOMENDASI</th>
                    <th style="width: 15%;">TANGGAL MASUK</th>
                    <th style="width: 10%; text-align: center;">STATUS</th>
                    <th style="width: 10%; text-align: center;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                if (mysqli_num_rows($query_pool) > 0) {
                    while ($row = mysqli_fetch_assoc($query_pool)) : 
                        // Menentukan class warna badge sesuai isi ENUM database Anda
                        $status_clean = strtolower($row['status']);
                        $class_badge = 'badge-aktif';
                        if ($status_clean == 'dihubungi') { $class_badge = 'badge-dihubungi'; }
                        if ($status_clean == 'direkrut') { $class_badge = 'badge-direkrut'; }
                        if ($status_clean == 'nonaktif') { $class_badge = 'badge-nonaktif'; }
                ?>
                <tr style="color: #1e293b;">
                    <!-- Kolom Nomor -->
                    <td style="text-align: center; font-weight: 600; color: #64748b;"><?= $no++; ?></td>
                    
                    <!-- Kolom Nama & NIK -->
                    <td>
                        <strong style="color: #0284c7; font-size: 14px;"><?= htmlspecialchars($row['nama_pendaftar']); ?></strong><br>
                        <span style="font-size: 11px; color: #94a3b8; font-weight: 500;">NIK: <?= htmlspecialchars($row['nik']); ?></span>
                    </td>
                    
                    <!-- Kolom Kontak Pelamar Resmi -->
                    <td style="line-height: 1.5; color: #1e293b; vertical-align: top;">
                        📞 <strong style="color: #334155;"><?= htmlspecialchars($row['no_telepon'] ?? '-'); ?></strong><br>
                        ✉️ <span style="color: #64748b;"><?= htmlspecialchars($row['email'] ?? '-'); ?></span>
                    </td>
                    
                    <!-- Kolom Catatan Penilai -->
                    <td style="color: #475569; font-style: italic; line-height: 1.4;">
                        "<?= !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : 'Tidak ada catatan khusus.'; ?>"
                    </td>
                    
                    <!-- Kolom Tanggal Masuk Pool -->
                    <td style="color: #475569; font-weight: 500;">
                        <?= !empty($row['tanggal_masuk']) ? date('d M Y - H:i', strtotime($row['tanggal_masuk'])) : '-'; ?> WIB
                    </td>
                    
                    <!-- Kolom Status ENUM -->
                    <td style="text-align: center;">
                        <span class="badge-status <?= $class_badge; ?>">
                            <?= htmlspecialchars($row['status']); ?>
                        </span>
                    </td>

                    <!-- Kolom Aksi -->
                    <td style="text-align: center; vertical-align: middle;">
                        <?php if (!empty($row['id_lamaran_tahapan'])): ?>
                            <a href="penilaian_tahapan.php?id=<?= $row['id_lamaran_tahapan']; ?>" style="display: inline-block; background-color: #2563eb; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 700; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2); transition: background-color 0.2s;">
                                🔍 Detail
                            </a>
                        <?php else: ?>
                            <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No Ref ID</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                } else {
                    // Colspan diubah menjadi 7 karena ada total 7 kolom di bagian thead
                    echo "<tr><td colspan='7' style='padding: 30px; text-align: center; color: #94a3b8; font-style: italic; font-size: 13px;'>Belum ada data kandidat yang masuk ke dalam kategori talent pool.</td></tr>";
                } 
                ?>
            </tbody>
        </table>
    </div>
</div>
