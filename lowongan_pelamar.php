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

$pesan_notifikasi = "";

// =========================================================================
// 2. FITUR A: PROSES REGISTRASI AKUN BARU PELAMAR
// =========================================================================
if (isset($_POST['proses_register_pelamar'])) {
    $nama  = mysqli_real_escape_string($koneksi, $_POST['reg_nama']);
    $email = mysqli_real_escape_string($koneksi, $_POST['reg_email']);
    $pass  = mysqli_real_escape_string($koneksi, $_POST['reg_password']);
    
    // Cek apakah email sudah terdaftar sebelumnya
    $cek_email = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE email = '$email'");
    if (mysqli_num_rows($cek_email) > 0) {
        $pesan_notifikasi = "<div class='alert-box error'>❌ Email sudah terdaftar! Silakan langsung login.</div>";
    } else {
        // Enkripsi password demi keamanan akun pelamar
        $password_hashed = password_hash($pass, PASSWORD_DEFAULT);
        
        // Simpan ke tabel pelamar dengan menyertakan nama_lengkap, email, dan password
        $q_reg = "INSERT INTO pelamar (nama_lengkap, email, password, created_at, updated_at) VALUES ('$nama', '$email', '$password_hashed', NOW(), NOW())";
        if (mysqli_query($koneksi, $q_reg)) {
            $pesan_notifikasi = "<div class='alert-box sukses'>🎉 Registrasi Berhasil! Silakan masukkan email & password Anda untuk login.</div>";
        } else {
            $pesan_notifikasi = "<div class='alert-box error'>Gagal register: " . mysqli_error($koneksi) . "</div>";
        }
    }
}

// =========================================================================
// 3. FITUR B: PROSES LOGIN USER PELAMAR
// =========================================================================
if (isset($_POST['proses_login_pelamar'])) {
    $email = mysqli_real_escape_string($koneksi, $_POST['log_email']);
    $pass  = $_POST['log_password'];
    
    $cek_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE email = '$email'");
    if (mysqli_num_rows($cek_user) > 0) {
        $user_data = mysqli_fetch_assoc($cek_user);
        
        // Cocokkan password yang diinput dengan password terenkripsi di DB
        if (password_verify($pass, $user_data['password'])) {
            $_SESSION['user_pelamar_id']   = $user_data['id'];
            $_SESSION['user_pelamar_nama'] = $user_data['nama_lengkap'];
            $_SESSION['user_pelamar_mail'] = $user_data['email'];
            
            $pesan_notifikasi = "<div class='alert-box sukses'>🔓 Login Berhasil! Halo, " . $_SESSION['user_pelamar_nama'] . ". Silakan pilih lowongan dan melamar.</div>";
        } else {
            $pesan_notifikasi = "<div class='alert-box error'>❌ Password yang Anda masukkan salah!</div>";
        }
    } else {
        $pesan_notifikasi = "<div class='alert-box error'>❌ Email tidak ditemukan! Silakan register dahulu.</div>";
    }
}

// =========================================================================
// 4. FITUR C: PROSES JIKA USER Selesai KLIK TOMBOL "LAMAR SEKARANG"
// =========================================================================
if (isset($_POST['kirim_berkas_lamaran'])) {
    $lowongan_id = intval($_POST['lowongan_id']);
    $pelamar_id  = intval($_SESSION['user_pelamar_id']); // Mengambil ID dari session login user
    $nik         = mysqli_real_escape_string($koneksi, $_POST['nik_ktp']);
    $nomor_hp    = mysqli_real_escape_string($koneksi, $_POST['no_hp']);

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");

    // A. Masukkan data ke tabel `rekrutmen_lamaran` (Mengikat ID pelamar yang sedang login)
    $q_lamaran = "INSERT INTO rekrutmen_lamaran (lowongan_id, pelamar_id, created_at, updated_at) VALUES ('$lowongan_id', '$pelamar_id', NOW(), NOW())";
    
    if (mysqli_query($koneksi, $q_lamaran)) {
        $lamaran_id = mysqli_insert_id($koneksi);
        $tahapan_id = 1; 
        $petugas_id = 1; 

        // B. Tembak data ke tabel `lamaran_tahapan`
        $q_tahapan = "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, petugas_id, created_at, updated_at) 
                      VALUES ('$lamaran_id', '$tahapan_id', NOW(), 'Pending', '$petugas_id', NOW(), NOW())";
        
        mysqli_query($koneksi, $q_tahapan);
    }
    
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");

    header("Location: lowongan_pelamar.php?id=" . $lowongan_id . "&status=sukses&nama=" . urlencode($_SESSION['user_pelamar_nama']));
    exit();
}

