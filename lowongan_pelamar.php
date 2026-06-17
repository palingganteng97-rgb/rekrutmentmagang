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

$error_message = "";
$success_message = "";

// 2. LOGIKA PROSES DAFTAR AKUN
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

// 3. LOGIKA PROSES UPDATE PROFIL BIODATA (TABEL: pelamar)
if (isset($_POST['update_profil'])) {
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nik           = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir  = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama         = mysqli_real_escape_string($koneksi, $_POST['agama']);
    // MENANGKAP DATA INPUT STATUS HUBUNGAN DARI FORM
    $status_sosial = mysqli_real_escape_string($koneksi, $_POST['status_sosial']); 
    $telepon       = mysqli_real_escape_string($koneksi, $_POST['telepon']);
    $alamat        = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $kota          = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $provinsi      = mysqli_real_escape_string($koneksi, $_POST['provinsi']);
    
    // Ambil data profil lama untuk validasi foto
    $query_lama = mysqli_query($koneksi, "SELECT foto FROM pelamar WHERE id = $pelamar_id");
    $data_lama = mysqli_fetch_assoc($query_lama);
    $nama_foto_baru = isset($data_lama['foto']) ? $data_lama['foto'] : ""; 

    // Logika Upload Foto
    if (isset($_FILES['foto']['name']) && $_FILES['foto']['name'] != '') {
        $tipe_file = $_FILES['foto']['type'];
        $ekstensi_diperbolehkan = array('image/jpeg', 'image/jpg', 'image/png');
        
        if (in_array($tipe_file, $ekstensi_diperbolehkan)) {
            $ekstensi = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nama_foto_baru = "foto_" . $pelamar_id . "_" . time() . "." . $ekstensi;
            $folder_tujuan = "uploads/" . $nama_foto_baru;
            
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            move_uploaded_file($_FILES['foto']['tmp_name'], $folder_tujuan);
        } else {
            $error_message = "Format foto harus JPG, JPEG, atau PNG!";
        }
    }

    if (empty($error_message)) {
        // MEMASUKKAN KOLOM status_sosial KE DALAM PERINTAH UPDATE SQL
        $query_update = "UPDATE pelamar SET 
            nama_lengkap = '$nama_lengkap', 
            nik = '$nik', 
            tempat_lahir = '$tempat_lahir', 
            tanggal_lahir = '$tanggal_lahir', 
            jenis_kelamin = '$jenis_kelamin', 
            agama = '$agama', 
            status_sosial = '$status_sosial', 
            telepon = '$telepon', 
            alamat = '$alamat', 
            kota = '$kota', 
            provinsi = '$provinsi', 
            foto = '$nama_foto_baru', 
            updated_at = NOW() 
            WHERE id = $pelamar_id";
            
        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['pelamar_nama'] = $nama_lengkap; 
            $success_message = "Profil biodata Anda berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui data profil: " . mysqli_error($koneksi);
        }
    }
}


