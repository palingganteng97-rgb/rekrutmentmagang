<?php 
session_start(); 

// 1. PROTEKSI HALAMAN
if (!isset($_SESSION['pelamar_logged_in'])) {
    header("Location: lowongan_pelamar.php");
    exit;
}

// 2. KONEKSI DATABASE SERVER
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
    $status_sosial = isset($_POST['status_hubungan']) ? mysqli_real_escape_string($koneksi, $_POST['status_hubungan']) : ''; 
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
            
        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['pelamar_nama'] = $nama_lengkap;
            echo "<script>alert('✓ Profil biodata Anda berhasil diperbarui!'); window.location.href='profil_pelamar.php';</script>";
            exit;
        } else {
            $error_message = "Gagal memperbarui data profil: " . mysqli_error($koneksi);
        }
    }
}


// LOGIKA PROSES SIMPAN RIWAYAT PENGALAMAN (TABEL: pelamar_pengalaman)
if (isset($_POST['simpan_pengalaman'])) {
    $perusahaan    = mysqli_real_escape_string($koneksi, $_POST['perusahaan']);
    $jabatan       = mysqli_real_escape_string($koneksi, $_POST['jabatan']);
    $mulai_kerja   = mysqli_real_escape_string($koneksi, $_POST['mulai_kerja']);
    $selesai_kerja = !empty($_POST['selesai_kerja']) ? mysqli_real_escape_string($koneksi, $_POST['selesai_kerja']) : NULL;
    $alasan_keluar = mysqli_real_escape_string($koneksi, $_POST['alasan_keluar']);

    $cek_data = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id");

    if (mysqli_num_rows($cek_data) > 0) {
        // PERBAIKAN: Menggunakan alasan_keluar (dengan garis bawah)
        $query_exp = "UPDATE pelamar_pengalaman SET 
                        perusahaan = '$perusahaan', 
                        jabatan = '$jabatan', 
                        mulai_kerja = '$mulai_kerja', 
                        selesai_kerja = " . ($selesai_kerja ? "'$selesai_kerja'" : "NULL") . ", 
                        alasan_keluar = '$alasan_keluar',
                        updated_at = NOW() 
                      WHERE pelamar_id = $pelamar_id";
    } else {
        // PERBAIKAN: Menggunakan alasan_keluar (dengan garis bawah)
        $query_exp = "INSERT INTO pelamar_pengalaman (pelamar_id, perusahaan, jabatan, mulai_kerja, selesai_kerja, alasan_keluar, created_at, updated_at) 
                      VALUES ($pelamar_id, '$perusahaan', '$jabatan', '$mulai_kerja', " . ($selesai_kerja ? "'$selesai_kerja'" : "NULL") . ", '$alasan_keluar', NOW(), NOW())";
    }

    if (mysqli_query($koneksi, $query_exp)) {
        echo "<script>alert('✓ Riwayat pengalaman berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal menyimpan pengalaman: " . mysqli_error($koneksi) . "');</script>";
    }
}


