<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. KONEKSI DATABASE
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. AMBIL DATA LOWONGAN
$query = "SELECT * FROM rekrutmen_lowongan WHERE status = 'Aktif' ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);

$lowongan_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lowongan_list[] = $row;
}

// ID Lowongan terpilih
$selected_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($lowongan_list[0]['id']) ? $lowongan_list[0]['id'] : 0);

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
    <style>
        /* CSS INTERNAL SUPER RINGAN - NO INTERNET REQUIRED FOR LAYOUT */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; color: #334155; line-height: 1.5; }
        
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; h-height: 64px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-size: 24px; font-weight: 900; color: #2563eb; text-decoration: none; }
        .logo span { font-size: 12px; color: #64748b; font-weight: normal; margin-left: 4px; }
        .nav-menu { display: flex; gap: 20px; font-size: 13px; font-weight: bold; }
        .nav-menu a { color: #475569; text-decoration: none; }
        .btn-masuk { border: 1px solid #2563eb; color: #2563eb; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; background: white; }
        .btn-masuk:hover { background: #f0f5ff; }

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
        .btn-lamar:hover { background: #1d4ed8; }

        .section-title { font-size: 16px; font-weight: bold; color: #0f172a; margin-bottom: 12px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .text-content { font-size: 14px; color: #334155; white-space: pre-line; background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #f0f0f0; }

        .right-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .sidebar-title { font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
        .list-item-job { display: block; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-decoration: none; color: inherit; transition: all 0.2s; }
        .list-item-job.active { border-color: #2563eb; box-shadow: 0 0 0 2px #bfdbfe; }
        .list-item-job h4 { font-size: 14px; font-weight: bold; color: #0f172a; }
        .list-item-job p { font-size: 12px; color: #64748b; margin-top: 4px; }

        .sticky-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; padding: 12px 40px; box-shadow: 0 -4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; z-index: 100; }

        /* MODAL LOGIN STYLE */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal.open { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 360px; position: relative; text-align: center; }
        .close-modal { position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
        .btn-google { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; border: 1px solid #cbd5e1; background: white; padding: 10px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 20px; text-decoration: none; color: #334155; }
        .btn-google:hover { background: #f8fafc; }
        .btn-apple { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; background: #000; color: white; padding: 10px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 10px; border: none; }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS (GLINTS STYLE) -->
    <header>
        <div class="nav-container">
            <div style="display: flex; align-items: center; gap: 30px;">
                <a href="#" class="nav-brand logo">glints<span>TapLoker</span></a>
                <div class="nav-menu">
                    <a href="#" style="color: #0f172a; border-bottom: 2px solid #0f172a; padding-bottom: 20px; margin-top: 20px;">LOWONGAN KERJA</a>
                </div>
            </div>
            <div>
                <button onclick="bukaModal()" class="btn-masuk">MASUK / DAFTAR</button>
            </div>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main>
        <?php if ($detail): ?>
        <!-- KOLOM KIRI: DETAIL LOWONGAN -->
        <div>
            <div class="card-main">
                <div class="info-header">
                    <div class="avatar-bulat">⚕️</div>
                    <div>
                        <h1 class="job-title"><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h1>
                        <p class="company-name">✓ Instansi Pusat Rekrutmen</p>
                    </div>
                </div>

                <div class="specs-list">
                    <div class="spec-item">💵 <span>Kompensasi Menarik</span></div>
                    <div class="spec-item">📁 <span>Kode: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></span></div>
                    <div class="spec-item">👥 <span>Kebutuhan: <strong><?php echo htmlspecialchars($detail['jumlah_kebutuhan']); ?> Orang</strong></span></div>
                    <div class="spec-item">⏳ <span style="color: #ef4444; font-weight: bold;">Batas: <?php echo date('d M Y', strtotime($detail['tanggal_selesai'])); ?></span></div>
                </div>

                <button onclick="bukaModal()" class="btn-lamar">LAMAR SEKARANG</button>

                <div class="section-title">Persyaratan & Kualifikasi Pelamar</div>
                <div class="text-content"><?php echo htmlspecialchars($detail['persyaratan'] ?? $detail['kualifikasi'] ?? 'Persyaratan tidak diisi.'); ?></div>
                
                <?php if(!empty($detail['deskripsi'])): ?>
                    <div class="section-title">Deskripsi Pekerjaan</div>
                    <div class="text-content"><?php echo htmlspecialchars($detail['deskripsi']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KOLOM KANAN: LOWONGAN LAIN -->
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

        <!-- STICKY BAR BAWAH -->
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

    <!-- MODAL POPUP SOSIAL LOGIN -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button onclick="tutupModal()" class="close-modal">&times;</button>
            <h3 style="font-size: 18px; font-weight: bold; color: #0f172a;">Mulai Lamaran Anda</h3>
            <p style="font-size: 12px; color: #64748b; margin-top: 4px;">Masuk instan untuk mengirim data langsung ke admin</p>
            
            <a href="auth-google.php" class="btn-google">
                <img src="https://wikimedia.org" style="width: 16px; height: 16px;" alt="G">
                Lanjutkan dengan Google
            </a>
            
            <button onclick="alert('Fitur Apple Sign-In memerlukan Apple Developer Account')" class="btn-apple">
                🍏 Lanjutkan dengan Apple
            </button>
        </div>
    </div>

    <script>
        function bukaModal() { 
            document.getElementById('loginModal').classList.add('open'); 
        }
        function tutupModal() { 
            document.getElementById('loginModal').classList.remove('open'); 
        }
    </script>
</body>
</html>