// 4. LOGIKA PROSES FINAL KIRIM LAMARAN KERJA (VALIDASI KETAT & PAKSA REDIRECT PROFIL)
if (isset($_POST['lamar'])) {
    if (!isset($_SESSION['pelamar_logged_in'])) {
        $error_message = "Anda harus masuk terlebih dahulu!";
    } else {
        $pelamar_id = $_SESSION['pelamar_id'];
        $lowongan_id = 5; // ID Contoh Formasi LWN-5

        // Ambil data profil dan pendidikan pelamar saat ini untuk dicek ulang nilainya
        $cek_validasi = mysqli_query($koneksi, "SELECT p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.status_sosial, p.telepon, p.kota, p.provinsi, p.alamat, pd.jenjang, pd.institusi, pd.jurusan, pd.tahun_lulus, pd.ipk FROM pelamar p LEFT JOIN pelamar_pendidikan pd ON p.id = pd.pelamar_id WHERE p.id = $pelamar_id");
        $data_riil = mysqli_fetch_assoc($cek_validasi);

        // Jika salah satu kolom biodata atau status hubungan atau pendidikan kosong
        if (
            empty($data_riil['nik']) || empty($data_riil['tempat_lahir']) || empty($data_riil['tanggal_lahir']) || 
            empty($data_riil['jenis_kelamin']) || empty($data_riil['agama']) || empty($data_riil['status_sosial']) || 
            empty($data_riil['telepon']) || empty($data_riil['kota']) || empty($data_riil['provinsi']) || 
            empty($data_riil['alamat']) || empty($data_riil['jenjang']) || empty($data_riil['institusi']) || 
            empty($data_riil['jurusan']) || empty($data_riil['tahun_lulus']) || empty($data_riil['ipk'])
        ) {
            // Memunculkan pesan error dan memaksa halaman berpindah ke profil_pelamar.php setelah pelamar klik OK
            echo "<script>
                alert('⚠️ Gagal mengirim lamaran! Ditemukan data profil atau pendidikan yang masih kosong. Anda akan diarahkan ke halaman profil untuk melengkapi data.');
                window.location.href = 'profil_pelamar.php';
            </script>";
            exit();
        } else {
            // Cek apakah pelamar sudah pernah mengirimkan lamaran sebelumnya
            $cek_lamaran = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
            if (mysqli_num_rows($cek_lamaran) > 0) {
                $error_message = "Anda sudah mengirimkan lamaran untuk formasi ini.";
            } else {
                // Jika semua data benar-benar lengkap, baru simpan ke database rekrutmen
                $query_lamar = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, created_at) VALUES ($pelamar_id, $lowongan_id, NOW())";
                if (mysqli_query($koneksi, $query_lamar)) {
                    $success_message = "Lamaran Anda berhasil dikirim ke sistem!";
                } else {
                    $error_message = "Gagal mengirim lamaran kerja.";
                }
            }
        }
    }
}


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
        .brand { font-size: 18px; font-weight: bold; color: #111; letter-spacing: 0.5px; }
        .nav-buttons { display: flex; gap: 15px; align-items: center; }
        .btn-masuk { background: none; border: none; color: #333; font-size: 14px; cursor: pointer; font-weight: 500; }
        .btn-daftar { background-color: #2563eb; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; }
        .btn-logout { background-color: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; }

        /* CONTAINER & ALERTS */
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .alert-error { background-color: #fff5f5; border: 1px solid #fed7d7; color: #c53030; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 25px; font-size: 14px; }
        .alert-success { background-color: #f0fff4; border: 1px solid #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 25px; font-size: 14px; }

        /* CARD JOB */
        .card-job { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-width: 650px; margin: 0 auto; }
        .card-header-job { text-align: center; margin-bottom: 30px; }
        .card-header-job h2 { color: #1e3a8a; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .card-header-job p { color: #2563eb; font-size: 14px; font-weight: 500; }
        
        .info-grid { display: grid; grid-template-columns: 150px 1fr; row-gap: 15px; margin-bottom: 35px; font-size: 15px; padding: 0 20px; }
        .info-label { font-weight: 600; color: #334155; }
        .info-value { color: #64748b; }
        
        .btn-lamar { background-color: #34495e; color: white; border: none; width: 100%; padding: 14px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; letter-spacing: 0.5px; }
        .btn-lamar:hover { background-color: #2c3e50; }

        /* MODAL POPUP */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-close { position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 22px; cursor: pointer; color: #94a3b8; }
        .modal-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn-submit { background-color: #2563eb; color: white; border: none; width: 100%; padding: 10px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 10px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="brand">PORTAL KARIR</div>
        <div class="nav-buttons">
            <?php if (isset($_SESSION['pelamar_logged_in'])): ?>
                <a href="profil_pelamar.php" style="font-size: 14px; color: #2563eb; text-decoration: none; font-weight: 600; margin-right: 15px;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                <?= htmlspecialchars($_SESSION['pelamar_nama']); ?>
                </a>
                <a href="?logout=true" class="btn-logout">Keluar</a>
            <?php else: ?>
                <button class="btn-masuk" onclick="openModal('login')">Masuk</button>
                <button class="btn-daftar" onclick="openModal('register')">Daftar Akun</button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- CONTAINER UTAMA -->
    <div class="container">
        <?php if (!empty($error_message)): ?>
            <div class="alert-error">✕ <?= $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success" id="success-alert">✓ <?= $success_message; ?></div>
        <?php endif; ?>

        <div class="card-job">
            <div class="card-header-job">
                <h2>Lowongan Kerja</h2>
                <p>✓ Instansi Pusat Rekrutmen Rumah Sakit</p>
            </div>

            <div class="info-grid">
                <div class="info-label">Kompensasi:</div>
                <div class="info-value">Menarik</div>
                
                <div class="info-label">Kode Formasi:</div>
                <div class="info-value">LWN-5</div>
                
                <div class="info-label">Kebutuhan:</div>
                <div class="info-value">0 Orang</div>
                
                <div class="info-label">Batas Akhir:</div>
                <div class="info-value">20 Jun 2026</div>
            </div>

            <!-- LOGIKA CEK PROFIL DAHULU -->
            <?php if (!isset($_SESSION['pelamar_logged_in'])): ?>
                <button onclick="openModal('login')" class="btn-lamar">🔒 LAMAR SEKARANG</button>
            <?php else: 
                $p_id = $_SESSION['pelamar_id'];
                $cek_profil = mysqli_query($koneksi, "SELECT p.nik, p.telepon, p.alamat, pd.jenjang FROM pelamar p LEFT JOIN pelamar_pendidikan pd ON p.id = pd.pelamar_id WHERE p.id = $p_id");
                $data_cek = mysqli_fetch_assoc($cek_profil);
                
                if (empty($data_cek['nik']) || empty($data_cek['telepon']) || empty($data_cek['alamat']) || empty($data_cek['jenjang'])): ?>
                    <button onclick="alert('⚠️ Profil Anda belum lengkap! Silakan klik nama Anda di pojok kanan atas untuk melengkapi Biodata & Riwayat Pendidikan terlebih dahulu.')" class="btn-lamar" style="background-color: #eab308;">⚠️ LENGKAPI PROFIL DAHULU</button>
                <?php else: 
                    // Ambil rangkuman untuk modal jika profil sudah lengkap
                    $q_preview = mysqli_query($koneksi, "SELECT p.*, pd.jenjang, pd.institusi, pd.jurusan, pd.tahun_lulus, pd.ipk FROM pelamar p INNER JOIN pelamar_pendidikan pd ON p.id = pd.pelamar_id WHERE p.id = $p_id");
                    $d_preview = mysqli_fetch_assoc($q_preview);
                ?>
                    <button onclick="openPreviewModal()" class="btn-lamar">📄 PREVIEW & LAMAR SEKARANG</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL MASUK / DAFTAR -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-title" id="modalTitle">Masuk</div>
            
            <form method="POST" action="">
                <div class="form-group" id="nameField" style="display: none;">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="namaInput" class="form-control" placeholder="Nama lengkap Anda">
                </div>
                <div class="form-group">
                    <label>Alamat Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="nama@email.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" id="submitBtn" name="login" class="btn-submit">Masuk</button>
            </form>
            <div style="text-align: center; margin-top: 15px; font-size: 13px; color: #64748b;">
                <span id="switchText">Belum punya akun? <a href="#" onclick="openModal('register')" style="color: #2563eb; text-decoration: none;">Daftar di sini</a></span>
            </div>
        </div>
    </div>

    <!-- MODAL POP-UP PREVIEW LENGKAP SEBELUM KIRIM -->
    <?php if (isset($_SESSION['pelamar_logged_in']) && isset($d_preview)): ?>
    <div class="modal-overlay" id="previewModal">
        <div class="modal-box" style="max-width: 600px; width: 90%;">
            <button class="modal-close" onclick="closePreviewModal()">&times;</button>
            <div class="modal-title" style="color: #1e3a8a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-weight: 700;">Konfirmasi Data Lamaran Anda</div>
            
            <div style="margin-top: 15px; font-size: 14px; color: #475569; line-height: 1.6; max-height: 400px; overflow-y: auto; padding-right: 5px;">
                                <h4 style="color: #2563eb; font-size: 15px; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 3px;">A. Biodata Pelamar</h4>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 13px;">
                    <tr><td style="width: 150px; font-weight: 600; padding: 4px 0;">Nama Lengkap</td><td>: <?= htmlspecialchars($d_preview['nama_lengkap']); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">NIK</td><td>: <?= htmlspecialchars($d_preview['nik']); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">Tempat, Tgl Lahir</td><td>: <?= htmlspecialchars($d_preview['tempat_lahir']); ?>, <?= !empty($d_preview['tanggal_lahir']) ? date('d/m/Y', strtotime($d_preview['tanggal_lahir'])) : '-'; ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">Jenis Kelamin</td><td>: <?= htmlspecialchars($d_preview['jenis_kelamin']); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">Agama</td><td>: <?= htmlspecialchars($d_preview['agama']); ?></td></tr>
                    <!-- MENAMPILKAN STATUS PERKAWINAN SECARA DINAMIS -->
                    <tr><td style="font-weight: 600; padding: 4px 0;">Status Hubungan</td><td>: <?= htmlspecialchars($d_preview['status_sosial'] ?? '-'); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">No. Telepon / WA</td><td>: <?= htmlspecialchars($d_preview['telepon']); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0;">Kota & Provinsi</td><td>: <?= htmlspecialchars($d_preview['kota']); ?>, <?= htmlspecialchars($d_preview['provinsi']); ?></td></tr>
                    <tr><td style="font-weight: 600; padding: 4px 0; vertical-align: top;">Alamat Rumah</td><td>: <?= htmlspecialchars($d_preview['alamat']); ?></td></tr>
                </table>


                <!-- POTONGAN KODE BARU YANG ANDA TEMPELKAN -->
<h4 style="color: #2563eb; font-size: 15px; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 3px; margin-top: 15px;">B. Riwayat Pendidikan</h4>

<?php 
$p_id = $_SESSION['pelamar_id'];
$q_all_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $p_id ORDER BY id ASC");
$no_pend = 1;
while($d_pend = mysqli_fetch_assoc($q_all_pend)): 
?>
    <div style="margin-bottom: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <span style="font-size: 11px; font-weight: 700; color: #475569; background: #cbd5e1; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 8px;">Pendidikan #<?= $no_pend++; ?></span>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <tr><td style="width: 150px; font-weight: 600; padding: 3px 0;">Jenjang / Kampus</td><td>: <?= htmlspecialchars($d_pend['jenjang']); ?> - <?= htmlspecialchars($d_pend['institusi']); ?></td></tr>
            <tr><td style="font-weight: 600; padding: 3px 0;">Jurusan / Prodi</td><td>: <?= htmlspecialchars($d_pend['jurusan']); ?></td></tr>
            <tr><td style="font-weight: 600; padding: 3px 0;">Tahun Lulus / IPK</td><td>: Lulus Th. <?= htmlspecialchars($d_pend['tahun_lulus']); ?> (IPK/Nilai: <?= htmlspecialchars($d_pend['ipk']); ?>)</td></tr>
        </table>
    </div>
<?php endwhile; ?>


                <h4 style="color: #2563eb; font-size: 15px; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 3px;">C. Formasi yang Dilamar</h4>
                <p style="font-size: 14px; font-weight: 700; color: #1e293b; padding: 4px 0;">LWN-5 (Pusat Rekrutmen Rumah Sakit)</p>
                
                <div style="background-color: #fffba6; padding: 12px; border-radius: 8px; font-size: 12px; color: #713f12; border: 1px solid #fef08a; margin-top: 15px; font-weight: 500;">
                    ⚠️ Pastikan seluruh berkas dan biodata di atas sudah sesuai. Setelah lamaran dikirim, data tidak dapat diubah kembali.
                </div>
            </div>

            <form method="POST" action="">
                <button type="submit" name="lamar" class="btn-submit" style="background-color: #10b981; padding: 12px; font-size: 14px; font-weight: 700; margin-top: 15px;">✓ YA, DATA SUDAH BENAR & KIRIM LAMARAN</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- JAVASCRIPT KONTROL POP-UP -->
    <script>
        const modal = document.getElementById('authModal');
        const previewModal = document.getElementById('previewModal');
        const title = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const nameField = document.getElementById('nameField');
        const nameInput = document.getElementById('namaInput');
        const switchText = document.getElementById('switchText');

        function openModal(type) {
            modal.style.display = 'flex';
            if (type === 'register') {
                title.innerText = 'Daftar Akun Baru';
                submitBtn.innerText = 'Daftar';
                submitBtn.name = 'register';
                nameField.style.display = 'block';
                if (nameInput) nameInput.required = true;
                switchText.innerHTML = 'Sudah punya akun? <a href="#" onclick="openModal(\'login\')" style="color: #2563eb; text-decoration: none;">Masuk di sini</a>';
            } else {
                title.innerText = 'Masuk';
                submitBtn.innerText = 'Masuk';
                submitBtn.name = 'login';
                nameField.style.display = 'none';
                if (nameInput) nameInput.required = false;
                switchText.innerHTML = 'Belum punya akun? <a href="#" onclick="openModal(\'register\')" style="color: #2563eb; text-decoration: none;">Daftar di sini</a>';
            }
        }

        function closeModal() { modal.style.display = 'none'; }
        function openPreviewModal() { if (previewModal) previewModal.style.display = 'flex'; }
        function closePreviewModal() { if (previewModal) previewModal.style.display = 'none'; }

        window.onclick = function(event) {
            if (event.target == modal) closeModal();
            if (event.target == previewModal) closePreviewModal();
        }

        // Timer alert 5 detik
        const successAlert = document.getElementById('success-alert');
        if (successAlert) {
            setTimeout(function() {
                successAlert.style.transition = "opacity 0.5s ease";
                successAlert.style.opacity = "0";
                setTimeout(function() { successAlert.style.display = "none"; }, 500);
            }, 5000);
        }
    </script>
</body>
</html>