// =========================================================================
// 5. LOGIKA PROSES SIMPAN MULTI DATA PENDIDIKAN
// =========================================================================
if (isset($_POST['simpan_pendidikan'])) {
    mysqli_query($koneksi, "DELETE FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");

    $jenjang_arr     = isset($_POST['jenjang']) ? $_POST['jenjang'] : [];
    $institusi_arr   = isset($_POST['institusi']) ? $_POST['institusi'] : [];
    $jurusan_arr     = isset($_POST['jurusan']) ? $_POST['jurusan'] : [];
    $tahun_lulus_arr = isset($_POST['tahun_lulus']) ? $_POST['tahun_lulus'] : [];
    $ipk_arr         = isset($_POST['ipk']) ? $_POST['ipk'] : [];

    for ($i = 0; $i < count($jenjang_arr); $i++) {
        $jenjang     = mysqli_real_escape_string($koneksi, $jenjang_arr[$i]);
        $institusi   = mysqli_real_escape_string($koneksi, $institusi_arr[$i]);
        $jurusan     = mysqli_real_escape_string($koneksi, $jurusan_arr[$i]);
        $tahun_lulus = intval($tahun_lulus_arr[$i]);
        $ipk         = mysqli_real_escape_string($koneksi, $ipk_arr[$i]);

        if (!empty($jenjang) && !empty($institusi)) {
            mysqli_query($koneksi, "INSERT INTO pelamar_pendidikan (pelamar_id, jenjang, institusi, jurusan, tahun_lulus, ipk, created_at, updated_at) 
                           VALUES ($pelamar_id, '$jenjang', '$institusi', '$jurusan', '$tahun_lulus', '$ipk', NOW(), NOW())");
        }
    }
    echo "<script>alert('✓ Riwayat pendidikan berhasil diperbarui!'); window.location.href='profil_pelamar.php';</script>";
    exit;
}

// =========================================================================
// 6. LOGIKA PROSES SIMPAN BERKAS PELAMAR
// =========================================================================
if (isset($_POST['simpan_berkas'])) {
    $jenis_berkas_arr = isset($_POST['jenis_berkas']) ? $_POST['jenis_berkas'] : [];
    
    $berkas_doc_lama = [];
    $query_doc_old = mysqli_query($koneksi, "SELECT nama_file FROM pelamar_berkas WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    if ($query_doc_old) {
        while ($rd = mysqli_fetch_assoc($query_doc_old)) { $berkas_doc_lama[] = $rd['nama_file']; }
    }
    
    mysqli_query($koneksi, "DELETE FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");

    for ($j = 0; $j < count($jenis_berkas_arr); $j++) {
        $jenis_berkas = mysqli_real_escape_string($koneksi, $jenis_berkas_arr[$j]);
        $nama_file_doc = isset($berkas_doc_lama[$j]) ? $berkas_doc_lama[$j] : "";

        if (!empty($jenis_berkas)) {
            if (isset($_FILES['file_berkas']['name'][$j]) && $_FILES['file_berkas']['name'][$j] != '') {
                $ekstensi_doc = pathinfo($_FILES['file_berkas']['name'][$j], PATHINFO_EXTENSION);
                $nama_file_doc = "berkas_" . $pelamar_id . "_" . $j . "_" . time() . "." . $ekstensi_doc;
                if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                move_uploaded_file($_FILES['file_berkas']['tmp_name'][$j], "uploads/" . $nama_file_doc);
            }
            mysqli_query($koneksi, "INSERT INTO pelamar_berkas (pelamar_id, jenis_berkas, nama_file) VALUES ($pelamar_id, '$jenis_berkas', '$nama_file_doc')");
        }
    }
    echo "<script>alert('✓ Seluruh berkas dokumen berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>";
    exit;
}

// =========================================================================
// 7. LOGIKA PROSES SIMPAN DATA STR
// =========================================================================
if (isset($_POST['simpan_str'])) {
    $berkas_str_lama = [];
    $query_str_old = mysqli_query($koneksi, "SELECT file_str FROM pelamar_str WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    if ($query_str_old) {
        while ($rl = mysqli_fetch_assoc($query_str_old)) { $berkas_str_lama[] = $rl['file_str']; }
    }
    
    mysqli_query($koneksi, "DELETE FROM pelamar_str WHERE pelamar_id = $pelamar_id");

    $nomor_str_arr   = isset($_POST['nomor_str']) ? $_POST['nomor_str'] : [];
    $tgl_terbit_arr  = isset($_POST['tanggal_terbit']) ? $_POST['tanggal_terbit'] : [];
    $tgl_expired_arr = isset($_POST['tanggal_expired']) ? $_POST['tanggal_expired'] : [];

    for ($i = 0; $i < count($nomor_str_arr); $i++) {
        $nomor_str   = mysqli_real_escape_string($koneksi, $nomor_str_arr[$i]);
        $tgl_terbit  = mysqli_real_escape_string($koneksi, $tgl_terbit_arr[$i]);
        $tgl_expired = mysqli_real_escape_string($koneksi, $tgl_expired_arr[$i]);
        $nama_file_str = isset($berkas_str_lama[$i]) ? $berkas_str_lama[$i] : "";

        if (!empty($nomor_str)) {
            if (isset($_FILES['file_str']['name'][$i]) && $_FILES['file_str']['name'][$i] != '') {
                $ekstensi = pathinfo($_FILES['file_str']['name'][$i], PATHINFO_EXTENSION);
                $nama_file_str = "str_" . $pelamar_id . "_" . $i . "_" . time() . "." . $ekstensi;
                if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                move_uploaded_file($_FILES['file_str']['tmp_name'][$i], "uploads/" . $nama_file_str);
            }
            mysqli_query($koneksi, "INSERT INTO pelamar_str (pelamar_id, nomor_str, tanggal_terbit, tanggal_expired, file_str) VALUES ($pelamar_id, '$nomor_str', '$tgl_terbit', '$tgl_expired', '$nama_file_str')");
        }
    }
    echo "<script>alert('✓ Semua data STR berhasil diperbarui!'); window.location.href='profil_pelamar.php';</script>";
    exit;
}

// =========================================================================
// 8. QUERY AMBIL KEMBALI SEMUA DATA UNTUK FORM HTML
// =========================================================================
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data = mysqli_fetch_assoc($query_user);

$list_pendidikan = [];
$query_pend_aktif = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
if ($query_pend_aktif) {
    while ($row = mysqli_fetch_assoc($query_pend_aktif)) { $list_pendidikan[] = $row; }
}

$query_exp_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_pengalaman'");
if ($query_exp_cek && mysqli_num_rows($query_exp_cek) > 0) {
    $query_exp_tampil = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id LIMIT 1");
    if ($data_exp = mysqli_fetch_assoc($query_exp_tampil)) {
        $data['perusahaan'] = $data_exp['perusahaan'];
        $data['jabatan'] = $data_exp['jabatan'];
        $data['mulai_kerja'] = $data_exp['mulai_kerja'];
        $data['selesai_kerja'] = $data_exp['selesai_kerja'];
        $data['alasan_keluar'] = $data_exp['alasan keluar'] ?? $data_exp['alasan_keluar'] ?? '';
    }
}

$list_str = [];
$query_str_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_str'");
if ($query_str_cek && mysqli_num_rows($query_str_cek) > 0) {
    $query_str_tampil = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($query_str_tampil)) { $list_str[] = $row; }
}

