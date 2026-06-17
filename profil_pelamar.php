<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

if (!isset($_SESSION['user_pelamar_id'])) {
    header("Location: lowongan_pelamar.php");
    exit();
}

$pelamar_id = intval($_SESSION['user_pelamar_id']);
$pesan_notifikasi = "";

// =========================================================================
// FITUR BARU: LOGIKA PROSES HAPUS FOTO PELAMAR (VIA URL ACTION)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'hapus_foto') {
    $q_foto = mysqli_query($koneksi, "SELECT foto_pelamar FROM pelamar WHERE id = $pelamar_id");
    $data_foto = mysqli_fetch_assoc($q_foto);
    
    if (!empty($data_foto['foto_pelamar'])) {
        $file_path = "uploads/" . $data_foto['foto_pelamar'];
        // Hapus file gambar fisik dari folder uploads proyek Anda
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        // Set kolom foto_pelamar di database menjadi NULL kembali
        mysqli_query($koneksi, "UPDATE pelamar SET foto_pelamar = NULL, updated_at = NOW() WHERE id = $pelamar_id");
    }
    
    header("Location: profil_pelamar.php?update=hapus_sukses");
    exit();
}

// =========================================================================
// LOGIKA PROSES SIMPAN & UPLOAD BIODATA LENGKAP PELAMAR
// =========================================================================
if (isset($_POST['simpan_profil_pelamar']) || (isset($_FILES['foto_pelamar']) && $_FILES['foto_pelamar']['error'] === 0)) {
    
    $q_foto_lama = mysqli_query($koneksi, "SELECT foto_pelamar FROM pelamar WHERE id = $pelamar_id");
    $data_foto_lama = mysqli_fetch_assoc($q_foto_lama);
    $nama_foto_db = $data_foto_lama['foto_pelamar'] ?? '';

    // A. PEMROSESAN UPLOAD FILE PAS FOTO INSTAN
    if (isset($_FILES['foto_pelamar']) && $_FILES['foto_pelamar']['error'] === 0) {
        $file_name = $_FILES['foto_pelamar']['name'];
        $file_tmp  = $_FILES['foto_pelamar']['tmp_name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!empty($nama_foto_db) && file_exists("uploads/" . $nama_foto_db)) {
            unlink("uploads/" . $nama_foto_db);
        }

        $nama_foto_db = "foto_" . $pelamar_id . "_" . time() . "." . $file_ext;
        move_uploaded_file($file_tmp, "uploads/" . $nama_foto_db);

        mysqli_query($koneksi, "UPDATE pelamar SET foto_pelamar = '$nama_foto_db', updated_at = NOW() WHERE id = $pelamar_id");
    }

    // B. PEMROSESAN TOMBOL SIMPAN UTAMA (UPDATE BIODATA TEKS)
    if (isset($_POST['simpan_profil_pelamar'])) {
        $nama_lengkap  = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
        $nik           = mysqli_real_escape_string($koneksi, trim($_POST['nik']));
        $tempat_lahir  = mysqli_real_escape_string($koneksi, trim($_POST['tempat_lahir']));
        $tanggal_lahir = mysqli_real_escape_string($koneksi, trim($_POST['tanggal_lahir']));
        $jenis_kelamin = mysqli_real_escape_string($koneksi, trim($_POST['jenis_kelamin']));
        $agama         = mysqli_real_escape_string($koneksi, trim($_POST['agama']));
        $status_sosial = mysqli_real_escape_string($koneksi, trim($_POST['status_sosial']));
        $alamat        = mysqli_real_escape_string($koneksi, trim($_POST['alamat']));
        $kota          = mysqli_real_escape_string($koneksi, trim($_POST['kota']));
        $provinsi      = mysqli_real_escape_string($koneksi, trim($_POST['provinsi']));
        $telepon       = mysqli_real_escape_string($koneksi, trim($_POST['telepon']));

        $q_update = "UPDATE pelamar SET 
                        nama_lengkap = '$nama_lengkap',
                        nik = '$nik', 
                        tempat_lahir = '$tempat_lahir', 
                        tanggal_lahir = '$tanggal_lahir', 
                        jenis_kelamin = '$jenis_kelamin', 
                        agama = '$agama', 
                        status_sosial = '$status_sosial',
                        alamat = '$alamat', 
                        kota = '$kota', 
                        provinsi = '$provinsi', 
                        telepon = '$telepon', 
                        updated_at = NOW() 
                     WHERE id = $pelamar_id";

        mysqli_query($koneksi, $q_update);
        $_SESSION['user_pelamar_nama'] = $nama_lengkap;
    }

    header("Location: profil_pelamar.php?update=sukses");
    exit();
}

