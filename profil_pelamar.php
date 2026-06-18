<?php 
session_start(); 

// 1. PROTEKSI HALAMAN: JIKA BELUM LOGIN, LEMPAR KEMBALI KE HALAMAN LOWONGAN
if (!isset($_SESSION['pelamar_logged_in'])) {
    header("Location: lowongan_pelamar.php");
    exit;
}

// 2. PENGATURAN KONEKSI DATABASE SERVER
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
$pelamar_id = $_SESSION['pelamar_id'];

// 3. LOGIKA PROSES UPDATE PROFIL BIODATA (TABEL: pelamar)
if (isset($_POST['update_profil'])) {
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nik           = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir  = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama         = mysqli_real_escape_string($koneksi, $_POST['agama']);
    $status_sosial = mysqli_real_escape_string($koneksi, $_POST['status_sosial']); 
    $telepon       = mysqli_real_escape_string($koneksi, $_POST['telepon']);
    $alamat        = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $kota          = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $provinsi      = mysqli_real_escape_string($koneksi, $_POST['provinsi']);
    
    $query_lama = mysqli_query($koneksi, "SELECT foto FROM pelamar WHERE id = $pelamar_id");
    $data_lama = mysqli_fetch_assoc($query_lama);
    $nama_foto_baru = isset($data_lama['foto']) ? $data_lama['foto'] : ""; 

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
            
        try {
            if (mysqli_query($koneksi, $query_update)) {
                $_SESSION['pelamar_nama'] = $nama_lengkap;
                $success_message = "Profil biodata Anda berhasil diperbarui!";
            } else {
                $error_message = "Gagal memperbarui data profil: " . mysqli_error($koneksi);
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_message = "⚠️ Gagal menyimpan! NIK tersebut sudah terdaftar pada akun lain.";
            } else {
                $error_message = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
    }
}

// 4. LOGIKA PROSES SIMPAN MULTI PENDIDIKAN (TABEL: pelamar_pendidikan)
if (isset($_POST['simpan_pendidikan'])) {
    mysqli_query($koneksi, "DELETE FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");

    $jenjang_arr     = isset($_POST['jenjang']) ? $_POST['jenjang'] : [];
    $institusi_arr   = isset($_POST['institusi']) ? $_POST['institusi'] : [];
    $jurusan_arr     = isset($_POST['jurusan']) ? $_POST['jurusan'] : [];
    $tahun_lulus_arr = isset($_POST['tahun_lulus']) ? $_POST['tahun_lulus'] : [];
    $ipk_arr         = isset($_POST['ipk']) ? $_POST['ipk'] : [];

    $sukses_insert = true;

    for ($i = 0; $i < count($jenjang_arr); $i++) {
        $jenjang     = mysqli_real_escape_string($koneksi, $jenjang_arr[$i]);
        $institusi   = mysqli_real_escape_string($koneksi, $institusi_arr[$i]);
        $jurusan     = mysqli_real_escape_string($koneksi, $jurusan_arr[$i]);
        $tahun_lulus = intval($tahun_lulus_arr[$i]);
        $ipk         = mysqli_real_escape_string($koneksi, $ipk_arr[$i]);

        if (!empty($jenjang) && !empty($institusi)) {
            $query_pend = "INSERT INTO pelamar_pendidikan (pelamar_id, jenjang, institusi, jurusan, tahun_lulus, ipk, created_at, updated_at) 
                           VALUES ($pelamar_id, '$jenjang', '$institusi', '$jurusan', '$tahun_lulus', '$ipk', NOW(), NOW())";
            if (!mysqli_query($koneksi, $query_pend)) {
                $sukses_insert = false;
            }
        }
    }

    if ($sukses_insert) {
        $success_message = "Semua riwayat pendidikan berhasil diperbarui!";
    } else {
        $error_message = "Terjadi kesalahan saat menyimpan data pendidikan.";
    }
}

// 5. LOGIKA PROSES SIMPAN RIWAYAT PENGALAMAN
if (isset($_POST['simpan_pengalaman'])) {
    $perusahaan    = mysqli_real_escape_string($koneksi, $_POST['perusahaan']);
    $jabatan       = mysqli_real_escape_string($koneksi, $_POST['jabatan']);
    $mulai_kerja   = mysqli_real_escape_string($koneksi, $_POST['mulai_kerja']);
    $selesai_kerja = !empty($_POST['selesai_kerja']) ? mysqli_real_escape_string($koneksi, $_POST['selesai_kerja']) : NULL;
    $alasan_keluar = mysqli_real_escape_string($koneksi, $_POST['alasan_keluar']);

    $sql_buat_tabel = "CREATE TABLE IF NOT EXISTS `pelamar_pengalaman` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `pelamar_id` INT NOT NULL,
      `perusahaan` VARCHAR(255) NOT NULL,
      `jabatan` VARCHAR(255) NOT NULL,
      `mulai_kerja` DATE NOT NULL,
      `selesai_kerja` DATE NULL,
      `alasan_keluar` TEXT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    mysqli_query($koneksi, $sql_buat_tabel);

    $cek_data = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id");

    if (mysqli_num_rows($cek_data) > 0) {
        $query_exp = "UPDATE pelamar_pengalaman SET 
                        perusahaan = '$perusahaan', 
                        jabatan = '$jabatan', 
                        mulai_kerja = '$mulai_kerja', 
                        selesai_kerja = " . ($selesai_kerja ? "'$selesai_kerja'" : "NULL") . ", 
                        alasan_keluar = '$alasan_keluar',
                        updated_at = NOW() 
                      WHERE pelamar_id = $pelamar_id";
    } else {
        $query_exp = "INSERT INTO pelamar_pengalaman (pelamar_id, perusahaan, jabatan, mulai_kerja, selesai_kerja, alasan_keluar, created_at, updated_at) 
                      VALUES ($pelamar_id, '$perusahaan', '$jabatan', '$mulai_kerja', " . ($selesai_kerja ? "'$selesai_kerja'" : "NULL") . ", '$alasan_keluar', NOW(), NOW())";
    }

    if (mysqli_query($koneksi, $query_exp)) {
        $success_message = "Riwayat pengalaman Anda berhasil disimpan!";
    } else {
        $error_message = "Gagal menyimpan riwayat pengalaman: " . mysqli_error($koneksi);
    }
}

// 6. LOGIKA PROSES SIMPAN MULTI DATA STR
if (isset($_POST['simpan_str'])) {
    $sql_buat_tabel_str = "CREATE TABLE IF NOT EXISTS `pelamar_str` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `pelamar_id` INT NOT NULL,
      `nomor_str` VARCHAR(100) NOT NULL,
      `tanggal_terbit` DATE NOT NULL,
      `tanggal_expired` DATE NOT NULL,
      `file_str` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($koneksi, $sql_buat_tabel_str);

    $nomor_str_arr   = isset($_POST['nomor_str']) ? $_POST['nomor_str'] : [];
    $tgl_terbit_arr  = isset($_POST['tanggal_terbit']) ? $_POST['tanggal_terbit'] : [];
    $tgl_expired_arr = isset($_POST['tanggal_expired']) ? $_POST['tanggal_expired'] : [];
    $sukses_insert_str = true;

    for ($i = 0; $i < count($nomor_str_arr); $i++) {
        $nomor_str   = mysqli_real_escape_string($koneksi, $nomor_str_arr[$i]);
        $tgl_terbit  = mysqli_real_escape_string($koneksi, $tgl_terbit_arr[$i]);
        $tgl_expired = mysqli_real_escape_string($koneksi, $tgl_expired_arr[$i]);
        $nama_file_str = "";

        if (!empty($nomor_str)) {
            if (isset($_FILES['file_str']['name'][$i]) && $_FILES['file_str']['name'][$i] != '') {
                $file_name = $_FILES['file_str']['name'][$i];
                $file_tmp  = $_FILES['file_str']['tmp_name'][$i];
                $ekstensi  = pathinfo($file_name, PATHINFO_EXTENSION);
                
                $nama_file_str = "str_" . $pelamar_id . "_" . $i . "_" . time() . "." . $ekstensi;
                $folder_tujuan = "uploads/" . $nama_file_str;

                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                move_uploaded_file($file_tmp, $folder_tujuan);
            }

$query_insert_str = "INSERT INTO pelamar_str (pelamar_id, nomor_str, tanggal_terbit, tanggal_expired, file_str, created_at, updated_at) 
                                 VALUES ($pelamar_id, '$nomor_str', '$tgl_terbit', '$tgl_expired', '$nama_file_str', NOW(), NOW())";
            if (!mysqli_query($koneksi, $query_insert_str)) {
                $sukses_insert_str = false;
            }
        }
    }

    if ($sukses_insert_str) {
        $success_message = "Semua data STR berhasil diperbarui!";
    } else {
        $error_message = "Terjadi kesalahan saat menyimpan data STR.";
    }
}

// 7. QUERY AMBIL DATA UNTUK DITAMPILKAN KEMBALI KE FORM
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data = mysqli_fetch_assoc($query_user);

$query_pend_aktif = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
$list_pendidikan = [];
while ($row = mysqli_fetch_assoc($query_pend_aktif)) {
    $list_pendidikan[] = $row;
}

$query_exp_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_pengalaman'");
if (mysqli_num_rows($query_exp_cek) > 0) {
    $query_exp_tampil = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id LIMIT 1");
    if ($data_exp = mysqli_fetch_assoc($query_exp_tampil)) {
        $data['perusahaan'] = $data_exp['perusahaan'];
        $data['jabatan'] = $data_exp['jabatan'];
        $data['mulai_kerja'] = $data_exp['mulai_kerja'];
        $data['selesai_kerja'] = $data_exp['selesai_kerja'];
        $data['alasan_keluar'] = $data_exp['alasan_keluar'];
    }
}

$list_str = [];
$query_str_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_str'");
if (mysqli_num_rows($query_str_cek) > 0) {
    $query_str_tampil = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($query_str_tampil)) {
        $list_str[] = $row;
    }
}

