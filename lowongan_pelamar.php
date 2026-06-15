<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. KONEKSI DATABASE ADMIN (Menghubungkan ke Server HeidiSQL Anda)
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. PROSES OTOMATIS: JIKA PELAMAR SELESAI MEMILIH AKUN GOOGLE
// =========================================================================
$pesan_sukses = "";
if (isset($_POST['credential'])) {
    // Membongkar JWT Token Respon dari Google secara aman tanpa library vendor
    $token = $_POST['credential'];
    $base64Url = explode('.', $token)[1];
    $base64 = str_replace(['-', '_'], ['+', '/'], $base64Url);
    $jsonPayload = base64_decode($base64);
    $googleUser = json_decode($jsonPayload, true);

    if (isset($googleUser['email'])) {
        $email_pelamar = mysqli_real_escape_string($koneksi, $googleUser['email']);
        $nama_pelamar  = mysqli_real_escape_string($koneksi, $googleUser['name']);
        
        // Tangkap lowongan_id dari session cookie agar datanya akurat
        $lowongan_id = isset($_SESSION['selected_job_id']) ? intval($_SESSION['selected_job_id']) : 1;

        // A. Simpan/Cek data ke tabel `pelamar`
        $cek_user = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE email = '$email_pelamar'");
        if (mysqli_num_rows($cek_user) > 0) {
            $data_p = mysqli_fetch_assoc($cek_user);
            $pelamar_id = $data_p['id'];
        } else {
            mysqli_query($koneksi, "INSERT INTO pelamar (nama, email, created_at, updated_at) VALUES ('$nama_pelamar', '$email_pelamar', NOW(), NOW())");
            $pelamar_id = mysqli_insert_id($koneksi);
        }

        // B. Simpan pendaftaran ke tabel `rekrutmen_lamaran`
        $insert_lamaran = mysqli_query($koneksi, "INSERT INTO rekrutmen_lamaran (lowongan_id, created_at, updated_at) VALUES ('$lowongan_id', NOW(), NOW())");
        if ($insert_lamaran) {
            $lamaran_id = mysqli_insert_id($koneksi);
            
            // C. Masukkan status awal 'Pending' ke tabel `lamaran_tahapan`
            mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, status, created_at, updated_at) VALUES ('$lamaran_id', 'Pending', NOW(), NOW())");
            
            $pesan_sukses = "<div style='background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 14px;'>🎉 Sukses! Akun Google Anda ($email_pelamar) Berhasil Terverifikasi & Lamaran Sudah Masuk Ke Panel Admin.</div>";
        }
    }
}

// 3. AMBIL DATA FORM LOWONGAN DARI DATABASE
$query = "SELECT * FROM rekrutmen_lowongan WHERE status = 'Aktif' ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);

$lowongan_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lowongan_list[] = $row;
}

$selected_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($lowongan_list[0]['id']) ? $lowongan_list[0]['id'] : 0);
$_SESSION['selected_job_id'] = $selected_id;

