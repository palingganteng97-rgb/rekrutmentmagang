<?php 
session_start(); 

// 1. PENGATURAN UTAMA WAKTU
date_default_timezone_set('Asia/Jakarta'); 

// 2. KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 3. AMBIL DATA SESSION USER LOGIN
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

if (!$pelamar_id) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
    exit;
}

// 4. LOGIC BACKEND: PROSES UPDATE BIODATA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profil'])) {
    $nama_lengkap    = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $nik             = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $tempat_lahir    = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir   = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $jenis_kelamin   = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $agama           = mysqli_real_escape_string($koneksi, $_POST['agama']);
    $status_hubungan = mysqli_real_escape_string($koneksi, $_POST['status_hubungan']);
    $no_telepon      = mysqli_real_escape_string($koneksi, $_POST['no_telepon']);
    $kota            = mysqli_real_escape_string($koneksi, $_POST['kota']);
    $provinsi        = mysqli_real_escape_string($koneksi, $_POST['provinsi']);
    $alamat          = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    $query_foto_part = "";
    if (!empty($_FILES['foto']['name'])) {
        $nama_file_foto = time() . "_" . $_FILES['foto']['name'];
        if (move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $nama_file_foto)) {
            $query_foto_part = ", foto = '$nama_file_foto'";
        }
    }

    $query_update = "UPDATE pelamar SET nama_lengkap='$nama_lengkap', nik='$nik', tempat_lahir='$tempat_lahir', tanggal_lahir='$tanggal_lahir', jenis_kelamin='$jenis_kelamin', agama='$agama', status_sosial='$status_hubungan', no_telepon='$no_telepon', kota='$kota', provinsi='$provinsi', alamat='$alamat' $query_foto_part WHERE id = $pelamar_id";
    if (mysqli_query($koneksi, $query_update)) {
        echo "<script>alert('✓ Biodata profil berhasil diperbarui!'); window.location.href='profil_pelamar.php';</script>";
        exit;
    }
}

// 5. LOGIC BACKEND: PROSES SIMPAN DATA STR (SINKRON HEIDISQL)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_str'])) {
    $nomor_str_arr      = $_POST['nomor_str'] ?? [];
    $tgl_terbit_arr     = $_POST['tanggal_terbit'] ?? [];
    $tgl_expired_arr    = $_POST['tanggal_expired'] ?? [];
    $file_str_lama_arr  = $_POST['file_str_lama'] ?? [];
    $sukses_insert      = true;

    mysqli_query($koneksi, "DELETE FROM pelamar_str WHERE pelamar_id = $pelamar_id");

    foreach ($nomor_str_arr as $index => $nomor_str) {
        if (empty(trim($nomor_str))) continue;

        $nomor_clean   = mysqli_real_escape_string($koneksi, $nomor_str);
        $tgl_terbit    = !empty($tgl_terbit_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $tgl_terbit_arr[$index]) . "'" : "NULL";
        $tgl_expired   = !empty($tgl_expired_arr[$index]) ? "'" . mysqli_real_escape_string($koneksi, $tgl_expired_arr[$index]) . "'" : "NULL";
        $nama_file_str = $file_str_lama_arr[$index] ?? '';

        if (isset($_FILES['file_str']['name'][$index]) && !empty($_FILES['file_str']['name'][$index])) {
            $f_ext  = strtolower(pathinfo($_FILES['file_str']['name'][$index], PATHINFO_EXTENSION));
            if (in_array($f_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $nama_file_str = "str_" . $pelamar_id . "_" . time() . "_" . $index . "." . $f_ext;
                move_uploaded_file($_FILES['file_str']['tmp_name'][$index], "uploads/" . $nama_file_str);
            }
        }

        $query_ins_str = "INSERT INTO pelamar_str (pelamar_id, nomor_str, tanggal_terbit, tanggal_expired, file_str, created_at, updated_at) VALUES ($pelamar_id, '$nomor_clean', $tgl_terbit, $tgl_expired, '$nama_file_str', NOW(), NOW())";
        if (!mysqli_query($koneksi, $query_ins_str)) { $sukses_insert = false; }
    }
    if ($sukses_insert) { echo "<script>alert('✓ Data STR berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; exit; }
}

// 6. LOGIC BACKEND: PROSES SIMPAN BERKAS DOKUMEN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_berkas'])) {
    $jenis_berkas_arr     = $_POST['jenis_berkas'] ?? [];
    $file_berkas_lama_arr = $_POST['file_berkas_lama'] ?? [];
    $sukses_berkas        = true;

    mysqli_query($koneksi, "DELETE FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");

    foreach ($jenis_berkas_arr as $index => $jenis_berkas) {
        if (empty(trim($jenis_berkas))) continue;

        $jenis_clean       = mysqli_real_escape_string($koneksi, $jenis_berkas);
        $nama_file_berkas  = $file_berkas_lama_arr[$index] ?? '';

        if (isset($_FILES['file_berkas']['name'][$index]) && !empty($_FILES['file_berkas']['name'][$index])) {
            $b_ext  = strtolower(pathinfo($_FILES['file_berkas']['name'][$index], PATHINFO_EXTENSION));
            if (in_array($b_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $nama_file_berkas = "berkas_" . $pelamar_id . "_" . time() . "_" . $index . "." . $b_ext;
                move_uploaded_file($_FILES['file_berkas']['tmp_name'][$index], "uploads/" . $nama_file_berkas);
            }
        }

        $query_ins_bk = "INSERT INTO pelamar_berkas (pelamar_id, jenis_berkas, nama_file) VALUES ($pelamar_id, '$jenis_clean', '$nama_file_berkas')";
        if (!mysqli_query($koneksi, $query_ins_bk)) { $sukses_berkas = false; }
    }
    if ($sukses_berkas) { echo "<script>alert('✓ Berkas berhasil disimpan!'); window.location.href='profil_pelamar.php';</script>"; exit; }
}

// 7. LOADER DATA SINKRONISASI KE FORM VIEW
$data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id"));

$list_pengalaman = [];
$q_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
while ($r = mysqli_fetch_assoc($q_exp)) { $list_pengalaman[] = $r; }

$list_pendidikan = [];
$q_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_pend)) { $list_pendidikan[] = $r; }

