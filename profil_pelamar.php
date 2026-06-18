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

    $jenjang_arr     = $_POST['jenjang'];
    $institusi_arr   = $_POST['institusi'];
    $jurusan_arr     = $_POST['jurusan'];
    $tahun_lulus_arr = $_POST['tahun_lulus'];
    $ipk_arr         = $_POST['ipk'];

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

// 5. LOGIKA PROSES SIMPAN RIWAYAT PENGALAMAN (AUTOPILOT TABLE CREATION)
if (isset($_POST['simpan_pengalaman'])) {
    $perusahaan    = mysqli_real_escape_string($koneksi, $_POST['perusahaan']);
    $jabatan       = mysqli_real_escape_string($koneksi, $_POST['jabatan']);
    $mulai_kerja   = mysqli_real_escape_string($koneksi, $_POST['mulai_kerja']);
    $selesai_kerja = !empty($_POST['selesai_kerja']) ? mysqli_real_escape_string($koneksi, $_POST['selesai_kerja']) : NULL;
    $alasan_keluar = mysqli_real_escape_string($koneksi, $_POST['alasan_keluar']);

    // Pembuatan otomatis tabel pelamar_pengalaman jika tidak ada
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

// 6. QUERY AMBIL DATA UNTUK DITAMPILKAN KEMBALI KE FORM
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data = mysqli_fetch_assoc($query_user);

// Ambil data dari tabel pengalaman jika tabelnya sudah terbentuk
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

$query_pend_aktif = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
$list_pendidikan = [];
while ($row = mysqli_fetch_assoc($query_pend_aktif)) {
    $list_pendidikan[] = $row;
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
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 10%; background: #fff; border-bottom: 1px solid #eef2f5; }
        .brand { font-size: 18px; font-weight: bold; color: #111; text-decoration: none; }
        .btn-kembali { background-color: #64748b; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .container { max-width: 850px; margin: 40px auto; padding: 0 20px; }
        .card-profil { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; }
ff4; border: 1px solid #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 25px; font-size: 14px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #334155; background: #fff; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-simpan-full { width: 100%; padding: 12px; font-size: 16px; font-weight: bold; color: white; background-color: #00b57a; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-simpan-full:hover { background-color: #009463; }
        .d-flex-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .btn-tambah-header { background-color: #0d6efd; color: white; padding: 6px 14px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="#" class="brand">Rekrutmen Magang</a>
    <a href="lowongan_pelamar.php" class="btn-kembali">← Kembali ke Lowongan</a>
</div>

<div class="container">

    <!-- ALERT BANNER NOTIFIKASI -->
    <?php if (!empty($error_message)) : ?>
        <div class="alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($success_message)) : ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <!-- ==================== FORM BIODATA UTAMA ==================== -->
    <div class="card-profil">
        <h2 class="card-title">Profil Biodata</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?php echo isset($data['nama_lengkap']) ? htmlspecialchars($data['nama_lengkap']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" class="form-control" value="<?php echo isset($data['nik']) ? htmlspecialchars($data['nik']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="form-control" value="<?php echo isset($data['tempat_lahir']) ? htmlspecialchars($data['tempat_lahir']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control" value="<?php echo isset($data['tanggal_lahir']) ? $data['tanggal_lahir'] : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-control" required>
                        <option value="Laki-laki" <?php echo (isset($data['jenis_kelamin']) && $data['jenis_kelamin'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="Perempuan" <?php echo (isset($data['jenis_kelamin']) && $data['jenis_kelamin'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Agama</label>
                    <input type="text" name="agama" class="form-control" value="<?php echo isset($data['agama']) ? htmlspecialchars($data['agama']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Status Hubungan</label>
                    <input type="text" name="status_sosial" class="form-control" value="<?php echo isset($data['status_sosial']) ? htmlspecialchars($data['status_sosial']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="telepon" class="form-control" value="<?php echo isset($data['telepon']) ? htmlspecialchars($data['telepon']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Kota</label>
                    <input type="text" name="kota" class="form-control" value="<?php echo isset($data['kota']) ? htmlspecialchars($data['kota']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Provinsi</label>
                    <input type="text" name="provinsi" class="form-control" value="<?php echo isset($data['provinsi']) ? htmlspecialchars($data['provinsi']) : ''; ?>" required>
                </div>
                <div class="form-group full-width">
                    <label>Alamat Lengkap</label>
                    <input type="text" name="alamat" class="form-control" value="<?php echo isset($data['alamat']) ? htmlspecialchars($data['alamat']) : ''; ?>" required>
                </div>
                <div class="form-group full-width">
                    <label>Foto Profil (.jpg, .jpeg, .png)</label>
                    <?php if (!empty($data['foto'])) : ?>
                        <div style="margin-bottom: 10px;">
                            <img src="uploads/<?php echo $data['foto']; ?>" alt="Foto Profil" style="max-width: 120px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="foto" class="form-control">
                </div>
            </div>
            <button type="submit" name="update_profil" class="btn-simpan-full">Perbarui Biodata Profil</button>
        </form>
    </div>

    <!-- ==================== FORM RIWAYAT PENDIDIKAN ==================== -->
    <div class="card-profil">
        <div class="d-flex-header">
            <h2 class="card-title" style="margin-bottom: 0; border: none; padding: 0;">Riwayat Pendidikan</h2>
            <button type="button" class="btn-tambah-header">+ Tambah Jenjang</button>
        </div>
        
        <form action="" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Jenjang Pendidikan</label>
                    <input type="text" name="jenjang[]" class="form-control" value="S1 / D4">
                </div>
                <div class="form-group">
                    <label>Nama Institusi / Kampus</label>
                    <input type="text" name="institusi[]" class="form-control" value="Stekom">
                </div>
                <div class="form-group">
                    <label>Jurusan / Program Studi</label>
                    <input type="text" name="jurusan[]" class="form-control" value="Teknologi Informatika">
                </div>
                <div class="form-group">
                    <label>Tahun Lulus</label>
                    <input type="number" name="tahun_lulus[]" class="form-control" value="2026">
                </div>
                <div class="form-group full-width">
                    <label>IPK / Nilai Rata-rata</label>
                    <input type="text" name="ipk[]" class="form-control" value="4.00">
                </div>
            </div>
            <button type="submit" name="simpan_pendidikan" class="btn-simpan-full">Simpan Semua Data Pendidikan</button>
        </form>
    </div>

    <!-- ==================== FORM BARU: RIWAYAT PENGALAMAN ==================== -->
    <div class="card-profil">
        <div class="d-flex-header">
            <h2 class="card-title" style="margin-bottom: 0; border: none; padding: 0; color: #0d6efd;">Riwayat Pengalaman</h2>
            <button type="button" class="btn-tambah-header">+ Tambah Pengalaman</button>
        </div>
        
        <form action="" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Perusahaan</label>
                    <input type="text" name="perusahaan" class="form-control" placeholder="Contoh: PT Tech Solusi Indonesia" value="<?php echo isset($data['perusahaan']) ? htmlspecialchars($data['perusahaan']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Jabatan</label>
                    <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Staff Administrasi" value="<?php echo isset($data['jabatan']) ? htmlspecialchars($data['jabatan']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Mulai Kerja</label>
                    <input type="date" name="mulai_kerja" class="form-control" value="<?php echo isset($data['mulai_kerja']) ? $data['mulai_kerja'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Selesai Kerja</label>
                    <input type="date" name="selesai_kerja" class="form-control" value="<?php echo isset($data['selesai_kerja']) ? $data['selesai_kerja'] : ''; ?>">
                </div>
                
                <div class="form-group full-width">
                    <label>Alasan Keluar</label>
                    <textarea name="alasan_keluar" class="form-control" rows="4" placeholder="Tuliskan alasan Anda resign atau keluar..."><?php echo isset($data['alasan_keluar']) ? htmlspecialchars($data['alasan_keluar']) : ''; ?></textarea>
                </div>
            </div>
            
            <!-- Tombol Simpan Pengalaman yang Sesuai Tema -->
            <button type="submit" name="simpan_pengalaman" class="btn-simpan-full">Simpan Semua Data Pengalaman</button>
        </form>
    </div>

</div>

</body>
</html>
