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
        $query_update = "UPDATE pelamar SET 
            nama_lengkap = '$nama_lengkap', nik = '$nik', tempat_lahir = '$tempat_lahir', 
            tanggal_lahir = '$tanggal_lahir', jenis_kelamin = '$jenis_kelamin', agama = '$agama', 
            telepon = '$telepon', alamat = '$alamat', kota = '$kota', provinsi = '$provinsi', 
            foto = '$nama_foto_baru', updated_at = NOW() 
            WHERE id = $pelamar_id";
            
        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['pelamar_nama'] = $nama_lengkap; 
            $success_message = "Profil biodata Anda berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui data profil: " . mysqli_error($koneksi);
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

// 5. AMBIL DATA TERBARU UNTUK DIRENDERING PADA FORM
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data = mysqli_fetch_assoc($query_user);

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
        .card-title { font-size: 24px; font-weight: 700; color: #1e3a8a; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .alert-error { background-color: #fff5f5; border: 1px solid #fed7d7; color: #c53030; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 25px; font-size: 14px; }
        .alert-success { background-color: #f0fff4; border: 1px solid #c6f6d5; color: #22543d; padding: 12px; border-radius: 6px; text-align: center; margin-bottom: 25px; font-size: 14px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #334155; background: #fff; }
        .form-control:focus { border-color: #2563eb; outline: none; }
        textarea.form-control { resize: vertical; height: 80px; }
        .avatar-section { display: flex; align-items: center; gap: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #cbd5e1; background: #e2e8f0; }
        .btn-simpan { background-color: #2563eb; color: white; border: none; width: 100%; padding: 14px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 15px; }
        .btn-simpan:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="lowongan_pelamar.php" class="brand">PORTAL KARIR</a>
        <a href="lowongan_pelamar.php" class="btn-kembali">← Kembali ke Lowongan</a>
    </nav>

    <div class="container">
        <?php if (!empty($error_message)): ?>
            <div class="alert-error">✕ <?= $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success" id="success-alert">✓ <?= $success_message; ?></div>
        <?php endif; ?>

        <!-- KARTU 1: FORM UTAMA BIODATA PELAMAR -->
        <div class="card-profil">
            <div class="card-title">Lengkapi Profil Pelamar</div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="avatar-section full-width">
                    <?php if(!empty($data['foto']) && file_exists("uploads/".$data['foto'])): ?>
                        <img src="uploads/<?= htmlspecialchars($data['foto']); ?>" class="avatar-preview" alt="Foto">
                    <?php else: ?>
                        <div class="avatar-preview" style="display: flex; align-items: center; justify-content: center; font-size: 12px; color: #64748b;">No Photo</div>
                    <?php endif; ?>
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label>Foto Profil (JPG / PNG)</label>
                        <input type="file" name="foto" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required value="<?= htmlspecialchars($data['nama_lengkap'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                    <label>NIK (Nomor Induk Kependudukan)</label>
                    <input type="text" name="nik" class="form-control" placeholder="16 Digit NIK" required value="<?= htmlspecialchars($data['nik'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="form-control" required value="<?= htmlspecialchars($data['tempat_lahir'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control" required value="<?= htmlspecialchars($data['tanggal_lahir'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-control" required>
                        <option value="">-- Pilih --</option>
                        <option value="Laki-Laki" <?= ($data['jenis_kelamin'] ?? '') == 'Laki-Laki' ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="Perempuan" <?= ($data['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Agama</label>
                    <input type="text" name="agama" class="form-control" placeholder="Contoh: Islam" required value="<?= htmlspecialchars($data['agama'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Nomor Telepon / WA</label>
                    <input type="text" name="telepon" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($data['telepon'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Kota / Kabupaten</label>
                    <input type="text" name="kota" class="form-control" required value="<?= htmlspecialchars($data['kota'] ?? ''); ?>">
                </div>

                <div class="form-group full-width">
                    <label>Provinsi</label>
                    <input type="text" name="provinsi" class="form-control" required value="<?= htmlspecialchars($data['provinsi'] ?? ''); ?>">
                </div>

                <div class="form-group full-width">
                    <label>Alamat Rumah Lengkap</label>
                    <textarea name="alamat" class="form-control" placeholder="Nama Jalan, RT/RW, Dusun..." required><?= htmlspecialchars($data['alamat'] ?? ''); ?></textarea>
                </div>
            </div>

            <button type="submit" name="update_profil" class="btn-simpan">Simpan Perubahan Profil</button>
        </form>
    </div>

    <!-- KARTU 2: MULTI FORM RIWAYAT PENDIDIKAN -->
    <div class="card-profil">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Riwayat Pendidikan</span>
            <button type="button" onclick="tambahFormPendidikan()" style="background-color: #2563eb; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">+ Tambah Jenjang</button>
        </div>
        
        <form method="POST" action="">
            <div id="pendidikan-container">
                <?php 
                if (empty($list_pendidikan)) {
                    $list_pendidikan[] = ['jenjang'=>'', 'institusi'=>'', 'jurusan'=>'', 'tahun_lulus'=>'', 'ipk'=>''];
                }
                foreach ($list_pendidikan as $index => $pend): 
                ?>
                <div class="form-pendidikan-item" style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 20px; background-color: #fafafa; position: relative;">
                    
                    <?php if ($index > 0): ?>
                        <button type="button" onclick="hapusFormPendidikan(this)" style="position: absolute; top: 15px; right: 15px; background-color: #dc2626; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: bold;">Hapus</button>
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Jenjang Pendidikan</label>
                            <select name="jenjang[]" class="form-control" required>
                                <option value="">-- Pilih Jenjang --</option>
                                <option value="SMA/SMK" <?= $pend['jenjang'] == 'SMA/SMK' ? 'selected' : ''; ?>>SMA / SMK</option>
                                <option value="D3" <?= $pend['jenjang'] == 'D3' ? 'selected' : ''; ?>>D3</option>
                                <option value="S1/D4" <?= $pend['jenjang'] == 'S1/D4' ? 'selected' : ''; ?>>S1 / D4</option>
                                <option value="S2" <?= $pend['jenjang'] == 'S2' ? 'selected' : ''; ?>>S2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nama Institusi / Kampus</label>
                            <input type="text" name="institusi[]" class="form-control" placeholder="Contoh: Universitas Diponegoro" required value="<?= htmlspecialchars($pend['institusi']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Jurusan / Program Studi</label>
                            <input type="text" name="jurusan[]" class="form-control" placeholder="Contoh: Teknik Informatika" required value="<?= htmlspecialchars($pend['jurusan']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Tahun Lulus</label>
                            <input type="number" name="tahun_lulus[]" class="form-control" min="1900" max="2100" placeholder="Contoh: 2024" required value="<?= htmlspecialchars($pend['tahun_lulus']); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>IPK / Nilai Rata-rata</label>
                            <input type="text" name="ipk[]" class="form-control" placeholder="Contoh: 3.50" required value="<?= htmlspecialchars($pend['ipk']); ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" name="simpan_pendidikan" class="btn-simpan" style="background-color: #10b981;">Simpan Semua Data Pendidikan</button>
        </form>
    </div>
</div>

<!-- JAVASCRIPT UNTUK ADD/REMOVE MULTI FORM -->
<script>
    function tambahFormPendidikan() {
        const container = document.getElementById('pendidikan-container');
        const formBaru = document.createElement('div');
        formBaru.className = 'form-pendidikan-item';
        formBaru.style = "border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 20px; background-color: #fafafa; position: relative;";
        
        formBaru.innerHTML = `
            <button type="button" onclick="hapusFormPendidikan(this)" style="position: absolute; top: 15px; right: 15px; background-color: #dc2626; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: bold;">Hapus</button>
            <div class="form-grid">
                <div class="form-group">
                    <label>Jenjang Pendidikan</label>
                    <select name="jenjang[]" class="form-control" required>
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="SMA/SMK">SMA / SMK</option>
                        <option value="D3">D3</option>
                        <option value="S1/D4">S1 / D4</option>
                        <option value="S2">S2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Institusi / Kampus</label>
                    <input type="text" name="institusi[]" class="form-control" placeholder="Contoh: Universitas Diponegoro" required>
                </div>
                <div class="form-group">
                    <label>Jurusan / Program Studi</label>
                    <input type="text" name="jurusan[]" class="form-control" placeholder="Contoh: Teknik Informatika" required>
                </div>
                <div class="form-group">
                    <label>Tahun Lulus</label>
                    <input type="number" name="tahun_lulus[]" class="form-control" min="1900" max="2100" placeholder="Contoh: 2024" required>
                </div>
                <div class="form-group full-width">
                    <label>IPK / Nilai Rata-rata</label>
                    <input type="text" name="ipk[]" class="form-control" placeholder="Contoh: 3.50" required>
                </div>
            </div>
        `;
        container.appendChild(formBaru);
    }

    function hapusFormPendidikan(button) {
        button.parentElement.remove();
    }

    // Efek timer otomatis untuk notifikasi sukses (5 detik)
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
