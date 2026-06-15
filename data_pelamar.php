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

// 2. QUERY RELASI AMBIL DATA PELAMAR ASLI YANG MASUK DARI FORM DEPAN
$query = "SELECT 
            p.nama_lengkap AS nama_pelamar, 
            l.judul_lowongan, 
            t.status AS status_lamaran,
            t.tanggal_mulai
          FROM lamaran_tahapan t
          JOIN rekrutmen_lamaran r ON t.lamaran_id = r.id
          JOIN pelamar p ON r.pelamar_id = p.id
          JOIN rekrutmen_lowongan l ON r.lowongan_id = l.id
          ORDER BY t.id DESC";

$result = mysqli_query($koneksi, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelamar Kerja</title>
    <style>
        /* CSS DISAMAKAN PERSIS DENGAN DASHBOARD ADMIN ANDA */
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
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* Badge Status Bulat Ungu Sesuai Desain */
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #4f46e5; background: #f5f3ff; padding: 6px 14px; border-radius: 50px; font-size: 13px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #4f46e5; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI (SAMA DENGAN DASHBOARD) -->
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
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
                </nav>
            </div>
            <div class="support-card">
                <a href="logout.php">Log Out</a>
            <div></div>
        </aside>

        <!-- KONTEN UTAMA TABLE DATA PELAMAR MASUK -->
        <main class="main-content">
            <div class="content-header">
                <h1>Daftar Pelamar Masuk</h1>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama Pelamar</th>
                            <th>Posisi Lowongan Kerja</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Status Tahap Awal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td class="candidate-name"><?php echo htmlspecialchars($row['nama_pelamar']); ?></td>
                                <td><?php echo htmlspecialchars($row['judul_lowongan']); ?></td>
                                <td><?php echo date('d M Y (H:i)', strtotime($row['tanggal_mulai'])); ?></td>
                                <td>
                                    <div class="status-pill">
                                        <span class="status-dot"></span>
                                        <?php echo htmlspecialchars($row['status_lamaran']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; color: #94a3b8; padding: 40px 0;'>Belum ada data berkas pelamar baru yang masuk ke database.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>

    </div>

</body>
</html>
