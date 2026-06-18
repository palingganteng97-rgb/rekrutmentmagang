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

// Ambil ID Pelamar jika sudah login
$pelamar_id = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : 0;

$error_message = "";
$success_message = "";
$show_preview = false;
$data_riil = array();

// QUERY GLOBAL: Mengambil data pelamar untuk kebutuhan preview di modal
if ($pelamar_id > 0) {
    $cek_global = mysqli_query($koneksi, "SELECT p.foto, p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.status_sosial, p.telepon, p.kota, p.provinsi, p.alamat, pd.jenjang, pd.institusi, pd.jurusan, pd.tahun_lulus, pd.ipk FROM pelamar p LEFT JOIN pelamar_pendidikan pd ON p.id = pd.pelamar_id WHERE p.id = $pelamar_id");
    if ($cek_global && mysqli_num_rows($cek_global) > 0) {
        $data_riil = mysqli_fetch_assoc($cek_global);
    }
}

// 2. LOGIKA PROSES DAFTAR AKUN (REGISTER)
if (isset($_POST['register'])) {
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = $_POST['password'];
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $cek_email = mysqli_query($koneksi, "SELECT email FROM pelamar WHERE email = '$email'");
    if (mysqli_num_rows($cek_email) > 0) {
        $error_message = "Email sudah terdaftar!";
    } else {
        $query_reg = "INSERT INTO pelamar (nama_lengkap, email, password) VALUES ('$nama_lengkap', '$email', '$hashed_password')";
        if (mysqli_query($koneksi, $query_reg)) {
            $success_message = "Pendaftaran berhasil! Silakan masuk.";
        } else {
            $error_message = "Gagal mendaftar, coba lagi.";
        }
    }
}

// 3. LOGIKA PROSES MASUK AKUN (LOGIN LANGSUNG)
if (isset($_POST['login_langsung']) || isset($_POST['login'])) {
    $email    = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = $_POST['password'];

    $query  = "SELECT * FROM pelamar WHERE email = '$email'";
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($password, $row['password'])) {
            $_SESSION['pelamar_logged_in'] = true;
            $_SESSION['pelamar_id']        = $row['id'];
            $_SESSION['pelamar_nama']      = $row['nama_lengkap'];

            echo "<script>window.location.href='lowongan_pelamar.php';</script>";
            exit();
        } else {
            $error_message = "Password yang Anda masukkan salah!";
        }
    } else {
        $error_message = "Email tidak terdaftar!";
    }
}

// 4. LOGIKA TOMBOL "PREVIEW & LAMAR SEKARANG" DI KARTU UTAMA
if (isset($_POST['lamar_sekarang'])) {
    if (!isset($_SESSION['pelamar_logged_in'])) {
        $error_message = "Anda harus masuk terlebih dahulu!";
    } else {
        if (
            empty($data_riil['nik']) || empty($data_riil['tempat_lahir']) || empty($data_riil['tanggal_lahir']) || 
            empty($data_riil['jenis_kelamin']) || empty($data_riil['alamat']) || empty($data_riil['foto'])
        ) {
            echo "<script>
                alert('⚠️ Gagal mengirim lamaran! Ditemukan data profil atau foto yang masih kosong. Anda akan diarahkan ke halaman profil untuk melengkapi data.');
                window.location.href = 'profil_pelamar.php';
            </script>";
            exit();
        } else {
            $show_preview = true;
        }
    }
}