$list_berkas = [];
$q_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_bk)) { $list_berkas[] = $r; }

$list_str = [];
$q_str = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
while ($r = mysqli_fetch_assoc($q_str)) { $list_str[] = $r; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pelamar Magang</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .brand { color: #0d6efd; text-decoration: none; font-weight: bold; }
        .main-container { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; max-width: 1300px; margin: 0 auto; align-items: start; }
        .card-profil { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .form-control:focus { border-color: #0d6efd; outline: none; }
        .btn-simpan-full { width: 100%; background-color: #0d6efd; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-simpan-full:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>

     <div class="navbar">
        <a href="lowongan_pelamar.php" class="brand">&larr; KEMBALI KE PORTAL LOWONGAN</a>
        <span style="font-size: 14px; font-weight: 600; color: #64748b;">Pengaturan Profil Akun</span>
    </div>

    <!-- KONTEN UTAMA (SISTEM GRID 2 KOLOM) -->
    <div class="main-container">
        
        <!-- ==================== KOLOM SEBELAH KIRI ==================== -->
        <div>
            <!-- KARTU 1: BIODATA -->
            <div class="card-profil">
                <div class="card-title" style="color: #0d6efd; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Biodata Profil Pelamar</div>
                <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    
                    <!-- FOTO PROFIL -->
                    <div style="text-align: center; margin-bottom: 25px; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <label style="display: block; font-size: 13px; font-weight: bold; color: #475569; margin-bottom: 10px;">Foto Profil</label>
                        <div class="avatar-wrapper" style="position: relative; width: 110px; height: 110px; margin: 0 auto; cursor: pointer;" onclick="document.getElementById('inputFoto').click();">
                            <?php if (!empty($data['foto'])) : ?>
                                <img id="previewFoto" src="uploads/<?= htmlspecialchars($data['foto']); ?>" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #cbd5e1;">
                            <?php else : ?>
                                <div id="placeholderFoto" style="width: 110px; height: 110px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px; border: 3px solid #cbd5e1;">Belum Ada Foto</div>
                                <img id="previewFoto" src="" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #cbd5e1; display: none;">
                            <?php endif; ?>
                        </div>
                        <small style="display: block; margin-top: 8px; color: #64748b; font-size: 11px;">Klik gambar di atas untuk mengganti foto berkas</small>
                        <input type="file" id="inputFoto" name="foto" accept="image/*" style="display: none;" onchange="bacaGambar(this)">
                    </div>

                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data['nama_lengkap'] ?? ''); ?>" required></div>
                    <div class="form-group"><label>NIK (Nomor Induk Kependudukan)</label><input type="text" name="nik" class="form-control" value="<?= htmlspecialchars($data['nik'] ?? ''); ?>" required></div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control" value="<?= htmlspecialchars($data['tempat_lahir'] ?? ''); ?>"></div>
                        <div class="form-group" style="flex: 1;"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" value="<?= $data['tanggal_lahir'] ?? ''; ?>"></div>
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-control">
                                <option value="Laki-laki" <?= ($data['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($data['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;"><label>Agama</label><input type="text" name="agama" class="form-control" value="<?= htmlspecialchars($data['agama'] ?? ''); ?>"></div>
                    </div>

                    <div style="display: flex; gap: 15px;">
<div class="form-group" style="flex: 1;">
    <label>Status Hubungan / Sosial</label>
    <!-- PERBAIKAN: Ganti name dari status_hubungan menjadi status_sosial -->
    <select name="status_sosial" class="form-control">
        <option value="">-- Pilih Status --</option>
        <option value="Belum Kawin" <?= ($data['status_sosial'] ?? '') == 'Belum Kawin' ? 'selected' : ''; ?>>Belum Kawin</option>
        <option value="Kawin" <?= ($data['status_sosial'] ?? '') == 'Kawin' ? 'selected' : ''; ?>>Kawin</option>
    </select>
</div>

                        <div class="form-group" style="flex: 1;">
                            <label>Nomor Telepon / WhatsApp</label>
                            <input type="tel" name="no_telepon" class="form-control" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($data['no_telepon'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;"><label>Kota</label><input type="text" name="kota" class="form-control" value="<?= htmlspecialchars($data['kota'] ?? ''); ?>"></div>
                        <div class="form-group" style="flex: 1;"><label>Provinsi</label><input type="text" name="provinsi" class="form-control" value="<?= htmlspecialchars($data['provinsi'] ?? ''); ?>"></div>
                    </div>
                    
                    <div class="form-group"><label>Alamat Rumah Lengkap</label><textarea name="alamat" class="form-control" rows="3" style="resize: none;"><?= htmlspecialchars($data['alamat'] ?? ''); ?></textarea></div>
                    <button type="submit" name="update_profil" class="btn-simpan-full">Perbarui Biodata Profil</button>
                </form>
            </div>

            <!-- KARTU 5: DATA STR (PINDAH KE SINI AGAR PAS DAN SEIMBANG) -->
            <div class="card-profil">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                    <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Data Surat Tanda Registrasi (STR)</div>
                    <button type="button" onclick="tambahBarisSTR()" style="background-color: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah STR</button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <div id="container-str">
                        <?php 
                        if (empty($list_str)) { $list_str[] = ['nomor_str' => '', 'tanggal_terbit' => '', 'tanggal_expired' => '', 'file_str' => '']; }
                        foreach ($list_str as $str) : 
                        ?>
                            <div class="item-str-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                                <div style="text-align: right; margin-bottom: 10px;">
                                    <button type="button" onclick="hapusBarisDinamis(this, 'container-str')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button>
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Nomor STR</label>
                                    <input type="text" name="nomor_str[]" class="form-control" value="<?= htmlspecialchars($str['nomor_str'] ?? ''); ?>" required>
                                </div>
                                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                                    <div class="form-group" style="flex: 1;"><label style="font-size: 12px; font-weight: bold; color: #475569;">Tanggal Terbit</label><input type="date" name="tanggal_terbit[]" class="form-control" value="<?= $str['tanggal_terbit'] ?? ''; ?>"></div>
                                    <div class="form-group" style="flex: 1;"><label style="font-size: 12px; font-weight: bold; color: #475569;">Tanggal Expired</label><input type="date" name="tanggal_expired[]" class="form-control" value="<?= $str['tanggal_expired'] ?? ''; ?>"></div>
                                </div>
                                <input type="hidden" name="file_str_lama[]" value="<?= htmlspecialchars($str['file_str'] ?? ''); ?>">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Upload Dokumen STR</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="file" name="file_str[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                                                                                <?php if (!empty($str['file_str'])) : ?>
                                            <a href="uploads/<?= htmlspecialchars($str['file_str']); ?>" target="_blank" style="background-color: #0d6efd; color: white; text-decoration: none; padding: 10px 14px; border-radius: 6px; font-size: 13px; font-weight: bold; white-space: nowrap;">👁 Lihat STR</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="simpan_str" class="btn-simpan-full" style="background-color: #d97706; width: 100%; margin-top: 10px;">Simpan Data STR</button>
                </form>
            </div>

        </div> <!-- PENUTUP KOLOM SEBELAH KIRI -->
        <!-- ==================== KOLOM KANAN: PENGALAMAN, PENDIDIKAN, BERKAS ==================== -->
        <div>
            
            <!-- KARTU 2: PENGALAMAN KERJA -->
            <div class="card-profil">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                    <div class="card-title" style="color: #0d6efd; margin-bottom: 0;">Riwayat Pengalaman Kerja</div>
                    <button type="button" onclick="tambahBarisPengalaman()" style="background-color: #0d6efd; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;">+ Tambah Pengalaman</button>
                </div>
                <form action="" method="POST" style="margin-top: 15px;">
                    <div id="container-pengalaman">
                        <?php 
                        if (empty($list_pengalaman)) { $list_pengalaman[] = ['perusahaan' => '', 'jabatan' => '', 'mulai_kerja' => '', 'selesai_kerja' => '', 'alasan_keluar' => '']; }
                        foreach ($list_pengalaman as $exp) : 
                        ?>
                            <div class="item-pengalaman-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                                <div style="text-align: right; margin-bottom: 10px;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pengalaman')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding:0;">Hapus</button></div>
                                <div class="form-group">
                                    <label>Nama Perusahaan / Instansi</label>
                                    <input type="text" name="perusahaan[]" class="form-control" value="<?= htmlspecialchars($exp['perusahaan'] ?? ''); ?>" placeholder="Contoh: PT Tech Indonesia" required>
                                </div>
                                <div class="form-group">
                                    <label>Jabatan / Posisi</label>
                                    <input type="text" name="jabatan[]" class="form-control" value="<?= htmlspecialchars($exp['jabatan'] ?? ''); ?>" placeholder="Contoh: Staff Administrasi" required>
                                </div>
                                <div style="display: flex; gap: 15px;">
                                    <div class="form-group" style="flex: 1;"><label>Mulai Kerja</label><input type="date" name="mulai_kerja[]" class="form-control" value="<?= $exp['mulai_kerja'] ?? ''; ?>" required></div>
                                    <div class="form-group" style="flex: 1;"><label>Selesai Kerja</label><input type="date" name="selesai_kerja[]" class="form-control" value="<?= $exp['selesai_kerja'] ?? ''; ?>"></div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;"><label>Alasan Keluar</label><textarea name="alasan_keluar[]" class="form-control" rows="2" style="resize:none;" placeholder="Tulis alasan singkat..."><?= htmlspecialchars($exp['alasan_keluar'] ?? ''); ?></textarea></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="simpan_pengalaman" class="btn-simpan-full">Simpan Pengalaman Kerja</button>
                </form>
            </div>
            <!-- KARTU 3: RIWAYAT PENDIDIKAN -->
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
                                <div style="text-align: right;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pendidikan')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer;">Hapus</button></div>
                                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label>Jenjang</label>
                                        <select name="jenjang[]" class="form-control" required>
                                            <option value="">-- Pilih --</option>
                                            <option value="SMA/SMK" <?= ($pend['jenjang'] ?? '') == 'SMA/SMK' ? 'selected' : ''; ?>>SMA/SMK</option>
                                            <option value="D3" <?= ($pend['jenjang'] ?? '') == 'D3' ? 'selected' : ''; ?>>D3</option>
                                            <option value="D4" <?= ($pend['jenjang'] ?? '') == 'D4' ? 'selected' : ''; ?>>D4</option>
                                            <option value="S1" <?= ($pend['jenjang'] ?? '') == 'S1' ? 'selected' : ''; ?>>S1</option>
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
            <!-- KARTU 4: UPLOAD BERKAS -->
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
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Nama / Jenis Berkas</label>
                                    <input type="text" name="jenis_berkas[]" class="form-control" value="<?= htmlspecialchars($bk['jenis_berkas'] ?? ''); ?>" required>
                                </div>
                                
                                <input type="hidden" name="file_berkas_lama[]" value="<?= htmlspecialchars($bk['nama_file'] ?? ''); ?>">
                                
                                <div class="form-group">
                                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Pilih File Baru (Kosongkan jika tidak ingin mengubah)</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                                        
                                        <?php if (!empty($bk['nama_file'])) : ?>
                                            <a href="uploads/<?= htmlspecialchars($bk['nama_file']); ?>" target="_blank" style="background-color: #0d6efd; color: white; text-decoration: none; padding: 10px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; text-align: center; white-space: nowrap; transition: 0.2s;" onmouseover="this.style.backgroundColor='#0b5ed7'" onmouseout="this.style.backgroundColor='#0d6efd'">
                                                👁 Lihat Berkas
                                            </a>
                                        <?php else : ?>
                                            <span style="font-size: 12px; color: #64748b; font-style: italic; white-space: nowrap;">Belum ada file</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="text-align: right; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                                    <button type="button" onclick="hapusBarisDinamis(this, 'container-berkas')" style="background: none; border: none; color: #dc3545; font-size: 12px; cursor: pointer; font-weight: bold; padding: 0;">Hapus</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="simpan_berkas" class="btn-simpan-full" style="background-color: #198754; width: 100%; margin-top: 10px;">Simpan Berkas Dokumen</button>
                </form>
            </div>

        </div> <!-- PENUTUP KOLOM KANAN -->
    </div> <!-- PENUTUP main-container -->
    <!-- ==================== TAHAP 3: LOGIC JAVASCRIPT DINAMIS MULTI-FORM ==================== -->
    <script>
    // 1. Fungsi bantu global untuk menghapus baris form dinamis secara aman
    function hapusBarisDinamis(btn, containerId) {
        const container = document.getElementById(containerId);
        const baris = btn.closest('.item-pengalaman-row, .item-pendidikan-row, .item-berkas-row, .item-str-row');
        
        // Validasi agar menyisakan minimal 1 baris input di layar agar form tidak kosong total
        if (container.children.length > 1) {
            baris.remove();
        } else {
            alert('Minimal harus menyisakan satu baris data pada formulir ini.');
        }
    }

    // 2. Handler Tambah Baris Dinamis: Riwayat Pengalaman Kerja
    function tambahBarisPengalaman() {
        const container = document.getElementById('container-pengalaman');
        const html = `
            <div class="item-pengalaman-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                <div style="text-align: right;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pengalaman')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button></div>
                <div class="form-group"><label style="font-size: 11px; font-weight: bold; color: #475569;">Nama Perusahaan / Instansi</label><input type="text" name="perusahaan[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <div class="form-group"><label style="font-size: 11px; font-weight: bold; color: #475569;">Jabatan / Posisi</label><input type="text" name="jabatan[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Mulai Kerja</label><input type="date" name="mulai_kerja[]" class="form-control" required style="padding:4px 12px; font-size:12px;"></div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Selesai Kerja</label><input type="date" name="selesai_kerja[]" class="form-control" style="padding:4px 12px; font-size:12px;"></div>
                </div>
                <div class="form-group" style="margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Alasan Keluar</label><textarea name="alasan_keluar[]" class="form-control" rows="2" placeholder="Tulis alasan singkat..." style="resize: none; padding:6px 12px; font-size:13px;"></textarea></div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    // 3. Handler Tambah Baris Dinamis: Riwayat Pendidikan
    function tambahBarisPendidikan() {
        const container = document.getElementById('container-pendidikan');
        const html = `
            <div class="item-pendidikan-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                <div style="text-align: right;"><button type="button" onclick="hapusBarisDinamis(this, 'container-pendidikan')" style="background:none; border:none; color:#dc3545; font-size:12px; font-weight:bold; cursor:pointer; padding: 0;">Hapus</button></div>
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label style="font-size: 11px; font-weight: bold; color: #475569;">Jenjang</label>
                        <select name="jenjang[]" class="form-control" required style="padding:6px 12px; font-size:13px;">
                            <option value="">-- Pilih --</option>
                            <option value="SMA/SMK">SMA/SMK</option>
                            <option value="D3">D3</option>
                            <option value="D4">D4</option>
                            <option value="S1">S1</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Institusi</label><input type="text" name="institusi[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                </div>
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 2; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Jurusan</label><input type="text" name="jurusan[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">Tahun</label><input type="number" name="tahun_lulus[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;"><label style="font-size: 11px; font-weight: bold; color: #475569;">IPK</label><input type="text" name="ipk[]" class="form-control" style="padding:6px 12px; font-size:13px;"></div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    // 4. Handler Tambah Baris Dinamis: Upload Berkas Dokumen
    function tambahBarisBerkas() {
        const container = document.getElementById('container-berkas');
        const html = `
            <div class="item-berkas-row" style="background: #fafafa; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                <div class="form-group"><label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Nama / Jenis Berkas</label><input type="text" name="jenis_berkas[]" class="form-control" required style="padding:6px 12px; font-size:13px;"></div>
                <input type="hidden" name="file_berkas_lama[]" value="">
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Pilih File Baru</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="file" name="file_berkas[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="flex: 1;">
                        <span style="font-size: 12px; color: #64748b; font-style: italic; white-space: nowrap;">Berkas baru</span>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                    <button type="button" onclick="hapusBarisDinamis(this, 'container-berkas')" style="background: none; border: none; color: #dc3545; font-size: 12px; cursor: pointer; font-weight: bold; padding: 0;">Hapus</button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    // 5. Handler Preview Unggahan Gambar Foto Profil Instan
    function bacaGambar(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').src = e.target.result;
                document.getElementById('previewFoto').style.display = 'block';
                if(document.getElementById('placeholderFoto')) {
                    document.getElementById('placeholderFoto').style.display = 'none';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

</body>
</html>
