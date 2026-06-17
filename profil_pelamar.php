<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// 1. PROTEKSI HALAMAN
if (!isset($_SESSION['user_pelamar_id'])) {
    header("Location: lowongan_pelamar.php");
    exit();
}

// 2. KONEKSI DATABASE
$host     = "10.10.6.59";      
$username = "root_host";       
$password = "password";        
$database = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $username, $password, $database);
if (mysqli_connect_errno()) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pelamar_id = intval($_SESSION['user_pelamar_id']);
$pesan_notifikasi = "";

// 3. FITUR: PROSES HAPUS FOTO
if (isset($_GET['action']) && $_GET['action'] == 'hapus_foto') {
    $q_foto = mysqli_query($koneksi, "SELECT foto_pelamar FROM pelamar WHERE id = $pelamar_id");
    $d_foto = mysqli_fetch_assoc($q_foto);
    
    if (!empty($d_foto['foto_pelamar'])) {
        $file_path = __DIR__ . '/uploads/' . $d_foto['foto_pelamar'];
        if (file_exists($file_path)) {
            @unlink($file_path); // Hapus file fisik
        }
        // Kosongkan nama file di database
        mysqli_query($koneksi, "UPDATE pelamar SET foto_pelamar = '', updated_at = NOW() WHERE id = $pelamar_id");
        header("Location: profil_pelamar.php?status=foto_dihapus");
        exit();
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'foto_dihapus') {
    $pesan_notifikasi = "<div class='alert sukses'>🗑️ Foto profil berhasil dihapus dari sistem!</div>";
}

// 4. PROSES SIMPAN DATA (PROFIL & PENDIDIKAN)
if (isset($_POST['simpan_profil'])) {
    $nama          = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap'] ?? '');
    $nik           = mysqli_real_escape_string($koneksi, $_POST['nik'] ?? '');
    $tempat_lahir  = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir'] ?? '');
    $telepon       = mysqli_real_escape_string($koneksi, $_POST['telepon'] ?? '');
    $alamat        = mysqli_real_escape_string($koneksi, $_POST['alamat'] ?? '');
    
    $jk_raw = $_POST['jenis_kelamin'] ?? '';
    if ($jk_raw == 'L' || $jk_raw == 'Laki-laki' || $jk_raw == 'laki-laki') {
        $jenis_kelamin = "Laki-laki"; 
    } elseif ($jk_raw == 'P' || $jk_raw == 'Perempuan' || $jk_raw == 'perempuan') {
        $jenis_kelamin = "Perempuan";
    } else {
        $jenis_kelamin = "";
    }

    $jenjang     = mysqli_real_escape_string($koneksi, $_POST['jenjang'] ?? '');
    $institusi   = mysqli_real_escape_string($koneksi, $_POST['institusi'] ?? '');
    $jurusan     = mysqli_real_escape_string($koneksi, $_POST['jurusan'] ?? '');
    
    $tahun_lulus = $_POST['tahun_lulus'] ?? '';
    $tahun_lulus_db = (!empty($tahun_lulus)) ? intval($tahun_lulus) : "NULL";

    $ipk         = $_POST['ipk'] ?? '';
    $ipk_db      = (!empty($ipk)) ? "'" . mysqli_real_escape_string($koneksi, str_replace(',', '.', $ipk)) . "'" : "NULL";

    $q_lama = mysqli_query($koneksi, "SELECT foto_pelamar FROM pelamar WHERE id = $pelamar_id");
    $d_lama = mysqli_fetch_assoc($q_lama);
    $nama_foto_db = $d_lama['foto_pelamar'] ?? '';

    // Logika upload foto fisik baru ke folder uploads
    if (isset($_FILES['foto_pelamar']) && $_FILES['foto_pelamar']['error'] === 0) {
        $file_name = $_FILES['foto_pelamar']['name'];
        $file_tmp  = $_FILES['foto_pelamar']['tmp_name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $nama_foto_baru = "foto_" . $pelamar_id . "_" . time() . "." . $file_ext;
        $target_dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        if (move_uploaded_file($file_tmp, $target_dir . $nama_foto_baru)) {
            if (!empty($nama_foto_db) && file_exists($target_dir . $nama_foto_db)) {
                @unlink($target_dir . $nama_foto_db);
            }
            $nama_foto_db = $nama_foto_baru;
        }
    }

    if (empty($nik) || empty($telepon) || empty($alamat)) {
        $pesan_notifikasi = "<div class='alert error'>❌ NIK, Nomor Telepon, dan Alamat Rumah wajib diisi.</div>";
    } else {
        // Update Akun Pelamar
        $q_update = "UPDATE pelamar SET 
                        nama_lengkap = '$nama', nik = '$nik', tempat_lahir = '$tempat_lahir', 
                        tanggal_lahir = '$tanggal_lahir', jenis_kelamin = '$jenis_kelamin', 
                        alamat = '$alamat', telepon = '$telepon', foto_pelamar = '$nama_foto_db', updated_at = NOW() 
                     WHERE id = $pelamar_id";
        mysqli_query($koneksi, $q_update);
        $_SESSION['user_pelamar_nama'] = $nama;

        // Simpan / Update Pendidikan Pelamar
        $cek_edu = mysqli_query($koneksi, "SELECT id FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
        if (mysqli_num_rows($cek_edu) > 0) {
            $q_edu = "UPDATE pelamar_pendidikan SET 
                        jenjang = '$jenjang', institusi = '$institusi', jurusan = '$jurusan', 
                        tahun_lulus = $tahun_lulus_db, ipk = $ipk_db, updated_at = NOW() 
                      WHERE pelamar_id = $pelamar_id";
        } else {
            $q_edu = "INSERT INTO pelamar_pendidikan (pelamar_id, jenjang, institusi, jurusan, tahun_lulus, ipk, created_at, updated_at) 
                      VALUES ($pelamar_id, '$jenjang', '$institusi', '$jurusan', $tahun_lulus_db, $ipk_db, NOW(), NOW())";
        }
        
        if (mysqli_query($koneksi, $q_edu)) {
            $pesan_notifikasi = "<div class='alert sukses'>🎉 Profil & Riwayat Pendidikan berhasil diperbarui!</div>";
        } else {
            $pesan_notifikasi = "<div class='alert error'>❌ Gagal menyimpan data pendidikan: " . mysqli_error($koneksi) . "</div>";
        }
    }
}

// 5. AMBIL DATA TERBARU
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$user = mysqli_fetch_assoc($query_user);

$query_edu = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
$edu = mysqli_fetch_assoc($query_edu);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pelamar</title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #94a3b8;
            --primary: #4f46e5;
            --border-color: #f1f5f9;
        }

        body { 
            font-family: 'Segoe UI', Roboto, Arial, sans-serif; 
            background-color: var(--bg-color); 
            margin: 0; 
            padding: 30px 20px; 
            color: var(--text-main);
        }

        .container { max-width: 1000px; margin: 0 auto; }

        .profile-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        .avatar-container { width: 100px; height: 100px; }

        .avatar-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            font-size: 38px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            object-fit: cover;
            border: 2px solid #fff;
        }

        .profile-info h2 { margin: 0 0 5px 0; font-size: 24px; color: #0f172a; }
        .profile-info p { margin: 0 0 12px 0; color: var(--text-muted); font-size: 14px; }
        .badge-status { background-color: #ecfdf5; color: #10b981; padding: 4px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-block; }

        .info-card { background: var(--card-bg); border-radius: 20px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: bold; color: #1e3a8a; margin: 10px 0 25px 0; text-transform: uppercase; letter-spacing: 0.05em; border-left: 4px solid var(--primary); padding-left: 10px;}

        .table-header {
            display: grid;
            grid-template-columns: 260px 1fr 120px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: bold;
            color: var(--text-muted);
            letter-spacing: 0.05em;
        }

        .form-row {
            display: grid;
            grid-template-columns: 260px 1fr 120px;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .form-row.no-border { border-bottom: none; }
        .field-label { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.02em; }

        input[type="text"], input[type="date"], input[type="number"], select, textarea {
            width: 100%;
            max-width: 480px;
            padding: 12px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            color: #334155;
            background-color: #f8fafc;
            box-sizing: border-box;
        }

        input:disabled { background-color: #f1f5f9 !important; color: #94a3b8; cursor: not-allowed; }
        textarea { height: 80px; resize: none; }

        .footer-buttons {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-save { 
            background: #4f46e5; 
            color: white; 
            border: none; 
            padding: 14px 35px; 
            border-radius: 12px; 
            font-weight: bold; 
            font-size: 15px; 
            cursor: pointer; 
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); 
        }
        .btn-back { 
            color: #4f46e5; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 15px; 
        }

        .alert { padding: 15px; margin-bottom: 25px; border-radius: 12px; font-weight: 600; font-size: 14px; }
        .sukses { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bcf0da; }
        .error { background-color: #fef2f2; color: #dc2626; border: 1px solid #f8b4b4; }
        
        .btn-hapus-foto { background-color: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: bold; text-decoration: none; display: inline-block; text-align: center; }
        .btn-hapus-foto:hover { background-color: #fee2e2; }
    </style>
</head>
<body>

<div class="container">

    <!-- 1. BADGE PROFILE ATAS -->
    <div class="profile-card">
        <div class="avatar-container">
            <?php if (!empty($user['foto_pelamar']) && file_exists(__DIR__ . '/uploads/' . $user['foto_pelamar'])): ?>
                <img src="uploads/<?php echo $user['foto_pelamar']; ?>?t=<?php echo time(); ?>" class="avatar-circle" alt="Foto">
            <?php else: ?>
                <div class="avatar-circle"><?php echo strtoupper(substr($user['nama_lengkap'] ?? 'A', 0, 1)); ?></div>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['nama_lengkap'] ?? 'Nama Pelamar'); ?></h2>
            <p>Terdaftar Sejak: 17 June 2026</p>
            <span class="badge-status">AKTIF</span>
        </div>
    </div>

    <?php echo $pesan_notifikasi; ?>

    <!-- SATU FORM INDUK MEMBUNGKUS SELURUH BLOK INPUT AGAR STRUKTUR GRID TIDAK DOUBLE -->
    <form action="" method="POST" enctype="multipart/form-data">

        <!-- KARTU FORM 1: INFORMASI PROFIL -->
        <div class="info-card">
            <div class="section-title">Informasi Data Akun & Profil</div>
            
            <div class="table-header">
                <div>KATEGORI KREDENSIAL</div>
                <div>DATA INFORMASI AKUN</div>
                <div style="text-align: right;">AKSI</div>
            </div>

            <!-- Baris Foto Profil -->
            <div class="form-row">
                <div class="field-label">Foto Fisik Pelamar</div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php if (!empty($user['foto_pelamar']) && file_exists(__DIR__ . '/uploads/' . $user['foto_pelamar'])): ?>
                        <img src="uploads/<?php echo $user['foto_pelamar']; ?>?t=<?php echo time(); ?>" alt="Foto" style="width: 55px; height: 55px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0;">
                    <?php else: ?>
                        <div style="width: 55px; height: 55px; border-radius: 8px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #94a3b8; border: 1px solid #e2e8f0;">🖼️</div>
                    <?php endif; ?>
                    <input type="file" name="foto_pelamar" accept="image/*" style="max-width: 320px;">
                </div>
                <div style="text-align: right;">
                    <?php if (!empty($user['foto_pelamar'])): ?>
                        <a href="profil_pelamar.php?action=hapus_foto" class="btn-hapus-foto" onclick="return confirm('Apakah Anda yakin ingin menghapus foto?')">Hapus Foto</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="field-label">Nama Lengkap</div>
                <div><input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" required></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Alamat Email</div>
                <div><input type="text" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">NIK (KTP) *</div>
                <div><input type="text" name="nik" value="<?php echo htmlspecialchars($user['nik'] ?? ''); ?>" required></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Nomor Telepon/WA *</div>
                <div><input type="text" name="telepon" value="<?php echo htmlspecialchars($user['telepon'] ?? ''); ?>" required></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Tempat Lahir</div>
                <div><input type="text" name="tempat_lahir" value="<?php echo htmlspecialchars($user['tempat_lahir'] ?? ''); ?>"></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Tanggal Lahir</div>
                <div><input type="date" name="tanggal_lahir" value="<?php echo htmlspecialchars($user['tanggal_lahir'] ?? ''); ?>"></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Jenis Kelamin</div>
                <div>
                    <select name="jenis_kelamin">
                        <option value="">-- Pilih --</option>
                        <option value="Laki-laki" <?php echo (isset($user['jenis_kelamin']) && strpos(strtolower($user['jenis_kelamin']), 'laki') !== false || ($user['jenis_kelamin'] ?? '') == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="Perempuan" <?php echo (isset($user['jenis_kelamin']) && strpos(strtolower($user['jenis_kelamin']), 'perem') !== false || ($user['jenis_kelamin'] ?? '') == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>
                <div></div>
            </div>

            <div class="form-row no-border">
                <div class="field-label">Alamat Rumah *</div>
                <div><textarea name="alamat" required><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea></div>
                <div></div>
            </div>
        </div>

        <!-- 🏢 KARTU FORM 2: DATA PENDIDIKAN TERBARU -->
        <div class="info-card">
            <div class="section-title">Riwayat Pendidikan Terakhir</div>
            
            <div class="table-header">
                <div>KATEGORI AKADEMIK</div>
                <div>DATA PENDIDIKAN ANDA</div>
                <div style="text-align: right;"></div>
            </div>

            <div class="form-row">
                <div class="field-label">Jenjang Pendidikan</div>
                <div>
                    <select name="jenjang">
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="SMA/SMK" <?php echo (($edu['jenjang'] ?? '') == 'SMA/SMK') ? 'selected' : ''; ?>>SMA / SMK</option>
                        <option value="D3" <?php echo (($edu['jenjang'] ?? '') == 'D3') ? 'selected' : ''; ?>>D3</option>
                        <option value="S1" <?php echo (($edu['jenjang'] ?? '') == 'S1') ? 'selected' : ''; ?>>S1 / D4</option>
                        <option value="S2" <?php echo (($edu['jenjang'] ?? '') == 'S2') ? 'selected' : ''; ?>>S2</option>
                    </select>
                </div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Nama Institusi / Kampus</div>
                <div><input type="text" name="institusi" placeholder="Contoh: Universitas Dian Nuswantoro" value="<?php echo htmlspecialchars($edu['institusi'] ?? ''); ?>"></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Program Studi / Jurusan</div>
                <div><input type="text" name="jurusan" placeholder="Contoh: S1 Kesehatan Masyarakat" value="<?php echo htmlspecialchars($edu['jurusan'] ?? ''); ?>"></div>
                <div></div>
            </div>

            <div class="form-row">
                <div class="field-label">Tahun Lulus</div>
                <div><input type="number" name="tahun_lulus" min="1990" max="2030" placeholder="Contoh: 2024" value="<?php echo htmlspecialchars($edu['tahun_lulus'] ?? ''); ?>"></div>
                <div></div>
            </div>

            <div class="form-row no-border">
                <div class="field-label">Nilai IPK / Nilai Rata-rata</div>
                <div><input type="text" name="ipk" placeholder="Contoh: 3.75" value="<?php echo htmlspecialchars($edu['ipk'] ?? ''); ?>"></div>
                <div></div>
            </div>
        </div>

        <!-- AREA TOMBOL AKSI BAWAH -->
        <div class="footer-buttons" style="background: white; padding: 20px 40px; border-radius: 20px; border: 1px solid #e2e8f0; margin-top: 25px;">
            <a href="lowongan_pelamar.php" class="btn-back">← Kembali ke Lowongan</a>
            <button type="submit" name="simpan_profil" class="btn-save">Simpan Semua Perubahan</button>
        </div>

    </form>
</div>

</body>
</html>
