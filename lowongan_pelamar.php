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
    
    $cek_email = mysqli_query($koneksi, "SELECT id FROM pelamar WHERE email = '$email'");
    if (mysqli_num_rows($cek_email) > 0) {
        $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box error'>❌ Email sudah terdaftar! Silakan langsung login.</div>";
    } else {
        $password_hashed = password_hash($pass, PASSWORD_DEFAULT);
        $q_reg = "INSERT INTO pelamar (nama_lengkap, email, password, status, created_at, updated_at) VALUES ('$nama', '$email', '$password_hashed', 'Aktif', NOW(), NOW())";
        if (mysqli_query($koneksi, $q_reg)) {
            $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box sukses'>🎉 Registrasi Berhasil! Silakan masuk dengan email & password Anda.</div>";
        } else {
            $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box error'>Gagal register: " . mysqli_error($koneksi) . "</div>";
        }
    }
}

// =========================================================================
// 3. FITUR B: PROSES LOGIN USER PELAMAR VIA EMAIL
// =========================================================================
if (isset($_POST['proses_login_pelamar'])) {
    $email = mysqli_real_escape_string($koneksi, $_POST['log_email']);
    $pass  = $_POST['log_password'];
    
    $cek_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE email = '$email'");
    if (mysqli_num_rows($cek_user) > 0) {
        $user_data = mysqli_fetch_assoc($cek_user);
        
        if (password_verify($pass, $user_data['password'])) {
            $_SESSION['user_pelamar_id']   = $user_data['id'];
            $_SESSION['user_pelamar_nama'] = $user_data['nama_lengkap'];
            $_SESSION['user_pelamar_mail'] = $user_data['email'];
            
            $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box sukses'>🔓 Login Berhasil! Halo, " . htmlspecialchars($_SESSION['user_pelamar_nama']) . ". Silakan klik tombol lamar di bawah.</div>";
        } else {
            $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box error'>❌ Password yang Anda masukkan salah!</div>";
        }
    } else {
        $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box error'>❌ Email tidak ditemukan! Silakan daftar dahulu.</div>";
    }
}

// =========================================================================
// 4. FITUR C: PROSES JIKA USER INPUT DATA LENGKAP & KIRIM LAMARAN
// =========================================================================
if (isset($_POST['kirim_berkas_lamaran'])) {
    $lowongan_id   = intval($_POST['lowongan_id']);
    $pelamar_id    = intval($_SESSION['user_pelamar_id']); 
    
    // Ambil input biodata lengkap
    $nik           = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir  = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama         = mysqli_real_escape_string($koneksi, $_POST['agama']);
    $alamat        = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $kota          = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $provinsi      = mysqli_real_escape_string($koneksi, $_POST['provinsi']);
    $telepon       = mysqli_real_escape_string($koneksi, $_POST['telepon']);

    // A. Update Profil Pelamar di tabel `pelamar` secara lengkap sesuai skema HeidiSQL
    $q_update = "UPDATE pelamar SET 
                    nik = '$nik', 
                    tempat_lahir = '$tempat_lahir', 
                    tanggal_lahir = '$tanggal_lahir', 
                    jenis_kelamin = '$jenis_kelamin', 
                    agama = '$agama', 
                    alamat = '$alamat', 
                    kota = '$kota', 
                    provinsi = '$provinsi', 
                    telepon = '$telepon', 
                    updated_at = NOW() 
                 WHERE id = $pelamar_id";
    mysqli_query($koneksi, $q_update);

    // B. Simpan transaksi pengajuan berkas ke tabel `rekrutmen_lamaran`
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");

    $q_lamaran = "INSERT INTO rekrutmen_lamaran (lowongan_id, pelamar_id, created_at, updated_at) VALUES ('$lowongan_id', '$pelamar_id', NOW(), NOW())";
    
    if (mysqli_query($koneksi, $q_lamaran)) {
        $lamaran_id = mysqli_insert_id($koneksi);
        $tahapan_id = 1; 
        $petugas_id = 1; 

        // C. Input log status seleksi awal ke tabel `lamaran_tahapan`
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

// Menangkap alert setelah sukses melamar
if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $nama_terkirim = htmlspecialchars($_GET['nama']);
    $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box sukses'>🎉 Sukses! Berkas lamaran atas nama $nama_terkirim berhasil dikirim langsung ke sistem seleksi Admin Rumah Sakit.</div>";
}

// =========================================================================
// 5. AMBIL DATA LOWONGAN UNTUK DITAMPILKAN (DENGAN PROTEKSI DATA NULL)
// =========================================================================
$query = "SELECT * FROM rekrutmen_lowongan WHERE status = 'Aktif' ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);

$lowongan_list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lowongan_list[] = $row;
    }
}

