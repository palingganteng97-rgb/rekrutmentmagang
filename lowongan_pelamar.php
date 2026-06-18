<?php 
session_start(); 

// 1. PROTEKSI LOGIN PELAMAR
if (!isset($_SESSION['pelamar_logged_in'])) {
    header("Location: login.php");
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

$pelamar_id = $_SESSION['pelamar_id'];

// =========================================================================
// PROSES SIMPAN DINAMIS SESUAI ID LOWONGAN DI DATABASE (100% BEBAS ERROR FOREIGN KEY)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    $tanggal_masuk = date('Y-m-d H:i:s');
    $status_awal   = 'Pending';

    // AMBIL ID PERTAMA YANG TERSEDIA DARI TABEL LOWONGAN (ANTI ERROR NAMA KOLOM)
    $ambil_id_lowongan = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lowongan LIMIT 1");
    if (mysqli_num_rows($ambil_id_lowongan) > 0) {
        $data_low = mysqli_fetch_assoc($ambil_id_lowongan);
        $lowongan_id = $data_low['id'];
    } else {
        $lowongan_id = 1; // Cadangan terakhir jika tabel benar-benar kosong
    }


    // 2. QUERY SIMPAN KE TABEL UTAMA REKRUTMEN_LAMARAN
    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        $lamaran_id_baru = mysqli_insert_id($koneksi);
        
        // 3. INSERT STATUS AWAL KE TABEL LAMARAN_TAHAPAN AGAR MASUK KE ADMIN
        mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, petugas_id, created_at, updated_at) 
                                VALUES ($lamaran_id_baru, 1, '$tanggal_masuk', '$status_awal', 1, '$tanggal_masuk', '$tanggal_masuk')");

        echo "<script>
                alert('✓ Sukses! Lamaran Anda berhasil dikirim ke Admin.');
                window.location.href='lowongan_pelamar.php';
              </script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengirim lamaran: " . mysqli_error($koneksi) . "');</script>";
    }
}

// 4. AMBIL DATA BIODATA PELAMAR
$query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
$data = mysqli_fetch_assoc($query_user);

// 5. AMBIL DATA PENDIDIKAN PELAMAR
$query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id ORDER BY id ASC");
$list_pendidikan = [];
while ($row = mysqli_fetch_assoc($query_pend)) {
    $list_pendidikan[] = $row;
}

// 6. AMBIL DATA PENGALAMAN PELAMAR
$query_exp_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pelamar_pengalaman'");
$data_pengalaman = null;
if (mysqli_num_rows($query_exp_cek) > 0) {
    $query_pengalaman = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id LIMIT 1");
    $data_pengalaman = mysqli_fetch_assoc($query_pengalaman);
}