// Fitur Logout Pelamar
if (isset($_GET['action']) && $_GET['action'] == 'logout_user') {
    unset($_SESSION['user_pelamar_id']);
    unset($_SESSION['user_pelamar_nama']);
    unset($_SESSION['user_pelamar_mail']);
    header("Location: lowongan_pelamar.php");
    exit();
}

// Menangkap alert redirect setelah sukses melamar
if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $nama_terkirim = htmlspecialchars($_GET['nama']);
    $pesan_notifikasi = "<div class='alert-box sukses'>🎉 Sukses! Berkas lamaran atas nama $nama_terkirim berhasil dikirim langsung ke sistem seleksi Admin Rumah Sakit.</div>";
}

// 5. AMBIL DATA LOWONGAN UNTUK DITAMPILKAN
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
        
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 900; color: #0f172a; text-decoration: none; letter-spacing: -0.5px; }
        .user-nav-status { font-size: 13px; font-weight: bold; color: #4f46e5; }
        .btn-logout-header { font-size: 12px; color: #ef4444; text-decoration: none; margin-left: 10px; font-weight: normal; }
        
        main { max-width: 1200px; margin: 25px auto; padding: 0 20px; display: flex; gap: 30px; }
        .left-content { flex: 2; min-width: 0; }
        .right-sidebar { flex: 1; min-width: 320px; display: flex; flex-direction: column; gap: 20px; }
        @media (max-width: 992px) { main { flex-direction: column; } .right-sidebar { min-width: 100%; } }

        .card-main { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .info-header { display: flex; gap: 20px; align-items: center; margin-bottom: 24px; }
        .avatar-bulat { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #eff6ff; color: #2563eb; flex-shrink: 0; }
        .job-title { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .company-name { color: #2563eb; font-weight: 600; font-size: 14px; margin-top: 4px; }
        
        .specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .spec-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #475569; }
        
        .btn-lamar { background: #2563eb; color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; display: block; width: 100%; text-align: center; margin-bottom: 24px; text-decoration: none; }
        .btn-lamar:hover { background: #1d4ed8; }
        
        .details-wrapper { width: 100%; display: flex; flex-direction: column; gap: 24px; margin-top: 24px; }
        .section-title { font-size: 16px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        .text-content { font-size: 14px; color: #334155; white-space: pre-line; background: #fafafa; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 8px; width: 100%; }

        .sidebar-title { font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
        .list-item-job { display: block; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-decoration: none; color: inherit; transition: border-color 0.2s; }
        .list-item-job.active { border-color: #2563eb; box-shadow: 0 0 0 2px #bfdbfe; }
        .list-item-job h4 { font-size: 14px; font-weight: bold; color: #0f172a; }
        .list-item-job p { font-size: 12px; color: #64748b; margin-top: 4px; }
        .sticky-bar { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; padding: 12px 40px; box-shadow: 0 -4px 10px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .sticky-text h4 { font-weight: bold; color: #0f172a; font-size: 16px; }
        .sticky-text p { font-size: 12px; color: #64748b; }

        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal.open { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 420px; position: relative; }
        .close-modal { position: absolute; right: 20px; top: 15px; background: none; border: none; font-size: 26px; cursor: pointer; color: #94a3b8; }
        
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 5px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #2563eb; }
        
        .alert-box { padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
        .alert-box.sukses { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-box.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        
        .toggle-link { font-size: 12px; color: #2563eb; cursor: pointer; display: block; text-align: center; margin-top: 15px; text-decoration: underline; }
    </style>
</head>
<body>

       <!-- NAVBAR ATAS (DENGAN UTAS DAFTAR DAN MASUK) -->
    <header>
        <div class="nav-container">
            <a href="#" class="nav-brand">PORTAL KARIR</a>
            <div>
                <?php if (isset($_SESSION['user_pelamar_nama'])): ?>
                    <span class="user-nav-status">👤 <?php echo $_SESSION['user_pelamar_nama']; ?> 
                        <a href="?action=logout_user" class="btn-logout-header">[Log Out]</a>
                    </span>
                <?php else: ?>
                    <!-- PERBAIKAN: Teks lama diganti dengan tombol interaktif -->
                    <span style="font-size: 13px; font-weight: bold; display: flex; gap: 15px; align-items: center;">
                        <span onclick="bukaModalSesuaiStatus(); pindahKeLogin();" style="color: #475569; cursor: pointer;">Masuk</span>
                        <span onclick="bukaModalSesuaiStatus(); pindahKeRegister();" style="background: #4f46e5; color: white; padding: 6px 14px; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">Daftar</span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </header>


    <!-- KONTEN -->
    <main>
        <?php if ($detail): ?>
        <div class="left-content">
            <?php echo $pesan_notifikasi; ?>
            
            <div class="card-main">
                <div class="info-header">
                    <div class="avatar-bulat">⚕️</div>
                    <div>
                        <h1 class="job-title"><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h1>
                        <p class="company-name">✓ Instansi Pusat Rekrutmen Rumah Sakit</p>
                    </div>
                </div>

                <div class="specs-grid">
                    <div class="spec-item">💵 <span>Kompensasi Menarik</span></div>
                    <div class="spec-item">📁 <span>Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></span></div>
                    <div class="spec-item">👥 <span>Kebutuhan: <strong><?php echo htmlspecialchars($detail['jumlah_kebutuhan']); ?> Orang</strong></span></div>
                    <div class="spec-item">⏳ <span style="color: #ef4444; font-weight: bold;">Batas Akhir: <?php echo date('d M Y', strtotime($detail['tanggal_selesai'])); ?></span></div>
                </div>

                <!-- Tombol Pemicu Modal Lamar -->
                <button onclick="bukaModalSesuaiStatus()" class="btn-lamar">LAMAR SEKARANG</button>

                <div class="details-wrapper">
                    <div>
                        <h3 class="section-title">Persyaratan & Kualifikasi Pelamar</h3>
                        <div class="text-content"><?php echo htmlspecialchars($detail['persyaratan'] ?? $detail['kualifikasi'] ?? 'Persyaratan kualifikasi tertera.'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="right-sidebar">
            <h3 class="sidebar-title">Lowongan Lainnya</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($lowongan_list as $l): ?>
                <a href="?id=<?php echo $l['id']; ?>" class="list-item-job <?php echo $l['id'] == $selected_id ? 'active' : ''; ?>">
                    <h4><?php echo htmlspecialchars($l['judul_lowongan']); ?></h4>
                    <p>Kuota: <?php echo htmlspecialchars($l['jumlah_kebutuhan']); ?> Orang</p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- STICKY FOOTER -->
        <div class="sticky-bar">
            <div class="sticky-text">
                <h4><?php echo htmlspecialchars($detail['judul_lowongan']); ?></h4>
                <p>Kode Formasi: <?php echo htmlspecialchars($detail['kode_lowongan']); ?></p>
            </div>
            <button onclick="bukaModalSesuaiStatus()" class="btn-lamar" style="margin-bottom: 0; width: auto; padding: 12px 30px;">LAMAR</button>
        </div>
        <?php endif; ?>
    </main>

    <!-- MODAL 1: JENDELA JIKA PELAMAR BELUM LOGIN -->
    <div id="authModal" class="modal">
        <div class="modal-content">
            <button onclick="tutupModal('authModal')" class="close-modal">&times;</button>
            
            <!-- SUB-BOX LOGIN -->
            <div id="box-login-view">
                <h3 style="font-size: 18px; font-weight: bold; color: #0f172a; text-align: center; margin-bottom: 15px;">Masuk Akun Pelamar</h3>
                <form action="" method="POST">
                    <div class="form-group"><label>Email</label><input type="email" name="log_email" required placeholder="nama@gmail.com"></div>
                    <div class="form-group"><label>Password</label><input type="password" name="log_password" required placeholder="••••••••"></div>
                    <button type="submit" name="proses_login_pelamar" class="btn-lamar" style="margin-bottom:0;">MASUK</button>
                </form>
                <span class="toggle-link" onclick="pindahKeRegister()">Belum punya akun? Daftar Baru Disini</span>
            </div>

            <!-- SUB-BOX REGISTER -->
            <div id="box-register-view" style="display: none;">
                <h3 style="font-size: 18px; font-weight: bold; color: #0f172a; text-align: center; margin-bottom: 15px;">Registrasi Akun Baru</h3>
                <form action="" method="POST">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="reg_nama" required placeholder="Sesuai KTP"></div>
                    <div class="form-group"><label>Email Aktif</label><input type="email" name="reg_email" required placeholder="contoh@gmail.com"></div>
                    <div class="form-group"><label>Buat Password</label><input type="password" name="reg_password" required placeholder="Minimal 6 Karakter"></div>
                    <button type="submit" name="proses_register_pelamar" class="btn-lamar" style="margin-bottom:0; background: #10b981;">DAFTAR AKUN</button>
                </form>
                <span class="toggle-link" onclick="pindahKeLogin()">Sudah punya akun? Masuk Disini</span>
            </div>
        </div>
    </div>

    <!-- MODAL 2: JENDELA FORMULIR BERKAS (TERBUKA JIKA USER LOGIN) -->
    <div id="lamaranModal" class="modal">
        <div class="modal-content">
            <button onclick="tutupModal('lamaranModal')" class="close-modal">&times;</button>
            <h3 style="font-size: 18px; font-weight: bold; color: #0f172a; text-align: center; margin-bottom: 4px;">Kirim Berkas Lamaran</h3>
            <p style="font-size: 12px; color: #64748b; text-align: center; margin-bottom: 20px;">Akun: <?php echo $_SESSION['user_pelamar_mail'] ?? ''; ?></p>
            
            <form action="" method="POST">
                <input type="hidden" name="lowongan_id" value="<?php echo $detail['id'] ?? 0; ?>">
                <div class="form-group"><label>Nama Pelamar (Otomatis)</label><input type="text" readonly value="<?php echo $_SESSION['user_pelamar_nama'] ?? ''; ?>" style="background: #f1f5f9; color: #64748b;"></div>
                <div class="form-group"><label>NIK KTP</label><input type="text" name="nik_ktp" required placeholder="16 Digit NIK"></div>
                <div class="form-group"><label>No HP / WhatsApp</label><input type="text" name="no_hp" required placeholder="Contoh: 0812xxxxxxxx"></div>
                <button type="submit" name="kirim_berkas_lamaran" class="btn-lamar" style="margin-bottom: 0;">KIRIM SEKARANG</button>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        const sudahLogin = <?php echo isset($_SESSION['user_pelamar_id']) ? 'true' : 'false'; ?>;

        function bukaModalSesuaiStatus() {
            if (sudahLogin) {
                document.getElementById('lamaranModal').classList.add('open');
            } else {
                document.getElementById('authModal').classList.add('open');
            }
        }

        function tutupModal(idModal) {
            document.getElementById(idModal).classList.remove('open');
        }

        function pindahKeRegister() {
            document.getElementById('box-login-view').style.display = 'none';
            document.getElementById('box-register-view').style.display = 'block';
        }

        function togglePilihan() {
            pindahKeLogin();
        }

        function pindahKeLogin() {
            document.getElementById('box-register-view').style.display = 'none';
            document.getElementById('box-login-view').style.display = 'block';
        }
    </script>
</body>
</html>

