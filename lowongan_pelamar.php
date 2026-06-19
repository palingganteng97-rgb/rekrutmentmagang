<?php 
session_start(); 

// PENGATURAN UTAMA: Mengunci zona waktu agar waktu kirim mengikuti jam laptop Anda (WIB)
date_default_timezone_set('Asia/Jakarta'); 

// 1. KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Menyamakan zona waktu server database MySQL agar ikut terkunci ke WIB (+07:00)
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 2. AMBIL DATA SESSION (PORTAL DAPAT DIAKSES OLEH TAMU)
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

// =========================================================================
// 3. AMBIL DATA PROFIL & VALIDASI REAL-TIME (HANYA JIKA USER SUDAH LOGIN)
// =========================================================================
$data_lengkap     = false;
$pesan_error      = "Silakan login terlebih dahulu.";

// Inisialisasi awal semua array agar halaman bebas dari error saat diakses Tamu
$lowongan_dilamar = []; 
$list_pendidikan  = [];
$list_berkas      = [];
$list_str         = [];
$list_pengalaman  = [];
$data             = null; 

if ($pelamar_id) {
    // A. Ambil Biodata Utama Pelamar
    $query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
    if ($query_user) {
        $data = mysqli_fetch_assoc($query_user);
    }

    // B. Ambil Riwayat Pendidikan Pelamar
    $query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
    if ($query_pend) {
        while ($row = mysqli_fetch_assoc($query_pend)) {
            $list_pendidikan[] = $row;
        }
    }
    
    // C. Ambil Lampiran Berkas Dokumen Upload untuk Preview Pop-Up
    $query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_bk) {
        while ($row_bk = mysqli_fetch_assoc($query_bk)) {
            $list_berkas[] = $row_bk;
        }
    }

    // D. Ambil Data Surat Tanda Registrasi (STR) untuk Preview Pop-Up
    $query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_s) {
        while ($row_s = mysqli_fetch_assoc($query_s)) {
            $list_str[] = $row_s;
        }
    }

    // E. Ambil Riwayat Pengalaman Kerja Pelamar untuk Preview Pop-Up
    $query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
    if ($query_exp) {
        while ($row_exp = mysqli_fetch_assoc($query_exp)) {
            $list_pengalaman[] = $row_exp;
        }
    }

    // F. Kumpulkan ID Lowongan yang Sudah Pernah Dilamar User Ini
    $query_l_dilamar = mysqli_query($koneksi, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id");
    if ($query_l_dilamar) {
        while ($row_ld = mysqli_fetch_assoc($query_l_dilamar)) {
            $lowongan_dilamar[] = $row_ld['lowongan_id'];
        }
    }

    // G. Evaluasi Kelengkapan Data Profil Wajib Sebelum Diizinkan Melamar
    if ($data) {
        $data_lengkap = true;
        $pesan_error  = "";

        if (empty($data['nama_lengkap']) || trim($data['nama_lengkap']) == '' ||
            empty($data['nik']) || trim($data['nik']) == '' ||
            empty($data['alamat']) || trim($data['alamat']) == '' ||
            empty($data['foto']) || trim($data['foto']) == '') {
            
            $data_lengkap = false;
            $pesan_error  = "Biodata, NIK, Alamat, atau Foto Profil Anda belum lengkap.";
        } elseif (empty($list_pendidikan)) {
            $data_lengkap = false;
            $pesan_error  = "Riwayat Pendidikan Anda belum diisi.";
        }
    }
}
// =========================================================================