// PROTEKSI CADANGAN: Jika database Anda kosong atau status tidak sesuai, isi larik manual agar tidak memicu Undefined Index
if (empty($lowongan_list)) {
    $lowongan_list[] = [
        'id' => 5,
        'nama_lowongan' => 'Dokter Umum',
        'kuota' => 3,
        'status' => 'Aktif',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

if (isset($_GET['id'])) {
    $selected_id = intval($_GET['id']);
} else {
    $selected_id = isset($lowongan_list[0]['id']) ? intval($lowongan_list[0]['id']) : 5;
}

$detail = null;
foreach ($lowongan_list as $l) {
    if (intval($l['id']) === $selected_id) {
        $detail = $l;
        break;
    }
}

// Pengaman akhir: Mengamankan properti nama_lowongan agar tidak memicu fatal error htmlspecialchars()
if (!$detail || !isset($detail['nama_lowongan'])) {
    $detail = $lowongan_list[0];
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
        .nav-container { max-width: 800px; margin: 0 auto; padding: 0 20px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 900; color: #0f172a; text-decoration: none; letter-spacing: -0.5px; }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-nav-status { font-size: 14px; font-weight: 600; color: #334155; display: flex; align-items: center; }
        .btn-logout-header { font-size: 12px; color: #ef4444; text-decoration: none; font-weight: bold; border: 1px solid #fecaca; padding: 5px 12px; border-radius: 6px; background: #fff5f5; transition: all 0.2s ease; }
        .btn-logout-header:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
        
        .nav-auth-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { text-decoration: none; font-size: 13px; font-weight: bold; padding: 6px 14px; border-radius: 6px; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-nav-login { background: #2563eb; color: white; }
        .btn-nav-login:hover { background: #1d4ed8; }
        .btn-nav-register { background: #10b981; color: white; }
        .btn-nav-register:hover { background: #059669; }

        main { max-width: 800px; margin: 25px auto; padding: 0 20px; display: block; }

        .card-main { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .info-header { display: flex; gap: 20px; align-items: center; margin-bottom: 24px; }
        .avatar-bulat { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #eff6ff; color: #2563eb; flex-shrink: 0; }
        .job-title { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .company-name { color: #2563eb; font-weight: 600; font-size: 14px; margin-top: 4px; }
        
        .specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; }
        .spec-item { font-size: 14px; color: #475569; }
        .spec-label { font-weight: bold; color: #64748b; }

        .btn { display: inline-block; width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-align: center; font-size: 15px; text-decoration: none; transition: background 0.2s ease; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #10b981; }
        .btn-secondary:hover { background: #059669; }

        /* Pop-up Modal Kontrol */
        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 100; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal-bg.active { opacity: 1; pointer-events: auto; }
        
        .modal-box { background: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 12px; right: 15px; font-size: 18px; cursor: pointer; color: #94a3b8; font-weight: bold; }

        /* Grid Untuk Form Isian Lengkap Berpasangan */
        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569; }
        .form-control { width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #334155; }
        
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-box.sukses { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-box.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <header>
        <div class="nav-container">
            <a href="lowongan_pelamar.php" class="nav-brand">PORTAL KARIR</a>
            <div class="user-info">
                <?php if (isset($_SESSION['user_pelamar_id'])): ?>
                    <span class="user-nav-status">👤 Halo, <?= htmlspecialchars($_SESSION['user_pelamar_nama']); ?></span>
                    <a href="lowongan_pelamar.php?action=logout_user" class="btn-logout-header">Log Out</a>
                <?php else: ?>
                    <div class="nav-auth-buttons">
                        <button class="btn-nav btn-nav-login" onclick="toggleModal('modal-login')">Masuk</button>
                        <button class="btn-nav btn-nav-register" onclick="toggleModal('modal-daftar')">Daftar</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <div class="left-content">
            <?php if (!empty($pesan_notifikasi)) echo $pesan_notifikasi; ?>

            <div class="card-main">
                <div class="info-header">
                    <div class="avatar-bulat">⚕️</div>
                    <div>
                        <div class="company-name">✓ Instansi Pusat Rekrutmen Rumah Sakit</div>
                    </div>
                </div>

                <div class="specs-grid">
                    <div class="spec-item"><span class="spec-label">Kompensasi:</span> Menarik</div>
                    <div class="spec-item"><span class="spec-label">Kode Formasi:</span> LWN-<?= $detail['id']; ?></div>
                    <div class="spec-item"><span class="spec-label">Kebutuhan:</span> <?= htmlspecialchars($detail['kuota'] ?? '0'); ?> Orang</div>
                    <div class="spec-item"><span class="spec-label">Batas Akhir:</span> 20 Jun 2026</div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 25px; margin-top: 25px;">
                    <?php if (isset($_SESSION['user_pelamar_id'])): ?>
                        <button type="button" class="btn" onclick="toggleModal('modal-berkas')">🚀 LAMAR SEKARANG</button>
                    <?php else: ?>
                        <button type="button" class="btn" onclick="toggleModal('modal-login')">🚀 LAMAR SEKARANG</button>
                        <p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 8px;">*Anda wajib masuk / login akun terlebih dahulu untuk melamar pekerjaan ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- POPUP MODAL DATA LENGKAP PELAMAR -->
    <div id="modal-berkas" class="modal-bg" onclick="closeModalOnBg(event, 'modal-berkas')">
        <div class="modal-box">
            <span class="modal-close" onclick="toggleModal('modal-berkas')">&times;</span>
            <h3 style="margin-bottom: 18px; color:#0f172a; font-size:18px;">📝 Pengisian Biodata Lengkap</h3>
            
            <form action="" method="POST">
                <input type="hidden" name="lowongan_id" value="<?= $detail['id']; ?>">
                
                <div class="form-group">
                    <label>Nomor Induk Kependudukan (NIK)</label>
                    <input type="text" name="nik" class="form-control" placeholder="16 Digit NIK KTP" value="<?= htmlspecialchars($old_data['nik'] ?? ''); ?>" required maxlength="16" minlength="16">
                </div>

                <div class="form-row-grid">
                    <div class="form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control" placeholder="Kota Lahir" value="<?= htmlspecialchars($old_data['tempat_lahir'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= htmlspecialchars($old_data['tanggal_lahir'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row-grid">
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control" required>
                            <option value="Laki-Laki" <?= ($old_data['jenis_kelamin'] == 'Laki-Laki') ? 'selected' : ''; ?>>Laki-Laki</option>
                            <option value="Perempuan" <?= ($old_data['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Agama</label>
                        <input type="text" name="agama" class="form-control" placeholder="Islam / Kristen / dll" value="<?= htmlspecialchars($old_data['agama'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <input type="text" name="alamat" class="form-control" placeholder="Nama Jalan, RT/RW, Dusun" value="<?= htmlspecialchars($old_data['alamat'] ?? ''); ?>" required>
                </div>

                <div class="form-row-grid">
                    <div class="form-group">
                        <label>Kabupaten / Kota</label>
                        <input type="text" name="kota" class="form-control" placeholder="Kota" value="<?= htmlspecialchars($old_data['kota'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Provinsi</label>
                        <input type="text" name="provinsi" class="form-control" placeholder="Provinsi" value="<?= htmlspecialchars($old_data['provinsi'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nomor Telepon / HP</label>
                    <input type="tel" name="telepon" class="form-control" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($old_data['telepon'] ?? ''); ?>" required>
                </div>

                <button type="submit" name="kirim_berkas_lamaran" class="btn" style="margin-top: 5px;">🚀 Kirim Lamaran Resmi</button>
            </form>
        </div>
    </div>

    <!-- POPUP MODAL LOGIN -->
    <div id="modal-login" class="modal-bg" onclick="closeModalOnBg(event, 'modal-login')">
        <div class="modal-box">
            <span class="modal-close" onclick="toggleModal('modal-login')">&times;</span>
            <h3 style="margin-bottom: 20px; color:#0f172a; font-size:18px;">🔐 Masuk Akun</h3>
            <form action="" method="POST">
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="email" name="log_email" class="form-control" placeholder="nama@email.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="log_password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" name="proses_login_pelamar" class="btn" style="margin-bottom: 15px;">Masuk Sistem</button>
            </form>
            <p style="text-align: center; font-size: 13px; color: #64748b;">
                Belum punya akun? <a href="javascript:void(0)" onclick="toggleModal('modal-login'); toggleModal('modal-daftar');" style="color: #2563eb; font-weight: bold; text-decoration: none;">Daftar disini!</a>
            </p>
        </div>
    </div>

    <!-- POPUP MODAL DAFTAR -->
    <div id="modal-daftar" class="modal-bg" onclick="closeModalOnBg(event, 'modal-daftar')">
        <div class="modal-box">
            <span class="modal-close" onclick="toggleModal('modal-daftar')">&times;</span>
            <h3 style="margin-bottom: 20px; color:#0f172a; font-size:18px;">📝 Daftar Baru</h3>
            <form action="" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="reg_nama" class="form-control" placeholder="Nama Lengkap Anda" required>
                </div>
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="email" name="reg_email" class="form-control" placeholder="emailbaru@email.com" required>
                </div>
                <div class="form-group">
                    <label>Password Akun</label>
                    <input type="password" name="reg_password" class="form-control" placeholder="Minimal 6 Karakter" required minlength="6">
                </div>
                <button type="submit" name="proses_register_pelamar" class="btn btn-secondary" style="margin-bottom: 15px;">Buat Akun</button>
            </form>
            <p style="text-align: center; font-size: 13px; color: #64748b;">
                Sudah punya akun? <a href="javascript:void(0)" onclick="toggleModal('modal-daftar'); toggleModal('modal-login');" style="color: #2563eb; font-weight: bold; text-decoration: none;">Login disini!</a>
            </p>
        </div>
    </div>

    <!-- JAVASCRIPT ANIMATION & LOGIK POPUP -->
    <script>
        function toggleModal(id) {
            const modal = document.getElementById(id);
            if(modal) {
                modal.classList.toggle('active');
            }
        }

        function closeModalOnBg(e, id) {
            if (e.target.id === id) {
                toggleModal(id);
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            var alertBox = document.getElementById("alert-auto-close");
            if (alertBox) {
                setTimeout(function() {
                    alertBox.style.transition = "opacity 0.5s ease";
                    alertBox.style.opacity = "0";
                    setTimeout(function() {
                        alertBox.remove();
                    }, 500); 
                }, 5000); 
            }
        });
    </script>
</body>
</html>