// Set Notifikasi Alert box dinamis
if (isset($_GET['update'])) {
    if ($_GET['update'] == 'sukses') {
        $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box sukses'>🎉 Biodata profil Anda berhasil disimpan ke sistem!</div>";
    } elseif ($_GET['update'] == 'hapus_sukses') {
        $pesan_notifikasi = "<div id='alert-auto-close' class='alert-box sukses' style='background-color:#fee2e2; color:#991b1b; border:1px solid #fecaca;'>🗑️ Pas foto resmi Anda telah berhasil dihapus!</div>";
    }
}

$res_pelamar = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data_p = mysqli_fetch_assoc($res_pelamar);
$inisial = !empty($data_p['nama_lengkap']) ? strtoupper(substr($data_p['nama_lengkap'], 0, 1)) : 'P';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pelamar - Portal Karir</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f8fafc; color: #475569; padding-bottom: 80px; }
        
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .nav-container { max-width: 1100px; margin: 0 auto; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 900; color: #0f172a; text-decoration: none; letter-spacing: -0.5px; }
        .btn-kembali { font-size: 13px; color: #4f46e5; text-decoration: none; font-weight: 700; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; background: white; transition: all 0.2s; }
        .btn-kembali:hover { background: #f8fafc; border-color: #cbd5e1; }

        main { max-width: 1100px; margin: 35px auto; padding: 0 24px; display: flex; flex-direction: column; gap: 25px; }
        
        .profile-summary-card { background: white; border: 1px solid #e2e8f0; border-radius: 24px; padding: 30px; display: flex; align-items: center; gap: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .avatar-circle { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 800; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.15); object-fit: cover; border: 3px solid white; }
        .summary-info h2 { font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.5px; }
        .summary-info .sub-text { font-size: 13px; color: #94a3b8; font-weight: 500; margin-bottom: 10px; }
        .status-badge { display: inline-block; font-size: 11px; font-weight: 800; color: #10b981; background: #e6fbf3; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-upload-label { font-size: 11px; font-weight: 700; color: #4f46e5; cursor: pointer; background: #f5f3ff; padding: 8px 14px; border-radius: 10px; display: inline-block; border: 1px solid #e0e7ff; }

        /* TOMBOL HAPUS FOTO GAYA MERAH MINIMALIS */
        .btn-delete-photo { font-size: 11px; font-weight: 700; color: #ef4444; text-decoration: none; background: #fff5f5; padding: 8px 14px; border-radius: 10px; display: inline-block; border: 1px solid #fecaca; text-align: center; }
        .btn-delete-photo:hover { background: #ef4444; color: white !important; border-color: #ef4444; }

        .info-card { background: white; border: 1px solid #e2e8f0; border-radius: 24px; padding: 35px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .table-header-row { display: grid; grid-template-columns: 1.5fr 3fr 1fr; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 10px; }
        .th-text { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .info-row { display: grid; grid-template-columns: 1.5fr 3fr 1fr; align-items: center; padding: 14px 0; border-bottom: 1px solid #f8fafc; }
        .info-row:last-of-type { border-bottom: none; }
        .row-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-control { width: 100%; padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; background: #f8fafc; transition: all 0.2s; font-weight: 500; }
        .form-control:focus { outline: none; border-color: #4f46e5; background: white; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; }
        .btn-edit-action { background: #f5f3ff; color: #4f46e5; border: 1px solid #e0e7ff; padding: 10px 24px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; text-align: center; transition: all 0.2s; width: 100%; display: block; border: none; }
-weight: 700; font-size: 13px; cursor: pointer; text-align: center; transition: all 0.2s; width: 100%; display: block; border: none; }
        .btn-edit-action:hover { background: #4f46e5; color: white; border-color: #4f46e5; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15); }

        .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 5px; font-size: 14px; font-weight: 600; text-align: center; }
        .alert-box.sukses { background-color: #e6fbf3; color: #10b981; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

    <header>
        <div class="nav-container">
            <a href="lowongan_pelamar.php" class="nav-brand">PORTAL KARIR</a>
            <div class="user-info">
                <a href="lowongan_pelamar.php" class="btn-kembali">← Kembali ke Lowongan</a>
            </div>
        </div>
    </header>

    <main>
        <?php if (!empty($pesan_notifikasi)) echo $pesan_notifikasi; ?>

        <div class="profile-summary-card">
            <div class="avatar-container">
                <?php if (!empty($data_p['foto_pelamar']) && file_exists("uploads/" . $data_p['foto_pelamar'])): ?>
                    <img src="uploads/<?= $data_p['foto_pelamar']; ?>?t=<?= time(); ?>" class="avatar-circle" alt="Foto">
                <?php else: ?>
                    <div class="avatar-circle"><?= $inisial; ?></div>
                <?php endif; ?>
            </div>
            <div class="summary-info">
                <h2><?= htmlspecialchars($data_p['nama_lengkap'] ?? 'Nama Pelamar'); ?></h2>
                <div class="sub-text">Terdaftar Sejak: <?= date('d F Y', strtotime($data_p['created_at'] ?? 'now')); ?></div>
                <span class="status-badge">Aktif</span>
            </div>
        </div>

        <div class="info-card">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="table-header-row">
                    <div class="th-text">Kategori Kredensial</div>
                    <div class="th-text">Data Informasi Akun / Biodata</div>
                    <div class="th-text" style="text-align: center;">Aksi</div>
                </div>

                <!-- Pas Foto Resmi Dengan Fitur Ganti & Hapus Bersandingan -->
                <div class="info-row" style="padding: 20px 0;">
                    <div class="row-label">Pas Foto Resmi</div>
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <div style="width: 85px; height: 110px; border-radius: 8px; border: 2px solid #e2e8f0; background: #f8fafc; overflow: hidden; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                            <?php if (!empty($data_p['foto_pelamar']) && file_exists("uploads/" . $data_p['foto_pelamar'])): ?>
                                <img src="uploads/<?= $data_p['foto_pelamar']; ?>?t=<?= time(); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Foto Pelamar">
                            <?php else: ?>
                                <svg xmlns="http://w3.org" viewBox="0 0 24 24" fill="#cbd5e1" style="width: 40px; height: 40px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5-4-8-4z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label class="btn-upload-label">
                                📂 Ganti File Foto
                                <input type="file" name="foto_pelamar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                            </label>
                            
                            <?php if (!empty($data_p['foto_pelamar'])): ?>
                                <a href="profil_pelamar.php?action=hapus_foto" class="btn-delete-photo" onclick="return confirm('Apakah Anda yakin ingin menghapus pas foto resmi Anda secara permanen?')">🗑️ Hapus Foto</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div></div>
                </div>

                <!-- Nama Lengkap -->
                <div class="info-row">
                    <div class="row-label">Nama Lengkap</div>
                    <div>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data_p['nama_lengkap'] ?? ''); ?>" required>
                    </div>
                    <div></div>
                </div>

                <!-- Alamat Email -->
                <div class="info-row">
                    <div class="row-label">Alamat Email</div>
                    <div>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($data_p['email'] ?? ''); ?>" readonly disabled>
                    </div>
                    <div></div>
                </div>

                <!-- Nomor NIK KTP -->
                <div class="info-row">
                    <div class="row-label">Nomor NIK KTP</div>
                    <div>
                        <input type="text" name="nik" class="form-control" placeholder="16 Digit NIK KTP" value="<?= htmlspecialchars($data_p['nik'] ?? ''); ?>" required maxlength="16" minlength="16">
                    </div>
                    <div></div>
                </div>

                <!-- Lahir -->
                <div class="info-row">
                    <div class="row-label">Lahir (Kota / Tgl)</div>
                    <div>
                        <div class="form-row-grid">
                            <input type="text" name="tempat_lahir" class="form-control" placeholder="Kota" value="<?= htmlspecialchars($data_p['tempat_lahir'] ?? ''); ?>" required>
                            <input type="date" name="tanggal_lahir" class="form-control" value="<?= htmlspecialchars($data_p['tanggal_lahir'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div></div>
                </div>

                <!-- Gender & Agama -->
                <div class="info-row">
                    <div class="row-label">Gender & Agama</div>
                    <div>
                        <div class="form-row-grid">
                            <select name="jenis_kelamin" class="form-control" required>
                                <option value="Laki-Laki" <?= ($data_p['jenis_kelamin'] == 'Laki-Laki') ? 'selected' : ''; ?>>Laki-Laki</option>
                                <option value="Perempuan" <?= ($data_p['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                            <select name="agama" class="form-control" required>
                                <option value="Islam" <?= ($data_p['agama'] == 'Islam') ? 'selected' : ''; ?>>Islam</option>
                                <option value="Kristen Protestan" <?= ($data_p['agama'] == 'Kristen Protestan') ? 'selected' : ''; ?>>Kristen Protestan</option>
                                <option value="Katolik" <?= ($data_p['agama'] == 'Katolik') ? 'selected' : ''; ?>>Katolik</option>
                                <option value="Hindu" <?= ($data_p['agama'] == 'Hindu') ? 'selected' : ''; ?>>Hindu</option>
                                <option value="Buddha" <?= ($data_p['agama'] == 'Buddha') ? 'selected' : ''; ?>>Buddha</option>
                                <option value="Khonghucu" <?= ($data_p['agama'] == 'Khonghucu') ? 'selected' : ''; ?>>Khonghucu</option>
                            </select>
                        </div>
                    </div>
                    <div></div>
                </div>

                <!-- Status Sosial -->
                <div class="info-row">
                    <div class="row-label">Status Sosial</div>
                    <div>
                        <select name="status_sosial" class="form-control" required>
                            <option value="Belum Kawin" <?= ($data_p['status_sosial'] == 'Belum Kawin') ? 'selected' : ''; ?>>Belum Kawin</option>
                            <option value="Kawin" <?= ($data_p['status_sosial'] == 'Kawin') ? 'selected' : ''; ?>>Kawin</option>
                        </select>
                    </div>
                    <div></div>
                </div>

                <!-- Alamat Jalan -->
                <div class="info-row">
                    <div class="row-label">Alamat Jalan</div>
                    <div>
                        <input type="text" name="alamat" class="form-control" placeholder="Nama Jalan, RT/RW, Dusun" value="<?= htmlspecialchars($data_p['alamat'] ?? ''); ?>" required>
                    </div>
                    <div></div>
                </div>

                <!-- Kota & Provinsi -->
                <div class="info-row">
                    <div class="row-label">Kota & Provinsi</div>
                    <div>
                        <div class="form-row-grid">
                            <input type="text" name="kota" class="form-control" placeholder="Kabupaten/Kota" value="<?= htmlspecialchars($data_p['kota'] ?? ''); ?>" required>
                            <input type="text" name="provinsi" class="form-control" placeholder="Provinsi" value="<?= htmlspecialchars($data_p['provinsi'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div></div>
                </div>

                <!-- Nomor HP / WA -->
                <div class="info-row">
                    <div class="row-label">Nomor HP / WA</div>
                    <div>
                        <input type="tel" name="telepon" class="form-control" placeholder="Contoh: 081234567" value="<?= htmlspecialchars($data_p['telepon'] ?? ''); ?>" required>
                    </div>
                    <div></div>
                </div>

                <!-- BARIS AKSI DETAIL: TOMBOL SIMPAN LURUS DI POJOK KANAN BAWAH -->
                <div class="info-row" style="padding-top: 25px;">
                    <div></div>
                    <div></div>
                    <div style="text-align: center;">
                        <button type="submit" name="simpan_profil_pelamar" class="btn-edit-action">Simpan</button>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var alertBox = document.getElementById("alert-auto-close");
            if (alertBox) {
                setTimeout(function() {
                    alertBox.style.transition = "opacity 0.5s ease";
                    alertBox.style.opacity = "0";
                    setTimeout(function() { alertBox.remove(); }, 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>
