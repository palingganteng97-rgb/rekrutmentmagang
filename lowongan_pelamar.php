<?php 
session_start(); 

// 1. KONEKSI DATABASE
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// 2. AMBIL DATA SESSION (PORTAL DAPAT DIAKSES OLEH TAMU)
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

$data = null;
$list_pendidikan = [];

// 3. AMBIL DATA PROFIL & VALIDASI REAL-TIME (HANYA JIKA USER SUDAH LOGIN)
$data_lengkap = false;
$pesan_error  = "Silakan login terlebih dahulu.";

// Inisialisasi array kosong di luar agar halaman tidak error saat diakses Tamu
$lowongan_dilamar = []; 

if ($pelamar_id) {
    // Ambil Biodata
    $query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
    if ($query_user) {
        $data = mysqli_fetch_assoc($query_user);
    }

    // Ambil Riwayat Pendidikan
    $query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
    if ($query_pend) {
        while ($row = mysqli_fetch_assoc($query_pend)) {
            $list_pendidikan[] = $row;
        }
    }
    
    // AMBIL DATA BERKAS PELAMAR UNTUK PREVIEW POP-UP
    $list_berkas = [];
    $query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_bk) {
        while ($row_bk = mysqli_fetch_assoc($query_bk)) {
            $list_berkas[] = $row_bk;
        }
    }

    // AMBIL DATA STR PELAMAR UNTUK PREVIEW POP-UP
    $list_str = [];
    $query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_s) {
        while ($row_s = mysqli_fetch_assoc($query_s)) {
            $list_str[] = $row_s;
        }
    }

    // AMBIL DATA PENGALAMAN KERJA PELAMAR
    $list_pengalaman = [];
    $query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
    if ($query_exp) {
        while ($row_exp = mysqli_fetch_assoc($query_exp)) {
            $list_pengalaman[] = $row_exp;
        }
    }

    // =========================================================================
    // FITUR UTAMA: KUMPULKAN ID LOWONGAN YANG SUDAH PERNAH DILAMAR USER INI
    // =========================================================================
    $query_l_dilamar = mysqli_query($koneksi, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id");
    if ($query_l_dilamar) {
        while ($row_ld = mysqli_fetch_assoc($query_l_dilamar)) {
            $lowongan_dilamar[] = $row_ld['lowongan_id'];
        }
    }
    // =========================================================================

    // Evaluasi Kelengkapan Data utama (Nama, NIK, Alamat, Foto)
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



// 4. PROSES INSERT LAMARAN KE DATABASE (SAAT MODAL DI-SUBMIT)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    if (!$pelamar_id) {
        echo "<script>alert('Anda harus login terlebih dahulu!'); window.location.href='login.php';</script>";
        exit;
    }
    if (!$data_lengkap) {
        echo "<script>alert('Akses ditolak! Data Anda belum lengkap.'); window.location.href='profil_pelamar.php';</script>";
        exit;
    }

    $tanggal_masuk = date('Y-m-d H:i:s');
    $status_awal   = 'Proses'; 
    $lowongan_id   = isset($_POST['lowongan_id']) ? intval($_POST['lowongan_id']) : 0;

    // =========================================================================
    // VALIDASI BACKEND: PROTEKSI AGAR USER TIDAK BISA MELAMAR LOWONGAN YANG SAMA
    // =========================================================================
    $cek_duplikat = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
    if (mysqli_num_rows($cek_duplikat) > 0) {
        echo "<script>
            alert('⚠️ Anda sudah pernah mengirimkan berkas lamaran untuk posisi lowongan ini!');
            window.location.href='rekrutmen_lamaran.php';
        </script>";
        exit;
    }
    // =========================================================================

    // Perintah INSERT utama ke tabel database Anda
    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, status, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$status_awal', '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        $lamaran_id_baru = mysqli_insert_id($koneksi);
        
        // Input histori log pelacakan alur ke lamaran_tahapan
        mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, created_at, updated_at) 
                                VALUES ($lamaran_id_baru, 1, '$tanggal_masuk', '$status_awal', '$tanggal_masuk', '$tanggal_masuk')");

        echo "<script>alert('✓ Sukses! Lamaran Anda berhasil dikirim.'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengirim lamaran: " . mysqli_error($koneksi) . "');</script>";
    }
}
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
        .grid-lowongan { 
            display: grid; 
            /* Mengunci lebar maksimum tiap kartu agar rapi dan pas di layar */
            grid-template-columns: repeat(auto-fill, minmax(300px, 350px)); 
            gap: 25px; 
            justify-content: start; /* Memaksa kartu pertama tetap rata kiri */
            margin-top: 20px;
        }
        .card-lowongan { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 5px; }
        .btn-lamar { width: 100%; background: #4338ca; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.2s; }
        .btn-lamar:hover { background: #3730a3; }
        
        /* Tautan interaktif nama user */
        .link-user-profil { color: #2563eb; text-decoration: none; font-weight: bold; transition: color 0.2s; }
        .link-user-profil:hover { color: #1d4ed8; text-decoration: underline; }
        
        /* Style Jendela Modal */
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
                <a href="login.php" style="background:#2563eb; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Masuk Akun</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <h3 style="color:#1e293b; margin-bottom:25px;">Lowongan Magang Tersedia</h3>
        
<div class="grid-lowongan">
    <?php
    // Mengambil data lowongan magang dari database
    $query_lowongan = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan");
    if (mysqli_num_rows($query_lowongan) > 0) {
        while ($row = mysqli_fetch_assoc($query_lowongan)) {
            // SINKRONISASI HEIDISQL: Menggunakan kolom 'judul_lowongan' dan 'kode_lowongan'
            $nama_tampil = isset($row['judul_lowongan']) ? $row['judul_lowongan'] : 'Lowongan Magang';
            $kode_tampil = isset($row['kode_lowongan']) ? $row['kode_lowongan'] : 'LWN-'.$row['id'];
            $deskripsi   = isset($row['deskripsi']) ? $row['deskripsi'] : '';
            $id_lowongan = $row['id'];
            
            // Cek apakah ID lowongan ini sudah pernah dilamar oleh pelamar yang login
            $sudah_melamar = in_array($id_lowongan, $lowongan_dilamar);
            ?>
            <div class="card-lowongan">
                <!-- JUDUL LOWONGAN -->
                <h3 style="margin: 0; color: #1e293b; font-size: 20px; font-weight: 600;"><?= htmlspecialchars($nama_tampil); ?></h3>
                
                <!-- BADGE KODE -->
                <span class="badge" style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-top: 8px;"><?= htmlspecialchars($kode_tampil); ?></span>
                
                <!-- DESKRIPSI -->
                <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 15px; margin-bottom: 5px;"><?= htmlspecialchars($deskripsi); ?></p>
                
                <?php if ($sudah_melamar) : ?>
    <!-- Tampilan Tombol Jika Sudah Dilamar (Ukurannya dibatasi agar tidak penuh) -->
    <div style="margin-top: 20px;">
        <button type="button" class="btn-lamar" style="background: #e2e8f0; color: #64748b; cursor: not-allowed; width: auto; min-width: 150px; display: inline-block; padding: 10px 20px;" disabled>
            ✔ Sudah Dilamar
        </button>
    </div>
<?php else : ?>
    <!-- Tampilan Tombol Normal Jika Belum Dilamar -->
    <button type="button" class="btn-lamar" onclick="bukaPreview('<?= addslashes(htmlspecialchars($nama_tampil)); ?>', '<?= $id_lowongan; ?>')">
        Lamar Sekarang
    </button>
<?php endif; ?>

            </div>
            <?php
        }
    } else {
        echo "<p style='color:#64748b; text-align: center; width: 100%;'>Belum ada lowongan magang yang tersedia saat ini.</p>";
    }
    ?>
</div>

    <!-- WINDOW MODAL PREVIEW DATA SUPER LENGKAP -->
    <div id="modalPreview" class="modal">
        <div class="modal-content" style="width: 550px; max-width: 95%; text-align: left; padding: 25px; border-radius: 12px; max-height: 85vh; overflow-y: auto;">
            <h3 style="margin-top: 0; color: #1e293b; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Preview Kelengkapan Data</h3>
            
            <p style="color: #64748b; font-size: 13px; margin-bottom: 20px; text-align: center;">Periksa kembali berkas pendaftaran Anda sebelum dikirim untuk posisi:<br><strong id="textFormasi" style="color: #4338ca; font-size: 15px;">-</strong></p>
            
            <!-- BAGIAN 1: BIODATA UTAMA -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; line-height: 1.8;">
                <strong style="color: #4338ca; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">I. Biodata & Pendidikan</strong>
                <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Nama Lengkap</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['nama_lengkap'] ?? '-'); ?></span></div>
                <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">NIK</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['nik'] ?? '-'); ?></span></div>
                <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Jenis Kelamin</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['jenis_kelamin'] ?? '-'); ?></span></div>
                <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Alamat</span><span style="flex: 1; color: #1e293b;">: <?= htmlspecialchars($data['alamat'] ?? '-'); ?></span></div>
                <div style="display: flex;"><span style="width: 130px; font-weight: bold; color: #475569;">Pendidikan</span><span style="flex: 1; color: #1e293b;">: 
                    <?php 
                    if (!empty($list_pendidikan)) {
                        $pend_terakhir = end($list_pendidikan);
                        echo htmlspecialchars($pend_terakhir['jenjang'] ?? $pend_terakhir['tingkat'] ?? '-') . " - " . htmlspecialchars($pend_terakhir['nama_sekolah'] ?? $pend_terakhir['institusi'] ?? '-');
                    } else { echo "-"; }
                    ?>
                </span></div>
            </div>

            <!-- BAGIAN 2: BERKAS DOKUMEN UPLOAD -->
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
                    <span style="color: #dc3545; font-style: italic;">Tidak ada lampiran berkas dokumen yang terunggah.</span>
                <?php endif; ?>
            </div>

            <!-- BAGIAN 3: DATA SURAT TANDA REGISTRASI (STR) -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
                <strong style="color: #d97706; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">III. Data STR Aktif</strong>
                <?php if (!empty($list_str) && !empty($list_str[0]['nomor_str'])) : ?>
                    <?php foreach ($list_str as $str) : ?>
                        <div style="margin-bottom: 5px; color: #1e293b;">
                            • No. STR: <strong><?= htmlspecialchars($str['nomor_str']); ?></strong> <small style="color: #64748b;">(Exp: <?= date('d/m/Y', strtotime($str['tanggal_expired'])); ?>)</small>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <span style="color: #64748b; font-style: italic;">Tidak ada data STR (Opsional / Non-Medis).</span>
                <?php endif; ?>
            </div>
            
                        <!-- BAGIAN 4: RIWAYAT PENGALAMAN KERJA -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
                <strong style="color: #4338ca; display: block; margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">IV. Riwayat Pengalaman Kerja</strong>
                <?php if (!empty($list_pengalaman)) : ?>
                    <ul style="margin: 0; padding-left: 20px; color: #1e293b;">
                        <?php foreach ($list_pengalaman as $exp) : ?>
                            <li style="margin-bottom: 5px;">
                                <strong><?= htmlspecialchars($exp['perusahaan'] ?? $exp['nama_perusahaan'] ?? '-'); ?></strong> 
                                Sebagai <span style="color: #4338ca; font-weight: 500;"><?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? '-'); ?></span> 
                                <small style="color: #64748b;">(<?= htmlspecialchars($exp['tahun_masuk'] ?? ''); ?> - <?= htmlspecialchars($exp['tahun_keluar'] ?? 'Sekarang'); ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <span style="color: #64748b; font-style: italic;">Belum memiliki riwayat pengalaman kerja (Fresh Graduate).</span>
                <?php endif; ?>
            </div>

            <!-- Tombol Aksi Form -->
            <form action="" method="POST" style="display: flex; justify-content: flex-end; gap: 10px;">
                <input type="hidden" id="inputFormasi" name="lowongan_id" value="">
                <button type="button" class="btn-batal" onclick="tutupPreview()" style="margin: 0;">Batal</button>
                <button type="submit" name="kirim_lamaran_final" class="btn-konfirmasi">Kirim Lamaran</button>
            </form>
        </div>
    </div>


<!-- LOGIKA JAVASCRIPT VALIDASI INTERAKTIF -->
<script>
    // Membaca variabel kontrol langsung dari backend PHP
    const isLogin       = <?= $pelamar_id ? 'true' : 'false'; ?>;
    const isDataLengkap = <?= $data_lengkap ? 'true' : 'false'; ?>;
    const pesanError    = "<?= isset($pesan_error) ? addslashes($pesan_error) : ''; ?>";

    function bukaPreview(namaLowongan, idLowongan) {
        if (!isLogin) {
            // Mencegah akses melamar bagi Tamu / Pengguna yang belum login
            alert("🔒 Akses Terkunci!\n\nAnda harus login terlebih dahulu untuk mengajukan lamaran.");
            window.location.href = "login.php";
        } else if (!isDataLengkap) {
            // Mencegah kiriman jika kolom wajib di database masih kosong
            alert("⚠️ Pendaftaran Ditolak!\n" + pesanError + "\n\nHarap lengkapi data profil Anda terlebih dahulu.");
            window.location.href = "profil_pelamar.php";
        } else {
            // Membuka modal konfirmasi pendaftaran
            document.getElementById('textFormasi').innerText = namaLowongan;
            document.getElementById('inputFormasi').value = idLowongan;
            document.getElementById('modalPreview').style.display = 'flex';
        }
    }

    function tutupPreview() {
        // Menutup kembali jendela modal konfirmasi
        document.getElementById('modalPreview').style.display = 'none';
    }
</script>
</body>
</html>