$list_berkas = [];
$query_berkas_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_berkas'");
if ($query_berkas_cek && mysqli_num_rows($query_berkas_cek) > 0) {
    $query_berkas_tampil = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($query_berkas_tampil)) { $list_berkas[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pelamar Magang</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
        body { background-color: #f8fafc; color: #334155; padding-bottom: 80px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 10%; background: #fff; border-bottom: 1px solid #eef2f5; }
        .brand { font-size: 18px; font-weight: bold; color: #111; text-decoration: none; }
        .main-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
        .card-profil { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; background: #fff; color: #334155; }
        .form-control:focus { border-color: #0d6efd; }
        .btn-simpan-full { width: 100%; background-color: #0d6efd; color: white; border: none; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-simpan-full:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="lowongan_pelamar.php" class="brand">← KEMBALI KE PORTAL LOWONGAN</a>
    <span style="font-size: 14px; font-weight: 600; color: #64748b;">Pengaturan Profil Akun</span>
</div>

<div class="main-container">

    <!-- ==================== KOLOM KIRI (BIODATA & PENGALAMAN) ==================== -->
    <div>
        <!-- KARTU 1: BIODATA -->
        <div class="card-profil">
            <div class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Biodata Profil Pelamar</div>
            <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <div style="text-align: center; margin-bottom: 25px; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                    <label style="display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 10px;">Foto Profil</label>
                    <?php if (!empty($data['foto'])) : ?>
                        <img src="uploads/<?= htmlspecialchars($data['foto']); ?>" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #cbd5e1;">
                    <?php else : ?>
                        <div style="width: 110px; height: 110px; border-radius: 50%; background: #e2e8f0; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px;">Belum Ada Foto</div>
                    <?php endif; ?>
                    <input type="file" name="foto" class="form-control" accept="image/*" style="margin-top: 15px;">
                </div>
                <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data['nama_lengkap'] ?? ''); ?>" required></div>
                <div class="form-group"><label>NIK (Nomor Induk Kependudukan)</label><input type="text" name="nik" class="form-control" value="<?= htmlspecialchars($data['nik'] ?? ''); ?>" required></div>
                <div style="display: flex; gap: 15px;"><div class="form-group" style="flex: 1;"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control" value="<?= htmlspecialchars($data['tempat_lahir'] ?? ''); ?>"></div><div class="form-group" style="flex: 1;"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" value="<?= $data['tanggal_lahir'] ?? ''; ?>"></div></div>
                <div style="display: flex; gap: 15px;"><div class="form-group" style="flex: 1;"><label>Jenis Kelamin</label><select name="jenis_kelamin" class="form-control"><option value="Laki-laki" <?= ($data['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option><option value="Perempuan" <?= ($data['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option></select></div><div class="form-group" style="flex: 1;"><label>Agama</label><input type="text" name="agama" class="form-control" value="<?= htmlspecialchars($data['agama'] ?? ''); ?>"></div></div>
                <div style="display: flex; gap: 15px;"><div class="form-group" style="flex: 1;"><label>Status Hubungan / Sosial</label><input type="text" name="status_hubungan" class="form-control" value="<?= htmlspecialchars($data['status_social'] ?? ''); ?>"></div><div class="form-group" style="flex: 1;"><label>No. Telepon / WA</label><input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($data['telepon'] ?? ''); ?>" required></div></div>
                <div style="display: flex; gap: 15px;"><div class="form-group" style="flex: 1;"><label>Kota</label><input type="text" name="kota" class="form-control" value="<?= htmlspecialchars($data['kota'] ?? ''); ?>"></div><div class="form-group" style="flex: 1;"><label>Provinsi</label><input type="text" name="provinsi" class="form-control" value="<?= htmlspecialchars($data['provinsi'] ?? ''); ?>"></div></div>
                <div class="form-group"><label>Alamat Rumah Lengkap</label><textarea name="alamat" class="form-control" rows="3" style="resize: none;"><?= htmlspecialchars($data['alamat'] ?? ''); ?></textarea></div>
                <button type="submit" name="update_profil" class="btn-simpan-full">Perbarui Biodata Profil</button>
            </form>
        </div>

        <!-- KARTU 2: PENGALAMAN -->
        <div class="card-profil">
            <div class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Riwayat Pengalaman Kerja</div>
            <form action="" method="POST" style="margin-top: 15px;">
                <div class="form-group"><label>Nama Perusahaan / Instansi</label><input type="text" name="perusahaan" class="form-control" value="<?= htmlspecialchars($data['perusahaan'] ?? ''); ?>"></div>
                <div class="form-group"><label>Jabatan / Posisi</label><input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($data['jabatan'] ?? ''); ?>"></div>
                <div style="display: flex; gap: 15px;"><div class="form-group" style="flex: 1;"><label>Mulai Kerja</label><input type="date" name="mulai_kerja" class="form-control" value="<?= $data['mulai_kerja'] ?? ''; ?>"></div><div class="form-group" style="flex: 1;"><label>Selesai Kerja</label><input type="date" name="selesai_kerja" class="form-control" value="<?= $data['selesai_kerja'] ?? ''; ?>"></div></div>
                <div class="form-group"><label>Alasan Keluar</label><textarea name="alasan_keluar" class="form-control" rows="3" style="resize: none;"><?= htmlspecialchars($data['alasan_keluar'] ?? ''); ?></textarea></div>
                <button type="submit" name="simpan_pengalaman" class="btn-simpan-full" style="background-color: #0d6efd; margin-top: 15px;">
                    Simpan Pengalaman Kerja
                </button>
            </form>
        </div>
    </div> <!-- TUTUP KOLOM GRID SEBELAH KIRI -->

    <!-- ==================== KOLOM KANAN (PENDIDIKAN, BERKAS, STR) ==================== -->
    <div>
        <!-- KARTU 3: PENDIDIKAN -->
        <div class="card-profil">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Riwayat Pendidikan</div>
                <button type="button" onclick="tambahBarisPendidikan()" style="background-color: #0d6efd; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Jenjang</button>
            </div>
            <form action="" method="POST" style="margin-top: 15px;">
                <div id="container-pendidikan">
                    <?php 
                    if (empty($list_pendidikan)) { $list_pendidikan[] = ['jenjang' => '', 'institusi' => '', 'jurusan' => '', 'tahun_lulus' => '', 'ipk' => '']; }
                    foreach ($list_pendidikan as $pend) : 
                    ?>
                        <div class="item-pendidikan-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                            <div style="text-align: right;"><button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer;">Hapus</button></div>
                            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
<div class="form-group" style="flex: 1;">
    <label>Jenjang</label>
    <select name="jenjang[]" class="form-control" required>
        <option value="">-- Pilih --</option>
        <option value="SMA/SMK" <?= ($pend['jenjang'] ?? '') == 'SMA/SMK' ? 'selected' : ''; ?>>SMA/SMK</option>
        <option value="D3" <?= ($pend['jenjang'] ?? '') == 'D3' ? 'selected' : ''; ?>>D3</option>
        <option value="D4" <?= ($pend['jenjang'] ?? '') == 'D4' ? 'selected' : ''; ?>>D4</option>
        <option value="S1" <?= ($pend['jenjang'] ?? '') == 'S1' ? 'selected' : ''; ?>>S1</option>
        <option value="S2" <?= ($pend['jenjang'] ?? '') == 'S2' ? 'selected' : ''; ?>>S2</option>
        <option value="S3" <?= ($pend['jenjang'] ?? '') == 'S3' ? 'selected' : ''; ?>>S3</option>
    </select>
</div>
                                <div class="form-group" style="flex: 1;"><label>Institusi</label><input type="text" name="institusi[]" class="form-control" value="<?= htmlspecialchars($pend['institusi'] ?? ''); ?>" required></div>
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <div class="form-group" style="flex: 2;"><label>Jurusan</label><input type="text" name="jurusan[]" class="form-control" value="<?= htmlspecialchars($pend['jurusan'] ?? ''); ?>"></div>
                                <div class="form-group" style="flex: 1;"><label>Tahun</label><input type="number" name="tahun_lulus[]" class="form-control" value="<?= $pend['tahun_lulus'] ?? ''; ?>"></div>
                                <div class="form-group" style="flex: 1;"><label>IPK</label><input type="text" name="ipk[]" class="form-control" value="<?= $pend['ipk'] ?? ''; ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="simpan_pendidikan" class="btn-simpan-full">Simpan Semua Data Pendidikan</button>
            </form>
        </div>

        <!-- KARTU 4: BERKAS -->
        <div class="card-profil">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Upload Berkas Pelamar</div>
                <button type="button" onclick="tambahBarisBerkas()" style="background-color: #198754; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Berkas</button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <div id="container-berkas">
                    <?php 
                    if (empty($list_berkas)) { $list_berkas[] = ['jenis_berkas' => '', 'nama_file' => '']; }
                    foreach ($list_berkas as $bk) : 
                    ?>
                        <div class="item-berkas-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                            <div class="form-group"><label>Nama / Jenis Berkas</label><input type="text" name="jenis_berkas[]" class="form-control" value="<?= htmlspecialchars($bk['jenis_berkas'] ?? ''); ?>" required></div>
                            <div class="form-group"><label>Pilih File</label><input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <div><?= !empty($bk['nama_file']) ? '<a href="uploads/'.$bk['nama_file'].'" target="_blank" style="font-size:12px; color:#198754; font-weight:bold; text-decoration:none;">👁 Lihat Berkas</a>' : '<span style="font-size:12px; color:#64748b; font-style:italic;">Berkas baru</span>'; ?></div>
                                <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold;">Hapus</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="simpan_berkas" class="btn-simpan-full" style="background-color: #198754;">Simpan Berkas Dokumen</button>
            </form>
        </div>

        <!-- KARTU 5: STR -->
        <div class="card-profil">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                <div class="card-title" style="color: #198754; margin-bottom: 0;">Data STR Pelamar</div>
                <button type="button" onclick="tambahBarisSTR()" style="background-color: #198754; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah STR</button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <div id="container-str">
                    <?php 
                    if (empty($list_str)) { $list_str[] = ['nomor_str' => '', 'tanggal_terbit' => '', 'tanggal_expired' => '', 'file_str' => '']; }
                    foreach ($list_str as $i => $str) : 
                    ?>
                        <div class="item-str-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;"><strong style="font-size:12px; color:#475569;">Data STR #<?= $i+1; ?></strong><button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold;">Hapus</button></div>
                            <div class="form-group"><label>Nomor STR</label><input type="text" name="nomor_str[]" class="form-control" value="<?= htmlspecialchars($str['nomor_str'] ?? ''); ?>" required></div>
                            <div style="display:flex; gap:10px;"><div class="form-group" style="flex:1;"><label>Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" value="<?= $str['tanggal_terbit'] ?? ''; ?>" required></div><div class="form-group" style="flex:1;"><label>Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" value="<?= $str['tanggal_expired'] ?? ''; ?>" required></div></div>
                            <div class="form-group"><label>File STR</label><input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><?= !empty($str['file_str']) ? '<a href="uploads/'.$str['file_str'].'" target="_blank" style="font-size:12px; color:#198754; font-weight:bold; text-decoration:none; display:block; margin-top:5px;">👁 Lihat STR</a>' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="simpan_str" class="btn-simpan-full" style="background-color: #198754;">Simpan Semua Data STR</button>
            </form>
        </div>
    </div> <!-- TUTUP KOLOM GRID SEBELAH KANAN -->

</div> <!-- TUTUP MAIN-CONTAINER -->

<!-- ==================== JAVASCRIPT DINAMIS MULTI-FORM ==================== -->
<script>
function tambahBarisPendidikan() {
    const container = document.getElementById('container-pendidikan');
    const divBaru = document.createElement('div');
    divBaru.className = 'item-pendidikan-row';
    divBaru.style.cssText = 'background:#fafafa; border:1px dashed #cbd5e1; padding:15px; border-radius:6px; margin-bottom:12px;';
    
    divBaru.innerHTML = `
        <div style="text-align:right;"><button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer;">Hapus</button></div>
        <div style="display:flex; gap:15px; margin-bottom:10px;">
            <div class="form-group" style="flex:1;">
                <label>Jenjang</label>
                <select name="jenjang[]" class="form-control" required>
                    <option value="">-- Pilih --</option>
                    <option value="SMA/SMK">SMA/SMK</option>
                    <option value="D3">D3</option>
                    <option value="D4">D4</option>
                    <option value="S1">S1</option>
                    <option value="S2">S2</option>
                    <option value="S3">S3</option>
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Institusi</label>
                <input type="text" name="institusi[]" class="form-control" required>
            </div>
        </div>
        <div style="display:flex; gap:15px;">
            <div class="form-group" style="flex:2;"><label>Jurusan</label><input type="text" name="jurusan[]" class="form-control"></div>
            <div class="form-group" style="flex:1;"><label>Tahun</label><input type="number" name="tahun_lulus[]" class="form-control"></div>
            <div class="form-group" style="flex:1;"><label>IPK</label><input type="text" name="ipk[]" class="form-control"></div>
        </div>
    `;
    container.appendChild(divBaru);
}
    function tambahBarisBerkas() {
        const container = document.getElementById('container-berkas');
        const divBaru = document.createElement('div');
        divBaru.className = 'item-berkas-row';
        divBaru.style.cssText = 'background:#fafafa; border:1px dashed #cbd5e1; padding:12px; border-radius:6px; margin-bottom:12px;';
        divBaru.innerHTML = `
            <div class="form-group"><label>Nama / Jenis Berkas</label><input type="text" name="jenis_berkas[]" class="form-control" required></div>
            <div class="form-group"><label>Pilih File</label><input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
            <div style="text-align: right;">
                <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold;">Hapus</button>
            </div>
        `;
        container.appendChild(divBaru);
    }

    function tambahBarisSTR() {
        const container = document.getElementById('container-str');
        const totalBaris = container.getElementsByClassName('item-str-row').length + 1;
        const divBaru = document.createElement('div');
        divBaru.className = 'item-str-row';
        divBaru.style.cssText = 'background:#fafafa; border:1px dashed #cbd5e1; padding:12px; border-radius:6px; margin-bottom:12px;';
        divBaru.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <strong style="font-size:12px; color:#475569;">Data STR #\${totalBaris}</strong>
                <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold;">Hapus</button>
            </div>
            <div class="form-group"><label>Nomor STR</label><input type="text" name="nomor_str[]" class="form-control" required></div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;"><label>Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" required></div>
                <div class="form-group" style="flex:1;"><label>Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" required></div>
            </div>
            <div class="form-group"><label>File STR</label><input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
        `;
        container.appendChild(divBaru);
    }
</script>

</body>
</html>
