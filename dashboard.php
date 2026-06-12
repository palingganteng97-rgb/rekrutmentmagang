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

// 2. INISIALISASI NILAI DEFAULT CADANGAN (Jika Session Belum Terisi)
$nama_tampilan = "Administrator";
$email_tampilan = "admin@magang.id";
$inisial_tampilan = "A";

// 3. LOGIKA AKTIF PENARIKAN DATA USER DARI DATABASE HEIDISQL
if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    
    // Mengambil kolom nama dan email sekaligus berdasarkan username pelamar/admin
    $query = "SELECT nama, email FROM users WHERE username = '$username_aktif'";
    $hasil = mysqli_query($koneksi, $query);
    
    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_user = mysqli_fetch_assoc($hasil);
        $nama_tampilan = $data_user['nama'];
        $email_tampilan = $data_user['email'];
        // Mengambil huruf pertama dari kolom nama sebagai avatar profile bulat
        $inisial_tampilan = strtoupper(substr($nama_tampilan, 0, 1));
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
        /* CSS INTERNAL UTUH - JAMINAN RAPI 100% SECARA OFFLINE */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        /* Layout Grid Pembagi Utama */
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); display: flex; min-height: 850px; overflow: hidden; }
        
        /* Menu Navigasi Sidebar Kiri */
        .sidebar-left { width: 260px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        .support-card { background: #f5f3ff; padding: 24px; border-radius: 20px; text-align: center; margin-top: 20px; }
        .support-card h4 { font-size: 14px; color: #1e293b; font-weight: 700; }
        .support-card p { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .support-card button { width: 100%; margin-top: 15px; background: #4f46e5; color: white; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }

        /* Area Konten Utama */
        .main-content { flex: 1; background: #fbfbfd; padding: 40px; display: flex; flex-direction: column; gap: 30px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Banner Sambutan Ungu */
        .welcome-banner { background: #4f46e5; border-radius: 24px; padding: 30px; color: #ffffff; position: relative; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15); }
        .welcome-banner h2 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .welcome-banner p { font-size: 14px; opacity: 0.9; line-height: 1.6; max-width: 500px; }
        .welcome-banner a { color: white; font-weight: bold; font-size: 13px; margin-top: 12px; display: inline-block; }
        
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
                <!-- SIDEBAR MENU KIRI DENGAN FITUR USER AKTIF -->
        <aside class="sidebar-left" style="width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0;">
            <div>
                <!-- Logo Aplikasi -->
                <div class="brand-logo" style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px;">
                    <span style="width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block;"></span>impozitions
                </div>
                
                <!-- KOTAK PROFIL USER OTOMATIS TERHUBUNG DATABASE -->
                                <!-- BOKS PROFIL USER SEKARANG BISA DIKLIK (LINK KE USER.PHP) -->
                <a href="user.php" style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 20px; margin-bottom: 25px; border: 1px solid #f1f5f9; width: 100%; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f0fdf4'; this.style.borderColor='#bbf7d0';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#f1f5f9';">
                    <!-- Foto Inisial Bulat -->
                    <div style="width: 44px; height: 44px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);">
                        <?php echo isset($inisial_tampilan) ? $inisial_tampilan : 'A'; ?>
                    </div>
                    <!-- Detail Nama & Status -->
                    <div style="overflow: hidden; flex: 1; text-align: left;">
                        <h4 style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 145px; text-transform: capitalize;">
                            <?php echo isset($nama_tampilan) ? $nama_tampilan : 'Admin'; ?>
                        </h4>
                        <p style="font-size: 11px; color: #94a3b8; margin-top: 2px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 145px;">
                            <?php echo isset($email_tampilan) ? $email_tampilan : 'admin@magang.id'; ?>
                        </p>
                    </div>
                </a>

                <!-- Navigasi Menu -->
                <nav class="menu-list">
                    <a href="#" class="menu-item active">Dashboard</a>
                </nav>
            </div>
            
            <!-- TOMBOL LOGOUT MERAH -->
            <div class="support-card" style="background: #fff5f5; border: 1px solid #fee2e2; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px;">
                <h4 style="font-size: 14px; color: #991b1b; font-weight: 700;">Sesi Akun</h4>
                <p style="font-size: 11px; color: #b91c1c; margin-top: 4px;">Keluar dari dashboard</p>
                <a href="logout.php" style="display: block; width: 100%; margin-top: 15px; background: #dc2626; color: white; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);">Log Out</a>
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
                    <div class="job-card">
                        <div>
                            <div class="qty">3</div>
                            <div class="title">Content Designers</div>
                            <div class="desc">(5 candidates)</div>
                        </div>
                        <div class="percentage-ring">75%</div>
                    </div>
                    <div class="job-card">
                        <div>
                            <div class="qty">9</div>
                            <div class="title">Node.js Developers</div>
                            <div class="desc">(12 candidates)</div>
                        </div>
                        <div class="percentage-ring" style="border-top-color: #f43f5e; color: #f43f5e;">25%</div>
                    </div>
                    <div class="job-card">
                        <div>
                            <div class="qty">1</div>
                            <div class="title">Senior UI Designer</div>
                            <div class="desc">(0 candidates)</div>
                        </div>
                        <div class="percentage-ring" style="border-top-color: #cbd5e1; color: #94a3b8;">0%</div>
                    </div>
                    <div class="job-card">
                        <div>
                            <div class="qty">2</div>
                            <div class="title">Marketing Managers</div>
                            <div class="desc">(10 candidates)</div>
                        </div>
                        <div class="percentage-ring">45%</div>
                    </div>
                </div>
            </section>

            <!-- Bagian Tabel Progress Pelamar -->
            <div class="table-wrapper">
                <div class="section-header">
                    <div class="section-title">Recruitment progress</div>
                    <a href="#" class="see-all-link">see all</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Profession</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="candidate-name">John Doe</td>
                            <td style="color: #64748b;">UI Designer</td>
                            <td><div class="status-pill"><div class="status-dot"></div>Tech interview</div></td>
                        </tr>
                        <tr>
                            <td class="candidate-name">Ella Clinton</td>
                            <td style="color: #64748b;">Content designer</td>
                            <td><div class="status-pill"><div class="status-dot" style="background:#ec4899;"></div>Task</div></td>
                        </tr>
                        <tr>
                            <td class="candidate-name">Mike Tyler</td>
                            <td style="color: #64748b;">Node.js Developer</td>
                            <td><div class="status-pill"><div class="status-dot" style="background:#06b6d4;"></div>Resume review</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <!-- PANEL INFORMASI SISI KANAN -->
        <aside class="sidebar-right">
            <div>
                
            </div>

            <div>
            </div>
        </aside>

    </div>

</body>
</html>