// 5. LOGIKA SUBMIT FINAL DARI DALAM POP-UP PREVIEW
if (isset($_POST['submit_final_lamaran'])) {
    $lowongan_id = 5; // ID Formasi LWN-5

    $cek_lamaran = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
    if (mysqli_num_rows($cek_lamaran) > 0) {
        $error_message = "Anda sudah mengirimkan lamaran untuk formasi ini.";
    } else {
        $query_lamar = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, created_at) VALUES ($pelamar_id, $lowongan_id, NOW())";
        if (mysqli_query($koneksi, $query_lamar)) {
            $success_message = "Lamaran Anda berhasil dikirim ke sistem!";
        } else {
            $error_message = "Gagal mengirim lamaran kerja.";
        }
    }
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: lowongan_pelamar.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Lowongan Kerja</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
        body { background-color: #ffffff; color: #333; }
        
        /* NAVBAR */
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 10%; background: #fff; border-bottom: 1px solid #eef2f5; }
        .brand { font-size: 18px; font-weight: bold; color: #111; letter-spacing: 0.5px; text-transform: uppercase; }
        .nav-buttons { display: flex; gap: 15px; align-items: center; }
        .btn-masuk { background: none; border: none; color: #2563eb; font-size: 14px; cursor: pointer; font-weight: 600; text-decoration: none; }
        .btn-daftar { background-color: #2563eb; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; text-decoration: none; }
        .btn-daftar:hover { background-color: #1d4ed8; }
        .btn-keluar { background-color: #dc2626; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; text-decoration: none; font-weight: 500; }
        
        /* CONTAINER UTAMA & NOTIFIKASI */
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; text-align: center; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        /* KARTU LOWONGAN UTAMA */
        .card-lowongan { background: white; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-width: 650px; margin: 50px auto; text-align: center; }
        .card-lowongan h2 { color: #1e3a8a; font-size: 28px; margin-bottom: 8px; }
        .card-lowongan .sub-title { color: #2563eb; font-size: 14px; margin-bottom: 30px; font-weight: 500; }
        .table-info { width: 100%; margin-bottom: 35px; border-collapse: collapse; text-align: left; font-size: 15px; }
        .table-info td { padding: 12px 0; }
        .table-info td:first-child { color: #475569; width: 35%; }
        .table-info td:last-child { color: #1e293b; font-weight: 500; }
        .btn-lamar-utama { width: 100%; background-color: #1e293b; color: white; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-lamar-utama:hover { background-color: #0f172a; }

        /* MODAL POP-UP STYLE */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; padding: 20px; }
        .modal-box { background-color: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .modal-box.large { max-width: 650px; max-height: 85vh; overflow-y: auto; scrollbar-width: thin; }
        .modal-close { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #94a3b8; background: none; border: none; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #475569; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn-submit-modal { width: 100%; background-color: #2563eb; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 15px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="brand">Portal Karir</div>
        <div class="nav-buttons">
            <!-- Ganti bagian dalam komponen navbar yang sudah login dengan ini -->
<?php if(isset($_SESSION['pelamar_logged_in']) && $_SESSION['pelamar_logged_in'] === true): ?>
    <span style="font-size: 14px; margin-right: 5px;">Halo, </span>
    <!-- Mengubah teks nama menjadi link aktif ke profil -->
    <a href="profil_pelamar.php" style="font-size: 14px; font-weight: 700; color: #2563eb; text-decoration: none; margin-right: 15px; border-bottom: 1px dashed #2563eb; padding-bottom: 2px;" title="Klik untuk edit profil & foto">
        <?php echo htmlspecialchars($_SESSION['pelamar_nama']); ?>
    </a>
    <a href="?logout=true" class="btn-keluar">Keluar</a>
<?php else: ?>

                <button class="btn-masuk" onclick="openAuthModal('login')">Masuk</button>
                <button class="btn-daftar" onclick="openAuthModal('register')">Daftar Akun</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <!-- Notifikasi Sistem -->
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- KARTU LOWONGAN UTAMA -->
        <div class="card-lowongan">
            <h2>Lowongan Kerja</h2>
            <div class="sub-title">✓ Instansi Pusat Rekrutmen Rumah Sakit</div>
            
            <table class="table-info">
                <tr>
                    <td>Kompensasi:</td>
                    <td>Menarik</td>
                </tr>
                <tr>
                    <td>Kode Formasi:</td>
                    <td>LWN-5</td>
                </tr>
                <tr>
                    <td>Kebutuhan:</td>
                    <td>0 Orang</td>
                </tr>
                <tr>
                    <td>Batas Akhir:</td>
                    <td>20 Jun 2026</td>
                </tr>
            </table>

            <form action="" method="POST">
                <button type="submit" name="lamar_sekarang" class="btn-lamar-utama">
                    💻 PREVIEW & LAMAR SEKARANG
                </button>
            </form>
        </div>
    </div>

    <!-- ================= MODAL LOGIN & REGISTER PINTAR (Satu Modal) ================= -->
    <div id="authModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('authModal')">&times;</button>
            <h3 id="modalTitle" style="margin-bottom: 20px; font-size: 20px; color: #1e293b;">Masuk</h3>
            
            <form action="" method="POST">
                <div class="form-group" id="nameField" style="display: none;">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="namaInput" class="form-control" placeholder="Masukkan nama lengkap">
                </div>
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="nama@email.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="******">
                </div>
                <button type="submit" id="submitBtn" name="login_langsung" class="btn-submit-modal" style="margin-top: 10px;">Masuk</button>
            </form>
            <div id="switchText" style="margin-top: 15px; font-size: 13px; color: #64748b; text-align: center;"></div>
        </div>
    </div>

    <!-- ================= MODAL PREVIEW DATA LAMARAN (BISA SCROLL) ================= -->
    <?php if (isset($_SESSION['pelamar_logged_in']) && $show_preview === true): ?>
        <div class="modal-overlay" id="previewModal" style="display: flex;">
            <div class="modal-box large">
                <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
                <h3 style="color: #1e3a8a; font-size: 20px; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">Konfirmasi Data Lamaran Anda</h3>

                <!-- BAGIAN A: BIODATA -->
                <div style="font-size: 14px; margin-bottom: 25px;">
                    <h4 style="color: #2563eb; font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; font-weight: 600;">A. Biodata Pelamar</h4>
                    <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 10px;">
                        
                        <!-- PREVIEW FOTO PROFIL -->
                        <div style="flex-shrink: 0; width: 110px; text-align: center;">
                            <?php if(!empty($data_riil['foto']) && file_exists("uploads/" . $data_riil['foto'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($data_riil['foto']); ?>" alt="Foto" style="width: 110px; height: 145px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <?php else: ?>
                                <div style="width: 110px; height: 145px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px dashed #cbd5e1; font-size: 11px; color: #94a3b8;">No Photo</div>
                            <?php endif; ?>
                        </div>

                        <!-- TABEL DATA TEKS -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px; color: #334155;">
                            <tr><td style="width: 140px; padding: 4px 0; color: #64748b;">Nama Lengkap</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['nama_lengkap'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">NIK</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['nik'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Tempat, Tgl Lahir</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars(($data_riil['tempat_lahir'] ?? '-') . ', ' . ($data_riil['tanggal_lahir'] ?? '-')) ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Jenis Kelamin</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['jenis_kelamin'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Agama</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['agama'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Status Hubungan</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['status_sosial'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">No. Telepon / WA</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars($data_riil['telepon'] ?? '-') ?></td></tr>
                            <tr><td style="padding: 4px 0; color: #64748b;">Kota & Provinsi</td><td style="padding: 4px 0; font-weight: 500;">: <?= htmlspecialchars(($data_riil['kota'] ?? '-') . ', ' . ($data_riil['provinsi'] ?? '-')) ?></td></tr>
                            <tr><td style="vertical-align: top; padding: 4px 0; color: #64748b;">Alamat Rumah</td><td style="padding: 4px 0; font-weight: 500; line-height: 1.4;">: <?= htmlspecialchars($data_riil['alamat'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- BAGIAN B: PENDIDIKAN -->
                <div style="margin-bottom: 25px; text-align: left;">
                    <h4 style="color: #2563eb; font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; font-weight: 600;">B. Riwayat Pendidikan</h4>
                    <div style="background-color: #f8fafc; padding: 12px 15px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 13px; line-height: 1.6; color: #475569;">
                        <span style="background-color: #cbd5e1; color: #334155; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; margin-bottom: 6px;">Pendidikan #1</span>
                        <p><strong>Jenjang / Kampus :</strong> <?= htmlspecialchars(($data_riil['jenjang'] ?? '-') . ' - ' . ($data_riil['institusi'] ?? '-')) ?></p>
                        <p><strong>Jurusan / Prodi :</strong> <?= htmlspecialchars($data_riil['jurusan'] ?? '-') ?></p>
                        <p><strong>Tahun Lulus / IPK :</strong> Lulus Th. <?= htmlspecialchars($data_riil['tahun_lulus'] ?? '-') ?> (IPK/Nilai: <?= htmlspecialchars($data_riil['ipk'] ?? '-') ?>)</p>
                    </div>
                </div>

                <!-- BAGIAN C: FORMASI -->
                <div style="margin-bottom: 30px; text-align: left;">
                    <h4 style="color: #2563eb; font-size: 15px; margin-bottom: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; font-weight: 600;">C. Formasi yang Dilamar</h4>
                    <p style="font-size: 14px; font-weight: 600; color: #1e293b;">LWN-5 (Pusat Rekrutmen Rumah Sakit)</p>
                </div>

                <!-- TOMBOL KONFIRMASI FINAL -->
                <form action="" method="POST" style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeModal('previewModal')" style="flex: 1; background-color: #cbd5e1; color: #334155; padding: 12px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">Batal</button>
                    <button type="submit" name="submit_final_lamaran" style="flex: 2; background-color: #10b981; color: white; padding: 12px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">✓ YA, DATA SUDAH BENAR & KIRIM LAMARAN</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- JAVASCRIPT KONTROL POP-UP -->
    <script>
        function openAuthModal(mode) {
            const modal = document.getElementById('authModal');
            const title = document.getElementById('modalTitle');
            const nameField = document.getElementById('nameField');
            const namaInput = document.getElementById('namaInput');
            const submitBtn = document.getElementById('submitBtn');
            const switchText = document.getElementById('switchText');

            modal.style.display = 'flex';

            if (mode === 'register') {
                title.innerText = 'Daftar Akun Baru';
                submitBtn.innerText = 'Daftar';
                submitBtn.name = 'register';
                nameField.style.display = 'block';
                if (namaInput) namaInput.required = true;
                switchText.innerHTML = 'Sudah punya akun? <a href="#" onclick="openAuthModal(\'login\')" style="color:#2563eb; font-weight:600; text-decoration:none;">Masuk</a>';
            } else {
                title.innerText = 'Masuk';
        submitBtn.innerText = 'Masuk';
        submitBtn.name = 'login_langsung';
        nameField.style.display = 'none';
        if (namaInput) namaInput.required = false;
        switchText.innerHTML = 'Belum punya akun? <a href="#" onclick="openAuthModal(\'register\')" style="color:#2563eb; font-weight:600; text-decoration:none;">Daftar</a>';
    }
}

function closeModal(idModal) {
    document.getElementById(idModal).style.display = 'none';
}

// Tutup otomatis pop-up jika area gelap di luar kotak diklik
window.onclick = function(event) {
    if (event.target.className === 'modal-overlay' || event.target.id === 'authModal' || event.target.id === 'previewModal') {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>
