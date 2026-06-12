<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE (Sesuaikan dengan XAMPP Anda)
$host     = "10.10.6.59"; // Jika database di server, ganti dengan IP '10.10.6.59' seperti di gambar
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

// Jika database gagal terhubung
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. AMBIL DATA USER SECARA DINAMIS BERDASARKAN SESSION LOGIN
// Kita siapkan nama cadangan 'Administrator' jika Anda membuka halaman tanpa login terlebih dahulu
$nama_tampilan = "Administrator";

if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    
    // Query untuk mengambil kolom 'nama' berdasarkan 'username' dari tabel 'users'
    $query = "SELECT nama FROM users WHERE username = '$username_aktif'";
    $hasil = mysqli_query($koneksi, $query);
    
    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data_user = mysqli_fetch_assoc($hasil);
        $nama_tampilan = $data_user['nama']; // Menyimpan nama asli user (Varchar 200)
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
        .main-content { 
    flex: 1; 
    background: #fbfbfd; 
    padding: 40px 50px; /* Menambah ruang di sisi kiri dan kanan halaman agar luas */
    display: flex; 
    flex-direction: column; 
    gap: 32px; 
    overflow-y: auto; 
}
.content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        /* Banner Sambutan Ungu */
.welcome-banner { 
    background: #4f46e5; 
    border-radius: 24px; 
    padding: 35px 40px; /* Jarak teks ke tepi boks diganti menjadi lebih luas */
    color: #ffffff; 
    position: relative; 
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
    margin-top: 10px; /* Jarak dari teks judul Dashboard */
}
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

        /* Panel Samping Kanan (Kalender & New Applicants) */
        .sidebar-right { width: 300px; background: #ffffff; border-left: 1px solid #f1f5f9; padding: 35px; flex-shrink: 0; display: flex; flex-direction: column; gap: 30px; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 15px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px 5px; text-align: center; font-size: 11px; font-weight: 700; }
        .day-name { color: #94a3b8; padding-bottom: 5px; }
        .day-num { color: #1e293b; padding: 4px 0; }
        .day-num.muted { color: #cbd5e1; }
        .applicant-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
        .applicant-avatar { width: 36px; height: 36px; background: #f5f3ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
        .applicant-info h5 { font-size: 13px; font-weight: 700; color: #1e293b; }
        .applicant-info p { font-size: 10px; color: #94a3b8; margin-top: 1px; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- SIDEBAR MENU KIRI -->
        <aside class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="#" class="menu-item active">Dashboard</a>
                    <a href="#" class="iser active">
                </nav>
            </div>
            <!-- KOTAK OPSI LOGOUT -->
<div class="support-card" style="background: #fff5f5; border: 1px solid #fee2e2; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px;">
    <a href="logout.php" style="display: block; width: 100%; margin-top: 15px; background: #dc2626; color: white; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);">Log Out</a>
</div>
        </aside>

        <!-- AREA KONTEN UTAMA TENGAH -->
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
            </div>

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
                    </tbody>
                </table>
            </div>
        </main>
    </div>

</body>
</html>
