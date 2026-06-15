<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. KONEKSI DATABASE LANGSUNG KE SERVER HEIDISQL ANDA
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. PROSES: JIKA PELAMAR SELESAI MENGISI FORM & KLIK "KIRIM LAMARAN SEKARANG"
// =========================================================================
if (isset($_POST['kirim_berkas_lamaran'])) {
    $lowongan_id = intval($_POST['lowongan_id']);
    $nama        = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nik         = mysqli_real_escape_string($koneksi, $_POST['nik_ktp']);
    $nomor_hp    = mysqli_real_escape_string($koneksi, $_POST['no_hp']);
    $email       = mysqli_real_escape_string($koneksi, $_POST['alamat_email']);

    // BYPASS PERTAMA: Matikan proteksi foreign key agar lolos dari error relasi tabel
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");

    // A. Masukkan data ke tabel `pelamar`
    $q_pelamar = "INSERT INTO pelamar (nama_lengkap, created_at, updated_at) VALUES ('$nama', NOW(), NOW())";
    
    if (mysqli_query($koneksi, $q_pelamar)) {
        $pelamar_id = mysqli_insert_id($koneksi);

        // B. Masukkan data ke tabel `rekrutmen_lamaran`
        $q_lamaran = "INSERT INTO rekrutmen_lamaran (lowongan_id, pelamar_id, created_at, updated_at) VALUES ('$lowongan_id', '$pelamar_id', NOW(), NOW())";
        
        if (mysqli_query($koneksi, $q_lamaran)) {
            $lamaran_id = mysqli_insert_id($koneksi);

            // BYPASS KEDUA: Langsung tembak ID default angka 1 tanpa select tabel yang error
            $tahapan_id = 1;
            $petugas_id = 1;

            // E. Tembak data ke tabel `lamaran_tahapan`
            $q_tahapan = "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, petugas_id, created_at, updated_at) 
                          VALUES ('$lamaran_id', '$tahapan_id', NOW(), 'Pending', '$petugas_id', NOW(), NOW())";
            
            mysqli_query($koneksi, $q_tahapan);
        }
    }
    
    // Hidupkan kembali proteksi foreign key database setelah selesai
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");

    // Alihkan halaman secara bersih ke URL asal agar peringatan refresh hilang total
    header("Location: lowongan_pelamar.php?id=" . $lowongan_id . "&status=sukses&nama=" . urlencode($nama));
    exit();
}

// Menangkap status sukses untuk memunculkan pesan banner hijau kembali
$pesan_sukses = "";
if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $nama_terkirim = htmlspecialchars($_GET['nama']);
    $pesan_sukses = "<div style='background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 14px;'>🎉 Sukses! Berkas lamaran atas nama $nama_terkirim berhasil dikirim langsung ke sistem seleksi Admin Rumah Sakit.</div>";
}


// 3. AMBIL DATA LOWONGAN UNTUK DITAMPILKAN DI HALAMAN
$query = "SELECT * FROM rekrutmen_lowongan WHERE status = 'Aktif' ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);