$daftar_berkas_saved = [];
$query_ambil_berkas = mysqli_query($koneksi, "SELECT jenis_berkas, nama_file FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
if ($query_ambil_berkas) {
    while ($row_berkas = mysqli_fetch_assoc($query_ambil_berkas)) {
        $daftar_berkas_saved[$row_berkas['jenis_berkas']] = $row_berkas['nama_file'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pelamar</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
        body { background-color: #f8fafc; color: #334155; padding-bottom: 60px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; background: #fff; border-bottom: 1px solid #eef2f5; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .brand { font-size: 18px; font-weight: bold; color: #1e293b; text-decoration: none; }
        .btn-kembali { background-color: #64748b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        .main-layout { display: grid; grid-template-columns: 1fr 1.3fr; gap: 25px; align-items: start; }
        @media (max-width: 992px) { .main-layout { grid-template-columns: 1fr; } }
        .card-profil { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .card-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
        .alert-success { background-color: #f0fff4; border: 1px solid #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .alert-error { background-color: #fff5f5; border: 1px solid #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #334155; background: #fff; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-simpan-full { width: 100%; padding: 11px; font-size: 14px; font-weight: bold; color: white; background-color: #0d6efd; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-simpan-full.btn-hijau { background-color: #198754; }
        .btn-tambah-header { background-color: #e2e8f0; color: #334155; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .pendidikan-item, .item-form-str { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 15px; background: #f8fafc; }
        .btn-hapus-item { background-color: #dc3545; color: white; padding: 4px 10px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="#" class="brand">Sistem Rekrutmen Magang</a>
    <a href="lowongan_pelamar.php" class="btn-kembali">← Kembali</a>
</div>

<div class="container">
    <?php if (!empty($error_message)) : ?><div class="alert-error"><?= $error_message; ?></div><?php endif; ?>
    <?php if (!empty($success_message)) : ?><div class="alert-success"><?= $success_message; ?></div><?php endif; ?>

    <div class="main-layout">
        
        <!-- ==================== SISI KIRI: BIODATA PROFIL ==================== -->
        <div class="card-profil">
            <h2 class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Data Biodata Profil</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width" style="text-align: center;">
                        <?php if (!empty($data['foto'])) : ?>
                            <img src="uploads/<?= $data['foto']; ?>" alt="Foto" style="max-width: 110px; border-radius: 50%; border: 3px solid #cbd5e1; margin-bottom: 10px; height: 110px; object-fit: cover;">
                        <?php else : ?>
                            <div style="width: 100px; height: 100px; background: #e2e8f0; border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px;">Tanpa Foto</div>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control" style="font-size: 12px;">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= isset($data['nama_lengkap']) ? htmlspecialchars($data['nama_lengkap']) : ''; ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label>NIK</label>
                        <input type="text" name="nik" class="form-control" value="<?= isset($data['nik']) ? htmlspecialchars($data['nik']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control" value="<?= isset($data['tempat_lahir']) ? htmlspecialchars($data['tempat_lahir']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= isset($data['tanggal_lahir']) ? $data['tanggal_lahir'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control" required>
                            <option value="Laki-laki" <?= (isset($data['jenis_kelamin']) && $data['jenis_kelamin'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?= (isset($data['jenis_kelamin']) && $data['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Agama</label>
                        <input type="text" name="agama" class="form-control" value="<?= isset($data['agama']) ? htmlspecialchars($data['agama']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status Hubungan</label>
                        <input type="text" name="status_sosial" class="form-control" value="<?= isset($data['status_sosial']) ? htmlspecialchars($data['status_sosial']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?= isset($data['telepon']) ? htmlspecialchars($data['telepon']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                    <label>Kota</label>
                    <input type="text" name="kota" class="form-control" value="<?= isset($data['kota']) ? htmlspecialchars($data['kota']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Provinsi</label>
                    <input type="text" name="provinsi" class="form-control" value="<?= isset($data['provinsi']) ? htmlspecialchars($data['provinsi']) : ''; ?>" required>
                </div>
                <div class="form-group full-width">
                    <label>Alamat Lengkap</label>
                    <input type="text" name="alamat" class="form-control" value="<?= isset($data['alamat']) ? htmlspecialchars($data['alamat']) : ''; ?>" required>
                </div>
            </div>
            <button type="submit" name="update_profil" class="btn-simpan-full">Perbarui Biodata Profil</button>
        </form>
    </div>

    <!-- ==================== SISI KANAN: PENDIDIKAN, PENGALAMAN & BERKAS-STR ==================== -->
    <div class="right-column">
        
        <!-- FORM PENDIDIKAN -->
        <div class="card-profil">
            <div class="card-title">
                <span>Riwayat Pendidikan</span>
                <button type="button" class="btn-tambah-header" id="btn-tambah-pendidikan">+ Tambah Jenjang</button>
            </div>
<form action="" method="POST" enctype="multipart/form-data">                <div id="container-pendidikan">
                    <?php if (!empty($list_pendidikan)) : ?>
                        <?php foreach ($list_pendidikan as $index => $pend) : ?>
                            <div class="pendidikan-item">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span style="font-weight: bold; color: #64748b;">Data Pendidikan</span>
                                    <?php if ($index > 0) : ?><button type="button" class="btn-hapus-item" onclick="this.parentNode.parentNode.remove()">Hapus</button><?php endif; ?>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group"><label>Jenjang</label><input type="text" name="jenjang[]" class="form-control" value="<?= htmlspecialchars($pend['jenjang']); ?>" required></div>
                                    <div class="form-group"><label>Nama Institusi</label><input type="text" name="institusi[]" class="form-control" value="<?= htmlspecialchars($pend['institusi']); ?>" required></div>
                                    <div class="form-group"><label>Jurusan</label><input type="text" name="jurusan[]" class="form-control" value="<?= htmlspecialchars($pend['jurusan']); ?>"></div>
                                    <div class="form-group"><label>Tahun Lulus</label><input type="number" name="tahun_lulus[]" class="form-control" value="<?= $pend['tahun_lulus']; ?>" required></div>
                                    <div class="form-group full-width"><label>IPK</label><input type="text" name="ipk[]" class="form-control" value="<?= htmlspecialchars($pend['ipk']); ?>" required></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="pendidikan-item">
                            <div class="form-grid">
                                <div class="form-group"><label>Jenjang Pendidikan</label><input type="text" name="jenjang[]" class="form-control" value="S1 / D4" required></div>
                                <div class="form-group"><label>Nama Institusi</label><input type="text" name="institusi[]" class="form-control" value="Stekom" required></div>
                                <div class="form-group"><label>Jurusan</label><input type="text" name="jurusan[]" class="form-control" value="Teknologi Informatika"></div>
                                <div class="form-group"><label>Tahun Lulus</label><input type="number" name="tahun_lulus[]" class="form-control" value="2026" required></div>
                                <div class="form-group full-width"><label>IPK</label><input type="text" name="ipk[]" class="form-control" value="4.00" required></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                                <button type="submit" name="simpan_pengalaman" class="btn-simpan-full btn-hijau">Simpan Semua Data Pengalaman</button>
            </form>
        </div> <!-- /Akhir card-profil Pengalaman -->

        <!-- ==================== SEGMEN LAYOUT DUA KOLOM HORIZONTAL SEJAJAR ==================== -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start; margin-top: 25px;">
            
                        <!-- FORM BERKAS PELAMAR (SISI KIRI) -->
            <div class="card-profil" style="margin-bottom: 0; min-height: 480px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span>Upload Berkas Pelamar</span>
                        <!-- Tombol Tambah diletakkan di header agar simetris dengan Form STR -->
                        <button type="button" class="btn-tambah-header" id="btn-tambah-berkas" style="background-color: #0d6efd; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer;">+ Tambah Berkas</button>
                    </div>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <!-- Container Utama Form Dinamis -->
                        <div id="container-form-berkas" style="margin-top: 15px; max-height: 280px; overflow-y: auto; padding-right: 5px;">
                            
                            <!-- Baris Item Pertama (Template) -->
                            <div class="item-form-berkas" style="border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 12px; background: #f8fafc;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="label-nomor-berkas" style="font-weight: bold; font-size: 13px; color: #475569;">Berkas #1</span>
                                    <!-- Tombol hapus dinonaktifkan di baris pertama -->
                                    <button type="button" class="btn-hapus-berkas" style="display: none; background: #dc3545; color: white; border: none; padding: 2px 8px; border-radius: 4px; font-size: 11px; cursor: pointer;">Hapus</button>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 8px;">
                                    <label style="font-size: 12px; font-weight: 600;">Jenis Berkas</label>
                                    <select name="jenis_berkas[]" class="form-control" required style="width: 100%; box-sizing: border-box;">
                                        <option value="">-- Pilih Jenis Berkas --</option>
                                        <option value="Ijazah">Ijazah</option>
                                        <option value="Transkrip Nilai">Transkrip Nilai</option>
                                        <option value="SKCK">SKCK</option>
                                        <option value="KTP">KTP / Kartu Identitas</option>
                                        <option value="Sertifikat Pelatihan">Sertifikat Pelatihan</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600;">Pilih File Berkas (PDF/JPG/PNG)</label>
                                    <input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="width: 100%; box-sizing: border-box;">
                                </div>
                            </div>

                        </div>

                        <!-- Status Berkas Unggahan (Ditempatkan di luar container scroll agar tetap terlihat) -->
                        <div style="margin-top: 10px; background: #fafafa; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 6px;">
                            <span style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">Status Berkas Unggahan:</span>
                            <ul style="font-size: 12px; color: #64748b; padding-left: 15px; list-style-type: square; margin: 0;">
                                <li style="margin-bottom: 3px;">Ijazah: <?= isset($daftar_berkas_saved['Ijazah']) ? '<a href="uploads/'.$daftar_berkas_saved['Ijazah'].'" target="_blank" style="color:#198754; font-weight:bold; text-decoration:none;">Tersedia (Lihat)</a>' : '<span style="color:#dc3545;">Belum diunggah</span>'; ?></li>
                                <li style="margin-bottom: 3px;">Transkrip: <?= isset($daftar_berkas_saved['Transkrip Nilai']) ? '<a href="uploads/'.$daftar_berkas_saved['Transkrip Nilai'].'" target="_blank" style="color:#198754; font-weight:bold; text-decoration:none;">Tersedia (Lihat)</a>' : '<span style="color:#dc3545;">Belum diunggah</span>'; ?></li>
                                <li>SKCK: <?= isset($daftar_berkas_saved['SKCK']) ? '<a href="uploads/'.$daftar_berkas_saved['SKCK'].'" target="_blank" style="color:#198754; font-weight:bold; text-decoration:none;">Tersedia (Lihat)</a>' : '<span style="color:#dc3545;">Belum diunggah</span>'; ?></li>
                            </ul>
                        </div>
                </div>
                <div>
                    <button type="submit" name="simpan_berkas" class="btn-simpan-full" style="background-color: #0d6efd; margin-top: 15px; width: 100%;">Unggah Berkas</button>
                    </form>
                </div>
            </div>


            <!-- FORM DATA STR PELAMAR (SISI KANAN) -->
            <div class="card-profil" style="margin-bottom: 0; min-height: 480px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div class="card-title" style="color: #198754; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                        <span>Data STR Pelamar</span>
                        <button type="button" class="btn-tambah-header" id="btn-tambah-str" style="background-color: #198754; color: white; border: none;">+ Tambah STR</button>
                    </div>
                    
<form action="" method="POST" enctype="multipart/form-data">
                        <div id="container-form-str" style="margin-top: 15px;">
                            <?php if (!empty($list_str)) : ?>
                                <?php foreach ($list_str as $index => $str) : ?>
                                    <div class="item-form-str" style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #f8fafc;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <span style="font-weight: bold; color: #475569;">Data STR #<?= ($index + 1); ?></span>
                                            <a href="hapus_str.php?id=<?= $str['id']; ?>" class="btn-hapus-item" style="text-decoration: none; display: inline-block;" onclick="return confirm('Apakah Anda yakin ingin menghapus data STR ini secara permanen?')">Hapus</a>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <label style="font-size:12px; font-weight:600;">Nomor STR</label>
                                            <input type="text" name="nomor_str[]" class="form-control" value="<?= htmlspecialchars($str['nomor_str']); ?>" required>
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group"><label>Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" value="<?= $str['tanggal_terbit']; ?>" required></div>
                                            <div class="form-group"><label>Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" value="<?= $str['tanggal_expired']; ?>" required></div>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <?php if (!empty($str['file_str'])): ?><div style="margin-bottom: 5px;"><a href="uploads/<?= $str['file_str']; ?>" target="_blank" style="font-size: 12px; color: #0d6efd; font-weight: 600;">Lihat Berkas Saat Ini</a></div><?php endif; ?>
                                            <input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="item-form-str" style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #f8fafc;">
                                    <h5 style="margin-bottom: 10px; color: #475569;">Data STR #1</h5>
                                    <div style="margin-bottom: 12px;"><label style="font-size:12px; font-weight:600;">Nomor STR</label><input type="text" name="nomor_str[]" class="form-control" required></div>
                                    <div class="form-grid">
                                        <div class="form-group"><label>Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" required></div>
                                        <div class="form-group"><label>Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" required></div>
                                    </div>
                                    <div style="margin-top: 10px;"><input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
                                </div>
                            <?php endif; ?>
                        </div>
                </div>
                <div>
                    <button type="submit" name="simpan_str" class="btn-simpan-full" style="background-color: #198754; margin-top: 20px;">Simpan Semua Data STR</button>
                    </form>
                </div>
            </div>

        </div> <!-- /Akhir Segmen Layout Grid Berkas & STR -->

    </div> <!-- /Penutup right-column murni -->
</div> <!-- /Penutup main-layout murni -->
</div> <!-- /Penutup container murni -->

<!-- ==================== LOGIKA JAVASCRIPT DINAMIS FORM MULTI ARRAYS ==================== -->
<script>
// 1. Fungsi Tambah Baris Pendidikan Dinamis
document.getElementById('btn-tambah-pendidikan').addEventListener('click', function() {
    var container = document.getElementById('container-pendidikan');
    var itemBaru = document.createElement('div');
    itemBaru.className = 'pendidikan-item';
    itemBaru.innerHTML = `
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="font-weight: bold; color: #64748b;">Data Pendidikan</span>
            <button type="button" class="btn-hapus-item" onclick="this.parentNode.parentNode.remove()">Hapus</button>
        </div>
        <div class="form-grid">
            <div class="form-group"><label>Jenjang</label><input type="text" name="jenjang[]" class="form-control" required></div>
            <div class="form-group"><label>Nama Institusi</label><input type="text" name="institusi[]" class="form-control" required></div>
            <div class="form-group"><label>Jurusan</label><input type="text" name="jurusan[]" class="form-control"></div>
            <div class="form-group"><label>Tahun Lulus</label><input type="number" name="tahun_lulus[]" class="form-control" required></div>
            <div class="form-group full-width"><label>IPK</label><input type="text" name="ipk[]" class="form-control" required></div>
        </div>
    `;
    container.appendChild(itemBaru);
});

// 2. Fungsi Tambah Baris STR Dinamis
document.getElementById('btn-tambah-str').addEventListener('click', function() {
    const container = document.getElementById('container-form-str');
    const jumlahForm = container.getElementsByClassName('item-form-str').length;
    const nomorBaru = jumlahForm + 1;

    const formBaru = document.createElement('div');
    formBaru.className = 'item-form-str';
    formBaru.style = 'border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #f8fafc;';
    formBaru.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="font-weight: bold; color: #475569;">Data STR #${nomorBaru}</span>
            <button type="button" class="btn-hapus-item" onclick="this.parentNode.parentNode.remove(); urutkanUlangNomorSTR();">Hapus</button>
        </div>
        <div style="margin-bottom: 12px;">
            <label style="font-size:12px; font-weight:600;">Nomor STR</label>
            <input type="text" name="nomor_str[]" class="form-control" required>
        </div>
        <div class="form-grid">
            <div class="form-group"><label>Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" required></div>
            <div class="form-group"><label>Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" required></div>
        </div>
        <div style="margin-top: 10px;">
            <input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>
    `;
    container.appendChild(formBaru);
});

// 3. Fungsi Reset/Urutkan Penomoran Judul Form STR jika ada yang dihapus
function urutkanUlangNomorSTR() {
    const items = document.querySelectorAll('#container-form-str .item-form-str');
    items.forEach((item, index) => {
        const header = item.querySelector('span') || item.querySelector('h5');
        if (header) { 
            header.innerText = `Data STR #${index + 1}`; 
        }
    });
}
</script>
<script>
document.getElementById('btn-tambah-berkas').addEventListener('click', function() {
    const container = document.getElementById('container-form-berkas');
    const originalItem = container.querySelector('.item-form-berkas');
    
    // Kloning elemen form pertama
    const newItem = originalItem.cloneNode(true);
    
    // Reset isi input & select berkas baru
    newItem.querySelector('select').value = '';
    newItem.querySelector('input[type="file"]').value = '';
    
    // Tampilkan tombol hapus untuk item baru
    const deleteBtn = newItem.querySelector('.btn-hapus-berkas');
    deleteBtn.style.display = 'inline-block';
    
    // Tambahkan event click untuk menghapus item ini
    deleteBtn.addEventListener('click', function() {
        newItem.remove();
        updateNomorBerkas(); // Perbarui nomor urut setelah dihapus
    });
    
    // Masukkan elemen baru ke dalam container
    container.appendChild(newItem);
    
    // Perbarui penomoran judul berkas
    updateNomorBerkas();
});

// Fungsi pembantu untuk mengurutkan string "Berkas #1", "Berkas #2" secara dinamis
function updateNomorBerkas() {
    const items = document.querySelectorAll('#container-form-berkas .item-form-berkas');
    items.forEach((item, index) => {
        item.querySelector('.label-nomor-berkas').innerText = 'Berkas #' + (index + 1);
    });
}
</script>
</body>
</html>
