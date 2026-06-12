<?php 
session_start(); 

// 1. KONEKSI KE SERVER DATABASE (Sesuai IP HeidiSQL Anda)
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; // Silakan isi jika server database Anda menggunakan password
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. AMBIL DATA DINAMIS BERDASARKAN USER YANG SEDANG LOGIN
$nama_tampilan     = "Administrator";
$username_tampilan = "admin";
$email_tampilan    = "admin@magang.id";
$status_akun       = "Aktif";
$login_terakhir    = "Baru Saja";
$terdaftar_sejak   = "12 Juni 2026";
$inisial_tampilan  = "A";

if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    
    // Ambil semua data dari tabel 'users' berdasarkan username aktif
    $query = "SELECT nama, username, email, last_login, status, created_at FROM users WHERE username = '$username_aktif'";
    $hasil = mysqli_query($koneksi, $query);

    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_user = mysqli_fetch_assoc($hasil);
        $nama_tampilan     = $data_user['nama'];
        $username_tampilan = $data_user['username'];
        $email_tampilan    = $data_user['email'];
        $status_akun       = $data_user['status'];
        
        // Memformat tampilan tanggal login terakhir dan registrasi
        $login_terakhir    = !empty($data_user['last_login']) ? date('d-m-Y H:i', strtotime($data_user['last_login'])) : 'Baru Saja';
        $terdaftar_sejak   = !empty($data_user['created_at']) ? date('d F Y', strtotime($data_user['created_at'])) : '12 Juni 2026';
        $inisial_tampilan  = strtoupper(substr($nama_tampilan, 0, 1));
    }
}
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Magang ID</title>
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
        
        /* Kartu Profil Besar */
        .profile-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 35px; display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .profile-avatar-big { width: 90px; height: 90px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 36px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); }
        .profile-meta h2 { font-size: 22px; font-weight: 800; color: #1e293b; text-transform: capitalize; }
        .profile-meta p { font-size: 14px; color: #94a3b8; margin-top: 4px; }
        .status-badge-active { display: inline-block; background: #ecfdf5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Detail Data Kredensial */
        .details-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 20px; }
        .detail-box { display: flex; flex-direction: column; gap: 6px; }
        .detail-box label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-box .value-text { font-size: 14px; font-weight: 600; color: #334155; background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #f1f5f9; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI BERSIH MINIMALIS SINKRON DASHBOARD -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                
                <!-- NAVIGASI MENU -->
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="user.php" class="menu-item active">Profil Pengguna</a>
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
                <h1>Detail Akun Pengguna</h1>
            </div>

            <!-- KARTU RINGKASAN PROFIL USER -->
            <div class="profile-card">
                <div class="profile-avatar-big"><?php echo isset($inisial_tampilan) ? $inisial_tampilan : 'A'; ?></div>
                <div class="profile-meta">
                    <h2><?php echo isset($nama_tampilan) ? $nama_tampilan : 'Administrator'; ?></h2>
                    <p>Terdaftar Sejak: <?php echo isset($terdaftar_sejak) ? $terdaftar_sejak : '12 Juni 2026'; ?></p>
                    <span class="status-badge-active"><?php echo isset($status_akun) ? $status_akun : 'Aktif'; ?></span>
                </div>
            </div>
                
                            <!-- DETAIL DATA KREDENSIAL BERBENTUK TABEL -->
                        <!-- TABEL KREDENSIAL YANG SUDAH DIRAPIKAN -->
            <div class="details-wrapper" style="background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); margin-top: 10px;">
                
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                    <thead>
                        <tr style="color: #94a3b8; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9;">
                            <th style="padding: 0 10px 16px 10px; width: 30%; letter-spacing: 0.5px;">Kategori Kredensial</th>
                            <th style="padding: 0 10px 16px 10px; width: 70%; letter-spacing: 0.5px;">Data Informasi Akun</th>
                        </tr>
                    </thead>
                    <tbody style="color: #334155;">
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 18px 10px; font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Nama Lengkap</td>
                            <td style="padding: 18px 10px; font-weight: 700; color: #1e293b;"><?php echo isset($nama_tampilan) ? $nama_tampilan : 'Administrator'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 18px 10px; font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Username Sistem</td>
                            <td style="padding: 18px 10px; font-weight: 600; color: #4f46e5;">@<?php echo isset($username_tampilan) ? $username_tampilan : 'admin'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f8fafc;">
                            <td style="padding: 18px 10px; font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Alamat Email</td>
                            <td style="padding: 18px 10px; font-weight: 600; color: #64748b;"><?php echo isset($email_tampilan) ? $email_tampilan : 'admin@magang.id'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 18px 10px; font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Login Terakhir</td>
                            <td style="padding: 18px 10px; font-weight: 600; color: #334155;"><?php echo isset($login_terakhir) ? $login_terakhir : 'Baru Saja'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