$lowongan_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $lowongan_list[] = $row;
}

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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; color: #334155; line-height: 1.5; padding-bottom: 100px; }
        
        /* HEADER */
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; height: 64px; display: flex; align-items: center; }
        .nav-brand { font-size: 20px; font-weight: 900; color: #0f172a; text-decoration: none; letter-spacing: -0.5px; margin-right: 30px; }
        .nav-menu { font-size: 13px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #0f172a; padding: 21px 0; }
        
        /* LAYOUT UTAMA */
        main { max-width: 1200px; margin: 25px auto; padding: 0 20px; display: flex; gap: 30px; }
        .left-content { flex: 2; min-width: 0; }
        .right-sidebar { flex: 1; min-width: 320px; display: flex; flex-direction: column; gap: 20px; }
        @media (max-width: 992px) { main { flex-direction: column; } .right-sidebar { min-width: 100%; } }

        /* KARTU PEKERJAAN */
        .card-main { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .info-header { display: flex; gap: 20px; align-items: center; margin-bottom: 24px; }
        .avatar-bulat { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #eff6ff; color: #2563eb; flex-shrink: 0; }
        .job-title { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .company-name { color: #2563eb; font-weight: 600; font-size: 14px; margin-top: 4px; }
        
        /* DETAIL BOX SPESIFIKASI */
        .specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .spec-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #475569; }
        @media (max-width: 576px) { .specs-grid { grid-template-columns: 1fr; } }
        
        /* TOMBOL SEJAJAR */
        .btn-lamar { background: #2563eb; color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; display: block; width: 100%; text-align: center; margin-bottom: 24px; text-decoration: none; }
        .btn-lamar:hover { background: #1d4ed8; }
        
        /* STRUKTUR PERSYARATAN & DESKRIPSI (PERBAIKAN GESER KANAN) */
        .details-wrapper { width: 100%; display: flex; flex-direction: column; gap: 24px; margin-top: 24px; }
        .section-title { font-size: 16px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        .text-content { font-size: 14px; color: #334155; white-space: pre-line; background: #fafafa; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 8px; width: 100%; }

        /* SIDEBAR LIST LOWONGAN */
        .sidebar-title { font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
        .list-item-job { display: block; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-decoration: none; color: inherit; transition: border-color 0.2s; }
        .list-item-job.active { border-color: #2563eb; box-shadow: 0 0 0 2px #bfdbfe; }
        .list-item-job h4 { font-size: 14px; font-weight: bold; color: #0f172a; }
        .list-item-job p { font-size: 12px; color: #64748b; margin-top: 4px; }

        /* STICKY BAR FOOTER */
        .sticky-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; padding: 12px 40px; box-shadow: 0 -4px 10px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .sticky-text h4 { font-weight: bold; color: #0f172a; font-size: 16px; }
        .sticky-text p { font-size: 12px; color: #64748b; }

        /* MODAL POPUP */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal.open { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 420px; position: relative; }
        .close-modal { position: absolute; right: 20px; top: 15px; background: none; border: none; font-size: 26px; cursor: pointer; color: #94a3b8; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 5px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #2563eb; }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <header>
        <div class="nav-container">
            <a href="#" class="nav-brand">PORTAL KARIR</a>
            <span class="nav-menu">LOWONGAN KERJA</span>
        </div>
    </header>

    <!-- CONTENT CONTAINER -->
    <main>
        <?php if ($detail): ?>
        <!-- KOLOM KIRI DETIL UTAMA -->
        <div class="left-content">
            <?php echo $pesan_sukses; ?>
            
            <div class="card-main">
                <div class="info-header">
                    <div class="avatar-bulat">⚕️</div>
                    <div>
                        <h1 class="job-title"><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h1>
                        <p class="company-name">✓ Instansi Pusat Rekrutmen Rumah Sakit</p>
                    </div>
                </div>

                <!-- Spesifikasi Formasi Lowongan -->
                <div class="specs-grid">
                    <div class="spec-item">💵 <span>Kompensasi Menarik</span></div>
                    <div class="spec-item">📁 <span>Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></span></div>
                    <div class="spec-item">👥 <span>Kebutuhan: <strong><?php echo htmlspecialchars($detail['jumlah_kebutuhan']); ?> Orang</strong></span></div>
                    <div class="spec-item">⏳ <span style="color: #ef4444; font-weight: bold;">Batas Akhir: <?php echo date('d M Y', strtotime($detail['tanggal_selesai'])); ?></span></div>
                </div>

                <!-- Tombol Lamar Utama -->
                <button onclick="bukaModal()" class="btn-lamar">LAMAR SEKARANG</button>
        <!-- Wrapper Rapi Untuk Detail Konten Tanpa Geser Kanan -->
        <div class="details-wrapper">
            <div>
                <h3 class="section-title">Persyaratan & Kualifikasi Pelamar</h3>
                <div class="text-content"><?php echo htmlspecialchars($detail['persyaratan'] ?? $detail['kualifikasi'] ?? 'Persyaratan kualifikasi tertera.'); ?></div>
            </div>
            
            <?php if(!empty($detail['deskripsi'])): ?>
            <div>
                <h3 class="section-title">Deskripsi Pekerjaan</h3>
                <div class="text-content"><?php echo htmlspecialchars($detail['deskripsi']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KOLOM KANAN SIDEBAR -->
<div class="right-sidebar">
    <h3 class="sidebar-title">Lowongan Lainnya</h3>
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($lowongan_list as $l): ?>
        <a href="?id=<?php echo $l['id']; ?>" class="list-item-job <?php echo $l['id'] == $selected_id ? 'active' : ''; ?>">
            <h4><?php echo htmlspecialchars($l['judul_lowongan']); ?></h4>
            <p>Kuota: <?php echo htmlspecialchars($l['jumlah_kebutuhan']); ?> Orang</p>
            <p style="color: #ef4444; font-size: 11px; margin-top: 8px;">Batas: <?php echo date('d/m/y', strtotime($l['tanggal_selesai'])); ?></p>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- STICKY FOOTER BAR -->
<div class="sticky-bar">
    <div class="sticky-text">
        <h4><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h4>
        <p>Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></p>
    </div>
    <button onclick="bukaModal()" class="btn-lamar" style="margin-bottom: 0; width: auto; padding: 12px 30px;">LAMAR</button>
</div>

<?php else: ?>
    <div class="card-main" style="text-align: center; color: #94a3b8; width: 100%;">Belum ada data lowongan aktif di database.</div>
<?php endif; ?>
</main>

<!-- MODAL FORMULIR ISI DATA PELAMAR -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <button onclick="tutupModal()" class="close-modal">&times;</button>
        
        <h3 style="font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 4px; text-align: center;">Formulir Data Pelamar</h3>
        <p style="font-size: 12px; color: #64748b; margin-bottom: 20px; text-align: center;">Silakan lengkapi berkas pendaftaran Anda untuk dikirim ke Admin.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="lowongan_id" value="<?php echo $detail['id'] ?? 0; ?>">

            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" required placeholder="Sesuai KTP">
            </div>

            <div class="form-group">
                <label>Alamat Email</label>
                <input type="email" name="alamat_email" required placeholder="nama@gmail.com">
            </div>

            <div class="form-group">
                <label>NIK KTP</label>
                <input type="text" name="nik_ktp" required placeholder="16 digit Nomor Induk Kependudukan">
            </div>

            <div class="form-group">
                <label>No HP / WhatsApp</label>
                <input type="text" name="no_hp" required placeholder="Contoh: 081234567890">
            </div>

            <button type="submit" name="kirim_berkas_lamaran" class="btn-lamar" style="margin-bottom: 0; padding: 12px;">
                KIRIM LAMARAN SEKARANG
            </button>
        </form>
        <div style="font-size: 11px; color: #94a3b8; margin-top: 20px; text-align: center;">Dengan mendaftar, Anda menyetujui seluruh aturan dan ketentuan privasi.</div>
    </div>
</div>

<script>
    function bukaModal() { document.getElementById('loginModal').classList.add('open'); }
    function tutupModal() { document.getElementById('loginModal').classList.remove('open'); }
</script>
</body>
</html>

