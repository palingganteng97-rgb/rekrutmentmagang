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

// 2. DATA ARRAY LOKAL (Bahasa Indonesia)
$lowongan_kerja = [
    ['qty' => '3', 'nama' => 'Desainer Konten', 'pendaftar' => '5 pendaftar', 'pct' => '75%', 'color' => 'indigo'],
    ['qty' => '9', 'nama' => 'Pengembang Node.js', 'pendaftar' => '12 pendaftar', 'pct' => '25%', 'color' => 'rose'],
    ['qty' => '1', 'nama' => 'Desainer UI Senior', 'pendaftar' => '0 pendaftar', 'pct' => '0%', 'color' => 'slate'],
    ['qty' => '2', 'nama' => 'Manajer Pemasaran', 'pendaftar' => '10 pendaftar', 'pct' => '45%', 'color' => 'indigo']
];

// 3. AMBIL DATA USER SECARA DINAMIS BERDASARKAN SESSION LOGIN
$nama_tampilan = "Administrator";

if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    $query = "SELECT nama FROM users WHERE username = '$username_aktif'";
    $hasil = mysqli_query($koneksi, $query);
    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_user = mysqli_fetch_assoc($hasil);
        $nama_tampilan = $data_user['nama'];
    }
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
        
        <!-- SIDEBAR MENU KIRI BERSIH MINIMALIS -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                
                <!-- NAVIGASI MENU (KOTAK PROFIL KECIL SUDAH DIHAPUS) -->
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item active">Dashboard</a>
                    <a href="master_user.php" class="menu-item" style="display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master User</a>
                    <a href="master_unit.php" class="menu-item" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item" style="text-decoration: none;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>                    <a href="user.php" class="menu-item" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='transparent'; this.style.color='#94a3b8';">Profil Pengguna</a>
                </nav>
            </div>
            
            <!-- KOTAK LOGOUT MINIMALIS -->
            <div class="support-card" style="background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center; margin-top: 20px;">
                <a href="logout.php" style="display: block; width: 100%; background: #dc2626; color: white; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);">Log Out</a>
            </div>
        </aside>

        <!-- AREA KONTEN UTAMA TENGAH -->
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
            </div>

            <!-- Banner Sambutan -->
            <div class="welcome-banner">
                <h2>Selamat Datang Kembali, <?php echo $nama_tampilan; ?>!</h2>
                <p>Sistem Rekrutmen Magang ID siap digunakan. Seluruh modul dan data pelamar dapat Anda kelola sepenuhnya melalui panel navigasi sebelah kiri.</p>
            </div>

            <!-- Bagian Lowongan Kerja -->
            <section>
                <div class="section-header">
                    <div class="section-title">You need to hire</div>
                    <a href="#" class="see-all-link">see all</a>
                </div>
                <div class="cards-grid">
                    <?php foreach($lowongan_kerja as $l): ?>
                    <div class="job-card">
                        <div>
                            <div class="qty"><?= $l['qty'] ?></div>
                            <div class="title"><?= $l['nama'] ?></div>
                            <div class="desc">(<?= $l['pendaftar'] ?>)</div>
                        </div>
                        <div class="percentage-ring" style="border-top-color: #4f46e5;"><?= $l['pct'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
                            </td>
                        </tr>
                    
                    </tbody>
                </table>
            </div>
        </main>

    </div>
</body>
</html>