// 4. PROSES INSERT LAMARAN KE DATABASE (SAAT MODAL DI-SUBMIT)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    if (!$pelamar_id) {
        echo "<script>alert('Anda harus login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
        exit;
    }
    if (!$data_lengkap) {
        echo "<script>alert('Akses ditolak! Data Anda belum lengkap.'); window.location.href='profil_pelamar.php';</script>";
        exit;
    }

    $tanggal_masuk = date('Y-m-d H:i:s'); 
    $status_awal   = 'Proses'; 
    $lowongan_id   = isset($_POST['lowongan_id']) ? intval($_POST['lowongan_id']) : 0;
    $tahap_awal    = 1; 

    // VALIDASI BACKEND: PROTEKSI DUPLIKAT
    $cek_duplikat = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
    if (mysqli_num_rows($cek_duplikat) > 0) {
        echo "<script>alert('⚠️ Anda sudah pernah mengirimkan berkas lamaran untuk posisi lowongan ini!'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    }

    // PERBAIKAN UTAMA: Menyertakan tanggal_lamaran agar ikut terisi variabel $tanggal_masuk
    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, tanggal_lamaran, current_tahapan_id, status, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$tanggal_masuk', $tahap_awal, '$status_awal', '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        $lamaran_id_baru = mysqli_insert_id($koneksi);
        
        // Input histori log ke lamaran_tahapan
        mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, created_at, updated_at) 
                                VALUES ($lamaran_id_baru, 1, '$tanggal_masuk', '$status_awal', '$tanggal_masuk', '$tanggal_masuk')");

        echo "<script>alert('✓ Sukses! Lamaran Anda berhasil dikirim.'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengirim lamaran: " . mysqli_error($koneksi) . "');</script>";
    }
} // << PENUTUP UTAMA INI YANG TADI HILANG/BELUM TERTUTUP
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portal Lowongan Kerja</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 40px auto; }
        
        /* TATA LETAK OTOMATIS: Membatasi lebar kartu & menyusun otomatis ke kanan-bawah dari kiri */
        .grid-lowongan { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 350px)); 
            gap: 25px; 
            justify-content: start; 
            margin-top: 20px;
        }
        
        .card-lowongan { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 5px; }
        
        .btn-lamar { width: 100%; background: #4338ca; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.2s; }
        .btn-lamar:hover { background: #3730a3; }
        
        .link-user-profil { color: #2563eb; text-decoration: none; font-weight: bold; transition: color 0.2s; }
        .link-user-profil:hover { color: #1d4ed8; text-decoration: underline; }
        
        /* Style Modal */
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index: 1000; }
        .modal-content { background:white; padding:30px; border-radius:8px; width:400px; text-align:center; }
        .btn-konfirmasi { background:#198754; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; }
        .btn-batal { background:#6c757d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; margin-right:10px; }
    </style>
</head>
<body>

    <div class="navbar">
        <h2 style="margin:0; color:#1e293b;">PORTAL KARIR</h2>
        <div>
            <?php if ($pelamar_id) : ?>
                <span style="margin-right:15px; color:#475569;">
                    Halo, <a href="profil_pelamar.php" class="link-user-profil" title="Klik untuk mengubah data profil"><?= htmlspecialchars($pelamar_nama); ?></a>
                </span>
                <a href="rekrutmen_lamaran.php" style="background:#198754; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold; margin-right:10px;">Data Lamaran Saya</a>
                <a href="logout_pelamar.php" style="background:#dc2626; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Keluar</a>
            <?php else : ?>
                <a href="login_pelamar.php" style="background:#2563eb; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Masuk Akun</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <h3 style="color:#1e293b; margin-bottom:25px;">Lowongan Magang Tersedia</h3>
        
        <div class="grid-lowongan">
            <?php
            $query_lowongan = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan");
            if (mysqli_num_rows($query_lowongan) > 0) {
                while ($row = mysqli_fetch_assoc($query_lowongan)) {

                            // SINKRONISASI HEIDISQL: Menggunakan judul_lowongan
            $nama_tampil = isset($row['judul_lowongan']) ? $row['judul_lowongan'] : 'Lowongan Magang';
            $kode_tampil = isset($row['kode_lowongan']) ? $row['kode_lowongan'] : 'LWN-'.$row['id'];
            $deskripsi   = isset($row['deskripsi']) ? $row['deskripsi'] : '';
            $id_lowongan = $row['id'];
            
            $sudah_melamar = in_array($id_lowongan, $lowongan_dilamar);
            ?>
            <div class="card-lowongan">
                <h3 style="margin: 0; color: #1e293b; font-size: 20px; font-weight: 600;"><?= htmlspecialchars($nama_tampil); ?></h3>
                <span class="badge"><?= htmlspecialchars($kode_tampil); ?></span>
                <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 15px; margin-bottom: 5px;"><?= htmlspecialchars($deskripsi); ?></p>
                
                <?php if ($sudah_melamar) : ?>
                    <div style="margin-top: 20px;">
                        <button type="button" class="btn-lamar" style="background: #e2e8f0; color: #64748b; cursor: not-allowed; width: auto; min-width: 150px; display: inline-block; padding: 10px 20px; margin: 0;" disabled>
                            ✔ Sudah Dilamar
                        </button>
                    </div>
                <?php else : ?>
                    <button type="button" class="btn-lamar" onclick="bukaPreview('<?= addslashes(htmlspecialchars($nama_tampil)); ?>', '<?= $id_lowongan; ?>')">
                        Lamar Sekarang
                    </button>
                <?php endif; ?>
            </div>
            <?php
        } // Penutup while baris 210
    } else {
        echo "<p style='color:#64748b; text-align: center; width: 100%;'>Belum ada lowongan magang yang tersedia saat ini.</p>";
    }
    ?>
    </div> <!-- Penutup grid-lowongan baris 206 -->
</div> <!-- Penutup container baris 202 -->

<!-- WINDOW MODAL PREVIEW DATA SUPER LENGKAP -->
<div id="modalPreview" class="modal">
    <div class="modal-content" style="width: 550px; max-width: 95%; text-align: left; padding: 25px; border-radius: 12px; max-height: 85vh; overflow-y: auto; margin: auto;">
        <h3 style="margin-top: 0; color: #1e293b; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Preview Kelengkapan Data</h3>
        <p style="color: #64748b; font-size: 13px; margin-bottom: 20px; text-align: center;">Periksa kembali berkas pendaftaran Anda sebelum dikirim untuk posisi:<br><strong id="textFormasi" style="color: #4338ca; font-size: 15px;">-</strong></p>
        
        <!-- I. BIODATA -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; line-height: 1.8;">
            <strong style="color: #4338ca; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">I. Biodata & Pendidikan</strong>
            <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Nama Lengkap</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['nama_lengkap'] ?? '-'); ?></span></div>
            <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">NIK</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['nik'] ?? '-'); ?></span></div>
            <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Alamat</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['alamat'] ?? '-'); ?></span></div>
            <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Pendidikan</span><span style="flex: 1; color: #1e293b;">: 
                <?php 
                if (!empty($list_pendidikan)) {
                    $pend_terakhir = end($list_pendidikan);
                    echo htmlspecialchars($pend_terakhir['jenjang'] ?? '-') . " - " . htmlspecialchars($pend_terakhir['nama_sekolah'] ?? '-');
                } else { echo "-"; }
                ?>
            </span></div>
        </div>

        <!-- II. BERKAS DOKUMEN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; line-height: 1.8;">
            <strong style="color: #198754; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">II. Lampiran Berkas Dokumen</strong>
            <?php if (!empty($list_berkas)) : ?>
                <ul style="margin: 0; padding-left: 20px; color: #1e293b;">
                    <?php foreach ($list_berkas as $bk) : ?>
                        <?php if(!empty($bk['nama_file'])) : ?>
                            <li><?= htmlspecialchars($bk['jenis_berkas']); ?> (<span style="color: #198754; font-weight: 500;">✔ Terunggah</span>)</li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <span style="color: #cbd5e1; font-style: italic;">Tidak ada lampiran berkas dokumen.</span>
            <?php endif; ?>
        </div>

        <!-- III. DATA STR -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; line-height: 1.8;">
            <strong style="color: #d97706; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">III. Data STR Aktif</strong>
            <?php if (!empty($list_str)) : ?>
                <?php foreach ($list_str as $str) : ?>
                    <?php if(!empty($str['nomor_str'])) : ?>
                        <div style="margin-bottom: 5px; color: #1e293b;">• No. STR: <strong><?= htmlspecialchars($str['nomor_str']); ?></strong></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color: #64748b; font-style: italic;">Tidak ada data STR (Non-Medis).</span>
            <?php endif; ?>
        </div>

        <!-- IV. RIWAYAT PENGALAMAN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
            <strong style="color: #4338ca; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">IV. Riwayat Pengalaman Kerja</strong>
            <?php if (!empty($list_pengalaman)) : ?>
                <ul style="margin: 0; padding-left: 20px; color: #1e293b;">
                    <?php foreach ($list_pengalaman as $exp) : ?>
                        <li style="margin-bottom: 5px;">
                            <strong><?= htmlspecialchars($exp['perusahaan'] ?? '-'); ?></strong> sebagai <span style="color: #4338ca; font-weight: 500;"><?= htmlspecialchars($exp['jabatan'] ?? '-'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <span style="color: #64748b; font-style: italic;">Belum memiliki riwayat pengalaman (Fresh Graduate).</span>
            <?php endif; ?>
        </div>
        
        <form action="" method="POST" style="display: flex; justify-content: flex-end; gap: 10px;">
            <input type="hidden" id="inputFormasi" name="lowongan_id" value="">
            <button type="button" class="btn-batal" onclick="tutupPreview()" style="margin: 0;">Batal</button>
            <button type="submit" name="kirim_lamaran_final" class="btn-konfirmasi">Kirim Lamaran</button>
        </form>
    </div>
</div>

<!-- LOGIKA JAVASCRIPT VALIDASI INTERAKTIF -->
<script>
    const isLogin         = <?= $pelamar_id ? 'true' : 'false'; ?>;
    const isDataLengkap   = <?= $data_lengkap ? 'true' : 'false'; ?>;
    const pesanError      = "<?= isset($pesan_error) ? addslashes($pesan_error) : ''; ?>";

    function bukaPreview(namaLowongan, idLowongan) {
        if (!isLogin) {
            alert("🔒 Akses Terkunci!\n\nAnda harus login terlebih dahulu untuk mengajukan lamaran kerja.");
            window.location.href = "login_pelamar.php";
        } else if (!isDataLengkap) {
            alert("⚠️ Pendaftaran Ditolak!\n" + pesanError + "\n\nHarap lengkapi data profil Anda terlebih dahulu.");
            window.location.href = "profil_pelamar.php";
        } else {
            document.getElementById('textFormasi').innerText = namaLowongan;
            document.getElementById('inputFormasi').value = idLowongan;
            document.getElementById('modalPreview').style.display = 'flex';
        }
    }

    function tutupPreview() {
        document.getElementById('modalPreview').style.display = 'none';
    }
</script>
</body>
</html>