// 7. VALIDASI KELENGKAPAN DATA UNTUK TOMBOL LAMAR
$data_lengkap = true;
$pesan_error = "";
if (empty($data['nama_lengkap']) || empty($data['nik']) || empty($data['telepon']) || empty($data['alamat']) || empty($data['foto'])) {
    $data_lengkap = false;
    $pesan_error = "Biodata, NIK, atau Foto Profil Anda belum lengkap.";
} elseif (empty($list_pendidikan)) {
    $data_lengkap = false;
    $pesan_error = "Riwayat Pendidikan Anda belum diisi.";
} elseif (!$data_pengalaman || empty($data_pengalaman['perusahaan'])) {
    $data_lengkap = false;
    $pesan_error = "Riwayat Pengalaman kerja Anda belum diisi.";
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
        body { background-color: #f8fafc; color: #334155; padding-bottom: 80px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 20px 10%; background: #fff; border-bottom: 1px solid #eef2f5; }
        .brand { font-size: 18px; font-weight: bold; color: #111; text-decoration: none; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-name { font-size: 14px; font-weight: 600; color: #4f46e5; }
        .btn-action { background-color: #4f46e5; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; border: none; cursor: pointer; display: inline-block; text-align: center; }
        .btn-logout { background-color: #dc3545; color: white; padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .main-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 25px; }
        .grid-lowongan { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .card-lowongan { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; }
        .lowongan-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .lowongan-code { display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 4px; margin-bottom: 15px; }
        .lowongan-desc { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 20px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
        .preview-box { background: white; border-radius: 16px; padding: 30px; max-width: 750px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); position: relative; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .preview-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .section-title { font-size: 15px; font-weight: 700; color: #4f46e5; margin-bottom: 8px; margin-top: 15px; }
        .profile-preview-layout { display: flex; gap: 20px; margin-bottom: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; }
        .profile-photo-area { width: 130px; text-align: center; flex-shrink: 0; }
        .profile-photo-area img { width: 100%; height: auto; border-radius: 8px; border: 2px solid #cbd5e1; object-fit: cover; }
        .info-table { width: 100%; font-size: 13.5px; line-height: 1.6; border-collapse: collapse; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .data-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 13.5px; line-height: 1.6; margin-bottom: 5px; }
        .modal-footer { display: flex; gap: 15px; justify-content: center; margin-top: 35px; padding-bottom: 10px; }
        .btn-batal { background-color: #cbd5e1; color: #334155; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-kirim { background-color: #00b57a; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="#" class="brand">PORTAL KARIR</a>
    <div class="user-info">
        <span class="user-name">Halo, <?php echo htmlspecialchars($_SESSION['pelamar_nama']); ?></span>
        <a href="profil_pelamar.php" class="btn-action">Ubah Profil</a>
        <a href="logout.php" class="btn-logout">Keluar</a>
    </div>
</div>

<div class="main-container">
    <h2 class="page-title">Lowongan Magang Tersedia</h2>
    <div class="grid-lowongan">
        <div class="card-lowongan">
            <div>
                <h4 class="lowongan-title">Pusat Rekrutmen Rumah Sakit</h4>
                <span class="lowongan-code">LWN-5</span>
                <p class="lowongan-desc">Terbuka untuk posisi magang umum di unit administrasi, pelayanan medik, dan teknologi informasi jajaran rumah sakit terintegrasi.</p>
            </div>
            <button type="button" class="btn-action" onclick="bukaPreview('DOKTER UMUM')">Lamar Sekarang</button>
        </div>
        <div class="card-lowongan">
            <div>
                <h4 class="lowongan-title">Asisten Lab Komputer</h4>
                <span class="lowongan-code">LWN-6</span>
                <p class="lowongan-desc">Membantu pengelolaan prasarana laboratorium komputer, penjadwalan praktikum, serta asistensi teknis jaringan.</p>
            </div>
            <button type="button" class="btn-action" onclick="bukaPreview('ASISTEN LAB')">Lamar Sekarang</button>
        </div>
    </div>
</div>

<!-- ==================== MODAL OVERLAY PREVIEW ==================== -->
<div id="modalPreview" class="modal-overlay">
    <div class="preview-box">
        <h3 class="preview-title">Pratinjau Data Konfirmasi Lamaran</h3>

        <h5 class="section-title">A. Biodata Profil Pelamar</h5>
        <div class="profile-preview-layout">
            <div class="profile-photo-area">
                <?php if (!empty($data['foto'])) : ?>
                    <img src="uploads/<?php echo $data['foto']; ?>" alt="Foto Profil">
                <?php else : ?>
                    <div style="width:100%; height:160px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:12px; color:#64748b; border-radius:8px;">Tidak Ada Foto</div>
                <?php endif; ?>
            </div>
            <div style="flex-grow: 1;">
                <table class="info-table">
                    <tr>
                        <td style="width: 140px; color: #64748b;">Nama Lengkap</td>
                        <td style="width: 15px;">:</td>
                        <td><strong><?php echo isset($data['nama_lengkap']) ? htmlspecialchars($data['nama_lengkap']) : '-'; ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">NIK</td>
                        <td>:</td>
                        <td><?php echo isset($data['nik']) ? htmlspecialchars($data['nik']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Tempat, Tgl Lahir</td>
                        <td>:</td>
                        <td><?php echo isset($data['tempat_lahir']) ? htmlspecialchars($data['tempat_lahir']) : '-'; ?>, <?php echo isset($data['tanggal_lahir']) ? date('d/m/Y', strtotime($data['tanggal_lahir'])) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Jenis Kelamin</td>
                        <td>:</td>
                        <td><?php echo isset($data['jenis_kelamin']) ? htmlspecialchars($data['jenis_kelamin']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Agama</td>
                        <td>:</td>
                        <td><?php echo isset($data['agama']) ? htmlspecialchars($data['agama']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Status Hubungan</td>
                        <td>:</td>
                        <td><?php echo isset($data['status_sosial']) ? htmlspecialchars($data['status_sosial']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">No. Telepon / WA</td>
                        <td>:</td>
                        <td><?php echo isset($data['telepon']) ? htmlspecialchars($data['telepon']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Kota & Provinsi</td>
                        <td>:</td>
                        <td><?php echo isset($data['kota']) ? htmlspecialchars($data['kota']) . ", " . htmlspecialchars($data['provinsi']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b;">Alamat Rumah</td>
                        <td>:</td>
                        <td><?php echo isset($data['alamat']) ? htmlspecialchars($data['alamat']) : '-'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- B. RIWAYAT PENDIDIKAN -->
        <h5 class="section-title">B. Riwayat Pendidikan</h5>
        <div class="data-box">
            <?php if (!empty($list_pendidikan)) : ?>
                <?php foreach ($list_pendidikan as $index => $pend) : ?>
                    <strong>Jenjang / Kampus :</strong> <?php echo htmlspecialchars($pend['jenjang']); ?> - <?php echo htmlspecialchars($pend['institusi']); ?><br>
                    <strong>Jurusan / Prodi :</strong> <?php echo htmlspecialchars($pend['jurusan']); ?><br>
                    <strong>Tahun Lulus / IPK :</strong> Lulus Th. <?php echo htmlspecialchars($pend['tahun_lulus']); ?> (IPK: <?php echo htmlspecialchars($pend['ipk']); ?>)<br><br>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color:#dc3545;">Data Pendidikan Kosong</span>
            <?php endif; ?>
        </div>

        <!-- C. RIWAYAT PENGALAMAN KERJA -->
        <h5 class="section-title">C. Riwayat Pengalaman Kerja</h5>
        <div class="data-box">
            <?php if ($data_pengalaman && !empty($data_pengalaman['perusahaan'])) : ?>
                <strong>Nama Perusahaan :</strong> <?php echo htmlspecialchars($data_pengalaman['perusahaan']); ?><br>
                <strong>Jabatan / Posisi :</strong> <?php echo htmlspecialchars($data_pengalaman['jabatan']); ?><br>
                <strong>Periode Kerja :</strong> 
                <?php 
                    $mulai = date('d/m/Y', strtotime($data_pengalaman['mulai_kerja']));
                    $selesai = !empty($data_pengalaman['selesai_kerja']) ? date('d/m/Y', strtotime($data_pengalaman['selesai_kerja'])) : 'Sekarang';
                    echo $mulai . " s/d " . $selesai;
                ?><br>
                <strong>Alasan Keluar :</strong> <?php echo !empty($data_pengalaman['alasan_keluar']) ? htmlspecialchars($data_pengalaman['alasan_keluar']) : '-'; ?>
            <?php else : ?>
                <span style="color:#dc3545;">Data Pengalaman Kosong</span>
            <?php endif; ?>
        </div>

        <!-- D. FORMASI YANG DILAMAR -->
        <h5 class="section-title">D. Formasi yang Dilamar</h5>
        <div class="data-box" style="background-color: #eff6ff; border-color: #bfdbfe;">
            <strong id="textFormasi">-</strong>
        </div>

        <!-- TOMBOL KONFIRMASI FINAL (PROSES DI HALAMAN SAMA) -->
        <form action="" method="POST">
            <input type="hidden" name="nama_formasi" id="inputFormasi">
            <div class="modal-footer">
                <button type="button" class="btn-batal" onclick="tutupPreview()">Batal</button>
                <button type="submit" name="kirim_lamaran_final" class="btn-kirim">✓ YA, DATA SUDAH BENAR & KIRIM LAMARAN</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== LOGIKA JAVASCRIPT VALIDASI ==================== -->
<script>
const isDataLengkap = <?php echo $data_lengkap ? 'true' : 'false'; ?>;
const pesanError = "<?php echo $pesan_error; ?>";

function bukaPreview(namaLowongan) {
    if (!isDataLengkap) {
        alert("⚠️ Pendaftaran Ditolak!\n" + pesanError + "\n\nHarap lengkapi semua data profil Anda terlebih dahulu.");
        window.location.href = "profil_pelamar.php";
    } else {
        document.getElementById('textFormasi').innerText = namaLowongan;
        document.getElementById('inputFormasi').value = namaLowongan;
        document.getElementById('modalPreview').style.display = 'flex';
    }
}

function tutupPreview() {
    document.getElementById('modalPreview').style.display = 'none';
}
</script>

</body>
</html>