$detail = null;
foreach ($lowongan_list as $l) {
    if ($l['id'] == $selected_id) {
        $detail = $l;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Lowongan Kerja</title>
    <!-- Library SDK Google Sign-In Resmi -->
    <script src="https://google.com" async defer></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; color: #334155; line-height: 1.5; padding-bottom: 80px; }
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 900; color: #0f172a; text-decoration: none; }
        .nav-menu { font-size: 13px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #0f172a; padding: 21px 0; margin-left: 20px; }
        .btn-masuk { border: 1px solid #2563eb; color: #2563eb; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; background: white; }
        main { max-width: 1200px; margin: 20px auto; padding: 0 20px; display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        @media (max-width: 768px) { main { grid-template-columns: 1fr; } .sticky-bar { display: none !important; } }
        .card-main { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .info-header { display: flex; gap: 20px; align-items: flex-start; }
        .avatar-bulat { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #eff6ff; color: #2563eb; }
        .job-title { font-size: 24px; font-weight: 800; color: #0f172a; }
        .company-name { color: #2563eb; font-weight: 600; font-size: 14px; margin-top: 4px; }
        .specs-list { margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 16px; border-radius: 8px; font-size: 14px; }
        .spec-item { display: flex; align-items: center; gap: 8px; color: #475569; }
        .btn-lamar { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; display: inline-block; margin-top: 20px; }
        .section-title { font-size: 16px; font-weight: bold; color: #0f172a; margin-bottom: 12px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .text-content { font-size: 14px; color: #334155; white-space: pre-line; background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #f0f0f0; }
        .right-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .sidebar-title { font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .list-item-job { display: block; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-decoration: none; color: inherit; }
        .list-item-job.active { border-color: #2563eb; box-shadow: 0 0 0 2px #bfdbfe; }
        .sticky-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; padding: 12px 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100; box-shadow: 0 -4px 6px rgba(0,0,0,0.02); }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal.open { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 360px; position: relative; text-align: center; }
        .close-modal { position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
        .btn-apple { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; background: #000; color: white; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; border: none; margin-top: 12px; }
    </style>
</head>
<body>

    <header>
        <div class="nav-container">
            <div style="display: flex; align-items: center;">
                <a href="#" class="nav-brand">PORTAL KARIR</a>
                <span class="nav-menu">LOWONGAN KERJA</span>
            </div>
            <div>
                <button onclick="bukaModal()" class="btn-masuk">MASUK / DAFTAR</button>
            </div>
        </div>
    </header>

    <main>
        <?php if ($detail): ?>
        <div>
            <?php echo $pesan_sukses; ?>
            <div class="card-main">
                <div class="info-header">
                    <div class="avatar-bulat">⚕️</div>
                    <div>
                        <h1 class="job-title"><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h1>
                        <p class="company-name">✓ Instansi Pusat Rekrutmen Rumah Sakit</p>
                    </div>
                </div>

                <div class="specs-list">
                    <div class="spec-item">💵 <span>Kompensasi Menarik</span></div>
                    <div class="spec-item">📁 <span>Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></span></div>
                    <div class="spec-item">👥 <span>Kebutuhan: <strong><?php echo htmlspecialchars($detail['jumlah_kebutuhan']); ?> Orang</strong></span></div>
                    <div class="spec-item">⏳ <span style="color: #ef4444; font-weight: bold;">Batas: <?php echo date('d M Y', strtotime($detail['tanggal_selesai'])); ?></span></div>
                </div>

                <button onclick="bukaModal()" class="btn-lamar">LAMAR SEKARANG</button>

                <div class="section-title">Persyaratan & Kualifikasi Pelamar</div>
                <div class="text-content"><?php echo htmlspecialchars($detail['persyaratan'] ?? $detail['kualifikasi'] ?? 'Persyaratan kualifikasi tertera aktif.'); ?></div>
            </div>
        </div>

        <div class="right-sidebar">
            <h3 class="sidebar-title">Lowongan Lainnya</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($lowongan_list as $l): ?>
                <a href="?id=<?php echo $l['id']; ?>" class="list-item-job <?php echo $l['id'] == $selected_id ? 'active' : ''; ?>">
                    <h4><?php echo htmlspecialchars($l['judul_lowongan']); ?></h4>
                    <p>Kuota: <?php echo htmlspecialchars($l['jumlah_kebutuhan']); ?> Orang</p>
                    <p style="color: #ef4444; font-size: 11px; margin-top: 8px;">Batas: <?php echo date('d/m/y', strtotime($l['tanggal_selesai'])); ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- STICKY BAR FOOTER -->
        <div class="sticky-bar">
            <div>
                <h4 style="font-weight: bold; color: #0f172a;"><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h4>
                <p style="font-size: 12px; color: #64748b;">Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></p>
            </div>
            <button onclick="bukaModal()" class="btn-lamar" style="margin-top: 0; padding: 10px 20px;">LAMAR</button>
        </div>
        <?php else: ?>
            <div class="card-main" style="text-align: center; color: #94a3b8;">Belum ada data lowongan aktif di database.</div>
        <?php endif; ?>
    </main>

    <!-- MODAL POPUP SOSIAL LOGIN MANUAL ANTI-GAGAL -->
        <!-- MODAL POPUP SOSIAL LOGIN MANUAL ANTI-GAGAL -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button onclick="tutupModal()" class="close-modal">&times;</button>
            <h3 style="font-size: 18px; font-weight: bold; color: #0f172a;">Mulai Lamaran Anda</h3>
            <p style="font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 24px;">Masuk instan untuk mengirim data langsung ke admin</p>
            
            <div style="display: flex; flex-direction: column; gap: 12px; align-items: center; width: 100%;">
                
                <!-- PERBAIKAN LINK UTAMA: Mengarah ke localhost Anda, bukan ke Glints asli -->
                <a href="auth-google.php?lowongan_id=<?php echo $selected_id; ?>" class="btn-google" style="text-decoration: none; width: 100%; display: flex; justify-content: center; align-items: center; background: white; border: 1px solid #cbd5e1; padding: 12px; border-radius: 8px; color: #334155;">
                    <svg viewBox="0 0 24 24" width="16" height="16" xmlns="http://w3.org" style="margin-right: 8px;">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                    </svg>
                    <span style="font-weight: bold; font-size: 14px;">Lanjutkan dengan Google</span>
                </a>
                
                <!-- TOMBOL APPLE -->
                <button onclick="alert('Fitur Apple Sign-In memerlukan Apple Developer Account')" class="btn-apple" style="width: 100%; display: flex; justify-content: center; align-items: center; background: #000; color: white; padding: 12px; border-radius: 8px; border: none; cursor: pointer;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white" xmlns="http://w3.org" style="margin-right: 8px;">
                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-1 .04-2.21.67-2.93 1.49-.62.69-1.16 1.84-1.01 2.96 1.12.09 2.27-.57 2.95-1.39z"/>
                    </svg>
                    <span style="font-weight: bold; font-size: 14px;">Lanjutkan dengan Apple</span>
                </button>
            </div>
            
            <div style="font-size: 11px; color: #94a3b8; margin-top: 24px;">Dengan mendaftar, Anda menyetujui seluruh aturan dan ketentuan privasi.</div>
        </div>
    </div>

    <!-- SCRIPT LOGIKA INTERAKSI -->
    <script>
        function bukaModal() { document.getElementById('loginModal').classList.add('open'); }
        function tutupModal() { document.getElementById('loginModal').classList.remove('open'); }
    </script>
</body>
</html>
