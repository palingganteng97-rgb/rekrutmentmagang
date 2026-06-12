<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE SERVER (DISESUAIKAN DENGAN DASHBOARD)
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. DATA CADANGAN JIKA BELUM LOGIN LEWAT LOGIN.PHP
$nama_tampilan   = "Administrator";
$email_tampilan  = "admin@magang.id";
$username_tampilan = "admin";
$status_akun     = "Aktif";
$login_terakhir  = "Baru Saja";
$terdaftar_sejak = "12 Juni 2026";
$inisial_tampilan = "A";

// 3. AMBIL DATA USER LENGKAP JIKA SESSION LOGIN TERSEDIA
if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    $query = "SELECT nama, username, email, last_login, status, created_at FROM users WHERE username = '$username_aktif'";
    $hasil = mysqli_query($koneksi, $query);

    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_user = mysqli_fetch_assoc($hasil);
        $nama_tampilan   = $data_user['nama'];
        $email_tampilan  = $data_user['email'];
        $username_tampilan = $data_user['username'];
        $status_akun     = $data_user['status'];
        $login_terakhir  = !empty($data_user['last_login']) ? $data_user['last_login'] : 'Baru Saja';
        $terdaftar_sejak = date('d F Y', strtotime($data_user['created_at']));
        $inisial_tampilan = strtoupper(substr($nama_tampilan, 0, 1));
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Magang ID</title>
    <style>
        /* CSS INTERNAL UTUH - RINGKAN & BERFUNGSI OFFLINE */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        /* Layout Pembagi Utama */
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        
        /* Sidebar Menu Kiri */
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item:hover { background: #f8fafc; color: #1e293b; }
        .support-card { background: #fff5f5; border: 1px solid #fee2e2; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px; }
        .support-card h4 { font-size: 14px; color: #991b1b; font-weight: 700; }
        .support-card p { font-size: 11px; color: #b91c1c; margin-top: 4px; }
        .support-card a { display: block; width: 100%; margin-top: 15px; background: #dc2626; color: white; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); }

        /* Area Konten Utama */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px; display: flex; flex-direction: column; gap: 30px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Kartu Profil Besar */
        .profile-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 35px; display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .profile-avatar-big { width: 90px; height: 90px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 36px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); }
        .profile-meta h2 { font-size: 22px; font-weight: 800; color: #1e293b; text-transform: capitalize; }
        .profile-meta p { font-size: 14px; color: #94a3b8; margin-top: 4px; }
        .status-badge-active { display: inline-block; background: #ecfdf5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Form / Detail Data */
        .details-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; }
        .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 20px; }
        .detail-box { display: flex; flex-direction: column; gap: 6px; }
        .detail-box label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-box .value-text { font-size: 15px; font-weight: 600; color: #334155; background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #f1f5f9; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI -->
                <!-- SIDEBAR MENU KIRI IDENTIK DENGAN DASHBOARD -->
        <aside class="sidebar-left" style="width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0;">
            <div>
                <!-- Logo Aplikasi -->
                <div class="brand-logo" style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px;">
                    <span style="width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block;"></span>impozitions
                </div>
                
                <!-- BOKS PROFIL USER (BISA DIKLIK KEMBALI KE USER.PHP) -->
                <a href="user.php" style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 20px; margin-bottom: 25px; border: 1px solid #f1f5f9; width: 100%; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f0fdf4'; this.style.borderColor='#bbf7d0';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#f1f5f9';">
                    <!-- Foto Inisial Bulat -->
                    <div style="width: 44px; height: 44px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);">
                        <?php echo isset($inisial_tampilan) ? $inisial_tampilan : 'A'; ?>
                    </div>
                    <!-- Detail Nama & Status -->
                    <div style="overflow: hidden; flex: 1; text-align: left;">
                        <h4 style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 145px; text-transform: capitalize;">
                            <?php echo isset($nama_tampilan) ? $nama_tampilan : 'Administrator'; ?>
                        </h4>
                        <p style="font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 145px;">
                            <?php echo isset($email_tampilan) ? $email_tampilan : 'admin@magang.id'; ?>
                        </p>
                    </div>
                </a>

                <!-- Navigasi Menu Samping -->
                <nav class="menu-list">
                    <!-- Menu Dashboard diberi link menuju dashboard.php agar bisa diklik kembali -->
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                </nav>
            </div>
            
            <!-- KOTAK OPSI LOGOUT MERAH -->
            <div class="support-card" style="background: #fff5f5; border: 1px solid #fee2e2; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px;">
                <a href="logout.php" style="display: block; width: 100%; margin-top: 15px; background: #dc2626; color: white; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);">Log Out</a>
            </div>
        </aside>

        <!-- AREA KONTEN UTAMA TENGAH -->
        <main class="main-content">
            <div class="content-header">
                <h1>Detail Akun Pengguna</h1>
            </div>

            <!-- KARTU RINGKASAN PROFIL USER -->
            <div class="profile-card">
                <div class="profile-avatar-big"><?php echo $inisial_tampilan; ?></div>
                <div class="profile-meta">
                    <h2><?php echo $nama_tampilan; ?></h2>
                    <p>Terdaftar Sejak: <?php echo $terdaftar_sejak; ?></p>
                    <span class="status-badge-active"><?php echo $status_akun; ?></span>
                </div>
            </div>

            <!-- DETAIL KREDENSIAL DARI DATABASE -->
            <div class="details-wrapper">
                <h3 style="font-size: 16px; font-weight: 800; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px;">Informasi Kredensial</h3>
                
                <div class="details-grid">
                    <div class="detail-box">
                        <label>Nama Lengkap</label>
                        <div class="value-text"><?php echo $nama_tampilan; ?></div>
                    </div>
                    <div class="detail-box">
                        <label>Username Sistem</label>
                        <div class="value-text">@<?php echo $username_tampilan; ?></div>
                    </div>
                    <div class="detail-box">
                        <label>Alamat Email</label>
                        <div class="value-text"><?php echo $email_tampilan; ?></div>
                    </div>
                    <div class="detail-box">
                        <label>Waktu Login Terakhir</label>
                        <div class="value-text"><?php echo $login_terakhir; ?></div>
                    </div>
                </div>
            </div>
        </main>

    </div>

</body>
</html>
